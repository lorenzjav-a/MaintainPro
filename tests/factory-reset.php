<?php
declare(strict_types=1);
require __DIR__.'/support/database.php';
require dirname(__DIR__).'/includes/store.php';

$test=new TestDatabase();
$previous=getenv('APP_SETUP_KEY');
$setupKey=str_repeat('r',64);
putenv('APP_SETUP_KEY='.$setupKey);
$checks=0;
$check=static function(bool $ok,string $label) use(&$checks): void { if(!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; };
$denied=static function(callable $work,string $label) use($check): void { try{$work();}catch(DomainException){$check(true,$label);return;} throw new RuntimeException('FAIL: expected rejection: '.$label); };
$password='Factory-reset-password-42';
try {
    $store=new ComplaintStore($test->connect());
    $admin=$store->setup(['name'=>'Reset Administrator','email'=>'reset-admin@example.test','password'=>$password,'setup_key'=>$setupKey]);
    $resident=$store->register(['name'=>'Reset Resident','email'=>'reset-resident@example.test','password'=>$password]);
    $store->createLocation($admin['id'],['name'=>'Reset Area','sortOrder'=>0]);
    $location=(string)$store->locations()[0]['id'];
    $report=['category'=>'Street Lighting','concernType'=>'Light not working','keyPoints'=>[],'purok'=>'forged','locationId'=>$location,'street'=>'Reset Street','exactArea'=>'Reset Gate'];
    $receipt=$store->submitGuest($report,'factory-reset-test');
    $denied(fn()=>$store->factoryReset($resident['id'],['current_password'=>$password,'confirmation'=>'RESET MAINTAINPRO']),'non-administrator cannot factory reset');
    $denied(fn()=>$store->factoryReset($admin['id'],['current_password'=>'wrong','confirmation'=>'RESET MAINTAINPRO']),'factory reset requires current password');
    $denied(fn()=>$store->factoryReset($admin['id'],['current_password'=>$password,'confirmation'=>'reset maintainpro']),'factory reset requires exact phrase');
    $result=$store->factoryReset($admin['id'],['current_password'=>$password,'confirmation'=>'RESET MAINTAINPRO']);
    $check($result['failedUploads']===0 && $store->needsSetup(),'factory reset returns workspace to first-time setup');
    $denied(fn()=>$store->login($admin['email'],$password,'after-reset'),'removed administrator cannot sign in');
    $replacement=$store->setup(['name'=>'Replacement Administrator','email'=>$admin['email'],'password'=>$password,'setup_key'=>$setupKey]);
    $check($replacement['is_system_admin'] && count($store->users($replacement['id']))===1,'first setup can recreate one system administrator');
    $category=array_key_first(ConcernCatalog::TYPES); $type=ConcernCatalog::TYPES[$category][0]; $point=ConcernCatalog::POINTS[$category][0];
    $check(count($store->officialRules($replacement['id'],$category,$type,$point))===3,'built-in official solution library is restored');
    $new=$store->submitGuest(array_replace($report,['locationId'=>'','purok'=>'Fresh Area']),'after-factory-reset');
    $check($new['reference']==='CON-'.date('Y').'-000001','concern numbering restarts at one');
    echo "PASS: $checks factory-reset authorization, confirmation, cleanup, setup and numbering checks.\n";
} finally {
    unset($store);
    $test->drop();
    if($previous===false) putenv('APP_SETUP_KEY'); else putenv('APP_SETUP_KEY='.$previous);
}
