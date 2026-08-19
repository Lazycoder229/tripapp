<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Framework\Config\Config;

final class ConfigTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = dirname(__DIR__, 2) . '/';
        Config::setPath($this->basePath . 'config');
    }

    protected function tearDown(): void
    {
        Config::clearCache($this->basePath);
    }

    public function testGetConfigurationValueWithDotNotation(): void
    {
        $appName = Config::get('app.name', 'Default');
        $this->assertNotEmpty($appName);

        $nonExistent = Config::get('app.non_existent_key_123', 'Fallback');
        $this->assertSame('Fallback', $nonExistent);
    }

    public function testConfigCompilationAndCaching(): void
    {
        $cacheFile = Config::cacheAll($this->basePath);
        $this->assertFileExists($cacheFile);
        $this->assertTrue(Config::hasCache($this->basePath));

        $loaded = Config::loadCache($this->basePath);
        $this->assertTrue($loaded);

        $appNameFromCache = Config::get('app.name');
        $this->assertNotEmpty($appNameFromCache);

        Config::clearCache($this->basePath);
        $this->assertFalse(Config::hasCache($this->basePath));
    }
}
