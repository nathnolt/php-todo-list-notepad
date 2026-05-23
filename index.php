<?php
session_start();

define('ROOT', __DIR__);
define('DB_PATH', ROOT . '/data.sqlite');

require_once ROOT . '/db.php';
require_once ROOT . '/functions.php';
require_once ROOT . '/repository.php';
require_once ROOT . '/template-handler.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once ROOT . '/controller.php';
    handlePostRequest();
}

// Routing logic
$section = 'dashboard';
if (isset($_GET['lists']))    $section = 'lists';
if (isset($_GET['notepads'])) $section = 'notepads';

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
$subId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash  = getFlash();

$data = [
    'section' => $section,
    'action'  => $action,
    'subId'   => $subId,
    'flash'   => $flash,
    'username' => $_SESSION['username'] ?? '',
    'loggedIn' => loggedIn(),
];

// Determine which template to render
if (!$data['loggedIn']) {
    echo Template::handle('login', $data);
    exit;
}

// Render based on section
if ($section === 'lists') {
    $data['groups'] = getListGroups();
    if ($action === 'view_list') {
        $data['list'] = getList($subId);
        $data['items'] = getItems($subId);
        echo Template::handle('list_view', $data);
    } else {
        echo Template::handle('lists_index', $data);
    }
} elseif ($section === 'notepads') {
    $data['groups'] = getNotepadGroups();
    if ($action === 'view_notepad') {
        $data['notepad'] = getNotepad($subId);
        $data['pages'] = getAllPages($subId);
        echo Template::handle('notepad_view', $data);
    } else {
        echo Template::handle('notepads_index', $data);
    }
} else {
    echo Template::handle('dashboard', $data);
}
