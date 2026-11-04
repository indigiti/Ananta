# ANANTA — AI-Native Nursery Commerce Network
## Product & Architecture Blueprint v2.0 (file-first)
**Status:** Proposed architecture / implementation roadmap — NOT a completed feature release  
**Date:** 2026-11-04  
**Target repository:** indigiti/Ananta, current v1.7.0 procedural PHP staging codebase  
**Initial constraint:** Remain **database-free** for a controlled, single-server pilot; do not require Laravel, MySQL, PostgreSQL, Redis or Node.js runtime on day one.

> This document supersedes earlier "SQL first / Laravel first" architecture recommendations **for the pilot only**. The previous multi-tenant transactional design is the intended migration target, not an initial hosting requirement. Existing v1.7.0 modules and routes remain unchanged unless separately implemented and tested. This is a blueprint, not a claim that new products have shipped.

## 1. Product thesis and operating entities

**Mission:** Become the network where reliable nursery transactions happen, not a static directory and not a generic AI chatbot.

- **Ananta Mall:** Public nursery discovery, branded seller storefronts, plant listings, retail enquiry and (later) checkout.
- **NurserySetu AI / SupplierOS:** Each nursery's operational cockpit for branch profiles, catalogue, stock evidence, leads, wholesale quotations and follow-up tasks.
- **Ananta Trade:** B2B BOQ/RFQ normalization, matching, invitations, multi-supplier quotation comparison and buyer-approved purchase orders.
- **Ananta Agent Network:** Verified product/availability feeds, authenticated agent tools and later ACP/UCP/provider adapters where partners support them.
- **Ananta Connect:** Permissioned WhatsApp messaging with audited delivery events and opt-in/opt-out enforcement.
- **Ananta Control:** Platform verification, moderation, subscription and usage accounting, system health and audit.

**Launch wedge:** First make the Pune–Solapur nursery corridor commercially useful for landscapers, plant buyers and suppliers. A target count of 2,000 nurseries is an unverified market hypothesis, NOT an existing verified Ananta supplier count.

## 2. Core principles

1. **File-first, evidence-first, human-approved:** Start with the proven PHP file foundation. Never invent live prices, stock, seller verification, delivery or plant safety claims.
2. **One source of truth per domain:** Canonical plant taxonomy is platform-owned; offer/stock/quotation truth belongs to its nursery; RFQ and matching lifecycle belongs to Ananta; transactions retain immutable snapshots.
3. **Separation of intent from execution:** AI may parse requirements, draft copy, recommend candidates and explain comparisons. It must not silently create binding orders, transfer money, publish sensitive claims or message suppliers without approval.
4. **Merchant-owned records remain isolated:** Cross-tenant access is through explicit broker operations, not directory traversal or direct file reads by tenant controllers.
5. **Portable adapters:** Define versioned interfaces before integrating external agent formats, messaging providers or payment gateways.
6. **Design for measured upgrades:** Database migration happens when correctness needs it, not merely because vendor marketing promises greater speed.

## 3. User experiences that must work

| User | Initial pilot journey | Later phase |
|---|---|---|
| Visitor | Choose location, search plants/nurseries, view verified profile, enquire | Direct checkout, saved carts |
| Retail buyer | Discover plant by intent, check supplier's latest stated availability, contact / request offer | Single-supplier purchase with payment |
| Landscaper / procurement buyer | Upload CSV/XLSX/PDF BOQ or type request; review extracted lines; shortlist sellers; send RFQ; compare delivered quotations | Staged fulfilment, automated purchase-order and payment |
| Nursery owner | Claim/register business, verify, publish catalogue, update stock freshness, respond to leads/quotes | Subscriptions, deep CRM, multi-branch operations |
| Nursery sales staff | View only assigned tenant/branch leads and conversations; send approved quotes | Automated follow-up suggestions |
| AI shopping agent | Search published structured offers, request quote draft, check freshness and service areas | Authorized agent transaction standards after partner approval |
| Platform team | Verify suppliers, review listings, inspect incidents and audit logs with role scopes | Finance/disputes/settlements |

