# Framework\Database\Model

Base abstract class para sa lahat ng database models. Nagbibigay ng fluent query builder interface at basic CRUD operations sa ibabaw ng `ConnectionInterface`.

---

## Namespace

```
Framework\Database\Model
```

## Location

```
src/Database/Model.php
```

---

## Setup

### 1. I-extend ang Model

```php
namespace App\Models;

use Framework\Database\Model;

class Trip extends Model
{
    protected string $table = 'trips';
    protected array $fillable = ['title', 'destination', 'status', 'price'];
    protected array $guarded  = ['id', 'created_at'];
}
```

### 2. I-inject sa Service o Controller

```php
class TripService
{
    public function __construct(
        private Trip $trip  // autowired ng Container
    ) {}
}
```

> Hindi na kailangan i-register ang Model sa `Application.php` — ang Container ay awtomatikong nag-aawire ng lahat ng concrete classes basta nakaregister na ang `ConnectionInterface`.

---

## Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `$table` | `string` | `''` | Pangalan ng database table |
| `$fillable` | `array` | `[]` | Whitelist ng columns na pwedeng i-INSERT/UPDATE |
| `$guarded` | `array` | `['id']` | Blacklist ng columns na hindi pwedeng i-mass assign |

### `fillable` vs `guarded`

| May `$fillable`? | Behavior |
|---|---|
| ✅ May laman | Whitelist — tanggap lang ang nasa `$fillable` |
| ❌ Walang laman | Blacklist — tanggap lahat **except** ang nasa `$guarded` |

---

## CRUD Methods

### `all(): array`

Ibinabalik ang lahat ng records mula sa table.

```php
$trips = $this->trip->all();
// SELECT * FROM trips
```

---

### `find(int $id): ?array`

Hinahanap ang isang record gamit ang primary key. Nagbabalik ng `null` kung wala.

```php
$trip = $this->trip->find(1);
// SELECT * FROM trips WHERE id = 1
```

---

### `create(array $data): string`

Nag-iinsert ng bagong record. Ibinabalik ang ID ng bagong record.
Ang `fillable`/`guarded` ay iniaaplika bago mag-INSERT.

```php
$id = $this->trip->create([
    'title'       => 'Boracay Trip',
    'destination' => 'PH',
    'status'      => 'active',
]);
// INSERT INTO trips (title, destination, status) VALUES (?, ?, ?)
```

---

### `update(int $id, array $data): int`

Ina-update ang isang record. Ibinabalik ang bilang ng affected rows.
Ang `fillable`/`guarded` ay iniaaplika bago mag-UPDATE.

```php
$affected = $this->trip->update(1, [
    'status' => 'inactive',
]);
// UPDATE trips SET status = ? WHERE id = ?
```

---

### `delete(int $id): int`

Nagde-delete ng isang record. Ibinabalik ang bilang ng affected rows.

```php
$affected = $this->trip->delete(1);
// DELETE FROM trips WHERE id = ?
```

---

## Fluent Builder Methods

Ang mga ito ay **chainable** — pwedeng pagsamahin bago mag-execute.

### `select(string ...$columns): static`

Tinutukoy ang mga columns na isasama sa SELECT.

```php
$this->trip->select('id', 'title', 'destination')->get();
// SELECT id, title, destination FROM trips
```

> **Security:** Ang mga column name ay bina-validate laban sa `fillable` + `guarded`. Mag-tthrow ng `FrameworkException` kung hindi allowed.

---

### `where(string $column, mixed $value, string $operator = '='): static`

Nagdadagdag ng WHERE clause. Multiple `where()` calls ay pinagsasama gamit ang `AND`.

```php
$this->trip
    ->where('status', 'active')
    ->where('price', 1000, '>')
    ->get();
// WHERE status = ? AND price > ?
```

**Allowed operators:**

| Operator | Gamit |
|---|---|
| `=` | Equal (default) |
| `!=` | Not equal |
| `<` | Less than |
| `>` | Greater than |
| `<=` | Less than or equal |
| `>=` | Greater than or equal |
| `LIKE` | Pattern matching (gamitin ang `%` sa value) |

> **Security:** Ang column name at operator ay bina-validate. Mag-tthrow ng `FrameworkException` kung hindi allowed.

**LIKE example:**

```php
$this->trip->where('title', '%Boracay%', 'LIKE')->get();
// WHERE title LIKE ?  → value: '%Boracay%'
```

---

### `orderBy(string $column, string $direction = 'ASC'): static`

Tinutukoy ang ORDER BY clause.

```php
$this->trip->orderBy('created_at', 'DESC')->get();
// ORDER BY created_at DESC
```

**Allowed directions:** `ASC`, `DESC`

> **Security:** Ang column name at direction ay bina-validate. Mag-tthrow ng `FrameworkException` kung hindi allowed.

