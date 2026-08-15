<?php

use Framework\Config\Env;

return [
    // Comma-separated allowed origins, e.g. "https://app.com,https://admin.app.com".
    // "*" means any origin is reflected back (NOT the literal wildcard string —
    // see CorsMiddleware::isOriginAllowed()).
    'allowed_origins'   => Env::get('CORS_ALLOWED_ORIGINS', '*'),

    'allowed_methods'   => Env::get('CORS_ALLOWED_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS'),
    'allowed_headers'   => Env::get('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With'),

    // Only sent when the request's Origin is explicitly matched (never with "*").
    'allow_credentials' => (bool) Env::get('CORS_ALLOW_CREDENTIALS', false),

    'max_age'           => (int) Env::get('CORS_MAX_AGE', 86400),
];
