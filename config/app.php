<?php

use Framework\Config\Env;

return [
    'name'  => Env::get('APP_NAME', 'My Framework'),
    'env'   => Env::get('APP_ENV', 'production'),
    'debug' => Env::isDebug(),
    'url'   => Env::appUrl(),
    'key'   => Env::appKey(),
];