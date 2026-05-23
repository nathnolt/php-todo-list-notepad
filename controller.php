<?php

function handlePostRequest() {
    $post = $_POST;

    if (isset($post['do_auth'])) {
        $u = trim((string)($post['username'] ?? ''));
        $p = (string)($post['password'] ?? '');
        if (strlen($u) < 2) { flash('Username must be at least 2 characters.'); redirect('?'); }
        if (strlen($p) < 4) { flash('Password must be at least 4 characters.'); redirect('?'); }

        $st = db()->prepare('SELECT * FROM user WHERE username=?');
        $st->execute([$u]);
        $row = $st->fetch();

        if ($row) {
            if (password_verify($p, $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$row['id'];
                $_SESSION['username'] = $row['username'];
                flash('Welcome back, ' . $u . '!', 'ok');
                redirect('?');
            } else { flash('Wrong password.'); redirect('?'); }
        } else {
            $st2 = db()->prepare('INSERT INTO user (username,password) VALUES (?,?)');
            $st2->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)db()->lastInsertId();
            $_SESSION['username'] = $u;
            flash('Account created. Welcome!', 'ok');
            redirect('?');
        }
    }

    if (isset($post['do_logout'])) {
        session_destroy();
        redirect('?');
    }

    if (!loggedIn()) redirect('?');
    $uid = uid();

    // --- LIST GROUPS ---
    if (isset($post['add_listgroup'])) {
        $name = trim((string)($post['name'] ?? ''));
        if ($name !== '') {
            $st = db()->prepare("SELECT COALESCE(MAX(ord),0)+1 FROM listgroup WHERE userid=?");
            $st->execute([$uid]);
            db()->prepare('INSERT INTO listgroup (userid,name,ord) VALUES (?,?,?)')->execute([$uid,$name,(int)$st->fetchColumn()]);
        }
        redirect('?lists&action=groups');
    }
    if (isset($post['rename_listgroup'])) {
        db()->prepare('UPDATE listgroup SET name=? WHERE id=? AND userid=?')->execute([trim($post['name']), (int)$post['gid'], $uid]);
        redirect('?lists&action=groups');
    }
    if (isset($post['confirmed_del_listgroup'])) {
        db()->prepare('UPDATE list SET groupid=NULL WHERE groupid=? AND userid=?')->execute([(int)$post['gid'], $uid]);
        db()->prepare('DELETE FROM listgroup WHERE id=? AND userid=?')->execute([(int)$post['gid'], $uid]);
        redirect('?lists&action=groups');
    }

    // --- LISTS ---
    if (isset($post['add_list'])) {
        $gid = $post['groupid'] !== '' ? (int)$post['groupid'] : null;
        $st = db()->prepare("SELECT COALESCE(MAX(ord),0)+1 FROM list WHERE userid=?");
        $st->execute([$uid]);
        db()->prepare('INSERT INTO list (userid,name,groupid,ord) VALUES (?,?,?,?)')->execute([$uid,'New list',$gid,(int)$st->fetchColumn()]);
        redirect('?lists&action=view_list&id=' . db()->lastInsertId());
    }
    if (isset($post['confirmed_del_list'])) {
        db()->prepare('DELETE FROM item WHERE listid=? AND EXISTS(SELECT 1 FROM list WHERE id=? AND userid=?)')->execute([(int)$post['lid'], (int)$post['lid'], $uid]);
        db()->prepare('DELETE FROM list WHERE id=? AND userid=?')->execute([(int)$post['lid'], $uid]);
        redirect('?lists');
    }
    
    if (isset($post['lid'])) {
        $lid = (int)$post['lid'];
        db()->prepare('UPDATE list SET name=?, groupid=? WHERE id=? AND userid=?')->execute([trim($post['list_name']), ($post['groupid'] ?: null), $lid, $uid]);

        $ids = $_POST['item_id'] ?? [];
        $contents = $_POST['item_content'] ?? [];
        $checkedIds = $_POST['item_checked'] ?? [];

        foreach ($ids as $idx => $iid) {
            $content = trim($contents[$idx] ?? '');
            if ($content === '') {
                db()->prepare('DELETE FROM item WHERE id=? AND listid=?')->execute([$iid, $lid]);
            } else {
                $isChecked = in_array($iid, $checkedIds);
                $st = db()->prepare('SELECT checked FROM item WHERE id=?');
                $st->execute([$iid]);
                $wasChecked = $st->fetchColumn();
                $date = $isChecked ? ($wasChecked ? null : date('Y-m-d H:i:s')) : null;
                
                $sql = "UPDATE item SET content=?, checked=?, checkeddate=COALESCE(?, checkeddate) WHERE id=? AND listid=?";
                db()->prepare($sql)->execute([$content, $isChecked ? 1 : 0, $date, $iid, $lid]);
            }
        }
        if (trim($post['new_item'] ?? '') !== '') {
            db()->prepare('INSERT INTO item (content, listid) VALUES (?,?)')->execute([trim($post['new_item']), $lid]);
        }
        redirect(isset($post['save_and_back_list']) ? '?lists' : '?lists&action=view_list&id='.$lid);
    }

    // --- NOTEPAD GROUPS ---
    if (isset($post['add_notepadgroup'])) {
        $st = db()->prepare("SELECT COALESCE(MAX(ord),0)+1 FROM notepadgroup WHERE userid=?");
        $st->execute([$uid]);
        db()->prepare('INSERT INTO notepadgroup (userid,name,ord) VALUES (?,?,?)')->execute([$uid, trim($post['name']), (int)$st->fetchColumn()]);
        redirect('?notepads&action=groups');
    }
    if (isset($post['rename_notepadgroup'])) {
        db()->prepare('UPDATE notepadgroup SET name=? WHERE id=? AND userid=?')->execute([trim($post['name']), (int)$post['ngid'], $uid]);
        redirect('?notepads&action=groups');
    }
    if (isset($post['confirmed_del_notepadgroup'])) {
        db()->prepare('UPDATE notepad SET groupid=NULL WHERE groupid=? AND userid=?')->execute([(int)$post['ngid'], $uid]);
        db()->prepare('DELETE FROM notepadgroup WHERE id=? AND userid=?')->execute([(int)$post['ngid'], $uid]);
        redirect('?notepads&action=groups');
    }

    // --- NOTEPADS ---
    if (isset($post['add_notepad'])) {
        $gid = $post['groupid'] !== '' ? (int)$post['groupid'] : null;
        $st = db()->prepare("SELECT COALESCE(MAX(ord),0)+1 FROM notepad WHERE userid=?");
        $st->execute([$uid]);
        db()->prepare('INSERT INTO notepad (userid,name,pages,groupid,ord) VALUES (?,?,?,?,?)')->execute([$uid,'New notepad',20,$gid,(int)$st->fetchColumn()]);
        $npid = db()->lastInsertId();
        $st = db()->prepare('INSERT INTO page (notepadid,num,content) VALUES (?,?,?)');
        for ($i = 1; $i <= 20; $i++) $st->execute([$npid, $i, '']);
        redirect('?notepads&action=view_notepad&id=' . $npid);
    }
    if (isset($post['confirmed_del_np'])) {
        db()->prepare('DELETE FROM page WHERE notepadid=? AND EXISTS(SELECT 1 FROM notepad WHERE id=? AND userid=?)')->execute([(int)$post['npid'], (int)$post['npid'], $uid]);
        db()->prepare('DELETE FROM notepad WHERE id=? AND userid=?')->execute([(int)$post['npid'], $uid]);
        redirect('?notepads');
    }
    
    if (isset($post['npid'])) {
        $npid = (int)$post['npid'];
        $st = db()->prepare('SELECT pages FROM notepad WHERE id=? AND userid=?');
        $st->execute([$npid, $uid]);
        $oldPages = (int)$st->fetchColumn();
        $newPages = max(1, (int)$post['pages']);

        db()->prepare('UPDATE notepad SET name=?, pages=?, groupid=? WHERE id=? AND userid=?')->execute([trim($post['notepad_name']), $newPages, ($post['groupid'] ?: null), $npid, $uid]);

        foreach (($_POST['page_content'] ?? []) as $num => $content) {
            db()->prepare('UPDATE page SET content=? WHERE notepadid=? AND num=?')->execute([(string)$content, $npid, (int)$num]);
        }

        if ($newPages > $oldPages) {
            $st = db()->prepare('INSERT INTO page (notepadid,num,content) VALUES (?,?,?)');
            for ($i = $oldPages + 1; $i <= $newPages; $i++) $st->execute([$npid, $i, '']);
        } elseif ($newPages < $oldPages) {
            db()->prepare('DELETE FROM page WHERE notepadid=? AND num>?')->execute([$npid, $newPages]);
        }
        redirect(isset($post['save_and_back_np']) ? '?notepads' : '?notepads&action=view_notepad&id='.$npid);
    }

    redirect('?');
}
