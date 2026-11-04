# Multi-vendor “Buy from” pilot — Ananta
**Release scope:** File-first, admin-curated nursery offers, no database.  
**Status:** Implementation code in this release; supplier onboarding and multi-vendor checkout are **not** automated.

## Shopper experience
- Ananta premium typography, botanical green/ivory palette, intent-aware catalogue search and mobile shortcuts.
- `/nurseries` lists the founding nursery and approved independent sellers.
- `/nurseries/{supplier_uuid}` shows published independent nursery offers.
- `/product/{slug}#buy-from` shows founding-nursery checkout-ready options (only when existing live variants are sellable), plus independently curated external offers.
- External offers have the action **Ask nursery on WhatsApp**; this opens a prefilled `wa.me` link without sending a message automatically.
- `GET /api/v1/products/{slug}/offers` returns only published offers belonging to verified independent sellers, with no personal phone numbers or internal evidence references.

## Administration
- `/admin/marketplace` has admin-only supplier registry and offer management. Edit links prefill the corresponding record.
- Supplier status: `draft`, `verified`, `suspended`. Only verified suppliers with an allowed public phone can have visible published offers.
- Offer state: `draft`, `published`, `withdrawn` plus availability `unknown`, `reported`, `confirmed`, `unavailable`.
- Confirmed availability requires a quantity > 0 and an internal evidence reference. Seven days after the last admin check, confirmed/reported status becomes **Check availability**.
- Supplier identities, phones, offer definitions and internal evidence references are stored outside `public/`; only whitelisted projections reach the public API.
- IDs use UUIDv7. Mutations run under existing `foundation_json_mutate` file locks and audit events.

## Limitations and safety
- The pilot uses one authoritative JSON registry; supplier-side independent accounts and tenant isolation are not yet implemented.
- Offers are **indicative**, not payment-ready or guaranteed stock. Never attach external supplier variants to existing Sanjay Nursery basket or payment paths.
- No price, stock, supplier or product images are fabricated. When no independent seller has been verified, the nursery directory explains that clearly.
- Photos are from the actual approved catalogue only; no synthetic product photos are substituted.
- The founding nursery's own cart and checkout paths remain unchanged. Its current code remains staging/file-mode only and is not certified for production settlement.
- No WhatsApp Business API is required to open seller enquiry links. This feature does not add WhatsApp automation or delivery-status tracking.
- Typography uses CSS system font fallbacks so no new third-party runtime font requests or CSP changes are required.
- Before a real supplier pilot, complete P0 secret and tracked-runtime-data review, backup restoration testing, phone publishing consent and tenancy isolation. No automated multi-seller settlement.

## Verification
Run `php tests/marketplace_offers.php` and the existing `bash scripts/full_regression.sh` on an isolated working copy. Perform responsive/manual checks at widths 320, 390, 768, 1024 and 1440px. Use two real verified supplier records and test draft/published/withdrawn, confirmed/expired availability, visibility, links and authorization. No P0 security or live deployment claim is made by this feature change.
