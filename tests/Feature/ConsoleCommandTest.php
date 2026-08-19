<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Framework\Cli\Console;

final class ConsoleCommandTest extends TestCase
{
    private Console $console;
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = dirname(__DIR__, 2) . '/';
        $this->console = new Console($this->basePath);
    }

    public function testHelpCommandReturnsZero(): void
    {
        ob_start();
        $code = $this->console->run(['trip', 'help']);
        ob_end_clean();

        $this->assertSame(0, $code);
    }

    public function testRouteCacheAndClearCommands(): void
    {
        ob_start();
        $cacheCode = $this->console->run(['trip', 'route:cache']);
        $clearCode = $this->console->run(['trip', 'route:clear']);
        ob_end_clean();

        $this->assertSame(0, $cacheCode);
        $this->assertSame(0, $clearCode);
    }

    public function testConfigCacheAndClearCommands(): void
    {
        ob_start();
        $cacheCode = $this->console->run(['trip', 'config:cache']);
        $clearCode = $this->console->run(['trip', 'config:clear']);
        ob_end_clean();

        $this->assertSame(0, $cacheCode);
        $this->assertSame(0, $clearCode);
    }

    public function testMaintenanceDownAndUpCommands(): void
    {
        ob_start();
        $downCode = $this->console->run(['trip', 'down', '--message=Testing', '--retry=60', '--secret=admintest']);
        $upCode = $this->console->run(['trip', 'up']);
        ob_end_clean();

        $this->assertSame(0, $downCode);
        $this->assertSame(0, $upCode);
    }
}
