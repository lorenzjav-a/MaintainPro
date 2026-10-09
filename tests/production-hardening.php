<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/store.php';
require __DIR__ . '/support/database.php';
$testDatabase=new TestDatabase();
$store=new ComplaintStore($testDatabase->connect());
$checks=0;
$check=static function(bool $ok,string $label) use (&$checks): void { if (!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; };
$denied=static function(callable $work,string $label) use ($check): void { try { $work(); } catch (DomainException) { $check(true,$label); return; } throw new RuntimeException('FAIL: expected rejection: '.$label); };
$password='Production-security-password-42';
try {
    $user=$store->setup(['name'=>'Security Official','email'=>'security@example.test','password'=>$password]);
    $denied(fn()=>$store->requestEmailChange($user['id'],'new-security@example.test','wrong',static function(){}),'email change requires current password');
    $sent=[];
    $challenge=$store->requestEmailChange($user['id'],'new-security@example.test',$password,static function(string $email,string $code) use (&$sent): void { $sent=[$email,$code]; });
    $check(strlen($challenge)===64 && ($sent[0] ?? '')==='new-security@example.test' && preg_match('/\A[0-9]{6}\z/',$sent[1] ?? '')===1,'email change sends an expiring six digit code');
    $check($store->user($user['id'])['email']==='security@example.test','original email remains active while pending');
    $denied(fn()=>$store->verifyEmailChange($user['id'],$challenge,'000000'),'incorrect email change code rejected');
    $changed=$store->verifyEmailChange($user['id'],$challenge,$sent[1]);
    $check($changed['new_email']==='new-security@example.test' && $store->user($user['id'])['email']==='new-security@example.test','verified email replaces original address');
    $denied(fn()=>$store->verifyEmailChange($user['id'],$challenge,$sent[1]),'email change code cannot be replayed');
    $database=new MaintainProDatabase($testDatabase->connect());
    $counts=$database->cleanupExpiredSecurityRecords(time()+86400);
    $check(isset($counts['email_changes'],$counts['login_attempts']),'scheduled cleanup covers temporary security records');
    $check(str_contains(file_get_contents(dirname(__DIR__).'/.env.example'),'DB_USER=maintainpro_app') && !str_contains(file_get_contents(dirname(__DIR__).'/config/mail.example.php'),'gmail.com'),'release configuration contains placeholders only');
    $canonical=['url'=>'https://www.maintainprosystem.online','production'=>true,'force_https'=>true];
    $check(br_normalize_app_url('https://maintainprosystem.online/')==='https://www.maintainprosystem.online','legacy configured non-www URL normalizes to canonical production origin');
    $legacyTarget=br_canonical_redirect_target($canonical,'maintainprosystem.online','/account-setup?token='.str_repeat('a',64),true);
    $check($legacyTarget==='https://www.maintainprosystem.online/account-setup?token='.str_repeat('a',64),'legacy non-www redirect preserves invitation route and token');
    $check(br_canonical_redirect_target($canonical,'www.maintainprosystem.online','/account-setup?token='.str_repeat('b',64),true)===null,'canonical HTTPS invitation avoids redirect');
    $check(br_canonical_redirect_target($canonical,'www.maintainprosystem.online','/account-setup?token='.str_repeat('c',64),false)==='https://www.maintainprosystem.online/account-setup?token='.str_repeat('c',64),'HTTPS redirect preserves invitation token');
    echo "PASS: $checks production configuration, canonical routing, reauthentication, email verification and cleanup checks.\n";
} finally {
    unset($store);
    $testDatabase->drop();
}
