# Sanjay Nursery v1.6.0 — Commerce Activation & Plant Discovery Audit

## Release objective

Activate the verified v1.4/v1.5 foundation for real nursery commerce and better discovery without fabricating live stock, prices, plant traits or product photography.

## Baseline preserved

- Parent plant varieties: **174**
- Plant-family collections: **18**
- Live variants shipped: **0**
- Fabricated prices: **0**
- Fabricated stock: **0**
- Approved product photography shipped: **0**
- Anonymous intelligence profiles/events shipped: **0 / 0**

This is an activation release: real batches can be added from Admin/CSV, then the storefront automatically exposes valid price, stock, shipping, media and Merchant state.

## 1. Real inventory activation

The existing Live Plant Variant Engine remains the transactional source of truth. v1.6 surfaces it throughout discovery and buying UX.

Each real nursery SKU can carry:

- parent plant variety
- SKU
- pot size / pot label
- height range
- grade
- nursery batch code and date
- live price / compare-at price
- sales mode
- availability
- tracked stock
- lead time
- shipping class
- local pickup capability
- Merchant eligibility flag
- image override
- status / internal notes

Bulk updates continue through `LIVE_VARIANT_IMPORT_TEMPLATE.csv` and Admin → Plant Variants.

### Commerce truthfulness

A parent variety does not receive a synthetic price merely because it exists in the catalogue. Price/stock discovery requires at least one checkout-ready live variant.

## 2. Plant Photography OS

MediaOS is now plant-native rather than furniture-oriented.

Roles:

1. Catalogue hero
2. Full plant
3. Foliage close-up
4. Pot / scale reference
5. Side / form
6. Nursery batch proof
7. Lifestyle / placement
8. Care / growing context

Production photography readiness requires approved:

- hero
- full plant
- foliage
- pot/scale

An image cannot be approved while rights are `unverified`. Approved rights states are `owned`, `licensed` or `generated`.

### Merchant hardening

Merchant eligibility now requires an approved, rights-cleared parent catalogue hero in addition to a real sellable variant and explicit Merchant enablement. A filename alone is not sufficient.

## 3. Plant Discovery Engine

A new discovery layer normalizes current catalogue/variant/media data into usable facets.

Supported filters include:

- light
- watering
- difficulty
- size (when evidenced)
- placement
- explicit pet-safety values
- explicit flowering values
- explicit air-purifying values
- live availability
- verified live price range

Unknown plant traits are not converted into positive claims.

### Current evidence coverage

At the verified zero-live-SKU baseline:

- light: 174 classified from existing care data
- watering: 174 classified
- difficulty: 174 classified
- live availability: 0 live / 174 enquiry
- live priced products: 0
- media-ready products: 0

Some optional facets intentionally remain absent where the existing catalogue has no verified value.

## 4. Search

Search now supports:

- common names
- botanical/context fields
- catalogue families
- synonyms
- small typo tolerance
- weighted ranking
- live/media-aware tie breaking

Verified examples:

- `aglaonema` → 19 requested Aglaonema varieties
- `agloneema` → 19 Aglaonema varieties
- `fitonia` → 14 Fittonia varieties
- `zz` → Zamioculcas / Zamia results
- `snake plant` → Sansevieria/Snake Plant results without whole-catalogue leakage
- `money plant` → Pothos / Scindapsus / Money Plant context

Generic words such as `plant` are excluded from fuzzy-token scoring so synonym phrases do not pollute relevance.

## 5. Intent landing pages

Clean routes are available for:

- `/plants/low-light`
- `/plants/office`
- `/plants/balcony`
- `/plants/beginner`
- `/plants/flowering`
- `/plants/gifts`
- `/plants/rare`
- `/plants/live-now`

Only evidence-backed, non-empty intent pages are promoted to the sitemap/indexable surface. Empty intent states do not fabricate matching inventory/content.

## 6. Plant Finder

`/plant-finder` now scores the catalogue using:

- placement
- light
- care difficulty
- size when known
- real live-SKU budget
- live-stock-only preference

The Finder exposes reasons for each match. `live_only` correctly returns zero results on the clean baseline because no real live variants are shipped.

## 7. Buying UX fixes

v1.6 fixes two important real-inventory edge cases found during audit:

1. Product cards now render the live variant price/range via the variant source of truth instead of falling back to a parent ₹0 value.
2. Mobile PDP action changes to **Choose size** when sellable variants exist instead of incorrectly showing the enquiry state.

Additional PDP improvements:

- exact live variant selector
- batch/SKU/height/grade visibility
- stock/availability cue
- delivery vs pickup cue
- variant-aware pincode checker
- Add to Bag only for a selected checkout-ready SKU
- enquiry fallback when no variant is checkout-ready

The pincode checker now evaluates the selected variant shipping class rather than a generic/empty cart.

## 8. Admin Discovery Center

The dashboard provides an activation funnel:

`174 catalogue parents → products with variants → checkout-ready products → photography-ready products → fully activated products`

Admin can also manage:

- discovery engine toggle
- Plant Finder toggle
- intent page toggle
- typo tolerance
- synonym mapping
- live inventory via the existing variant workflow
- photography approval via MediaOS

No source-code editing is required for normal inventory/media activation.

## 9. Intelligence integration

Discovery and commerce remain integrated with v1.5 Shopping Intelligence.

The event layer records search and active discovery filters asynchronously, subject to configured consent. Failure of recommendation/discovery intelligence does not block catalogue, cart, checkout or payment.

## 10. QA and runtime verification

### Static / regression

- PHP files linted: **133 PASS**
- Full regression: **PASS**
- Dedicated v1.6 discovery regression: **PASS**
- Variant commerce regression: **PASS**
- Media rights/approval gate: **PASS**
- Merchant safety: **PASS**
- Zero-fabricated-stock baseline: **PASS**

### Runtime

HTTP 200 verified for:

- `/`
- `/shop`
- `/plant-finder`
- `/plants/low-light`
- `/collections/aglaonema`
- `/product/aglaonema-bai-kaw`
- `/health`
- `/ready`

`/health` reports:

- version **1.6.0**
- products **174**
- variants **0**
- sellable variants **0**
- Merchant variants **0**

`/ready` reports **102 checks / 102 PASS / 0 failures**.

## 11. Operational activation sequence

1. Enter the first real nursery batches using the live-variant CSV/Admin workflow.
2. Upload current batch photography.
3. Assign and approve the four mandatory photography roles.
4. Verify price/stock/shipping on representative mobile and desktop PDPs.
5. Enable Merchant on only eligible SKUs.
6. Review zero-result searches and add synonyms/verified product facts as needed.
7. Expand verified product taxonomy (size, pet safety, flowering, air purification) only from reliable nursery/product knowledge.
8. Use Plant Finder/search behavioural data to prioritize the next content and inventory batches.

## Release assessment

v1.6.0 successfully connects the previous Growth, Variant and Shopping Intelligence foundations to a truthful customer discovery and commerce-activation layer. The remaining constraint is operational data—not architecture: live price/stock and approved nursery photography must now be populated for products to become fully activated.
