# Sanjay Nursery v1.6.4 — Storefront UI/UX + Light/Dark Theme Audit

## Audit scope

Reviewed the supplied storefront header screenshot together with `templates/layout.php`, `public/assets/css/app.css`, `public/assets/js/app.js`, the shared SVG icon helper and responsive breakpoints. The audit focuses on the public nursery storefront; the Admin OS remains intentionally light and operational in this release.

## Pre-audit findings

### 1. Header utility area was visually crowded

The desktop header reserved about `330px` for Visit, Theme, Sign in/Account and Bag. Every action repeated an icon plus text, while the search control also repeated a search icon and the word “Search”. This compressed the search field and made the utility group visually heavier than the primary category navigation.

**Resolution:** Visit, Theme, Account and Bag are now icon-only. Each has a 42px circular target, accessible `aria-label`, desktop tooltip, focus state and active state. Desktop search submit is icon-only while the input placeholder retains the text cue.

### 2. Icon geometry and alignment were inconsistent

The previous SVG itself carried the border/background circle while the surrounding action remained a text-based flex row. That made visual centering depend on the adjacent label and created inconsistent optical spacing.

**Resolution:** the clickable control is now the circle. SVGs are clean 19px glyphs inside a consistent 42px control. Bag count is an anchored badge rather than part of the text label.

### 3. Dark mode changed tokens but left major surfaces light

The page had dark root variables, but the retail theme later forced numerous surfaces with hard-coded values such as `#fff`, `#fafbf9`, `#f8faf7`, `#173f35` and light borders. As a result, toggling dark mode could produce a dark page body with white navigation/cards/forms and mismatched text colors.

**Resolution:** v1.6.4 adds a coherent dark surface system for the public storefront covering:

- header, search and category navigation;
- utility icons and mobile menu;
- category/trust/content sections;
- collection and catalogue controls;
- discovery search, filters and sort controls;
- Plant Finder cards and choices;
- live variant cards and availability controls;
- newsletter and consent UI;
- contact/care/blog cards;
- pagination, collection chips and common commerce panels.

Commerce aliases (`--panel`, `--surface`, `--text`, `--accent`, `--soft`) now resolve to the active storefront theme so account/cart/checkout components inherit the same mode rather than falling back to transparent/undefined custom properties.

### 4. Theme could flash light before switching dark

`<html>` was emitted as light, while the saved theme was applied only by the deferred application JavaScript at the end of the page. A returning dark-mode visitor could therefore see a light flash before dark mode was applied.

**Resolution:** a tiny nonce-protected bootstrap runs in `<head>` before the stylesheet paints. It uses the saved preference when present and otherwise follows `prefers-color-scheme`.

### 5. Theme control disappeared at tablet widths

At widths below 1180px the previous stylesheet hid the desktop Theme action, while the mobile menu did not become the primary control until 820px. This created an 821–1180px range without an obvious theme switch.

**Resolution:** the icon-only theme control remains available through tablet/compact desktop widths. On mobile, Theme is available in the menu with a dynamic “Dark mode / Light mode” label.

### 6. Mobile commerce priority was wrong

Below 820px all utility actions were hidden except Account. Bag was therefore removed from the top-level header even though it is a high-intent commerce action.

**Resolution:** mobile now keeps **Account + Bag** visible. Theme moves to the menu, and Visit remains available as a menu item.

## Theme behavior after the update

- Saved user preference persists with `localStorage`.
- First visit follows OS/browser light/dark preference.
- Theme toggle updates `aria-pressed` and its accessible action label.
- Moon is shown in light mode; Sun is shown in dark mode to indicate the available switch.
- Browser `theme-color` changes with the active mode.
- Native form controls opt into the active `color-scheme`.
- Existing `prefers-reduced-motion` behavior remains intact.

## Contrast spot-check

Key palette pairs were checked mathematically:

| Pair | Contrast |
|---|---:|
| Light primary `#173f35` on `#ffffff` | 11.67:1 |
| Light muted `#69716b` on `#ffffff` | 5.03:1 |
| Dark primary `#edf4ef` on `#101813` | 16.17:1 |
| Dark muted `#aab8b0` on `#101813` | 8.77:1 |
| Dark CTA text `#102019` on `#a8cdb1` | 9.68:1 |

These key combinations clear WCAG AA for normal text. Decorative botanical/image-like gradients are intentionally not inverted; interactive text/control surfaces are the priority for theme parity.

## Post-audit implementation summary

| Area | Before | v1.6.4 |
|---|---|---|
| Header utilities | Icon + text | Icon-only + tooltip + ARIA |
| Utility target | Mixed visual hit area | 40–42px circular target |
| Search submit | Icon + “Search” | Compact icon-only submit |
| Theme initialization | Deferred | Pre-paint head bootstrap |
| Default first visit | Always light | System preference |
| Stored preference | Supported | Supported + synchronized UI |
| Dark storefront | Partial / mixed surfaces | Coherent core storefront surfaces |
| Tablet theme control | Hidden below 1180px | Retained |
| Mobile top utilities | Account only | Account + Bag |
| Theme icon state | Static | Moon/Sun state cue |

## Recommended next UI/UX improvements

### P1 — high impact

1. **Smart sticky header:** compact the header after 60–90px scroll, while keeping search and Bag accessible. This gives product grids more viewport height without losing navigation.
2. **Search command experience:** expand the existing search intelligence into a true predictive panel with product thumbnail, plant family, price/availability state, “recent searches”, and keyboard arrow/Enter navigation.
3. **Mobile bottom commerce navigation:** Home, Search, Plant Finder, Wishlist and Bag. This is stronger than hiding high-intent actions inside the hamburger menu on one-handed mobile use.
4. **Cart mini-drawer:** after Add to Bag, show a lightweight side/bottom drawer with selected plant, quantity, subtotal and Checkout/Continue buttons instead of forcing a page transition.
5. **Real photography state:** replace botanical placeholders progressively with consistent nursery photography (same crop ratio, lighting, background discipline and image focal-point metadata).

### P2 — conversion and discovery

6. **Visual mega menu:** desktop category menu with 4–6 image/category tiles and “Shop by light / room / care level” shortcuts rather than a single long text row.
7. **Quick-view / quick-add:** allow users to choose live pot-size variants from collection cards when the product is checkout-ready.
8. **Persistent delivery pincode:** remember the chosen pincode and show “Deliverable to …” consistently on cards/PDP/cart, with a clear edit action.
9. **Recently viewed rail:** privacy-safe browser/session rail for plant comparison and return navigation.
10. **Compare plants:** 2–4 plant comparison for light, watering, mature size, pet-safety status, placement and current live variant price.

### P3 — refinement

11. **Micro-interaction system:** standardize 120/180/240ms motion tokens for hover, drawer, filter sheet, toasts and cart feedback; keep the existing reduced-motion override.
12. **Skeleton and transition states:** for recommendation/search endpoints, use small structural skeletons rather than empty jumps.
13. **Unified empty states:** consistent icon, explanation and primary/secondary action across Search, Wishlist, Cart, Finder and Account.
14. **Header/nav active-state logic:** highlight current collection/Plant Finder/Care route in the category row and expose `aria-current="page"`.
15. **Accessibility pass:** automated contrast/landmark checks plus keyboard testing of predictive search, filter sheet, mobile menu, cart drawer and modal focus trapping.

## Verification

- PHP syntax validation: passed.
- CSS parser validation: no stylesheet parse errors.
- Theme/UI static regression: passed.
- Full project regression suite: passed after the v1.6.4 changes.
- Existing Web Operations / System Health / Error Monitor behavior is retained.
