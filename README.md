# Trip PHP Framework

A lightweight PHP 8.4 micro-framework with attribute-based routing, a
Fiber-based runtime, a built-in DI container, and a first-class CLI (`trip`)
for scaffolding, migrations, and production optimization.

## Features

- **Attribute-based routing** — `#[Route]`, `#[Get]`, `#[Post]`, `#[Put]`,
  `#[Delete]` on controller classes/methods, with class-level prefixes and
  middleware inheritance.
- **DI Container** — auto-wires constructor dependencies; services (DB,
  session, cache, logger, encryption, JWT, file storage) are bound once in
  `Application::run()`.
- **Route & config caching** — `php trip optimize` compiles routes/config
  into a single file for sub-millisecond boot in production.
- **Security built in** — CSRF, JWT, AES-256-GCM encryption, rate limiting,
  CORS, and a security-headers middleware, all configurable via `.env`.
- **File storage** — validated uploads via `Framework\Storage\FileStorage`,
  with separate private (`storage/app/uploads`) and public (`public/uploads`)
  drivers. See `app/Controller/FileController.php` for the usage pattern.
- **Fail-safe error handling** — `APP_ENV=production` with `APP_DEBUG=true`
  is rejected outright (503, logged server-side) rather than ever rendering
  a debug page in production.
- **Maintenance mode** — `php trip down` / `php trip up`, with an optional
  bypass secret.
- **Migrations, seeders, and code generators** — `make:controller`,
  `make:model`, `make:service`, `make:middleware`, `make:view`,
  `make:migration`, `make:seeder`.

## Requirements

- PHP 8.4
- MySQL (via `pdo_mysql`)
- Composer

## Getting Started

```bash
git clone https://github.com/Lazycoder229/tripphp.git
cd tripphp
composer install
cp .env.example .env
php trip key:generate
php trip jwt:secret
php trip migrate
php trip run
```

The dev server starts at `http://localhost:3000` by default
(`php trip run --host=127.0.0.1 --port=8000` to override).

## The `trip` CLI

Run `php trip help` for the full list. Highlights:

| Category | Commands |
|---|---|
| Routing & Optimization | `route:list`, `route:cache`, `route:clear`, `config:cache`, `config:clear`, `optimize`, `optimize:clear` |
| Database & Migrations | `migrate`, `migrate:rollback`, `migrate:status`, `migrate:fresh`, `db:seed` |
| Code Generators | `make:controller`, `make:model`, `make:service`, `make:middleware`, `make:view`, `make:migration`, `make:seeder` |
| Security & Secrets | `key:generate`, `jwt:secret` |
| Maintenance Mode | `down`, `up` |
| Cache & Logging | `cache:clear`, `view:clear`, `log:clear` |
| Development | `run` |

> **Note:** any edit to a controller's route attributes is ignored while
> `storage/cache/routes.php` exists — that cache is only meant to be
> generated as a production optimization step. Run `php trip route:clear`
> after changing routes during local development.

## Testing

```bash
composer test
```

Runs the PHPUnit suite (`tests/Unit`, `tests/Feature`) — no database
connection required, environment values come from `phpunit.xml`.

## Deployment

See [`DEPLOYMENT.md`](DEPLOYMENT.md) for the full guide, covering:

- **Option A** — Docker & Docker Compose
- **Option B** — Nginx on a bare-metal Linux VPS
- **Option C** — Apache / shared hosting / cPanel (e.g. Hostinger)

`deploy.sh` automates a git-based deploy (maintenance mode →
fetch/reset --hard → `composer install --no-dev` → migrate → recompile
caches → strip dev-only files → back online).

## CI/CD

- `.github/workflows/ci.yml` — runs `composer validate --strict` and the
  PHPUnit suite on every push/PR to `main`.
- `.github/workflows/deploy.yml` — SSH-deploys to production over
  `appleboy/ssh-action`, but only after `ci.yml` passes. Requires
  `HOSTINGER_HOST`, `HOSTINGER_USERNAME`, `HOSTINGER_PORT`,
  `HOSTINGER_SSH_KEY`, and `HOSTINGER_DEPLOY_PATH` repository secrets —
  optional until you're ready to wire it up.

## License

MIT — see [`LICENSE`](LICENSE).
