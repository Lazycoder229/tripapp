<?php

use Framework\Config\Env;

return [
    'driver'    => Env::get('LOG_DRIVER', 'file'),
    // Lowest severity actually written. One of: emergency, alert, critical,
    // error, warning, notice, info, debug. Everything above this is dropped
    // silently — e.g. 'error' in production skips info/debug noise.
    'min_level' => Env::get('LOG_LEVEL', 'debug'),
];
