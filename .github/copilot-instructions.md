# Copilot instructions — FreePanel

Short, actionable guide to help AI agents be productive in this repository.

## Big picture (what matters)
- FreePanel is a Laravel 11 backend + React/TypeScript frontend (see `README.md`). Backend lives under `app/` and the public API is versioned under `routes/api.php` (prefix `/api/v1`).
- System integration is core: the app orchestrates **system services** (Apache/Nginx, MariaDB, Dovecot/Exim, BIND, Pure-FTPd) via `app/Services/*` and `system/scripts/*`. Many changes will touch both application code and host-level configuration.
- Authentication uses **Laravel Sanctum** (`auth:sanctum`) and role checks via **spatie/laravel-permission** (`role:` middleware). Audit logging middleware (`audit`) and quota/throttle middlewares are used widely.

## Where to start (quick wins)
- Read `routes/api.php` to understand public vs. protected endpoints (note `v1` and `admin` prefixes).
- Inspect `app/Services/*` for platform-specific behavior (e.g., `app/Services/WebServer/ApacheService.php`, `NginxService.php`, `DovecotService.php`). Services implement interfaces — prefer using the interface in tests and commands.
- Check `config/freepanel.php` for environment-driven defaults (ports, paths, service mappings).

## Development & local workflow
- Backend: `composer install` then `php artisan serve` for local dev.
- Frontend: `cd frontend && npm ci && npm run dev`; build with `npm run build`.
- Tests: run `php artisan test` or `vendor/bin/phpunit`. The repo's `phpunit.xml` config uses sqlite in-memory for CI/test runs — tests may skip database-dependent checks unless migrations/seeds are run.
- Lint & format: PHP code uses **Pint** (installed in `require-dev`), run `vendor/bin/pint -- --fix`. Frontend lint: `cd frontend && npm run lint`.

## Useful, project-specific commands
- Create initial admin user: `php artisan freepanel:create-admin` (see `app/Console/Commands/CreateAdminCommand.php`).
- Renew SSL via CLI (supports `--dry-run`): `php artisan freepanel:renew-ssl --days=30 --dry-run`.
- Check quotas: `php artisan freepanel:check-quotas`.
- Installer script (dangerous, requires root): `curl -sSL https://.../install.sh | sudo bash` or run locally `system/scripts/install.sh` (only on disposable VMs).

## Conventions & patterns
- API style: RESTful controllers use `Route::apiResource`. Admin endpoints are under `prefix('admin')` and protected with `role:admin` or `role:admin,reseller` middleware.
- Services pattern: `app/Services` contains `*Interface.php` and concrete implementations. Inject interfaces into controllers/commands for testability.
- Middleware usage patterns to note: `auth:sanctum`, `audit`, `quota:xxx`, `throttle:auth`. Tests and agents should respect these (e.g., use test tokens or bypass middleware via configuration when appropriate).
- Models & policies: authorization enforced with `app/Policies/*` and `spatie/laravel-permission` roles.

## Integration & safety notes 🚨
- Many operations are destructive or require root/systemd. **Do not** run installer scripts or system-manipulating commands on your main workstation — use disposable VMs or CI (workflows use dry-run by default: see `.github/workflows/clean_slate_staging.yml`).
- For system-affecting code changes, add/invoke high-level integration tests or document manual verification steps in the PR description.

## Tests, CI and reproducibility
- CI currently includes a staging dry-run workflow that executes `system/scripts/clean_slate.sh` in dry-run mode. Use the workflow as a pattern for safe, reproducible integration checks.
- Unit/feature tests live in `tests/Unit` and `tests/Feature`. Typical test entrypoint: `php artisan test`.
- If adding tests that require real system services, mark them as integration tests and keep them behind an opt-in CI workflow or use emulation/mocks for services.

## PR / contribution guidance
- Keep changes small and focused: include a brief description of how to test locally (commands and env vars). Example: "Run `php artisan migrate --seed` then `php artisan test --filter MyFeatureTest`".
- Run `vendor/bin/pint -- --fix` and `cd frontend && npm run lint` before opening a PR.

---
If anything here is unclear or you want additional examples (e.g., how to mock `WebServerInterface` in a unit test, or which middleware to explicitly bypass in tests), tell me which area to expand and I'll iterate. ✅
