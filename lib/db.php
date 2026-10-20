<?php
require_once __DIR__ . '/../config.php';

/**
 * PDO 单例
 */
function db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ));
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<div style="font:14px/1.7 system-ui;padding:24px;max-width:640px;margin:40px auto;'
           . 'border:1px solid #f3c2c2;background:#fff7f7;border-radius:10px;color:#a12d2d">';
        echo '<b>数据库连接失败</b><br>请检查 config.php 中的 DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS。';
        echo '<pre style="white-space:pre-wrap;color:#666;margin-top:12px">' . htmlspecialchars($e->getMessage()) . '</pre></div>';
        exit;
    }
    return $pdo;
}
