<?php

use Framework\Config\Env;

return [
    'max_requests' => (int) Env::get('RATE_LIMIT_MAX', 60),
    'window'       => (int) Env::get('RATE_LIMIT_WINDOW', 60), // seconds
];
