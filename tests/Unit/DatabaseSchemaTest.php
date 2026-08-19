<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Framework\Database\Schema\Blueprint;

final class DatabaseSchemaTest extends TestCase
{
    public function testCreateTableSqlGeneration(): void
    {
        $table = new Blueprint('orders');
        $table->id();
        $table->string('order_number', 50)->unique();
        $table->unsignedBigInteger('user_id');
        $table->decimal('total_amount', 12, 2)->default(0.00);
        $table->enum('status', ['pending', 'processing', 'completed', 'cancelled'])->default('pending');
        $table->json('items_payload')->nullable();
        $table->text('notes')->nullable();
        $table->boolean('is_paid')->default(false);
        $table->softDeletes();
        $table->timestamps();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index(['user_id', 'status'], 'idx_user_status');

        $sql = $table->toSqlCreate();

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `orders`', $sql);
        $this->assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        $this->assertStringContainsString('`order_number` VARCHAR(50) NOT NULL UNIQUE', $sql);
        $this->assertStringContainsString('`total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0', $sql);
        $this->assertStringContainsString("`status` ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'", $sql);
        $this->assertStringContainsString('`items_payload` JSON NULL', $sql);
        $this->assertStringContainsString('`is_paid` TINYINT(1) NOT NULL DEFAULT 0', $sql);
        $this->assertStringContainsString('`deleted_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringContainsString('`created_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringContainsString('INDEX `idx_user_status` (`user_id`, `status`)', $sql);
        $this->assertStringContainsString('CONSTRAINT `fk_orders_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', $sql);
    }

    public function testAlterTableSqlGeneration(): void
    {
        $alter = new Blueprint('orders');
        $alter->renameColumn('notes', 'customer_notes');
        $alter->dropColumn('legacy_field');
        $alter->dropIndex('idx_user_status');
        $alter->dropForeign('fk_orders_user_id');

        $statements = $alter->toSqlAlter();

        $this->assertCount(4, $statements);
        $this->assertSame('ALTER TABLE `orders` DROP FOREIGN KEY `fk_orders_user_id`;', $statements[0]);
        $this->assertSame('ALTER TABLE `orders` DROP INDEX `idx_user_status`;', $statements[1]);
        $this->assertSame('ALTER TABLE `orders` DROP COLUMN `legacy_field`;', $statements[2]);
        $this->assertSame('ALTER TABLE `orders` RENAME COLUMN `notes` TO `customer_notes`;', $statements[3]);
    }
}
