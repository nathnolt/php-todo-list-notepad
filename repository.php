<?php

function getListGroups(): array {
    $st = db()->prepare('SELECT * FROM listgroup WHERE userid=? ORDER BY ord,id');
    $st->execute([uid()]);
    return $st->fetchAll();
}

function getListgroup(int $id): ?array {
    $st = db()->prepare('SELECT * FROM listgroup WHERE id=? AND userid=?');
    $st->execute([$id, uid()]);
    $res = $st->fetch();
    return $res ?: null;
}

function getListsByGroup(?int $gid): array {
    if ($gid === null) {
        $st = db()->prepare('SELECT * FROM list WHERE userid=? AND groupid IS NULL ORDER BY ord,id');
        $st->execute([uid()]);
    } else {
        $st = db()->prepare('SELECT * FROM list WHERE userid=? AND groupid=? ORDER BY ord,id');
        $st->execute([uid(), $gid]);
    }
    return $st->fetchAll();
}

function getList(int $id): ?array {
    $st = db()->prepare('SELECT l.*, g.name AS groupname FROM list l LEFT JOIN listgroup g ON g.id=l.groupid WHERE l.id=? AND l.userid=?');
    $st->execute([$id, uid()]);
    $res = $st->fetch();
    return $res ?: null;
}

function getItems(int $listid): array {
    $st = db()->prepare('SELECT * FROM item WHERE listid=? ORDER BY checked ASC, id ASC');
    $st->execute([$listid]);
    return $st->fetchAll();
}

function getNotepadGroups(): array {
    $st = db()->prepare('SELECT * FROM notepadgroup WHERE userid=? ORDER BY ord,id');
    $st->execute([uid()]);
    return $st->fetchAll();
}

function getNotepadgroup(int $id): ?array {
    $st = db()->prepare('SELECT * FROM notepadgroup WHERE id=? AND userid=?');
    $st->execute([$id, uid()]);
    $res = $st->fetch();
    return $res ?: null;
}

function getNotepadsByGroup(?int $gid): array {
    if ($gid === null) {
        $st = db()->prepare('SELECT * FROM notepad WHERE userid=? AND groupid IS NULL ORDER BY ord,id');
        $st->execute([uid()]);
    } else {
        $st = db()->prepare('SELECT * FROM notepad WHERE userid=? AND groupid=? ORDER BY ord,id');
        $st->execute([uid(), $gid]);
    }
    return $st->fetchAll();
}

function getNotepad(int $id): ?array {
    $st = db()->prepare('SELECT n.*, g.name AS groupname FROM notepad n LEFT JOIN notepadgroup g ON g.id=n.groupid WHERE n.id=? AND n.userid=?');
    $st->execute([$id, uid()]);
    $res = $st->fetch();
    return $res ?: null;
}

function getAllPages(int $npid): array {
    $st = db()->prepare('SELECT * FROM page WHERE notepadid=? ORDER BY num ASC');
    $st->execute([$npid]);
    return $st->fetchAll();
}

function countItems(int $lid): array {
    $st = db()->prepare("SELECT (SELECT COUNT(*) FROM item WHERE listid=?) as total, (SELECT COUNT(*) FROM item WHERE listid=? AND checked=1) as done");
    $st->execute([$lid, $lid]);
    return $st->fetch();
}

function countListsInGroup(int $gid): int {
    $st = db()->prepare("SELECT COUNT(*) FROM list WHERE groupid=?");
    $st->execute([$gid]);
    return (int)$st->fetchColumn();
}

function countWrittenPages(int $npid): int {
    $st = db()->prepare("SELECT COUNT(*) FROM page WHERE notepadid=? AND content != ''");
    $st->execute([$npid]);
    return (int)$st->fetchColumn();
}

function countNotepadsInGroup(int $gid): int {
    $st = db()->prepare("SELECT COUNT(*) FROM notepad WHERE groupid=?");
    $st->execute([$gid]);
    return (int)$st->fetchColumn();
}