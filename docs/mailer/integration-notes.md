# TOCO Mailer: integration notes (Phase 1)

**Closes:** OPEN-02, OPEN-07 | **Spec:** SRS-TOC-01 v1.1 (`docs/email_automation/`) | **Date:** 24 September 2026
**Branch:** `feat/toco-mailer` (from `fix/jsonld-context-directive` @ 7d76bd7)

This file records how the Mailer module plugs into the existing tocojapan.com app. It also lists every change the module makes to shared code (hard rule 9).

---

## 1. Platform

| Item | Found |
|---|---|
| Laravel | 13.18.0 |
| PHP | 8.3.28 (CLI and `lsphp83`) |
| Database | MySQL (existing app database). Tests run on SQLite `:memory:` (phpunit.xml) |
| Test runner | **Pest** (`tests/Pest.php`), `php artisan test` |
| Cache / queue | `CACHE_STORE=database`, `QUEUE_CONNECTION=database` (`jobs` and `failed_jobs` tables exist and are empty) |
| Mail | `MAIL_MAILER=smtp`. Used for the TOC-IMP-009 failure alert |
| Server | OpenLiteSpeed/CyberPanel, docroot `public/` |

## 2. How the admin is built: Filament 5, not a hand-rolled CMS

The "Mobiz CMS module convention" in this repo is **one Filament panel** (`app/Providers/Filament/AdminPanelProvider.php`, path `/admin`, id `admin`):

- It auto-discovers Resources in `app/Filament/Admin/Resources/{Name}/` (split into `{Name}Resource.php`, `Pages/`, `Schemas/{Name}Form.php` and `Tables/{Name}Table.php`), Pages in `app/Filament/Admin/Pages/` and Widgets in `app/Filament/Admin/Widgets/`.
- Navigation groups: `Catalogue, Content, Enquiries, Shipping, System`. Pages set `$navigationGroup` and `$navigationSort`.
- Admin auth: Filament `Authenticate` middleware plus `User::canAccessPanel()`, which checks `hasAnyRole(['super_admin','admin','sales'])`. An unauthenticated request redirects to `/admin/login` (custom Login page with Turnstile).
- Theme: `resources/css/filament/admin/theme.css` (Vite), primary colour `#E30613`.
- The UI stack is Livewire 3 + Alpine (bundled with Filament). Livewire is already the admin's stack, so under the plan's rule "use Livewire only if the admin already uses it", Mailer screens are Filament Pages and Resources. **No SortableJS needed:** Filament's `Repeater` has built-in drag reorder and keyboard move up/down buttons (TOC-NFR-006).
- Settings pattern: `spatie/laravel-settings` classes in `app/Settings/`, edited on `app/Filament/Admin/Pages/Settings.php` (tabs), with encryption via `static encrypted()` (see `SocialSettings`).
- Page gates use `public static function canAccess(): bool` (see `Settings`, `UserResource`). Filament hides the menu item and returns 403 when it is false.

### Decision: module layout adapted to Filament

`app/Modules/Mailer/` with its own provider as the plan suggests. Admin screens are Filament classes, registered explicitly on the panel from `MailerServiceProvider::filamentPages()` / `filamentResources()` rather than discovered:

```
app/Modules/Mailer/
  MailerServiceProvider.php          registered in bootstrap/providers.php
  config/mailer.php                  merged as config('mailer')
  Database/Migrations/  Database/Seeders/   loaded via loadMigrationsFrom
  routes/public.php                  none needed: /storage/email-assets is served by the existing storage link
  Domain/{Importer,Brevo,Vehicles,Campaigns}/
  Jobs/  Models/  Console/
  Filament/Pages/  Filament/Resources/  Filament/Widgets/   registered by AdminPanelProvider
  resources/views/                   namespace "mailer::"
tests/Mailer/{Unit,Feature,Fixtures/eml}
```

Navigation group **"Mailer"** is added to the panel's `navigationGroups` list: Overview, Campaigns, Banners, Importer (admin only), Mailer Settings (admin only).

## 3. Permissions (TOC-GEN-002/003)

Mechanism: **spatie/laravel-permission v7** (`HasRoles` on `User`). Seeded by `database/seeders/RoleSeeder.php`. Roles: `super_admin`, `admin`, `sales`, `customer`. The Users screen (`UserForm`) grants access with a **roles multi-select only**; there is no per-user permission picker.

