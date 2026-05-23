<?php function template($data) { extract($data); ?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Editing List - Pocketbook</title>
	
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
            <h1 class="app-title m0 inline-block">Editing List - Pocketbook</h1>
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

        <form method="POST">
    <input type="hidden" name="lid" value="<?php echo $subId; ?>">
    
    <div class="mb5">
        <div class="mb3">
            <label for="list_name" class="fw-bold">Name</label>
            <input type="text" id="list_name" name="list_name" value="<?php echo htmlspecialchars($list['name']); ?>" required class="w-full text-lg">
        </div>
        <div class="inline-block mr4">
            <label for="list_group" class="fw-bold">Group</label>
            <select id="list_group" name="groupid">
                <option value="">(None)</option>
                <?php foreach($groups as $g) { ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $list['groupid'] == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                <?php } ?>
            </select>
        </div>
        <button type="submit" class="btn bg-primary">Save Changes</button>
    </div>

    <ul class="checklist">
    <?php foreach($items as $idx => $item) { ?>
        <li>
            <input type="hidden" name="item_id[<?php echo $idx; ?>]" value="<?php echo $item['id']; ?>">
            <input type="checkbox" name="item_checked[]" value="<?php echo $item['id']; ?>" <?php echo $item['checked'] ? 'checked' : ''; ?>>
            <input type="text" name="item_content[<?php echo $idx; ?>]" value="<?php echo htmlspecialchars($item['content']); ?>" class="<?php echo $item['checked'] ? 'done' : ''; ?>">
        </li>
    <?php } ?>
    </ul>

    <div class="section-bottom">
        <label>Add item:</label>
        <input type="text" name="new_item" placeholder="What needs doing?">
        <button type="submit" class="btn bg-primary">Add</button>
    </div>

    <div class="mt5">
        <button type="submit" name="save_and_back_list" value="1" class="btn bg-muted">Save & Back</button>
        <a href="?lists&action=confirm_delete_list&id=<?php echo $subId; ?>" class="btn bg-danger float-right">Delete List</a>
    </div>
</form>
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