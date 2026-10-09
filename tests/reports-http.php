<?php
// Included by the isolated HTTP harness after all role sessions are available.
$reportResponse = req($limitedJar,'reports.php?priority=High&personnel=unassigned');
httpCheck($reportResponse['status']===200 && str_contains($reportResponse['body'],'id="report-filters"'),'operational official can filter reports');
httpCheck(str_contains($reportResponse['body'],'reports-pdf.php?') && str_contains($reportResponse['body'],'Export to PDF') && !str_contains($reportResponse['body'],'export=csv'),'only Reports replaces CSV with PDF');
httpCheck(str_contains(req($adminJar,'complaints.php')['body'],'api.php?export=csv') && str_contains(req($adminJar,'history.php')['body'],'api.php?export=csv'),'Concerns and History retain CSV links');
httpCheck(req($adminJar,'api.php?export=csv')['status']===200,'existing CSV endpoint remains functional');
foreach (['start=2026-02-30','start=2026-10-10&end=2026-10-01','priority=Critical','type=Pothole&category=Street%20Lighting','status[]=Submitted'] as $invalidFilter) {
    httpCheck(req($adminJar,'reports.php?'.$invalidFilter)['status']===422,'invalid page filter '.$invalidFilter);
    $invalidPdf=req($adminJar,'reports-pdf.php?'.$invalidFilter);
    httpCheck($invalidPdf['status']===422 && !str_starts_with($invalidPdf['body'],'%PDF-'),'invalid PDF filter '.$invalidFilter);
}
foreach ([$adminJar,$limitedJar] as $officialJar) {
    $pdfResponse=req($officialJar,'reports-pdf.php');
    httpCheck($pdfResponse['status']===200 && str_starts_with($pdfResponse['body'],'%PDF-') && str_contains($pdfResponse['body'],'%%EOF'),'official gets real PDF');
    httpCheck(($pdfResponse['headers']['content-type'] ?? '')==='application/pdf' && str_contains($pdfResponse['headers']['content-disposition'] ?? '','attachment; filename="MaintainPro_Concerns_Report_'),'PDF MIME and safe attachment filename');
    httpCheck(str_contains($pdfResponse['headers']['cache-control'] ?? '','no-store'),'PDF not cached');
    httpCheck((int)($pdfResponse['headers']['content-length'] ?? 0)===strlen($pdfResponse['body']),'PDF length matches clean binary');
}
httpCheck(req($guestJar,'reports-pdf.php')['status']===401,'unauthenticated guest denied PDF');
httpCheck(req($verificationJar,'reports-pdf.php')['status']===403,'resident denied PDF');
httpCheck(req($resetJar,'reports-pdf.php')['status']===403,'personnel denied PDF');
httpCheck(req($adminJar,'reports-pdf.php',['action'=>'export'],token($adminJar))['status']===405,'PDF endpoint read-only GET');
$emptyPdf=req($adminJar,'reports-pdf.php?search=NONEXISTENT-REPORT-ONLY');
httpCheck($emptyPdf['status']===200 && str_starts_with($emptyPdf['body'],'%PDF-'),'zero-match PDF generated without fallback');
httpCheck(req($guestJar,'vendor/dompdf/autoload.inc.php')['status']===404,'PDF library files protected by router');
