<?php
declare(strict_types=1);

function br_security_log(string $event, array $context = []): void
{
    $allowed=[];
    foreach (['actor_role','target_type','result','request_id'] as $key) {
        if (isset($context[$key]) && is_scalar($context[$key])) $allowed[$key]=mb_substr((string)$context[$key],0,80);
    }
    error_log('MaintainPro security event: '.$event.($allowed ? ' '.json_encode($allowed,JSON_UNESCAPED_SLASHES) : ''));
}
