# Runbook — Deploy + Go-Live (Phase G)

> **Owner**: Anderson de Oliveira Venturini
> **Last updated**: 2026-05-19
> **Target host**: Oracle Cloud Always-Free Tier (Ampere A1, ARM64) at <https://fa.andersonventurini.cloud>
> **Status**: Staging is live and acts as the testing environment. M7 go-live promotes the same machine to production.

This runbook covers everything between "the code is green on `main`" and "the manager is using the system in production". It is meant to be followed top-to-bottom on a fresh release.

---

## Pre-flight (do before each release)

1. **Working tree is clean and all commits are on `main`.** Confirm with `git status` and `git log --oneline -n 5`.
2. **Full Pest suite is green locally.**
   ```bash
   docker run --rm -v "$PWD:/app" -w /app fa-test:php84 vendor/bin/pest --parallel
   ```
   Expected: every test passes (current snapshot: 132 passed, 1 pre-existing skip, 395 assertions).
3. **Brand-compliance gate is green.** Same test run — `Tests\Feature\DesignSystemTest` lives there.
4. **No hex literal violations.** If the gate flags a new file, either move the token into `theme.css` / `AdminPanelProvider` or annotate the file in the allow-list with a comment explaining why.
5. **The PRD implementation-status table and `IMPLEMENTATION_PLAN.md` checklist reflect what's actually shipping.** Update before tagging.

## Tagging the release

```bash
git tag -a vMVP-$(date +%Y-%m-%d) -m "MVP release"
git push origin --tags
```

(`git push` is currently blocked by the harness; run from a regular terminal.)

## Deploy to Oracle Cloud

1. SSH to the Oracle host: `ssh ubuntu@fa.andersonventurini.cloud`.
2. `cd /var/www/fa`
3. Pull and rebuild:
   ```bash
   git fetch --all --tags
   git checkout vMVP-2026-XX-XX
   docker compose pull
   docker compose build --no-cache app
   docker compose up -d
   ```
4. Run migrations:
   ```bash
   docker compose exec app php artisan migrate --force
   ```
5. Refresh caches:
   ```bash
   docker compose exec app php artisan config:cache
   docker compose exec app php artisan route:cache
   docker compose exec app php artisan view:cache
   docker compose exec app php artisan filament:upgrade
   ```
6. Vite assets:
   ```bash
   docker compose exec app npm ci
   docker compose exec app npm run build
   ```

## Post-deploy verification

| Check | Command / URL | Expected |
|-------|---------------|----------|
| Health | `curl -I https://fa.andersonventurini.cloud/admin/login` | `HTTP/2 200` |
| DB connectivity | `docker compose exec app php artisan tinker --execute='echo DB::connection()->getPdo() ? "ok" : "fail";'` | `ok` |
| Schedule loop | `docker compose exec app php artisan schedule:list` | Shows `fa:backup-database` and the Contacts pull |
| First backup | `docker compose exec app php artisan fa:backup-database` | Exits 0, BackupRun row visible in Settings |
| Brand assets | open the admin login | FA logo + Branco Mineral background + Inter font |

## Operating during parallel-usage week (M7 hardening)

For ~10 days after go-live the manager keeps the paper notebook open AND the system open. Each day:

1. Manager records the day's sales in the system as the depot operates.
2. End of day: compare totals (system "Vendas de água" + "Vendas gerais" + Dashboard KPIs) against the notebook.
3. Any discrepancy → file under `docs/POSTMORTEM_<date>.md` and fix before the next day.

## Roll-back path

If a deploy goes wrong:

```bash
git checkout vMVP-<previous>
docker compose build app
docker compose up -d
docker compose exec app php artisan migrate:rollback --force # only if a migration is the cause
```

Database backups land daily in Google Drive (see [`docs/RUNBOOK_GOOGLE.md`](RUNBOOK_GOOGLE.md)); the most recent dump restores state without losing more than 24 h.

## Performance sanity (Phase G.2)

The current dataset is tiny (~150 SKUs, dozens of sales per day). The `sales` table already carries indexes on `(customer_id, created_at)`, `(status, created_at)`, `contains_water`; `stock_movements` indexes the morph source pair through Eloquent. EXPLAIN runs on the dashboard queries:

```bash
docker compose exec app php artisan tinker --execute='\DB::enableQueryLog(); app(\App\Filament\Widgets\SalesKpis::class); foreach (\DB::getQueryLog() as $q) print_r($q);'
```

Re-run after any feature that touches Sale queries.

## Training (Phase G.3)

A one-day on-site session with the manager + 1–2 attendants:

1. **Catalog**: how to create a Category / Product / Variant (manager only). Visual price-warning demonstration.
2. **Cargo**: receive an order, see stock go up + average cost adjust.
3. **Sale**: counter sale + delivery sale, including a returnable-shell exchange.
4. **Recompute fees**: change the default fee in Settings, recompute, verify manual-override customers keep their price.
5. **Delivery board**: deliverer flow on a phone.
6. **Reports**: water vs general listings, dashboard KPIs.
7. **Backup**: manager triggers a manual run and sees the file land in Drive.

Hand the manager a printed cheat sheet covering 1–6.

## Go-live (Phase G.4)

1. Reset the staging DB:
   ```bash
   docker compose exec app php artisan migrate:fresh --force --seed
   ```
2. Run `ProductCatalogSeeder` (idempotent — included in the seed step).
3. Manager creates the FA Distribuidora user accounts via `php artisan tinker`:
   ```php
   App\Models\User::factory()->manager()->create(['name' => '...', 'email' => '...']);
   App\Models\User::factory()->attendant()->create(...);
   App\Models\User::factory()->deliverer()->create(...);
   ```
4. Update the PRD's `Status` field from "Draft" to "Production v1.0".
5. Annotate the milestone table in the PRD with the actual go-live date.

---

## Outstanding items at MVP boundary

These are P1/P2 in the PRD and are **not** required for go-live:

- Thermal printer driver integration (an A5 PDF / 80mm HTML print is shipped; the actual ESC/POS driver is post-MVP).
- Detailed audit log UI (the `audit_logs` table is populated; the table viewer ships in P1).
- Mobile-first deliverer dashboard (current `DeliveryBoard` is mobile-friendly but not yet optimized as a PWA).
- Loyalty / cashback (P2).
