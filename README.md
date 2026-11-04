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

## Release B1 — Supplier branches and inventory evidence (staging)

Verified nursery members can manage up to 25 private branch records from `/supplier#branches`, each with branch name, city, address and optional PIN. Owner and manager roles can submit branches; inventory/viewer roles can read but cannot edit them. The platform verifies, rejects or suspends each branch separately at `/admin/marketplace#branch-verification` with a private evidence reference. Editing a branch resets review to pending, immediately hiding its public offers until approval returns.

Each new supplier offer is assigned to **one branch** and can be submitted as a draft, with canonical plant reference, specification, indicative price and stock report. Platform administrators must approve the branch **and** publish the offer. Confirmed stock still requires explicit private evidence and expires after seven days; supplier reports cannot self-verify stock. The public API exposes only approved branch name/city, not exact site address, PIN or review evidence.

Legacy offers without a branch remain supported so preexisting curated listings do not disappear. This is still **not** real-time reserved inventory, multi-seller checkout, fully verified business identity, or a complete supplier catalogue importer. No database is required for this staging pilot.

Local security test: `php tests/supplier_branches.php`. Deployment acceptance additionally requires `bash scripts/full_regression.sh`, DigiOps release packaging, and manual Cloudways browser verification.

## Release A supplier security pilot

The planned tenant-security implementation adds invitation-only supplier accounts at `/supplier/login`, a tenant-scoped workspace at `/supplier`, and one-time platform-issued invitations in `/admin/marketplace`. Supplier staff can propose drafts and report stock but **cannot** publish, verify stock, or enter multi-seller checkout.

Treat this as a limited staging pilot: no public self-registration, no external supplier payments, and no acceptance of private data from real suppliers until the P0 security gates, encrypted backups, real-device QA and credential exposure review are complete. The current Git tree excludes mutable operational records; **old Git commits may still contain them**, so affected live secrets must be rotated. See [the consolidated blueprint](docs/ANANTA_BLUEPRINT.md#part-v-release-a--tenant-identity-and-repository-containment-implementation).

## DigiOps release for Cloudways /ananta/

DigiOps is configured to deploy **`indigiti/Ananta` → `main`** to **`/ananta/`**, with public files at `public_html/ananta/` and private PHP code at `private_html/ananta/`. It searches GitHub Actions for a **successful run** containing the artifact named **`digiops-release`**. This is an artifact name, **not a Git branch**.

The workflow [`.github/workflows/digiops-release.yml`](.github/workflows/digiops-release.yml) runs PHP 8.4 regressions, creates the split payload with `php tools/package-digiops-release.php`, validates it, performs an isolated /ananta/ layout smoke test, and publishes the artifact only if every step succeeds. Use DigiOps **Check update**, verify the run/commit/artifact, and only then click **Deploy**. Opening DigiOps does not automatically query GitHub.

Package contract:

| Artifact directory | DigiOps target | Content |
| --- | --- | --- |
| `release/public/` | `public_html/ananta/` | Thin `index.php`, `/ananta/` rewrite and static assets |
| `release/private/` | `private_html/ananta/` | PHP application, templates, config, resources and immutable first-install seed |
| `release/RELEASE.json` | Release metadata | Source commit, branch, CI run and target paths |

**Important:** The public PHP entry point loads `private_html/ananta/public/index.php` from the same Cloudways application home. This relies on the sibling `public_html/` and `private_html/` layout shown in DigiOps. The first installation copies a small allowlist of committed **public-domain catalogue/configuration seed JSON** into missing private `storage/` files; later releases **never** ship or overwrite live storage, user records, audit journals, admin keys or supplier offers. Catalogue updates after first install require a separate reviewed data migration, not an artifact overwrite.

New uploaded product WebP files are stored persistently under `private_html/ananta/storage/private-domain/media/products/` and served through the application only when appropriately authorized or approved. Unlike the legacy release directory, these files are not overwritten during a normal DigiOps code deployment. A backup/restore drill is still required.

This remains a **staging-only/file-first release**. `APP_ENV=production` is intentionally blocked. Before exposing real customers or accepting real payments, complete P0 tenant isolation, data/secret remediation, backup restore tests and a persistence design for media uploads: files written into the replaceable public assets tree will not survive an atomic public-directory swap unless stored and served from persistent private storage.

Local validation: `php tools/package-digiops-release.php && php tools/check-digiops-release.php && bash tools/smoke-digiops-layout.sh`. Do not configure both Cloudways Git Pull and DigiOps to write the same `/ananta/` paths.

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
