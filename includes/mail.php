<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/SMTP.php';

final class MailConfigurationException extends RuntimeException {}

function br_mailer(): PHPMailer
{
    require_once dirname(__DIR__) . '/config/app.php';
    $config = require dirname(__DIR__) . '/config/mail.example.php';
    if (is_file(dirname(__DIR__) . '/config/mail.local.php')) {
        $config = array_replace($config, require dirname(__DIR__) . '/config/mail.local.php');
    }
    $environmentNames = [
        'host'=>'MAIL_HOST', 'port'=>'MAIL_PORT', 'encryption'=>'MAIL_ENCRYPTION',
        'username'=>'MAIL_USERNAME', 'password'=>'MAIL_PASSWORD',
        'from_email'=>'MAIL_FROM_ADDRESS', 'from_name'=>'MAIL_FROM_NAME',
    ];
    foreach ($environmentNames as $key => $name) {
        $value = getenv($name);
        if ($value === false) $value = getenv('BR_SMTP_' . strtoupper($key));
        if ($value !== false) $config[$key] = $value;
    }
    $local = in_array($config['host'], ['127.0.0.1', '::1', 'localhost'], true);
    $authValue = getenv('MAIL_AUTH');
    if ($authValue === false) $authValue = getenv('BR_SMTP_AUTH');
    $auth = $authValue === false || !in_array(strtolower(trim($authValue)), ['0','false','no','off'], true);
    $from = $config['from_email'] ?: $config['username'];
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)
        || ($auth && ($config['username'] === '' || $config['password'] === ''))
        || (!$auth && !$local)
        || !in_array($config['encryption'], $local ? ['tls', 'ssl', 'none'] : ['tls', 'ssl'], true)
        || !filter_var($config['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
        throw new MailConfigurationException("We couldn't send the verification code right now. Please try again later.");
    }
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->Port = (int)$config['port'];
    $mail->SMTPAuth = $auth;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = $config['encryption'] === 'none' ? '' : $config['encryption'];
    $mail->SMTPAutoTLS = $config['encryption'] !== 'none';
    $mail->SMTPOptions = ['ssl' => ['verify_peer'=>true, 'verify_peer_name'=>true, 'allow_self_signed'=>false]];
    $mail->SMTPDebug = 0;
    $mail->Timeout = 10;
    $mail->Timelimit = 15;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($from, $config['from_name']);
    return $mail;
}

function br_send_reset_code(PHPMailer $mail, string $email, string $code): void
{
    $mail->addAddress($email);
    $mail->Subject = 'MaintainPro Password Reset Code';
    $mail->isHTML(true);
    $mail->Body = '<h2>Reset your MaintainPro password</h2><p>Your one-time code is:</p>'
        . '<p style="font-size:32px;font-weight:bold;letter-spacing:6px">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>This code expires in 10 minutes. Enter it in the browser where you requested the reset.</p>'
        . '<p>If you did not request this, ignore this email. Your password has not changed. Do not share this code.</p>';
    $mail->AltBody = "Your MaintainPro password reset code is: $code\n\n"
        . "It expires in 10 minutes. Enter it in the browser where you requested the reset.\n"
        . 'If you did not request this, ignore this email. Your password has not changed. Do not share this code.';
    $mail->send();
}

function br_send_registration_code(PHPMailer $mail, string $email, string $code): void
{
    $mail->addAddress($email);
    $mail->Subject = 'Verify your MaintainPro resident account';
    $mail->isHTML(true);
    $safe=htmlspecialchars($code,ENT_QUOTES,'UTF-8');
    $mail->Body='<h2>Verify your resident account</h2><p>Your six-digit code is:</p><p style="font-size:32px;font-weight:bold;letter-spacing:6px">'.$safe.'</p><p>The code expires in 10 minutes. Enter it in the browser where you registered. If you did not register, ignore this email.</p>';
    $mail->AltBody="Your MaintainPro verification code is: $code\nIt expires in 10 minutes. If you did not register, ignore this email.";
    $mail->send();
}

function br_send_email_change_code(PHPMailer $mail, string $email, string $code): void
{
    $mail->addAddress($email);
    $mail->Subject='Verify your new MaintainPro email address';
    $safe=htmlspecialchars($code,ENT_QUOTES,'UTF-8');
    $mail->isHTML(true);
    $mail->Body='<h2>Verify your new email address</h2><p>Your six-digit code is:</p><p style="font-size:32px;font-weight:bold;letter-spacing:6px">'.$safe.'</p><p>This code expires in 10 minutes. Your existing email remains active until verification succeeds.</p>';
    $mail->AltBody="Your MaintainPro email-change code is: $code\nIt expires in 10 minutes. Your existing email remains active until verification succeeds.";
    $mail->send();
}

function br_send_email_changed_notice(string $email, string $name, string $newEmail): void
{
    try {
        $mail=br_mailer();
        $mail->addAddress($email,$name);
        $mail->Subject='Your MaintainPro email address was changed';
        $mail->Body="Hello $name,\n\nYour MaintainPro account email address was changed to $newEmail. If you did not make this change, contact the barangay administrator immediately.";
        $mail->send();
    } catch (Throwable) {
        error_log('MaintainPro: previous-address email change notification failed.');
    }
}

