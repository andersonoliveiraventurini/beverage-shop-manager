# Beverage Shop Manager

Full-stack management system for beverage depots, built with **Laravel 12** + **Filament v5** (admin panel) + **Livewire 4** + **MySQL 8** + **Apache 2.4**. Handles the product catalog with multiple variants, customer registry with per-customer delivery and building fees, batch-based inventory with expiration tracking, sales with multiple payment methods (cash, PIX, debit, credit), daily delivery dispatch, and an optional returnable water-gallon ledger.

Tailored for **FA Distribuidora** (Água · Bebidas · Carvão — Av. Transamazônica 1197, Jardim Garcia, Campinas-SP).

## Environments

| Environment | URL | Purpose |
|-------------|-----|---------|
| Local development | http://localhost:8765 (Laravel Sail) | Daily development on the maintainer's machine |
| Staging / testing | https://fa.andersonventurini.cloud | Live deployment on Oracle Cloud Always-Free (Ampere ARM A1). Currently used as the **testing environment** until the system reaches production go-live (milestone **M7 — 2026-08-20**) |

Both environments share the same database schema and seed data (`ProductCatalogSeeder`). Production go-live will reuse this same Oracle Cloud instance — the URL stays the same; only its operational status changes.

## Documentation

- [`docs/prd/prd-fa-distribuidora.md`](docs/prd/prd-fa-distribuidora.md) — full Product Requirements Document
- [`FA Distribuidora · Print.pdf`](FA%20Distribuidora%20%C2%B7%20Print.pdf) — brand book (logo, palette, typography, print pieces)

## Quick start (local)

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Admin panel: <http://localhost:8765/admin>
