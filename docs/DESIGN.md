# Design System — FA Distribuidora

> **Canonical visual reference**: [`FA Distribuidora · Print.pdf`](../FA%20Distribuidora%20%C2%B7%20Print.pdf) (brand manual, 14 pages)
> **Status**: Locked — every UI change in this repository must conform.
> **Owner**: Anderson de Oliveira Venturini
> **Last updated**: 2026-05-18

This document is the **textual source-of-truth** distilled from the printable brand manual. The PDF stays authoritative for visual judgement (logo construction, mockups, photography direction); this file stays authoritative for **code**: which token to import, which Blade component to use, what is allowed and what is not.

PRD [NFR-01 — Brand Compliance](./prd/prd-fa-distribuidora.md#nfr-01--brand-compliance) makes adherence mandatory. Any PR that hard-codes a hex value, font family or wave shape outside the files listed in [§Source of truth](#source-of-truth) is rejected on review.

---

## 1. Identity at a glance

FA Distribuidora is a neighborhood beverage depot in Campinas-SP. The 2026 rebrand drops alcoholic beverages and cigarettes from the spotlight and concentrates on **water · beverages · charcoal**. The visual system was redesigned to feel **clean, trustworthy, family-run** — not the gradient-blue cliché of generic water brands.

The four pillars of the identity:

1. **Redesigned logo** — FA monogram inside a circle, with a water droplet over the F. Geometric, hand-drawn-feeling typography for originality at any size (sticker → sign).
2. **Hydrated palette** — Deep Blue + Crystal Blue + Solar Yellow. Conveys freshness, trust and energy without the light-blue-gradient cliché.
3. **Weighted typography** — *Archivo Black* for titles and the phone number (high contrast, fast reading). *Inter* for practical information (address, hours).
4. **No third-party brands** — Product photography uses neutral placeholders; replace with real photos of the depot's own stock. Avoids trademark misuse on third-party drink brands.

Two recurring graphic devices unify everything:

- **Wave system** — A water wave as a recurring divider; it cuts sections, animates posts, forms stripes on the storefront. The signature mark that ties printed and digital pieces together.
- **Strong phone CTA** — "Disk Entregas" is the hero: the number is **big, set in a yellow block, with a WhatsApp seal**. Anyone seeing a flyer knows in one beat how to order.

---

## 2. Color tokens

| Token | Name (pt-BR) | Hex | Role | CSS variable |
|---|---|---|---|---|
| `--fa-azul-profundo` | Azul Profundo | `#0B3D91` | **Primary** — logo, headings, primary action backgrounds | `var(--fa-azul-profundo)` |
| `--fa-azul-cristal` | Azul Cristal | `#3FA9F5` | **Secondary** — info accents, water-related accents, link hover | `var(--fa-azul-cristal)` |
| `--fa-amarelo-solar` | Amarelo Solar | `#F7C948` | **Accent** — phone CTA block, "warning" status, highlight ticks | `var(--fa-amarelo-solar)` |
| `--fa-branco-mineral` | Branco Mineral | `#FAFBFD` | **Background** — page background, card body, neutral surface | `var(--fa-branco-mineral)` |
| `--fa-tinta-noite` | Tinta Noite | `#0E1730` | **Text** — body copy, headings on light surfaces | `var(--fa-tinta-noite)` |

### Usage rules

- **Headings + phone CTA** always on Azul Profundo or Tinta Noite. Never gray.
- **Disk Entregas block** must be filled `--fa-amarelo-solar` with the phone number in Azul Profundo, *Archivo Black*. Add a WhatsApp ✓ glyph next to the mobile number.
- **Backgrounds**: prefer `--fa-branco-mineral` over `#FFFFFF`. The slight cool tint is what separates the system from a generic Bootstrap layout.
- **Status colors** (red error, green success) are inherited from Filament defaults — the brand palette never carries error semantics. Solar Yellow is allowed as `warning` but reserved for genuine attention states, not decoration.
- **Gradients**: only the soft brand-tinted radial glow used on the login layout is allowed. No blue-to-cyan water-cliché gradients.

---

## 3. Typography tokens

| Token | Family | Weights | Use | CSS variable |
|---|---|---|---|---|
| Display | **Archivo Black** | 400 (only weight available) | Logo lockup, h1/h2, phone CTA, big numbers (totals on receipt) | `var(--font-display)` |
| Text | **Inter** | 400 / 600 / 800 | Body copy, table cells, form labels, addresses, hours | `var(--font-sans)` |

Both fonts are loaded from `https://fonts.bunny.net/` in `theme.css` — no Google Fonts to keep LGPD-friendly. Self-hosting under `public/fonts/` is acceptable when needed.

### Type ramp

| Use | Family | Weight | Size (px) | Line-height | Letter-spacing |
|---|---|---|---|---|---|
| Logo lockup | Display | 400 | 28 / 32 | 1.0 | -0.01em |
| H1 page title | Display | 400 | 28 | 1.1 | -0.01em |
| H2 section heading | Display | 400 | 20 | 1.2 | 0 |
| H3 card heading | Sans | 800 | 16 | 1.3 | 0 |
| Body | Sans | 400 | 14 | 1.5 | 0 |
| Caption / meta | Sans | 600 | 12 | 1.4 | 0.04em |
| Tag / chip | Sans | 800 | 11 | 1.0 | 0.18em (UPPERCASE) |
| Phone CTA number | Display | 400 | 36+ | 1.0 | -0.01em |

The Tag / chip 0.18em UPPERCASE pattern is what the brand manual uses for `ÁGUA · BEBIDAS · CARVÃO` and the address footer.

---

## 4. Logo & lockups

Three SVG files live under `public/images/`:

| File | Use |
|---|---|
| [`fa-logo.svg`](../public/images/fa-logo.svg) | Full lockup (mark + wordmark + tag). Default in the Filament header. Min width 120 px. |
| [`fa-logo-mark.svg`](../public/images/fa-logo-mark.svg) | Mark only (circle + FA + drop). Use when there is no room for the wordmark — favicon, mobile drawer header, receipt watermark. |
| [`favicon.svg`](../public/favicon.svg) | Browser favicon. Square mark variant. |

### Construction rules

- **Mark anatomy**: white-filled circle, Azul Profundo stroke, FA monogram in Azul Profundo *Archivo Black*, water-droplet in Amarelo Solar above the F, with a soft Azul Cristal shadow ellipse at the bottom.
- **Clear-space**: at least one droplet diameter all around the mark. Don't place text or other marks inside that buffer.
- **Minimum size**: full lockup ≥ 96 px wide; mark ≥ 24 px wide.
- **Don'ts**: don't recolor the mark, don't substitute fonts, don't apply gradients on the FA letters, don't rotate. The droplet **always points up**.

---

## 5. Wave system (graphic device)

The wave divider is the brand's signature graphic. It is used to:

- Separate hero sections from body content (admin dashboard, login layout)
- Top the printable receipt header and the depot contact card
- Decorate stripes on the storefront sign (out of scope for the web app, but referenced for consistency)

### Implementation

Lives as a Blade component at [`resources/views/components/fa/wave-divider.blade.php`](../resources/views/components/fa/wave-divider.blade.php).

Usage:

```blade
<x-fa.wave-divider />                          {{-- default: Azul Profundo, 16 px tall --}}
<x-fa.wave-divider color="amarelo-solar" />    {{-- Solar Yellow band --}}
<x-fa.wave-divider color="azul-cristal" inverted />
```

Accepts `color` (one of `azul-profundo` / `azul-cristal` / `amarelo-solar`), `inverted` (flip vertically), and `class` (extra Tailwind classes). Internally renders an inline SVG so it inherits the surrounding font color when needed.

**Don't reimplement the wave** in another file. There is exactly one source SVG path so the shape stays consistent across surfaces.

---

## 6. Phone CTA pattern (Disk Entregas)

The hero element of the brand. Used on the admin dashboard footer card, the printed receipt, and any future external-facing surface.

| Field | Value (from the brand manual) |
|---|---|
| Heading | `DISK ENTREGAS` — Sans 800, 0.18em tracking, UPPERCASE |
| Primary number | `(19) 3326-7690` — fixed line |
| Secondary number | `(19) 98177-8284` — mobile, with WhatsApp ✓ glyph |
| Background | `--fa-amarelo-solar` |
| Number color | `--fa-azul-profundo`, Display, ≥ 36 px |
| Container | Rounded 8 px, padded 16 px / 24 px, no border |

Implementation lives at [`resources/views/components/fa/disk-entregas.blade.php`](../resources/views/components/fa/disk-entregas.blade.php) (added in Phase 0 of [`IMPLEMENTATION_PLAN.md`](./IMPLEMENTATION_PLAN.md#phase-0--brand-retrofit-cross-cutting)). Use it whenever the phone numbers appear on a customer-facing surface (receipt, future portal); skip on internal admin pages unless the manager explicitly asks for it.

---

## 7. Tagline & store identifiers

These literals live in `config/brand.php` so they can be reused without copy-paste drift.

| Token | Value |
|---|---|
| `name` | FA Distribuidora |
| `tagline` | ÁGUA · BEBIDAS · CARVÃO |
| `address` | Av. Transamazônica, 1197 · Jardim Garcia · Campinas–SP |
| `hours` | Seg–Sáb 08–18h · Dom/feriado 08–12h |
| `phone_landline` | (19) 3326-7690 |
| `phone_mobile` | (19) 98177-8284 |
| `whatsapp` | (19) 98177-8284 |

Access them via `config('brand.name')`, `config('brand.tagline')`, etc. Don't hard-code these strings in a Blade template; pull from config so a future change reflects everywhere.

---

## 8. Component checklist for new features

Every new Filament resource, page, widget or Blade template ships with these checks during code review:

- [ ] No hex value outside [`theme.css`](../resources/css/filament/admin/theme.css) or this document.
- [ ] No `font-family` declaration outside `theme.css`; consume `var(--font-display)` / `var(--font-sans)`.
- [ ] Headings use the Display token (Archivo Black) via the existing `.fi-*-heading` selectors or `font-display` Tailwind utility class.
- [ ] Backgrounds use `var(--fa-branco-mineral)` (never `#FFFFFF` directly) unless the surface is on top of a colored band, in which case white is allowed.
- [ ] Phone numbers anywhere customer-visible reuse the `<x-fa.disk-entregas />` component.
- [ ] Section headers — when a visual break is needed — use `<x-fa.wave-divider />` between sections, not a horizontal rule.
- [ ] Logo: full lockup in the panel topbar, mark-only on mobile drawer and on the receipt watermark.

---

## 9. Source of truth

These are the only files allowed to declare brand values:

| File | Owns |
|---|---|
| [`docs/DESIGN.md`](./DESIGN.md) (this file) | Tokens table, usage rules, prose |
| [`config/brand.php`](../config/brand.php) | String literals (name, tagline, address, hours, phones) |
| [`resources/css/filament/admin/theme.css`](../resources/css/filament/admin/theme.css) | CSS custom properties, Tailwind theme extensions, `@source` directives |
| [`app/Providers/Filament/AdminPanelProvider.php`](../app/Providers/Filament/AdminPanelProvider.php) | Filament `Color::hex(…)` bindings — must mirror the values declared in `theme.css` (synchronized by comment) |
| [`public/images/fa-logo*.svg`](../public/images/) | Logo + mark SVGs |
| [`resources/views/components/fa/*.blade.php`](../resources/views/components/fa/) | Reusable brand components |

Every other file in the repo **consumes** these — it never **defines** them.

---

## 10. Reference: brand manual table of contents

For quick lookup when working from the PDF:

| Page | Section | Use this file when… |
|---|---|---|
| 01 | Marca · Logo & Lockups | Working on the topbar, the receipt header, or any context where the logo appears |
| 02 | Marca · Cor & Tipografia | Picking a color or a font weight |
| 03 | Marca · Notas de Design | Onboarding a new dev; understanding the *why* behind the system |
| 04 | Impressos · Flyer A5 | Designing the future external flyer or print catalog (out of MVP scope) |
| 05 | Impressos · Cartão | Reference for the printable receipt copy layout |
| 06 | Impressos · Adesivo | Out of MVP scope |
| 07 | Impressos · Fachada | Out of MVP scope |
| 08 | Digital · Instagram Post | Out of MVP scope (marketing) |
| 09 | Digital · Story | Out of MVP scope (marketing) |
| 10–14 | Visual mockups | Visual reference only — illustrative pieces |

---

## Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2026-05-18 | Anderson de Oliveira Venturini | Initial design system distilled from `FA Distribuidora · Print.pdf` (v1, 2026-05-09). Token tables, source-of-truth map, component checklist |