function br_send_account_invitation(array $user,string $token): bool
{
    try {
        $config=br_app_config();
        $url=$config['url'].'/account-setup?token='.rawurlencode($token);
        if ($config['production'] && parse_url($url,PHP_URL_SCHEME)!=='https') throw new RuntimeException('Invitation links require HTTPS in production.');
        $mail=br_mailer();
        $mail->addAddress($user['email'],$user['name']);
        $mail->Subject="You're Invited to Join MaintainPro";
        $role=match($user['role']) {'official'=>'Barangay Official','personnel'=>'Barangay Personnel',default=>'Resident'};
        $safeName=htmlspecialchars($user['name'],ENT_QUOTES,'UTF-8');
        $safeRole=htmlspecialchars($role,ENT_QUOTES,'UTF-8');
        $safeTeam=htmlspecialchars($user['team'],ENT_QUOTES,'UTF-8');
        $safeEmail=htmlspecialchars($user['email'],ENT_QUOTES,'UTF-8');
        $safeUrl=htmlspecialchars($url,ENT_QUOTES,'UTF-8');
        $teamRow=$user['team']!==''?'<tr><td style="padding:5px 0;color:#60727a">Assigned team</td><td style="padding:5px 0;text-align:right;font-weight:700;color:#102b32">'.$safeTeam.'</td></tr>':'';
        $mail->isHTML(true);
        $mail->Body='<!doctype html><html><body style="margin:0;background:#eef4f1;font-family:Segoe UI,Arial,sans-serif;color:#263b42"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef4f1;padding:24px 12px"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border:1px solid #d9e5df;border-radius:16px;overflow:hidden"><tr><td style="background:#102b32;padding:24px 30px;color:#fff"><div style="font-size:26px;font-weight:800">Maintain<span style="color:#5fc68d">Pro</span></div><div style="font-size:13px;color:#cce0d7;margin-top:4px">Community Care, Connected</div></td></tr><tr><td style="padding:30px"><h1 style="font-size:23px;line-height:1.3;margin:0 0 16px;color:#102b32">Welcome to MaintainPro</h1><p>Hello '.$safeName.',</p><p>An administrator created a MaintainPro account for you. Create your own password to activate it.</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;padding:14px;background:#f3f8f5;border-radius:10px"><tr><td style="padding:5px 0;color:#60727a">Account type</td><td style="padding:5px 0;text-align:right;font-weight:700;color:#102b32">'.$safeRole.'</td></tr>'.$teamRow.'<tr><td style="padding:5px 0;color:#60727a">Email</td><td style="padding:5px 0;text-align:right;font-weight:700;color:#102b32">'.$safeEmail.'</td></tr></table><p style="margin:26px 0;text-align:center"><a href="'.$safeUrl.'" style="display:inline-block;background:#198754;color:#fff;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:9px">Set Up My Account</a></p><p style="font-size:13px;color:#60727a">This invitation expires in 24 hours and can be used only once. If you were not expecting it, ignore this email or contact the system administrator.</p><p style="margin-bottom:0;color:#60727a">MaintainPro &mdash; Community Care, Connected.</p></td></tr></table></td></tr></table></body></html>';
        $mail->AltBody="Hello {$user['name']},\n\nAn administrator created a MaintainPro account for you.\nAccount type: $role".($user['team']!==''?"\nAssigned team: {$user['team']}":'')."\nEmail: {$user['email']}\n\nSet up your account: $url\n\nThis invitation expires in 24 hours and can be used only once. If you were not expecting it, ignore this email or contact the system administrator.\n\nMaintainPro - Community Care, Connected.";
        $mail->send();
        return true;
    } catch(Throwable) {
        error_log('MaintainPro: account invitation email delivery failed.');
        return false;
    }
}

function br_send_assignment(array $personnel, array $concern): bool
{
    try {
        $mail = br_mailer();
        $mail->addAddress($personnel['email'], $personnel['name']);
        $mail->Subject = 'New MaintainPro Team Assignment';
        // Exact private location stays behind authentication, even in email.
        $mail->Body = 'Hello ' . $personnel['name'] . ",\n\nA concern has been assigned to your team. Review it in MaintainPro and accept the work if you are available.\n\nConcern reference: " . $concern['id']
            . "\nCategory: " . $concern['category'] . "\nConcern: " . ($concern['concernType'] ?? 'Legacy concern — sign in for details')
            . "\nPriority: " . $concern['priority'] . "\n\nSign in to MaintainPro to view the private location and complete work instructions.";
        $url = br_app_config()['url'];
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) $mail->Body .= "\n" . $url . '/concern.php?id=' . rawurlencode($concern['id']);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        // Do not log SMTP credentials, message body, recipient or private location.
        error_log('MaintainPro: team assignment saved but an email notification could not be sent.');
        return false;
    }
}
