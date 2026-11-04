# Ananta

**AI-ready nursery discovery and commerce, built PHP-first.**

Ananta is the project and repository name for a nursery platform evolving from a single-brand storefront toward a multi-nursery retail and wholesale commerce network. The current codebase is a **Sanjay Nursery v1.7.0 staging application**; its transaction-ready multi-seller marketplace, fully autonomous AI BOQ procurement, verified production WhatsApp messaging, CRM automations and settlement remain **roadmap items**. B3–B6 add staging-only supplier onboarding, multi-nursery RFQs, buyer-reviewed BOQs, optional AI-assisted text intake and non-binding supplier fulfilment planning.

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

## B8.3 — Tenant CRM, assignments, follow-ups and RFQ reminders

- Encrypted CRM lead records with stage, tags, notes, active owner/manager assignment, optimistic-version concurrency and tenant-private tasks.
- Nursery screens: \`/supplier/connect/report\` and \`/supplier/connect/inbox/{uuid}/crm\`.
- Optional CLI sourcing reconciler: \`php src/connect_crm_worker_cli.php\`. Requires \`ANANTA_CONNECT_RFQ_AUTOMATION=1\`, an explicitly approved \`ANANTA_WA_RFQ_TEMPLATE\`, B8.2 outbound activation and buyer-recorded \`rfq_updates\` opt-in.
- Persisted quote-version notification keys guard replay. Buyer consent to share their contact is **not** WhatsApp opt-in; both are necessary. Template variables are not supported in this staging path.
- No external sends or live Meta receipt claims without actual account integration and an end-to-end pilot.

## B8.2 — Meta webhook, durable outbox and tenant CRM (staging)

B8.2 adds an authenticated WhatsApp Business Platform webhook, encrypted tenant-private inbox, and durable outbound queue.

Webhook URL: https://YOUR-HOST/ananta/webhooks/meta/whatsapp/PHONE_NUMBER_ID.
Meta verification uses the registered verification token. Signed POST payloads use the exact raw-body X-Hub-Signature-256 and must match the registered Meta WABA and Phone Number ID.
Duplicates are ignored; webhooks are acknowledged only after encrypted persistence. Signed STOP/UNSUBSCRIBE text blocks outbound. No incoming message grants marketing opt-in.

Platform UI: /admin/connect/inbox. Nursery UI: /supplier/connect/inbox. Only nursery owners and managers may view sensitive inbox content or enqueue approved templates.
Sending requires administrator sender activation, ANANTA_CONNECT_ALLOW_SEND=1, purpose-specific opt-in and template:language allowlisting via ANANTA_WA_APPROVED_TEMPLATES. It is disabled by default.
Run the worker with Cloudways CLI cron: php /path/to/private_html/ananta/src/connect_worker_cli.php 5 . This worker and the encrypted runtime database are separate from the public site.
Provider acceptance is recorded as submitted, while sent/delivered/read require authentic provider webhooks. Unknown status receipts are retained for reconciliation. Timeout, 429, server-error and expired leases require manual review to prevent duplicate sends.
Keep the pilot small; test real Meta credentials, approved templates, log retention, encrypted backups and restore before enabling outbound traffic.
B8.3 follow-up assignments and automatic RFQ notifications remain pending. No production delivery is claimed.
## B8.1 — Ananta Connect + Discover: safe integration foundations (staging)

### Ananta Connect

- **No messages are sent by this release.** The provider registry, consent evidence and webhook signature verifier are infrastructure for B8.2, not a functional Meta inbox, delivery receipt system or broadcast tool.
- Platform admins can register a WhatsApp Business Account ID and Meta Phone Number ID mapped to exactly one `platform` or verified supplier tenant from `/admin/connect`. Access token, Meta app secret and webhook challenge verification tokens must be placed in Cloudways environment variables using `ANANTA_WA_TOKEN_*`, `ANANTA_WA_SECRET_*` and `ANANTA_WA_VERIFY_*` references. The UI neither stores nor echoes secret values.
- Connections default to disabled and have no outbound transport. Environment secret readiness is labelled local configuration only—not a successful Meta Business registration or webhook connection.
- Platform staff and verified nursery owners/managers can record purpose-specific phone consent or opt-outs with a documentary reference. Encrypted at rest with `SANJAY_DATA_KEY`, hashed identifiers in audit logs, single-writer mutations, tenant isolation and capacity guards.
- The signature-verification helper rejects invalid `X-Hub-Signature-256` values, but no inbound webhook ingestion or outbound Graph API call is exposed until B8.2 is implemented and secured.

### Ananta Discover

- `/admin/discover/merchant` provides a local per-SKU eligibility dashboard with reason codes such as `enquiry_only`, `missing_price`, `merchant_disabled`, `zero_stock`, `pickup_only`, `unapproved_parent_image`, and `unverified_inventory`. A separate AI readiness summary audits published product metadata without guaranteeing AI citations.
- `/feeds/google-merchant.xml` uses one shared readiness filter and only includes checkout-ready founding-nursery products/SKUs with rights-cleared media. Supplier RFQ offers remain excluded. No Google Merchant Center account is connected or receiving submissions in B8.1.
- Variant feed links use crawlable `?variant=<SKU>` URLs rather than `#configure`, product pages preselect the SKU for price and cart, and `ProductGroup` schema references the same variant URL. Persistent approved media are supported in image eligibility checks.
- Merchant XML has well-formed headers, deterministic IDs, and truthful founding retailer branding. Shipping, returns, tax, seller-of-record, Merchant approval and external indexing must be confirmed independently before any feed submission.

