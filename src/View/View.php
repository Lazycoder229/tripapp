<?php

namespace Framework\View;

use Framework\Exception\ViewNotFoundException;
use Framework\Http\Response;

/**
 * View
 *
 * Blade-like minimal templating layer over plain PHP.
 *
 * Supported:
 *
 *     {{ $value }}
 *     {!! $html !!}
 *
 *     @foreach (...) 
 *     @endforeach
 *
 *     @extends('layouts.app')
 *
 *     @section('content')
 *     @endsection
 *
 *     @yield('content')
 *
 *     @include('partials.nav')
 *
 *     @push('scripts')
 *     @endpush
 *
 *     @stack('scripts')
 *
 * Plain PHP remains fully supported.
 *
 * @package Framework\View
 */
class View
{
    private static ?string $viewsPath = null;

    private static ?string $cachePath = null;

    /**
     * Current layout sections.
     *
     * @var array<string, string>
     */
    private static array $sections = [];

    /**
     * Current section name while rendering.
     */
    private static ?string $currentSection = null;

    /**
     * Stacked content.
     *
     * @var array<string, array<int, string>>
     */
    private static array $stacks = [];

    /**
     * Set the views directory.
     */
    public static function setPath(string $path): void
    {
        static::$viewsPath = rtrim($path, '/\\');
    }

    /**
     * Set the compiled views directory.
     */
    public static function setCachePath(string $path): void
    {
        static::$cachePath = rtrim($path, '/\\');
    }

    /**
     * Render a view into an HTTP response.
     */
    public static function make(
        string $template,
        array $data = [],
        int $statusCode = 200
    ): Response {
        return Response::html(
            static::render($template, $data),
            $statusCode
        );
    }

    /**
     * Render a view into a string.
     */
    public static function render(
        string $template,
        array $data = []
    ): string {
        if (static::$viewsPath === null) {
            throw new ViewNotFoundException($template);
        }

        /*
         * Reset layout state for every top-level render.
         */
        static::$sections = [];
        static::$stacks = [];
        static::$currentSection = null;

        /*
         * Render the child view.
         */
        $content = static::renderTemplate(
            $template,
            $data
        );

        /*
         * If the child declared a layout,
         * renderTemplate() will already return the layout.
         */
        return $content;
    }

    /**
     * Render an individual template.
     */
    private static function renderTemplate(
        string $template,
        array $data = []
    ): string {
        $sourceFile = static::resolve($template);

        $compiledFile = static::compiledPathFor(
            $sourceFile
        );

        return static::renderFile(
            $compiledFile,
            $data
        );
    }

    /**
     * Resolve a template name into a real file.
     */
    private static function resolve(
        string $template
    ): string {
        $relative = str_replace(
            '.',
            DIRECTORY_SEPARATOR,
            $template
        ) . '.php';

        $file = static::$viewsPath
            . DIRECTORY_SEPARATOR
            . $relative;

        $realBase = realpath(
            static::$viewsPath
        );

        $realFile = realpath($file);

        if (
            $realBase === false ||
            $realFile === false ||
            !str_starts_with(
                $realFile,
                $realBase . DIRECTORY_SEPARATOR
            )
        ) {
            throw new ViewNotFoundException(
                $template
            );
        }

        return $realFile;
    }

    /**
     * Get compiled template path.
     */
    private static function compiledPathFor(
        string $sourceFile
    ): string {
        $cacheDir = static::$cachePath
            ?? (
                sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'trip-views'
            );

        if (!is_dir($cacheDir)) {
            mkdir(
                $cacheDir,
                0755,
                true
            );
        }

        $compiledFile = $cacheDir
            . DIRECTORY_SEPARATOR
            . sha1($sourceFile)
            . '.php';

        $needsCompile =
            !file_exists($compiledFile)
            || filemtime($sourceFile)
                > filemtime($compiledFile);

        if ($needsCompile) {
            $source = file_get_contents(
                $sourceFile
            );

            if ($source === false) {
                throw new ViewNotFoundException(
                    $sourceFile
                );
            }

            $compiled = static::compile(
                $source
            );

            file_put_contents(
                $compiledFile,
                $compiled
            );
        }

        return $compiledFile;
    }

