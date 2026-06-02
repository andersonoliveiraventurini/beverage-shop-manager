# Parking Lot — parked scope (NOT lost, just deferred)

> **Why this file exists** — Board decision [DEC-002](../.board/decisions/DEC-002.json) (2026-05-29, unanimous, VERY HIGH): the project keeps **building instead of shipping**. The codebase is excellent (260 tests green) but the family business is still 100% on paper. Every new idea from now until **M7 go-live** lands HERE, not in `app/`. Parking an idea here means it is **safe and remembered** — so there is no reason to build it now "before it's forgotten."
>
> **The only metric that matters until go-live:** `days in production = 0`.
>
> **Freeze rule (enforced by a `commit-msg` git hook):** commits touching `app/` are rejected unless the message is tagged `[M7]` (production-readiness / go-live work) or `[hotfix]`. To park an idea instead of building it, add a line below.

## Allowed until go-live (tag `[M7]`)
- The DEC-002 backup-gate closure on the **host**: `composer require google/apiclient`, OAuth grant, real `mysqldump` backup, verify the file in Drive, prove a restore (the `fa:restore-database` command now exists), wire monitoring on `BackupRun` failures.
- Deploy + on-site training + the 10-day humane parallel run (`docs/RUNBOOK_DEPLOY.md`).
- Genuine `[hotfix]` for anything broken in the above.

## Parked — revisit only after ~30 days of real production usage
| Idea | Origin | Note |
|---|---|---|
| F18 — WhatsApp Inbox (Evolution API) | PRD §F18 / IMPLEMENTATION_PLAN Phase H | ~2.5 weeks, ban-risk; build only with real conversation data |
| Polish item 5.10 — Google contacts import preview | NEXT_STEPS Track 5 | Needs the F16 OAuth grant to verify end-to-end |
| Hardening front (Track 6) | NEXT_STEPS Track 6 | Audit-log UI, soft-delete UI, force-cancel, backup retention, Nominatim rate-limit — prioritize from real usage pain |
| Post-MVP P1 (Track 7) | NEXT_STEPS Track 7 | F17 forecasting (needs 60 days data), supplier registry, PWA deliverer, thermal driver |

_Add new ideas below this line as one-liners. Do not build them — park them._

### Deferred UI cosmetics (from the 2026-06-01 M7-UX audit)
> The high-leverage, error-reducing fixes from that audit shipped under `[M7]` (PDV loading/confirm/insufficient-cash, cadastro helper texts + empty states, dashboard briefing order). These remaining items are purely cosmetic — revisit only after real production usage proves they matter.
- Product thumbnails / image column in the catalog and Top-Products widget (recognition aid).
- `CargoInfolist` structured view page for received cargo (supplier, date, items summary).
- Add `in_delivery_area` badge to the Customer infolist.
- MonthlyEvolutionChart: BRL-formatted Y-axis tick callback (`R$ Xk`).
- Make Expiring-Products/Shells widgets honor the dashboard date filter (currently always "from today").
- Healthy-inventory empty state (single "Inventário saudável ✓" stat when all expiry counts are zero).
- Geolocation/lat-lng fieldset grouping + helper text on customer addresses.
- Categories table column reorder (product count before slug; slug toggleable).

### Deferred audit findings (from the 2026-06-01 routes/models/forms validation)
> The high-value data-entry / tampering gaps (value clamps, discount cap, non-manager fee/price re-derivation, PDV finalize hardening) shipped under `[hotfix]`. These remaining items are real but lower-priority for a trusted, internal, low-concurrency family tool — revisit at the next unfreeze window or if real usage hits them.
- **#5 (MEDIUM) — `SalePolicy::viewAny/view` return `true`**: any authenticated user (incl. deliverer) can read every sale + its receipt (the `sales.receipt` route's `can('view')` gate is effectively a no-op). Fix: return `! $user->isDeliverer()` (or scope to own deliveries). `app/Policies/SalePolicy.php:12-20`.
- **#6 (MEDIUM, latent) — Non-atomic stock settlement**: `Sale::settleStock` does `exists()`→`create()` with no lock and `stock_movements` has no unique index on `(source_type, source_id, direction, reason)`; a double-submit/retry could decrement stock twice. Fix: unique index + `lockForUpdate`/`firstOrCreate`. Needs a migration. `app/Models/Sale.php:135` + migration `..._create_stock_movements_table`.
- **#7 (MEDIUM edge case) — Shell-ledger soft-delete collision**: `water_shell_ledgers` unique `(customer_id, variant_id, expires_at)` excludes `deleted_at`, and `Sale::bumpLedger` uses `firstOrNew` (excludes trashed) → duplicate-key violation if a row was ever soft-deleted, aborting the sale's shell sync. Fix: make the unique index `deleted_at`-aware or `withTrashed()`+restore. Needs a migration.
- **#8 (LOW) — Broad `$fillable`** on state/ownership columns (`Sale::status/user_id/stock_settled`, `StockMovement`, `Delivery::status`): no exposure today (only Filament/PDV write them) but a future non-Filament endpoint could escalate state. Fix: drop them from `$fillable` and set explicitly.
- **Money as `float`** (round-per-step mitigates; pennies can still drift on large multi-line sums). Consider integer cents / `brick/money`. `Sale.php:80-86,302`, `SaleItem.php:40`.
- **No overselling guard**: neither PDV nor SaleForm checks `current_stock` before confirming — stock can go negative (may be an intentional counter-sale decision).
- _Fixed 2026-06-01:_ the `RestoreCommandTest` parallel flake (shared `storage/app/backups` dir, second-granular filenames) is now deterministic via a unique-marker dump selection. The robust **app-side** improvement — recording the local dump path on `BackupRun` so callers don't have to glob — remains parked (touches the backup command; `[hotfix]` when convenient).
