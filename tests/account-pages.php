<?php
declare(strict_types=1);
if (!isset($testDatabase, $adminJar)) throw new RuntimeException('Run tests/http.php --accounts-only.');

foreach (['assets/vendor/sweetalert2.all.min.js', 'assets/vendor/bootstrap.min.css', 'assets/js/app.js'] as $path) {
    $asset = req($guestJar, $path);
    httpCheck($asset['status'] === 200 && $asset['body'] === file_get_contents(dirname(__DIR__) . '/' . $path), 'public browser dependency: ' . $path);
}
foreach (['vendor/phpmailer/src/PHPMailer.php', 'config/mail.local.php', 'includes/store.php', 'database/database.php', '.data/', 'uploads/'] as $path) {
    httpCheck(req($guestJar, $path)['status'] === 404, 'private path remains blocked: ' . $path);
}
$data = ['name'=>'Account Test Personnel','email'=>'personnel@example.test','role'=>'personnel','team'=>'Maintenance crew'];
httpCheck(post($adminJar,'create_user',$data,'')['status']===403,'missing CSRF rejected');
httpCheck(post($guestJar,'create_user',$data,'')['status']===401,'guest creation rejected');
$created=post($adminJar,'create_user',$data,$adminCsrf);
httpCheck($created['status']===200,'personnel creation succeeds with audit write');
$account=$created['json']['created_account'];
httpCheck($account['role']==='personnel' && $account['must_change_password'] && str_starts_with($account['temporary_password'],'MP-'),'one-time credential and onboarding requirement');
httpCheck(post($adminJar,'create_user',$data,$adminCsrf)['status']===422,'duplicate email rejected');
httpCheck(auth($staffJar,'login',['email'=>$data['email'],'password'=>$account['temporary_password']],token($staffJar,'login.php'))['status']===200,'temporary personnel login');
httpCheck(req($staffJar,'api.php')['status']===403,'temporary account gated');
httpCheck(auth($staffJar,'change_password',['current_password'=>$account['temporary_password'],'password'=>$password,'confirm_password'=>$password],token($staffJar,'login.php'))['status']===200,'personnel changes temporary password');
$staffCsrf=token($staffJar);
httpCheck(post($staffJar,'create_user',array_replace($data,['email'=>'unauthorized@example.test']),$staffCsrf)['status']===422,'personnel cannot create accounts');
httpCheck(req($staffJar,'user-create.php')['status']===403,'personnel direct URL denied');
httpCheck(post($adminJar,'update_user',array_replace($data,['name'=>'Updated Personnel','active'=>'1']),$adminCsrf,$account['id'])['status']===200,'account editing retains audit write');
httpCheck(str_contains(req($adminJar,'users.php')['body'],'Updated Personnel'),'created account persisted');
require_once dirname(__DIR__) . '/database/database.php';
DatabaseMaintenance::initialize($testDatabase->connect());
httpCheck(str_contains(req($adminJar,'users.php')['body'],'Updated Personnel'),'migration repeat preserves accounts');

// Re-signing in shares cookies with older tabs but must not accept their old token.
$oldToken=$adminCsrf;
httpCheck(auth($adminJar,'login',$adminData,$adminCsrf)['status']===200,'same account reauthentication');
$session=req($adminJar,'api.php?view=session');
httpCheck($session['status']===200 && preg_match('/^[a-f0-9]{64}$/',$session['json']['csrf']) && $session['json']['csrf']!==$oldToken,'authenticated session refresh returns rotated token');
httpCheck(req($guestJar,'api.php?view=session')['status']===401,'anonymous token probe rejected');
$expired=post($adminJar,'update_user',array_replace($data,['active'=>'1']),$oldToken,$account['id']);
httpCheck($expired['status']===403 && $expired['json']['code']==='csrf_expired','old tab token fails with recovery code');
$adminCsrf=$session['json']['csrf'];
httpCheck(post($adminJar,'update_user',array_replace($data,['active'=>'1']),$adminCsrf,$account['id'])['status']===200,'same account can explicitly save with refreshed token');

// Seed only the disposable DB: isolate assignment from unrelated public reporting changes.
require_once dirname(__DIR__) . '/includes/domain.php';
$fixtureState=['nextId'=>1,'cases'=>[]];
$concernId=ComplaintWorkflow::submit($fixtureState,['id'=>null,'role'=>'guest','name'=>'Anonymous resident'],['category'=>'Street Lighting','concernType'=>'Light not working','keyPoints'=>[],'purok'=>'Test area','street'=>'Test street','exactArea'=>'Test gate']);
$fixture=$fixtureState['cases'][0]; $fixture['version']=1;
$fixture['status']='Under Review'; $fixture['recommendation']='Inspect safely.';
$db=new MaintainProDatabase($testDatabase->connect());
$db->insertComplaint($fixture);
$assigned=post($adminJar,'assign',['personnelId'=>$account['id']],$adminCsrf,$concernId,1);
httpCheck($assigned['status']===200 && $assigned['json']['notification_sent']===true,'assignment saves and sends local test email');
$saved=json_decode($db->complaint($concernId)['payload'],true);
httpCheck($saved['assignedUserId']===$account['id'] && $saved['version']===2,'assignment owner and version saved');
httpCheck(post($adminJar,'assign',['personnelId'=>$account['id']],$adminCsrf,$concernId,1)['status']===409,'session refresh does not bypass stale concern version');
httpCheck(post($staffJar,'assign',['personnelId'=>$account['id']],$staffCsrf,$concernId,2)['status']===422,'personnel cannot assign work');
$saved['linkedPrimaryId']='CON-2026-999999'; $db->updateComplaint($saved);
httpCheck(post($adminJar,'assign',['personnelId'=>$account['id']],$adminCsrf,$concernId,2)['status']===422,'linked concerns still deny independent assignment');
echo "PASS: $checks account creation, onboarding, authorization, CSRF, asset and migration checks.\n";
