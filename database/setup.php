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
echo 'Database ', $config['name'], ' is ready. No sample data was inserted.', PHP_EOL;
