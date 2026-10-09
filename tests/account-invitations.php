<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/store.php';
require __DIR__.'/support/database.php';

if (($argv[1] ?? '')==='--worker') {
    DatabaseMaintenance::requireTestDatabase($argv[2] ?? '');
    $store=new ComplaintStore(br_database($argv[2]));
    try {
        $store->acceptInvitation($argv[3] ?? '',['password'=>$argv[4] ?? '','confirm_password'=>$argv[4] ?? '']);
        echo 'accepted';
    } catch (DomainException) { echo 'denied'; }
    exit;
}

$test=new TestDatabase(); $checks=0;
function invitationCheck(bool $ok,string $label): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; }
function invitationDenied(callable $call,string $label): void { try { $call(); } catch (DomainException) { invitationCheck(true,$label); return; } throw new RuntimeException('FAIL: expected denial: '.$label); }

try {
    $pdo=$test->connect(); $store=new ComplaintStore($pdo); $fixtures=new DatabaseTestFixtures($pdo);
    $admin=$store->setup(['name'=>'Invitation Admin','email'=>'admin@example.test','password'=>'Admin-password-42']);
    $invited=$store->createUser($admin['id'],['name'=>'Invited Worker','email'=>'worker@example.test','role'=>'personnel','team'=>'Maintenance crew']);
    invitationCheck($invited['pending_setup'] && !$invited['email_verified'] && !$invited['must_change_password'],'new account is Pending Setup');
    invitationCheck($store->actor($invited['id'])===null && count((new MaintainProDatabase($pdo))->activePersonnelForTeam('Maintenance crew'))===0,'pending account is not operational team capacity');
    invitationDenied(fn()=>$store->login($invited['email'],'Worker-password-42','pending-login'),'pending account cannot sign in');
    $sent=false;
    $store->requestPasswordReset($invited['email'],'pending-reset',function() use (&$sent): void { $sent=true; });
    invitationCheck(!$sent,'pending setup cannot bypass invitation through password reset');

    $row=$fixtures->invitation($invited['id']);
    invitationCheck($row['token_hash']===hash('sha256',$invited['invitation_token']) && !hash_equals($row['token_hash'],$invited['invitation_token']),'database stores only the invitation token hash');
    invitationCheck((int)$row['lifetime']===86400,'invitation lifetime is 24 hours');
    invitationCheck($store->inspectInvitation($invited['invitation_token'])['status']==='valid','valid invitation can be inspected safely');
    invitationDenied(fn()=>$store->acceptInvitation($invited['invitation_token'],['password'=>'Worker-password-42','confirm_password'=>'Mismatch-password-42']),'password confirmation required');

    $store->recordInvitationDelivery($admin['id'],$invited['id'],(int)$invited['invitation_id'],true);
    invitationCheck($store->user($invited['id'])['invitation_delivery_status']==='sent','accepted SMTP delivery is recorded');
    invitationDenied(fn()=>$store->resendInvitation($admin['id'],$invited['id']),'successful invitation has resend cooldown');
    $fixtures->elapseInvitationCooldown($invited['id']);
    $replacement=$store->resendInvitation($admin['id'],$invited['id']);
    invitationCheck($store->inspectInvitation($invited['invitation_token'])['status']==='revoked','resend revokes the earlier link');
    invitationDenied(fn()=>$store->acceptInvitation($invited['invitation_token'],['password'=>'Worker-password-42','confirm_password'=>'Worker-password-42']),'revoked invitation rejected');
    $active=activateInvitedUser($store,$replacement,'Worker-password-42');
    invitationCheck($active['email_verified'] && !$active['pending_setup'] && $store->login($active['email'],'Worker-password-42','active-login')['id']===$active['id'],'accepted invitation activates normal login');
    invitationDenied(fn()=>$store->acceptInvitation($replacement['invitation_token'],['password'=>'Another-password-42','confirm_password'=>'Another-password-42']),'used invitation rejected');
    invitationDenied(fn()=>$store->resendInvitation($admin['id'],$active['id']),'active account cannot receive setup invitation');

    $expired=$store->createUser($admin['id'],['name'=>'Expired Official','email'=>'expired@example.test','role'=>'official']);
    $fixtures->expireInvitation($expired['id']);
    invitationCheck($store->inspectInvitation($expired['invitation_token'])['status']==='expired','expired invitation has explicit state');
    invitationDenied(fn()=>$store->acceptInvitation($expired['invitation_token'],['password'=>'Expired-password-42','confirm_password'=>'Expired-password-42']),'expired invitation rejected');

    $inactive=$store->createUser($admin['id'],['name'=>'Inactive Resident','email'=>'inactive@example.test','role'=>'resident']);
    $store->updateUser($admin['id'],$inactive['id'],['name'=>$inactive['name'],'email'=>$inactive['email'],'role'=>'resident','active'=>'0']);
    invitationDenied(fn()=>$store->acceptInvitation($inactive['invitation_token'],['password'=>'Inactive-password-42','confirm_password'=>'Inactive-password-42']),'deactivated pending account rejected');

    $race=$store->createUser($admin['id'],['name'=>'Concurrent Invitee','email'=>'race@example.test','role'=>'personnel','team'=>'Maintenance crew']);
    $workers=[];
    for ($i=0;$i<2;$i++) {
        $process=proc_open([PHP_BINARY,__FILE__,'--worker',$test->name,$race['invitation_token'],'Race-password-42'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true,'create_no_window'=>true]);
        if (!is_resource($process)) throw new RuntimeException('Cannot start invitation concurrency worker.');
        fclose($pipes[0]); $workers[]=[$process,$pipes];
    }
    $accepted=0;
    foreach ($workers as [$process,$pipes]) {
        $output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        invitationCheck(proc_close($process)===0 && $errors==='','concurrent invitation worker completed');
        if ($output==='accepted') $accepted++;
    }
    invitationCheck($accepted===1 && $store->login($race['email'],'Race-password-42','race-login')['id']===$race['id'],'simultaneous redemption activates exactly once');
    $actions=array_column($store->auditLogs($admin['id'],[],1,100)['items'],'action');
    invitationCheck(in_array('account_invitation_accepted',$actions,true) && in_array('account_invitation_sent',$actions,true),'invitation delivery and acceptance are audited');
    echo "PASS: $checks secure account invitation checks.\n";
} finally { $test->drop(); }
