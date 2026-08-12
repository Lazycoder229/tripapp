<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Application;
use Framework\View\View;
require_once __DIR__ . '/../src/Helpers/View.php';
View::setPath(dirname(__DIR__) . '/app/Views');
Application::create()
    ->withBindings(function ($container) {
        // register your bindings here
        // example:
        // $container->bind(UserRepositoryInterface::class, MySqlUserRepository::class);
        // $container->singleton(Database::class, Database::class);
    })
    ->withControllerDirectory(dirname(__DIR__) . '/app/Controllers')
    ->run();