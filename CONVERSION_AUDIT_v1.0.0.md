# Sanjay Nursery Conversion Audit — v1.0.0

## Scope

Converted the supplied marketplace staging build into a Sanjay Nursery baseline while removing all active legacy product records and product media.

## Data reset

| Area | Result |
|---|---:|
| Active products | 0 |
| Configurator rules | 0 |
| Product media mappings | 0 |
| Legacy sample CMS entries | 0 |
| Legacy product images | Removed |
| Nursery categories | 6 |

## Public UX conversion

- Header, footer and mobile navigation use Sanjay Nursery naming and nursery categories.
- Home page is plant/nursery focused and contains no legacy product cards or product imagery.
- Catalogue page has an intentional zero-product state and category navigation.
- Plant Care replaces the former material-library positioning.
- About and Contact pages are nursery specific.
- Account navigation is reduced to product-independent Overview, Addresses and Orders while the catalogue is empty.
- Product-specific wishlist/cart controls are hidden in the public header until products exist.

## Technical conversion

- App name/version: `Sanjay Nursery` / `1.0.0`.
- Order prefixes: `SN` / `SNI`.
- Environment variable namespace changed to `SANJAY_*`.
- Confidential persistence contexts use the `sanjaynursery:` namespace.
- Session name uses `sanjaynursery_staging`.
- Nginx sample deployment path updated to `sanjay_nursery_staging_v1.0.0/public`.
- Theme registry replaced with nursery-specific presets.
- Obsolete Sacred Spaces collection route redirects to the main catalogue.

## Validation result

- PHP lint: PASS.
- Clean-baseline regression suite: PASS.
- HTTP runtime routes: PASS.
- `/health`: PASS, reports `products: 0` and `configurator_rules: 0`.
- `/ready`: PASS with no failed critical checks.

## Deferred until product import

Cart/order placement, product configurator, bespoke/project-product flows, MakeOS product production, Product Passport issuance and product-linked TradeOS scenarios require at least one approved product. These engines remain in source but are not activated by the empty catalogue.
