<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Manila');
require dirname(__DIR__) . '/includes/store.php';
(new ComplaintStore())->sweepDeadlines();
echo "Deadline notifications checked.\n";
