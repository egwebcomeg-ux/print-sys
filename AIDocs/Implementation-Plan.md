# Pantopack Job Costing, CRM & Production Platform — Implementation Plan

## 1. Architecture Decision

The platform is a **custom Laravel application**, not an Odoo configuration project. It owns:

- CRM & lead capture
- Job costing / quoting (with manual, case-by-case profit margin entry)
- Production tracking (dies, paper, imposition, spoilage)
- Press & subcontractor routing (decision support, not automated assignment)
- Project/stage tracking per job
- Supplier price comparison

**Odoo's only role**: one API call per job, at the very end, to generate the compliant customer invoice using the actual post-waste quantity. Odoo's CRM, Sales, Manufacturing, Project, Inventory, Purchase, and full Accounting apps are **not used**.

Recommended stack: Laravel + Inertia.js + React, since the pricing tools were already built as React/TypeScript components (`QuickBoxPricingCalculator.tsx`, `ManualJobCostingCalculator.tsx`, `PressRoutingSelector.tsx`) and Inertia lets them be reused directly as page components with minimal rework, rather than rewritten in Blade.

## 2. What already exists

| Asset | Status | Notes |
| --- | --- | --- |
| `QuickBoxPricingCalculator.tsx` | Built (frontend only) | Die matching, multi-supplier paper pricing, box-shape presets, 2D dieline preview, imposition/interlocking preview, repeat-job shortcut |
| `ManualJobCostingCalculator.tsx` | Built (frontend only) | Manual paper line items + free-form cost lines + manual margin, for non-box paper jobs |
| `PressRoutingSelector.tsx` | Built (frontend only) | Filters presses by capability, sorts by current backlog, manual selection only |
| Database schema (12 migrations) | Built (schema only) | `customers`, `paper_suppliers`, `paper_types`, `paper_grammages`, `paper_grammage_prices`, `dies`, `presses`, `jobs`, `job_cost_lines`, `job_press_assignments`, `job_stages`, `odoo_invoice_syncs` |

None of these are wired to a real Laravel app yet — no models, no controllers, no auth, no API, no database actually running.

## 3. What's still placeholder and needs real data before go-live

- **Box dieline formulas** (reverse tuck, straight tuck, auto-lock bottom, pillow bag) are schematic approximations pending real reference dielines per box type.
- **Interlocked-imposition paper savings** (~15%) is an estimate pending real flap/notch geometry.
- **Paper supplier prices** are placeholder numbers — must be replaced with real, current vendor quotes.
- **Press capability/capacity data** (`presses` table) needs real entries for Pantopack's actual internal lines and subcontractors, and `current_backlog_days` needs a real (even if manual) update process.
- **Pricing constants** (plate cost per color, press-run rate, lamination rate, die-cut rate, gluing rate, spoilage %) are all placeholders flagged in code comments.

## 4. Phased plan

### Phase 1 — Backend foundation (Laravel core)
- Initialize the Laravel project, auth (staff logins, roles: sales, production, admin)
- Run the 12 migrations; add a `users` table if not already present (Laravel ships one by default)
- Build Eloquent models + relationships for every table
- Build basic CRUD for: customers, paper suppliers, paper types/grammages/prices, dies, presses
- Seed real data: actual die inventory, actual paper catalogue with real supplier prices, actual internal/subcontractor press list

### Phase 2 — Job costing & quoting
- Wire `QuickBoxPricingCalculator` and `ManualJobCostingCalculator` into Inertia pages, backed by real API data (dies, paper catalogue, past jobs) instead of the sample data currently hardcoded in the components
- Build the `jobs` create/edit flow: customer selection, box or manual line-item costing, manual margin entry, quote output (PDF or shareable link)
- Implement "repeat last job" against real job history instead of the sample `pastJobs` data

### Phase 3 — Production, routing & project tracking
- Wire `PressRoutingSelector` into the job approval flow, backed by the real `presses` table
- Implement `job_stages`: a default stage template per box shape/type, seeded when a job moves to `approved`, with a UI for staff to advance stages
- Implement `job_press_assignments` recording (who picked which press, when)
- Implement `produced_quantity` capture at job completion (the actual, post-waste quantity)

### Phase 4 — Odoo invoicing integration
- Build the single Odoo API integration: on `job.status = completed`, construct the invoice payload (customer, line items, tax, `produced_quantity`) and call Odoo's Invoicing API
- Log every attempt in `odoo_invoice_syncs` (request/response payloads, status)
- Build retry logic for transient failures, and a manual fallback path: if the API call fails, staff can create the invoice by hand in Odoo and record the resulting `odoo_invoice_id` manually
- Test this integration in isolation first, against a real Odoo sandbox, before wiring it into the job lifecycle

### Phase 5 — Testing, training, go-live
- End-to-end test: lead → quote (manual margin) → approval → production stages → press routing → produced quantity → Odoo invoice
- User acceptance testing per department (sales, production, follow-up staff)
- Staff training
- Parallel run if needed, then cutover

### Phase 6 — Post go-live support
- Bug fixes, business-rule tuning (margin habits, routing criteria)
- Adoption monitoring

## 5. Key risks (carried over from the roadmap)

| Risk | Mitigation |
| --- | --- |
| Custom-built system takes longer than configuring an existing Odoo app | Keep the Phase 1–3 scope tight; treat everything in the "Phase 2 feature ideas" list as strictly post-launch |
| Every business rule lives in code | Keep margin tiers, routing criteria, and pricing constants as editable data/config, not hardcoded values — the paper supplier-price manager pattern already used in the calculators is the model to follow elsewhere |
| Odoo integration is a single point of failure for invoicing | Retry logic + manual fallback (see Phase 4) |
| Dieline/imposition formulas are still approximations | Do not go live on real customer quotes until at least the highest-volume box shapes are validated against real reference dielines |
| Press capacity data (`current_backlog_days`) goes stale without a real update habit | Start manual, but flag this as a strong candidate for a later integration with real production scheduling data |

## 6. Next actions

1. Decide who builds the Laravel app (in-house, freelancer, or agency) and confirm availability
2. Set up the Laravel project and get the 12 migrations running against a real database
3. Start compiling real die inventory, real paper/supplier prices, and a real press/subcontractor list
4. Send the reference dielines for the highest-volume box types (starting with the medicine box) so the shape formulas can be finalized
5. Get access to an Odoo sandbox to test the invoicing API call in isolation, early — don't leave this to the end
