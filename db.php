<?php
/**
 * db.php — CaseFlowX SQLite Connection
 * Shared database utility. Include this in any page that needs DB access.
 */

define('DB_PATH', __DIR__ . '/data/caseflowx.sqlite');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    init_schema($pdo);
    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS citizens (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name       TEXT    NOT NULL,
            national_id     TEXT    NOT NULL UNIQUE,
            date_of_birth   TEXT    NOT NULL,
            gender          TEXT    NOT NULL CHECK(gender IN ('male','female','other')),
            phone           TEXT    NOT NULL UNIQUE,
            email           TEXT    UNIQUE,
            division        TEXT    NOT NULL,
            district        TEXT    NOT NULL,
            address         TEXT    NOT NULL,
            password_hash   TEXT    NOT NULL,
            status          TEXT    NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended')),
            created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
            last_login      TEXT
        )
    ");
}
