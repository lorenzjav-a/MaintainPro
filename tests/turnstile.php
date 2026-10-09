<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/security.php';
require dirname(__DIR__) . '/includes/turnstile.php';

$checks=0;
$check=static function(bool $ok,string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    $checks++;
};
$rejected=static function(TurnstileVerifier $verifier,mixed $token,string $action,string $reason,string $label) use($check): void {
    try { $verifier->verify($token,$action,'203.0.113.10'); }
    catch(TurnstileException $error) { $check($error->reason===$reason,$label); return; }
    throw new RuntimeException('FAIL: expected Turnstile rejection: '.$label);
};
$response=['success'=>true,'hostname'=>'www.maintainprosystem.online','action'=>'login','error-codes'=>[]];
$payload=null;
$valid=new TurnstileVerifier('private-secret',['maintainprosystem.online','www.maintainprosystem.online'],static function(array $sent) use(&$payload,$response): array { $payload=$sent; return $response; });
$valid->verify('valid-token','login','203.0.113.10');
$check($payload['secret']==='private-secret' && $payload['response']==='valid-token' && $payload['remoteip']==='203.0.113.10','Siteverify payload includes secret token and valid client IP');
$rejected($valid,'','login','missing','missing token rejected');
$rejected(new TurnstileVerifier('secret',['www.maintainprosystem.online'],fn()=>['success'=>false,'error-codes'=>['invalid-input-response']]),'bad','login','invalid','invalid token rejected');
$rejected(new TurnstileVerifier('secret',['www.maintainprosystem.online'],fn()=>['success'=>false,'error-codes'=>['timeout-or-duplicate']]),'used','login','expired_or_reused','expired or reused token rejected');
$rejected(new TurnstileVerifier('secret',['www.maintainprosystem.online'],fn()=>['success'=>true,'hostname'=>'evil.example','action'=>'login']),'token','login','hostname_mismatch','unapproved hostname rejected');
$rejected(new TurnstileVerifier('secret',['www.maintainprosystem.online'],fn()=>['success'=>true,'hostname'=>'www.maintainprosystem.online','action'=>'register']),'token','login','action_mismatch','cross-form token rejected');
$rejected(new TurnstileVerifier('secret',['www.maintainprosystem.online'],fn()=>throw new RuntimeException('offline')),'token','login','unavailable','Siteverify outage fails closed');
$check(br_turnstile_action('login')==='login' && br_turnstile_action('register')==='register' && br_turnstile_action('request_reset')==='password_recovery' && br_turnstile_action('verify_reset')===null,'only target authentication entry actions require Turnstile');
echo "PASS: $checks Turnstile token, replay, hostname, action and outage checks.\n";
