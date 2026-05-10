# Toco Japan — Laravel platform

Replacement for the WordPress install at tocojapan.com (owner-stock vehicles, CIF
calculator, customer accounts, admin tooling, CMS). The "One Price" auction
inventory remains on WordPress and is **not** migrated.

See `../new_design/MIGRATION_PLAN.md` for the full plan and sprint breakdown.

## Local dev

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev      # in one terminal
php artisan serve --port=8000
```

The default dev DB is sqlite at `database/database.sqlite`. To use the production
schema set `DB_CONNECTION=mysql` and the `DB_*` env vars.

## Tooling

- **Tests** — `./vendor/bin/pest`
- **Static analysis** — `./vendor/bin/phpstan analyse`
- **Code style** — `./vendor/bin/pint` (`--test` for dry-run)
- **Build** — `npm run build`

CI runs all of the above on push / PR (`.github/workflows/ci.yml`).

## Where things live

- `resources/css/app.css` — design tokens (Toco palette + Montserrat / JetBrains Mono).
- `resources/views/welcome.blade.php` — Sprint 0 placeholder; replaced by the v5 home in Sprint 3.
- `config/database.php` — adds a read-only `wp` connection used only by `app/Services/MigrationFromWp/*` once Sprint 8 lands.

## Sprint status

- **Sprint 0** — scaffold, tooling, tokens. Done.
- **Sprint 1** — schema, models, seeders, Filament admin shell at `/admin`,
  Breeze customer auth (Blade), Sanctum-backed JSON API at `/api/v1` for the
  Expo mobile client (auth + Expo push token registry). Done.
- **Sprint 2** — vehicle CRUD + public listings + filters. (pending)

## Default credentials (dev)

After `php artisan migrate:fresh --seed` the super-admin is:
`admin@tocojapan.com` / `password` (change before any non-local deployment).

## WordPress coexistence

This app is meant to run at `new.tocojapan.com` (or `tocojapan.com/new`) during
development. The WP install at the parent docroot continues to serve traffic,
including the One Price auction module, until cutover.
