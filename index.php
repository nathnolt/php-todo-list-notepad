<?php
// ============================================================
//  CONFIG & BOOTSTRAP
// ============================================================
session_start();

define('DB_PATH', __DIR__ . '/data.sqlite');

function db(): PDO {
    /** @var PDO|null $pdo */
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
    
    $stmt = $pdo->query('PRAGMA user_version');
    $ver = $stmt ? (int)$stmt->fetchColumn() : 0;
    if ($ver < 2) {
        migrate($pdo);
        $pdo->exec('PRAGMA user_version = 2');
    }
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT    NOT NULL UNIQUE,
            password TEXT    NOT NULL
        );

        CREATE TABLE IF NOT EXISTS listgroup (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            userid  INTEGER NOT NULL REFERENCES user(id),
            name    TEXT    NOT NULL,
            ord     INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS list (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            userid   INTEGER NOT NULL REFERENCES user(id),
            name     TEXT    NOT NULL DEFAULT 'New list',
            date     DATETIME NOT NULL DEFAULT (datetime('now')),
            groupid  INTEGER REFERENCES listgroup(id),
            ord      INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS item (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            content     TEXT    NOT NULL,
            listid      INTEGER NOT NULL REFERENCES list(id),
            date        DATETIME NOT NULL DEFAULT (datetime('now')),
            checked     INTEGER NOT NULL DEFAULT 0,
            checkeddate DATETIME
        );

        CREATE TABLE IF NOT EXISTS notepadgroup (
            id     INTEGER PRIMARY KEY AUTOINCREMENT,
            userid INTEGER NOT NULL REFERENCES user(id),
            name   TEXT    NOT NULL,
            ord    INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS notepad (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            userid  INTEGER NOT NULL REFERENCES user(id),
            name    TEXT    NOT NULL DEFAULT 'New notepad',
            date    DATETIME NOT NULL DEFAULT (datetime('now')),
            pages   INTEGER NOT NULL DEFAULT 20,
            groupid INTEGER REFERENCES notepadgroup(id),
            ord     INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS page (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            content   TEXT    NOT NULL DEFAULT '',
            notepadid INTEGER NOT NULL REFERENCES notepad(id),
            num       INTEGER NOT NULL DEFAULT 1
        );
    ");
}

// ============================================================
//  HELPERS
// ============================================================
function uid(): ?int  { 
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null; 
}
function loggedIn(): bool { return uid() !== null; }
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function flash(string $msg, string $type = 'error'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

/**
 * @return array{msg: string, type: string}|null
 */
function getFlash(): ?array {
    /** @var array{msg: string, type: string}|null $f */
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function requireAuth(): void {
    if (!loggedIn()) redirect('?');
}

function fmtDate(string $dt): string {
    $ts = strtotime($dt);
    return date('d M Y', $ts === false ? time() : $ts);
}

// ============================================================
//  ROUTING & CONTEXT METADATA
// ============================================================
$section = 'dashboard';
if (isset($_GET['lists']))    $section = 'lists';
if (isset($_GET['notepads'])) $section = 'notepads';

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
$subId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================================
//  POST HANDLER
// ============================================================
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    db();
    $post = $_POST;

    if (isset($post['do_auth'])) {
        $u = trim((string)($post['username'] ?? ''));
        $p = (string)($post['password'] ?? '');
        if (strlen($u) < 2) { flash('Username must be at least 2 characters.'); redirect('?'); }
        if (strlen($p) < 4) { flash('Password must be at least 4 characters.'); redirect('?'); }

        $st = db()->prepare('SELECT * FROM user WHERE username=?');
        $st->execute([$u]);
        /** @var array{id: int, username: string, password: string}|false $row */
        $row = $st->fetch();

        if ($row) {
            if (password_verify($p, $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$row['id'];
                $_SESSION['username'] = $row['username'];
                flash('Welcome back, ' . $u . '!', 'ok');
                redirect('?');
            } else {
                flash('Wrong password.');
                redirect('?');
            }
        } else {
            $st2 = db()->prepare('INSERT INTO user (username,password) VALUES (?,?)');
            $st2->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)db()->lastInsertId();
            $_SESSION['username'] = $u;
            flash('Account created. Welcome, ' . $u . '!', 'ok');
            redirect('?');
        }
    }

    if (isset($post['do_logout'])) {
        session_destroy();
        redirect('?');
    }

    requireAuth();
    $uid = (int)uid();

    if (isset($post['add_listgroup'])) {
        $name = trim((string)($post['name'] ?? ''));
        if ($name !== '') {
            $stmt = db()->query("SELECT COALESCE(MAX(ord),0)+1 FROM listgroup WHERE userid=$uid");
            $ord = $stmt ? (int)$stmt->fetchColumn() : 1;
            db()->prepare('INSERT INTO listgroup (userid,name,ord) VALUES (?,?,?)')->execute([$uid,$name,$ord]);
        }
        redirect('?lists&action=groups');
    }
    if (isset($post['rename_listgroup'])) {
        $gid  = (int)($post['gid'] ?? 0);
        $name = trim((string)($post['name'] ?? ''));
        if ($name !== '') db()->prepare('UPDATE listgroup SET name=? WHERE id=? AND userid=?')->execute([$name,$gid,$uid]);
        redirect('?lists&action=groups');
    }
    if (isset($post['confirmed_del_listgroup'])) {
        $gid = (int)($post['gid'] ?? 0);
        $own = db()->prepare('SELECT id FROM listgroup WHERE id=? AND userid=?');
        $own->execute([$gid, $uid]);
        if ($own->fetch()) {
            db()->prepare('UPDATE list SET groupid=NULL WHERE groupid=? AND userid=?')->execute([$gid,$uid]);
            db()->prepare('DELETE FROM listgroup WHERE id=?')->execute([$gid]);
        }
        redirect('?lists&action=groups');
    }

    if (isset($post['add_list'])) {
        $gid = (isset($post['groupid']) && $post['groupid'] !== '') ? (int)$post['groupid'] : null;
        $stmt = db()->query("SELECT COALESCE(MAX(ord),0)+1 FROM list WHERE userid=$uid");
        $ord = $stmt ? (int)$stmt->fetchColumn() : 1;
        db()->prepare('INSERT INTO list (userid,name,groupid,ord) VALUES (?,?,?,?)')->execute([$uid,'New list',$gid,$ord]);
        $lid = (int)db()->lastInsertId();
        redirect('?lists&action=view_list&id=' . $lid);
    }
    if (isset($post['confirmed_del_list'])) {
        $lid = (int)($post['lid'] ?? 0);
        $own = db()->prepare('SELECT id FROM list WHERE id=? AND userid=?');
        $own->execute([$lid, $uid]);
        if ($own->fetch()) {
            db()->prepare('DELETE FROM item WHERE listid=?')->execute([$lid]);
            db()->prepare('DELETE FROM list WHERE id=?')->execute([$lid]);
        }
        redirect('?lists');
    }
    
    if (isset($post['lid'])) {
        $lid = (int)$post['lid'];
        $own = db()->prepare('SELECT id FROM list WHERE id=? AND userid=?');
        $own->execute([$lid, $uid]);
        if (!$own->fetch()) redirect('?lists');

        $newName = trim((string)($post['list_name'] ?? ''));
        $groupid = (isset($post['groupid']) && $post['groupid'] !== '') ? (int)$post['groupid'] : null;

        if ($newName !== '') {
            db()->prepare('UPDATE list SET name=?, groupid=? WHERE id=?')->execute([$newName, $groupid, $lid]);
        }

        /** @var array<mixed> $itemIds */
        $itemIds      = isset($post['item_id']) && is_array($post['item_id']) ? $post['item_id'] : [];
        /** @var array<mixed> $itemContents */
        $itemContents = isset($post['item_content']) && is_array($post['item_content']) ? $post['item_content'] : [];
        /** @var array<mixed> $itemChecked */
        $itemChecked  = isset($post['item_checked']) && is_array($post['item_checked']) ? $post['item_checked'] : [];

        foreach ($itemIds as $idx => $iid) {
            $iid     = (int)$iid;
            $content = trim((string)($itemContents[$idx] ?? ''));
            $checked = in_array((string)$iid, array_values($itemChecked), true) ? 1 : 0;
            if ($content === '') {
                db()->prepare('DELETE FROM item WHERE id=? AND listid=?')->execute([$iid, $lid]);
            } else {
                $rowStmt = db()->prepare('SELECT checked, checkeddate FROM item WHERE id=?');
                $rowStmt->execute([$iid]);
                /** @var array{checked: int, checkeddate: string|null}|false $row */
                $row = $rowStmt->fetch();
                $checkeddate = $row ? $row['checkeddate'] : null;
                if ($checked && $row && !$row['checked']) {
                    $checkeddate = date('Y-m-d H:i:s');
                } elseif (!$checked) {
                    $checkeddate = null;
                }
                db()->prepare('UPDATE item SET content=?, checked=?, checkeddate=? WHERE id=? AND listid=?')
                    ->execute([$content, $checked, $checkeddate, $iid, $lid]);
            }
        }

        $newItem = trim((string)($post['new_item'] ?? ''));
        if ($newItem !== '') {
            db()->prepare('INSERT INTO item (content, listid) VALUES (?,?)')->execute([$newItem, $lid]);
        }

        if (isset($post['save_and_back_list'])) {
            redirect('?lists');
        }
        redirect('?lists&action=view_list&id=' . $lid);
    }

    if (isset($post['add_notepadgroup'])) {
        $name = trim((string)($post['name'] ?? ''));
        if ($name !== '') {
            $stmt = db()->query("SELECT COALESCE(MAX(ord),0)+1 FROM notepadgroup WHERE userid=$uid");
            $ord = $stmt ? (int)$stmt->fetchColumn() : 1;
            db()->prepare('INSERT INTO notepadgroup (userid,name,ord) VALUES (?,?,?)')->execute([$uid,$name,$ord]);
        }
        redirect('?notepads&action=groups');
    }
    if (isset($post['rename_notepadgroup'])) {
        $ngid = (int)($post['ngid'] ?? 0);
        $name = trim((string)($post['name'] ?? ''));
        if ($name !== '') db()->prepare('UPDATE notepadgroup SET name=? WHERE id=? AND userid=?')->execute([$name,$ngid,$uid]);
        redirect('?notepads&action=groups');
    }
    if (isset($post['confirmed_del_notepadgroup'])) {
        $ngid = (int)($post['ngid'] ?? 0);
        $own = db()->prepare('SELECT id FROM notepadgroup WHERE id=? AND userid=?');
        $own->execute([$ngid, $uid]);
        if ($own->fetch()) {
            db()->prepare('UPDATE notepad SET groupid=NULL WHERE groupid=? AND userid=?')->execute([$ngid,$uid]);
            db()->prepare('DELETE FROM notepadgroup WHERE id=?')->execute([$ngid]);
        }
        redirect('?notepads&action=groups');
    }

    if (isset($post['add_notepad'])) {
        $ngid = (isset($post['groupid']) && $post['groupid'] !== '') ? (int)$post['groupid'] : null;
        $pages = 20;
        $stmt = db()->query("SELECT COALESCE(MAX(ord),0)+1 FROM notepad WHERE userid=$uid");
        $ord = $stmt ? (int)$stmt->fetchColumn() : 1;
        db()->prepare('INSERT INTO notepad (userid,name,pages,groupid,ord) VALUES (?,?,?,?,?)')->execute([$uid,'New notepad',$pages,$ngid,$ord]);
        $npid = (int)db()->lastInsertId();
        $st = db()->prepare('INSERT INTO page (notepadid,num,content) VALUES (?,?,?)');
        for ($i = 1; $i <= $pages; $i++) $st->execute([$npid, $i, '']);
        redirect('?notepads&action=view_notepad&id=' . $npid);
    }
    if (isset($post['confirmed_del_np'])) {
        $npid = (int)($post['npid'] ?? 0);
        $own = db()->prepare('SELECT id FROM notepad WHERE id=? AND userid=?');
        $own->execute([$npid, $uid]);
        if ($own->fetch()) {
            db()->prepare('DELETE FROM page WHERE notepadid=?')->execute([$npid]);
            db()->prepare('DELETE FROM notepad WHERE id=?')->execute([$npid]);
        }
        redirect('?notepads');
    }
    
    if (isset($post['npid'])) {
        $npid = (int)$post['npid'];
        $own = db()->prepare('SELECT id,pages FROM notepad WHERE id=? AND userid=?');
        $own->execute([$npid, $uid]);
        /** @var array{id: int, pages: int}|false $npRow */
        $npRow = $own->fetch();
        if (!$npRow) redirect('?notepads');

        $newName  = trim((string)($post['notepad_name'] ?? ''));
        $newPages = max(1, (int)($post['pages'] ?? $npRow['pages']));
        $groupid  = (isset($post['groupid']) && $post['groupid'] !== '') ? (int)$post['groupid'] : null;

        if ($newName !== '') {
            db()->prepare('UPDATE notepad SET name=?, pages=?, groupid=? WHERE id=?')->execute([$newName, $newPages, $groupid, $npid]);
        }

        /** @var array<mixed> $pageContents */
        $pageContents = isset($post['page_content']) && is_array($post['page_content']) ? $post['page_content'] : [];
        foreach ($pageContents as $num => $content) {
            $num = (int)$num;
            db()->prepare('UPDATE page SET content=? WHERE notepadid=? AND num=?')->execute([(string)$content, $npid, $num]);
        }

        $stmt = db()->query("SELECT MAX(num) FROM page WHERE notepadid=$npid");
        $existing = $stmt ? (int)$stmt->fetchColumn() : 0;
        if ($newPages > $existing) {
            $st = db()->prepare('INSERT INTO page (notepadid,num,content) VALUES (?,?,?)');
            for ($i = $existing + 1; $i <= $newPages; $i++) $st->execute([$npid, $i, '']);
        } elseif ($newPages < $existing) {
            db()->prepare('DELETE FROM page WHERE notepadid=? AND num>?')->execute([$npid, $newPages]);
        }

        if (isset($post['save_and_back_np'])) {
            redirect('?notepads');
        }
        redirect('?notepads&action=view_notepad&id=' . $npid);
    }

    redirect('?');
}

// ============================================================
//  DATA FETCHERS
// ============================================================
db();

/**
 * @return array<int, array{id: int, name: string, ord: int}>
 */
function getListGroups(): array {
    $st = db()->prepare('SELECT * FROM listgroup WHERE userid=? ORDER BY ord,id');
    $st->execute([uid()]);
    /** @var array<int, array{id: int, name: string, ord: int}> $res */
    $res = $st->fetchAll();
    return $res;
}

/**
 * @return array{id: int, name: string, ord: int}|null
 */
function getListgroup(int $id): ?array {
    $st = db()->prepare('SELECT * FROM listgroup WHERE id=? AND userid=?');
    $st->execute([$id, uid()]);
    /** @var array{id: int, name: string, ord: int}|false $res */
    $res = $st->fetch();
    return $res ?: null;
}

/**
 * @return array<int, array{id: int, name: string, date: string, groupid: int|null, ord: int}>
 */
function getListsByGroup(?int $gid): array {
    if ($gid === null) {
        $st = db()->prepare('SELECT * FROM list WHERE userid=? AND groupid IS NULL ORDER BY ord,id');
        $st->execute([uid()]);
    } else {
        $st = db()->prepare('SELECT * FROM list WHERE userid=? AND groupid=? ORDER BY ord,id');
        $st->execute([uid(), $gid]);
    }
    /** @var array<int, array{id: int, name: string, date: string, groupid: int|null, ord: int}> $res */
    $res = $st->fetchAll();
    return $res;
}

/**
 * @return array{id: int, name: string, date: string, groupid: int|null, ord: int, groupname: string|null}|null
 */
function getList(int $id): ?array {
    $st = db()->prepare('SELECT l.*, g.name AS groupname FROM list l LEFT JOIN listgroup g ON g.id=l.groupid WHERE l.id=? AND l.userid=?');
    $st->execute([$id, uid()]);
    /** @var array{id: int, name: string, date: string, groupid: int|null, ord: int, groupname: string|null}|false $res */
    $res = $st->fetch();
    return $res ?: null;
}

/**
 * @return array<int, array{id: int, content: string, listid: int, checked: int, checkeddate: string|null}>
 */
function getItems(int $listid): array {
    $st = db()->prepare('SELECT * FROM item WHERE listid=? ORDER BY checked ASC, id ASC');
    $st->execute([$listid]);
    /** @var array<int, array{id: int, content: string, listid: int, checked: int, checkeddate: string|null}> $res */
    $res = $st->fetchAll();
    return $res;
}

/**
 * @return array<int, array{id: int, name: string, ord: int}>
 */
function getNotepadGroups(): array {
    $st = db()->prepare('SELECT * FROM notepadgroup WHERE userid=? ORDER BY ord,id');
    $st->execute([uid()]);
    /** @var array<int, array{id: int, name: string, ord: int}> $res */
    $res = $st->fetchAll();
    return $res;
}

/**
 * @return array{id: int, name: string, ord: int}|null
 */
function getNotepadgroup(int $id): ?array {
    $st = db()->prepare('SELECT * FROM notepadgroup WHERE id=? AND userid=?');
    $st->execute([$id, uid()]);
    /** @var array{id: int, name: string, ord: int}|false $res */
    $res = $st->fetch();
    return $res ?: null;
}

/**
 * @return array<int, array{id: int, name: string, date: string, pages: int, groupid: int|null, ord: int}>
 */
function getNotepadsByGroup(?int $gid): array {
    if ($gid === null) {
        $st = db()->prepare('SELECT * FROM notepad WHERE userid=? AND groupid IS NULL ORDER BY ord,id');
        $st->execute([uid()]);
    } else {
        $st = db()->prepare('SELECT * FROM notepad WHERE userid=? AND groupid=? ORDER BY ord,id');
        $st->execute([uid(), $gid]);
    }
    /** @var array<int, array{id: int, name: string, date: string, pages: int, groupid: int|null, ord: int}> $res */
    $res = $st->fetchAll();
    return $res;
}

/**
 * @return array{id: int, name: string, date: string, pages: int, groupid: int|null, ord: int, groupname: string|null}|null
 */
function getNotepad(int $id): ?array {
    $st = db()->prepare('SELECT n.*, g.name AS groupname FROM notepad n LEFT JOIN notepadgroup g ON g.id=n.groupid WHERE n.id=? AND n.userid=?');
    $st->execute([$id, uid()]);
    /** @var array{id: int, name: string, date: string, pages: int, groupid: int|null, ord: int, groupname: string|null}|false $res */
    $res = $st->fetch();
    return $res ?: null;
}

/**
 * @return array<int, array{id: int, num: int, content: string}>
 */
function getAllPages(int $npid): array {
    $st = db()->prepare('SELECT * FROM page WHERE notepadid=? ORDER BY num ASC');
    $st->execute([$npid]);
    /** @var array<int, array{id: int, num: int, content: string}> $res */
    $res = $st->fetchAll();
    return $res;
}

/**
 * @return array{total: int, done: int}
 */
function countItems(int $lid): array {
    $stmt1 = db()->query("SELECT COUNT(*) FROM item WHERE listid=$lid");
    $total = $stmt1 ? (int)$stmt1->fetchColumn() : 0;
    
    $stmt2 = db()->query("SELECT COUNT(*) FROM item WHERE listid=$lid AND checked=1");
    $done  = $stmt2 ? (int)$stmt2->fetchColumn() : 0;
    
    return ['total' => $total, 'done' => $done];
}

function countListsInGroup(int $gid): int {
    $stmt = db()->query("SELECT COUNT(*) FROM list WHERE groupid=$gid");
    return $stmt ? (int)$stmt->fetchColumn() : 0;
}

function countWrittenPages(int $npid): int {
    $stmt = db()->query("SELECT COUNT(*) FROM page WHERE notepadid=$npid AND content != ''");
    return $stmt ? (int)$stmt->fetchColumn() : 0;
}

function countNotepadsInGroup(int $gid): int {
    $stmt = db()->query("SELECT COUNT(*) FROM notepad WHERE groupid=$gid");
    return $stmt ? (int)$stmt->fetchColumn() : 0;
}

// ============================================================
//  RENDER SETUP
// ============================================================
$flash = getFlash();

$isMasterFormView = loggedIn() && (
    ($section === 'lists' && $action === 'view_list') || 
    ($section === 'notepads' && $action === 'view_notepad')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pocketbook</title>
<style>
body, button, input, textarea, select { font-family: Georgia, serif; font-size: 16px; line-height: 1.4; color: #000000; }
body { background: #ffffff; margin: 0; padding: 0 0 40px 0; }
a { color: #000000; text-decoration: underline; }
h1, h2, h3 { margin: 0 0 12px 0; }
.bg-primary { background-color: #b3d1ff; } 
.bg-danger  { background-color: #ffb3b3; } 
.bg-muted   { background-color: #e6e6e6; }
.bg-white   { background-color: #ffffff; }
.wrap { width: 840px; max-width: 100%; margin: 0 auto; padding: 16px 4px 40px 4px; box-sizing: border-box; }
form { padding: 0; margin: 0; }
input[type=text], input[type=password], input[type=number], select { border: 2px solid #e6e6e6; padding: 6px; border-radius: 4px; background: #ffffff; font-size: 16px; }
textarea { width: 100%; max-width: 100%; background-color: #e6e6e6; border: 2px solid #e6e6e6; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 15px; box-sizing: border-box; }
label { font-weight: bold; margin-right: 8px; }
.field { margin-bottom: 12px; }
.mt2 { margin-top: 8px; } 
.mt3 { margin-top: 12px; } 
.mt4 { margin-top: 16px; }
.mb2 { margin-bottom: 8px; } 
.mb4 { margin-bottom: 16px; }
.mr3 { margin-right: 12px; }
.w-full { width: 100%; }
.d-inline { display: inline; }
.d-inline-block { display: inline-block; }
.cl-both { clear: both; }
.fs-base { font-size: 16px; }
.fw-bold { font-weight: bold; }
.btn { display: inline-block; padding: 6px 14px; border: none; border-radius: 4px; color: #000000; text-decoration: none; cursor: pointer; font-family: Georgia, serif; font-size: 16px; margin-right: 6px; vertical-align: middle; box-sizing: border-box; }
.topbar { padding-bottom: 12px; margin-bottom: 16px; border-bottom: 2px dashed #e6e6e6; }
.app-title { font-size: 22px; font-weight: bold; color: #000000; vertical-align: middle; display: inline-block; }
.add-item-wrap input[type=text] { vertical-align: middle; margin-right: 6px; }
.flash { padding: 10px 14px; margin-bottom: 16px; border-radius: 4px; }
.empty-note { margin: 4px 0; font-style: italic; color: #555555; }
.section-bottom { margin-top: 32px; padding-top: 12px; border-top: 2px dashed #e6e6e6; }
ul.dash-list { list-style: none; padding: 0; margin: 0; }
ul.dash-list li { margin-bottom: 12px; }
h2.group-name { font-size: 18px; margin-top: 24px; margin-bottom: 12px; padding-bottom: 4px; border-bottom: 2px dashed #e6e6e6; }
ul.item-list { list-style: none; padding: 0; margin: 0 0 12px 0; }
ul.item-list li { padding: 8px 12px; margin-bottom: 6px; border-radius: 4px; }
ul.item-list li a { font-weight: bold; }
ul.item-list li .meta { font-size: 13px; margin-left: 8px; color: #555555; }
.checklist { list-style: none; padding: 0; margin: 12px 0; }
.checklist li { padding: 6px 0; }
.checklist input[type=checkbox] { margin-right: 10px; vertical-align: middle; }
.checklist input[type=text] { border: none; background: transparent; padding: 2px; width: 80%; border-radius: 0; font-size: 16px; vertical-align: middle; }
.checklist input[type=text].done { text-decoration: line-through; opacity: 0.5; }
.page-header { margin-bottom: 8px; padding: 4px 0; }
.page-label { font-weight: bold; display: inline-block; margin-top: 6px; }
.page-header .btn { float: right; margin-right: 0; }
.page-divider { border: none; border-top: 2px dashed #e6e6e6; margin: 24px 0; clear: both; }
.form-inline-block { display: inline-block; margin-right: 16px; margin-bottom: 12px; vertical-align: middle; }
</style>
</head>
<body>
<div class="wrap">

<?php if ($isMasterFormView): ?>
<form method="POST">
    <?php if ($section === 'lists'): ?>
        <input type="hidden" name="lid" value="<?= $subId ?>">
    <?php else: ?>
        <input type="hidden" name="npid" value="<?= $subId ?>">
    <?php endif; ?>
<?php endif; ?>

<div class="topbar">
    <?php if (loggedIn()): ?>
        <?php if ($isMasterFormView): ?>
            <button type="submit" class="btn bg-primary">save</button>
        <?php endif; ?>

        <?php if (($section === 'lists' || $section === 'notepads') && $action !== 'view_list' && $action !== 'view_notepad' && $action !== 'confirm_delete_list' && $action !== 'confirm_delete_np'): ?>
            <a href="?" class="btn bg-muted">Home</a>
        <?php endif; ?>

        <?php if ($section === 'lists'): ?>
            <?php if ($action === 'groups' || $action === 'confirm_delete_listgroup' || $action === 'confirm_delete_list'): ?>
                <a href="?lists" class="btn bg-muted">Lists</a>
            <?php elseif ($action === 'view_list'): ?>
                <button type="submit" name="save_and_back_list" value="1" class="btn bg-muted">Lists</button>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($section === 'notepads'): ?>
            <?php if ($action === 'groups' || $action === 'confirm_delete_notepadgroup' || $action === 'confirm_delete_np'): ?>
                <a href="?notepads" class="btn bg-muted">Notebooks</a>
            <?php elseif ($action === 'view_notepad'): ?>
                <button type="submit" name="save_and_back_np" value="1" class="btn bg-muted">Notebooks</button>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    
    <span class="app-title">Pocketbook</span>
</div>

<?php if ($flash): ?>
<div class="flash <?= $flash['type'] === 'ok' ? 'bg-primary' : 'bg-danger' ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<?php if (!loggedIn()): ?>
<div class="auth-wrap">
    <h2>Pocketbook</h2>
    <form method="POST">
        <div class="field">
            <label for="un">Username</label>
            <input type="text" id="un" name="username" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="pw">Password</label>
            <input type="password" id="pw" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn bg-primary" name="do_auth" value="1">Sign in / Register</button>
    </form>
    <p class="empty-note">If your username does not exist yet, an account will be created automatically.</p>
</div>

<?php else: ?>
<?php
$uid = (int)uid();

if ($section === 'dashboard'): ?>
<p class="empty-note mt2 mb4">Hello, <?= e(isset($_SESSION['username']) ? (string)$_SESSION['username'] : '') ?>.</p>

<ul class="dash-list">
    <li><a href="?lists" class="btn bg-muted w-full">Todo Lists</a></li>
    <li><a href="?notepads" class="btn bg-muted w-full mt2">Notepads</a></li>
</ul>

<div class="section-bottom">
    <form method="POST">
        <button class="btn bg-danger" name="do_logout" value="1">Sign out</button>
    </form>
</div>

<?php
elseif ($section === 'lists' && $action === 'groups'):
    $groups = getListGroups();
?>
<h2 class="group-name">List groups</h2>

<?php if (empty($groups)): ?>
<p class="empty-note mt2">No groups yet.</p>
<?php else: ?>
<?php foreach ($groups as $g):
    $cnt = countListsInGroup((int)$g['id']);
?>
<div class="fs-base fw-bold mt3"><?= e($g['name']) ?></div>
<div class="empty-note mb2"><?= $cnt ?> list<?= $cnt !== 1 ? 's' : '' ?></div>
<form method="POST" class="d-inline">
    <input type="hidden" name="gid" value="<?= (int)$g['id'] ?>">
    <input type="text" name="name" value="<?= e($g['name']) ?>" required aria-label="Rename group">
    <button class="btn bg-muted" name="rename_listgroup" value="1">Rename</button>
</form>
<a href="?lists&action=confirm_delete_listgroup&id=<?= (int)$g['id'] ?>" class="btn bg-danger">Delete group</a>
<?php endforeach; ?>
<?php endif; ?>

<div class="section-bottom">
    <p class="empty-note mb2">Add a new group:</p>
    <form method="POST">
        <input type="text" name="name" placeholder="Group name" required>
        <button class="btn bg-primary" name="add_listgroup" value="1">Add group</button>
    </form>
</div>

<?php
elseif ($section === 'lists' && $action === 'confirm_delete_listgroup'):
    if (!$subId) redirect('?lists&action=groups');
    $g = getListgroup($subId);
    if (!$g) { flash('Group not found.'); redirect('?lists&action=groups'); }
    $cnt = countListsInGroup($subId);
?>
<h2>Delete group "<?= e($g['name']) ?>"?</h2>
<p>
    Lists in group: <?= $cnt ?><br>
    <?php if ($cnt > 0): ?>
    These lists will become ungrouped, not deleted.<br>
    <?php endif; ?>
    <strong>The group itself cannot be recovered.</strong>
</p>
<form method="POST" class="d-inline">
    <input type="hidden" name="gid" value="<?= $subId ?>">
    <button class="btn bg-danger" name="confirmed_del_listgroup" value="1">Delete group</button>
</form>
<a href="?lists&action=groups" class="btn bg-muted">Back</a>

<?php
elseif ($section === 'lists' && $action !== 'view_list' && $action !== 'confirm_delete_list'):
    $groups = getListGroups();
    $ungrouped = getListsByGroup(null);
?>

<?php foreach ($groups as $g): ?>
<h2 class="group-name"><?= e($g['name']) ?></h2>
<?php $gLists = getListsByGroup((int)$g['id']); ?>
<?php if (empty($gLists)): ?>
<p class="empty-note mb2">No lists in this group yet.</p>
<?php else: ?>
<ul class="item-list">
<?php foreach ($gLists as $l): $c = countItems((int)$l['id']); ?>
<li class="bg-muted">
    <a href="?lists&action=view_list&id=<?= (int)$l['id'] ?>"><?= e($l['name']) ?></a>
    <span class="meta"><?= fmtDate($l['date']) ?> - <?= $c['total'] ?> item<?= $c['total'] !== 1 ? 's' : '' ?></span>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<form method="POST" class="mb4">
    <input type="hidden" name="groupid" value="<?= (int)$g['id'] ?>">
    <button class="btn bg-primary" name="add_list" value="1">New list in <?= e($g['name']) ?></button>
</form>
<?php endforeach; ?>

<?php if (!empty($ungrouped)): ?>
<h2 class="group-name">Ungrouped Lists</h2>
<ul class="item-list">
<?php foreach ($ungrouped as $l): $c = countItems((int)$l['id']); ?>
<li class="bg-muted">
    <a href="?lists&action=view_list&id=<?= (int)$l['id'] ?>"><?= e($l['name']) ?></a>
    <span class="meta"><?= fmtDate($l['date']) ?> - <?= $c['total'] ?> item<?= $c['total'] !== 1 ? 's' : '' ?></span>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<div class="section-bottom">
    <form method="POST" class="d-inline-block mr3">
        <input type="hidden" name="groupid" value="">
        <button class="btn bg-primary" name="add_list" value="1">Create Loose List</button>
    </form>
    <a href="?lists&action=groups" class="btn bg-muted">Manage groups</a>
</div>

<?php
elseif ($section === 'lists' && $action === 'confirm_delete_list'):
    if (!$subId) redirect('?lists');
    $list = getList($subId);
    if (!$list) { flash('List not found.'); redirect('?lists'); }
    $c = countItems($subId);
?>
<h2>Delete list "<?= e($list['name']) ?>"?</h2>
<p>
    Group: <?= e($list['groupname'] ?? 'none') ?><br>
    Created: <?= fmtDate($list['date']) ?><br>
    Items: <?= $c['total'] ?> (<?= $c['done'] ?> done)<br>
    <strong>This cannot be undone.</strong>
</p>
<form method="POST" class="d-inline">
    <input type="hidden" name="lid" value="<?= $subId ?>">
    <button class="btn bg-danger" name="confirmed_del_list" value="1">Delete list</button>
</form>
<a href="?lists&action=view_list&id=<?= $subId ?>" class="btn bg-muted">Back</a>

<?php
elseif ($section === 'lists' && $action === 'view_list'):
    if (!$subId) { flash('No list selected.'); redirect('?lists'); }
    $list  = getList($subId);
    if (!$list) { flash('List not found.'); redirect('?lists'); }
    $items = getItems($subId);
    $allGroups = getListGroups();
?>

<div class="mb2">
    <div class="form-inline-block">
        <label for="list_name">Name</label>
        <input type="text" id="list_name" name="list_name" value="<?= e($list['name']) ?>" required>
    </div>
    <div class="form-inline-block">
        <label for="list_group">Group:</label>
        <select id="list_group" name="groupid">
            <option value="">(None)</option>
            <?php foreach ($allGroups as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= (int)$list['groupid'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-inline-block">
        <button type="submit" class="btn bg-primary">save</button>
        <a href="?lists&action=confirm_delete_list&id=<?= $subId ?>" class="btn bg-danger">Delete list</a>
    </div>
</div>

<ul class="checklist">
<?php foreach ($items as $idx => $item): ?>
<li>
    <input type="hidden" name="item_id[<?= (int)$idx ?>]" value="<?= (int)$item['id'] ?>">
    <input type="checkbox" name="item_checked[]" value="<?= (int)$item['id'] ?>" id="chk_<?= (int)$item['id'] ?>" <?= $item['checked'] ? 'checked' : '' ?>>
    <input type="text" name="item_content[<?= (int)$idx ?>]" value="<?= e($item['content']) ?>" class="<?= $item['checked'] ? 'done' : '' ?>" aria-label="Item content">
</li>
<?php endforeach; ?>
</ul>

<div class="mt4 mb2 add-item-wrap">
    <label for="new_item">New item:</label>
    <input type="text" id="new_item" name="new_item" placeholder="Add an item">
    <button type="submit" class="btn bg-primary">Add item</button>
</div>
<p class="empty-note mt2">To remove an item: clear its text field and press save or add item.</p>

<?php
elseif ($section === 'notepads' && $action === 'groups'):
    $npGroups = getNotepadGroups();
?>
<h2 class="group-name">Notepad groups</h2>

<?php if (empty($npGroups)): ?>
<p class="empty-note mt2">No groups yet.</p>
<?php else: ?>
<?php foreach ($npGroups as $g):
    $cnt = countNotepadsInGroup((int)$g['id']);
?>
<div class="fs-base fw-bold mt3"><?= e($g['name']) ?></div>
<div class="empty-note mb2"><?= $cnt ?> notepad<?= $cnt !== 1 ? 's' : '' ?></div>
<form method="POST" class="d-inline">
    <input type="hidden" name="ngid" value="<?= (int)$g['id'] ?>">
    <input type="text" name="name" value="<?= e($g['name']) ?>" required aria-label="Rename group">
    <button class="btn bg-muted" name="rename_notepadgroup" value="1">Rename</button>
</form>
<a href="?notepads&action=confirm_delete_notepadgroup&id=<?= (int)$g['id'] ?>" class="btn bg-danger">Delete group</a>
<?php endforeach; ?>
<?php endif; ?>

<div class="section-bottom">
    <p class="empty-note mb2">Add a new group:</p>
    <form method="POST">
        <input type="text" name="name" placeholder="Group name" required>
        <button class="btn bg-primary" name="add_notepadgroup" value="1">Add group</button>
    </form>
</div>

<?php
elseif ($section === 'notepads' && $action === 'confirm_delete_notepadgroup'):
    if (!$subId) redirect('?notepads&action=groups');
    $g = getNotepadgroup($subId);
    if (!$g) { flash('Group not found.'); redirect('?notepads&action=groups'); }
    $cnt = countNotepadsInGroup($subId);
?>
<h2>Delete group "<?= e($g['name']) ?>"?</h2>
<p>
    Notepads in group: <?= $cnt ?><br>
    <?php if ($cnt > 0): ?>
    These notepads will become ungrouped, not deleted.<br>
    <?php endif; ?>
    <strong>The group itself cannot be recovered.</strong>
</p>
<form method="POST" class="d-inline">
    <input type="hidden" name="ngid" value="<?= $subId ?>">
    <button class="btn bg-danger" name="confirmed_del_notepadgroup" value="1">Delete group</button>
</form>
<a href="?notepads&action=groups" class="btn bg-muted">Back</a>

<?php
elseif ($section === 'notepads' && $action !== 'view_notepad' && $action !== 'confirm_delete_np'):
    $npGroups = getNotepadGroups();
    $ungroupedPads = getNotepadsByGroup(null);
?>

<?php foreach ($npGroups as $g): ?>
<h2 class="group-name"><?= e($g['name']) ?></h2>
<?php $gPads = getNotepadsByGroup((int)$g['id']); ?>
<?php if (empty($gPads)): ?>
<p class="empty-note mb2">No notepads in this group yet.</p>
<?php else: ?>
<ul class="item-list">
<?php foreach ($gPads as $np): $written = countWrittenPages((int)$np['id']); ?>
<li class="bg-muted">
    <a href="?notepads&action=view_notepad&id=<?= (int)$np['id'] ?>"><?= e($np['name']) ?></a>
    <span class="meta"><?= fmtDate($np['date']) ?> - <?= (int)$np['pages'] ?> pages, <?= $written ?> written</span>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<form method="POST" class="mb4">
    <input type="hidden" name="groupid" value="<?= (int)$g['id'] ?>">
    <button class="btn bg-primary" name="add_notepad" value="1">New notepad in <?= e($g['name']) ?></button>
</form>
<?php endforeach; ?>

<?php if (!empty($ungroupedPads)): ?>
<h2 class="group-name">Ungrouped Notebooks</h2>
<ul class="item-list">
<?php foreach ($ungroupedPads as $np): $written = countWrittenPages((int)$np['id']); ?>
<li class="bg-muted">
    <a href="?notepads&action=view_notepad&id=<?= (int)$np['id'] ?>"><?= e($np['name']) ?></a>
    <span class="meta"><?= fmtDate($np['date']) ?> - <?= (int)$np['pages'] ?> pages, <?= $written ?> written</span>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<div class="section-bottom">
    <form method="POST" class="d-inline-block mr3">
        <input type="hidden" name="groupid" value="">
        <button class="btn bg-primary" name="add_notepad" value="1">Create Loose Notepad</button>
    </form>
    <a href="?notepads&action=groups" class="btn bg-muted">Manage groups</a>
</div>

<?php
elseif ($section === 'notepads' && $action === 'confirm_delete_np'):
    if (!$subId) redirect('?notepads');
    $np = getNotepad($subId);
    if (!$np) { flash('Notepad not found.'); redirect('?notepads'); }
    $written = countWrittenPages($subId);
?>
<h2>Delete notebook "<?= e($np['name']) ?>"?</h2>
<p>
    Group: <?= e($np['groupname'] ?? 'none') ?><br>
    Created: <?= fmtDate($np['date']) ?><br>
    Pages: <?= (int)$np['pages'] ?> (<?= $written ?> with content)<br>
    <strong>This cannot be undone.</strong>
</p>
<form method="POST" class="d-inline">
    <input type="hidden" name="npid" value="<?= $subId ?>">
    <button class="btn bg-danger" name="confirmed_del_np" value="1">Delete notebook</button>
</form>
<a href="?notepads&action=view_notepad&id=<?= $subId ?>" class="btn bg-muted">Back</a>

<?php
elseif ($section === 'notepads' && $action === 'view_notepad'):
    if (!$subId) { flash('No notepad selected.'); redirect('?notepads'); }
    $np = getNotepad($subId);
    if (!$np) { flash('Notepad not found.'); redirect('?notepads'); }
    $pages = getAllPages($subId);
    $allGroups = getNotepadGroups();
?>

<div class="mb2">
    <div class="form-inline-block">
        <label for="notepad_name">Name</label>
        <input type="text" id="notepad_name" name="notepad_name" value="<?= e($np['name']) ?>" required>
    </div>
    <div class="form-inline-block">
        <label for="np_pages">Pages</label>
        <input type="number" id="np_pages" name="pages" value="<?= (int)$np['pages'] ?>" min="1" max="999">
    </div>
    <div class="form-inline-block">
        <label for="np_group">Group:</label>
        <select id="np_group" name="groupid">
            <option value="">(None)</option>
            <?php foreach ($allGroups as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= (int)$np['groupid'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-inline-block">
        <button type="submit" class="btn bg-primary">save</button>
        <a href="?notepads&action=confirm_delete_np&id=<?= $subId ?>" class="btn bg-danger">Delete notebook</a>
    </div>
</div>

<?php foreach ($pages as $page): ?>
<div class="mb4 cl-both">
    <div class="page-header">
        <span class="page-label">Page <?= (int)$page['num'] ?></span>
        <button type="submit" class="btn bg-primary">save</button>
    </div>
    <textarea name="page_content[<?= (int)$page['num'] ?>]" rows="14" aria-label="Page <?= (int)$page['num'] ?>"><?= e($page['content']) ?></textarea>
</div>
<?php if ((int)$page['num'] < (int)$np['pages']): ?>
<hr class="page-divider">
<?php endif; ?>
<?php endforeach; ?>

<?php endif; ?>
<?php endif; ?>

<?php if ($isMasterFormView): ?>
</form>
<?php endif; ?>

</div>

<script>
document.querySelectorAll('.checklist input[type=checkbox]').forEach(function(cb) {
    var li = cb.closest('li');
    if (!li) return;
    var txt = li.querySelector('input[type=text]');
    if (!txt) return;
    cb.addEventListener('change', function() {
        txt.classList.toggle('done', cb.checked);
    });
});

document.addEventListener('keydown', function(ev) {
    if ((ev.ctrlKey || ev.metaKey) && ev.key === 's') {
        ev.preventDefault();
        var btns = document.querySelectorAll('button[type="submit"]');
        for (var i = 0; i < btns.length; i++) {
            if (!btns[i].name) {
                btns[i].click();
                break;
            }
        }
    }
});
</script>
</body>
</html>