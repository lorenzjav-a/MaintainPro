<?php
declare(strict_types=1);
// Check SMTP authentication without sending an email.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/mail.php';
try {
    $mail = br_mailer();
    if (!$mail->smtpConnect()) throw new RuntimeException('SMTP connection failed.');
    $mail->smtpClose();
    echo "SMTP connection and authentication succeeded. No email was sent.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "SMTP is not ready. Check config/mail.local.php, the Gmail App Password, and the network connection.\n");
    exit(1);
}
