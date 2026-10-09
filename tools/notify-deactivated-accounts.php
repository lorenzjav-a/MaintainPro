<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Manila');
require dirname(__DIR__) . '/includes/store.php';

$checked = (new ComplaintStore())->sweepDeactivatedAccounts();
echo 'Deactivated account notifications checked for ' . $checked . " eligible account(s).\n";
