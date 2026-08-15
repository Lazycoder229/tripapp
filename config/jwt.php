<?php

use Framework\Config\Env;

return [
    // HMAC signing secret. Required — Jwt::class refuses to be constructed
    // without one (see Jwt::__construct()), same fail-fast posture as
    // Env::required('APP_KEY').
    'secret'     => Env::get('JWT_SECRET'),

    // Seconds a token stays valid after issue. Checked against the 'exp'
    // claim on decode(); expired tokens fail verification the same way a
    // bad signature does.
    'ttl'        => (int) Env::get('JWT_TTL', 3600),

    // Included in every token's 'iss' claim and, if set, checked on
    // decode() — a token issued for a different app/service is rejected.
    'issuer'     => Env::get('JWT_ISSUER', null),
];
