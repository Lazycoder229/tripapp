<?php

declare(strict_types=1);

namespace Framework\Cli;

/**
 * Output helper for CLI console formatting and styling.
 * 
 * @package Framework\Cli
 */
final class Output
{
    public static function success(string $message): void
    {
        echo "\033[32m[SUCCESS]\033[0m " . $message . "\n";
    }

    public static function error(string $message): void
    {
        echo "\033[31m[ERROR]\033[0m " . $message . "\n";
    }

    public static function warning(string $message): void
    {
        echo "\033[33m[WARNING]\033[0m " . $message . "\n";
    }

    public static function info(string $message): void
    {
        echo "\033[36m[INFO]\033[0m " . $message . "\n";
    }

    public static function line(string $message = ''): void
    {
        echo $message . "\n";
    }

    /**
     * Renders a clean ASCII table from headers and row data.
     *
     * @param string[] $headers
     * @param array<int, array<int, string>> $rows
     */
    public static function table(array $headers, array $rows): void
    {
        $colWidths = [];
        foreach ($headers as $i => $header) {
            $colWidths[$i] = strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $val) {
                $colWidths[$i] = max($colWidths[$i] ?? 0, strlen((string) $val));
            }
        }

        $separator = '+';
        foreach ($colWidths as $w) {
            $separator .= str_repeat('-', $w + 2) . '+';
        }

        echo $separator . "\n";
        echo '|';
        foreach ($headers as $i => $header) {
            echo ' \033[1m' . str_pad($header, $colWidths[$i]) . "\033[0m |";
        }
        echo "\n" . $separator . "\n";

        foreach ($rows as $row) {
            echo '|';
            foreach ($row as $i => $val) {
                echo ' ' . str_pad((string) $val, $colWidths[$i]) . ' |';
            }
            echo "\n";
        }

        echo $separator . "\n";
    }
}
