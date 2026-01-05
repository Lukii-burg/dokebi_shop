<?php
return [
    'host'        => getenv('SMTP_HOST') ?: '',
    'port'        => getenv('SMTP_PORT') ?: 587,
    'user'        => getenv('SMTP_USER') ?: '',
    'pass'        => getenv('SMTP_PASS') ?: '',
    'from'        => getenv('SMTP_FROM') ?: 'no-reply@example.com',
    'from_name'   => getenv('SMTP_FROM_NAME') ?: 'Dokebi Family',
    'encryption'  => strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls'),
    'admin_email' => 'gabriealfelo@gmail.com'
];
