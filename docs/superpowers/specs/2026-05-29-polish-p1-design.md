# Design — Polish P1 (PRD §4 ⚠️ items, single front)

- **Date:** 2026-05-29
- **Status:** Approved (design); pending implementation plan
- **Author:** Anderson de Oliveira Venturini
- **Source backlog:** [`docs/NEXT_STEPS.md`](../../NEXT_STEPS.md) Track 5 · PRD `⚠️` acceptance criteria on F08/F09/F10/F11/F12/F13

## 1. Context & scope

NEXT_STEPS Track 5 ("P1 polish") lists ~10 small items that close the `⚠️` acceptance
criteria left open across the shipped MVP. They share no infrastructure and are all
idiomatic Filament v5 work, so they ship as **one front, one spec, one plan**.

**In scope (8 items):** 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9.

**Excluded:**
- **5.1 (F10 manual-override Toggle)** — **already shipped.** `has_manual_fee_override` is
  a `Toggle` in `CustomerForm` (`Section "Entrega e taxas"`). Verified 2026-05-29; this
  spec only adds a Pest assertion confirming the toggle persists and is honored by recompute.
- **5.10 (F16 Google import preview)** — **deferred** until the F16 OAuth grant is live
  (Phase 1 / M6). Cannot be verified end-to-end without a real People API connection.

**Non-goals:** F18 WhatsApp, hardening (Track 6), post-MVP (Track 7). No new feature scope
beyond closing the listed `⚠️` criteria.

## 2. Dependencies & decisions

| Decision | Choice | Rationale |
|---|---|---|
| XLSX/CSV export library | **None added** — Filament v5 `ExportAction` uses `openspout/openspout`, already in `composer.lock` | The F14 deviation dropped `maatwebsite/excel`; openspout is the lighter writer Filament bundles |
| Dashboard PDF library | **Add `barryvdh/laravel-dompdf` (require-dev not applicable — it is a runtime route, so `require`)** | DomPDF is pure PHP — no binary wheels, safe on the Oracle ARM64 host (the F14 concern does not apply) |
| Dashboard filter propagation | Filament native `HasFiltersForm` (page) + `InteractsWithPageFilters` (widgets) | Idiomatic v5; one filter source feeds 5.5 + 5.6 + 5.7 |
| 5.9 fee-override audit | Extend the existing `Sale::updating` → `audit_logs` pattern | Mirrors `out_of_area_override` / `unit_price` already gated + audited |

> **Dependency approval:** `barryvdh/laravel-dompdf` is the only new package and was approved
> by the owner on 2026-05-29 (for item 5.7).

## 3. Per-item design

### 5.2 — Customer Infolist (F10)
- **Artisan:** `make:filament-page ViewCustomer` (the resource currently has only
  Create/Edit/List) registered on `CustomerResource::getPages()`.
- **Infolist** (`CustomerInfolist::configure`): name (Display font), `distance_km`,
  `delivery_fee`, `building_fee`, **fee source** (`IconEntry`/`TextEntry` derived from
  `has_manual_fee_override` → "Manual" vs "Automática"), `fees_calculated_at`.
- Read-only; manager + attendant may view (same gate as the resource).
- **Test:** `livewireTest(ViewCustomer)` asserts the computed source label and that fees render.

### 5.3 — DeliveryBoard filter chips (F11)
- Add explicit filters to `DeliveryBoard`: **district** (`SelectFilter` populated distinct
  from `customer_addresses.district`) and **status** (`SelectFilter` over the `deliveries.status`
  enum). The automatic deliverer scope (own + unassigned) is unchanged and stacks with these.
- **Test:** board filtered by district shows only matching deliveries; by status idem.

### 5.4 — CSV/XLSX export on Water/General sales (F12)
- **Artisan:** `make:filament-exporter SaleExporter` defining the exported columns
  (date, id, customer, type, payment, total, status, contains_water).
- Add `ExportAction::make()->exporter(SaleExporter::class)` to the `headerActions` (or
  table header) of `WaterSales` and `GeneralSales`. The scoped query (`contains_water`)
  carries into the export.
