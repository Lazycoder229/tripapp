<?php

declare(strict_types=1);

namespace App\Model;

use Framework\Database\Model;

class Product extends Model
{
    protected string $table = 'products';

    // Whitelist — only these columns can ever be mass-assigned via create()/update().
    protected array $fillable = [];

    // Columns protected from mass-assignment, but allowed in select().
    protected array $guarded = ['id', 'created_at', 'updated_at'];

    // Automatically stamps created_at / updated_at timestamps.
    protected bool $timestamps = true;
}
