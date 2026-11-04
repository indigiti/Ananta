# Sanjay Nursery v1.7.0 — AI Search, Catalogue & Content Intelligence

v1.7.0 builds on the verified v1.6.4 storefront/theme release and v1.6.3 Web Operations layer. It adds a **web-managed AI Engine** for catalogue intelligence, SEO auditing, governed enrichment, comparisons, listicles, review/rollback and agent-readable discovery without making SSH/console access part of normal setup or operation.

## What is new in v1.7.0

### AI Engine — Admin → AI & Search

The AI Engine has three explicit operating modes:

- **Scan Only** — deterministic catalogue, SEO and content-opportunity analysis; no external AI provider is required and no AI-generated product edits are applied.
- **Review** — provider-assisted product enrichment creates field-level suggestions in a review queue. An administrator decides what is applied.
- **Safe Auto** — only allowlisted, non-protected fields can auto-apply when they meet their configured confidence threshold; protected or high-risk facts remain review-only.

The engine is disabled by default in the clean package. Provider configuration, API key storage, model selection, limits and activation are handled from the browser at `/admin/ai/settings`.

### Provider hub and cost controls

- OpenAI-compatible HTTPS provider adapter.
- Encrypted API key in protected private storage outside `public/`.
- Test Connection and Refresh Models actions.
- Refreshed model IDs become selectable from the Text Model field.
- Daily request and token ceilings.
- Maximum jobs per run and per hour.
- Request timeout and bounded retries through the job lifecycle.
- Changed-record-only enrichment to avoid unnecessary repeat calls.

### Catalogue Intelligence

- Scores each product for catalogue, SEO, entity, commerce and media completeness.
- Detects missing botanical/entity fields, imagery, live variants and Pune-specific guidance.
- Creates provider-assisted enrichment suggestions only in Review/Safe Auto modes.
- Never allows AI to invent or Safe-Auto-write price, stock, SKU, availability/delivery, reviews/ratings or safety-critical claims.
- Botanical genus/species and mature-size suggestions are high-confidence/review protected.

### SEO Intelligence

- Audits product and CMS records for title/description quality, image readiness, botanical identity, Pune context and editorial-quality thresholds.
- Generates an SEO health score and an issue queue.
- Content Opportunity detection identifies data-backed listicle and comparison ideas from the current catalogue.
- Existing canonical, sitemap, robots, Product/ProductGroup, Offer, Breadcrumb and Organization architecture remains intact.

### Comparisons and listicles

- Admin comparison builder for 2–4 real catalogue products.
- Data-led listicle builder using catalogue attributes instead of invented product facts.
- Published comparison URLs: `/compare/{slug}`.
- Interactive ad-hoc compare route: `/compare?plants=...`, automatically `noindex,follow`.
- Published listicle URLs: `/lists/{slug}`.
- Article + ItemList + Breadcrumb structured data for published content.
- Published comparison/listicle URLs are added to the XML sitemap.
- Product pages can surface related published comparisons/listicles.
- Content is marked **STALE** in Admin when an underlying product changed after the content item was last updated.

### Human review and rollback

- Field-level before/after diff.
- Confidence percentage per suggestion.
- Accept/reject review controls.
- Immutable AI change history.
- One-click product restore to the pre-AI snapshot.
- Safe Auto only applies allowlisted fields that pass their field-specific threshold.

### Web-only job scheduler

- File-backed, lock-protected job queue.
- Manual **Run queue** action from the dashboard.
- Optional bounded web runner; no CLI worker is required.
- Manual, daily, every-3-days or weekly scheduling.
- Hourly throttling and per-request job limits prevent long web requests.
- Scan Only scheduling performs deterministic audits without requiring a provider.

### System Health + Error Monitor integration

AI provider, queue and release-check failures are recorded in the existing Error Monitor. The System Health page now exposes AI state, provider readiness, selected model, queue/failed-job counts, catalogue/SEO health and daily request/token usage. Matching health incidents resolve automatically after recovery under the existing v1.6.3 incident lifecycle.

### Agent and AI discovery

- `/feeds/ai-products.json`
- `/feeds/agent-products.json`
- `/feeds/ai-content.json`
- `/api/agent/catalog/search`
- `/.well-known/agent-capabilities.json`
- `/api/openapi.json`

The capability contract explicitly keeps purchase, payment and order submission behind human approval. Commerce truth remains sourced from verified live variants.

## Retained platform capabilities

- v1.6.4 icon-only storefront utilities and light/dark parity.
- v1.6.3 browser-only first-run setup, System Health and Error Monitor.
- Automatic incident resolution/reopen and 90-day purge of resolved non-critical errors while retaining critical history.
- 376 parent plant records from the v1.6.2 catalogue expansion.
- Discovery, Plant Finder, Live Variant Engine, Shopping Intelligence, CMS/SEO, Growth Center and commerce guardrails.

## Web-only installation

1. Upload the application folder using the hosting panel or FTP.
2. Point the domain/subdomain document root to `public/`.
3. Open `/admin/setup` and create the first administrator.
4. Open **Admin → System Health** and run/confirm the health scan.
5. Open **Admin → AI & Search → Settings**.
6. Leave **Scan Only** selected for provider-free deterministic auditing, or configure an API base URL, encrypted API key and text model before choosing **Review** or **Safe Auto**.
7. Use **Test Connection** / **Refresh Models**, save, then activate the AI Engine.

No SSH, environment-file editing, queue daemon or console migration is required for normal v1.7.0 installation and administration.

## Important truthfulness rule

AI is an enrichment and maintenance layer, not the commercial source of truth. v1.7.0 does **not** permit AI to fabricate live price, inventory, SKU, delivery availability, customer ratings/reviews, toxicity/pet-safety or other protected facts. Those values must come from verified business data or explicit human verification.

## Developer QA

Automated regression remains in `scripts/full_regression.sh`; it is for development/CI and is not required for browser installation.
