<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require dirname(__DIR__).'/includes/store.php';
require dirname(__DIR__).'/includes/view.php';
require dirname(__DIR__).'/includes/report-pdf.php';
require __DIR__.'/support/database.php';
$test = new TestDatabase(); $checks = 0;
function reportCheck(bool $ok,string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; }
function reportDenied(callable $call,string $label): void { try { $call(); } catch (DomainException) { reportCheck(true,$label); return; } throw new RuntimeException('FAIL: '.$label); }
try {
    $store = new ComplaintStore($test->connect()); $db = new MaintainProDatabase($test->connect());
    $password = 'Report-test-password-42';
    $official = $store->setup(['name'=>'Report Official','email'=>'reports@example.test','password'=>$password]);
    $resident = $store->register(['name'=>'SECRET-ANONYMOUS-IDENTITY','email'=>'resident@example.test','password'=>$password]);
    $person = $store->createUser($official['id'],['name'=>'Report Worker','email'=>'worker@example.test','role'=>'personnel','team'=>'Maintenance crew']);
    $person = activateInvitedUser($store,$person,$password);
    $store->createLocation($official['id'],['name'=>'Purok One']); $location = $store->locations()[0];
    $input = ['category'=>'Street Lighting','concernType'=>'Exposed wiring','keyPoints'=>['Sparks visible'],'locationId'=>(string)$location['id'],'street'=>'Mabini St.','exactArea'=>'School gate','description'=>'Peña & Niño <script>private-file.php</script>'];
    $sourceId = $store->submitAccount($resident['id'],$input+['isAnonymous'=>'1']);
    $source = $store->state()['cases'][0];
    // Bulk records are isolated fixtures, not live submissions or live database writes.
    $ids = [];
    for ($i=1;$i<=65;$i++) {
        $c = $source; $c['id']='CON-2026-'.str_pad((string)(1000+$i),6,'0',STR_PAD_LEFT);
        $c['createdAt'] = $i===65 ? '2026-10-10T00:00:00+08:00' : ($i===64 ? '2026-10-09T23:59:59+08:00' : '2026-10-01T12:00:00+08:00');
        $c['priority'] = $i%2===0 ? 'High':'Low';
        $c['status'] = $i%3===0 ? 'In Progress':'Submitted';
        $c['team'] = $i%3===0 ? 'Maintenance crew':'';
        $c['assignedUserId'] = $i%3===0 ? $person['id']:null;
        $c['assignedName'] = $i%3===0 ? 'Report Worker':null;
        if ($i===2) unset($c['locationDetails']['purokId']);
        if ($i===3) { $c['locationDetails']=[]; $c['concernType']=''; $c['keyPoints']=[]; }
        if ($i===4) $c['description']=str_repeat('Long location response Peña & Niño. ',110).'END-OF-LONG-DESCRIPTION';
        $db->insertComplaint($c); $ids[]=$c['id'];
    }
    $before = (new DatabaseTestFixtures($test->connect()))->dataChecksums();
    $all = $store->concernReport($official['id'],[]);
    $originalMetrics=br_metrics($store->visibleConcerns($official['id']));
    reportCheck(array_intersect_key($all['metrics'],$originalMetrics)===$originalMetrics,'incremental report metrics match existing workflow metrics');
    reportCheck($all['metrics']['total']===66 && count($all['preview']['items'])===20,'all concerns counted and preview paginated');
    $second = $store->concernReport($official['id'],[],2);
    reportCheck($second['preview']['page']===2 && $second['preview']['items'][0]['id']!==$all['preview']['items'][0]['id'],'second preview page distinct');
    $filters=['period'=>'custom','start'=>'2026-10-01','end'=>'2026-10-09','category'=>'Street Lighting','priority'=>'High','personnel'=>'unassigned','location'=>(string)$location['id']];
    $matching=$store->concernReport($official['id'],$filters);
    reportCheck($matching['metrics']['total']===22,'combined filters intersect including legacy named location');
    $range=$store->concernReport($official['id'],['start'=>'2026-10-09','end'=>'2026-10-09']);
    reportCheck(in_array($ids[63],array_column($range['preview']['items'],'id'),true) && !in_array($ids[64],array_column($range['preview']['items'],'id'),true),'inclusive end date excludes next midnight');
    $options=$store->reportOptions($official['id']);
    foreach(['today','week','month','30days'] as $period) {
        $f=ConcernReportFilters::validate(['period'=>$period],$options,new DateTimeImmutable('2026-10-09T12:00:00+08:00'));
        reportCheck($f['end']>=$f['start'],'preset '.$period);
    }
    reportCheck(ConcernReportFilters::validate(['period'=>'week'],$options,new DateTimeImmutable('2026-10-09'))['start']==='2026-10-05','Monday-based Manila week');
    foreach([['start'=>'2026-02-30'],['start'=>'2026-10-10','end'=>'2026-10-01'],['category'=>'invented'],['category'=>'Street Lighting','type'=>'Pothole'],['status'=>'Closed'],['priority'=>'Critical'],['personnel'=>'unknown'],['location'=>'999999'],['team'=>'fake'],['search'=>['array']]] as $invalid) reportDenied(fn()=>$store->concernReport($official['id'],$invalid),'invalid filter '.json_encode($invalid));
    $search=$store->concernReport($official['id'],['search'=>$ids[0]]);
    reportCheck($search['metrics']['total']===1,'search by ID');
    reportCheck($store->concernReport($official['id'],['search'=>'SECRET-ANONYMOUS-IDENTITY'])['metrics']['total']===0,'search cannot reveal anonymous identity');
    reportCheck($store->concernReport($official['id'],['team'=>'unassigned'])['metrics']['total']===45,'unassigned teams');
    reportCheck($store->concernReport($official['id'],['personnel'=>$person['id'],'status'=>'In Progress'])['metrics']['total']===21,'assigned personnel and status');
    reportCheck($store->concernReport($official['id'],['keypoint'=>'Sparks visible'])['metrics']['total']===65,'keypoint excludes missing legacy value');
    reportDenied(fn()=>$store->concernReport($resident['id'],[],1,true),'resident PDF denied by store');
    reportDenied(fn()=>$store->concernReport($person['id'],[],1,true),'personnel PDF denied by store');
    $export=$store->concernReport($official['id'],[],2,true);
    reportCheck(count($export['preview']['items'])===66,'PDF ignores preview pagination and includes all matching records');
    $json=json_encode($export['preview']['items']);
    reportCheck(!str_contains($json,'SECRET-ANONYMOUS-IDENTITY') && !str_contains($json,'residentId') && !str_contains($json,'trackingCode'),'export projection excludes reporter identity and tracking');
    $html=ConcernReportPdf::html($export,new DateTimeImmutable('2026-10-09T13:00:00+08:00'));
    reportCheck(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>'),'report values escaped as text');
    $pdf=ConcernReportPdf::generate($export,new DateTimeImmutable('2026-10-09T13:00:00+08:00'));
    reportCheck(str_starts_with($pdf,'%PDF-') && str_contains($pdf,'%%EOF'),'real complete PDF binary');
    if(!is_dir(__DIR__.'/tmp')) mkdir(__DIR__.'/tmp',0700,true);
    file_put_contents(__DIR__.'/tmp/reporting-full.pdf',$pdf);
    $filteredExport=$store->concernReport($official['id'],$filters,1,true);
    file_put_contents(__DIR__.'/tmp/reporting-filtered.pdf',ConcernReportPdf::generate($filteredExport));
    $empty=$store->concernReport($official['id'],['search'=>'NO-MATCH'],1,true);
    reportCheck($empty['metrics']['total']===0 && $empty['preview']['items']===[],'empty PDF never falls back to all records');
    file_put_contents(__DIR__.'/tmp/reporting-empty.pdf',ConcernReportPdf::generate($empty));
    reportCheck((new DatabaseTestFixtures($test->connect()))->dataChecksums()===$before,'report generation is database read-only');
    $oldLimit=ini_get('memory_limit');
    try {
        ini_set('memory_limit',(string)(memory_get_usage(true)+24*1048576));
        reportDenied(fn()=>ConcernReportPdf::generate($export),'low-memory export rejected before rendering, never truncated');
    } finally { ini_set('memory_limit',$oldLimit); }
    for($i=66;$i<=2000;$i++) {
        $c=$source; $c['id']='CON-2026-'.str_pad((string)(1000+$i),6,'0',STR_PAD_LEFT); $db->insertComplaint($c);
    }
    $large=$store->concernReport($official['id'],[],100);
    reportCheck($large['metrics']['total']===2001 && $large['preview']['total']===2001 && count($large['preview']['items'])===20,'large dataset counted across every 250-record retrieval batch');
    $oldLimit=ini_get('memory_limit');
    try {
        ini_set('memory_limit','128M');
        reportDenied(fn()=>$store->concernReport($official['id'],[],1,true),'host-budget oversized PDF refused explicitly, never silently capped');
    } finally { ini_set('memory_limit',$oldLimit); }
    $test->assertHealthyLog();
    echo 'PASS: '.$checks.' reporting/filter/PDF checks. QA PDFs in tests/tmp; disposable data only.',PHP_EOL;
} finally { $test->drop(); }
