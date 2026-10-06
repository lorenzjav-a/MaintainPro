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

function br_send_assignment(array $personnel, array $concern): bool
{
    try {
        $mail = br_mailer();
        $mail->addAddress($personnel['email'], $personnel['name']);
        $mail->Subject = 'New MaintainPro Work Assignment';
        // Exact private location stays behind authentication, even in email.
        $mail->Body = 'Hello ' . $personnel['name'] . ",\n\nA concern has been assigned to you.\n\nConcern reference: " . $concern['id']
            . "\nCategory: " . $concern['category'] . "\nConcern: " . ($concern['concernType'] ?? 'Legacy concern — sign in for details')
            . "\nPriority: " . $concern['priority'] . "\n\nSign in to MaintainPro to view the private location and complete work instructions.";
        $url = br_app_config()['url'];
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) $mail->Body .= "\n" . $url . '/concern.php?id=' . rawurlencode($concern['id']);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        // Do not log SMTP credentials, message body, recipient or private location.
        error_log('MaintainPro: assignment saved but its email notification could not be sent.');
        return false;
    }
}
