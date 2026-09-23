<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/SMTP.php';

final class MailConfigurationException extends RuntimeException {}

function br_mailer(): PHPMailer
{
    $config = require dirname(__DIR__) . '/config/mail.example.php';
    if (is_file(dirname(__DIR__) . '/config/mail.local.php')) {
        $config = array_replace($config, require dirname(__DIR__) . '/config/mail.local.php');
    }
    foreach (['host', 'port', 'encryption', 'username', 'password', 'from_email', 'from_name'] as $key) {
        $value = getenv('BR_SMTP_' . strtoupper($key));
        if ($value !== false) $config[$key] = $value;
    }
    $local = in_array($config['host'], ['127.0.0.1', '::1', 'localhost'], true);
    $auth = getenv('BR_SMTP_AUTH') !== '0';
    $from = $config['from_email'] ?: $config['username'];
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)
        || ($auth && ($config['username'] === '' || $config['password'] === ''))
        || (!$auth && !$local)
        || !in_array($config['encryption'], $local ? ['tls', 'ssl', 'none'] : ['tls', 'ssl'], true)
        || !filter_var($config['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
        throw new MailConfigurationException('Password reset email is temporarily unavailable. Please contact an official.');
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
    $mail->Subject = 'MaintainPro password reset code';
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
        $url = rtrim(getenv('BR_APP_URL') ?: '', '/');
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) $mail->Body .= "\n" . $url . '/concern.php?id=' . rawurlencode($concern['id']);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        // Do not log SMTP credentials, message body, recipient or private location.
        error_log('MaintainPro: assignment saved but its email notification could not be sent.');
        return false;
    }
}
