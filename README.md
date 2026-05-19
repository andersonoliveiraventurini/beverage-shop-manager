# FA Distribuidora — Management System

Full-stack management system for **FA Distribuidora** (Água · Bebidas · Carvão — Av. Transamazônica 1197, Jardim Garcia, Campinas-SP). Handles the product catalog with multiple variants, customer registry with per-customer delivery and building fees, cargo-driven inventory with batch expiry, sales with multiple payment methods (cash, PIX, debit, credit), a daily delivery dispatch board, an optional per-customer returnable water-gallon ledger, separated water-vs-general sales listings and a consolidated admin dashboard.

> **Status (2026-05-19)**: code-complete MVP. Every PRD feature F01–F16 ships in code. F15/F16 await a one-time Google OAuth grant (see [`docs/RUNBOOK_GOOGLE.md`](docs/RUNBOOK_GOOGLE.md)); production cut-over awaits on-site training (see [`docs/RUNBOOK_DEPLOY.md`](docs/RUNBOOK_DEPLOY.md)).

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4 + Laravel 12 |
| Admin panel | Filament v5 |
| Reactivity | Livewire 3 + Tailwind CSS |
| Database | MySQL 8 (production) · SQLite in-memory (test) |
| Web server | Apache 2.4 inside Docker |
| Hosting | Oracle Cloud Always-Free Tier — Ampere A1 (ARM64) |
| Tests | Pest 4 + Filament's `livewireTest()` helper |

UI strings are pt-BR; code, identifiers, table names and docs are English (project convention — see [`docs/DESIGN.md`](docs/DESIGN.md) §9).

## Environments

| Environment | URL | Purpose |
|---|---|---|
| Local development | <http://localhost:8765> (Laravel Sail) | Daily development on the maintainer's machine |
| Staging / testing | <https://fa.andersonventurini.cloud> | Live deployment on Oracle Cloud Always-Free. **Testing environment** until production go-live (milestone M7) — the same instance becomes production at cut-over |

Both environments share the same schema and `ProductCatalogSeeder`. Operational data does not flow between them; staging may be wiped and re-seeded at any time.

## Documentation

| Document | Purpose |
|---|---|
| [`docs/prd/prd-fa-distribuidora.md`](docs/prd/prd-fa-distribuidora.md) | Product Requirements Document — every feature, every acceptance criterion, every milestone. Read this first |
| [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) | Phase-by-phase implementation plan with the commit map of what shipped where |
| [`docs/DESIGN.md`](docs/DESIGN.md) | Design system distilled from the brand manual. Token tables, Blade components, source-of-truth map |
| [`docs/RUNBOOK_GOOGLE.md`](docs/RUNBOOK_GOOGLE.md) | One-time runbook for the Google OAuth grant that unlocks F15 (Drive backup) + F16 (Contacts sync) |
| [`docs/RUNBOOK_DEPLOY.md`](docs/RUNBOOK_DEPLOY.md) | Deploy + go-live runbook (tag → deploy → verify → train → cut-over) |
| [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md) | Six-track roadmap of what remains. Read this when you sit down to work |
| [`FA Distribuidora · Print.pdf`](FA%20Distribuidora%20%C2%B7%20Print.pdf) | Canonical visual brand manual (palette, typography, logo, mockups) |
| [`produtos-deposito.xlsx`](produtos-deposito.xlsx) | Reference catalog spreadsheet (already transcribed into `ProductCatalogSeeder`) |

## Quick start — local development

