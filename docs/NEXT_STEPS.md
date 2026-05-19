# Next Steps — FA Distribuidora

> **Snapshot date**: 2026-05-19
> **Status**: MVP code-complete (commits `f03bd7d` → `81393f6`). 132/132 tests green. Six follow-up tracks below.

Each track is independent. Pick the one with the most pressure today.

---

## Track 1 — Production cut-over (highest value)

**Goal**: replace the paper notebook with the system in real operation.

| Step | Owner | Time | Notes |
|---|---|---|---|
| 1.1 | Push the remaining local commit (`git push origin main`) — Phase 0→G is already on `origin/main` (commits `f03bd7d`…`81393f6`); only the doc-refresh commit `584eb79` is still local | Anderson | 1 min | The harness blocks direct pushes; run from a regular terminal |
| 1.2 | Tag `vMVP-2026-05-19` | Anderson | 1 min | `git tag -a … && git push --tags` |
| 1.3 | Deploy to Oracle Cloud per [`docs/RUNBOOK_DEPLOY.md`](RUNBOOK_DEPLOY.md) §"Deploy to Oracle Cloud" | Anderson | 30 min | Migrations, caches, Vite assets |
| 1.4 | Seed staging users (1 manager + 1 attendant + 1 deliverer) via `php artisan tinker` | Anderson | 5 min | Recipe at the bottom of [`README.md`](../README.md#quick-start--local-development) |
| 1.5 | Smoke-test the admin panel on `fa.andersonventurini.cloud` | Anderson | 15 min | Login, sale, receipt, delivery board, dashboard |
| 1.6 | Train the FA Distribuidora manager + 1–2 attendants on-site | Anderson + manager | 1 day | Agenda in [`docs/RUNBOOK_DEPLOY.md`](RUNBOOK_DEPLOY.md) §"Training (Phase G.3)" |
| 1.7 | Run 10 days of **parallel usage** (notebook + system) | Manager | 10 days | Reconcile daily totals; file `docs/POSTMORTEM_<date>.md` for any divergence |
| 1.8 | Switch PRD status to **"Production v1.0"** and annotate go-live date | Anderson | 5 min | Last line of `prd-fa-distribuidora.md` §10 milestones |

**Blockers**: none — every code dependency is in place.

---

## Track 2 — Google integrations live (F15 + F16)

**Goal**: daily Drive backup + 15-min Contacts sync running unattended.

| Step | Owner | Time | Notes |
|---|---|---|---|
| 2.1 | Create the dedicated FA Google account (`fa.distribuidora.sistema@gmail.com`) | Anderson | 10 min | Skip if already provisioned |
| 2.2 | Generate the Gmail SMTP **app password** for that account | Anderson | 5 min | Stored in `.env::MAIL_PASSWORD` |
| 2.3 | Follow [`docs/RUNBOOK_GOOGLE.md`](RUNBOOK_GOOGLE.md) steps 1–7 end-to-end | Anderson | 30 min | Google Cloud project, OAuth grant, env vars, schedule, verification |
| 2.4 | Install `composer require google/apiclient google/apiclient-services` on the host (verify ARM64 wheels) | Anderson | 10 min | Listed in `docs/RUNBOOK_GOOGLE.md` step 5 |
| 2.5 | Wire the real `google/apiclient` client into `GoogleDriveUploader::callDrive()` and `GoogleContactsSync::callPeopleApi()` / `pushToPeopleApi()` seams | Anderson | 2 h | Tests use the seam — swap-in is non-breaking |
| 2.6 | Add the schedule lines to `routes/console.php` | Anderson | 5 min | Snippet ready in `docs/RUNBOOK_GOOGLE.md` step 6 |
| 2.7 | Confirm: first backup lands in Drive; first contacts pull creates rows on staging | Anderson | 10 min | Settings page surfaces `BackupRun::latestRun()` for the green check |

**Blockers**: needs the FA Google account credentials in the maintainer's hands.

---

## Track 3 — P1 polish — close out the ⚠️ items in PRD §4

Pick these in priority order. None blocks go-live.

| # | Feature | What's pending | Effort | Why it's worth doing |
|---|---|---|---|---|
| 3.1 | **F10** | Add an explicit "Override manual" Toggle on the Customer form (today the column is set programmatically) | S | Manager-facing UX — the column exists but isn't manageable from the UI yet |
| 3.2 | **F10** | Customer Infolist surface with distance, fees, source (auto/manual), `fees_calculated_at` | S | Manager wants to see "why is this customer's fee X?" at a glance |
| 3.3 | **F11** | DeliveryBoard explicit filter chips for district / status (deliverer scope is automatic) | S | Easier triage on busy days |
| 3.4 | **F12** | Wire Filament v5 native **CSV/XLSX export** on `WaterSales` + `GeneralSales` page headers | S | One-line each via Filament's `Tables\Actions\ExportAction` |
| 3.5 | **F13** | Page-level **date range filter** that fans out to every dashboard widget | M | Today the dashboard always shows the current month — manager will want quarterly views |
| 3.6 | **F13** | Add **Top customers** widget + **Monthly evolution** line chart | M | Both already specced; need their own widget classes following the Phase E pattern |
| 3.7 | **F13** | Dashboard **PDF export** (DomPDF route) | M | Manager-facing report generation |
| 3.8 | **F08** | Per-product + per-period filters on `SalesRelationManager`; total-spent-in-period column | S | Filament Select filter on `variant_id` + a `Tables\Columns\Summarizers\Sum` |
| 3.9 | **F09** | Explicit per-row authorization (`delivery_fee` / `building_fee` override) with `audit_logs` entry | S | Already half-paved (`unit_price` + `out_of_area_override` already gated and audited) |
| 3.10 | **F16** | "Initial import preview" — count Google contacts before applying on first connect | M | Polish for the OAuth-grant flow |

**Blockers**: none.

---

## Track 4 — Hardening for first-month traffic

**Goal**: protect against the obvious foot-guns once real users are typing.

| Step | Effort | Notes |
|---|---|---|
| 4.1 Audit-log UI viewer | M | The `audit_logs` table is populated (Phase C); a manager-only `AuditLogResource` lists entries by event/user/date |
| 4.2 Soft-delete UI for customers + sales | S | Both models already `SoftDeletes`; expose a `TrashedFilter` + `RestoreAction` on each table |
| 4.3 Manager-only "force-cancel" on already-confirmed sales older than 24 h | S | Otherwise attendants reverse stock with no friction |
| 4.4 Backup retention policy: monthly archive on top of the 30-day rolling | S | Open question Q17 in the PRD — resolved when the manager + Anderson agree |
| 4.5 Rate-limit Nominatim per address — current code already caches per-query but a bulk recompute on 200+ customers could still rate-limit | S | Queue the geocode + spread the calls; current implementation is fine for ≤ 50 customers |

---

## Track 5 — Post-MVP P1 features (PRD §"Post-MVP (P1)")

| Feature | Effort | When |
|---|---|---|
| F17 Purchase forecasting | M | Once 60 days of sales data exists on production |
| Detailed audit-log surface | S | Track 4 item 4.1 covers this |
| Mobile-first deliverer dashboard (PWA) | M | Current DeliveryBoard is mobile-friendly but not installable |
| Thermal-printer ESC/POS driver | M | A5/80mm HTML works today; the actual USB driver is hardware-shop work |
| First-class supplier registry | S | Today supplier is just a string on Cargo; promote to its own model |

---

## Track 7 — WhatsApp Conversational Inbox via Evolution API (F18 · Phase H · M8)

**Goal**: cliente manda mensagem no WhatsApp → atendente lê dentro do sistema, responde, e clica "Iniciar venda" sem trocar de aplicativo. Novos contatos viram cadastros em um clique.

Scope decisions locked 2026-05-19 (see PRD §F18):

- **Self-hosted Evolution API** alongside the FA Docker Compose (no SaaS).
- **Manager + attendant** only (deliverer blocked).
- **Match by normalized phone** against `customer_phones.number`; unmatched conversations expose a "Cadastrar novo cliente" inline action.
- **"Iniciar venda"** button in the conversation panel opens `CreateSale` with `customer_id` + `type=delivery` + `address_id=primary` pre-filled.
- **Text-only in MVP** (media in F18.1).
- **Operator-initiated only** — no auto-reply, no scheduled broadcasts. Minimizes WhatsApp ban risk on the existing FA number.

| Step | Owner | Effort | Notes |
|---|---|---|---|
| 7.1 | Provision the WhatsApp number for system use (one-time QR-scan after H.1 lands) | Anderson + Manager | 10 min | FA's existing number; minimize programmatic use |
| 7.2 | Implementation Phase H (steps H.1–H.11) | Anderson | ~2.5 weeks | See [`docs/IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md#phase-h--whatsapp-conversational-inbox-via-evolution-api-m8--new-scope) |
| 7.3 | Smoke-test on staging: send/receive at least one round-trip; create one customer from an unmatched conversation; open one "Iniciar venda" | Anderson + Manager | 1 h | Document anomalies in `docs/POSTMORTEM_F18.md` |
| 7.4 | Train manager + attendants on the new inbox (extension of Track 1.6) | Anderson + Manager | 1 h | Quick-reference card |
| 7.5 | Bump PRD to v1.8.0 marking F18 acceptance criteria checked | Anderson | 5 min | Final closure |

**Blockers**: Phase H **must wait for M7 cut-over** so the integration ships against production data, not a half-seeded staging.

**Out of MVP (deferred to F18.1)**: bidirectional media, templated quick replies, recommendation chips based on purchase history, Reverb-broadcast push.

**Risk + mitigation**: WhatsApp may ban the FA number for non-official API usage. Mitigations baked into Phase H: operator-initiated only, low volume (≤ 50 msg/day expected), and the `WhatsAppGateway` interface keeps a clean migration path to the official Meta Business API in a future major.

---

## Track 6 — Documentation maintenance (continuous)

The PRD and the plan are now the **operational truth** for the project. Keep them in sync:

- **Every feature commit** ticks a checkbox in PRD §4 — the brand-compliance gate is the only one the test enforces; everything else is a discipline check at code review.
- **Every milestone shift** updates the table in PRD §10 + the IMPLEMENTATION_PLAN change log.
- **Every deviation** lands in the *Deviations* table near the top of the PRD (precedent: F14's hard-coded catalog seeder).
- **Every external dependency** (a new API, a new env var, a new manual step) lands in the matching runbook before it's merged.

A 5-minute discipline that keeps the project legible for the next person — human or AI — that opens the repo.

---

## Quick-glance summary

| Status | What's left |
|---|---|
| ✅ Code (MVP F01–F16) | Nothing — every PRD MVP feature ships in code |
| 🟡 Operational (M6 + M7) | Push commits · OAuth grant · on-site training · go-live |
| 🟡 Polish | ~10 small P1 items in PRD §4 (Track 3) |
| ⛔ New scope (M8) | **F18 WhatsApp inbox (Track 7)** — starts after M7 go-live |
| ⛔ Out of MVP | Post-MVP features (Track 5) |
