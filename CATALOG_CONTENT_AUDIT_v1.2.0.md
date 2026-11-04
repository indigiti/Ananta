# Sanjay Nursery v1.2.0 — Catalogue Content Audit

## Scope

This release adds the 174 requested plant varieties as **variety-level catalogue records**. It intentionally does not invent pot-size SKUs, live stock, fixed prices or product photography.

## Catalogue integrity

| Check | Result |
|---|---:|
| Total catalogue records | 174 |
| Collections | 18 |
| Unique GUIDs | 174 |
| Unique SKUs | 174 |
| Unique slugs | 174 |
| Enquiry-mode records | 174 |
| Complete botanical-name/context field | 174 |
| Complete five-field care profiles | 174 |
| Complete SEO title + description | 174 |
| Records with approved product images | 0 |
| Featured variety records | 19 |

## Category totals

| Store category | Varieties |
|---|---:|
| indoor-plants | 148 |
| outdoor-plants | 10 |
| succulents-cacti | 16 |

## Collection totals

| Collection | Varieties |
|---|---:|
| Aglaonema | 19 |
| Alocasia / Colocasia | 19 |
| Asplenium & Ferns | 19 |
| Fittonia | 14 |
| Zamioculcas / Zamia | 7 |
| Haworthia | 11 |
| Peperomia | 10 |
| Dieffenbachia | 7 |
| Philodendron | 6 |
| Sansevieria / Snake Plants | 10 |
| Syngonium | 4 |
| Caladium | 5 |
| Money Plant / Pothos / Scindapsus | 7 |
| Monstera | 2 |
| Ficus / Bonsai | 6 |
| Flowering Plants | 9 |
| Palms / Pachira / Dracaena | 6 |
| Other plants / speciality items | 13 |

## Content model

- `name`, `slug`, GUID and internal variety SKU
- Store category + requested collection/family group
- Botanical name/context, using conservative cultivar/species wording where the trade name is ambiguous
- Short catalogue copy and full product-page description
- Care profile: light, watering, humidity, difficulty and placement
- Nursery note and living-plant variation/aftercare copy
- SEO title and meta description
- `sales_mode: enquiry` and `price_label: Check price & availability`
- `catalogue_stage: content-ready-stock-pending` until the live nursery batch is linked

## Commerce safety

- All 174 records are **enquiry-first**. A ₹0 database value is never presented as a retail price.
- Enquiry-only products are rejected by the cart layer.
- Product structured data omits a fake Offer when there is no approved sellable price.
- Google Merchant output omits enquiry-only products.
- Direct checkout should only be enabled after an exact pot size/batch, current selling price and stock quantity are assigned.

## Media policy

No generic or fabricated product photos were attached. Each image-pending product uses the botanical theme placeholder. Approved nursery photography can later be mapped through MediaOS with alt text.

## Runtime verification

- Home: HTTP 200
- Catalogue: HTTP 200
- Aglaonema collection filter: HTTP 200
- Catalogue page 2: HTTP 200
- Aglaonema Bai Kaw PDP: HTTP 200
- Raven / Black ZZ PDP: HTTP 200
- `/health`: HTTP 200, `products: 174`
- `/ready`: HTTP 200, `ok: true`
- AI product feed: HTTP 200
- Google Merchant feed: HTTP 200 and intentionally no enquiry-only product items
- Representative enquiry-only cart insertion returns `false`

## Deferred before ecommerce activation

For each sellable plant batch/variant, confirm real pot size, plant height/grade where applicable, SKU, current price, stock, delivery restrictions and approved photography. The current catalogue pages are the stable content/discovery layer those sellable variants can attach to.
