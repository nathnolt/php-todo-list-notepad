<?php function template($data) { extract($data); ?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Todo Lists - Pocketbook</title>
	
    <style>
        body, button, input, textarea, select { font-family: Georgia, serif; font-size: 16px; line-height: 1.3; color: #000; }
        body { background: #fff; margin: 0; padding: 0 0 40px 0; }
        a { color: #000; text-decoration: underline; }
        .bg-primary { background-color: #b3d1ff; } 
        .bg-danger  { background-color: #ffb3b3; } 
        .bg-muted   { background-color: #e6e6e6; }
        .wrap { width: 840px; max-width: 100%; margin: 0 auto; padding: 8px 4px; box-sizing: border-box; }
        input[type=text], input[type=password], input[type=number], select { box-sizing: border-box; border: 2px solid #e6e6e6; padding: 2px 4px; border-radius: 4px; }
        textarea { width: 100%; background-color: #e6e6e6; border: 2px solid #e6e6e6; padding: 4px; border-radius: 4px; font-family: monospace; box-sizing: border-box; }
        .btn { display: inline-block; padding: 2px 8px; border: none; border-radius: 4px; color: #000; text-decoration: none; cursor: pointer; }
        .topbar { padding-bottom: 4px; border-bottom: 2px dashed #e6e6e6; }
        .app-title { font-size: 18px; font-weight: bold; }
        .flash { padding: 6px 10px; margin-bottom: 8px; border-radius: 4px; }
        .empty-note { font-style: italic; color: #555; }
        .section-bottom { margin-top: 10px; border-top: 2px dashed #e6e6e6; padding-top: 4px; }
        ul.item-list { list-style: none; padding: 0; margin: 0; }
        ul.item-list li { padding: 4px 8px; margin-bottom: 4px; border-radius: 4px; background: #e6e6e6; }
        ul.item-list li a { font-weight: bold; }
        ul.item-list li .meta { font-size: 13px; margin-left: 8px; color: #555; }
        .checklist { list-style: none; padding: 0; }
        .checklist li { padding: 2px 0; }
        .checklist input[type=text].done { text-decoration: line-through; opacity: 0.5; }
        .page-divider { border: none; border-top: 2px dashed #e6e6e6; margin: 24px 0; }
        h2.group-name { font-size: 18px; margin: 0; }
        .fs-base { font-size: 14px; } .fw-bold { font-weight: bold; }
        .mt3 { margin-top: 6px; } .mb2 { margin-bottom: 4px; } .mb4 { margin-bottom: 8px; }
        .cl-both { clear: both; } .page-label { font-weight: bold; display: inline-block; margin-top: 6px; }

        
        .w-full { width: 100%; }
        .box-border { box-sizing: border-box; }
        .block { display: block; }
        .inline { display: inline; }
        .inline-block { display: inline-block; }
        .float-right { float: right; }
        .list-none { list-style: none; margin: 0; padding: 0;}
        .text-center { text-align: center; }
		.m0 {margin: 0;}
        .mt0 { margin-top: 0; } .mt1 {margin-top: 4px} .mt5 { margin-top: 12px; }
        .mb1 { margin-bottom: 2px; } .mb3 { margin-bottom: 6px; } .mb5 { margin-bottom: 10px; } .mb6 { margin-bottom: 12px; }
        .p0 { padding: 0; } .m0 { margin: 0; }
        .mr4 { margin-right: 8px; }
        .py1 { padding-top: 4px; padding-bottom: 4px; }
        .text-sm { font-size: 12px; } .text-lg { font-size: 16px; } .text-xl { font-size: 18px; }
        .auth-box { max-width: 400px; margin: 15px auto; padding: 10px; border: 2px dashed #e6e6e6; border-radius: 8px; }
    </style>
	
</head>
<body>
    <div class="wrap">
        <header class="topbar mb1">
            <h1 class="app-title m0 inline-block">Todo Lists - Pocketbook</h1>
			<?php if($loggedIn) { ?>
                <nav class="inline-block">
                        <?php if($section !== 'dashboard' && $action === '') { ?>
                            <a href="?" class="btn bg-muted">Home</a>
                        <?php } ?>
                        <?php if($action !== '') { ?>
                            <?php if($section === 'lists') { ?><a href="?lists" class="btn bg-muted">Lists</a><?php } ?>
                            <?php if($section === 'notepads') { ?><a href="?notepads" class="btn bg-muted">Notebooks</a><?php } ?>
                        <?php } ?>
                </nav>
            <?php } ?>
        </header>

        <?php if($flash) { ?>
            <div class="flash <?php echo $flash['type'] === 'ok' ? 'bg-primary' : 'bg-danger'; ?>"><?php echo htmlspecialchars($flash['msg']); ?></div>
        <?php } ?>

        <?php if($action === 'groups') { ?>
    <h2 class="group-name">List groups</h2>
    <?php if(empty($groups)) { ?>
        <p class="empty-note">No groups yet.</p>
    <?php } else { ?>
        <?php foreach($groups as $g) { ?>
            <?php $cnt = countListsInGroup((int)$g['id']); ?>
            <div class="empty-note mt3 mb2"><?php echo $cnt; ?> list<?php echo $cnt !== 1 ? 's' : ''; ?></div>
            <form method="POST" class="inline">
                <input type="hidden" name="gid" value="<?php echo $g['id']; ?>">
                <input type="text" name="name" value="<?php echo htmlspecialchars($g['name']); ?>" required>
                <button class="btn bg-muted" name="rename_listgroup" value="1">Rename</button>
            </form>
            <a href="?lists&action=confirm_delete_listgroup&id=<?php echo $g['id']; ?>" class="btn bg-danger">Delete group</a>
        <?php } ?>
    <?php } ?>
    <div class="section-bottom">
        <p class="empty-note mb2">Add a new group:</p>
        <form method="POST">
            <input type="text" name="name" placeholder="Group name" required>
            <button class="btn bg-primary" name="add_listgroup" value="1">Add group</button>
        </form>
    </div>
<?php } ?>

<?php if($action === 'confirm_delete_listgroup') { ?>
    <?php $g = getListgroup($subId); ?>
    <?php $cnt = countListsInGroup($subId); ?>
    <h2>Delete group "<?php echo htmlspecialchars($g['name']); ?>"?</h2>
    <p>Lists in group: <?php echo $cnt; ?><br>
    <?php if($cnt > 0) { ?>These lists will become ungrouped, not deleted.<br><?php } ?>
    <strong>The group itself cannot be recovered.</strong></p>
    <form method="POST" class="inline">
        <input type="hidden" name="gid" value="<?php echo $subId; ?>">
        <button class="btn bg-danger" name="confirmed_del_listgroup" value="1">Delete group</button>
    </form>
    <a href="?lists&action=groups" class="btn bg-muted">Back</a>
<?php } ?>

<?php if($action === 'confirm_delete_list') { ?>
    <?php $list = getList($subId); ?>
    <?php $c = countItems($subId); ?>
    <h2>Delete list "<?php echo htmlspecialchars($list['name']); ?>"?</h2>
    <p>Group: <?php echo htmlspecialchars($list['groupname'] ?? 'none'); ?><br>
    Items: <?php echo $c['total']; ?> (<?php echo $c['done']; ?> done)<br>
    <strong>This cannot be undone.</strong></p>
    <form method="POST" class="inline">
        <input type="hidden" name="lid" value="<?php echo $subId; ?>">
        <button class="btn bg-danger" name="confirmed_del_list" value="1">Delete list</button>
    </form>
    <a href="?lists&action=view_list&id=<?php echo $subId; ?>" class="btn bg-muted">Back</a>
<?php } ?>

<?php if($action === '') { ?>
    <?php $ungrouped = getListsByGroup(null); ?>
    <?php foreach($groups as $g) { ?>
        <h2 class="group-name"><?php echo htmlspecialchars($g['name']); ?></h2>
        <?php $gLists = getListsByGroup((int)$g['id']); ?>
        <?php if(empty($gLists)) { ?>
            <p class="empty-note mb2">No lists in this group yet.</p>
        <?php } else { ?>
            <ul class="item-list">
            <?php foreach($gLists as $l) { ?>
                <?php $c = countItems((int)$l['id']); ?>
                <li class="bg-muted">
                    <a href="?lists&action=view_list&id=<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?></a>
                    <span class="meta"><?php echo fmtDate($l['date']); ?> - <?php echo $c['total']; ?> item<?php echo $c['total'] !== 1 ? 's' : ''; ?></span>
                </li>
            <?php } ?>
            </ul>
        <?php } ?>
        <form method="POST" class="mb4">
            <input type="hidden" name="groupid" value="<?php echo $g['id']; ?>">
            <button class="btn bg-primary" name="add_list" value="1">New list in <?php echo htmlspecialchars($g['name']); ?></button>
        </form>
    <?php } ?>

    <?php if(!empty($ungrouped)) { ?>
        <h2 class="group-name">Ungrouped Lists</h2>
        <ul class="item-list">
        <?php foreach($ungrouped as $l) { ?>
            <?php $c = countItems((int)$l['id']); ?>
            <li class="bg-muted">
                <a href="?lists&action=view_list&id=<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?></a>
                <span class="meta"><?php echo fmtDate($l['date']); ?> - <?php echo $c['total']; ?> item<?php echo $c['total'] !== 1 ? 's' : ''; ?></span>
            </li>
        <?php } ?>
        </ul>
    <?php } ?>

    <div class="section-bottom">
        <form method="POST" class="inline-block mr4">
            <input type="hidden" name="groupid" value="">
            <button class="btn bg-primary" name="add_list" value="1">Create Loose List</button>
        </form>
        <a href="?lists&action=groups" class="btn bg-muted">Manage groups</a>
    </div>
<?php } ?>
    </div>
	
	
    <script>
        // Auto-save styling for checklists
        document.querySelectorAll('.checklist input[type=checkbox]').forEach(cb => {
            cb.addEventListener('change', () => {
                cb.nextElementSibling.classList.toggle('done', cb.checked);
            });
        });
        // Ctrl+S to save any form
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const btn = document.querySelector('button[type="submit"]');
                if (btn) btn.click();
            }
        });
    </script>
	
</body>
</html><?php } ?>