# PHP Framework

A modern, lightweight PHP framework built on **PHP 8.4** using **Attributes** and **Reflection**. Designed to be incremental, readable, and developer-friendly.

---

## Requirements

- PHP 8.1+
- Composer

---

## Installation

```bash
composer install
```

Start the development server:

```bash
php -S localhost:8000 -t public
```

---

## Directory Structure

```
my-framework/
├── public/
│   └── index.php               # Entry point
├── src/
│   ├── Application.php         # Bootstrap
│   ├── Container/
│   │   ├── Container.php       # Dependency Injection Container
│   │   └── ContainerException.php
│   ├── Http/
│   │   ├── Request.php         # HTTP Request wrapper
│   │   └── Response.php        # HTTP Response
│   └── Routing/
│       ├── Controller.php      # #[Controller] attribute
│       ├── ControllerDiscovery.php  # Auto-discovery
│       ├── Route.php           # #[Route] attribute
│       └── Router.php          # Route matching + dispatch
├── app/
│   ├── Controllers/            # Your controllers go here
│   └── Services/               # Your services go here
└── composer.json
```

---

## Application Bootstrap

The `Application` class is the main entry point of the framework. It is a **Singleton** — only one instance exists per request lifecycle.

`public/index.php` calls it like so:

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Application;

Application::create()
    ->withBindings(function ($container) {
        // register your bindings here
    })
    ->withControllerDirectory(dirname(__DIR__) . '/app/Controllers')
    ->run();
```

The bootstrap flow:

```
public/index.php
    → Application::create()       # instantiate singleton
    → withBindings()              # register container bindings
    → withControllerDirectory()   # auto-discover controllers
    → run()                       # capture request, dispatch
```

---

## Routing

Routes are defined using the `#[Route]` attribute directly on controller methods. No separate route file needed.

### Basic Usage

```php
<?php

namespace App\Controllers;

use Framework\Http\Response;
use Framework\Routing\Route;

class HomeController
{
    #[Route('/', 'GET')]
    public function index(): Response
    {
        return Response::html('<h1>Hello World!</h1>');
    }

    #[Route('/about', 'GET')]
    public function about(): Response
    {
        return Response::html('<h1>About Page</h1>');
    }
}
```

### Supported HTTP Methods

| Method | Constant |
|--------|----------|
| GET | `Route::GET` |
| POST | `Route::POST` |
| PUT | `Route::PUT` |
| PATCH | `Route::PATCH` |
| DELETE | `Route::DELETE` |

```php
#[Route('/users', Route::GET)]
#[Route('/users', Route::POST)]
#[Route('/users/{id}', Route::PUT)]
#[Route('/users/{id}', Route::DELETE)]
```

### Route Parameters

Wrap parameter names in `{}` in the URI. They are automatically extracted and injected into the method by name.

```php
#[Route('/users/{id}', 'GET')]
public function show(string $id): Response
{
    return Response::json(['id' => $id]);
}

#[Route('/users/{id}/posts/{slug}', 'GET')]
public function post(string $id, string $slug): Response
{
    return Response::json([
        'user' => $id,
        'post' => $slug,
    ]);
}
```

Test in browser:
```
GET /users/42              → { "id": "42" }
GET /users/42/posts/hello  → { "user": "42", "post": "hello" }
```

---

## Controllers

### Naming Convention

Any class inside the controllers directory with a `Controller` suffix is auto-discovered:

```php
// auto-discovered ✅
class HomeController {}
class UserController {}
class AdminController {}
```

### #[Controller] Attribute

For classes without the `Controller` suffix, use the `#[Controller]` attribute:

```php
<?php

namespace App\Controllers;

use Framework\Routing\Controller;
use Framework\Routing\Route;
use Framework\Http\Response;

#[Controller]
class AdminPanel
{
    #[Route('/admin', 'GET')]
    public function index(): Response
    {
        return Response::html('<h1>Admin Panel</h1>');
    }
}
```

### Auto-Discovery

The framework automatically scans `app/Controllers/` and registers all controllers. No manual registration needed.

Whenever you add a new controller, run:

```bash
composer dump-autoload
```

---

## Request

The `Request` class wraps all incoming HTTP data. It is auto-injected into controller methods when type-hinted.

