<?php
function db(): PDO {
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