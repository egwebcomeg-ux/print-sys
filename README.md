# Pantopack — نظام التسعير والإنتاج

Laravel 13 + Inertia 3 + React 19 (TypeScript, Tailwind 4), MariaDB. Job costing, CRM and production tracking for Pantopack. Odoo 18 is used only to create the final customer invoice.

Background and business rules: [AIDocs/Claude-Code-Prompt.md](AIDocs/Claude-Code-Prompt.md) · Build plan: [AIDocs/BUILD-PLAN.md](AIDocs/BUILD-PLAN.md)

## Run locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # then set DB_* (MariaDB)
php artisan migrate --seed                          # sample catalogue in local only
composer run dev                                    # serve + queue:listen + vite
```

App: http://127.0.0.1:8000 — seeded logins (password `password`, change them on any shared server):

| Role | Email | Can |
|---|---|---|
| مدير | admin@pantopack.local | everything |
| مبيعات | sales@pantopack.local | customers, leads, pricing/quotes, paper prices, invoicing |
| إنتاج | production@pantopack.local | stages, press routing, backlog, completion |

Public registration is off; admins add staff from **المستخدمين**.

## What's in it

- **Catalogue:** paper types → grammages → several supplier prices each (cheapest = suggestion only), suppliers, dies, presses (inline backlog), pricing constants (**ثوابت التسعير**).
- **Quoting:** `QuickBoxPricingCalculator` (boxes) and `ManualJobCostingCalculator` (paper jobs) on server data. Margin is always typed per job. Manual jobs are re-priced on the server.
- **Lifecycle:** draft → quoted → approved → in_production → completed → invoiced. Approval seeds production stages. Presses are always picked by hand (`PressRoutingSelector`). Completion records the produced (post-waste) quantity.
- **Odoo:** one queued invoice per completed job (`SyncOdooInvoice`) billed on `produced_quantity`. Every attempt is logged in `odoo_invoice_syncs`, with retry/backoff and a manual fallback. It is idempotent (it reuses an Odoo invoice with ref `PP-{id}`). `ODOO_FAKE=true` until a sandbox exists.
- **CRM:** customers and leads (الفرص). A lead converts into a job and becomes won when the job is approved.

## Checks

```bash
php artisan test          # feature tests (SQLite in memory)
npx tsc --noEmit          # types
npx vp lint && npx vp fmt --check
vendor/bin/pint --test
```

## Still placeholders (need real data)

- Paper prices, dies, presses and pricing constants are sample values. Replace them from the catalogue pages.
- Default production stages per box type: `config/pantopack.php`.
- Dieline formulas and the ~15% interlock saving in `QuickBoxPricingCalculator.tsx` (don't change them without reference dielines).
- Invoicing lump-sum manual jobs (no piece count) as one line: `OdooInvoiceService::payload()`, still to be confirmed.

## Deploying to cPanel

- Build assets locally (`npm run build`) and upload `public/build`. cPanel usually has no Node.
- Cron (every minute): `php artisan schedule:run` and `php artisan queue:work --stop-when-empty --max-time=50`. Odoo retries run through the queue.
- Point the document root at `public/`. Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=database`, real `ODOO_*`, `ODOO_FAKE=false`.
- After each deploy: `composer install --no-dev -o`, `php artisan migrate --force`, `php artisan optimize`.
- PHP ≥ 8.3 with `intl`, `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `bcmath`; MariaDB ≥ 10.6.
