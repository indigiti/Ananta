# Ananta ACP.1–ACP.3: Agentic Commerce Protocol implementation

**Reference:** Latest ACP stable checkout OpenAPI version `2026-04-17` and OpenAI native file-upload products specification, checked on 5 November 2026.
**Operational status:** staging protocol implementation only. Production Instant Checkout, partner certification and live payments are NOT available.

## ACP.1 — Product feed

- Source: `discover_merchant_rows()` after the existing B8-G eligibility verification. Products must have a real checkout price, tracked stock, approved image, legal seller and recent SKU evidence; enquiry-only records are excluded.
- Native columns include `item_id,title,description,url,brand,seller_name,seller_url,image_url,price,availability,target_countries,store_country,is_eligible_search,is_eligible_checkout,gtin,mpn,group_id,product_category,condition`.
- `is_eligible_search=true` and `is_eligible_checkout=false` are explicit. The latter is not enabled until the partner, PSP, merchant country, buyer protection and technical checkout pass independent acceptance. Only accepted and configured partner feeds can be displayed by ChatGPT. A native CSV export is NOT a partner registration.
- Obtain admin-only export at `/ananta/admin/acp/products.csv` through `/ananta/admin/acp`. Zero eligible rows cause a clean error instead of an empty partner submission.
- Supported market in this staged export is `IN`/`INR`. OpenAI partner acceptance for Indian merchants is not presumed or represented as confirmed.

## ACP.2 — Authenticated checkout protocol

Route prefix: `https://YOUR-HTTPS-MERCHANT-HOST/ananta/acp`. Implemented operations:

| Method | Route | Pilot behavior |
| --- | --- | --- |
| POST | `/checkout_sessions` | 201, create an encrypted UUIDv7 30-minute session with authoritative SKU and shipping quotes |
| GET | `/checkout_sessions/{uuid}` | 200, retrieve latest state |
| POST | `/checkout_sessions/{uuid}` | 200, update items, buyer or Indian address with idempotency |
| POST | `/checkout_sessions/{uuid}/cancel` | 200, terminal cancel |
| POST | `/checkout_sessions/{uuid}/complete` | 503, fail closed pending certification and SQL order/stock transaction |

Transport security: enable only on a staging host with HTTPS, set `ANANTA_ACP_ENABLE_STAGING_API=1`, `ANANTA_ACP_API_KEY` (>=32 random characters) and `ANANTA_ACP_HMAC_SECRET` (>=32 random characters) in private Cloudways environment. Each request requires Authorization bearer, API-Version `2026-04-17`, fresh RFC3339 Timestamp (within five minutes), and base64 HMAC-SHA256 Signature over the **exact raw request body**. Mutations require a stable `Idempotency-Key`. Note: signature canonicalization and merchant partner authorization must be confirmed in OpenAI partner onboarding before production.

Create request example (only actual Merchant-eligible SKU IDs are usable):

```json
{
  "line_items": [{"id": "REVIEWED-SKU", "quantity": 1}],
  "currency": "inr",
  "capabilities": {}
}
```

Responses include required checkout id, protocol version, `not_ready_for_payment`, monetary totals in INR minor units, fulfillment options, messages, links and capability negotiation. No checkout session is ever promoted to `ready_for_payment` by flags or user input. Buyer and address data are encrypted in the private session ledger, outside public_html. Sessions expire after 30 minutes, and the pilot enforces explicit capacity limits and deduplicated idempotency keys.

Order/cart security: SKU price is never taken from an agent-supplied `unit_amount`; it is sourced from current checked inventory and Merchant records. Delivery is quoted from Ananta's actual zone configuration and Indian six-digit PINs. Tax breakdown remains provisional because plant-level GST compliance and financial rounding are not yet certified. Quote totals are NOT a binding invoice.

## ACP.3 — Stripe test adapter and order webhooks

`acp_psp_validate_payment_data()` accepts only structured `handler_id=ananta-stripe-test-card` with `instrument.type=card`, `credential.type=spt` and a provider-issued `spt_*` token. It rejects raw card fields. `acp_stripe_test_authorize()` is an **explicitly called** test-only method; it requires `ANANTA_ACP_STRIPE_TEST_ONLY=1` and a `sk_test_` Stripe secret and requests a Stripe PaymentIntent with `capture_method=manual`, `confirm=true`, and delegated-payment card parameters. It only reports a test authorization when Stripe returns `requires_capture`. No automatic calls or captures are made from ACP checkout completion. A test PaymentIntent must be canceled manually in the PSP dashboard.

A private encrypted order-event outbox supports HMAC-signed `order_created` and `order_updated` delivery. `ANANTA_ACP_EVENT_WEBHOOK_ENABLE=1` additionally requires an allowlisted HTTPS URL (`ANANTA_ACP_EVENT_WEBHOOK_URL` and `ANANTA_ACP_EVENT_WEBHOOK_ALLOWED_HOST`) and `ANANTA_ACP_EVENT_WEBHOOK_SECRET`. Only orders linked to a genuinely completed ACP session can queue events. As no agentic orders can be completed in this staging release, the real outbox remains empty. Network ambiguity or expired send leases require reconciliation; they are not blindly retried.

CLI status: `php src/acp_cli.php status`. Controlled outbox worker: `php src/acp_cli.php events-worker`. Never run a payment capture through cron. The existing B8 encrypted backup includes ACP sessions and event ledger (verify recovery before operational activation).

## Remaining mandatory production gates

1. **OpenAI partner review and India merchant/INR availability:** verified merchant account, accepted product feed and conformance access.
2. **Retail inventory:** authenticated Cloudways reconciliation, licensed photography, accurate SKU pricing, availability, delivery zones and verified product tax/GST treatment.
3. **Atomic SQL stock reservation and order ledger:** single source of truth for all storefront/ACP checkout channels, unique paid order IDs, reserved stock with TTL, cancellations and reliable outbox commits. Existing file-first checkout/sandbox payment is insufficient.
4. **Supported Indian payment PSP/delegated-payment scheme:** merchant onboarding, ownership, allowlisted handler and capture/refund authentication, 3DS/SCA and webhook reconciliation. No production PSP or credentials were available for this implementation.
5. **Protocol certification:** partner signature handshake, schema validation against ACP 2026-04-17 OpenAPI, security pen test, fraud controls, idempotency collisions, provider webhooks, delivery updates, tax/returns and full device/checkout acceptance.

**Release meaning:** an implemented and CI-tested *staging protocol integration*, **not** a certification claim or live agentic-commerce launch. Do not disable the hard-coded ACP live checkout safety gate until all prerequisites have independent evidence and a reviewed production architecture.