Decision: to meet "granted through the existing user screens" without redesigning that screen:

- Permissions `mailer.admin` and `mailer.marketer`.
- Roles `mailer_admin` (both permissions) and `mailer_marketer` (marketer only). Both appear in the existing roles picker automatically.
- `super_admin` gets both permissions (the RoleSeeder already syncs `Permission::all()` to super_admin). Plain `admin` and `sales` get **no** Mailer access until the role is added, as TOC-GEN-004 requires.
- Gates: `Gate::define('mailer.use', …)` (either permission) and `mailer.admin`. Every Mailer page's `canAccess()` uses them.
- Seeder: `Database\Seeders\MailerPermissionSeeder` (idempotent, calls `permission:cache-reset`).

**Shared-code change needed:** `User::canAccessPanel()` must also allow `mailer_admin` and `mailer_marketer`. Otherwise a marketer-only user can't log in to /admin. Consequence: a marketer-only user will also see non-Mailer resources that have no `canAccess()` gate (e.g. Vehicles, Quotes). Today those are open to every panel user (`sales` sees them too). **Decision (approved 2026-09-24):** accepted as is. No extra guards are added to the other resources.

## 4. Vehicle model: field map (TOC-VEH-002)

Model `App\Models\Vehicle` (SoftDeletes, LogsActivity, spatie medialibrary `HasMedia`). Relations: `make`, `vehicleModel`, `bodyType` (all BelongsTo). Live data: 136 published, 33 sold, 1 draft.

| SRS field | Source | Notes |
|---|---|---|
| stock ref | `stock_no` (e.g. `E02056`, `E02059`) | `ref_no` is legacy and null on current stock. Fall back to `ref_no` when `stock_no` is empty |
| title | `title` | Already uppercase, e.g. "2022 TOYOTA HIACE COMMUTER" |
| make / model / body type | `make->name`, `vehicleModel->name`, `bodyType->name` | Filter by `make_id` and `body_type_id` |
| model year | `manufacture_year` (+ `manufacture_month`) | |
| registration year | `year_first_reg` (+ `registration_month`) | The site's card meta line uses `year_first_reg`; the email does the same |
| mileage km | `mileage_km` (int) | |
| transmission | `transmission`: `automatic` / `manual` / null | Displayed as "Automatic" |
| FOB price USD | `effectivePriceFob()` = `price_fob_discount` when > 0, otherwise `price_fob`; null when `price_on_request` | `currency` column exists; all stock is USD |
| previous price | `price_fob` **when `isDiscounted()`** | No separate "previous price" column. The discount is how the site shows a struck-through price |
| badge | `is_featured` → **HOT DEAL**; `isNewArrival()` (7 most recently published) → **NEW**; else none | Same precedence as `components/vehicle-card.blade.php` (SOLD > Hot Deal > New) |
| status | `status`: `draft` / `published` / `sold` (+ `sold_at`, soft-deleted rows) | **There is no "reserved" status.** TOC-VEH-003 "include reserved" has nothing to map to: I'll implement available = `published`, and the admin option is a no-op until a reserved status exists. Draft, sold and trashed are never selectable |
| primary photo | `getFirstMedia('photos')`, disk `public`, originals are **WebP** at `storage/app/public/{media_id}/…webp` (watermarked and ref-stamped by `ImageProcessor`) | Conversions `thumb`, `card` (560×420 webp), `gallery` (1280w webp) |
| public URL | `route('vehicles.show', $vehicle->slug)` | e.g. `https://tocojapan.com/vehicles/2022-toyota-hiace-commuter` |

Reusable scopes: `scopeFilter()` already handles `q` (title/ref/stock/make/model), price range on the effective price, `featured`, and make/body-type by slug. `VehicleSource` reuses it. `scopePublished()` is **not** reused, because it includes sold vehicles for 90 days. Search uses `where('status','published')` instead.

Write safety: `Vehicle::booted()` has a `saving` hook. `VehicleSource` never calls save, update or delete, and a test asserts no INSERT, UPDATE or DELETE queries during search/find (TOC-VEH-001).

