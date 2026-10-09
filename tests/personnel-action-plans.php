<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/store.php';
require __DIR__.'/support/database.php';
$test=new TestDatabase(); $checks=0;
function planCheck(bool $ok,string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; }
function planDenied(callable $call,string $label): void { try { $call(); } catch (DomainException) { planCheck(true,$label); return; } throw new RuntimeException('FAIL: '.$label); }
try {
    $store=new ComplaintStore($test->connect());
    $official=$store->setup(['name'=>'Plan Official','email'=>'plan-official@example.test','password'=>'Plan-password-42']);
    $staff=$store->createUser($official['id'],['name'=>'Plan Personnel','email'=>'plan-staff@example.test','role'=>'personnel','team'=>'Maintenance crew']);
    $staff=activateInvitedUser($store,$staff,'Plan-password-42');
    $other=$store->createUser($official['id'],['name'=>'Other Personnel','email'=>'other-staff@example.test','role'=>'personnel','team'=>'Maintenance crew']);
    $other=activateInvitedUser($store,$other,'Plan-password-42');
    $otherTeam=$store->createUser($official['id'],['name'=>'Sanitation Personnel','email'=>'sanitation-staff@example.test','role'=>'personnel','team'=>'Sanitation team']);
    $otherTeam=activateInvitedUser($store,$otherTeam,'Plan-password-42');
    $resident=$store->register(['name'=>'Plan Resident','email'=>'plan-resident@example.test','password'=>'Plan-password-42']);
    $rule=$store->officialRules($official['id'],'Street Lighting','Exposed wiring','Sparks visible')[0];
    $data=['ruleId'=>$rule['id'],'ruleVersion'=>$rule['version'],'requestKey'=>bin2hex(random_bytes(32)),
        'title'=>'Inspect exposed wiring','notes'=>'Check the public fixture.','team'=>'Maintenance crew','personnelId'=>$staff['id'],'targetDate'=>date('Y-m-d',time()+86400)];
    $id=$store->saveActionPlan($official['id'],0,$data);
    planCheck($store->myActionPlans($staff['id'])['total']===1 && $store->visibleActionPlan($staff['id'],$id)!==null,'assigned personnel sees plan');
    $teamData=$data;
    $teamData['requestKey']=bin2hex(random_bytes(32));
    $teamData['personnelId']='';
    $teamPlan=$store->saveActionPlan($official['id'],0,$teamData);
    $staffTeamNotices=array_values(array_filter($store->notifications($staff['id'])['items'],fn($notice)=>$notice['type']==='plan_team_assignment'));
    $otherTeamNotices=array_values(array_filter($store->notifications($other['id'])['items'],fn($notice)=>$notice['type']==='plan_team_assignment'));
    $wrongTeamNotices=array_values(array_filter($store->notifications($otherTeam['id'])['items'],fn($notice)=>$notice['type']==='plan_team_assignment'));
    planCheck(count($staffTeamNotices)===1 && count($otherTeamNotices)===1 && $wrongTeamNotices===[],'team-only plan notifies every active verified personnel in the responsible team only');
    planCheck(str_contains($staffTeamNotices[0]['message'],'Maintenance crew') && str_contains($staffTeamNotices[0]['target_url'],'id='.$teamPlan),'team-only notification identifies the team work and links to action plans');
    planCheck($store->myActionPlans($staff['id'])['total']===2 && $store->myActionPlans($other['id'])['total']===1 && $store->myActionPlans($otherTeam['id'])['total']===0,'personnel lists include personal plans plus team-only plans for their own team');
    planCheck($store->visibleActionPlan($staff['id'],$teamPlan)!==null && $store->visibleActionPlan($other['id'],$teamPlan)!==null && $store->visibleActionPlan($otherTeam['id'],$teamPlan)===null,'team-only plan detail is restricted to responsible-team personnel');
    $store->savePersonnelActionPlan($other['id'],$teamPlan,['step'=>'start','version'=>1,'note'=>'Team inspection started.']);
    planCheck($store->visibleActionPlan($staff['id'],$teamPlan)['status']==='Ongoing','one team member update is visible to the whole responsible team');
    planDenied(fn()=> $store->myActionPlans($resident['id']),'resident has no personnel plan list');
    planCheck($store->visibleActionPlan($other['id'],$id)===null,'other personnel cannot open assigned plan');
    planDenied(fn()=> $store->savePersonnelActionPlan($other['id'],$id,['step'=>'start','version'=>1]),'other personnel cannot update plan');
    $png='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=';
    $store->savePersonnelActionPlan($staff['id'],$id,['step'=>'start','version'=>1,'note'=>'Started inspection.']);
    planCheck($store->visibleActionPlan($staff['id'],$id)['status']==='Ongoing','personnel starts assigned work');
    planDenied(fn()=> $store->savePersonnelActionPlan($staff['id'],$id,['step'=>'complete','version'=>1,'note'=>'Done']),'stale plan version refused');
    $store->savePersonnelActionPlan($staff['id'],$id,['step'=>'progress','version'=>2,'note'=>'Fault isolated.','photo'=>$png,'photoName'=>'proof.png']);
    $progress=$store->visibleActionPlan($staff['id'],$id)['progress'];
    $evidence=end($progress)['evidence_id'];
    planCheck(count($progress)===2 && $evidence!==null && $store->planEvidenceRecord($staff['id'],$evidence)!==null,'progress and private evidence saved');
    planCheck($store->planEvidenceRecord($other['id'],$evidence)===null && $store->planEvidenceRecord($resident['id'],$evidence)===null,'unassigned users cannot read plan evidence');
    $store->savePersonnelActionPlan($staff['id'],$id,['step'=>'complete','version'=>3,'note'=>'Fixture repaired and tested.']);
    $completed=$store->visibleActionPlan($staff['id'],$id);
    planCheck($completed['status']==='Completed' && $completed['outcome']==='Fixture repaired and tested.','completion retains outcome');
    planDenied(fn()=> $store->savePersonnelActionPlan($staff['id'],$id,['step'=>'progress','version'=>4,'note'=>'Another note']),'completed plan cannot receive personnel updates');
    $data['requestKey']=bin2hex(random_bytes(32));
    $activePlan=$store->saveActionPlan($official['id'],0,$data);
    $report=$store->submitAccount($resident['id'],['category'=>'Street Lighting','concernType'=>'Exposed wiring','keyPoints'=>['Sparks visible'],'purok'=>'Purok One','street'=>'Main Road','exactArea'=>'Public pole']);
    $store->mutate($official['id'],'assess',$report,['priority'=>'High','recommendation'=>'Inspect the pole.'],1);
    $store->mutate($official['id'],'assign',$report,['team'=>'Maintenance crew'],2);
    $store->mutate($staff['id'],'accept_work',$report,[],null);
    $change=['name'=>$staff['name'],'email'=>$staff['email'],'role'=>'personnel','team'=>$staff['team'],'active'=>'0'];
    planDenied(fn()=> $store->updateUser($official['id'],$staff['id'],$change),'deactivation refuses active assigned work without replacement');
    planCheck($store->assignedWorkCounts($official['id'],$staff['id'])===['concerns'=>1,'actionPlans'=>1],'warning counts both types of active work');
    $store->updateUser($official['id'],$staff['id'],$change+['reassignTo'=>$other['id']]);
    planCheck($store->actor($staff['id'])===null && $store->visibleActionPlan($other['id'],$activePlan)!==null,'deactivation atomically moves active plan');
    planCheck($store->concernForActor($other['id'],$report)['assignedUserId']===$other['id'] && $store->assignedWorkCounts($official['id'],$staff['id'])===['concerns'=>0,'actionPlans'=>0],'active concern reassigned without deleting history');
    echo "PASS: $checks personnel action-plan access, progress, evidence, version and completion checks.\n";
} finally { $test->drop(); }
