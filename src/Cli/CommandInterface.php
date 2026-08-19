<?php

declare(strict_types=1);

namespace Framework\Cli;

/**
 * Interface CommandInterface
 * 
 * Defines the contract for all CLI commands executable via the Trip console.
 * 
 * @package Framework\Cli
 */
interface CommandInterface
{
    /**
     * Executes the CLI command with arguments.
     *
     * @param array $args Command line arguments.
     * @return int Exit code (0 for success, non-zero for error).
     */
    public function execute(array $args): int;

    /**
     * Returns a concise description of what the command does.
     */
    public function getDescription(): string;
}
