# Pantopack Platform — Detailed Build Plan (for the implementing AI agent)

> **Who this is for:** the Claude Code agent (Opus 5.5) that will implement the project in `C:\www\print`.
> **How to use it:** read this whole file, then `AIDocs/Claude-Code-Prompt.md`, then the 12 migrations and 3 `.tsx` files in `AIDocs/`. Work phase by phase, in order. Stop at every **CHECKPOINT** and report to the user before continuing. Never make a judgment call on anything under "Business rules" — ask.

---

## 0. Working protocol

1. **Read before writing.** Every phase names the files it touches; open them first. The three React components are the source of truth for types, field names and formulas — the backend adapts to them, not the reverse.
2. **Do not rewrite the components.** Move them, remove their sample data, add the minimal edits listed in Phase 2. Diff against `AIDocs/` must stay small and reviewable.
3. **Ask, don't guess**, whenever something touches a business rule (§1.2), a pricing formula, or a dieline formula.
4. **Flag placeholders.** Any pricing constant or formula you touch keeps (or gains) a `PLACEHOLDER` comment, the same style as the existing components.
5. **One phase = one commit.** `git init` in Phase 0; commit at each CHECKPOINT with a message like `Phase 1: schema, models, seeders, catalogue CRUD`. Do not push anywhere.
6. **Verify with real commands** (`php artisan migrate:fresh --seed`, `php artisan test`, `npm run build`, `npx tsc --noEmit`) before declaring a phase done. Report failures verbatim.
7. **Language:** code, identifiers, comments in English; every user-facing string in Egyptian Arabic; UI is RTL and dark (`bg-slate-950`, `border-slate-800`, `text-slate-100`, accent `emerald`/`sky` as in the components); icons from `lucide-react`.
8. **Agents:** work directly. Optionally, at the end of Phases 1–4, run the `security-appsec-engineer` and `testing-api-tester` subagents as *reviewers only* (they must not change business logic).

---

## 1. Context

### 1.1 What is being built
A Laravel application for **Pantopack** (printing & packaging factory, Egypt) covering: CRM (customers + leads), box and manual job costing/quoting, job lifecycle & production stage tracking, press/subcontractor routing (decision support), multi-supplier paper price comparison, and a single outbound integration: **one Odoo 18 API call per job at the end** to create the customer invoice from `produced_quantity` (actual, post-waste).

Odoo's CRM/Sales/Manufacturing/Project/Inventory/Purchase/Accounting apps are **not** used or integrated.

### 1.2 Business rules — never change without asking
- **Profit margin is always entered manually, per job.** No fixed markup tier. Every place margin appears must be an editable field.
- **Press/subcontractor assignment is decision support, not automation.** Filter + sort only; a human always clicks to assign. No auto-assign logic anywhere.
- **Paper pricing is multi-supplier.** Each grammage has N supplier prices; cheapest is the default suggestion, staff may pick any. Never collapse to one price per grammage.
- **Odoo is called exactly once per job, at the end,** with `produced_quantity` (not `quantity`). Every attempt logged in `odoo_invoice_syncs` with request/response payloads. Retry path for transient failures + manual fallback (staff records a hand-made Odoo invoice ID). A failed Odoo call must never block a job from being `completed`.
- **Dieline/imposition formulas are placeholders.** `BOX_SHAPE_CALCULATORS` and the geometry constants in `QuickBoxPricingCalculator.tsx` are not to be "corrected" — ask for real reference dielines instead.

### 1.3 Existing assets (in `C:\www\print\AIDocs\`)
| Asset | Notes |
|---|---|
| `2025_01_01_000001..000012_create_*_table.php` | 12 migrations: customers, paper_suppliers, paper_types, paper_grammages, paper_grammage_prices, dies, presses, jobs, job_cost_lines, job_press_assignments, job_stages, odoo_invoice_syncs |
| `QuickBoxPricingCalculator.tsx` (1532 lines) | Box pricing: die matching, multi-supplier paper, box presets, SVG dieline preview, imposition + interlock estimate, repeat-last-job, `onConfirmOrder(BoxQuote)` |
| `ManualJobCostingCalculator.tsx` (569 lines) | Non-box paper jobs: manual paper line items + free-form cost lines + manual margin, `onConfirmOrder(ManualJobQuote)` |
| `PressRoutingSelector.tsx` (284 lines) | Filters/sorts presses by capability + backlog; manual selection; inline-editable backlog |
| `Claude-Code-Prompt.md`, `Implementation-Plan.md`, `المميزات-Features.md` | Original brief, roadmap, feature list (Arabic) |

### 1.4 Decisions already made with the user (do not re-open)
| Topic | Decision |
|---|---|
| Stack | **Laravel 13 + Inertia 3 + React 19 + TypeScript + Tailwind 4**, scaffolded with `laravel/react-starter-kit` (`dev-main`; ships Fortify auth + Wayfinder + shadcn/ui + lucide-react) |
| Database | **MariaDB** (FlyEnv, local `127.0.0.1:3306`), database `print`, user `print`, password `Fvt9flaggXBh427R` — already created and verified |
| Production | **cPanel** hosting (MariaDB 10.6+, PHP ≥ 8.3, no Docker, no Supervisor). Keep migrations MariaDB-10.6-compatible; queue via cron; build assets locally |
| Odoo | Version **18**, separate server, reached over HTTPS JSON-RPC. Integration built as an isolated service with a fake client until a sandbox exists |
| Schema changes | Approved: `jobs.job_type` (box\|manual); box-only columns nullable; new `job_paper_items`; extra nullable `jobs` columns `title`, `quantity`, `raw_sheets_needed`, `ups_per_raw_sheet`, `interlocked`, `quote_snapshot`; new `settings` table for pricing constants |
| Local dev | Native Windows: `php artisan serve` + `npm run dev` (no Docker) |

