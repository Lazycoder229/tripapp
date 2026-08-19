<?php

declare(strict_types=1);

namespace App\Service;

use Framework\Exception\NotFoundException;
use Framework\Exception\ValidationException;
use Framework\Security\Validator;

/**
 * ProductService
 * 
 * Encapsulates domain and business logic.
 */
class ProductService
{
    public function __construct()
    {
    }

    public function list(): array
    {
        return [];
    }

    public function find(int $id): array
    {
        return [];
    }

    public function create(array $input): array
    {
        return $input;
    }

    public function update(int $id, array $input): array
    {
        return $input;
    }

    public function delete(int $id): void
    {
    }
}
