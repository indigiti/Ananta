# Sanjay Nursery v1.6.1 — Visual, Code & Performance Stabilisation Audit

**Base audited:** v1.6.0 Commerce Activation & Plant Discovery  
**Resulting release:** v1.6.1 UI/UX & Performance Stabilisation  
**Audit date:** 16 September 2026  
**Catalogue baseline preserved:** 174 parent varieties · 18 plant families · 0 fabricated live variants · 0 fabricated prices/stock · 0 approved fake product images

## Executive result

The v1.6.0 commerce/discovery architecture was functionally sound, but the audit identified a mix of presentation inconsistencies, accessibility gaps, staging/runtime asset-delivery problems, excessive file-mode reads, admin-navigation clutter and a small amount of furniture-era customer copy. v1.6.1 fixes those verified issues without changing the product model or weakening inventory/commerce safeguards.

The largest technical finding was not cosmetic: the documented PHP built-in-server workflow returned **404 for CSS/JS/static assets**, because `router.php` returned control to PHP's server after locating a file inside `public/`, while the server document root remained the project root. This could make a valid project appear visually broken during staging/local QA. v1.6.1 directly serves safe public assets from the router and returns the correct content type/cache headers.

The largest performance finding was repeated JSON/file reads inside the 174-product discovery loop. Request-scoped caches reduced catalogue generation dramatically while preserving the existing file/JSON architecture.

## Pre → Post summary

| Area | Pre v1.6.0 | Post v1.6.1 | Result |
|---|---|---|---|
| Built-in-server CSS asset | 404 | 200 | Fixed |
| `/shop` median PHP generation | 443.26 ms | 7.09 ms | **98.4% lower** |
| Aglaonema collection median | 82.67 ms | 5.91 ms | **92.9% lower** |
| Low-Light intent median | 70.80 ms | 6.41 ms | **90.9% lower** |
| Home median | 7.74 ms | 4.28 ms | 44.7% lower |
| Product-page H1 count | 2 on PDP | 1 | Fixed semantics |
| Primary UI icon system | Mixed Unicode symbols | Consistent inline SVG | Fixed |
| Keyboard focus treatment | Inconsistent | Global `:focus-visible` | Fixed |
| Minimum touch target | Mixed | 44px on primary compact controls | Improved |
| Admin navigation | Long/flat mixed IA | Grouped nursery-first IA | Improved |
| Static cache busting | Weak/none | App-version query string | Added |
| Nginx static caching | Generic | 30-day immutable assets | Added |
| PHP lint | — | 134 files PASS | PASS |
| JS syntax | — | PASS | PASS |
| Nginx syntax | — | PASS | PASS |
| Full regression | v1.6 suites | v1.6 + UI/performance suite | PASS |
| `/ready` | — | 102/102 critical checks | PASS |

Performance figures were measured repeatedly on the same local PHP runtime, same catalogue baseline and same route set. They measure server-side generation in this file-mode environment, not internet/network latency.

## 1. Visual consistency audit

### Findings

1. Header/action icons used mixed Unicode glyphs such as search, location, theme, account, bag, heart and care symbols. Rendering varied by operating system/font and made alignment inconsistent.
2. Product cards/PDP care blocks mixed text symbols and iconography.
3. Compact buttons and icon controls had inconsistent visual dimensions.
4. Product PDP rendered separate mobile/desktop titles as two semantic `<h1>` elements.
5. Admin navigation had become visually dense as commerce, growth, intelligence, production and legacy modules accumulated.
6. Several customer-visible phrases still reflected the original furniture workflow.

### v1.6.1 fixes

- Added a first-party `ui_icon()` SVG helper and normalized the primary storefront icon set.
- Converted header Menu, Search, Visit, Theme, Account, Bag, Wishlist and plant-care icons to SVG.
- Added consistent `.ui-icon` geometry and button/icon alignment.
- Standardized major compact controls to a minimum 44px interactive height/area.
- Added consistent hover/active/disabled states.
- Kept one semantic H1 on PDP and converted the duplicate desktop visual title to H2 without changing the intended visual hierarchy.
- Reorganized Admin navigation into:
  - **Storefront & Commerce**
  - **Growth & Discovery**
  - **Operations**
  - **Projects & B2B**
  - **Advanced Workflows** (collapsed)
