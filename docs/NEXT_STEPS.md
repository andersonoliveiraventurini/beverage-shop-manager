# Next Steps — FA Distribuidora

> **Snapshot date**: 2026-05-29
> ## 🎯 THE ONLY METRIC THAT MATTERS: `days in production = 0`
> Not test count, not closed criteria. The family business is still 100% on paper. **Code is frozen until M7 go-live** (a `commit-msg` git hook rejects `app/` commits not tagged `[M7]`/`[hotfix]`; park ideas in [`docs/PARKING_LOT.md`](PARKING_LOT.md)).
>
> **State**: MVP F01–F16 + F19 PDV + F20 storefront + the **Polish P1 front (Track 5, 5.1–5.9)** all ship in code and are on `origin/main`. **260 Pest tests green (1 skipped)**. The DEC-002 backup-gate code is closed: a real **restorable** logical dump, the **`fa:restore-database`** command with a **proven round-trip test**, and **production fails loud** when no off-host copy leaves the host. F18 WhatsApp Inbox stays parked (Phase H).
>
> **Board of Advisors — DEC-001 (2026-05-23) AND DEC-002 (2026-05-29): _ship beats build_ (reaffirmed, VERY HIGH).** DEC-001 said stop building and ship; in the 6 days after, three more code fronts were built instead. DEC-002 (unanimous) ruled the codebase healthy but the *project failing* (0% of goal), named the build-avoidance pattern, and prescribed: **(0)** push + protect work ✅ done · **(1)** structural freeze (commit-guard + parking-lot) ✅ done · **(2)** close the backup gate *for real* — code done ✅; the **host steps remain** (install `google/apiclient`, OAuth grant, real `mysqldump`, verify file in Drive, **prove a restore**, monitoring) · **(3)** M7 cut-over · **(4)** ~30 days real usage *then* reassess F18/hardening/polish. **No new scope until the family is off paper.**

This document is the operational roadmap. Tracks are listed **in the order you should attack them**. Each track is self-contained — owner, effort, dependencies and blockers up front so you can pick the top open track and run.

---

## Priority map (execution order)

