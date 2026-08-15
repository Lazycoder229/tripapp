<?php

use Framework\Config\Env;

return [
    'driver' => Env::get('CACHE_DRIVER', 'file'),
    'ttl'    => (int) Env::get('CACHE_TTL', 3600), // default seconds when set()/remember() get no explicit TTL
];
