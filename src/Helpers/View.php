<?php

use Framework\Http\Response;
use Framework\View\View;

function view(
    string $template,
    array $data = [],
    int $statusCode = 200
): Response {
    return View::make(
        $template,
        $data,
        $statusCode
    );
}