| # | Track | Status | Calendar | Unblocks |
|---|---|---|---|---|
| 1 | **Push `main` + protect work** | ✅ Done 2026-05-29 | — | 14 commits + gate-closing pushed to origin/main (copy `.board/` off-machine — it's gitignored) |
| 2 | **Backup gate LIVE on the host (DEC-002 §2)** 🔒 | 🟡 Open — **blocks everything** | ~½ day human | Install `google/apiclient` + OAuth grant + real `mysqldump` + verify file in Drive + **prove a restore** (`fa:restore-database`). Code done; host steps pending. NO real data before this is proven |
| 3 | **M7 — Production cut-over** | 🟡 Open | ~2 weeks (deploy + 10 days humane parallel-usage) | The whole point of the project — the family off paper |
| 4 | **M8 — F18 WhatsApp Inbox (Phase H)** | ⛔ Spec done, code not started | ~2.5 weeks | Closes the biggest operational gap after cut-over |
| 5 | **P1 polish** | ✅ Shipped (9/10 on `feat/polish-p1`) | — | Items 5.1–5.9 done; 5.10 (Google import preview) deferred to the F16 OAuth grant |
| 6 | **Hardening for first-month traffic** | 🟡 Open | ~1 week, scattered | Defensive — addresses the foot-guns once the manager and attendants are typing |
| 7 | **Post-MVP P1 features** | ⛔ Not started | Each L/M | Only worth doing once production data exists |
| 8 | **Doc maintenance** | 🟢 Continuous | 5 min per commit | The PRD, the plan and the runbooks stay in sync as we ship |

The next thing to **do** (push is done) is the **backup gate LIVE on the host** — the one item that both blocks go-live and cannot be rationalized into more features. Then **M7 cut-over**. Per **DEC-002**, nothing else — no F18, no further polish, no hardening — until the family has used the system in production for ~30 days. Code is frozen (`[M7]`/`[hotfix]` only); everything else goes to [`PARKING_LOT.md`](PARKING_LOT.md).

---

## Track 1 — Push consolidated `main`

On 2026-05-29 the two divergent feature branches were merged into `main`: `feat/pos-counter-sales` (PDV + Drive client wiring + E2E sweep + brand hardening) fast-forwarded, then `feat/public-homepage` (storefront) merged clean. Full Pest suite green (207 passed / 1 skip). The harness blocks direct pushes to `main`; push from a regular terminal.

| Step | Command | Notes |
|---|---|---|
| 1.1 | `git log origin/main..HEAD --oneline` | Confirm the merged commits + the v1.8.0 doc commit are about to be pushed |
| 1.2 | `git push origin main` | Run from a normal terminal (Claude Code's auto-classifier blocks this) |
| 1.3 | Verify on GitHub | `main` carries the PDV, storefront and v1.8.0 docs; PRD reflects F19 + F20 |

**Blockers**: none.

---

## Track 2 — M7 production cut-over

**Goal**: replace the paper notebook with the system in real operation at FA Distribuidora.

| Step | Owner | Time | Notes |
|---|---|---|---|
| 2.1 | Tag `vMVP-2026-05-19` | Anderson | 1 min | `git tag -a vMVP-2026-05-19 -m "MVP release"` then `git push --tags` |
| 2.2 | Deploy to Oracle Cloud per [`docs/RUNBOOK_DEPLOY.md`](RUNBOOK_DEPLOY.md) §"Deploy to Oracle Cloud" | Anderson | 30 min | Migrations, caches, Vite assets |
| 2.3 | Seed staging users (1 manager + 1 attendant + 1 deliverer) via `php artisan tinker` | Anderson | 5 min | Recipe at the bottom of [`README.md`](../README.md#quick-start--local-development) |
| 2.4 | Smoke-test the admin panel on `fa.andersonventurini.cloud` | Anderson | 15 min | Login, sale, receipt, delivery board, dashboard |
| 2.5 | Train the FA Distribuidora manager + 1–2 attendants on-site | Anderson + Manager | 1 day | Agenda in [`docs/RUNBOOK_DEPLOY.md`](RUNBOOK_DEPLOY.md) §"Training (Phase G.3)" |
| 2.6 | Run 10 days of **parallel usage** (notebook + system) | Manager | 10 days | Reconcile daily totals; file `docs/POSTMORTEM_<date>.md` for any divergence |
| 2.7 | Switch PRD status to **"Production v1.0"** and annotate go-live date | Anderson | 5 min | Last line of `prd-fa-distribuidora.md` §10 milestones |

**Blockers**: none — every code dependency is in place.

---

## Track 3 — M6 Google integrations live (F15 + F16)

**Goal**: daily Drive backup + 15-min Contacts sync running unattended.

| Step | Owner | Time | Notes |
|---|---|---|---|
| 3.1 | Create the dedicated FA Google account (`fa.distribuidora.sistema@gmail.com`) | Anderson | 10 min | Skip if already provisioned |
| 3.2 | Generate the Gmail SMTP **app password** for that account | Anderson | 5 min | Stored in `.env::MAIL_PASSWORD` |
| 3.3 | Follow [`docs/RUNBOOK_GOOGLE.md`](RUNBOOK_GOOGLE.md) steps 1–7 end-to-end | Anderson | 30 min | Google Cloud project, OAuth grant, env vars, schedule, verification |
| 3.4 | Install `composer require google/apiclient google/apiclient-services` on the host (verify ARM64 wheels) | Anderson | 10 min | Listed in `docs/RUNBOOK_GOOGLE.md` step 5 |
| 3.5 | Wire the real `google/apiclient` client into `GoogleDriveUploader::callDrive()` and `GoogleContactsSync::callPeopleApi()` / `pushToPeopleApi()` seams | Anderson | ~2 h | Tests use the seam — swap-in is non-breaking |
| 3.6 | Add the schedule lines to `routes/console.php` | Anderson | 5 min | Snippet ready in `docs/RUNBOOK_GOOGLE.md` step 6 |
| 3.7 | Confirm: first backup lands in Drive; first contacts pull creates rows on staging | Anderson | 10 min | Settings page surfaces `BackupRun::latestRun()` for the green check |

**Blockers**: needs the FA Google account credentials in the maintainer's hands.

---

## Track 4 — M8 WhatsApp Conversational Inbox (F18 · Phase H)

**Goal**: cliente manda mensagem no WhatsApp → atendente lê dentro do sistema, responde, e clica "Iniciar venda" sem trocar de aplicativo. Novos contatos viram cadastros em um clique.

Scope decisions locked 2026-05-19 (see PRD §F18 and IMPLEMENTATION_PLAN §"Phase H"):

- **Self-hosted Evolution API** alongside the FA Docker Compose (no SaaS).
- **Manager + attendant** only (deliverer blocked).
- **Match by normalized phone** against `customer_phones.number`; unmatched conversations expose a "Cadastrar novo cliente" inline action.
- **"Iniciar venda"** button in the conversation panel opens `CreateSale` with `customer_id` + `type=delivery` + `address_id=primary` pre-filled.
- **Text-only in MVP** (media in F18.1).
- **Operator-initiated only** — no auto-reply, no scheduled broadcasts. Minimizes WhatsApp ban risk on the existing FA number.

| Step | Owner | Effort | Notes |
|---|---|---|---|
| 4.1 | Provision the WhatsApp number for system use (one-time QR-scan after H.1 lands) | Anderson + Manager | 10 min | FA's existing number; minimize programmatic use |
| 4.2 | Implementation Phase H (steps H.1–H.11) | Anderson | ~2.5 weeks | See [`docs/IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md#phase-h--whatsapp-conversational-inbox-via-evolution-api-m8--new-scope) |
| 4.3 | Smoke-test on staging: send/receive at least one round-trip; create one customer from an unmatched conversation; open one "Iniciar venda" | Anderson + Manager | 1 h | Document anomalies in `docs/POSTMORTEM_F18.md` |
| 4.4 | Train manager + attendants on the new inbox (extension of Track 2.5) | Anderson + Manager | 1 h | Quick-reference card |
| 4.5 | Bump PRD to v1.8.0 marking F18 acceptance criteria checked | Anderson | 5 min | Final closure |

**Blockers**: Phase H **must wait for M7 cut-over** so the integration ships against production data, not a half-seeded staging.

**Out of MVP (deferred to F18.1)**: bidirectional media, templated quick replies, recommendation chips based on purchase history, Reverb-broadcast push.

**Risk + mitigation**: WhatsApp may ban the FA number for non-official API usage. Mitigations baked into Phase H: operator-initiated only, low volume (≤ 50 msg/day expected), and the `WhatsAppGateway` interface keeps a clean migration path to the official Meta Business API in a future major.

---

## Track 5 — P1 polish (close out the ⚠️ items in PRD §4)

✅ **Shipped 2026-05-29 on `feat/polish-p1`** (spec + plan in [`docs/superpowers/`](superpowers/); design = single front; +47 tests, suite 207 → 254). Items 5.1–5.9 done; **5.10 deferred** to the F16 OAuth grant.

| # | Feature | Status | Delivered as |
|---|---|---|---|
| 5.1 | **F10** | ✅ Already shipped | `has_manual_fee_override` Toggle in `CustomerForm` (regression test added) |
| 5.2 | **F10** | ✅ Done | `ViewCustomer` page + `CustomerInfolist` (distance, fees, source, `fees_calculated_at`) |
| 5.3 | **F11** | ✅ Done | District + status filters on `DeliveryBoard` |
| 5.4 | **F12** | ✅ Done | `ExportAction` + `SaleExporter` on `WaterSales` + `GeneralSales` (openspout, no new dep) |
| 5.5 | **F13** | ✅ Done | Custom `Dashboard` page (`HasFiltersForm`) → widgets read range via `InteractsWithPageFilters` |
| 5.6 | **F13** | ✅ Done | `TopCustomersTable` + `MonthlyEvolutionChart` widgets |
| 5.7 | **F13** | ✅ Done | Dashboard PDF export (`barryvdh/laravel-dompdf`, manager-gated route, brand Blade) |
| 5.8 | **F08** | ✅ Done | Period + `variant_id` filters + period-total `Sum` on `SalesRelationManager` |
| 5.9 | **F09** | ✅ Done | Manager-gated `delivery_fee`/`building_fee` + `audit_logs` via `Sale::updating` |
| 5.10 | **F16** | ⛔ Deferred | "Initial import preview" — needs the F16 OAuth grant (Track 3 / M6) to verify end-to-end |

**Blockers**: none — only 5.10 remains, gated on the Google OAuth grant.

---

## Track 6 — Hardening for first-month traffic

**Goal**: protect against the obvious foot-guns once real users are typing.

| # | Step | Effort | Notes |
|---|---|---|---|
| 6.1 | Audit-log UI viewer | M | The `audit_logs` table is populated (Phase C); a manager-only `AuditLogResource` lists entries by event/user/date |
| 6.2 | Soft-delete UI for customers + sales | S | Both models already `SoftDeletes`; expose a `TrashedFilter` + `RestoreAction` on each table |
| 6.3 | Manager-only "force-cancel" on already-confirmed sales older than 24 h | S | Otherwise attendants reverse stock with no friction |
| 6.4 | Backup retention policy: monthly archive on top of the 30-day rolling | S | Open question Q17 in the PRD — resolved when the manager + Anderson agree |
| 6.5 | Rate-limit Nominatim per address — current code already caches per-query but a bulk recompute on 200+ customers could still rate-limit | S | Queue the geocode + spread the calls; current implementation is fine for ≤ 50 customers |

**Blockers**: best done **after** the first 30 days of production data exists — let real-world usage drive the priorities.

---

## Track 7 — Post-MVP P1 features

| Feature | Effort | When |
|---|---|---|
| F17 Purchase forecasting | M | Once 60 days of sales data exists on production |
| Detailed audit-log surface | S | Track 6.1 covers this |
| Mobile-first deliverer dashboard (PWA) | M | Current DeliveryBoard is mobile-friendly but not installable |
| Thermal-printer ESC/POS driver | M | A5/80mm HTML works today; the actual USB driver is hardware-shop work |
| First-class supplier registry | S | Today supplier is just a string on Cargo; promote to its own model |
| F18.1 media (photo + audio) in WhatsApp | M | After F18 ships and real conversations show what's missing |
| F18.1 templated quick replies | S | Same — driven by real conversation patterns |

**Blockers**: only worth doing once real production data exists.

---

## Track 8 — Documentation maintenance (continuous)

The PRD and the plan are the **operational truth** for the project. Keep them in sync:

- **Every feature commit** ticks a checkbox in PRD §4 — the brand-compliance gate is the only one the test enforces; everything else is a discipline check at code review.
- **Every milestone shift** updates the table in PRD §10 + the IMPLEMENTATION_PLAN change log.
- **Every deviation** lands in the *Deviations* table near the top of the PRD (precedent: F14's hard-coded catalog seeder).
- **Every external dependency** (a new API, a new env var, a new manual step) lands in the matching runbook before it's merged.

A 5-minute discipline that keeps the project legible for the next person — human or AI — who opens the repo.

---

## Quick-glance summary

| Status | What's left |
|---|---|
| ✅ Code (MVP F01–F16) | Nothing — every PRD MVP feature ships in code |
| 🟡 Operational (M6 + M7) | Push commits · OAuth grant · on-site training · go-live |
| ⛔ New scope (M8) | **F18 WhatsApp inbox (Track 4)** — starts after M7 go-live |
| 🟡 Polish | ~10 small P1 items in PRD §4 (Track 5) |
| 🟡 Hardening | 5 defensive items (Track 6) — best after first 30 production days |
| ⛔ Post-MVP | Post-MVP features (Track 7) — depend on production data |

**The single highest-value next action**: push the pending commits (Track 1, 1 minute) and start the M7 deploy (Track 2). Everything else can wait without losing momentum.
