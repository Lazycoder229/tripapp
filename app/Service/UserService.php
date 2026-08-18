<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use Framework\Exception\NotFoundException;
use Framework\Exception\ValidationException;
use Framework\Security\Hash;
use Framework\Security\Validator;

/**
 * Owns all user business logic: model/DB access, id resolution, existence
 * checks, validation rules, password hashing. UsersController only calls
 * these methods and returns whatever comes back or throws — it never
 * touches User (the Model), the database, Validator, or Hash directly,
 * and never branches on "did this exist" itself. A bad/missing id and a
 * failed validation both surface as an exception (NotFoundException /
 * ValidationException) that the global Handler renders — see
 * Framework\Exception\Handler.
 */
class UserService
{
    public function __construct(private readonly User $user)
    {
    }

    /**
     * @return array List of users (password never included).
     */
    public function list(): array
    {
        return $this->user
            ->select('id', 'name', 'email', 'created_at', 'updated_at')
            ->latest()
            ->get();
    }

    /**
     * @throws NotFoundException If $id isn't a valid id shape, or no user exists with it.
     */
    public function find(int $id): array
    {
        $userId = $this->resolveId($id);
        $user   = $this->fetch($userId);

        if ($user === null) {
            throw new NotFoundException("User with id {$userId} not found.");
        }

        return $user;
    }

    public function create(array $input): array
    {
        $data = Validator::make($input, [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ])->validate();

        $data['password'] = Hash::make($data['password']);

        $id = $this->user->create($data);

        return $this->fetch((int)$id);
    }

    public function update(int $id, array $input): array
    {
        $userId = $this->resolveId($id);

        if ($this->fetch($userId) === null) {
            throw new NotFoundException("User with id {$userId} not found.");
        }

        $data = Validator::make($input, [
            'name'     => 'string|max:255',
            'email'    => 'email|max:255',
            'password' => 'string|min:8',
        ])->validate();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $this->user->update($userId, $data);

        return $this->fetch($userId);
    }

    public function delete(int $id): void
    {
        $userId = $this->resolveId($id);

        if ($this->fetch($userId) === null) {
            throw new NotFoundException("User with id {$userId} not found.");
        }

        $this->user->delete((int) $userId);
    }
  

   private function resolveId(int $id): int
    {
        if (!ctype_digit((string)$id)) {
            throw new NotFoundException('User not found.');
        }

        return (int)$id;
    }

    private function fetch(int $id): ?array
    {
        return $this->user
            ->select('id', 'name', 'email', 'created_at', 'updated_at')
            ->find($id);
    }
}