- **Test:** the export action exists and uses `SaleExporter`; a queued-export assertion via
  Filament's export testing helper.

### 5.5 — Dashboard date-range filter (F13)
- **Artisan:** `make:filament-page Dashboard` extending `Filament\Pages\Dashboard`, using
  `HasFiltersForm` with a `startDate` / `endDate` range (default = current month). Register it
  in `AdminPanelProvider::pages()` **replacing** the default `Dashboard::class`.
- Existing widgets (`SalesKpis`, `PaymentMethodBreakdown`, `WaterVsRestChart`,
  `TopProductsTable`) gain `InteractsWithPageFilters` and read `$this->pageFilters['startDate'|'endDate']`,
  falling back to the current month when unset (preserves today's behavior).
- **Test:** setting the filter narrows a KPI value to the chosen range.

### 5.6 — Top customers + Monthly evolution (F13)
- **Artisan:** `make:filament-widget TopCustomersTable` (table) and
  `make:filament-widget MonthlyEvolutionChart` (line chart). Both manager-only
  (`canView` → `isManager()`), both read the page filter (5.5).
- `MonthlyEvolutionChart` plots revenue per month over the selected range; line color from
  `config('brand.chart_colors.*')`.
- Register both in `AdminPanelProvider::widgets()`.
- **Test:** widgets render for a manager, are hidden for an attendant, and respect the range.

### 5.7 — Dashboard PDF export (F13)
- `composer require barryvdh/laravel-dompdf`.
- Route `GET /admin/dashboard/report.pdf` (manager-gated middleware) → controller renders a
  Blade view summarizing the same KPIs for the active date range (passed as query params).
- Blade is **brand-compliant**: colors via `config('brand.*')` / CSS variables, no inline hex
  (keeps `DesignSystemTest` green). A "Exportar PDF" header action on the Dashboard page links
  to the route with the current filter range.
- **Test:** the route returns 200 + `application/pdf` for a manager, 403 for an attendant.

### 5.8 — SalesRelationManager filters + period total (F08)
- Add to `SalesRelationManager`: a **period** filter (`Filter` with `startDate`/`endDate`
  form fields → `whereBetween('created_at', …)`) and a **product** filter
  (`SelectFilter` on `variant_id` resolved via `whereHas('items', …)`).
- Add a **"Total no período"** summary using `Tables\Columns\Summarizers\Sum` on the `total`
  column (reflects the active filters).
- **Test:** filtering by period + variant narrows the rows and the summary total.

### 5.9 — Manager-gated fee override + audit (F09)
- In `SaleForm`, make `delivery_fee` / `building_fee` editable **only** when
  `Auth::user()->isManager()` (mirror the existing `unit_price` gate); disabled otherwise.
- Extend the `Sale::updating` hook (already auditing `out_of_area_override`) to write an
  `audit_logs` row whenever `delivery_fee` or `building_fee` changes
  (`model_type`, `model_id`, `field`, `before`, `after`, `user_id`, `reason`, `created_at`).
- **Test:** an attendant cannot edit the fields; a manager edit produces matching `audit_logs` rows.

## 4. Testing strategy

- Each item lands with Pest coverage in `tests/Feature/Filament/*` (page/widget/relation-manager
  interactions via `livewireTest`) or `tests/Feature/Models/SaleTest.php` (5.9 audit).
- `tests/Feature/DesignSystemTest.php` (brand gate) must stay green — the PDF Blade and the new
  chart widget are the only new UI surfaces; both consume tokens / `config('brand.*')`.
- Run the full suite (`fa-test:php84 vendor/bin/pest --parallel`) green before each commit.
- Artisan-first for every new artifact (`make:filament-page`, `make:filament-widget`,
  `make:filament-exporter`).

## 5. Out of scope / follow-ups

- 5.10 Google import preview — revisit with the F16 OAuth grant (M6).
- Hardening (Track 6) and F18 (Track 4) remain separate fronts with their own spec → plan cycles.
