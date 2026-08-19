<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\View\ViewEngine;
use Framework\View\View;
use Framework\Http\Response;
use Framework\Database\Schema\Blueprint;
use Framework\Database\Schema\Column;
use Framework\Database\Schema\Schema;

$basePath = dirname(__DIR__) . '/';

echo "\n========================================================\n";
echo "   RUNNING TEMPLATE ENGINE & MIGRATION UNIT TESTS       \n";
echo "========================================================\n\n";

// -----------------------------------------------------------------
// 1. TEST VIEW ENGINE & COMPILATION
// -----------------------------------------------------------------
echo "1. Testing View Engine Compilation & Directives...";

$viewsDir = $basePath . 'storage/test_views';
$cacheDir = $basePath . 'storage/cache/test_views';
if (!is_dir($viewsDir)) mkdir($viewsDir, 0775, true);
if (!is_dir($cacheDir)) mkdir($cacheDir, 0775, true);

$engine = new ViewEngine($viewsDir, $cacheDir);

// Test 1.1: Directives compilation
$rawTemplate = "Hello {{ \$name }}! Raw: {!! \$raw !!} @if(\$show) YES @endif";
$compiled = $engine->compile($rawTemplate);

assert(str_contains($compiled, 'htmlspecialchars'), 'Escaping should use htmlspecialchars');
assert(str_contains($compiled, '<?= $raw; ?>'), 'Raw output should output unescaped expression');
assert(str_contains($compiled, '<?php if ($show): ?>'), '@if should compile to PHP if');

// Test 1.2: Layout inheritance & Sections
file_put_contents($viewsDir . '/layout.php', "<html><head><title>@yield('title')</title>@csrfMeta\n@stack('styles')\n</head><body>@yield('content')@stack('scripts')</body></html>");
file_put_contents($viewsDir . '/page.php', "@extends('layout')\n@section('title', 'My Page')\n@push('styles')\n@css('css/app.css')\n@endpush\n@push('scripts')\n@js('js/app.js')\n@csrfJs\n@endpush\n@section('content')\n<h1>Hello {{ \$name }}</h1>\n@csrf\n@method('PUT')\n@endsection");

$output = $engine->render('page', ['name' => '<script>alert(1)</script>']);