## 5. Images (TOC-VEH-006, TOC-BAN-002)

- Libraries present: **spatie/image 3.9** (used by `ImageProcessor` and medialibrary), with both **GD and Imagick** loaded. No intervention/image, and it isn't needed: `EmailImageService` uses spatie/image (`Fit::Crop` 540×310, JPEG, quality stepped down until < 70 KB).
- Photo originals are WebP. Email clients (Outlook desktop) don't render WebP reliably, so email images are always converted to JPEG.
- Output: `storage/app/public/email-assets/vehicles/{id}-{hash}.jpg` and `…/banners/…`, served by the existing `public/storage` symlink at `https://tocojapan.com/storage/email-assets/…`. No new route is needed (TOC-NFR-002).
- Placeholder (TOC-VEH-007): a branded 540×310 JPEG generated once from the logo on black.

## 6. Queue and scheduler (OPEN-07): fixed 2026-09-24

- Found: no cron entry ran `schedule:run` for this site and no queue worker was running, so the existing daily `currency:fetch-rates` never ran either.
- Fixed (approved): a per-minute `artisan schedule:run` cron entry under the site user. The queue worker runs from the scheduler (`queue:work --queue=mailer,default --stop-when-empty --max-time=55`, `withoutOverlapping`), so no supervisor is needed.
- Side effect (accepted): the daily currency-rate fetch now runs.

## 7. External packages

| Need | Status |
|---|---|
| `google/apiclient` ^2.19 | **Already installed** (used by `GoogleSearchConsoleService`) |
| `tijsverkoyen/css-to-inline-styles` 2.4 | **Already installed** (transitive via laravel/framework). Add it as a direct `require` so it can't disappear |
| intervention/image, SortableJS, Alpine | Not needed (see sections 2 and 5) |
| Brevo | In-house client over `Http`, no SDK |

## 8. Design inputs

- Round 1 email exports are extracted from `docs/email_automation/Toco_mailer_design.zip` into `docs/mailer/design/email/`: 2, 5, 6, 12 vehicles, images off, dark sim, a compact variant, and sample images. The 6-vehicle file has the `VEHICLE_CARD_START/END` and `FIELD:*` markers. The 12-vehicle design export is 45.6 KB, well under the 90 KB limit.
- **No Round 3 admin designs in the zip.** Because the admin is Filament, the screens are built from Filament components (tables, forms, modals, repeaters) plus a few small custom pieces (status pills, vehicle rows, notices). Design approval can happen on the built screens.
- Brand red: the design uses `#E10613`, but the site and admin use `#E30613` (`--color-toco-red`). OPEN-10 decision (approved): the email uses the site's `#E30613`.

## 9. Shared-code changes (hard rule 9 register)

| File | Change | Why |
|---|---|---|
| `bootstrap/providers.php` | register `MailerServiceProvider` | allowed (provider registration) |
| `app/Providers/Filament/AdminPanelProvider.php` | add "Mailer" navigation group, plus `...MailerServiceProvider::filamentPages()` and `->resources(MailerServiceProvider::filamentResources())` | allowed (menu registration) |
| `database/seeders/DatabaseSeeder.php` | call `MailerPermissionSeeder` | allowed (permission seeding) |
| `app/Models/User.php` | `canAccessPanel()` also allows `mailer_admin` and `mailer_marketer` | approved 2026-09-24 (section 3) |
| `composer.json` | direct require of `tijsverkoyen/css-to-inline-styles` | already in vendor (Phase 5) |
| `routes/console.php` | scheduled `queue:work --queue=mailer,default --stop-when-empty` every minute | OPEN-07 worker (approved) |
| `phpunit.xml`, `tests/Pest.php` | add the `tests/Mailer` suite | test wiring |
| `CLAUDE.md` | new file with the Mailer section | plan section 3 |
| `.gitignore` | ignore the design zip | done in 7d76bd7 |

## 10. Open items still needed from TOCO before later phases

A1/A2 (Workspace mailbox and delegation) and A5 (sender list plus `.eml` samples) are needed for Phase 2. A4/A6 (Brevo key, lists, consent mode) are needed for Phase 3. A7 (DNS) is needed for Phase 7. OPEN-09 (backfill date) is needed for Phase 8.
