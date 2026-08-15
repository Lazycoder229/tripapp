<?php

use Framework\Config\Env;

return [
    // HSTS only ever gets sent on an actual HTTPS request regardless of this flag —
    // this just lets you kill it entirely (e.g. local/staging behind plain HTTP).
    'hsts_enabled'    => (bool) Env::get('SECURITY_HSTS_ENABLED', true),

    'frame_options'   => Env::get('SECURITY_FRAME_OPTIONS', 'DENY'),
    'referrer_policy' => Env::get('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
];
