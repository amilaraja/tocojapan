# CR — CIF calculator add-ons + per-vehicle option upsells

- **Logged:** 2026-05-28
- **Source:** Designs `/home/tocojapan.com/designs/302ebd7d-2b84-413e-bf3e-6eb28728a79b.jfif` and `fafb82fd-e7eb-43ed-9a60-9ca2785b3db1.jfif` (picknbuy24.com reference)
- **Status:** **awaiting answers to open questions** before execution; user pushes 19 unpushed commits first

## Scope (what the designs show)

On the vehicle detail page, immediately under the existing **Calculate Your Total Price** (Country + Port) block, three new add-on rows and an expandable upsell section:

### 1. Three CIF add-on lines (between port selector and grand total)
| Row | Mandatory? | Default | Designed price (USD) |
|---|---|---|---|
| Marine Insurance | optional checkbox | unchecked | **$100** (rough — see Q1) |
| Maintenance Package | optional checkbox | unchecked | **$195** |
| Pre-inspection Fee | **mandatory** (always charged, no checkbox) | always on | **$500** |

Pre-inspection fee renders as a plain row (no checkbox), implying it's part of the baseline quote.

### 2. "Option (Enhance your drive)" upsells (collapsible accordion)
Per the design, a fixed catalogue of customisation options, all optional, all admin-managed. The reference list shown:

| Option | Price |
|---|---|
| Spare Key | $450 |
| New CD Player | $550 |
| Touch Screen Car Stereo | $800 |
| Refresh Package | $400 |
| New Engine Oil | $80 |
| New Battery | $150 |
| Tires Replacement | **ASK** (no price — “ask sales consultants”) |

Footer note in the design: *"New Tire: Please ask the details to sales consultants for above options."*

### 3. Total Price line
Updates live as user toggles add-ons + options. Designs read:
> **Total Price: $4,455**  *(CIF USD)*
> Footer: *"CIF = Car Price + Shipping Cost + Warranty + Option + Insurance"*

## Proposed implementation

### Schema
- **`ports` table** — new column `marine_insurance_usd` (decimal:8,2, nullable). Per-port flat fee. Falls back to a global default in `CifSettings` when null. (See Q1 — confirm structure.)
- **`vehicle_options` table** — new
  - `id`, `name`, `price` (decimal, nullable for "ASK" options), `tooltip` (text, nullable), `is_active`, `sort_order`, timestamps
  - Single global catalogue (same options offered for every vehicle, matching the design).
- **`CifSettings`** — new fields:
  - `maintenance_package_usd` (default 195)
  - `pre_inspection_fee_usd` (default 500)
  - `marine_insurance_default_usd` (default 100, fallback when port has none)
- **No per-vehicle pivot** — options are global. Admin can disable individual ones via `is_active`. (See Q2.)

### Backend
- `CifCalculator::calculate()` extended to accept an optional `$addons` array (`['marine_insurance' => bool, 'maintenance_package' => bool, 'options' => [option_id, …]]`) and return the breakdown including each add-on + total.
- Pre-inspection fee is unconditional in the breakdown.
- New `App\Models\VehicleOption` Eloquent model.
- Admin: new Filament resource `/admin/vehicle-options` with editable name / price (nullable) / tooltip / active / order.

### Frontend (`vehicles/show.blade.php`)
- Inside the existing right-sidebar pricing card, after the country/port row:
  - 3 add-on rows (checkbox for the two optional ones; plain row for pre-inspection).
  - Collapsible "Option (Enhance your drive)" accordion listing every active `VehicleOption`. Rows with `price = NULL` render the word "ASK" (greyed) and don't contribute to the total.
- Alpine state tracks the toggle set; the total recalculates client-side from the same numbers shipped down in the initial Blade payload (no extra fetch).
- Existing CIF formula stays — add-ons are layered on top.

### Admin UX
- "Add-on defaults" tab on `/admin/settings` (pre-inspection / maintenance / marine fallback amounts).
- "Marine insurance" per port input on the existing port edit form.
- New `/admin/vehicle-options` resource (name / price / tooltip / active / sort_order). Seeded with the 7 reference options at deploy.

## Open questions (need answers before execution)

**Q1. Marine insurance pricing model.** Per-port flat USD amount (column on `ports`)? Or a percentage of CIF total? Or both with a "use this if set, otherwise %"? The design shows `$100` flat — assuming per-port flat unless told otherwise.

**Q2. "ASK"-price options.**
- Render the word **ASK** in the price slot, checkbox stays clickable but doesn't change the total — then collect a flag with the quote so sales follows up? (this is what the picknbuy24 design implies)
- OR hide them from the public list and only show on a separate "request quote" form?
- OR something else?

**Q3. Pre-inspection fee scope.**
- Universal `$500` from settings, applied to every vehicle?
- OR per-vehicle override possible (some sellers may have it already done)?

**Q4. Does the user (buyer) need to be logged in to use any of this**, or is the live-total purely a calculator that drives a quote request at the end?

## Rollout plan

1. User answers Q1–Q4 and pushes the 19 queued commits.
2. Migration: ports.marine_insurance_usd, vehicle_options table, CifSettings additions.
3. Seed `vehicle_options` with the 7 reference rows.
4. Backend: extend `CifCalculator`, add `VehicleOption` model + Filament resource.
5. Frontend: render add-on rows + Options accordion + Alpine total.
6. Admin: add Marine-insurance field to PortForm; add CifSettings inputs to /admin/settings.
7. Smoke test: pick a Honda Acty Truck, toggle each combo, verify the total reads correctly.
8. Build CSS, commit, recycle lsphp.

## Files expected to change
- `database/migrations/2026_05_29_*_add_marine_insurance_to_ports.php` (new)
- `database/migrations/2026_05_29_*_create_vehicle_options.php` (new)
- `database/settings/2026_05_29_*_add_cif_addon_defaults.php` (new)
- `app/Models/VehicleOption.php` (new)
- `app/Filament/Admin/Resources/VehicleOptions/…` (new, generated)
- `app/Settings/CifSettings.php` (extend)
- `app/Services/CifCalculator.php` (extend signature)
- `app/Filament/Admin/Resources/Ports/Schemas/PortForm.php` (add marine_insurance_usd input)
- `app/Filament/Admin/Pages/Settings.php` (add Add-ons tab)
- `app/Http/Controllers/VehicleController.php::show()` (pass options to view)
- `resources/views/vehicles/show.blade.php` (add the three rows + accordion)