- Added active navigation state and `aria-current="page"` in Admin.
- Reworded customer checkout/passport copy from furniture/installation terminology to nursery/assisted-placement and plant-care terminology.

### Remaining visual dependency

The catalogue intentionally still has **0 approved product photographs** and **0 live SKUs**. Therefore the final visual quality of real plant cards, live variant price density, image crops and photography rhythm cannot be truthfully scored until real nursery media and stock are loaded. The MediaOS/photo readiness workflow remains the correct next input rather than generating fake imagery.

## 2. Responsive / viewability audit

The release contains desktop, tablet and mobile rules and retains the dedicated discovery/responsive regressions. Post-fix server-rendered HTML checks were run across:

- `/`
- `/shop`
- `/plant-finder`
- `/plants/low-light`
- `/collections/aglaonema`
- `/product/aglaonema-bai-kaw`
- `/materials`
- `/about`
- `/contact`
- `/privacy/preferences`
- `/cart`
- `/wishlist`
- `/account/login`

For every audited route:

- HTTP status: **200**
- H1 count: **1**
- Duplicate HTML IDs: **0**
- `<img>` elements missing `alt`: **0**
- Empty/unlabelled buttons detected by the DOM audit: **0**

The environment's automated Chromium/Playwright instance blocks localhost/file navigation with an administrator policy (`ERR_BLOCKED_BY_ADMINISTRATOR`). Those browser-runner failures were therefore treated as **audit-environment failures, not application failures**. Responsive verification was instead based on live HTTP rendering, DOM semantics, CSS breakpoint/touch-target inspection, the project's responsive regression suite and supplementary server-side render sanity checks. A real-device Chrome/Safari sweep should still be repeated after deployment with real photographs.

## 3. Accessibility audit

### Fixed

- Global keyboard-visible focus treatment for links, buttons, inputs, selects, textareas, summaries and tabbable elements.
- Consistent focus outline with sufficient spatial separation.
- One H1 per audited public page.
- No duplicate IDs across the audited route set.
- No missing `alt` attributes in rendered image elements across the audited route set.
- No detected unnamed buttons across the audited route set.
- Touch targets increased for compact primary controls.
- Existing `prefers-reduced-motion` support broadened for the new controls.
- Disabled buttons/controls use consistent visual treatment.
- Admin active page is exposed with `aria-current`.

### Follow-up after real media activation

- Validate every uploaded plant image alt description in context rather than merely requiring the attribute.
- Test color contrast against actual plant photography/overlays.
- Run VoiceOver/TalkBack/NVDA manual flows for variant selection, Plant Finder, cart drawer and consent controls.

## 4. Performance audit

### Root cause

The discovery catalogue evaluates facets for many products. v1.6.0 repeatedly reopened/redecoded the same JSON stores while sorting/filtering those products:

- Discovery settings
- Discovery product facets
- Live variant store
- MediaOS store

### Fix

v1.6.1 introduces **request-scoped in-memory caches** for those stores/facets, with invalidation/update on relevant write paths. No database/Redis dependency was introduced.

### Measured result

| Route | v1.6.0 median | v1.6.1 median | Reduction |
|---|---:|---:|---:|
| `/shop` | 443.26 ms | 7.09 ms | 98.4% |
| `/collections/aglaonema` | 82.67 ms | 5.91 ms | 92.9% |
| `/plants/low-light` | 70.80 ms | 6.41 ms | 90.9% |
| `/` | 7.74 ms | 4.28 ms | 44.7% |

### Asset delivery

- Built-in PHP router now serves assets correctly.
- CSS/JS URLs include `?v=1.6.1` cache busting.
- Nginx sample now applies 30-day immutable cache headers to versioned static assets.
- Final local CSS/JS asset responses: HTTP **200**, sub-millisecond server response in the audit runtime.

## 5. Code/runtime audit

### Validation performed