### 1.5 Local environment facts (Windows Server 2025)
- PHP **8.4.25** at `C:\Users\Administrator\AppData\Local\Programs\FlyEnv-Data\env\php\php.exe` (extensions present: pdo_mysql, mysqli, intl, mbstring, openssl, gd, zip, fileinfo, curl, bcmath, sqlite3)
- Composer **2.10** at `C:\tools\composer\composer.phar` (`C:\tools\composer` is on the user PATH → `composer` works in new shells; otherwise `php C:\tools\composer\composer.phar …`)
- Node **26.8**, npm **11.19**
- MariaDB **13.0.1** listening on `127.0.0.1:3306`; CLI: `mysql` (on PATH). Test: `mysql -u print -pFvt9flaggXBh427R -e "SHOW DATABASES"` → shows `print`
- Git available. `C:\www\print` is **not** yet a git repo and contains only `AIDocs/`
- Shell: PowerShell 5.1 (no `&&`); Git Bash also available

### 1.6 Two findings that must be handled early
1. **Table name collision:** Laravel's default migration `0001_01_01_000002_create_jobs_table.php` creates a queue table named `jobs`. The domain table is also `jobs`. → Rename the queue tables to `queue_jobs`, `queue_job_batches`, `queue_failed_jobs` and point `config/queue.php` at them **before the first migrate** (Phase 1.1).
2. **Rule violation in the box calculator:** `QuickBoxPricingCalculator.tsx` line 292 `const PROFIT_MARGIN = 1.3;` is applied at line 865 (`sellingPrice = baseCost * PROFIT_MARGIN`). This is a fixed margin → replace with a manual `marginPercent` input (Phase 2.2).

---

## Phase 0 — Environment & scaffold

### 0.1 Scaffold into a temp folder, then move
`composer create-project` refuses a non-empty directory, and `C:\www\print\AIDocs` must be kept.

