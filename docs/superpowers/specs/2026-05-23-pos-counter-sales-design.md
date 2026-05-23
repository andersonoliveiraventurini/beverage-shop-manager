# Design — Point-of-Sale (PDV) counter screen

- **Date:** 2026-05-23
- **Status:** Approved (design); pending implementation plan
- **Branch:** `feat/pos-counter-sales`
- **Author:** Anderson de Oliveira Venturini

## 1. Context & problem

The current `/admin/sales/create` screen (`SaleForm`) builds a sale with a Filament
`Repeater`. To add each product the attendant must: click "Adicionar item" → open a
`Select` → search across ~200 variants → pick → type quantity, repeated per item. There is
no live total (subtotal/total are only computed on save via `Sale::saving` +
`recalculate()`), no category browsing, no quantity steppers, and the returnable-water
modality fields make every row tall. For a high-frequency counter tool this is slow and
rigid ("engessado").

## 2. Goals

- Add products in **one click** from a searchable, category-tabbed catalog grid.
- Show a **live cart and running total** while the sale is being built.
- Keep the water-gallon domain rules (modality + shell-validity ledger) intact and fast.
- Reuse all existing persistence/domain logic (stock settlement, shell ledger, total
  calculation) through the existing `Sale` / `SaleItem` models.

## 3. Non-goals

- Replacing `/admin/sales/create`. The existing resource form stays for editing/correcting
  sales and for audit-heavy flows.
- Barcode-scanner hardware integration (search box is keyboard/scanner friendly, but no
  device drivers).
- The public homepage (`/`) — tracked separately, parked.
- Mobile/tablet-first layout. Primary target is **desktop/notebook** (responsive degradation
  is acceptable but not optimized this round).

## 4. Decisions (from brainstorming)

| Decision | Choice |
|---|---|
| Form factor | Dedicated custom Filament page at `/admin/pos` |
| Primary device | Desktop/notebook — two-column layout, keyboard shortcuts |
| Default water modality on 1-click add | **Troca** (exchange: customer hands empty shell, takes full) |
| Persistence | Create normal `Sale` + `SaleItem`; rely on model hooks for stock/ledger/total |
| Existing create form | Kept as-is |

## 5. UX & layout

Two-column layout inside the Filament page chrome:

```
┌────────────────────────────────────────────────────────────────────────┐
│ FA · Ponto de Venda      [ / search product, brand or SKU… ]   Balcão ▾  │
├──────────────────────────────────────────────┬───────────────────────────┤
│ [Água][Refri][Cerveja][Doces][Limpeza][…]     │  CART                  🗑 │
│ ┌────────┐┌────────┐┌────────┐┌────────┐       │ ┌───────────────────────┐ │
│ │ Acqua  ││ Acqua  ││ Coca   ││ Coca   │       │ │ 2× Acqua 20L   R$30,00│ │
│ │ 20L    ││ 10L    ││ 2L     ││ Lata   │       │ │  [−] 2 [+]   Troca ▾ ✕│ │
│ │ R$15,00││ R$10,00││ R$13,00││ R$6,00 │       │ ├───────────────────────┤ │
│ │ ↻ retor││ ↻ retor││        ││        │       │ │ 1× Coca 2L     R$13,00│ │
│ └────────┘└────────┘└────────┘└────────┘       │ │  [−] 1 [+]          ✕ │ │
│ … scrollable grid …                            │ └───────────────────────┘ │
│                                                 │  Subtotal       R$43,00  │
│                                                 │  Discount        R$0,00  │
│                                                 │ ───────────────────────  │
│                                                 │  TOTAL          R$43,00  │
│                                                 │  [  Finalize sale   ⏎ ]  │
└──────────────────────────────────────────────┴───────────────────────────┘
```

### Interactions

- **Click product card** → add 1 to cart, or increment if already present; micro-animation
  + total updates live.
- **Search** filters the grid live (name/brand/SKU); `Enter` with a single match adds it.
- **Category tabs** filter the grid.
- **Cart line**: stepper `[−] qty [+]`, line price, remove `✕`. Unit price editable only by
  managers (preserves the current `isManager()` rule).