### Acceptance and constraints

Run `php tests/b8_foundation.php` and `bash scripts/full_regression.sh`, then DigiOps packaging and the `/ananta/` split-layout smoke suite. Inspect both admin dashboards at phone/tablet/desktop resolutions on Cloudways. **B8.2 (signed inbound webhooks, durable outbound queue, CRM conversations, provider delivery receipts) is not part of B8.1.** This milestone does not connect Meta or submit products to Google.
## Release B6 — Supplier fulfilment readiness and buyer planning acknowledgement (staging)

This milestone adds a **non-binding purchasing readiness workflow** to B4 buyer-selected BOQ drafts. It does **not** legally issue or accept a purchase order, verify or reserve stock, invoice GST, collect payment, or trigger shipping.

- **Selected supplier only:** supplier owner/manager enters per-BOQ-line *supplier-reported* stock quantities (including zero for shortages), estimated dispatch days, freight INR estimate, delivery responsibility, tax-treatment declaration, and optional note or decline reason at `/supplier/enquiries/{rfq_uuid}`. Invited but unselected competitors and inventory/viewer staff cannot alter the draft.
- **Buyer review:** the owning buyer sees the current availability statement and commercial terms at `/account/sourcing/{rfq_uuid}`. They can request changes or **acknowledge for planning only**, requiring an explicit non-binding confirmation. A shortage prohibits planning acknowledgement.
- **Protected state:** `awaiting_supplier` → `supplier_proposed` → `buyer_changes_requested` → `supplier_proposed` → `buyer_acknowledged`; supplier may alternatively decline. The parent `buyer_approved_draft` record remains frozen and non-binding. All changes are atomic within the existing encrypted RFQ file, version-checked against stale submissions, and accompanied by a bounded append-only event record. No buyer internal IDs are exposed in the supplier view.
- **Important tax limit:** tax handling is a supplier statement (*included, extra, or unconfirmed*), **not** a GST rate calculation. Freight is an estimate. Neither should be treated as an invoice or binding total. Seller-reported available quantities are not physical allocations.
- **Regression gates:** `php tests/sourcing_readiness.php` validates tenant/role/buyer isolation, all-line stock reporting, insufficient-stock rejection, freight and delivery validation, stale versions, supplier change cycles, terminal acknowledgement/decline, immutable quote/draft and encrypted activity. Full regression and DigiOps split-layout smoke also apply.

