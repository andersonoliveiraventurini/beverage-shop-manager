<!-- Brand banner — palette & wave device mirror docs/DESIGN.md (single source of truth) -->
<p align="center">
  <img src="docs/assets/banner.svg" alt="FA Distribuidora — Sistema de Gestão · Água · Bebidas · Carvão" width="100%">
</p>

<p align="center">
  <a href="https://www.php.net"><img alt="PHP 8.5" src="https://img.shields.io/badge/PHP-8.5-0B3D91?style=for-the-badge&logo=php&logoColor=white"></a>
  <a href="https://laravel.com"><img alt="Laravel 12" src="https://img.shields.io/badge/Laravel-12-0B3D91?style=for-the-badge&logo=laravel&logoColor=white"></a>
  <a href="https://filamentphp.com"><img alt="Filament v5" src="https://img.shields.io/badge/Filament-v5-3FA9F5?style=for-the-badge&logo=laravel&logoColor=white"></a>
  <a href="https://livewire.laravel.com"><img alt="Livewire 4" src="https://img.shields.io/badge/Livewire-4-3FA9F5?style=for-the-badge&logo=livewire&logoColor=white"></a>
  <img alt="Pest" src="https://img.shields.io/badge/Tested%20with-Pest%203-F7C948?style=for-the-badge&logo=pest&logoColor=0E1730">
  <img alt="Tests passing" src="https://img.shields.io/badge/tests-261%20passing-0B3D91?style=for-the-badge">
  <img alt="Milestone M7" src="https://img.shields.io/badge/milestone-M7%20go--live-F7C948?style=for-the-badge&labelColor=0E1730">
</p>

<p align="center">
  <strong>Água · Bebidas · Carvão</strong> &nbsp;—&nbsp; Av. Transamazônica 1197, Jardim Garcia, Campinas-SP
</p>

Full-stack management system for **FA Distribuidora**, a neighborhood beverage depot. It handles the product catalog with multiple variants, a customer registry with per-customer delivery and building fees, cargo-driven inventory with batch expiry, point-of-sale and sales with multiple payment methods (cash, PIX, debit, credit), a daily delivery dispatch board, an optional per-customer returnable water-gallon ledger, separated water-vs-general sales listings, and a consolidated owner dashboard.

<!-- wave divider -->
<img src="docs/assets/wave-divider.svg" alt="" width="100%">

> ### 🚦 Project status — _2026-06-01_
> **Code-complete MVP** (every PRD feature F01–F16 ships in code) now under a deliberate **code freeze**: the focus is **shipping, not building**. The only metric that matters until **M7 go-live** is `days in production`. New ideas are parked in [`docs/PARKING_LOT.md`](docs/PARKING_LOT.md), not added to `app/`. F15/F16 await a one-time Google OAuth grant ([`docs/RUNBOOK_GOOGLE.md`](docs/RUNBOOK_GOOGLE.md)); cut-over awaits on-site training ([`docs/RUNBOOK_DEPLOY.md`](docs/RUNBOOK_DEPLOY.md)).

## 🎨 Brand identity

The interface is built on a locked design system distilled from the printed brand manual. Hydrated palette, weighted typography, and a recurring **wave** device — clean, trustworthy, family-run.

