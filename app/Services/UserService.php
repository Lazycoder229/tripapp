<?php

namespace App\Services;
use Framework\Exception\NotFoundException;
/**
 * UserService
 *
 * Sample service to demonstrate dependency injection.
 * Will be auto-injected into UserController by the Container.
 *
 * @package App\Services
 */
class UserService
{
    private array $users = [
    '1' => [
        'id' => '1',
        'name' => 'John Doe',
        'email' => 'user1@example.com',
    ],
    '2' => [
        'id' => '2',
        'name' => 'Jane Doe',
        'email' => 'user2@example.com',
    ],
];

    /**
     * Find a user by ID.
     * Returns dummy data for now.
     *
     * @param string $id
     * @return array<string, mixed>
     */
   public function find(string $id): array
    {
        if (!isset($this->users[$id])) {
                throw new NotFoundException(
                    "User with ID {$id} was not found."
                );
            }

        return $this->users[$id];
    }

    /**
     * Get all users.
     * Returns dummy data for now.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->users;
    }
}