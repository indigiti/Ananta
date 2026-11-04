# Sanjay Nursery v1.4.0 — Growth Foundation Build Audit

## Release objective

Turn the stable 174-variety botanical storefront into a measurable, migration-safe and content-governed growth platform before adding live commerce variants or scaling content production.

## Built in v1.4.0

### Measurement
- Browser-managed GA4/GTM configuration.
- Consent-aware loading; analytics stays off when no valid ID is configured.
- Catalogue/search/lead/newsletter intent event hooks.
- No submitted PII is attached to the built-in analytics events.

### Search & discovery
- 24 canonical collection hubs: 6 category hubs + 18 plant-family hubs.
- Filter/search/sort URLs stay usable but are noindex when they create non-canonical combinations.
- Sitemap expanded to include collection hubs and all product pages.
- Organization/GardenStore, WebSite, BreadcrumbList, CollectionPage/ItemList and Product structured data.
- Enquiry-only Product schema intentionally omits Offer.

### Migration protection
- CMS redirects retained as durable migration rules.
- Bulk CSV/line importer in Growth Center.
- Public GET 404s are journalled without storing a raw IP address.
- Growth Center aggregates missing paths by hit count and most recent occurrence.

### Content authority controls
- CMS fields for target query, search intent, audience, expert reviewer, first-hand nursery evidence and reference URLs.
- 100-point editorial quality score.
- Configurable publish threshold, default 65/100.
- Server-side publish gate; low-quality drafts cannot bypass the UI.
- Old staging test article removed from the release baseline.

### Local / Merchant readiness
- Google Business Profile reference fields.
- Merchant Center ID field.
- Merchant XML continues to output only genuinely sellable products.
- Current 174 enquiry products therefore produce zero Merchant feed items by design.

## Verification snapshot

- Version: 1.4.0
- Catalogue records: 174
- Plant-family collections: 18
- Clean indexable hubs: 24
- Product pages in sitemap: 174
- Total sitemap URLs on the clean baseline: 204
- Approved product media: 0
- Sellable products: 0
- Merchant feed items: 0
- Published CMS authority content: 0 clean baseline
- Full regression: PASS
- `/health`: HTTP 200
- `/ready`: HTTP 200 / `ok: true`
- `/collections/indoor-plants`: HTTP 200
- `/collections/aglaonema`: HTTP 200
- `/product/aglaonema-bai-kaw`: HTTP 200, no ₹0 and no schema Offer
- Unknown public URL: HTTP 404 and migration journal capture verified

## Deferred deliberately

- Real GA4/GTM/Search Console/GBP/Merchant account identifiers: configure after deployment in Growth Center.
- Paid/sellable SKU activation: deferred until pot size, live batch, stock and selling price are verified.
- Product photography: deferred until approved nursery media is supplied.
- Large-scale content publication: deferred until editorial evidence/reviewer workflow is operational.

## Next release candidate

v1.4.0 should implement the Live Plant Variant Engine: parent variety → pot-size/batch SKU → current stock/price → approved media → Offer/Merchant eligibility only when the checkout path is genuinely available.
