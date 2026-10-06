<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/database/database.php';
$database=new MaintainProDatabase(br_database());
$counts=$database->cleanupExpiredSecurityRecords(time());
echo 'Expired security records removed: '.array_sum($counts).PHP_EOL;
