<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/store.php';
require __DIR__.'/support/database.php';
$test=new TestDatabase(); $checks=0;
function teamCheck(bool $condition,string $label): void { global $checks; if(!$condition) throw new RuntimeException('FAIL: '.$label); $checks++; }
function teamDenied(callable $work,string $label): void { try{$work();}catch(DomainException){teamCheck(true,$label);return;} throw new RuntimeException('FAIL: expected denial: '.$label); }
try {
    $store=new ComplaintStore($test->connect());
    $official=$store->setup(['name'=>'Team Official','email'=>'team-official@example.test','password'=>'Team-password-42']);
    $make=function(string $name,string $email,string $team) use($store,$official): array {
        $user=$store->createUser($official['id'],['name'=>$name,'email'=>$email,'role'=>'personnel','team'=>$team]);
        return activateInvitedUser($store,$user,'Team-password-42');
    };
    $first=$make('First Worker','first-worker@example.test','Maintenance crew');
    $second=$make('Second Worker','second-worker@example.test','Maintenance crew');
    $other=$make('Other Team','other-team@example.test','Sanitation team');
    $report=['category'=>'Street Lighting','concernType'=>'Light not working','keyPoints'=>['Completely dark'],'purok'=>'Purok 1','street'=>'Test Street','exactArea'=>'Near hall'];
    $id=$store->submitGuest($report,'team-offer')['reference'];
    $store->mutate($official['id'],'assess',$id,['priority'=>'High','recommendation'=>'Inspect and repair safely.'],1);
    $store->mutate($official['id'],'assign',$id,['team'=>'Maintenance crew','dueAt'=>date('Y-m-d\TH:i',time()+86400)],2);
    $assigned=$store->concernForActor($official['id'],$id);
    teamCheck($assigned['team']==='Maintenance crew' && empty($assigned['assignedUserId']) && $assigned['status']==='Assigned','official assigns team without selecting a person');
    teamCheck($assigned['teamAssignment']['notified']===2 && $assigned['teamAssignment']['pending']===2,'official sees notified and pending counts');
    teamCheck(count($store->pendingTeamOffers($first['id']))===1 && count($store->pendingTeamOffers($second['id']))===1,'eligible team personnel receive pending offers');
    teamCheck($store->pendingTeamOffers($other['id'])===[],'other team receives no offer');
    teamCheck(count(array_filter($store->notifications($first['id'])['items'],fn($notice)=>$notice['type']==='team_assignment'))===1,'eligible personnel receives in-app notification');
    teamCheck(count(array_filter($store->notifications($other['id'])['items'],fn($notice)=>$notice['type']==='team_assignment'))===0,'other team receives no notification');
    teamDenied(fn()=>$store->mutate($other['id'],'accept_work',$id,[],null),'other team cannot accept');
    $store->mutate($first['id'],'decline_work',$id,[],null);
    $progress=$store->concernForActor($official['id'],$id)['teamAssignment'];
    teamCheck($progress['declined']===1 && $progress['pending']===1,'decline response is recorded for official monitoring');
    $store->mutate($second['id'],'accept_work',$id,[],null);
    $accepted=$store->concernForActor($official['id'],$id);
    teamCheck($accepted['assignedUserId']===$second['id'] && $accepted['assignedName']===$second['name'],'first valid acceptance becomes responsible worker');
    teamCheck($store->pendingTeamOffers($first['id'])===[] && $store->pendingTeamOffers($second['id'])===[],'accepted offer closes the team queue');
    teamDenied(fn()=>$store->mutate($first['id'],'accept_work',$id,[],null),'closed offer cannot be accepted again');
    teamCheck($store->concernForActor($second['id'],$id)['canWork'] && $store->concernForActor($first['id'],$id)===null,'accepted worker gains access and declined worker does not');
    teamCheck($store->auditLogs($official['id'],['action'=>'team_assignment_accepted'])['total']===1 && $store->auditLogs($official['id'],['action'=>'team_assignment_declined'])['total']===1,'acceptance and decline are audited');
    echo "PASS: $checks team assignment, notification, response and authorization checks.\n";
} finally { $test->drop(); }
