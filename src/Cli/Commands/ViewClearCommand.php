<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;
use Framework\View\View;

/**
 * ViewClearCommand
 * 
 * Clears compiled view templates from storage/cache/views/.
 * 
 * @package Framework\Cli\Commands
 */
final class ViewClearCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        View::init($this->basePath);
        $count = View::getEngine()->clearCache();

        Output::success("Compiled views cleared ({$count} files deleted).");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Clear all compiled view template files';
    }
}