assert(str_contains($output, '<title>My Page</title>'), 'Layout title section failed');
assert(str_contains($output, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'XSS escaping failed');
assert(!str_contains($output, '<script>alert(1)</script>'), 'Unescaped script should not be present');
assert(str_contains($output, 'name="_csrf"'), 'CSRF token input missing');
assert(str_contains($output, 'name="_method" value="PUT"'), 'Method spoofing input missing');

// Test @csrfMeta: should generate meta tag with csrf-token
assert(str_contains($output, '<meta name="csrf-token" content="'), '@csrfMeta meta tag missing');

// Test @csrfJs: should generate script that patches fetch() and XMLHttpRequest
assert(str_contains($output, 'X-CSRF-Token'), '@csrfJs missing X-CSRF-Token header injection');
assert(str_contains($output, 'XMLHttpRequest.prototype.open'), '@csrfJs missing XHR patch');
assert(str_contains($output, 'window.fetch'), '@csrfJs missing fetch patch');

// Test @css: should generate <link> tag
assert(str_contains($output, '<link rel="stylesheet" href="/css/app.css">'), '@css directive failed');

// Test @js: should generate <script src> tag
assert(str_contains($output, '<script src="/js/app.js"></script>'), '@js directive failed');

// Test @push/@stack: styles and scripts should appear in the correct locations
// styles should be in <head>, scripts should be before </body>
$headPos = strpos($output, '</head>');
$cssPos = strpos($output, 'css/app.css');
assert($cssPos !== false && $cssPos < $headPos, '@push("styles") should appear in <head> via @stack("styles")');

$bodyEndPos = strpos($output, '</body>');
$jsPos = strpos($output, 'js/app.js');
assert($jsPos !== false && $jsPos < $bodyEndPos, '@push("scripts") should appear before </body> via @stack("scripts")');

// Test 1.3: @prepend stack ordering
$engine2 = new ViewEngine($viewsDir, $cacheDir);
file_put_contents($viewsDir . '/layout2.php', "<head>@stack('meta')</head>");
file_put_contents($viewsDir . '/page2.php', "@extends('layout2')\n@push('meta')\n<meta name=\"description\" content=\"test\">\n@endpush\n@prepend('meta')\n<meta charset=\"UTF-8\">\n@endprepend");
// Clear compiled cache for these specific views
foreach (glob($cacheDir . '/*.php') ?: [] as $f) @unlink($f);
$output2 = $engine2->render('page2');
$charsetPos = strpos($output2, 'charset');
$descPos = strpos($output2, 'description');
assert($charsetPos !== false && $descPos !== false, '@prepend and @push should both be present');
assert($charsetPos < $descPos, '@prepend should appear BEFORE @push content in the stack');

// Test 1.4: Response::view()
View::init($basePath);
// create test view for Response::view
$viewsAppDir = $basePath . 'app/views';
if (!is_dir($viewsAppDir)) mkdir($viewsAppDir, 0775, true);
file_put_contents($viewsAppDir . '/test.php', "<div>Test View: {{ \$message }}</div>");

$response = Response::view('test', ['message' => 'Working!']);
assert($response instanceof Response, 'Response::view must return Response instance');
assert($response->getStatusCode() === 200, 'Response::view status should be 200');
assert(str_contains($response->getContent(), 'Test View: Working!'), 'Response content mismatch');

// Test 1.5: Compile-only checks for asset directives
$compiled3 = $engine->compile('@css("https://cdn.example.com/bootstrap.css") @js("https://cdn.example.com/vue.js")');
assert(str_contains($compiled3, '<link rel="stylesheet" href="'), '@css external URL compile failed');
assert(str_contains($compiled3, '<script src="'), '@js external URL compile failed');

echo " PASSED\n";


// Test 2: Full Schema Blueprint & All Column Syntax
echo "2. Testing Schema Blueprint & Full Syntax Coverage...";

$table = new Blueprint('products');
$table->id();
$table->string('title', 150);
$table->char('sku', 12)->unique();
$table->text('description')->nullable();
$table->mediumText('details')->nullable();
$table->longText('raw_data')->nullable();
$table->integer('stock')->default(0);
$table->unsignedBigInteger('category_id');
$table->boolean('is_published')->default(true);
$table->decimal('price', 10, 2);
$table->float('rating')->default(0.0);
$table->double('weight')->nullable();
$table->date('release_date')->nullable();
$table->time('start_time')->nullable();
$table->datetime('published_at')->nullable();
$table->json('attributes')->nullable();
$table->enum('status', ['draft', 'published', 'archived'])->default('draft');
$table->rememberToken();
$table->softDeletes();
$table->timestamps();
$table->index(['category_id', 'is_published'], 'idx_cat_pub');
$table->fulltext(['title', 'description'], 'ft_title_desc');

$createSql = $table->toSqlCreate();

assert(str_contains($createSql, 'CREATE TABLE IF NOT EXISTS `products`'), 'CREATE TABLE missing');
assert(str_contains($createSql, '`sku` CHAR(12) NOT NULL UNIQUE'), 'CHAR unique missing');
assert(str_contains($createSql, '`raw_data` LONGTEXT NULL'), 'LONGTEXT missing');
assert(str_contains($createSql, '`category_id` BIGINT UNSIGNED NOT NULL'), 'unsignedBigInteger missing');
assert(str_contains($createSql, '`attributes` JSON NULL'), 'JSON type missing');
assert(str_contains($createSql, "`status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft'"), 'ENUM type missing');
assert(str_contains($createSql, '`remember_token` VARCHAR(100) NULL'), 'rememberToken missing');
assert(str_contains($createSql, '`deleted_at` TIMESTAMP NULL DEFAULT NULL'), 'softDeletes missing');
assert(str_contains($createSql, 'INDEX `idx_cat_pub` (`category_id`, `is_published`)'), 'Compound index missing');
assert(str_contains($createSql, 'FULLTEXT `ft_title_desc` (`title`, `description`)'), 'FULLTEXT index missing');

// Test Alter operations: rename, drop index, drop column
$alter = new Blueprint('products');
$alter->renameColumn('title', 'product_name');
$alter->dropColumn('weight');
$alter->dropIndex('idx_cat_pub');
$alterStatements = $alter->toSqlAlter();

assert(str_contains($alterStatements[0], 'ALTER TABLE `products` DROP INDEX `idx_cat_pub`'), 'DROP INDEX missing');
assert(str_contains($alterStatements[1], 'ALTER TABLE `products` DROP COLUMN `weight`'), 'DROP COLUMN missing');
assert(str_contains($alterStatements[2], 'ALTER TABLE `products` RENAME COLUMN `title` TO `product_name`'), 'RENAME COLUMN missing');

echo " PASSED\n";


// Clean up test views
@unlink($viewsDir . '/layout.php');
@unlink($viewsDir . '/page.php');
@unlink($viewsDir . '/layout2.php');
@unlink($viewsDir . '/page2.php');
@unlink($viewsAppDir . '/test.php');
foreach (glob($cacheDir . '/*.php') ?: [] as $f) @unlink($f);
@rmdir($viewsDir);
@rmdir($cacheDir);

echo "\n========================================================\n";
echo "   ALL TEMPLATE ENGINE & SCHEMA TESTS PASSED!           \n";
echo "========================================================\n\n";