**UI strategy:** Premium botanical commerce, mobile-first, lightweight public pages; action-oriented supplier dashboard rather than a sea of charts. Use the existing theme tokens and modular templates before a disruptive UI rewrite. Local-language labels and supplier-friendly mobile workflows are high priority.

## 4. File-first platform structure

A single Cloudways-compatible PHP application, one authoritative filesystem, web-only admin setup and bounded web/cron jobs. Do **not** deploy multiple concurrent write-serving instances against separate local disks. No database required for pilot.

Logical modules, not a claim about existing folders:

    Identity + Tenancy → Directory + Canonical Catalogue
             ↘ Supplier Profiles + Listings + Inventory Evidence
               → Trade RFQ Broker → Quotes → Buyer Approval
               → WhatsApp Transport + Event Receipts
               → Governed AI Orchestrator
               → Public Feeds / Agent Capability API

Existing files to adapt incrementally:
- `src/foundation.php`: locks, optimistic checksum, atomic write, journal and confidential encryption helpers.
- `src/admin.php`: UUIDv7, storage and admin foundation; refactor single-brand administrative assumptions.
- `src/variants.php`: plant variants and truth-preserving commercial eligibility.
- `src/discovery.php`: plant search and facets; add seller/location/availability evidence.
- `src/tradeos.php`: professional projects; extend to tenant-scoped supplier quotations and RFQs.
- `src/commerce.php`: preserve legacy single-seller checkout behavior, do not turn on marketplace payments without reconciliation.
- `src/ai_engine.php`: modes, field protections, review/rollback and provider budget logic.
- `src/integrations.php`: existing discovery endpoints; add versioned capability contracts.
- `src/monitor.php`: health, incident retention, recovery indicators.

**No direct runtime changes in this blueprint commit.**

## 5. Data ownership and identity

Use UUIDv7 for every new entity and preserve stable UUID strings at HTTP boundaries. Core entities:
- Platform: `tenants`, `tenant_domains`, `users`, `memberships`, `supplier_reviews`, `plant_taxa`, `catalogue_aliases`, `rfqs`, `rfq_dispatches`, `buyer_accounts`, `audit_events`.
- Tenant: `branches`, `seller_profiles`, `listings`, `variants`, `availability_claims`, `price_books`, `stock_batches`, `leads`, `quotes`, `conversations`, `delivery_zones`.
- Operations: `outbox_events`, `inbox_dedup`, `jobs`, `ai_reviews`, `ai_usage`, `message_status_history`, `reconciliation_runs`.
- Phase-gated: `orders`, `payments`, `refunds`, `settlement_ledger`, `inventory_reservations` **must not be made cross-tenant transactional in the file-only pilot**.

Single public product identity = canonical taxon/product. Its many seller listings = seller-controlled offers that may differ by pot size, height, maturity, grade, lead time and lot. A public listing may be enquiry-only and must disclose stock freshness.

Roles: platform superadmin, platform operations, supplier owner, supplier manager, branch staff (sales/inventory), buyer, trade buyer. Memberships are explicit; a nursery can also be a buyer. RBAC is enforced in server-side policy and each data repository call, not by hiding sidebar links.

## 6. AI-native product strategy

**P0/P1 — valuable immediately:** multilingual typed or spoken request input (voice via optional provider), BOQ import/extraction with row-level confidence, botanical synonym normalization, evidence-based supplier eligibility ranking and quotation comparison. Require buyer review before transmitting a parsed RFQ.

**P2 — supplier copilot:** draft product details and quote messages, summarize enquiries, flag missing listing evidence, recommend tasks and stock refreshes. Models are advisory; protected fields (price, stock, tax, safety, delivery, reviews, supplier verification) are human-verified only.

**P3 — external agents:** publish structured machine-readable catalogue metadata and capability documents, with OAuth/scoped tokens for non-public operations; expose `search_offers`, `check_offer_freshness`, `draft_rfq`, `get_quote_status` as typed, permission-controlled tools. External ACP/UCP support is via adapters **only when current external specifications and partner onboarding allow it**. Do not imply registration with, approval from, or distribution on third-party AI surfaces.

