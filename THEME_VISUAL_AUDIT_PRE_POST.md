# Sanjay Nursery Botanical Retail Theme — Visual Audit Pre / Post

**Release:** v1.1.0  
**Reference supplied by user:** Ugaoo standalone visual mockup  
**Scope:** Adapt the reference typography, proportions, retail rhythm and botanical visual language to Sanjay Nursery while keeping the catalogue intentionally empty.

## Audit principle

The build adopts the supplied reference's design system and shopping rhythm without importing Ugaoo branding, copy, product records, photographs, or proprietary assets. Sanjay Nursery remains its own brand and currently contains **0 products by design**.

## Reference visual language extracted

| Layer | Reference characteristic | Sanjay Nursery v1.1.0 implementation |
|---|---|---|
| Display typography | Georgia-style editorial serif | Georgia display stack for hero/section headings |
| UI/body typography | Inter/system sans | Inter/system sans for navigation, controls and body copy |
| Primary colour | Deep botanical green | `#173f35` |
| Soft surface | Warm cream | `#f6f3eb` |
| Accent | Warm ochre/gold | `#d3a348` |
| Content width | Broad retail container | approx. 1380px shell |
| Announcement/header/nav | 36px / 76px / 49px | compact retail header rhythm retained |
| Radius language | Soft rounded cards/controls | 18px-class rounded cards and pill controls |
| Discovery | Search + category-led browsing | search, category rail and compact retail navigation |
| Mobile | Dense, swipe-friendly retail UI | horizontal category rails, compressed controls, no page overflow |

## PRE audit — v1.0.0 Clean Baseline

The clean baseline had already been converted from the former furniture project, but visually it still carried an editorial-luxury rhythm rather than a plant-retail rhythm.

- Hero and section typography was oversized, with desktop H1 values around **91–104px** and major H2 values up to **72px**.
- Section spacing was very airy and presentation-led, reducing retail information density.
- Botanical graphics were abstract and decorative rather than integrated into a nursery-shopping hierarchy.
- Category discovery and shopping utilities were less prominent than in the supplied reference.
- The zero-product catalogue was technically correct but needed a more intentional retail empty state.
- Header height was already compact enough to retain as a foundation.

## POST audit — v1.1.0 Botanical Retail

### Global shell

- Added and activated the **Sanjay Botanical Retail** theme preset.
- Rebuilt the public header into a compact green botanical retail shell with announcement strip, rounded search treatment, account/action controls and category navigation.
- Added a green/cream/white visual system using the supplied reference proportions.
- Standardised display headings on Georgia and interface/body copy on Inter/system sans.
- Added a denser 1380px retail shell with reference-like rounded controls and cards.
- Rebuilt newsletter/footer presentation to match the same visual system.

### Homepage

- Rebuilt hero hierarchy for nursery-first messaging and a more reference-like typographic scale.
- Added category discovery rail and category merchandising cards.
- Added nursery trust/service strip, plant-care sections, local visit content, project/corporate enquiry surfaces and an intentional zero-product merchandising state.
- Kept all product slots empty; no fake SKUs were introduced to make the design appear populated.

### Catalogue

- Added nursery category rail for Indoor Plants, Succulents & Cacti, Outdoor Plants, Pots & Planters, Gardening Essentials and Corporate Gifting.
- Added compact catalogue header/count/sort treatment.
- Added intentional empty catalogue state with clear next actions.
- Product count remains **0**.

### Supporting pages

- About, Contact and Plant Care were restyled to the same botanical retail design system.
- Page heading scales and section density were normalised against the supplied reference.

## Quantitative visual comparison

### Desktop — 1440px viewport

| Page | PRE H1 | POST H1 | PRE primary H2 | POST primary H2 |
|---|---:|---:|---:|---:|
| Home | 100.8px | 68px | 72px | 46px |
| Catalogue | 90.72px | 56px | 68px | 52px |
| About | 103.68px | 68px | 72px | 58px |
| Contact | 103.68px | 68px | 32px | 54px |
| Plant Care | 103.68px | 68px | 41.6px | 46px |

The desktop shell remains compact at roughly **126px total header/navigation chrome**, closely matching the reference's 36px + 76px + 49px retail structure after component adaptation.

### Tablet — 768px viewport

All five audited public pages render at exactly **768px scroll width** with no horizontal overflow. The post-theme header is approximately **120px** high, and display sizing compresses into a practical 42–62px range.

### Mobile — 390px viewport

All five audited public pages render at exactly **390px scroll width** with **no horizontal overflow**. The post-theme header is approximately **122px** high. Homepage H1 is approximately **50.7px**, with section H2 at approximately **36px**.

## Functional / integrity audit

- Active products: **0**
- Configurator rules: **0**
- Product media mappings: **0**
- Legacy product media: **0**
- Nursery categories: **6**
- PHP lint: **PASS**
- Full regression suite: **PASS**
- Zero-product catalogue regression: **PASS**
- Theme registry/default surface regression: **PASS**
- Desktop/tablet/mobile overflow audit: **PASS**
- Core HTTP routes: **PASS / HTTP 200**
- `/health`: **PASS / HTTP 200**, reports `products: 0`
- `/ready`: **PASS / HTTP 200**, all critical readiness checks green

## Deferred product-flow tests

Cart/order/configurator/bespoke/production/passport/trade product-flow tests remain deferred until approved Sanjay Nursery inventory is imported. The engines remain available in source, but the public catalogue intentionally has no products.

## Result

**PASS — Sanjay Nursery v1.1.0 now uses a botanical retail theme aligned to the supplied reference's typography, proportions, colour system, card language, navigation density and responsive behaviour, without reintroducing legacy products or copying the reference brand.**
