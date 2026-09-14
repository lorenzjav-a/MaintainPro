<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/database.php';
$config = br_database_config();
$server = br_database(serverOnly: true);
$server->exec('CREATE DATABASE IF NOT EXISTS `' . $config['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db = br_database();
$db->exec(file_get_contents(__DIR__ . '/schema.sql'));
$columns = $db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('must_change_password', $columns, true)) {
    $db->exec('ALTER TABLE users ADD COLUMN must_change_password TINYINT NOT NULL DEFAULT 0');
}
echo 'Database ', $config['name'], ' is ready. No sample data was inserted.', PHP_EOL;
