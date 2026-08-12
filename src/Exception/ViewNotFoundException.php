<?php

namespace Framework\Exception;

/**
 * ViewNotFoundException
 *
 * Thrown when a requested view template cannot be found.
 *
 * @package Framework\Exception
 */
class ViewNotFoundException extends HttpException
{
    /**
     * Create a new ViewNotFoundException.
     *
     * @param string $template The view template that could not be found.
     */
    public function __construct(string $template)
    {
        parent::__construct(
            "View not found: {$template}",
            404
        );
    }
}