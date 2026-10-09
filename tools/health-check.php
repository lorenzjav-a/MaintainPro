<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/database/database.php';
$checks=[];
$add=static function(string $label,bool $ok,string $detail='') use (&$checks): void { $checks[]=[$label,$ok,$detail]; };
$add('PHP 8.2+',version_compare(PHP_VERSION,'8.2.0','>='),PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION);
foreach (['pdo','pdo_mysql','mbstring','openssl','json','fileinfo','session','phar','dom'] as $extension) $add('Extension '.$extension,extension_loaded($extension));
$add('Bundled PDF renderer',is_file(dirname(__DIR__).'/vendor/dompdf/autoload.inc.php'));
$app=br_app_config();
$add('Application environment',in_array($app['environment'],['development','staging','production','test'],true),$app['environment']);
$add('Production HTTPS configuration',!$app['production'] || ($app['force_https'] && parse_url($app['url'],PHP_URL_SCHEME)==='https'));
$add('Cloudflare Turnstile configured',!$app['production'] || !empty($app['turnstile']['enabled']));
try {
    $db=new MaintainProDatabase(br_database());
    $migrationCount=$db->migrationCount();
    $add('Database connectivity',true);
    $add('Migration ledger', $migrationCount>0, $migrationCount.' applied');
} catch (Throwable) { $add('Database connectivity',false,'unavailable'); }
foreach (['uploads/evidence','uploads/profiles','.data/logs'] as $relative) {
    $path=dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
    if (!is_dir($path)) @mkdir($path,0750,true);
    $add('Writable '.$relative,is_dir($path) && is_writable($path));
}
$mail=require dirname(__DIR__).'/config/mail.example.php';
if (is_file(dirname(__DIR__).'/config/mail.local.php')) $mail=array_replace($mail,require dirname(__DIR__).'/config/mail.local.php');
$mailHost=getenv('MAIL_HOST');
if ($mailHost===false) $mailHost=getenv('BR_SMTP_HOST');
if ($mailHost!==false) $mail['host']=$mailHost;
$add('SMTP configured',is_string($mail['host'] ?? null) && trim($mail['host'])!=='');
$add('Upload deny rule',is_file(dirname(__DIR__).'/uploads/.htaccess'));
$add('Scheduled tools',is_file(__DIR__.'/notify-deadlines.php') && is_file(__DIR__.'/cleanup-security.php'));
foreach ($checks as [$label,$ok,$detail]) echo ($ok?'PASS':'FAIL').': '.$label.($detail!==''?' ('.$detail.')':'').PHP_EOL;
exit(array_reduce($checks,fn(int $status,array $check)=>$status||!$check[1]?1:0,0));
