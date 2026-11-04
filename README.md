# Ananta

**AI-ready nursery discovery and commerce, built PHP-first.**

Ananta is the project and repository name for a nursery platform evolving from a single-brand storefront toward a multi-nursery retail and wholesale commerce network. The current codebase is a **Sanjay Nursery v1.7.0 staging application**; its supplier SaaS, verified multi-nursery marketplace, cross-supplier RFQs and WhatsApp integrations are **roadmap items, not features already delivered**.

> **Runtime boundary:** The included application is file-backed and staging-only. `src/bootstrap.php` rejects `APP_ENV=production`; do not present this checkout as a production-ready multi-tenant marketplace.

## What works today (Ananta storefront + v1.7.0 foundation)

- Ananta-branded premium botanical storefront, responsive mobile typography, curated plant catalogue, collections, search, discovery and Plant Finder.
- **File-first admin-managed nursery offers:** `/nurseries`, verified supplier storefronts, `/product/{slug}#buy-from`, freshness-aware availability, WhatsApp enquiry links and a public seller-offer API. Independent nursery offers are **enquiry-only** and cannot enter the founding nursery cart.
- Supplier/offer administration at `/admin/marketplace` for trusted platform admins. This is **not yet supplier self-service or hardened multi-tenant SaaS**.
- Admin interfaces for catalogue, CMS, media, themes, commerce/variant workflows and operations.
- AI & Search engine with **Scan Only**, administrator-reviewed suggestions, constrained **Safe Auto**, catalogue/SEO analysis, comparison/listicle publishing and rollback.
- Human-approval-gated AI discovery and agent-facing catalogue feeds.
- Browser-managed setup, bounded web job processing, System Health and Error Monitor.

**Commerce truth:** Enquiry-first catalogue items are not proof of live price or inventory. Do not invent SKUs, prices, stock, delivery guarantees, reviews, botanical risk or toxicity facts with AI. Publish verified offers and media only.

## Roadmap: one authoritative blueprint

The combined [Ananta AI-native commerce blueprint](docs/ANANTA_BLUEPRINT.md) contains:

1. The retail/wholesale nursery marketplace product vision and customer/supplier journeys.
2. The Cloudways-compatible, single-writer PHP **file-first pilot architecture**, security and SQL migration gates.
3. The P0–P5 implementation backlog, acceptance tests, tenant isolation and RFQ/WhatsApp delivery constraints.

**Planned ≠ implemented.** Complete P0 containment, data isolation, restore testing and permission checks before advertising supplier SaaS capabilities. Cross-seller settlement and automated inventory reservations need transactional persistence.

## Installation (staging / Cloudways)

1. Upload or deploy this repository to a PHP 8.4-compatible staging application.
2. Configure the website document root as **`public/`**, never the repository root. Never expose `src/`, `config/` or `storage/`.
3. Visit `/admin/setup` and complete first-administrator setup immediately.
4. Sign in at `/admin/login`; run **Admin → System Health** and inspect Error Monitor.
5. Open **Admin → AI & Search → Settings**. Keep **Scan Only** for provider-free audits; configure an OpenAI-compatible HTTPS provider and encrypted key only for Review or Safe Auto.
6. Test the provider/model (if used), set request/token/job limits, run initial catalogue/SEO scans and review changes before publication.
7. Confirm routes, assets, feeds, themes, consent gating, release checks and backups before any external pilot access.

Normal browser setup does not require SSH, Node.js, a queue daemon or a SQL server. The included sample Nginx file is **illustrative**; use the actual Cloudways web-root and PHP-FPM settings rather than copying its version-specific example path unchanged.

## Operational checks

- Public entry point: `public/index.php`; local development router: `router.php`.
- Release version: `VERSION` (currently **1.7.0**).
- Health and errors: administrator System Health / Error Monitor.
- AI controls: **Scan Only → Review → Safe Auto**; protected price, stock, availability, safety and review fields remain outside automatic AI writes.
- Developer validation: `bash scripts/full_regression.sh` (requires local PHP, and does not replace production security or load testing).
- Deployment verification: HTTP 200 for `/assets/css/app.css`, `/assets/js/app.js`, `/.well-known/agent-capabilities.json` and `/api/openapi.json`; validate `/feeds/ai-products.json`, `/feeds/agent-products.json`, `/feeds/ai-content.json` and `/sitemap.xml` where relevant.
- Publishing policy: require original nursery evidence, a responsible reviewer, credible citations for factual claims and rights-cleared photographs; avoid mass-generated near-duplicate pages.

## Project layout

| Path | Responsibility |
| --- | --- |
| `public/` | HTTP entry point and public assets; only web-exposed folder |
| `src/` | PHP application and modules |
| `templates/` | Storefront and administrator views |
| `config/` | PHP application configuration |
| `storage/` | File-backed catalogue, snapshots and deployment runtime state |
| `tests/`, `scripts/` | Regression, diagnostics and maintenance utilities |
| `python/` | Optional media/visual tooling; not a required web runtime |
| `resources/` | Import templates and reference CSV datasets |
| `docs/ANANTA_BLUEPRINT.md` | Canonical future-product/architecture/backlog document |

Reusable source files: [live-variant import template](resources/LIVE_VARIANT_IMPORT_TEMPLATE.csv), [redirect migration template](resources/MIGRATION_REDIRECT_TEMPLATE.csv), [catalogue expansion source](resources/CATALOGUE_EXPANSION_LIST_v1.6.2.csv) and [intelligence event dictionary](resources/INTELLIGENCE_EVENT_DICTIONARY_v1.5.0.csv).

## Security and repository hygiene

**Important: historical operational data needs a separate remediation.** The public Git history includes tracked files under `storage/private-domain/` and `storage/journals/`, including a setup key. A new `.gitignore` blocks accidental addition of new storage artifacts, but **does not untrack existing files** or remove them from Git history. Verify whether any values or customer data were real, revoke/rotate exposed secrets, audit access, and plan safe replacement of tracked runtime files with a backed-up deployment migration. Never run a cleanup that deletes live storage during a Cloudways Git pull.

The earlier documentation cleanup removed obsolete audit outputs; historical versions remain accessible through Git history. The newer storefront/marketplace pilot does change public PHP templates, CSS and routes, but **does not** add a database, payment gateway, or independent supplier login.

## Branding note

**Ananta** is now the public-facing storefront identity. **Sanjay Nursery** remains explicitly the founding nursery and the legacy application's internal runtime/configuration/session identity for compatibility. Completing the internal rename is a separate staged migration; do not confuse branding with multi-tenant production readiness.
