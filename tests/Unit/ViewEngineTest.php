<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Framework\View\ViewEngine;
use Framework\View\View;
use Framework\Http\Response;

final class ViewEngineTest extends TestCase
{
    private string $viewsDir;
    private string $cacheDir;
    private ViewEngine $engine;

    protected function setUp(): void
    {
        $this->viewsDir = sys_get_temp_dir() . '/trip_tests_views_' . uniqid();
        $this->cacheDir = sys_get_temp_dir() . '/trip_tests_cache_' . uniqid();
        mkdir($this->viewsDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);

        $this->engine = new ViewEngine($this->viewsDir, $this->cacheDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->viewsDir . '/*') ?: [] as $f) @unlink($f);
        foreach (glob($this->cacheDir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($this->viewsDir);
        @rmdir($this->cacheDir);
    }

    public function testEscapedAndRawOutput(): void
    {
        $template = "Hello {{ \$name }}, Raw: {!! \$raw !!}";
        $compiled = $this->engine->compile($template);

        $this->assertStringContainsString('htmlspecialchars', $compiled);
        $this->assertStringContainsString('<?= $raw; ?>', $compiled);
    }

    public function testLayoutInheritanceAndSections(): void
    {
        file_put_contents($this->viewsDir . '/layout.php', "<html><head><title>@yield('title')</title>@csrfMeta\n@stack('styles')</head><body>@yield('content')@stack('scripts')@csrfJs</body></html>");
        file_put_contents($this->viewsDir . '/page.php', "@extends('layout')\n@section('title', 'Test Title')\n@push('styles')\n@css('css/custom.css')\n@endpush\n@push('scripts')\n@js('js/custom.js')\n@endpush\n@section('content')\n<p>Hello {{ \$name }}</p>\n@csrf\n@method('DELETE')\n@endsection");

        $html = $this->engine->render('page', ['name' => '<World>']);

        $this->assertStringContainsString('<title>Test Title</title>', $html);
        $this->assertStringContainsString('&lt;World&gt;', $html);
        $this->assertStringContainsString('<meta name="csrf-token"', $html);
        $this->assertStringContainsString('name="_csrf"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="/css/custom.css">', $html);
        $this->assertStringContainsString('<script src="/js/custom.js"></script>', $html);
        $this->assertStringContainsString('X-CSRF-Token', $html);
    }

    public function testStackPrependAndPushOrdering(): void
    {
        file_put_contents($this->viewsDir . '/layout_stack.php', "<head>@stack('meta')</head>");
        file_put_contents($this->viewsDir . '/page_stack.php', "@extends('layout_stack')\n@push('meta')\n<meta name=\"author\" content=\"Trip\">\n@endpush\n@prepend('meta')\n<meta charset=\"UTF-8\">\n@endprepend");

        $html = $this->engine->render('page_stack');

        $charsetPos = strpos($html, 'charset');
        $authorPos = strpos($html, 'author');

        $this->assertNotFalse($charsetPos);
        $this->assertNotFalse($authorPos);
        $this->assertLessThan($authorPos, $charsetPos, '@prepend must appear before @push in stack');
    }
}
