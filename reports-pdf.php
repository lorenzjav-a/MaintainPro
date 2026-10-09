<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/view.php';
$actor = br_actor();
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Content-Type: text/plain; charset=utf-8');
if (!$actor) { http_response_code(401); exit('Sign in to export a report.'); }
if ($actor['role'] !== 'official' || $actor['must_change_password']) { http_response_code(403); exit('Only an authorized official with a completed account may export reports.'); }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); header('Allow: GET'); exit('Use GET to download a report.'); }
try {
    $report = br_store()->concernReport($actor['id'],$_GET,1,true);
    require_once __DIR__.'/includes/report-pdf.php';
    $generated = new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
    session_write_close();
    $binary = ConcernReportPdf::generate($report,$generated);
    if (!str_starts_with($binary,'%PDF-')) throw new RuntimeException('Invalid PDF output.');
    // Only send attachment headers after successful rendering; buffered diagnostics
    // and the JSON API can never contaminate a downloaded document.
    br_error_clear_output();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="MaintainPro_Concerns_Report_'.$generated->format('Y-m-d').'.pdf"');
    header('Content-Length: '.strlen($binary));
    echo $binary;
} catch (DomainException $error) {
    br_error_clear_output();
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo $error->getMessage();
}
