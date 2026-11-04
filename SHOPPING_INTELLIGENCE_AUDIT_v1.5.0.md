# Sanjay Nursery v1.5.0 — Shopping Intelligence & Personalisation Engine Audit

## Release decision

The supplied Shopping Intelligence specification called the work `v1.4.0`, but Sanjay Nursery v1.4.0 already exists as the verified Live Plant Variant Engine. This implementation is therefore versioned **v1.5.0** so release history is not overwritten.

## Architecture

The engine is deliberately **first-party, anonymous, file-mode compatible and fail-open**.

Visitor UI → batched event API → private event journal → anonymous profile/session intent → rules/relationship/recommendation engine → lazy recommendation APIs → homepage/PDP/cart placements → recommendation/purchase attribution.

Storefront/cart/checkout functions remain independent of the intelligence layer.

## Implemented scope

### Tracking and identity

- Random first-party `V-...` visitor identity and `S-...` session identity.
- Local/session browser storage according to consent state.
- Batched asynchronous event transport with page-exit Beacon fallback.
- Allowlisted event/property schema; arbitrary form content and PII are not accepted.
- Navigation, search, discovery, product, wishlist, cart, checkout and purchase event vocabulary.

### Plant intelligence taxonomy

All 174 plant parent records now support structured intelligence metadata including plant type, indoor/outdoor classification, sunlight, watering, difficulty, humidity, placement, growth/pet/air-purifying/flowering placeholders, cart-booster control, merchandising flags and intent categories.

Existing catalogue facts were backfilled conservatively. Unknown characteristics remain `unknown`/blank rather than being guessed.

### Profiles, scoring and intent

- Long-term interest profile.
- Current-session interest profile.
- Price-band affinity.
- Editable behaviour weights.
- Editable recommendation weights, validated to exactly 100%.
- Interest decay: 0–7d 100%, 8–30d 75%, 31–90d 45%, 90+d 20%.
- Minimum-event threshold before profiling.
- Automatic merchandising segments.

### Recommendation modes

- Recommended for You.
- Inspired by Browsing.
- Recently Viewed.
- Similar/Related Plants.
- Frequently Bought Together / Customers Also Bought using real paid-order co-occurrence when available.
- Complete the Look using explicit relationships.
- Plant Care Essentials using explicit care relationships.
- Trending / Best Sellers using observed performance.
- Cart Completion using live sellable variants and cart gap.

Cold start uses catalogue similarity, featured status, explicit relationships and admin rules. It does not invent purchase history.

### Cart intelligence

- Optional minimum-order threshold; **disabled by default**.
- Transparent progress meter and remaining amount.
- Cart-gap range controlled by configurable price tolerance.
- Quick Add via AJAX with instant cart totals.
- Out-of-stock/unsellable variants are excluded.
- Cart-booster product-level opt-out (`auto / yes / no`).
- Automatic Under ₹49/99/149/199/299/499 pool metrics.
- Cart-booster attribution and revenue metrics.
- No automatic product addition.

### Search intelligence

- Search start/submission/refinement/no-result/result-click vocabulary.
- Query → product/cart/purchase attribution when the signal is available.
- Search dashboard for searches, zero results, CTR, purchase rate and revenue.
- Context-aware search suggestion API.

### Rules and relationships

Admin rules support:

- category/collection condition;
- visitor-interest condition;
- cart maximum condition;
- boost collections/products;
- suppress products;
- exclude categories/collections;
- pin products;
- priority and campaign date windows.

The engine always applies availability/sellability guards after business intent.

### Experiments

- Deterministic anonymous A/B assignment.
- Placement-specific experiments.
- Recommendation attribution carries experiment variant.
- No automatic winner declaration.

The framework is ready for measured impressions/click/cart/purchase/revenue/AOV comparisons once traffic is sufficient.

### Privacy and retention

- Separate Essential, Analytics, Personalisation and Marketing choices.
- Anonymous-profile export.
- Reset interests.
- Disable personalisation.
- Delete profile + visitor events.
- Configurable raw-event, profile, aggregate and cookie retention.
- Automatic daily cleanup plus manual Admin cleanup.
- No sensitive-characteristic profiling taxonomy.

### Admin Information Architecture

`Admin → Intelligence` includes:

Overview · Live Visitors · Visitor Profiles · Events · Interests · Segments · Recommendations · Cart Intelligence · Product Relationships · Search Intelligence · Rules · Experiments · Insights · Privacy · Scoring · Settings.

### Analytics and insights

KPIs include personalised sessions, recommendation impressions/CTR/add rate/revenue, cart-booster revenue, AOV, cart completion, minimum-order completion, search conversion, zero-result searches and cart abandonment.

Automated insights identify observable search gaps/opportunities and cart friction. External LLM-generated business advice is **not** enabled by default.

## Deliberately data-dependent / dormant

The following capabilities are structurally supported but should not pretend to have evidence before real traffic exists:

- predictive Level 3 affinity/purchase-likelihood modelling;
- statistically meaningful A/B winner selection;
- meaningful Frequently Bought Together strength before paid multi-item orders;
- meaningful best-seller/trending strength before traffic/orders;
- recommendation revenue before checkout-ready variants exist.

The release baseline still has zero fabricated live variants, prices or stock.

## Acceptance / safeguards

- Intelligence engine can be disabled without breaking storefront.
- Anonymous tracking/personalisation can be independently consented.
- Event/recommendation weights are Admin-editable.
- Minimum order and cart tolerance are Admin-editable.
- Products/categories can be boosted, pinned or suppressed.
- Privacy profile reset/delete/export is customer-accessible.
- Recommendation requests are lazy and fail gracefully.
- Event transport is asynchronous and batched.
- Cart/checkout/payment retain their existing server-side validation.
- Recommendation attribution survives Quick Add into cart/order snapshot and confirmed-purchase analytics.

## Baseline data

- Products: 174
- Collections: 18
- Live variants: 0
- Anonymous profiles shipped: 0
- Raw events shipped: 0
- Rules shipped: 0
- Relationships shipped: 0
- Experiments shipped: 0
- Minimum order: disabled by default
- Predictive Level 3: dormant

## Deployment notes

1. Deploy v1.5.0 over the verified v1.4.0 baseline.
2. Keep Intelligence enabled but use consent mode appropriate to the deployment jurisdiction/policy.
3. Add verified live plant variants/photos/prices before expecting cart recommendation revenue.
4. Configure explicit complementary/care relationships for high-priority plants.
5. Review zero-result searches weekly.
6. Do not activate predictive/automated decisioning until enough reliable data has accumulated.
7. For high event volume, move behavioural storage to an event queue/database without changing the public API contract.

## Final verification

- PHP files linted: **129 / 129 PASS**
- Full regression: **PASS**
- Shopping-intelligence isolated regression: **PASS**
- Public runtime pages/APIs checked: **8 / 8 HTTP 200**
- `/health`: **version 1.5.0 · products 174 · variants 0 · profiles/events 0**
- `/ready`: **96 checks · 0 failures**
- Shopping-intelligence readiness checks: **8 / 8 PASS**
- Release baseline after QA scrub: **0 profiles · 0 events · 0 rules · 0 relationships · 0 experiments · 0 variants**