**P4+ — optional visual intelligence:** suggest plant taxonomy and inspect media completeness. Do not infer exact grade, health, stock count or legal safety claims solely from images. Autonomous purchases and unattended pricing remain out of scope until controls are proven.

AI response contract: include `source_record_ids`, `data_as_of`, `availability_status` (CONFIRMED / REPORTED / UNKNOWN / EXPIRED), `requires_confirmation`, and any `review_flags`. A model's confidence score is never a substitute for supplier evidence.

## 7. File-first business flows

### A. Verified supplier onboarding
1. Supplier registers, verified contact and business identity recorded in private storage.
2. Platform checks claim/duplicates and approves public profile.
3. Supplier creates branch/service area and first 10 listings with photos.
4. System stores published redacted projections separately from private contact and verification documents.
5. Staff updates stock/price and freshness timestamp. Unknown availability remains explicitly unknown.

### B. Buyer RFQ / AI sourcing
1. Buyer submits requirement file or text; validate size/type and scan uploads.
2. AI creates **draft** BOQ rows; normalize plant names and highlight ambiguity.
3. Buyer confirms quantity, size, location and dispatch authorization.
4. RFQ broker checks published eligibility, geographic serviceability, seller invitation policy and opt-in.
5. Supplier receives an invitation event and submits a versioned quote into its own tenant aggregate.
6. Broker stores a signed/referenceable accepted quote snapshot and offers side-by-side landed cost comparison.
7. Buyer approves a supplier-specific PO; fulfilment and payment remain manually coordinated and explicitly marked until transactional infrastructure is available.

### C. WhatsApp communications
Use a `MessagingProvider` interface, with official WhatsApp Business API the preferred production transport; optional WaBridge only behind explicit noncritical/testing feature flags. Record queue/submission/provider-sent/delivered/read/failed events separately. Require provider signature verification and deduplication, tenant phone-number mapping, approved templates and opt-in records. **Queued != delivered.**

### D. Retail checkout
Pilot: enquiry or quote-to-PO only. A **single-supplier** direct-order pilot may be designed after an audited per-order state machine and payment reconciliation, but no automatic cross-nursery cart, payment splitting or stock reservation in file mode. Customer-facing UI cannot promise guaranteed inventory until a seller has confirmed it.

## 8. Agent-compatible capability APIs (proposed, not implemented)

Public:
- `GET /api/v1/plants?query=&locale=` — canonical plant information, paginated.
- `GET /api/v1/suppliers?region=&verified=` — moderated supplier list.
- `GET /api/v1/offers?plant=&location=&sales_mode=` — seller-specific published offers.
- `GET /.well-known/agent-capabilities.json` — existing endpoint, version as capabilities evolve.
- `GET /feeds/agent-products.json` — extend existing truth-bound feed with seller context only when verified.

Authenticated / role-scoped:
- `POST /api/v1/rfqs/drafts` — parse/validate; **does not dispatch**.
- `POST /api/v1/rfqs/{id}/dispatch` — buyer confirmation; idempotent broker operation.
- `GET /api/v1/rfqs/{id}/quotes` — buyer or authorized invited supplier's constrained view.
- `POST /api/v1/merchant/quotes` — signed-in supplier member within tenant.
- `POST /api/v1/merchant/listings/{id}/availability` — verified supplier update.
- `POST /api/v1/ai/assist` — scoped, rate-limited and budgeted.

Cross-module events: `merchant.verified`, `offer.published`, `availability.updated`, `rfq.dispatched`, `quote.submitted`, `quote.accepted`, `whatsapp.status.changed`. Envelope: `event_id` UUIDv7, `aggregate_id`, `tenant_id` (where relevant), `event_type`, `version`, `occurred_at`, `correlation_id`, `idempotency_key`, and payload with protected data redacted where forwarded. Internal dispatch must be idempotent and eventually reconcilable.

## 9. Revenue model (hypotheses, not forecasts)

