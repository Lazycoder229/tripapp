<?php

declare(strict_types=1);

namespace Framework\Database\Schema;

/**
 * Column
 * 
 * Represents a single database column definition in a Schema Blueprint.
 * 
 * @package Framework\Database\Schema
 */
final class Column
{
    private bool $nullable = false;
    private mixed $default = null;
    private bool $hasDefault = false;
    private bool $unique = false;
    private bool $unsigned = false;
    private bool $autoIncrement = false;
    private bool $primary = false;
    private bool $index = false;
    private ?string $after = null;
    private ?string $comment = null;
    private ?string $foreignReferences = null;
    private ?string $foreignOn = null;
    private ?string $foreignOnDelete = null;
    private ?string $foreignOnUpdate = null;

    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly array $parameters = []
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function index(bool $value = true): self
    {
        $this->index = $value;
        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;
        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;
        return $this;
    }

    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function references(string $column): self
    {
        $this->foreignReferences = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->foreignOn = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->foreignOnDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->foreignOnUpdate = strtoupper($action);
        return $this;
    }

    public function hasForeignKey(): bool
    {
        return $this->foreignReferences !== null && $this->foreignOn !== null;
    }

    public function hasIndex(): bool
    {
        return $this->index;
    }

    public function getForeignKeySql(string $table): string
    {
        $fkName = "fk_{$table}_{$this->name}";
        $sql = "CONSTRAINT `{$fkName}` FOREIGN KEY (`{$this->name}`) REFERENCES `{$this->foreignOn}` (`{$this->foreignReferences}`)";

        if ($this->foreignOnDelete !== null) {
            $sql .= " ON DELETE {$this->foreignOnDelete}";
        }
        if ($this->foreignOnUpdate !== null) {
            $sql .= " ON UPDATE {$this->foreignOnUpdate}";
        }

        return $sql;
    }

    /**
     * Builds the SQL column definition for MySQL.
     */
    public function toSql(): string
    {
        $typeSql = match ($this->type) {
            'id', 'bigIncrements' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'increments'          => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'string', 'varchar'   => 'VARCHAR(' . ($this->parameters['length'] ?? 255) . ')',
            'char'                => 'CHAR(' . ($this->parameters['length'] ?? 255) . ')',
            'text'                => 'TEXT',
            'mediumText'          => 'MEDIUMTEXT',
            'longText'            => 'LONGTEXT',
            'integer', 'int'      => ($this->unsigned ? 'INT UNSIGNED' : 'INT'),
            'tinyInteger'         => ($this->unsigned ? 'TINYINT UNSIGNED' : 'TINYINT'),
            'smallInteger'        => ($this->unsigned ? 'SMALLINT UNSIGNED' : 'SMALLINT'),
            'mediumInteger'       => ($this->unsigned ? 'MEDIUMINT UNSIGNED' : 'MEDIUMINT'),
            'bigInteger'          => ($this->unsigned ? 'BIGINT UNSIGNED' : 'BIGINT'),
            'boolean', 'bool'     => 'TINYINT(1)',
            'decimal'             => 'DECIMAL(' . ($this->parameters['precision'] ?? 10) . ',' . ($this->parameters['scale'] ?? 2) . ')',
            'float'               => 'FLOAT',
            'double'              => 'DOUBLE',
            'date'                => 'DATE',
            'time'                => 'TIME',
            'datetime'            => 'DATETIME',
            'timestamp'           => 'TIMESTAMP',
            'json'                => 'JSON',
            'enum'                => 'ENUM(' . implode(', ', array_map(fn($v) => "'" . addslashes((string)$v) . "'", $this->parameters['values'] ?? [])) . ')',
            default               => strtoupper($this->type),
        };

        if (in_array($this->type, ['id', 'increments', 'bigIncrements'], true)) {
            return "`{$this->name}` {$typeSql}";
        }

        $sql = "`{$this->name}` {$typeSql}";

        if (!$this->nullable) {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }

        if ($this->hasDefault) {
            if ($this->default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_bool($this->default)) {
                $sql .= ' DEFAULT ' . ($this->default ? '1' : '0');
            } elseif (is_numeric($this->default)) {
                $sql .= " DEFAULT {$this->default}";
            } elseif (in_array(strtoupper((string)$this->default), ['CURRENT_TIMESTAMP', 'NOW()'], true)) {
                $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $sql .= " DEFAULT '" . addslashes((string)$this->default) . "'";
            }
        }

        if ($this->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($this->unique) {
            $sql .= ' UNIQUE';
        }

        if ($this->primary) {
            $sql .= ' PRIMARY KEY';
        }

        if ($this->comment !== null) {
            $sql .= " COMMENT '" . addslashes($this->comment) . "'";
        }

        if ($this->after !== null) {
            $sql .= " AFTER `{$this->after}`";
        }

        return $sql;
    }
}
