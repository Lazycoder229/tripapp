<?php

declare(strict_types=1);

namespace Framework\Database\Migration;

/**
 * Migration
 * 
 * Base abstract class for all database schema migrations.
 * 
 * @package Framework\Database\Migration
 */
abstract class Migration
{
    /**
     * Run the migrations (apply schema changes).
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations (rollback schema changes).
     */
    abstract public function down(): void;
}
