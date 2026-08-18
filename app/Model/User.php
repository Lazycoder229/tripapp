<?php

declare(strict_types=1);

namespace App\Model;

use Framework\Database\Model;

class User extends Model
{
    protected string $table = 'users';

    // Whitelist — only these columns can ever be mass-assigned via create()/update().
    protected array $fillable = ['name', 'email', 'password'];

    // Not mass-assignable, but still allowed in select() — Model::select()
    // validates against fillable ∪ guarded, so timestamps/id need to be
    // listed here too or select('created_at') etc. gets rejected.
    protected array $guarded = ['id', 'created_at', 'updated_at'];

    // Stamps created_at / updated_at automatically (Model::$timestamps defaults to true).
    protected bool $timestamps = true;
}