| Token | Name | Hex | Role |
|---|---|---|---|
| ![](https://img.shields.io/badge/-0B3D91?style=flat-square&color=0B3D91) | Azul Profundo | `#0B3D91` | Primary — logo, headings, primary actions |
| ![](https://img.shields.io/badge/-3FA9F5?style=flat-square&color=3FA9F5) | Azul Cristal | `#3FA9F5` | Secondary — info & water accents |
| ![](https://img.shields.io/badge/-F7C948?style=flat-square&color=F7C948) | Amarelo Solar | `#F7C948` | Accent — phone CTA, highlights, warnings |
| ![](https://img.shields.io/badge/-FAFBFD?style=flat-square&color=FAFBFD) | Branco Mineral | `#FAFBFD` | Background — page & card surfaces |
| ![](https://img.shields.io/badge/-0E1730?style=flat-square&color=0E1730) | Tinta Noite | `#0E1730` | Text — body & headings on light |

Typography: **Archivo Black** for titles, big numbers and the phone CTA · **Inter** for everything practical. Full token tables, Blade components and the source-of-truth map live in [`docs/DESIGN.md`](docs/DESIGN.md).

## 🧱 Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.5 + Laravel 12 |
| Admin panel | Filament v5 |
| Reactivity | Livewire 4 + Tailwind CSS v4 |
| Database | MySQL 8 (production) · SQLite in-memory (test) |
| Web server | Apache 2.4 inside Docker |
| Hosting | Oracle Cloud Always-Free — Ampere A1 (ARM64) |
| Tests | Pest 3 + Filament's `livewireTest()` helper |

> UI strings are pt-BR; code, identifiers, table names and docs are English (project convention — see [`docs/DESIGN.md`](docs/DESIGN.md) §9).

## 🌐 Environments

| Environment | URL | Purpose |
|---|---|---|
| Local development | <http://localhost:8765> (Laravel Sail) | Daily development on the maintainer's machine |
| Staging / testing | <https://fa.andersonventurini.cloud> | Live on Oracle Cloud Always-Free. **Testing environment** until M7 go-live — the same instance becomes production at cut-over |

Both environments share the same schema and `ProductCatalogSeeder`. Operational data does not flow between them; staging may be wiped and re-seeded at any time.

<img src="docs/assets/wave-divider.svg" alt="" width="100%">

## 🚀 Quick start — local development

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

<details>
<summary>👤 Seed two users for sign-in</summary>

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
</details>

## ✅ Running tests

Tests use SQLite `:memory:` — no database container required.

```bash
./vendor/bin/sail artisan test --parallel
```

Current snapshot:

```
Tests: 1 skipped, 261 passed (764 assertions)
```

## 👥 Roles

| Role | Sees | Can write |
|---|---|---|
| **Manager** | Everything | Catalog, customers, sales, stock, settings, depot, recompute, cargo, receipt reprint, dashboard |
| **Attendant** | Catalog (read), customers, sales, stock (read), shell ledger (read), delivery board | Customer + Sale CRUD; no settings, no depot, no role changes |
| **Deliverer** | Sale (view only), DeliveryBoard scoped to own + unassigned | Start route / mark completed / cancel-with-reason on own deliveries |

## 📚 Documentation

| Document | Purpose |
|---|---|
| [`docs/prd/prd-fa-distribuidora.md`](docs/prd/prd-fa-distribuidora.md) | Product Requirements Document — every feature, acceptance criterion and milestone. Read this first |
| [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) | Phase-by-phase plan with the commit map of what shipped where |
| [`docs/DESIGN.md`](docs/DESIGN.md) | Design system — token tables, Blade components, source-of-truth map |
| [`docs/PARKING_LOT.md`](docs/PARKING_LOT.md) | Deferred scope. Where new ideas go during the freeze (ship, don't build) |
| [`docs/RUNBOOK_GOOGLE.md`](docs/RUNBOOK_GOOGLE.md) | One-time Google OAuth grant that unlocks F15 (Drive backup) + F16 (Contacts sync) |
| [`docs/RUNBOOK_DEPLOY.md`](docs/RUNBOOK_DEPLOY.md) | Deploy + go-live runbook (tag → deploy → verify → train → cut-over) |
| [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md) | Roadmap in execution order. Read this when you sit down to work |
| [`FA Distribuidora · Print.pdf`](FA%20Distribuidora%20%C2%B7%20Print.pdf) | Canonical visual brand manual (palette, typography, logo, mockups) |

<details>
<summary>🗺️ Code map (high level)</summary>

```text
app/
  Console/Commands/                            fa:backup-database, fa:restore-database — F15
  Enums/UserRole.php                           manager / attendant / deliverer
  Filament/
    Pages/                                     PointOfSale, Settings, DepotConfig, DeliveryBoard,
                                               WaterSales, GeneralSales, Dashboard
    Resources/                                 Categories, Products, Customers, Sales, StockMovements,
                                               Cargos, WaterShellLedgers
    Widgets/                                   SalesKpis, PaymentMethodBreakdown, WaterVsRestChart,
                                               MonthlyEvolutionChart, TopProductsTable, TopCustomersTable,
                                               ExpiringProducts, ExpiringShells
  Models/                                      Store, User, Category, Product, ProductVariant, Customer,
                                               CustomerAddress, CustomerPhone, Sale, SaleItem,
                                               StockMovement, Cargo, CargoItem, Delivery, WaterShellLedger,
                                               DeliverySetting, DeliverySettingRevision, AuditLog, BackupRun
  Policies/                                    Category, Product, Customer, Sale, StockMovement,
                                               WaterShellLedger, Cargo, Delivery
  Services/                                    CustomerFeeCalculator, AddressGeocoder (Nominatim),
                                               GoogleDriveUploader, GoogleContactsSync
config/brand.php                               brand string literals + chart colors (NFR-01)
resources/
  css/filament/admin/theme.css                 CSS variables — single source for color + font tokens
  views/
    components/fa/                             wave-divider, disk-entregas
    filament/pages/                            point-of-sale, settings, depot-config, delivery-board, …
    sales/receipt.blade.php                    A5 + 80mm printable receipt
docs/                                          PRD + design system + plan + runbooks + parking lot
tests/                                         Filament feature, model, service, design-system & policy suites
```
</details>

## 🛡️ Brand compliance gate (NFR-01)

Every PR runs `tests/Feature/DesignSystemTest.php`. Hex literals outside the allow-list (`theme.css`, `AdminPanelProvider`, brand SVGs, receipt template, the gate test itself) fail the build. Move new tokens into `theme.css` (CSS variable) or `config/brand.php` (string / chart color) — never inline.

## 🤝 Contributing

1. Read [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) for the phase plan and what each commit covers.
2. **Pest-first.** Failing test → minimal implementation → green. Every commit keeps `./vendor/bin/sail artisan test --parallel` green.
3. Use Artisan generators (`make:model`, `make:filament-resource`, `make:policy`, `make:migration`, `make:command`, `make:filament-widget`, `make:filament-page`) — no hand-written boilerplate.
4. Conventional commit messages. No "Claude as co-author" tag.
5. During the freeze, commits touching `app/` must be tagged `[M7]` or `[hotfix]` (enforced by a `commit-msg` hook). Everything else goes to the [parking lot](docs/PARKING_LOT.md).
6. The brand-compliance gate must stay green.

<img src="docs/assets/wave-divider.svg" alt="" width="100%">

<p align="center">
  <sub><strong>FA Distribuidora</strong> · Internal project — not licensed for redistribution · <em>Água · Bebidas · Carvão</em></sub>
</p>
