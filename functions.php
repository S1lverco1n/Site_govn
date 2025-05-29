<?php
// functions.php

define('DB_PATH', __DIR__ . '/database/app.db');
define('UPLOAD_DIR', __DIR__ . '/images/'); // Папка для загрузки изображений

$pdo = null;

try {
    $db_dir = __DIR__ . '/database';
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0755, true);
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $createTableUsersQuery = "
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user',
        created_at TEXT DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now', 'localtime'))
    );";
    $pdo->exec($createTableUsersQuery);

    $createTablePostsQuery = "
    CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        image_path TEXT NULL,
        created_at TEXT DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now', 'localtime')),
        updated_at TEXT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );";
    $pdo->exec($createTablePostsQuery);

    $tableInfoPosts = $pdo->query("PRAGMA table_info(posts);")->fetchAll();
    $columnsPosts = array_column($tableInfoPosts, 'name');
    
    if (!in_array('image_path', $columnsPosts)) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN image_path TEXT NULL;");
    }
    if (!in_array('updated_at', $columnsPosts)) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN updated_at TEXT NULL;");
    }

} catch (PDOException $e) {
    die("ОШИБКА БД: " . $e->getMessage());
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
}

function isAdmin() {
    return (isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

function deleteFile($filePath) {
    if ($filePath && file_exists($filePath)) {
        @unlink($filePath); // Используем @ для подавления возможных ошибок, если файл уже удален или нет прав
    }
}
?>