Start with a free verified directory listing. Test paid supplier subscriptions for CRM, quotation workflows, team collaboration and operational analytics; charge transparently for optional premium placement or qualified RFQ volume. Usage-based AI and WhatsApp fees must be observable and controllable. Defer commission/split-settlement models until seller-of-record, invoicing, refund and applicable Indian tax responsibilities are agreed and a payment provider approves the arrangement.

Illustrative test tiers: Free / Grow / Pro / Business. Validate actual willingness to pay, onboarding cost, support overhead, lead quality and renewal before committing to price points.

## 10. Stage gates and success measures

Pilot hypotheses (not current stats): 100 verified profiles, 40 usable catalogues, 25 RFQ-responsive suppliers, 15 paying suppliers and 10 completed sourcing transactions. Those targets must not be reported as actual.

Decision-critical pilot metrics:
- Share of qualified RFQs with at least one response in one business day.
- Share of RFQs with truly comparable quantity/size/grade/landed-cost quotes.
- Supplier response latency and stock verification age.
- Extraction correction rate on a hand-labeled BOQ test corpus.
- Quote acceptance, fulfilment and damage/cancellation rates.
- Weekly active suppliers and paid retention.
- Per-tenant operational cost and support burden.

Promotion to **SQL-backed transactional commerce** becomes mandatory when any of these occurs:
- Cross-supplier atomic inventory/payment/settlement workflows are introduced.
- Horizontal writer replicas or multi-region writes are needed.
- File lock latency, queue backlog, restore time or mutation concurrency breaches measured SLOs.
- Recovery/reconciliation cannot prove data correctness from append-only records.
- The pilot grows beyond tested filesystem and operational limits.

Do not postpone migration merely to preserve the "no database" label.

## 11. Phased delivery

| Phase | Delivery | Acceptance gate |
|---|---|---|
| P0 | Security containment; no-DB storage boundaries, tenant/membership policies, operational test harness, redacted public views | No tracked runtime secrets; tenant-isolation tests; backups restore |
| P1 | Nursery profiles, storefronts, catalogue variants, evidence/freshness, search and manual enquiries | First real suppliers publish controlled verified data |
| P2 | AI BOQ draft/review, RFQ broker, scoped supplier quotes, comparisons, buyer-approved POs | End-to-end multi-supplier sourcing without phantom inventory |
| P3 | WhatsApp provider adapter, consent/status webhooks, supplier CRM/collaboration, first paid plan experiments | Delivery event reconciliation; measured supplier engagement |
| P4 | Agent capability adapters, supplier AI copilot, external discovery feed validation | Typed tool permissions; human approval and zero unverified commerce claims |
| P5 | SQL migration and transactional checkout/settlements if justified, high-scale search and automated operations | Concurrency, payment reconciliation, load and restore tests pass |

**Prioritization note:** Build the minimum AI-assisted sourcing loop in P2, not a generic chat experience. Standards-readiness belongs in APIs early; expensive partner-specific adapters wait until accepted integration pathways exist.

## 12. Explicit non-goals during file-only pilot

- No microservices, multiple independently writable databases or mandatory Redis.
- No unsupported "instant checkout inside ChatGPT/Gemini" claims.
- No autonomous payments, payouts, refunds, stock changes or public botanical/safety edits.
- No automated multi-supplier settlement or guaranteed stock promises.
- No public exposure of tenant private files, personal contact databases, provider keys or unredacted conversations.
- No fabricated nursery counts, revenue forecasts, live prices or delivery SLA.

## 13. Reference and implementation next steps

See [FILE_FIRST_PILOT_ARCHITECTURE.md](./FILE_FIRST_PILOT_ARCHITECTURE.md) for exact storage layout, transaction discipline, tenant isolation, backup/recovery, no-DB capacity gates and SQL migration contract. See [IMPLEMENTATION_BACKLOG_FILE_FIRST.md](./IMPLEMENTATION_BACKLOG_FILE_FIRST.md) for acceptance-focused work items.

**Baseline reality:** The repo presently contains a functional single-brand PHP staging application with 376 parent plants and AI/variant/discovery modules. Multi-supplier SaaS, verified supplier network, multi-tenant isolation and marketplace checkout are **planned**, not delivered.