**Operational before transaction launch:** two-nursery real-world pilot, explicit seller-of-record/GST contract design, supplier/KYC and shipment proof, stock reservation transaction guarantees (SQL), data retention and recovery, webhook idempotency, and separate payment-provider compliance.
## Release B5 — Excel and text-PDF BOQ ingestion with mandatory human verification

Ananta now offers an **optional document intake workflow** alongside the original strict CSV importer at `/sourcing/boq`. A buyer can upload XLSX (up to 1 MiB) or a text-based PDF (up to 3 MiB). XLSX is parsed using PHP ZIP + DOM/XML; the first worksheet must contain exactly `product_slug` (or `plant_name`), `quantity`, and `specification` columns. XLSX macros, formulas, embedded external objects, extra filled columns, pathological ZIP inflation and more than 30 rows are rejected. These restrictions are deliberate security boundaries, not full Excel-format support.

PDF processing requires the server's optional `pdftotext` executable and an existing selectable text layer. There is **no built-in OCR for scanned PDFs** and no guarantee that Cloudways exposes `pdftotext`. When absent or the PDF is image-only, the application refuses extraction and directs the buyer to XLSX/CSV. Text PDF tables may be split by a pipe, tabs or multiple spaces. The untrusted PDF is never executed.

If a text PDF is extractable and the existing AI Engine has an enabled, configured review provider, the buyer can **explicitly opt in** to send a capped excerpt of extracted PDF text to that provider for suggested rows. The model must provide verbatim source evidence per row. Missing evidence or malformed JSON fails the entire preview. No document, plant name, quantity, price or purchase order is accepted based solely on an AI reply. The third-party provider may process opted-in document text, subject to the provider's terms; using AI is not required to complete sourcing.

A buyer sees a session-bound 15-minute extraction preview with each original source row, confidence and the proposed catalogue match. Unmatched plant names have **no preselected match**; quantities/specifications can be corrected, and each row must be explicitly confirmed. Only then does Ananta create the existing B4 **reviewed BOQ draft** and present the separate verified-nursery selection/contact-sharing/consent step. No uploading or reviewing creates an RFQ, supplier quote, payment or order. The raw source document is never saved in application storage; a source hash, limited evidence excerpts and human verification flags are retained in the encrypted RFQ once consented and submitted.

**Cloudways prerequisites:** PHP 8.4 with `zip` and `dom`/`xml` for Excel, and an installed executable `pdftotext` for PDFs. The optional AI path requires the existing AI Engine's configured provider and appropriate buyer consent. The strict B4 CSV workflow remains available with no new runtime dependencies.

**Tests:** `php tests/sourcing_ingest.php`, full PHP regression, DigiOps privacy/package verification and Cloudways staging smoke. Live browser, optional PDF binary, upload limits, real-world XLSX variants and vendor-specific PDFs require acceptance before enabling production buyers.

## Release B4 — Reviewed multi-plant BOQs, quote revisions and non-binding PO drafts (staging)

The buyer can upload a UTF-8 CSV at `/sourcing/boq` with the exact columns `product_slug,quantity,specification`. The server validates a maximum of 30 unique canonical plant/grade lines and 128 KB per file. The preview stays in the buyer's session for 15 minutes; the buyer must review the matched plant names/quantities and explicitly select 1–5 verified, onboarded nurseries before submission. No PDFs, image OCR or LLM extraction is enabled in B4: hallucinated product/specification/quantity values are prevented by deterministic catalogue validation and human review. AI extraction remains a later opt-in module, never a silent source of commercial truth.

Invited nursery owners/managers respond to **every line** with a manually entered indicative unit price and lead time. A maximum of ten revisions is retained as separate snapshots with optimistic version checks; stale submissions fail. Suppliers cannot see competing vendors or their quote versions. A buyer may select an exact submitted version and approve a **non-binding, buyer-approved purchase-order draft**. Duplicate approvals are idempotent; other vendor awards and further quote changes are blocked.

