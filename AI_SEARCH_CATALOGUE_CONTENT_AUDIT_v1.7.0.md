# Sanjay Nursery v1.7.0 — AI Search, Catalogue & Content Intelligence Audit

## Release objective

v1.7.0 adds a governed AI automation layer to the existing file-mode PHP application while preserving the project's web-only operational model and the truthfulness boundaries introduced by the commerce/catalogue releases.

## Implemented architecture

Browser/Admin OS → AI Engine → private settings/credential/queue/review/history stores → optional OpenAI-compatible HTTPS provider.

The deterministic catalogue, SEO, comparison/listicle candidate and agent-discovery layers do not require an external provider. Provider-assisted catalogue enrichment is only available in Review or Safe Auto mode.

### Admin surfaces

- `/admin/ai/overview`
- `/admin/ai/catalogue`
- `/admin/ai/seo`
- `/admin/ai/comparisons`
- `/admin/ai/listicles`
- `/admin/ai/review`
- `/admin/ai/sources`
- `/admin/ai/jobs`
- `/admin/ai/history`
- `/admin/ai/agent`
- `/admin/ai/settings`

The main Admin dashboard includes an AI Engine status card; System Health includes AI provider/queue/usage metrics.

## AI modes

| Mode | External provider | Automatic product edits |
|---|---:|---:|
| Off | No | No |
| Scan Only | Not required | No |
| Review | Required for enrichment | No; suggestions wait for review |
| Safe Auto | Required for enrichment | Allowlisted fields only above confidence thresholds |

## Protected data policy

The engine explicitly protects price, compare-at price, SKU, inventory, variants, availability/delivery, ratings/reviews, pet-safety and toxicity. AI cannot be used as the source of truth for these fields. Botanical genus/species and mature size are review-protected rather than blind Safe Auto fields.

## Catalogue Intelligence

Each product receives five completeness dimensions:

- catalogue content;
- SEO;
- plant/entity intelligence;
- commerce readiness;
- media readiness.

The clean v1.7.0 release retains 376 parent records and intentionally does not fabricate live inventory or photography. The intelligence scanner therefore surfaces these gaps instead of hiding them.

## SEO Intelligence

The deterministic scanner evaluates product/CMS metadata, approved imagery, botanical identity, Pune context and editorial-quality readiness. Results are persisted for dashboard use and can be re-run from the web queue.

## Comparison and listicle engine

### Comparisons

- 2–4 real product slugs per comparison.
- Attribute matrix generated from the live catalogue.
- Price/availability shown only from verified commerce data.
- Permanent published comparison URLs are indexable.
- Interactive query-string comparisons are `noindex,follow`.

### Listicles

- Candidate selection is catalogue-data-led.
- Topic terms and relevant plant attributes influence selection.
- Published pages receive Article + ItemList + Breadcrumb schema.
- Product-page related-content relationships are generated from published items.
- Content is flagged STALE when an underlying product was updated later than the content item.

## Review, Safe Auto and rollback

Provider proposals create field-level before/after changes with confidence. Review mode requires explicit acceptance. Safe Auto applies only allowlisted, non-protected fields that meet field-specific thresholds. Every applied product change stores the pre-change snapshot and can be restored from Admin → AI & Search → History.

## Web scheduler

The job queue uses protected JSON storage and a file lock. It processes bounded batches from normal web traffic/admin requests or the explicit Run Queue action. No daemon or command-line worker is required for normal staging operation. Request/token limits and jobs-per-hour/run limits cap cost and runtime.

## Error/health integration

AI provider, job and scheduler failures create incidents with `source=ai`. AI release checks are merged into the existing System Health checks. The v1.6.3 automatic resolve/reopen and retention policies remain in effect.

## Agent/AI discovery

Implemented discovery surfaces:

- `/feeds/ai-products.json`
- `/feeds/agent-products.json`
- `/feeds/ai-content.json`
- `/api/agent/catalog/search`
- `/.well-known/agent-capabilities.json`
- `/api/openapi.json`

The capability document states that purchase, payment and order submission require human approval.

## Security controls

- AI API credentials are encrypted in private storage outside `public/`.
- Provider base URL requires HTTPS, except localhost HTTP for development.
- Provider redirects are disabled.
- CSRF protection covers all Admin mutations.
- Admin authentication protects provider settings, jobs, review actions, publishing and rollback.
- Daily provider budgets and execution limits reduce accidental runaway usage.
- No provider key is shipped in the release package.

## Verification scope

Developer regression covers catalogue/SEO scanning, opportunity generation, protected-field policy, listicle candidate selection, comparison matrix generation, capability manifest and OpenAPI contract. The complete project regression also covers prior catalogue, discovery, commerce, monitoring, theme/UI, security and release checks.

Clean HTTP verification covers browser-only setup/login, all AI Admin sections, machine-readable endpoints, comparison/listicle creation/publishing, public page rendering, sitemap and `/ready`.

External model quality/provider behavior is intentionally not asserted in the clean release because no customer API credential/model is bundled. That is verified after the administrator configures the chosen provider from the dashboard.