```bash
git clone https://github.com/<owner>/beverage-shop-manager.git
cd beverage-shop-manager
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Admin panel: <http://localhost:8765/admin>.

Seed two users for sign-in:

```bash
./vendor/bin/sail artisan tinker --execute='
\App\Models\User::factory()->manager()->create([
    "name" => "Gerente Local",
    "email" => "manager@fa.local",
    "password" => bcrypt("password"),
]);
\App\Models\User::factory()->attendant()->create([
    "name" => "Atendente Local",
    "email" => "attendant@fa.local",
    "password" => bcrypt("password"),
]);'
```

## Running tests

Tests run in a self-contained Docker image with PHP 8.4 + intl (built once locally):

```bash
docker build -t fa-test:php84 .docker/test  # see RUNBOOK_DEPLOY.md if the directory is missing
docker run --rm -v "$PWD:/app" -w /app fa-test:php84 vendor/bin/pest --parallel
```

Current snapshot:

```
Tests: 1 skipped, 132 passed (395 assertions)
Duration: ~57 s (parallel, 12 processes)
```

The single skip is a deferred Category-factory list-render test documented at `tests/Feature/Filament/CategoryResourceTest.php`.

## Code map (high level)

```text
app/
  Console/Commands/RunDatabaseBackup.php       fa:backup-database — F15
  Enums/UserRole.php                            manager / attendant / deliverer
  Filament/
    Pages/                                     Settings, DepotConfig, DeliveryBoard, WaterSales, GeneralSales
    Resources/                                 Categories, Products, Customers, Sales, StockMovements, Cargos, WaterShellLedgers
    Widgets/                                   SalesKpis, PaymentMethodBreakdown, WaterVsRestChart,
                                               TopProductsTable, ExpiringProducts, ExpiringShells
  Models/                                      Store, User, Category, Product, ProductVariant, Customer,
                                               CustomerAddress, CustomerPhone, Sale, SaleItem,
                                               StockMovement, Cargo, CargoItem, Delivery, WaterShellLedger,
                                               DeliverySetting, DeliverySettingRevision, AuditLog, BackupRun
  Policies/                                    Category, Product, Customer, Sale, StockMovement,
                                               WaterShellLedger, Cargo, Delivery
  Services/                                    CustomerFeeCalculator, AddressGeocoder (Nominatim),
                                               GoogleDriveUploader, GoogleContactsSync
config/brand.php                              brand string literals + chart colors (NFR-01)
docs/                                          PRD + design system + plan + runbooks
resources/
  css/filament/admin/theme.css                CSS variables — single source for color + font tokens
  views/
    components/fa/                             wave-divider, disk-entregas
    filament/pages/                            settings, depot-config, delivery-board, water-sales, general-sales
    sales/receipt.blade.php                    A5 + 80mm printable receipt
tests/                                         87 Filament tests, 26 model tests, 9 service tests,
                                               5 design-system tests, 5 policy tests
```

## Roles

| Role | Sees | Can write |
|---|---|---|
| **Manager** | Everything | Catalog, customers, sales, stock, settings, depot, recompute, cargo, receipt reprint, dashboard |
| **Attendant** | Catalog (read), customers, sales, stock (read), shell ledger (read), delivery board | Customer + Sale CRUD; no settings, no depot, no role-changing |
| **Deliverer** | Sale (view only), DeliveryBoard scoped to own + unassigned | Start route / mark completed / cancel-with-reason on own deliveries |

## Brand compliance gate (NFR-01)

Every PR runs `tests/Feature/DesignSystemTest.php`. Hex literals outside the allow-list (`theme.css`, `AdminPanelProvider`, brand SVGs, receipt template, the gate test itself) fail the build. Move new tokens into `theme.css` (CSS variable) or `config/brand.php` (string / chart color) — never inline.

## Contributing

1. Read [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) for the phase plan and what each commit covers.
2. Pest-first. Failing test → minimal implementation → green. Every commit must keep `vendor/bin/pest --parallel` green.
3. Use Artisan (`make:model`, `make:filament-resource`, `make:policy`, `make:migration`, `make:command`, `make:filament-widget`, `make:filament-page`) — no hand-written boilerplate.
4. Conventional commit messages. No "Claude as co-author" tag.
5. Brand-compliance gate must stay green.

## License

Internal project — not licensed for redistribution.
