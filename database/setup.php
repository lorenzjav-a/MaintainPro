<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/database.php';
if (array_slice($argv, 1) === ['--schema']) {
    echo DatabaseMaintenance::schema(), PHP_EOL, DatabaseMaintenance::migrationsSql(), PHP_EOL;
    exit;
}
if (array_slice($argv, 1) === ['--migration']) {
    echo DatabaseMaintenance::migrationsSql(), PHP_EOL;
    exit;
}
if (count($argv) !== 1) {
    fwrite(STDERR, "Usage: php database/setup.php [--schema|--migration]\n");
    exit(2);
}
$config = br_database_config();
DatabaseMaintenance::create($config['name'], ifNotExists: true);
DatabaseMaintenance::initialize(br_database());
echo 'Database ', $config['name'], ' is ready. No sample data was inserted.', PHP_EOL;
