<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Application;

// Sariling log file para dito lang sa app na 'to — hindi na kasama
// sa shared Apache/Laragon error.log.
$logDir = __DIR__ . '/../storage/log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/app.' . date('Y-m-d') . '.log');

Application::run(
    controllersPath:      __DIR__ . '/../app/Controller',
    controllersNamespace: 'App\\Controller',
    middlewaresPath:      __DIR__ . '/../app/Middleware',
    middlewaresNamespace: 'App\\Middleware',
    basePath:             __DIR__  . '/../'
);