The draft explicitly records that supplier acceptance, live stock, GST/taxes, freight, payment and binding legal terms are unconfirmed. No commercial purchase order is issued, emailed, dispatched, paid or settled; this is buyer quotation selection only. The original single-plant RFQ and founding nursery checkout remain intact. The pilot persists data through Ananta's authenticated encrypted file store with UUIDv7 identifiers, tenant authorization and single-writer locking.

Acceptance tests: `php tests/sourcing_boq.php`, `php tests/sourcing_boq_ui.php`, full PHP regression, DigiOps package checks and Cloudways split-path staging smoke. Production gates remain legal/tax verification, supplier document verification, backups and actual two-nursery pilot, followed by a measured SQL migration when capacity warrants.

## Release B3 — Multi-nursery RFQs and supplier sourcing inbox (staging)

**Buyer journey:** A signed-in buyer opens `/sourcing` (also linked from plant and nursery pages), selects a plant, quantity, specification, delivery region and up to five verified nurseries with active supplier accounts, then explicitly consents to sending the requirement. Optional separate consent controls sharing their account name, email and phone. Requests are kept in a single encrypted file-first record under `storage/private-domain/marketplace/sourcing-rfqs.json`; customer account details are not sent without consent. A buyer tracks their requests and compares indicative supplier quotations through `/account/sourcing`.

**Supplier journey:** Verified, invited nursery users open `/supplier/enquiries`, see only requests addressed to their own tenant and can return an indicative unit quote / lead time or decline. Owner/manager roles may quote; inventory/viewer roles have read-only access. A buyer can close requests to new quotes. Uninvited suppliers cannot access other supplier names, quotes, buyer contacts or RFQs.

**Admin:** The existing `/admin/marketplace` screen shows aggregate sourcing counts without publishing buyer details. The original TradeOS project/BOQ workflow remains independent.

**Safeguards:** 1–5 recipient cap, 50,000-plant request limit, 5 submissions/hour per buyer/IP, verified supplier rechecks, client ownership checks, per-supplier authorization, strict input limits, CSRF, encrypted atomic writes, UUIDv7 IDs and a 1,500-RFQ pilot cap. No auto-published quote, stock reservation, order, payment, bulk BOQ import, AI price invention or automated WhatsApp messages.

**Tests:** `php tests/sourcing.php`, `php tests/sourcing_ui.php`, the full PHP regression and DigiOps package/staging boot checks. This feature requires real Cloudways buyer/supplier/browser acceptance, encrypted backups with restoration, and operational onboarding checks before production exposure.

## Release B2 — SupplierOS layout and controlled CSV inventory imports

The supplier login and invitation screens use a separate, release-fingerprinted SupplierOS stylesheet: public/assets/css/supplier-os.css. They have restrained editorial typography, stacked accessible form fields, a two-column desktop composition, a mobile fallback, and dark-mode support. On supplier pages the consumer newsletter is omitted. CSS/JS URLs use source-content fingerprints instead of only the unchanged 1.7.0 application version.

Verified supplier owners/managers can now visit /supplier#import and upload UTF-8 CSV with exactly these columns: branch_id,product_slug,label,price_inr,quantity. The validator accepts up to 50 rows / 128 KB and allows only canonical published plants and branch IDs from the current tenant. The operator must approve a 15-minute session preview before writing. One locked mutation creates only draft, unknown-availability offers; existing duplicates are skipped and retries are idempotent. No external seller checkout or automatic stock confirmation is enabled.

Regression gates: php tests/supplier_ui.php; php tests/supplier_import.php; bash scripts/full_regression.sh; and the DigiOps split-layout smoke test. After deployment, visually check supplier login, invitation, branch screens and CSV preview on desktop and mobile. Live browser acceptance, supplier MFA and production data restore remain open.

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
