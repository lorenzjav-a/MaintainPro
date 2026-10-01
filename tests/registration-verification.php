<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/store.php';
require __DIR__.'/support/database.php';

$test=new TestDatabase();
$checks=0;
function registrationCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    $checks++;
}
function registrationDenied(callable $call, string $label): void
{
    try { $call(); }
    catch (DomainException) { registrationCheck(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
try {
    $db=$test->connect();
    $store=new ComplaintStore($db);
    $fixture=new DatabaseTestFixtures($db);
    $official=$store->setup(['name'=>'Test Official','email'=>'official@example.test','password'=>'Secure-pass-42']);
    $delivered=[];
    $send=function(string $email,string $code) use (&$delivered): void { $delivered[]=[$email,$code]; };
    $data=['name'=>'New Resident','email'=>'new@example.test','password'=>'Secure-pass-42'];
    $challenge=$store->requestRegistration($data,'shared-household-ip',$send);
    $pending=array_values(array_filter($store->users($official['id']),fn($u)=>$u['email']==='new@example.test'))[0];
    registrationCheck(!$pending['email_verified'] && count($delivered)===1,'new resident remains pending and receives one code');
    registrationDenied(fn()=> $store->login($data['email'],$data['password'],'pending-ip'),'unverified resident cannot sign in');
    registrationDenied(fn()=> $store->verifyRegistration($challenge,'000000'),'invalid code refused');
    registrationDenied(fn()=> $store->resendRegistration($challenge,'shared-household-ip',$send),'immediate resend limited');
    $fixture->ageRegistrationChallenge($challenge,61);
    $store->resendRegistration($challenge,'shared-household-ip',$send);
    registrationCheck(count($delivered)===2 && $delivered[0][1]!==$delivered[1][1],'resend rotates the code');
    registrationDenied(fn()=> $store->verifyRegistration($challenge,$delivered[0][1]),'old code cannot verify');
    $user=$store->verifyRegistration($challenge,$delivered[1][1]);
    registrationCheck($user['email_verified'] && $store->login($data['email'],$data['password'],'verified-ip')['id']===$user['id'],'verification enables resident login');
    registrationDenied(fn()=> $store->verifyRegistration($challenge,$delivered[1][1]),'verification code is single use');
    $duplicate=$store->requestRegistration($data,'other-ip',$send);
    registrationCheck(strlen($duplicate)===64 && count($delivered)===2,'existing account receives generic registration response without email');
    $other=['name'=>'Another Resident','email'=>'another@example.test','password'=>'Another-pass-42'];
    $expired=$store->requestRegistration($other,'other-ip',$send);
    $fixture->expireRegistrationChallenge($expired);
    registrationDenied(fn()=> $store->verifyRegistration($expired,$delivered[2][1]),'expired code refused');
    $fixture->ageRegistrationChallenge($expired,61);
    $fixture->ageRegistrationRequests($other['email'],61);
    $resumed=$store->requestRegistration($other,'other-ip',$send);
    registrationCheck($resumed!==$expired && count($delivered)===4,'pending registration resumes with password and new code');
    registrationDenied(fn()=> $store->verifyRegistration($expired,$delivered[2][1]),'replaced challenge invalid');
    registrationCheck($store->verifyRegistration($resumed,$delivered[3][1])['email_verified'],'resumed registration verifies');
    echo "PASS: $checks resident email verification checks.\n";
} finally {
    $test->drop();
}