---

### `limit(int $n): static`

Tinutukoy ang maximum na bilang ng rows na ibabalik.

```php
$this->trip->limit(10)->get();
// LIMIT 10
```

---

### `offset(int $n): static`

Tinutukoy ang starting point ng query. Karaniwang ginagamit kasama ng `limit()` para sa pagination.

```php
$this->trip->limit(10)->offset(20)->get();
// LIMIT 10 OFFSET 20  → page 3
```

---

## Terminal Methods

Ang mga ito ang **nag-eexecute ng query** — dapat laging nasa dulo ng chain.

### `get(): array`

Ini-execute ang built query at ibinabalik ang lahat ng matching rows.

```php
$trips = $this->trip
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();
```

---

### `first(): ?array`

Ini-execute ang built query at ibinabalik ang unang row. Nagbabalik ng `null` kung wala.

```php
$trip = $this->trip
    ->where('title', 'Boracay Trip')
    ->first();
```

---

## Custom Methods sa Child Model

Para sa mas specific na queries, pwede kang magdagdag ng sariling methods sa iyong Model gamit ang `$this->db` directly.

```php
class Trip extends Model
{
    protected string $table = 'trips';
    protected array $fillable = ['title', 'destination', 'status'];
    protected array $guarded  = ['id', 'created_at'];

    // Custom method — gumagamit ng $this->db directly
    public function getActiveByDestination(string $destination): array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE status = ? AND destination = ?",
            ['active', $destination]
        );
    }

    public function deactivateAll(): int
    {
        return $this->db->execute(
            "UPDATE {$this->table} SET status = 'inactive'"
        );
    }
}
```

### Available `$this->db` methods

| Method | Returns | Gamit |
|---|---|---|
| `query(string $sql, array $bindings)` | `array` | SELECT — maraming rows |
| `queryOne(string $sql, array $bindings)` | `?array` | SELECT — isang row |
| `execute(string $sql, array $bindings)` | `int` | INSERT / UPDATE / DELETE |
| `lastInsertId()` | `string` | ID ng pinakabagong INSERT |
| `beginTransaction()` | `void` | Magsimula ng transaction |
| `commit()` | `void` | I-commit ang transaction |
| `rollBack()` | `void` | I-rollback ang transaction |

---

## Transactions

```php
class Trip extends Model
{
    public function transferStatus(int $fromId, int $toId): void
    {
        $this->db->beginTransaction();

        try {
            $this->db->execute(
                "UPDATE {$this->table} SET status = 'inactive' WHERE id = ?",
                [$fromId]
            );
            $this->db->execute(
                "UPDATE {$this->table} SET status = 'active' WHERE id = ?",
                [$toId]
            );
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
```

---

## Security

| Feature | Paano Protektado |
|---|---|
| SQL Injection | Lahat ng values ay dumadaan sa `?` placeholders — real prepared statements |
| Mass Assignment | Ang `fillable`/`guarded` ay nag-fifilter ng input bago mag-INSERT/UPDATE |
| Column Injection | Ang `select()`, `where()`, `orderBy()` ay nagva-validate ng column names laban sa whitelist |
| Operator Injection | Ang `where()` ay nagva-validate ng operators laban sa allowed list |
| Direction Injection | Ang `orderBy()` ay nagva-validate — `ASC` at `DESC` lang ang accepted |

---

## Exceptions

| Exception | Kailan Nag-tthrow |
|---|---|
| `Framework\Exception\FrameworkException` | Invalid column, operator, o sort direction |
| `Framework\Exception\QueryException` | SQL execution failure |
| `Framework\Exception\ConnectionException` | Database connection failure |

---

## Complete Usage Example

```php
// Model
class Trip extends Model
{
    protected string $table = 'trips';
    protected array $fillable = ['title', 'destination', 'status', 'price'];
    protected array $guarded  = ['id', 'created_at'];
}

// Service
class TripService
{
    public function __construct(private Trip $trip) {}

    // Basic CRUD
    public function getAll(): array          { return $this->trip->all(); }
    public function find(int $id): ?array    { return $this->trip->find($id); }
    public function create(array $data): string { return $this->trip->create($data); }
    public function update(int $id, array $data): int { return $this->trip->update($id, $data); }
    public function delete(int $id): int     { return $this->trip->delete($id); }

    // Fluent builder
    public function getActive(): array
    {
        return $this->trip
            ->select('id', 'title', 'destination', 'price')
            ->where('status', 'active')
            ->where('price', 0, '>')
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->offset(0)
            ->get();
    }

    public function search(string $keyword): array
    {
        return $this->trip
            ->where('title', "%{$keyword}%", 'LIKE')
            ->where('status', 'active')
            ->get();
    }

    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $data = $this->trip
            ->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        $total = count($this->trip->all());

        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }
}
```