    /**
     * Compile Blade-like syntax into PHP.
     */
    private static function compile(
        string $content
    ): string {
        /*
         * @extends('layouts.app')
         */
        $content = preg_replace_callback(
            "/@extends\s*\(\s*['\"](.+?)['\"]\s*\)/",
            function (array $matches): string {
                return '<?php $__layout = '
                    . var_export($matches[1], true)
                    . '; ?>';
            },
            $content
        );

        /*
         * @section('content')
         */
        $content = preg_replace_callback(
            "/@section\s*\(\s*['\"](.+?)['\"]\s*\)/",
            function (array $matches): string {
                return '<?php $__viewSectionStart('
                    . var_export($matches[1], true)
                    . '); ?>';
            },
            $content
        );

        /*
         * @endsection
         */
        $content = str_replace(
            '@endsection',
            '<?php $__viewSectionEnd(); ?>',
            $content
        );

        /*
         * @yield('content')
         */
        $content = preg_replace_callback(
            "/@yield\s*\(\s*['\"](.+?)['\"](?:\s*,\s*(.+?))?\s*\)/",
            function (array $matches): string {
                $name = var_export(
                    $matches[1],
                    true
                );

                if (isset($matches[2])) {
                    return '<?php echo $__viewYield('
                        . $name
                        . ', '
                        . $matches[2]
                        . '); ?>';
                }

                return '<?php echo $__viewYield('
                    . $name
                    . '); ?>';
            },
            $content
        );

        /*
         * @include('partials.nav')
         */
        $content = preg_replace_callback(
            "/@include\s*\(\s*['\"](.+?)['\"]\s*\)/",
            function (array $matches): string {
                return '<?php echo $__viewInclude('
                    . var_export($matches[1], true)
                    . '); ?>';
            },
            $content
        );

        /*
         * @push('scripts')
         */
        $content = preg_replace_callback(
            "/@push\s*\(\s*['\"](.+?)['\"]\s*\)/",
            function (array $matches): string {
                return '<?php $__viewStackStart('
                    . var_export($matches[1], true)
                    . '); ?>';
            },
            $content
        );

        /*
         * @endpush
         */
        $content = str_replace(
            '@endpush',
            '<?php $__viewStackEnd(); ?>',
            $content
        );

        /*
         * @stack('scripts')
         */
        $content = preg_replace_callback(
            "/@stack\s*\(\s*['\"](.+?)['\"]\s*\)/",
            function (array $matches): string {
                return '<?php echo $__viewStack('
                    . var_export($matches[1], true)
                    . '); ?>';
            },
            $content
        );

        /*
         * @foreach (...)
         */
        $content = preg_replace_callback(
            '/@foreach\s*\((.*?)\)/',
            function (array $matches): string {
                return '<?php foreach ('
                    . $matches[1]
                    . '): ?>';
            },
            $content
        );

        /*
         * @endforeach
         */
        $content = str_replace(
            '@endforeach',
            '<?php endforeach; ?>',
            $content
        );

        /*
         * Raw output:
         *
         * {!! $html !!}
         */
        $content = preg_replace_callback(
            '/\{!!\s*(.*?)\s*!!\}/',
            function (array $matches): string {
                return '<?= '
                    . $matches[1]
                    . ' ?>';
            },
            $content
        );

        /*
         * Escaped output:
         *
         * {{ $value }}
         */
        $content = preg_replace_callback(
            '/\{\{\s*(.*?)\s*\}\}/',
            function (array $matches): string {
                return '<?= e('
                    . $matches[1]
                    . ') ?>';
            },
            $content
        );

        return $content;
    }

    /**
     * Execute a compiled PHP template.
     */
    private static function renderFile(
        string $__file,
        array $__data
    ): string {
        extract(
            $__data,
            EXTR_SKIP
        );

        /*
         * Layout variables/functions available
         * inside the compiled template.
         */
        $__viewSectionStart = function (
            string $name
        ): void {
            static::$currentSection = $name;
            ob_start();
        };

        $__viewSectionEnd = function (): void {
            $content = ob_get_clean();

            static::$sections[
                static::$currentSection
            ] = $content;

            static::$currentSection = null;
        };

        $__viewYield = function (
            string $name,
            ?string $default = ''
        ): string {
            return static::$sections[$name]
                ?? $default
                ?? '';
        };

        $__viewInclude = function (
            string $template
        ) use ($__data): string {
            return static::renderTemplate(
                $template,
                $__data
            );
        };

        $__viewStackStart = function (
            string $name
        ): void {
            static::$currentSection = $name;
            ob_start();
        };

        $__viewStackEnd = function (): void {
            $content = ob_get_clean();

            static::$stacks[
                static::$currentSection
            ][] = $content;

            static::$currentSection = null;
        };

        $__viewStack = function (
            string $name
        ): string {
            return implode(
                "\n",
                static::$stacks[$name] ?? []
            );
        };

        ob_start();

        try {
            include $__file;

            $content = ob_get_clean();

        } catch (\Throwable $e) {

            ob_end_clean();

            throw $e;
        }

        /*
         * If the child template has a layout,
         * render the layout after collecting sections.
         */
        if (isset($__layout)) {
            return static::renderTemplate(
                $__layout,
                $__data
            );
        }

        return $content;
    }

    /**
     * Escape output for HTML.
     */
    public static function escape(
        mixed $value
    ): string {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }
}

require_once __DIR__ . '/helpers.php';