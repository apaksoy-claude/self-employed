<?php
declare(strict_types=1);

// Defaults. The installer writes your real values to config/local.php, which overrides this file.
return [
    'debug' => false,
    'url' => '',                 // leave empty to detect automatically
    'key' => '',
    'recovery_key' => '',
    'admin_path' => 'admin',     // change to move the admin panel, e.g. "manage"
    'session_lifetime' => 7200,
    'trust_proxy' => false,      // set true behind a load balancer that sends X-Forwarded-Proto
];
