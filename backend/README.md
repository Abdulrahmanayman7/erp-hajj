# Backend — ERP Hajj

> **Status:** Scaffolded — health endpoint and response envelope only. No business modules, authentication flow, RBAC, or multi-tenancy logic yet.
> **Last updated:** 2026-08-06

Laravel 12 REST API (`/api/v1`) with Sanctum (SPA cookie authentication), MySQL, database queue, Pest, and Pint.

## Setup (native)

```bash
cd backend
composer install
cp .env.example .env    # set DB credentials (MySQL database: erp_hajj)
php artisan key:generate
php artisan migrate
php artisan serve       # http://localhost:8000
```

`GET /api/v1/health` returns the standardized success envelope and requires no authentication.

## Quality checks

| Command | Purpose |
|---|---|
| `php artisan test` | Pest test suite |
| `./vendor/bin/pint --test` | Code style check |
| `composer validate --strict` | composer.json validity |

## Structure

Follows [docs/02-architecture/BACKEND_STRUCTURE.md](../docs/02-architecture/BACKEND_STRUCTURE.md): `app/Core/` for cross-cutting concerns (currently `Shared/ApiResponse` and `Health/`), `app/Modules/` for business modules (created when implemented). Routes live in `routes/api.php` under the `v1` prefix.

## Rules

- Every response uses the envelope from [docs/04-api/API_STANDARDS.md](../docs/04-api/API_STANDARDS.md) (`App\Core\Shared\ApiResponse`).
- Thin controllers, Form Requests, Actions, Policies — see `.cursor/rules/01-backend-standards.mdc`.
- Tenant isolation and audit are mandatory for every future business module.
