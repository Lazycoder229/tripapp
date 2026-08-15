<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Application;

Application::run(
    controllersPath:      __DIR__ . '/../app/Controller',
    controllersNamespace: 'App\\Controller',
    middlewaresPath:      __DIR__ . '/../app/Middleware',
    middlewaresNamespace: 'App\\Middleware',
    basePath:             __DIR__ . '/../'
);