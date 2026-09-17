<?php
// Copy to mail.local.php and enter the Gmail sender and its Google App Password.
// https://support.google.com/accounts/answer/185833
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => '',
    'password' => '',
    'from_email' => '', // Leave blank to use the username.
    'from_name' => 'MaintainPro',
];
