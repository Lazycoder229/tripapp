<?php

// No namespace here on purpose — this file is require_once'd by View.php,
// and templates (which are include'd with no namespace declaration of
// their own) need e() to resolve as a plain global function call.

if (!function_exists('e')) {
    /**
     * HTML-escape a value for interpolation into a template.
     *
     * @param mixed $value
     * @return string
     */
    function e(mixed $value): string
    {
        return \Framework\View\View::escape($value);
    }
}