```php
use Framework\Http\Request;

#[Route('/users', 'POST')]
public function store(Request $request): Response
{
    $name  = $request->input('name');        // from body
    $page  = $request->query('page', 1);     // from ?page=1
    $token = $request->header('Authorization'); // from headers

    return Response::json(['name' => $name]);
}
```

### Available Methods

| Method | Description |
|--------|-------------|
| `$request->method` | HTTP method (`GET`, `POST`, etc.) |
| `$request->uri` | Request URI (`/users/42`) |
| `$request->input(key, default)` | Get value from request body |
| `$request->query(key, default)` | Get value from query string |
| `$request->header(key, default)` | Get a request header |
| `$request->isJson()` | Check if body is JSON |
| `$request->wantsJson()` | Check if client expects JSON |

### JSON Requests

The framework automatically parses JSON bodies when `Content-Type: application/json` is set:

```bash
curl -X POST http://localhost:8000/users \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe"}'
```

```php
$name = $request->input('name'); // "John Doe"
```

---

## Response

The `Response` class manages the outgoing HTTP response. Controller methods should return a `Response` instance.

### Response Types

```php
// HTML response
return Response::html('<h1>Hello</h1>');

// JSON response
return Response::json(['key' => 'value']);

// Plain text
return Response::text('Hello World');
```

### Status Codes

```php
return Response::json(['message' => 'Created'], 201);
return Response::json(['message' => 'Not Found'], 404);
```

### Custom Headers

```php
return Response::json($data)
    ->withHeader('X-Custom-Header', 'value')
    ->withStatus(202);
```

---

## Dependency Injection Container

The Container automatically resolves class dependencies via **Reflection**. You never need to manually instantiate services.

### Auto-Resolution

If a service has no special binding, the Container resolves it automatically:

```php
// UserService is automatically injected — no binding needed
class HomeController
{
    public function __construct(
        private UserService $userService,
    ) {}
}
```

### Binding Interfaces

When a class depends on an interface, register a binding in `public/index.php`:

```php
Application::create()
    ->withBindings(function ($container) {
        $container->bind(
            UserRepositoryInterface::class,
            MySqlUserRepository::class
        );
    })
    ->withControllerDirectory(...)
    ->run();
```

### Singletons

Register a class as a singleton so only one instance is created per request:

```php
->withBindings(function ($container) {
    $container->singleton(Database::class, Database::class);

    // or with a closure for custom instantiation
    $container->singleton(Database::class, function ($container) {
        return new Database(
            host: 'localhost',
            name: 'mydb',
        );
    });
})
```

### Nested Dependencies

The Container resolves nested dependencies automatically:

```
HomeController
    → UserService          # auto-resolved
        → UserRepository   # auto-resolved
            → Database     # singleton
```

---

## Example: Full Controller

```php
<?php

namespace App\Controllers;

use App\Services\UserService;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

class UserController
{
    public function __construct(
        private UserService $userService,
    ) {}

    #[Route('/users', 'GET')]
    public function index(): Response
    {
        return Response::json($this->userService->all());
    }

    #[Route('/users/{id}', 'GET')]
    public function show(string $id): Response
    {
        $user = $this->userService->find($id);
        return Response::json($user);
    }

    #[Route('/users', 'POST')]
    public function store(Request $request): Response
    {
        $name = $request->input('name');
        return Response::json(['message' => "Created: {$name}"], 201);
    }

    #[Route('/users/{id}', 'PUT')]
    public function update(string $id, Request $request): Response
    {
        $name = $request->input('name');
        return Response::json(['message' => "Updated user {$id}: {$name}"]);
    }

    #[Route('/users/{id}', 'DELETE')]
    public function destroy(string $id): Response
    {
        return Response::json(['message' => "Deleted user {$id}"]);
    }
}
```

---

## Troubleshooting

**Class not found errors** — always run after adding new files:
```bash
composer dump-autoload
```

**Route not found (404)** — check that the HTTP method matches the `#[Route]` definition and the URI pattern is correct.

**Container cannot resolve** — if a class depends on a primitive type (string, int) with no default value, the Container cannot auto-resolve it. Use `withBindings()` to register a closure that manually instantiates it.