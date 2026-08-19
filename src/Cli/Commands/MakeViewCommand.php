<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * MakeViewCommand
 * 
 * Scaffolds a new view template in app/views/.
 * 
 * @package Framework\Cli\Commands
 */
final class MakeViewCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if (empty($name)) {
            Output::error("Please provide a view name. Example: php trip make:view users.index");
            return 1;
        }

        $normalized = str_replace('.', '/', $name);
        $parts = explode('/', $normalized);
        $fileName = array_pop($parts);
        $subDir = !empty($parts) ? '/' . implode('/', $parts) : '';

        $targetDir = rtrim($this->basePath, '/') . '/app/views' . $subDir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $targetFile = $targetDir . '/' . $fileName . '.php';
        if (file_exists($targetFile)) {
            Output::warning("View [{$targetFile}] already exists.");
            return 1;
        }

        $isLayout = in_array('--layout', $args, true) || str_starts_with($name, 'layouts.');

        if ($isLayout) {
            $template = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Trip App')</title>
    @csrfMeta
    @stack('styles')
</head>
<body>
    <div class="container">
        @yield('content')
    </div>

    @stack('scripts')
    @csrfJs
</body>
</html>
HTML;
        } else {
            $template = <<<HTML
@extends('layouts.app')

@section('title', '{$fileName}')

@section('content')
    <h1>Welcome to {$name}</h1>
    <p>This template was created by the Trip CLI.</p>
@endsection
HTML;
        }

        if (file_put_contents($targetFile, $template, LOCK_EX) === false) {
            Output::error("Failed to write view file [{$targetFile}].");
            return 1;
        }

        Output::success("View [{$name}] created successfully!");
        Output::line("  Location: app/views/{$normalized}.php");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Create a new template view in app/views/ (options: --layout)';
    }
}
