<?php

use Framework\Config\Env;

return [
    'driver'   => Env::get('SESSION_DRIVER', 'file'),
    'lifetime' => (int) Env::get('SESSION_LIFETIME', 120), // minutes
    'secure'   => Env::get('SESSION_SECURE', null), // null (unset in .env) = auto-detect from request scheme
];