```powershell
Set-Location C:\www
php C:\tools\composer\composer.phar create-project laravel/react-starter-kit print-tmp dev-main --prefer-dist --no-interaction
```
Then move **everything** from `C:\www\print-tmp` (including dotfiles: `.env`, `.env.example`, `.gitignore`, `.gitattributes`, `.editorconfig`, `.prettierrc`, `.prettierignore`, `.github`, `eslint.config.js`, `components.json`) into `C:\www\print`, and delete `C:\www\print-tmp` and `C:\www\print\database\database.sqlite` (the kit's post-install migrated into SQLite; discard it).

Confirm the kit's actual contents (versions may have moved): `composer.json` should require `laravel/framework ^13`, `inertiajs/inertia-laravel ^3`, `laravel/fortify`, `laravel/wayfinder`; `package.json` should have `@inertiajs/react ^3`, `react ^19`, `tailwindcss ^4`, `lucide-react`. **If the layout differs materially from what this plan assumes (e.g. pages under `resources/js/pages/`, layouts under `resources/js/layouts/`, shadcn primitives under `resources/js/components/ui/`), adapt paths and say so in the checkpoint report.**

Add `AIDocs/` to the `ignores` list in `eslint.config.js` and to `.prettierignore`. Run `git init` and make the first commit (`Phase 0: scaffold`).

### 0.2 `.env`
```
APP_NAME=Pantopack
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=ar_EG
APP_TIMEZONE=Africa/Cairo

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=print
DB_USERNAME=print
DB_PASSWORD=Fvt9flaggXBh427R

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync        # switched to `database` in Phase 4
```
Mirror the non-secret keys into `.env.example`. Charset stays `utf8mb4` / `utf8mb4_unicode_ci` (Arabic-safe).

### 0.3 Frontend + dark RTL shell
- `npm install`, then `npm run build` once so `public/build/manifest.json` exists.
- `resources/views/app.blade.php`: `<html lang="ar" dir="rtl" class="dark">`.
- Make dark the default appearance (the kit has an appearance hook, e.g. `resources/js/hooks/use-appearance.tsx`).
- App layout background/text: `bg-slate-950 text-slate-100`; sidebar/border tones `border-slate-800`. Keep the kit's shadcn primitives; just retune tokens in `resources/css/app.css` so they match the components' palette.
- Optional: Arabic font (`@fontsource/cairo` or Google Fonts "Cairo") applied to `body`.
- Disable public registration (users are created by admin): remove/guard the register routes in Fortify config (`features` array) and hide the link from the login page.

### 0.4 Dev loop
Two terminals: `php artisan serve` and `npm run dev` (or the kit's `composer run dev` script). App at `http://127.0.0.1:8000`.

**CHECKPOINT 0:** `php artisan about` shows `mariadb`; `/login` renders dark + RTL with Vite HMR; do **not** run `migrate` yet (Phase 1.1 renames the queue tables first). Report and commit.

---

## Phase 1 — Schema, enums, models, seeders, catalogue CRUD

### 1.1 Migrations (`database/migrations/`)
1. **Rename the kit's queue migration** `0001_01_01_000002_create_jobs_table.php` → `0001_01_01_000002_create_queue_tables.php`; inside, create `queue_jobs`, `queue_job_batches`, `queue_failed_jobs` (same columns as the original). In `config/queue.php` set `connections.database.table = 'queue_jobs'`, `batching.table = 'queue_job_batches'`, `failed.table = 'queue_failed_jobs'`.
2. **`2025_01_01_000000_add_role_to_users_table.php`**: `$table->string('role', 20)->default('sales')->index()->after('email');` (string + PHP enum, not DB enum, so adding roles never needs an ALTER on cPanel).
3. **Copy the 12 migrations** from `AIDocs/` into `database/migrations/` unchanged, **except** `2025_01_01_000008_create_jobs_table.php`, which becomes:
   ```php
   Schema::create('jobs', function (Blueprint $table) {
       $table->id();
       $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
       $table->string('job_type', 10)->default('box');          // box | manual (App\Enums\JobType)
       $table->string('title')->nullable();                      // اسم الشغلانة — used on the Odoo invoice line

       // What's being made (box jobs only — nullable for manual jobs)
       $table->string('box_type')->nullable();                   // medicine | candy | cosmetics | food | general
       $table->string('box_shape')->nullable();                  // reverse_tuck_end | straight_tuck_end | auto_lock_bottom | pillow_bag
       $table->decimal('length_cm', 6, 2)->nullable();
       $table->decimal('width_cm', 6, 2)->nullable();
       $table->decimal('depth_cm', 6, 2)->nullable();
       $table->unsignedInteger('quantity')->nullable();          // originally quoted quantity (manual jobs may omit)

       // Materials (box jobs; manual jobs use job_paper_items)
       $table->foreignId('paper_grammage_id')->nullable()->constrained('paper_grammages')->restrictOnDelete();
       $table->foreignId('paper_grammage_price_id')->nullable()->constrained('paper_grammage_prices')->nullOnDelete();
       $table->unsignedTinyInteger('print_colors')->default(0);
       $table->enum('lamination', ['none', 'matte', 'gloss'])->default('none');

       // Die
       $table->boolean('is_using_existing_die')->default(true);
       $table->foreignId('die_id')->nullable()->constrained('dies')->nullOnDelete();

       // Production prep captured at quote time (box jobs)
       $table->unsignedInteger('raw_sheets_needed')->nullable();
       $table->unsignedSmallInteger('ups_per_raw_sheet')->nullable();
       $table->boolean('interlocked')->default(false);

       // Pricing — margin is always typed manually (business rule)
       $table->decimal('base_cost_egp', 12, 2)->default(0);
       $table->decimal('margin_percent', 5, 2)->default(0);
       $table->decimal('final_price_egp', 12, 2)->default(0);
       $table->json('quote_snapshot')->nullable();               // full BoxQuote / ManualJobQuote payload at confirmation time

       // Filled once production is done (post-waste) — this feeds the Odoo invoice
       $table->unsignedInteger('produced_quantity')->nullable();

       $table->enum('status', ['draft','quoted','approved','in_production','completed','invoiced'])->default('draft');
       $table->timestamps();

       $table->index(['status', 'job_type']);
   });
   ```
4. **`2025_01_01_000013_create_job_paper_items_table.php`** (manual calculator paper line items):
   ```php
   $table->id();
   $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
   $table->string('label');                                       // "غلاف", "متن الكتيب"
   $table->foreignId('paper_grammage_id')->constrained('paper_grammages')->restrictOnDelete();
   $table->foreignId('paper_grammage_price_id')->nullable()->constrained('paper_grammage_prices')->nullOnDelete();
   $table->decimal('sheet_width_cm', 6, 2);
   $table->decimal('sheet_height_cm', 6, 2);
   $table->unsignedInteger('sheets_count');
   $table->decimal('weight_kg', 10, 3);                          // recomputed server-side
   $table->decimal('cost_egp', 12, 2);                           // recomputed server-side
   $table->unsignedTinyInteger('sort_order')->default(0);
   $table->timestamps();
   ```
5. **`2025_01_01_000014_create_settings_table.php`**: `string('key')->primary()`, `json('value')`, `string('group', 40)`, `string('label_ar')`, `text('note')->nullable()`, timestamps.

Order check: kit `0001_*` (users, cache, queue) → `2025_01_01_000000` (role) → `000001..000014`. `job_press_assignments` references `users` — fine.

### 1.2 Enums (`app/Enums/`, PHP 8.1 string-backed, each with `label(): string` in Arabic and `values(): array`)
| Enum | Values (must equal the TSX unions) |
|---|---|
| `UserRole` | sales, production, admin |
| `JobType` | box, manual |
| `JobStatus` | draft, quoted, approved, in_production, completed, invoiced |
| `BoxType` | medicine, candy, cosmetics, food, general |
| `BoxShape` | reverse_tuck_end, straight_tuck_end, auto_lock_bottom, pillow_bag |
| `Lamination` | none, matte, gloss |
| `ClosureType` | reverse_tuck, straight_tuck, auto_bottom, snap_lock |
| `CutFraction` | 1/1, 1/2, 1/4, 1/6, 1/8 |
| `DieCondition` | ready, needs_rubber, maintenance |
| `PaperCategory` | duplex_grey_back, duplex_white_back, bristol_white_back, kraft_liner, couche, triplex_board |
| `JobStageStatus` | pending, in_progress, done |
| `OdooSyncStatus` | pending, sent, success, failed |

### 1.3 Models (`app/Models/`)
| Model | Relationships / casts |
|---|---|
| `Customer` | hasMany Job; hasMany Lead (Phase 5); `credit_limit_egp` decimal:2 |
| `PaperSupplier` | hasMany PaperGrammagePrice |
| `PaperType` | hasMany PaperGrammage; `category` → PaperCategory |
| `PaperGrammage` | belongsTo PaperType; hasMany PaperGrammagePrice; `cheapestPrice()` = hasOne(PaperGrammagePrice)->ofMany('price_per_ton_egp','min') |
| `PaperGrammagePrice` | belongsTo PaperGrammage, PaperSupplier; `price_as_of` datetime; decimal:2 |
| `Die` | casts closure_type/cut_fraction/condition enums, decimals |
| `Press` | `supported_cut_fractions`, `supported_paper_categories` → array; `is_internal` bool |
| `Job` | belongsTo Customer, PaperGrammage, PaperGrammagePrice, Die; hasMany JobCostLine, JobPaperItem, JobStage (ordered by sort_order), JobPressAssignment, OdooInvoiceSync; hasOne `latestPressAssignment` (latestOfMany), hasOne `successfulOdooSync` (where status=success); casts job_type/status/box_type/box_shape/lamination enums, decimals, `quote_snapshot` array, `interlocked` bool; scopes `box()`, `manual()`, `withStatus(JobStatus)` |
| `JobCostLine`, `JobPaperItem` (belongsTo grammage + price), `JobStage` (status enum, datetimes), `JobPressAssignment` (belongsTo Press; `assignedBy` → User), `OdooInvoiceSync` (json casts, status enum), `Setting` (key PK, value array) | |
| `User` | `role` → UserRole; `isAdmin()/isSales()/isProduction()`; hasMany JobPressAssignment |

Support: `app/Support/Settings.php` with `get(string $key, mixed $default)` (cached) and `pricingConstants(): array`.
Auth: `app/Http/Middleware/EnsureRole.php` (alias `role` in `bootstrap/app.php`, usage `->middleware('role:admin')`). `app/Policies/JobPolicy.php`: sales → create/update draft & quoted, transition to quoted; production → stages, press assignment, complete; admin → everything.

### 1.4 Factories & seeders
Factories for every model (needed by tests); `JobFactory` states `box()`, `manual()`, `status(JobStatus $s)`.

Seeders mirror the TSX sample data so the pages work on first boot (values are PLACEHOLDERS — keep that word in `note`/comments):
- `UserSeeder`: admin@pantopack.local / sales@… / production@… with password `password`.
- `PaperSupplierSeeder`: the 3 suppliers used by `sp()` in `QuickBoxPricingCalculator.tsx` ~lines 181-187 (مصنع الأهرام للورق، شركة النصر للورق، مستورد المعادي).
- `PaperCatalogSeeder`: paper types + grammages + 3 prices each (0.98 / 1.00 / 1.04 × base) from `PAPER_TYPES_DATABASE` (~lines 194-236).
- `DieSeeder`: `FACTORY_DIES_DATABASE` (~lines 172-178).
- `PressSeeder`: `SAMPLE_PRESSES` in `PressRoutingSelector.tsx` (~lines 73-94).
- `CustomerSeeder`: صيدلية العزبي، حلواني الحاج.
- `SampleJobSeeder`: the two `SAMPLE_PAST_JOBS` (~lines 239-252) as `quoted` box jobs.
- `SettingsSeeder`: the pricing constants at ~lines 282-291 (`spoilage_rate 0.03`, `new_die_cost_egp 650`, `plate_cost_per_color_egp 150`, `press_run_rate_per_color_per_1000_sheets_egp 180`, `lamination_rate_per_sheet_egp {matte:0.35, gloss:0.30}`, `die_cut_rate_per_sheet_egp 0.15`, `glue_fold_rate_per_unit_egp 0.05`, `default_margin_percent 20`), group `pricing`.
`DatabaseSeeder` always runs users + settings; sample seeders only when `app()->environment(['local','testing'])`.

### 1.5 Catalogue CRUD (Inertia pages, minimal and consistent)
Routes (`routes/web.php`, inside `auth` middleware):
```
Route::resource('customers', …);
Route::resource('paper-suppliers', …);
Route::resource('paper-types', …);                       // edit page manages grammages + prices inline
Route::post('paper-types/{paperType}/grammages', …);      // add grammage
Route::delete('grammages/{grammage}', …);
Route::post('grammages/{grammage}/prices', …);            // add/update supplier price (upsert on grammage+supplier)
Route::delete('prices/{price}', …);
Route::resource('dies', …);
Route::resource('presses', …);
Route::patch('presses/{press}/backlog', …);               // { current_backlog_days } — used by PressRoutingSelector later
```
Controllers in `app/Http/Controllers/Catalog/`, FormRequests in `app/Http/Requests/Catalog/`, pages in `resources/js/pages/{customers,paper-suppliers,paper-types,dies,presses}/{index,create,edit}.tsx`. Build one small shared set in `resources/js/components/crud/` (page header, data table, form field wrapper, confirm-delete) on top of the kit's shadcn primitives. Arabic sidebar entries: العملاء، الموردين، أنواع الورق، الاسطمبات، المطابع، الشغلانات، الإعدادات.

Settings page (`/settings/pricing`, admin only): one form over the `pricing` group.

**CHECKPOINT 1:** `php artisan migrate:fresh --seed` succeeds on MariaDB; `php artisan test` green (kit auth tests + `tests/Feature/Catalog/CatalogCrudTest.php` covering create/update/delete of a paper type with grammage + price and press backlog patch); browser: login as admin, add a supplier price, edit a press backlog. Commit.

---

## Phase 2 — Costing pages & job creation

### 2.1 Move the components (logic intact)
- `AIDocs/QuickBoxPricingCalculator.tsx` → `resources/js/components/costing/QuickBoxPricingCalculator.tsx`
- `AIDocs/ManualJobCostingCalculator.tsx` → `resources/js/components/costing/ManualJobCostingCalculator.tsx`
- `AIDocs/PressRoutingSelector.tsx` → `resources/js/components/routing/PressRoutingSelector.tsx`

In each, delete the sample-data constants and the default-prop fallbacks so props become required:
- QuickBox: `FACTORY_DIES_DATABASE`, `sp()`, `PAPER_TYPES_DATABASE`, `SAMPLE_PAST_JOBS` (~172-252) and the defaults at ~597-599.
- Manual: `sp()`, `PAPER_TYPES_DATABASE` (~100-151) and the default at ~208.
- PressRouting: `SAMPLE_PRESSES` (~73-94) and the default at ~142.
Keep everything else (including the local "add paper type" panel in QuickBox and the `nextLocalId()` helpers).

**IDs:** the components use `string` ids. The server sends numeric ids **as strings** (`(string) $model->id`) in API Resources; validation uses `integer|exists:` rules (numeric strings pass). Do not change the component types.

Shared TS types: `resources/js/types/pantopack.ts` re-exports `DieCutTool`, `PaperType`, `PaperGrammageOption`, `PaperSupplierPrice`, `Press`, `SavedJobSpec`, `BoxQuote`, `ManualJobQuote`, plus `Customer`, `Job` shapes used by pages.

API Resources (`app/Http/Resources/`, call `JsonResource::withoutWrapping()` in `AppServiceProvider::boot`):
- `DieResource` → `DieCutTool` (id, code, name, lengthCm, widthCm, depthCm, closureType, rackLocation, upsOnCutSheet, cutFraction, condition)
- `PaperTypeResource` → `PaperType` with `standardSheetSize {widthCm,heightCm}` and nested `grammages[] { id, gsm, prices[] { id, supplierId, supplierName, pricePerTonEgp } }` (eager-load `grammages.prices.supplier`, order prices by price asc)
- `PressResource` → `Press` (id, name, isInternal, maxColors, supportedCutFractions, supportedPaperCategories (null → `[]`), currentBacklogDays, contactNote)
- `SavedJobSpecResource` → `SavedJobSpec` (label = `"{customer.name} — {BoxType label} {L}×{W}×{D}"`, paperTypeId via `paperGrammage.paper_type_id`, grammageId, supplierPriceId, selectedDieId…)
- `JobResource` for index/show pages.

### 2.2 Margin fix in `QuickBoxPricingCalculator.tsx` (minimal, flagged)
1. Delete `const PROFIT_MARGIN = 1.3;` (~line 292). Leave: `// BUSINESS RULE: margin is typed per job by staff — see marginPercent state. No fixed markup.`
2. Add state next to the others (~after line 625): `const [marginPercent, setMarginPercent] = useState<number>(defaultMarginPercent);` where `defaultMarginPercent` is a new optional prop (default `20`, fed from settings).
3. In the pricing `useMemo` (~line 865): replace `const sellingPrice = baseCost * PROFIT_MARGIN;` with
   ```ts
   const marginAmount = baseCost * (marginPercent / 100);
   const sellingPrice = baseCost + marginAmount;
   ```
   return `marginAmount` too, and add `marginPercent` to the memo dependency array (~line 896).
4. UI: add a "نسبة الربح %" number input (copy the markup pattern from `ManualJobCostingCalculator.tsx` ~lines 505-514) plus a "الربح (X%)" row in the التفاصيل column, above the unit-price rows (~before line 1511).
5. Do **not** add margin to the customer-facing `copyText` (~899-913) and do **not** store it in `SavedJobSpec` — repeating a job starts from the default margin.

### 2.3 Additive payload/prop extensions
- `BoxQuote` (~116-135) gains: `paperTypeId: string`, `grammageId: string`, `supplierPriceId: string | null`, `dieId: string | null`, `marginPercent: number`, `marginAmountEgp: number`, `costBreakdown: { paperCost: number; platesCost: number; pressRunCost: number; laminationCost: number; dieToolingCost: number; dieCuttingCost: number; gluingCost: number }`. Fill them in `handleConfirm` (~922-944) from state + `calc`. Existing fields unchanged.
- QuickBox new optional prop `pricingConstants?: Partial<PricingConstants>`: turn the constants at ~282-291 into a `DEFAULT_PRICING: PricingConstants` object and merge the prop over it inside the component. Geometry constants (`GLUE_FLAP_MM`, `BLEED_MM`, `GRIPPER_ALLOWANCE_MM`, `SIDE_TRIM_MM`, `EXACT_MATCH_THRESHOLD_CM`, `INTERLOCK_HEIGHT_SAVING_RATIO`) and `BOX_SHAPE_CALCULATORS` (~333-398) are **not** touched.
- `PressRoutingSelector` gains optional props `onBacklogChange?: (pressId: string, days: number) => void` (call it inside the existing `handleUpdateBacklog`) and `initialSelectedPressId?: string | null`.
- Local paper-manager panel in QuickBox stays local-only. `StoreBoxJobRequest` rejects ids that don't exist with the Arabic message "احفظ نوع الورق من صفحة أنواع الورق أولاً".

### 2.4 Pages & endpoints
Routes (auth):
```
GET  /jobs                       Jobs\JobController@index
GET  /jobs/create/box            Jobs\BoxJobController@create
POST /jobs/box                   Jobs\BoxJobController@store
GET  /jobs/create/manual         Jobs\ManualJobController@create
POST /jobs/manual                Jobs\ManualJobController@store
GET  /jobs/{job}                 Jobs\JobController@show
```
- `pages/jobs/create-box.tsx`: customer picker (shadcn combobox + "عميل جديد" dialog posting to `customers.store`), then `<QuickBoxPricingCalculator dies papers pastJobs pricingConstants defaultMarginPercent onConfirmOrder>`. `onConfirmOrder` → `router.post('/jobs/box', { customer_id, title?, ...quote })`. Props from controller: `dies` (all), `papers` (with grammages.prices.supplier), `pastJobs` (last 15 box jobs), `customers`, `pricingConstants`, `defaultMarginPercent`.
- `pages/jobs/create-manual.tsx`: customer picker + title input + `<ManualJobCostingCalculator papers onConfirmOrder>`; posts `ManualJobQuote` to `/jobs/manual`.
- `POST /jobs/box` → `app/Http/Requests/Jobs/StoreBoxJobRequest.php` → `app/Actions/Jobs/CreateBoxJob.php`: in a transaction create the job (`job_type=box`, dimensions, quantity, FK ids, print_colors, lamination, is_using_existing_die, base/margin/final, raw_sheets_needed, ups_per_raw_sheet, interlocked, `quote_snapshot` = whole payload) and one `job_cost_lines` row per non-zero `costBreakdown` entry with labels: ورق، زنكات، طباعة، سلوفان، اسطمبة جديدة، تكسير، لصق وتطبيق. If `pricePerTonEgp` differs from the DB price for `supplierPriceId`, log a warning (box math intentionally stays in the TSX). Redirect to `jobs.show`.
- `POST /jobs/manual` → `StoreManualJobRequest` → `CreateManualJob`: **recompute on the server** each paper item's weight and cost from DB grammage + price using the same formula as `computeLineItem` in `ManualJobCostingCalculator.tsx` (~168-180: `weightKg = widthCm*heightCm/10000 * gsm/1000 * sheetsCount`, `cost = weightKg/1000 * pricePerTon`), then totals, `base_cost = paper + manual cost lines`, `final = base * (1 + margin/100)`. Persist `job_paper_items`, `job_cost_lines`, `quote_snapshot`. Return 422 with Arabic messages if client totals differ by more than 1 EGP (guards against stale prices).
- `pages/jobs/index.tsx`: table with filters (status, type, customer); columns #، العميل، الشغلانة/العلبة، الكمية، السعر النهائي، الحالة (badge).
- `pages/jobs/show.tsx`: summary card, cost lines, paper items (manual), snapshot details; leave clearly marked slots for Phase 3 (lifecycle/stages/press) and Phase 4 (Odoo).

**CHECKPOINT 2:** `tests/Feature/Jobs/CreateBoxJobTest.php` (creates job + cost lines; rejects unknown die/paper ids; margin ≥ 0) and `CreateManualJobTest.php` (server totals match seeded prices; paper items persisted) pass; `npx tsc --noEmit` clean; browser: repeat a past job, change margin and see unit price change, create a manual job. Commit.

---

## Phase 3 — Lifecycle, stages, routing, completion

- `JobStatus::transitions(): array` → `draft→[quoted]`, `quoted→[approved]`, `approved→[in_production]`, `in_production→[completed]`, `completed→[invoiced]` (`invoiced` only reachable via Phase 4 success or manual fallback). Backward transitions: **ask the user** (plan default: none).
- `app/Services/Jobs/JobLifecycleService::transition(Job $job, JobStatus $to, User $actor, array $data = []): Job` — validates against the map (422 with Arabic message otherwise), runs in a transaction, dispatches `App\Events\JobStatusChanged($job, $from, $to, $actor)`. Side effects:
  - on `approved` → `JobStageTemplate::seedFor($job)` creates `job_stages` from `config/pantopack.php` `default_stages` keyed by `box_shape` and `manual`. **Placeholder list** (ask the user for the real one): طباعة، سلوفان، تكسير، لصق وتطبيق، مراجعة جودة، جاهز للتسليم.
  - on `completed` → requires `produced_quantity` (validated ≥ 1; default prefilled with `quantity`).
- Endpoints:
  ```
  PATCH /jobs/{job}/status                  { status }                         JobStatusController@update
  POST  /jobs/{job}/complete                { produced_quantity }              JobCompletionController@store
  PATCH /jobs/{job}/stages/{stage}          { status }                         JobStageController@update (sets started_at / completed_at)
  POST  /jobs/{job}/press-assignments       { press_id }                       JobPressAssignmentController@store
  ```
  Press assignment **appends** a row (`assigned_by_user_id = auth id`, `assigned_at = now()`) — re-assignments keep history. No auto-assign anywhere.
- `pages/jobs/show.tsx` additions: status stepper with the single allowed next-action button (role-gated via `JobPolicy`); stage checklist (pending → in_progress → done); `<PressRoutingSelector presses requirements onSelectPress onBacklogChange initialSelectedPressId>` visible from `approved` onward. `requirements` is built server-side in `JobResource`: `cutFraction = job.die?.cut_fraction ?? '1/2'`, `printColors`, `paperCategory = job.paperGrammage.paperType.category` (manual jobs: first paper item's category), `quantity`. `onBacklogChange` → debounced `PATCH /presses/{press}/backlog`.
- Completion form: "الكمية الفعلية بعد الهالك" input + button "تأكيد الكمية النهائية".

**CHECKPOINT 3:** `JobStatusTransitionTest` (illegal transition → 422; `approved` seeds stages; `completed` requires produced_quantity), `PressAssignmentTest` (records user; second assignment appends). Browser: walk a job draft → completed. Commit.

---

## Phase 4 — Odoo 18 invoicing (isolated; fake client until a sandbox exists)

- Migration `…_add_odoo_partner_id_to_customers_table.php`: `string('odoo_partner_id')->nullable()`.
- `config/odoo.php`: `url`, `db`, `username`, `api_key`, `timeout` (15), `fake` (bool), `tax_id`, `journal_id`, `auto_post` (bool), `auto_invoice_on_complete` (bool, default true). `.env`: `ODOO_URL, ODOO_DB, ODOO_USERNAME, ODOO_API_KEY, ODOO_FAKE=true, ODOO_TAX_ID, ODOO_JOURNAL_ID, ODOO_AUTO_POST=false`.
- `app/Services/Odoo/`:
  - `OdooClientInterface` — `authenticate(): int`, `executeKw(string $model, string $method, array $args, array $kwargs = []): mixed`.
  - `OdooClient` — JSON-RPC 2.0 to `{url}/jsonrpc`; `service: common, method: authenticate` → uid; `service: object, method: execute_kw`. Uses Laravel `Http` with timeout. Maps connection errors / 5xx / timeouts → `OdooTransientException`; Odoo `error` payloads → `OdooRejectedException` (message from `data.message`).
  - `FakeOdooClient` — bound when `config('odoo.fake')`; returns deterministic ids (`partner 1001`, `invoice 5001`, name `INV/FAKE/0001`); can be told to throw for tests.
  - `OdooInvoicePayloadBuilder::build(Job $job): array` — ensures partner (`res.partner` `search_read` by name → `create` if missing → store `odoo_partner_id`), then `account.move` create payload: `move_type: 'out_invoice'`, `partner_id`, `invoice_date` (today), `ref: "PP-{job id}"`, `journal_id` (if set), `invoice_line_ids: [[0,0,{ name: job.title ?? "{BoxType label} {L}×{W}×{D}", quantity: produced_quantity, price_unit: round(final_price_egp / quantity, 4), tax_ids: [[6,0,[tax_id]]] }]]`. Manual jobs without `quantity`: single line qty 1 at `final_price_egp` (**confirm with user**).
  - `OdooInvoiceService::invoice(Job $job): OdooInvoiceSync` — creates the sync row `pending` → stores request payload, `sent` → calls create (+ `action_post` if `auto_post`) → `read` back `name` → `success` (`odoo_invoice_id`, response payload, `synced_at`) and transitions the job to `invoiced` through `JobLifecycleService`; on exception → `failed` with `error_message`, rethrow so the queue can decide.
- `app/Jobs/SyncOdooInvoice.php`: `ShouldQueue`, `ShouldBeUnique` (by job id), `$tries = 5`, `backoff(): [60, 300, 900, 3600]`; guard: skip if a `success` sync already exists; retry only on `OdooTransientException` (call `$this->fail($e)` on `OdooRejectedException`).
- `app/Listeners/DispatchOdooInvoice.php` on `JobStatusChanged` where `$to === completed` and `config('odoo.auto_invoice_on_complete')` → `SyncOdooInvoice::dispatch($job)`. Completion itself never waits on Odoo.
- Switch `.env` `QUEUE_CONNECTION=database`; local dev runs `php artisan queue:listen` (or `queue:work`).
- UI on `pages/jobs/show.tsx`: sync history table (الحالة، الوقت، الخطأ), button "إعادة المحاولة" → `POST /jobs/{job}/odoo-syncs` (re-dispatch), and manual fallback form "سجّل رقم فاتورة أودو يدوياً" → `POST /jobs/{job}/odoo-syncs/manual` `{ odoo_invoice_id }` creating a `success` row (flag `is_manual` in `response_payload`) and transitioning to `invoiced`.

**CHECKPOINT 4:** `tests/Feature/Odoo/OdooSyncTest.php` with `FakeOdooClient`: success → job `invoiced`; transient failure → released with backoff; rejected → `failed`, no retry; manual fallback works; `Queue::fake()` asserts completion dispatches exactly once. Commit. **Real sandbox test happens only after the user provides Odoo credentials** — run one `php artisan tinker` call to `OdooInvoiceService` with `ODOO_FAKE=false` before enabling `auto_invoice_on_complete` in production.

---

## Phase 5 — CRM leads (brief)

- Migration `create_leads_table`: `customer_id` nullable FK, `contact_name`, `company_name` nullable, `phone`, `source` nullable, `status` string (new, contacted, quoted, won, lost → `App\Enums\LeadStatus`), `expected_quantity` nullable, `notes` text nullable, `owner_user_id` nullable FK users, `converted_job_id` nullable FK jobs, timestamps.
- `LeadController` resource (index/create/edit) + `POST /leads/{lead}/convert` (creates or links the customer, redirects to `/jobs/create/box?customer={id}&lead={id}`); job creation with `lead` sets lead `quoted` + `converted_job_id`; `approved` transition sets `won`.
- Pages `resources/js/pages/leads/{index,create,edit}.tsx`; sidebar entry "الفرص".

**CHECKPOINT 5:** `LeadConversionTest`; browser: create a lead → convert → job → approve → lead shows won. Commit.

---

## 6. Final layout
```
app/Enums/*                      app/Models/*                       app/Support/Settings.php
app/Actions/Jobs/{CreateBoxJob,CreateManualJob}.php
app/Services/Jobs/{JobLifecycleService,JobStageTemplate}.php
app/Services/Odoo/{OdooClientInterface,OdooClient,FakeOdooClient,OdooInvoicePayloadBuilder,OdooInvoiceService}.php
app/Exceptions/Odoo/{OdooTransientException,OdooRejectedException}.php
app/Jobs/SyncOdooInvoice.php     app/Events/JobStatusChanged.php    app/Listeners/DispatchOdooInvoice.php
app/Http/Controllers/Catalog/*   app/Http/Controllers/Jobs/*        app/Http/Controllers/{LeadController,SettingsController}.php
app/Http/Requests/{Catalog,Jobs,Odoo}/*                             app/Http/Resources/*
app/Http/Middleware/EnsureRole.php                                  app/Policies/JobPolicy.php
config/{pantopack,odoo}.php
database/migrations/ (kit 0001_* with queue rename, 2025_01_01_000000 role, 000001..000014, Phase 4/5 additions)
database/seeders/*               database/factories/*
resources/js/components/costing/{QuickBoxPricingCalculator,ManualJobCostingCalculator}.tsx
resources/js/components/routing/PressRoutingSelector.tsx
resources/js/components/crud/*   resources/js/components/jobs/{customer-picker,status-stepper,stage-list,odoo-sync-panel}.tsx
resources/js/pages/{customers,paper-suppliers,paper-types,dies,presses,leads}/{index,create,edit}.tsx
resources/js/pages/jobs/{index,create-box,create-manual,show}.tsx   resources/js/pages/settings/pricing.tsx
resources/js/types/pantopack.ts
tests/Feature/{Catalog,Jobs,Odoo,Leads}/*
```
Tests run on SQLite in-memory (the kit's `phpunit.xml` default; migrations are driver-neutral). Optionally add a `print_test` MariaDB database to exercise native ENUMs.

---

## 7. cPanel deployment notes (later — not part of the build phases)
- Build assets locally (`npm run build`) and commit `public/build` (cPanel usually has no Node), or build in CI.
- Cron entries: `* * * * * cd ~/app && php artisan schedule:run >> /dev/null 2>&1` and `* * * * * cd ~/app && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1` (Odoo retries run through this).
- Document root → `public/` (or `public_html` → symlink / `.htaccess` rewrite). `.env` on the server: `APP_ENV=production`, `APP_DEBUG=false`, `ODOO_FAKE=false`.
- After each deploy: `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`.
- cPanel PHP ≥ 8.3 with `intl`, `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `bcmath` enabled (MultiPHP INI Editor). MariaDB ≥ 10.6.

---

## 8. Open questions for the user (none block Phases 0–2)
1. Default `job_stages` list per box type and for manual jobs (Phase 3).
2. Backward transitions (e.g. quoted → draft to re-price)? Must a press be assigned before `in_production`? (Default: no backward moves; warn only.)
3. Odoo sandbox: URL, database, login, API key; VAT rate/tax id (14%?); journal; auto-post or leave draft; invoicing rule for manual jobs without a quantity.
4. Real pricing constants and real supplier prices (placeholders are seeded and editable in the UI).
5. Who may edit paper prices — admin only, or sales too? (Default: admin + sales.)
6. cPanel: PHP version, SSH availability, MariaDB version (needed only at deploy time).
