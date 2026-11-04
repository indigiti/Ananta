# Sanjay Nursery v1.4.0 — Live Plant Variant Engine Build Audit

## Objective

Convert the 174 plant-variety catalogue from enquiry-only parent records into a commerce-ready architecture without inventing real nursery inventory.

## Architecture delivered

- Parent plant variety remains the canonical content/discovery entity.
- Live variant is the transactional inventory entity.
- Stable variant GUID + SKU are preserved in cart/order snapshots.
- Variant-specific pot size, plant-height range, grade, batch, price, inventory and media can be managed independently.
- Parent deletion is blocked while variants reference it.
- Once a parent has variants, cart insertion requires an explicit live variant.

## Commerce safety

A variant is checkout-ready only when all applicable checks pass:

- status = active
- sales mode = commerce
- price > 0
- availability = in_stock
- tracked stock > 0 when inventory tracking is enabled
- shipping class is supported by the current checkout

`pickup-only` is deliberately not considered sellable in v1.4.0 because a pickup checkout flow has not yet been implemented.

## Inventory/payment integrity

- Stock is re-checked before successful payment processing.
- Successful verified payment decrements tracked variant stock exactly once.
- Duplicate webhook events do not decrement stock twice.
- Immutable order items retain variant GUID/SKU, parent SKU, batch, pot size, height range, grade and price.

## Search / Merchant / API

- Variant parents emit `ProductGroup` structured data with `hasVariant` child products.
- Public and AI product DTOs expose sanitized variant state.
- Merchant rows are generated per live variant, not from the generic parent.
- Merchant eligibility additionally requires an approved image and explicit `merchant_enabled`.
- Zero-price parent rows remain excluded.

## Admin operations

- Central Plant Variants dashboard.
- Per-parent create/edit/delete UI.
- Browser CSV import/update.
- Import supports draft/enquiry staging before a batch becomes sellable.
- Product edit screen summarizes variant count, sellable rows and tracked stock.

## Clean release baseline

- 174 parent products preserved.
- 18 plant collections preserved.
- 0 pre-populated live variants.
- 0 fabricated pot-size prices.
- 0 fabricated stock.

## Regression coverage

The v1.4.0 suite verifies variant validation, identity uniqueness, parent references, ProductGroup schema, DTO safety, Merchant image gate, CSV import, variant-aware cart, immutable order snapshots, stock decrement idempotency, payment controls, growth checks and the prior v1.3.0 platform safeguards.