- PHP syntax: **134 files PASS**
- JavaScript syntax: **PASS**
- Nginx configuration syntax: **PASS**
- Full v1.6.1 regression: **PASS**
- Existing v1.6 Discovery regression: **PASS**
- Variant commerce regression: **PASS**
- Shopping Intelligence regression: **PASS**
- Growth/CMS/security/MediaOS regressions: **PASS**
- New `ui_performance` regression: **PASS**

### New regression specifically guards

- v1.6.1 version integrity
- SVG icon helper availability
- removal of old primary header Unicode icons
- single-H1 PDP semantics
- global focus visibility
- Admin grouped navigation CSS
- PHP-router asset serving
- Nginx static cache policy
- versioned CSS asset URL
- Discovery settings/facet request-cache consistency

## 6. Runtime audit

Final clean-baseline runtime checks:

| Route | HTTP |
|---|---:|
| Home | 200 |
| Catalogue | 200 |
| Plant Finder | 200 |
| Low-Light intent | 200 |
| Aglaonema collection | 200 |
| Aglaonema Bai Kaw PDP | 200 |
| Cart | 200 |
| Wishlist | 200 |
| Account Login | 200 |
| Privacy Preferences | 200 |
| `/health` | 200 |
| `/ready` | **200** |
| CSS asset | 200 |
| JS asset | 200 |

`/ready` result: **102 critical checks / 102 PASS / 0 failures**.

Clean `/health` baseline confirms:

- Version: **1.6.1**
- Products: **174**
- Variants: **0**
- Sellable variants: **0**
- Merchant variants: **0**
- Commerce encryption capability: **ready** under staging runtime keys
- Sandbox webhook capability: **ready** under staging runtime keys
- Intelligence profiles/events: **0 clean baseline**

Admin remains intentionally uninitialized in the distributed clean package.

## 7. Error/failure audit

### Fixed errors / hidden failure modes

1. **Static CSS/JS 404 under documented PHP server** — fixed.
2. **Duplicate product-page H1** — fixed.
3. **Repeated JSON reads causing catalogue latency** — fixed.
4. **Inconsistent OS-dependent icon glyphs** — fixed in primary user flows.
5. **Incomplete keyboard focus visibility** — fixed globally.
6. **Admin navigation overcrowding** — reorganized.
7. **Furniture-era customer copy in checkout/passport** — corrected.
8. **Weak cache-busting/static caching** — fixed.

### Intentionally not removed

Some original advanced workflow code (production/configurator/material-style modules) remains in the codebase for backward compatibility. It has been demoted under **Advanced Workflows** rather than deleted in a stabilization release. A future nursery-only architecture cleanup can remove modules after confirming no operational dependency exists.

## 8. Recommended next visual/performance priorities

### P0 — before public commerce launch

1. Add real approved product photography for the first 25–50 high-demand plants.
2. Add real live variants/prices/stock for those same plants.
3. Test the activated product cards/PDP/mobile cart on physical Android/iPhone/tablet devices.
4. Run Lighthouse/Chrome performance with real images over the real CDN/domain.
5. Add image width/height/aspect metadata and confirm LCP hero strategy once actual media exists.

### P1 — design-system consolidation

The stylesheet has accumulated many overlapping responsive breakpoints across the project's release history. It works, but future maintainability will improve if the visual tokens, components and breakpoints are consolidated into a smaller nursery design system rather than continually appending overrides.

Recommended canonical breakpoint family for a later refactor:

- Mobile: <= 640
- Tablet: 641–1023
- Desktop: >= 1024
- Wide: >= 1280

Do this as a controlled component refactor, not as part of v1.6.1 stabilization.

### P1 — real accessibility QA

After photography and live variants are active, run:

- Chrome/Android
- Safari/iOS
- Edge/Windows
- keyboard-only checkout
- VoiceOver/NVDA/TalkBack
- 200% zoom
- reduced motion
- high-contrast/forced-colors sanity check

## 9. Release decision

**v1.6.1 is suitable as the stabilized successor to v1.6.0.**

It improves viewability, icon/button consistency, semantic/accessibility quality, staging reliability and catalogue performance without weakening the project's zero-fabrication inventory rule or making checkout dependent on Discovery/Intelligence services.

The next largest quality improvement will come from **real nursery photography and live inventory**, not another cosmetic framework rewrite.
