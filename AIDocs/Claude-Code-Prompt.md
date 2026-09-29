# Claude Code Prompt — Pantopack Laravel Platform

Copy everything below into Claude Code (in the terminal, VS Code, JetBrains, or the desktop app) inside a fresh or existing Laravel project directory to start the build.

---

## Prompt

I'm building a custom job-costing, CRM, and production-tracking platform in Laravel for Pantopack, a printing and packaging factory. Read this whole brief before writing any code, and ask me clarifying questions if anything below is ambiguous — don't guess on business logic.

### Architecture

This is a **Laravel-first** system. It owns CRM, quoting, production tracking, project/stage tracking, supplier price comparison, and press/subcontractor routing. **Odoo is used for exactly one thing**: generating the final customer invoice via API, once a job's actual (post-waste) quantity is confirmed. Odoo's CRM, Sales, Manufacturing, Project, Inventory, Purchase, and full Accounting apps are not used and should not be integrated.

Use **Laravel + Inertia.js + React** (not Blade) — I already have working React/TypeScript components for the core UI that should be adapted into Inertia pages rather than rewritten from scratch.

### Assets I already have (attached / in this repo)

1. **Database migrations** (12 files, already written, not yet run): `create_customers_table`, `create_paper_suppliers_table`, `create_paper_types_table`, `create_paper_grammages_table`, `create_paper_grammage_prices_table`, `create_dies_table`, `create_presses_table`, `create_jobs_table`, `create_job_cost_lines_table`, `create_job_press_assignments_table`, `create_job_stages_table`, `create_odoo_invoice_syncs_table`.
2. **React components** (currently self-contained with hardcoded sample data — need to be wired to real Eloquent-backed API data):
   - `QuickBoxPricingCalculator.tsx` — box/carton pricing: die matching, multi-supplier paper price comparison, box-shape presets (medicine/candy/cosmetics/food/general), 2D dieline preview (SVG), sheet-imposition preview with an optional interlocked-nesting estimate, a "repeat last job" shortcut, and an `onConfirmOrder` callback carrying the full computed quote.
   - `ManualJobCostingCalculator.tsx` — for non-box paper jobs where sheet size/count are already known: manual paper line items (reusing the same multi-supplier pricing model) plus free-form cost lines and a manual margin field.
   - `PressRoutingSelector.tsx` — takes a job's requirements (cut fraction, colors, paper category, quantity) and filters/sorts presses by capability and current backlog; selection is always a manual click, never automatic.

### Business rules — do not change these without asking me first

- **Profit margin is always entered manually, case by case.** There is no fixed markup tier by quantity or customer type. Every place margin appears in the UI must be an editable field, not a computed default beyond "cheapest supplier" style conveniences.
- **Press/subcontractor assignment is decision support, not automation.** `PressRoutingSelector` filters and sorts; a human always clicks to assign. Do not build logic that auto-assigns a press.
- **Paper pricing is multi-supplier.** Every grammage can have several supplier prices; the cheapest is the default suggestion, but staff can pick any listed supplier. Don't collapse this back to a single price per grammage.
- **Odoo is called exactly once per job, at the end**, using `produced_quantity` (the actual, post-waste amount), not the originally quoted `quantity`. Log every attempt in `odoo_invoice_syncs` with request/response payloads. Build a retry path for transient failures and a manual fallback (staff can record a manually-created Odoo invoice ID if the API call fails).
- **Dieline and imposition formulas are placeholders.** They live in one function per box shape in `QuickBoxPricingCalculator.tsx` (`BOX_SHAPE_CALCULATORS`). Don't "correct" them without real reference dielines from me — ask instead.

### What I need you to do, in order

1. **Scaffold the Laravel + Inertia + React project** if not already set up (Laravel 11+, Inertia, React, TypeScript, Tailwind — match the existing component styling: dark theme, RTL, `bg-slate-950`/`border-slate-800` palette).
2. **Run the 12 migrations** against a local database (ask me for DB credentials/config if needed, or set up SQLite for local dev) and confirm they apply cleanly. Add a `users` table/migration if one isn't already present, with a simple `role` column (`sales`, `production`, `admin`).
3. **Build Eloquent models** for every table, with proper relationships (e.g. `PaperGrammage belongsTo PaperType`, `PaperGrammage hasMany PaperGrammagePrice`, `PaperGrammagePrice belongsTo PaperSupplier`, `Job belongsTo Customer`, `Job belongsTo PaperGrammage`, `Job belongsTo Die` (nullable), `Job hasMany JobCostLine`, `Job hasMany JobStage`, `Job hasMany JobPressAssignment`, `Job hasOne OdooInvoiceSync` or `hasMany` if retries are tracked as separate rows).
4. **Build API endpoints/controllers** to back the three React components with real data instead of their hardcoded sample arrays: dies list, paper catalogue (types → grammages → supplier prices), presses list, and a job history endpoint for the "repeat last job" feature.
5. **Convert the three components into Inertia page components**, keeping their existing UI/logic intact, replacing only the data source (props from the server instead of hardcoded `_DATABASE` constants) and wiring `onConfirmOrder` / `onSelectPress` to real endpoints that create/update `jobs`, `job_cost_lines`, and `job_press_assignments` rows.
6. **Build the job lifecycle**: draft → quoted → approved → in_production → completed → invoiced, matching the `status` enum in the `jobs` migration. Seed a default `job_stages` set when a job moves to `approved` (ask me what the default stage list should be per box type if I haven't told you yet).
7. **Build the Odoo invoicing integration** as an isolated service class first (test it against a sandbox before wiring it into the job-completion flow). Do not let a failed Odoo call block a job from being marked `completed` — invoicing is a separate step that can retry.
8. **Basic CRM screens**: customer list/create/edit, and a simple lead/opportunity list feeding into job creation.

### What NOT to build yet

Everything in this list is a deliberately deferred "Phase 2" idea — don't build any of it unless I explicitly ask:
proof-approval workflow, client brand-asset library, die lifespan/wear tracking, paper-waste resale tracking, machine scheduling calendar, preventive maintenance reminders, separate design tickets, design-file version archive, price-change alerts on open quotes, retainer/subscription customers, supplier performance ratings, seasonal customer reminders, audit trail, customer self-service portal, WhatsApp notifications, customer credit limits, sales commission tracking, QR/barcode scanning on job tickets.

### Working style

- Work incrementally: get migrations running and models in place before touching the frontend integration.
- Ask before making a judgment call on anything under "Business rules" above.
- Flag any placeholder pricing constants or formulas you touch, the same way the existing component comments do, so they're easy to find and replace with real numbers later.
