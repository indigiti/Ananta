# Sanjay Nursery v1.6.2 — Indoor & Small Garden Catalogue Expansion Audit

## Release result

- Existing parent varieties preserved: **174**
- New deduplicated parent varieties: **202**
- Final parent catalogue: **376**
- New Indoor Plants: **100**
- New Small Garden / Outdoor Plants: **75**
- New compact Succulents & Cacti: **27**
- Final taxonomy collections: **40**
- Live variants invented: **0**
- Prices invented: **0**
- Stock invented: **0**
- Product photography invented: **0**

## Content/data safeguards

Every new parent record has a unique GUID, SKU and slug plus botanical context, care profile, SEO metadata, discovery intelligence and v1.6.2 provenance. All expansion products remain `sales_mode=enquiry` with price `0` until a verified live variant supplies real pot size, batch, stock and price. Pet-safety and air-purifying fields remain `unknown` unless explicitly verified.

## Discovery additions

Search synonym coverage was expanded for Fiddle Leaf Fig, Rubber Plant, Prayer Plant, Chinese Money Plant, Lady Palm, Holy Basil, Butterfly Pea, Periwinkle and Crown of Thorns. New intent pages were added for **Small Garden Plants**, **Hanging Plants**, and **Herbs & Edibles**.

## QA

- Full PHP/application regression: **PASS**
- Catalogue expansion regression: **PASS**
- Unique GUID/SKU/slug integrity: **PASS**
- MediaOS catalogue coverage: **PASS (376/376)**
- Variant baseline: **0**
- `/`, `/shop`, `/plant-finder`: **HTTP 200**
- `/plants/small-garden`: **HTTP 200**
- `/plants/hanging`: **HTTP 200**
- `/plants/herbs-edibles`: **HTTP 200**
- Representative new Ficus and Hibiscus PDPs: **HTTP 200**
- `/health`: **HTTP 200**
- `/ready`: **HTTP 200 / 102 of 102 checks PASS**

## New collection families

- Asplenium & Ferns: 4
- Begonia: 7
- Bougainvillea: 8
- Cactus: 6
- Calathea / Maranta: 11
- Climbers & Creepers: 4
- Compact Flowering Plants: 7
- Crossandra: 2
- Croton: 10
- Euphorbia / Crown of Thorns: 4
- Ficus / Bonsai: 12
- Flowering Plants: 6
- Gardenia: 2
- Hanging & Trailing Plants: 4
- Herbs & Edibles: 15
- Hibiscus: 7
- Hoya: 4
- Ixora: 5
- Jasmine & Fragrant Plants: 4
- Kalanchoe: 5
- Kitchen Garden Plants: 7
- Monstera: 5
- Other plants / speciality items: 3
- Palms / Pachira / Dracaena: 13
- Philodendron: 16
- Pilea & Hypoestes: 5
- Seasonal Flowering Plants: 14
- Succulents: 8
- Trailing Succulents: 4