- **Returnable water (10L/20L)**: an inline modality selector (Troca / Cheia / Casco) appears
  on the line, defaulting to **Troca**. Shell-validity date fields expand only when the
  modality requires them (Troca needs the returned-shell validity; all reveal the delivered
  validity per current `SaleForm` rules).
- **Balcão ▾** toggle switches type to **Entrega**, revealing customer + address + delivery/
  building fees (reuses current `SaleForm` logic, including out-of-area override).
- **Finalize** opens a quick modal: payment method (Dinheiro/PIX/Débito/Crédito), cash-tendered
  → change, confirm. On confirm, the sale is persisted (see §6).

### Visual language (refined utilitarian, Filament-native)

- Respect Filament theme, dark mode, and primary color; reserve a strong accent for action
  points (add, TOTAL, finalize).
- **Distinctive touch:** prices and totals in a **monospace, tabular-figures** treatment
  ("cash register" alignment); TOTAL rendered with display weight. Product names in a clean
  humanist sans.
- Tactile product cards with a per-category color stripe; `↻ retornável` tag on returnable
  shells.
- Desktop keyboard shortcuts: `/` focus search, `Enter` add, `Ctrl+Enter` finalize.

## 6. Architecture

- **Page:** custom Filament page generated via `php artisan make:filament-page` (Artisan-only
  rule). The page class is itself a Livewire component holding the cart state.
- **View:** Blade view (Filament page view) using Tailwind (Filament's) + Alpine for snappy
  client-side behavior (steppers, search filtering, animations). Livewire handles add/remove/
  finalize and persistence.
- **Cart state shape** (Livewire public property), one entry per variant:
  ```php
  // keyed by variant_id
  ['variant_id' => int, 'quantity' => int, 'unit_price' => float,
   'modality' => ?string, 'returned_shell_expires_at' => ?string,
   'delivered_shell_expires_at' => ?string]
  ```
- **Catalog source:** `ProductVariant` with `product` + `category`, grouped by category for
  the tabs/grid. Cached per request.
- **Finalize / data flow:** inside a DB transaction, create `Sale` (type from toggle;
  `status = confirmed`; `paid_at = now()` for counter; `user_id = auth()->id()`) then create
  each `SaleItem`. Existing model hooks then run automatically:
  - `SaleItem::saving` computes `line_total`.
  - `SaleItem::saved` cascades `Sale::recalculate()` (subtotal, `contains_water`).
  - `Sale::saved` settles stock (`settleStock`) and syncs the shell ledger when `confirmed`,
    and creates the `Delivery` record for delivery-type sales.
  No domain logic is reimplemented in the page.

## 7. Permissions & validation

- Page access: any panel user (same as the existing Sales resource; no `canAccessPanel`
  restriction exists today). Revisit if a finer policy is introduced.
- Unit-price editing gated by `isManager()` (mirrors `SaleForm`).
- Validation on finalize: cart not empty; for returnable items the required shell-validity
  date(s) present per modality (mirrors `SaleForm` rules); delivery type requires a customer +
  address.

## 8. Error handling

- Empty cart → finalize disabled with inline hint.
- Missing required water-validity date → blocked finalize with field-level message.
- Persistence wrapped in a transaction; on failure, surface a Filament notification and leave
  the cart intact.
- Stock is not hard-guarded today (settlement only writes movements); behavior preserved — no
  new negative-stock block introduced here.

## 9. Testing (Pest)

Feature tests driving the Livewire page:

- Renders for an authenticated user.
- Add variant → cart entry created; clicking same variant increments quantity.
- Increment / decrement / remove update cart and live total.
- Adding a returnable water variant defaults `modality = exchange` (Troca) and requires
  `returned_shell_expires_at` before finalize.
- Live subtotal/total reflect quantity × unit price, fees, and discount.
- Finalize persists `Sale` + `SaleItem`s, creates `StockMovement` OUT rows (status
  `confirmed`), and a `Delivery` row for delivery-type sales.
- Non-manager cannot change unit price; manager can.
- Authorization: unauthenticated users are redirected to login.

## 10. Out of scope / follow-ups

- Public homepage at `/` (parked).
- Barcode hardware, cash-drawer integration, receipt printing tweaks.
- Responsive/touch optimization for tablet (later round).
