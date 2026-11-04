# Ananta — AI-Native Nursery Commerce Blueprint

> **Design document, not shipped functionality.** The current v1.7.0 PHP application is a single-brand staging build. Multi-nursery SaaS, supplier tenants, the wholesale RFQ broker and WhatsApp messaging integrations described below are future implementation work. The file-first pilot is conditional and must pass the security, durability and capacity gates before onboarding real suppliers.

## Document map

- [Part I: Product blueprint](#part-i-product-blueprint)
- [Part II: File-first pilot architecture](#part-ii-file-first-pilot-architecture)
- [Part III: Implementation backlog](#part-iii-implementation-backlog)
- [Part IV: Implemented Ananta storefront and seller-offer pilot](#part-iv-implemented-ananta-storefront-and-seller-offer-pilot)
- [Part V: Release A — tenant identity and repository containment](#part-v-release-a--tenant-identity-and-repository-containment-implementation)
- [Part VI: Release A2 — persistent uploaded plant media](#part-vi-release-a2--persistent-uploaded-plant-media)
- [Part VII: Release B1 — supplier branches and stock review](#part-vii-release-b1--supplier-branches-private-locations-and-stock-review)
- [Part VIII: Release B2 — SupplierOS styling and CSV import](#part-viii-release-b2--supplieros-styling-and-csv-to-draft-imports)
- [Part IX: Release B3 — private buyer sourcing and nursery quotations](#part-ix-release-b3--private-buyer-sourcing-nursery-inbox-and-indicative-quotations)

---

## Part I: Product Blueprint

### Product & Architecture Blueprint v2.0 (file-first)
**Status:** Proposed architecture / implementation roadmap — NOT a completed feature release  
**Date:** 2026-11-04  
**Target repository:** indigiti/Ananta, current v1.7.0 procedural PHP staging codebase  
**Initial constraint:** Remain **database-free** for a controlled, single-server pilot; do not require Laravel, MySQL, PostgreSQL, Redis or Node.js runtime on day one.

> This document supersedes earlier "SQL first / Laravel first" architecture recommendations **for the pilot only**. The previous multi-tenant transactional design is the intended migration target, not an initial hosting requirement. Existing v1.7.0 modules and routes remain unchanged unless separately implemented and tested. This is a blueprint, not a claim that new products have shipped.

### 1. Product thesis and operating entities

**Mission:** Become the network where reliable nursery transactions happen, not a static directory and not a generic AI chatbot.

- **Ananta Mall:** Public nursery discovery, branded seller storefronts, plant listings, retail enquiry and (later) checkout.
- **NurserySetu AI / SupplierOS:** Each nursery's operational cockpit for branch profiles, catalogue, stock evidence, leads, wholesale quotations and follow-up tasks.
- **Ananta Trade:** B2B BOQ/RFQ normalization, matching, invitations, multi-supplier quotation comparison and buyer-approved purchase orders.
- **Ananta Agent Network:** Verified product/availability feeds, authenticated agent tools and later ACP/UCP/provider adapters where partners support them.
- **Ananta Connect:** Permissioned WhatsApp messaging with audited delivery events and opt-in/opt-out enforcement.
- **Ananta Control:** Platform verification, moderation, subscription and usage accounting, system health and audit.

**Launch wedge:** First make the Pune–Solapur nursery corridor commercially useful for landscapers, plant buyers and suppliers. A target count of 2,000 nurseries is an unverified market hypothesis, NOT an existing verified Ananta supplier count.

### 2. Core principles

1. **File-first, evidence-first, human-approved:** Start with the proven PHP file foundation. Never invent live prices, stock, seller verification, delivery or plant safety claims.
2. **One source of truth per domain:** Canonical plant taxonomy is platform-owned; offer/stock/quotation truth belongs to its nursery; RFQ and matching lifecycle belongs to Ananta; transactions retain immutable snapshots.
3. **Separation of intent from execution:** AI may parse requirements, draft copy, recommend candidates and explain comparisons. It must not silently create binding orders, transfer money, publish sensitive claims or message suppliers without approval.
4. **Merchant-owned records remain isolated:** Cross-tenant access is through explicit broker operations, not directory traversal or direct file reads by tenant controllers.
5. **Portable adapters:** Define versioned interfaces before integrating external agent formats, messaging providers or payment gateways.
6. **Design for measured upgrades:** Database migration happens when correctness needs it, not merely because vendor marketing promises greater speed.

### 3. User experiences that must work

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

### 4. File-first platform structure

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

### 5. Data ownership and identity

Use UUIDv7 for every new entity and preserve stable UUID strings at HTTP boundaries. Core entities:
- Platform: `tenants`, `tenant_domains`, `users`, `memberships`, `supplier_reviews`, `plant_taxa`, `catalogue_aliases`, `rfqs`, `rfq_dispatches`, `buyer_accounts`, `audit_events`.
- Tenant: `branches`, `seller_profiles`, `listings`, `variants`, `availability_claims`, `price_books`, `stock_batches`, `leads`, `quotes`, `conversations`, `delivery_zones`.
- Operations: `outbox_events`, `inbox_dedup`, `jobs`, `ai_reviews`, `ai_usage`, `message_status_history`, `reconciliation_runs`.
- Phase-gated: `orders`, `payments`, `refunds`, `settlement_ledger`, `inventory_reservations` **must not be made cross-tenant transactional in the file-only pilot**.

Single public product identity = canonical taxon/product. Its many seller listings = seller-controlled offers that may differ by pot size, height, maturity, grade, lead time and lot. A public listing may be enquiry-only and must disclose stock freshness.

Roles: platform superadmin, platform operations, supplier owner, supplier manager, branch staff (sales/inventory), buyer, trade buyer. Memberships are explicit; a nursery can also be a buyer. RBAC is enforced in server-side policy and each data repository call, not by hiding sidebar links.

### 6. AI-native product strategy

**P0/P1 — valuable immediately:** multilingual typed or spoken request input (voice via optional provider), BOQ import/extraction with row-level confidence, botanical synonym normalization, evidence-based supplier eligibility ranking and quotation comparison. Require buyer review before transmitting a parsed RFQ.

**P2 — supplier copilot:** draft product details and quote messages, summarize enquiries, flag missing listing evidence, recommend tasks and stock refreshes. Models are advisory; protected fields (price, stock, tax, safety, delivery, reviews, supplier verification) are human-verified only.

**P3 — external agents:** publish structured machine-readable catalogue metadata and capability documents, with OAuth/scoped tokens for non-public operations; expose `search_offers`, `check_offer_freshness`, `draft_rfq`, `get_quote_status` as typed, permission-controlled tools. External ACP/UCP support is via adapters **only when current external specifications and partner onboarding allow it**. Do not imply registration with, approval from, or distribution on third-party AI surfaces.

**P4+ — optional visual intelligence:** suggest plant taxonomy and inspect media completeness. Do not infer exact grade, health, stock count or legal safety claims solely from images. Autonomous purchases and unattended pricing remain out of scope until controls are proven.

AI response contract: include `source_record_ids`, `data_as_of`, `availability_status` (CONFIRMED / REPORTED / UNKNOWN / EXPIRED), `requires_confirmation`, and any `review_flags`. A model's confidence score is never a substitute for supplier evidence.

### 7. File-first business flows

#### A. Verified supplier onboarding
1. Supplier registers, verified contact and business identity recorded in private storage.
2. Platform checks claim/duplicates and approves public profile.
3. Supplier creates branch/service area and first 10 listings with photos.
4. System stores published redacted projections separately from private contact and verification documents.
5. Staff updates stock/price and freshness timestamp. Unknown availability remains explicitly unknown.

#### B. Buyer RFQ / AI sourcing
1. Buyer submits requirement file or text; validate size/type and scan uploads.
2. AI creates **draft** BOQ rows; normalize plant names and highlight ambiguity.
3. Buyer confirms quantity, size, location and dispatch authorization.
4. RFQ broker checks published eligibility, geographic serviceability, seller invitation policy and opt-in.
5. Supplier receives an invitation event and submits a versioned quote into its own tenant aggregate.
6. Broker stores a signed/referenceable accepted quote snapshot and offers side-by-side landed cost comparison.
7. Buyer approves a supplier-specific PO; fulfilment and payment remain manually coordinated and explicitly marked until transactional infrastructure is available.

#### C. WhatsApp communications
Use a `MessagingProvider` interface, with official WhatsApp Business API the preferred production transport; optional WaBridge only behind explicit noncritical/testing feature flags. Record queue/submission/provider-sent/delivered/read/failed events separately. Require provider signature verification and deduplication, tenant phone-number mapping, approved templates and opt-in records. **Queued != delivered.**

#### D. Retail checkout
Pilot: enquiry or quote-to-PO only. A **single-supplier** direct-order pilot may be designed after an audited per-order state machine and payment reconciliation, but no automatic cross-nursery cart, payment splitting or stock reservation in file mode. Customer-facing UI cannot promise guaranteed inventory until a seller has confirmed it.

### 8. Agent-compatible capability APIs (proposed, not implemented)

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

### 9. Revenue model (hypotheses, not forecasts)

Start with a free verified directory listing. Test paid supplier subscriptions for CRM, quotation workflows, team collaboration and operational analytics; charge transparently for optional premium placement or qualified RFQ volume. Usage-based AI and WhatsApp fees must be observable and controllable. Defer commission/split-settlement models until seller-of-record, invoicing, refund and applicable Indian tax responsibilities are agreed and a payment provider approves the arrangement.

Illustrative test tiers: Free / Grow / Pro / Business. Validate actual willingness to pay, onboarding cost, support overhead, lead quality and renewal before committing to price points.

### 10. Stage gates and success measures

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

### 11. Phased delivery

| Phase | Delivery | Acceptance gate |
|---|---|---|
| P0 | Security containment; no-DB storage boundaries, tenant/membership policies, operational test harness, redacted public views | No tracked runtime secrets; tenant-isolation tests; backups restore |
| P1 | Nursery profiles, storefronts, catalogue variants, evidence/freshness, search and manual enquiries | First real suppliers publish controlled verified data |
| P2 | AI BOQ draft/review, RFQ broker, scoped supplier quotes, comparisons, buyer-approved POs | End-to-end multi-supplier sourcing without phantom inventory |
| P3 | WhatsApp provider adapter, consent/status webhooks, supplier CRM/collaboration, first paid plan experiments | Delivery event reconciliation; measured supplier engagement |
| P4 | Agent capability adapters, supplier AI copilot, external discovery feed validation | Typed tool permissions; human approval and zero unverified commerce claims |
| P5 | SQL migration and transactional checkout/settlements if justified, high-scale search and automated operations | Concurrency, payment reconciliation, load and restore tests pass |

**Prioritization note:** Build the minimum AI-assisted sourcing loop in P2, not a generic chat experience. Standards-readiness belongs in APIs early; expensive partner-specific adapters wait until accepted integration pathways exist.

### 12. Explicit non-goals during file-only pilot

- No microservices, multiple independently writable databases or mandatory Redis.
- No unsupported "instant checkout inside ChatGPT/Gemini" claims.
- No autonomous payments, payouts, refunds, stock changes or public botanical/safety edits.
- No automated multi-supplier settlement or guaranteed stock promises.
- No public exposure of tenant private files, personal contact databases, provider keys or unredacted conversations.
- No fabricated nursery counts, revenue forecasts, live prices or delivery SLA.

### 13. Reference and implementation next steps

See [Part II: File-first pilot architecture](#part-ii-file-first-pilot-architecture) for exact storage layout, transaction discipline, tenant isolation, backup/recovery, no-DB capacity gates and SQL migration contract. See [Part III: Implementation backlog](#part-iii-implementation-backlog) for acceptance-focused work items.

**Baseline reality:** The repo presently contains a functional single-brand PHP staging application with 376 parent plants and AI/variant/discovery modules. Multi-supplier SaaS, verified supplier network, multi-tenant isolation and marketplace checkout are **planned**, not delivered.

---

## Part II: File-first Pilot Architecture

**ADR-001: No database during the controlled pilot**  
**Status:** Accepted as a proposed implementation constraint, contingent on security, recovery and measured load testing  
**Date:** 2026-11-04  
**Applies to:** One Cloudways-compatible PHP deployment, one authoritative writable filesystem, no multi-node writers.

### 1. Decision and scope

Keep existing PHP 8.4-compatible Ananta application and its file-backed foundation for a controlled directory / supplier-enquiry / RFQ / quotation pilot. Do **not** require Laravel, MySQL, PostgreSQL, Redis, RabbitMQ, Rust, Node.js or a resident daemon to begin.

Current `src/foundation.php` already exposes file locks, checksums, atomic JSON writes, journaling and authenticated encryption. Reuse/extend that infrastructure, and preserve existing `src/ai_engine.php` protected-field policy. **Neither existing capability is proof that file mode is safe for multi-seller payment or distributed transaction processing.**

The no-DB option is **conditional**, not permanent: make the persistence interface replaceable and retain documented migration gates.

### 2. Hosting topology

    Browser / PWA / approved API clients
                 |
         Cloudflare (optional)
                 |
        Cloudways Nginx/PHP-FPM
                 |
       Ananta modular PHP application
          |      |       |
      Public   Private   Bounded jobs
      views    records   + outbox
                 |
         Local private filesystem
                 |
         Encrypted offsite backups

Requirements:
- Expose **only** `public/` as web document root; never the application root or `storage/`.
- A **single authoritative writable host**; no separate writer copies, no CDN caching of private content.
- TLS, trusted proxy configuration, CSRF/session hardening, MFA for privileged accounts, strict tenant RBAC.
- Bounded job processing triggered from an authenticated dashboard and/or Cloudways cron where available. User requests must not silently run unbounded work.
- Publish a minimal `/health` and authorization-protected `/admin/system`; no secrets or filesystem paths in public health output.
- Remote backup storage must be outside this application's public web root and outside the Git repository.

### 3. Logical filesystem layout (proposed)

The following paths describe **runtime data only**. Do not commit populated files.

    storage/
      private-domain/
        platform/
          tenants/{tenant_uuid}.json
          hosts/{sha256_normalized_host}.json
          identity/users/{user_uuid}.enc.json
          identity/memberships/{tenant_uuid}/{user_uuid}.json
          buyers/{buyer_uuid}.enc.json
          rfqs/{rfq_uuid}.enc.json
          rfq-dispatches/{rfq_uuid}/{supplier_uuid}.json
          broker-events/YYYY-MM-DD.ndjson
          indices/...
        tenants/{tenant_uuid}/
          profile.json
          branches/{branch_uuid}.json
          listings/{listing_uuid}.json
          variants/{variant_uuid}.json
          inventory/{branch_uuid}/{variant_uuid}.json
          availability/{variant_uuid}.json
          leads/{lead_uuid}.enc.json
          quotes/{quote_uuid}.enc.json
          conversations/{conversation_uuid}.enc.json
          messages/{conversation_uuid}/YYYY-MM-DD.ndjson
          outbox/YYYY-MM-DD.ndjson
          inbox-dedup/{provider}/{event_hash}.json
          audit/YYYY-MM-DD.ndjson
          ai/reviews/{review_uuid}.json
          ai/usage/YYYY-MM-DD.json
        jobs/pending/...
        jobs/processing/...
        jobs/failed/...
        security/
        config/
      public-domain/
        marketplace/indexes/...
        marketplace/storefronts/{tenant_uuid}.json
        marketplace/offers/{tenant_uuid}/{listing_uuid}.json
      journals/
      locks/
      tmp/
      backups/ (short-lived local snapshots only)

- `{tenant_uuid}` is generated or resolved by trusted server-side repository code, never copied directly from an arbitrary request path.
- Store private business/person data only in encrypted private aggregates, not the public read models.
- Public-domain is still outside `public/` and only intentionally published projections are exposed through a controller.
- Runtime indexes are **rebuildable derived data**, not an alternative source of truth.
- Global identity and RFQ broker records are platform-owned; a supplier must not read other suppliers' private aggregates.

### 4. File access and corruption prevention

#### Single-aggregate mutation protocol

1. Validate authorization, tenant membership, schema version and maximum payload size.
2. Normalize the aggregate ID (UUIDv7) and resolve a fixed server-generated path.
3. Acquire a stable per-aggregate `flock(LOCK_EX)` lock file (do not lock a data file and then replace the data inode).
4. Read current aggregate, verify checksum/integrity and compare expected version.
5. Apply pure domain transition and increment a monotonically increasing `version`.
6. Write a new snapshot to a unique temporary file in the **same filesystem/directory**, flush, fsync when available, then atomically rename into place.
7. Append an audit/operation event with aggregate ID, version, correlation ID and idempotency key; ensure recoverable ordering.
8. Release the lock and respond with the new version. If disk write, checksum or journal fails, **fail closed** and reconcile.

Multiple-file writes are **not atomic** merely because each file uses `rename` or a lock. Define one aggregate as the authoritative unit for each critical transition; treat indexes, notifications and cross-tenant projections as asynchronous read models. Where authoritative snapshot + journal need stronger guarantees, record a pre-commit intent and replay/reconcile on startup/maintenance. Test power-loss and partial-write scenarios. Do not claim exactly-once delivery.

#### Concurrency and freshness

- Every editing endpoint uses optimistic `expected_version`; return `409 conflict` on stale form submissions.
- Reject negative inventory or stale price overrides under the aggregate lock.
- Use one inventory aggregate per (tenant, branch, variant). The calculated available quantity is `on_hand - reserved` and `reserved` is zero in the initial enquiry-only release.
- A stock number is **reported** until a supplier confirms and timestamps it; after configured TTL it becomes **STALE**, not silently "in stock".
- Store offer pricing in integer paise (or another explicitly declared minor unit), with currency, validity period, tax basis and seller provenance. Do not assume a universal GST rate.
- For the pilot, availability should be a **claim and confirmation workflow**, not a hard stock reservation guarantee.

#### Append-only events and job dispatch

- Outbox entries are generated when an authoritative aggregate changes; background workers publish them after commit.
- Message delivery is at-least-once; receivers deduplicate by event UUID / provider event ID; retries must be idempotent.
- Append-only NDJSON should use its own `flock` and fsync; partition by day/tenant to avoid a single unbounded hot file.
- Materialize paginated recent-event summaries, not full event logs on every dashboard request.
- Expire or archive indexes and noncritical operational records using documented retention, but retain legally required financial and security evidence.

### 5. Tenant isolation and routing

**Input:** verified hostname or platform route + authenticated user session.  
**Resolution:** normalize IDNA/host and reject ports/illegal hosts; lookup a verified and enabled host mapping; resolve tenant to an application-generated UUID.  
**Authorization:** load membership scoped to tenant and branch; check action-specific policy before reading or writing private aggregates.

Critical rules:
- Never trust request-body `tenant_id`, URL-selected supplier slug or `X-Tenant-Id` as authorization.
- Prevent path traversal, case/Unicode host confusion, symlink escapes and unknown-domain fallback.
- Bind cache/index keys, cookies and storage namespaces to tenant and verified host where appropriate.
- Verify custom domain ownership by a challenge and review before activation.
- Buyers may access their own RFQs across sellers, but supplier staff may only see a broker-provided **redacted invitation view** of permitted RFQs.
- Platform support access must be explicitly granted, time-limited, least-privileged and logged.

### 6. Reconciliation across supplier and platform files

Do not attempt one atomic "multi-seller transaction" by locking many JSON files. For an RFQ invitation:
1. Platform RFQ aggregate is created with a unique request UUID and state `DRAFT`.
2. Buyer approves dispatch; RFQ state transitions to `DISPATCH_REQUESTED` and emits outbox intents for selected suppliers.
3. Idempotent worker creates broker invitation records and supplier-facing redacted views.
4. Status advances as dispatch is acknowledged; failed invitations remain retryable.
5. Each supplier submits its own immutable quote version, which emits `quote.submitted`.
6. Broker reconciles quote IDs / versions into the buyer RFQ projection.
7. Buyer accepts a quote with explicit confirmation; produce a downloadable PO artifact with immutable terms.
8. No cross-supplier stock holds, payment collection or automated settlement occurs in this workflow.

Scheduled repair scans compare outbox, invitation and quote-receipt references. A record that cannot reconcile is marked `NEEDS_REVIEW` and surfaced in System Health rather than silently treating the transaction as completed.

### 7. AI and ingestion boundaries

- Use deterministic matching and supplier eligibility rules in PHP.
- AI parses BOQ rows, normalizes plant synonyms and proposes matches with references to observed records; require row review and final dispatch confirmation.
- Each provider call has a tenant-scoped token/request budget, timeout, retries, source hash and review log.
- Never send full customer PII, financial documents or private WhatsApp transcripts to external models unless authorization, purpose and contractual/data-handling safeguards permit it.
- Source-backed AI fields: `source_id`, `verified_at`, `freshness`, `confidence`, `needs_review`.
- All consequential tool actions are typed and permissioned; no arbitrary filesystem or HTTP access from the model.

### 8. WhatsApp adapter and webhook durability

Interfaces:
- `MessagingProvider::sendTemplate(tenant, recipient, template, variables, idempotencyKey)`
- `MessagingProvider::sendSessionMessage(tenant, recipient, body, idempotencyKey)`
- `MessagingProvider::verifyWebhook(headers, rawBody)`
- `MessagingProvider::normalizeWebhook(rawBody)`

Persist incoming webhook event and deduplication identifier before durable acknowledgement, with bounded file size and strict permission checks. Map provider account to tenant through a trusted account registry. Handle out-of-order provider status receipts with immutable status history and a monotonic/allowlisted status reducer.

**Transport policy:** Official WhatsApp Business Platform for customer-facing production; WaBridge may be optional for a controlled noncritical test, subject to separate risk review. Do not represent a queued message as sent or delivered. Track consent, template category, opt-out, messaging costs and replay protection.

### 9. Operational safeguards

#### Backup and restore
- Backup both snapshots **and** journals/critical queue metadata, plus application code version and schema version; capture a consistent point-in-time (pause writes or use a documented coordinated snapshot protocol).
- Encrypt and ship backups offsite; retain multiple generations. Do not store backup archives in Git.
- Verify checksums, run periodic restore drills to isolated staging and replay pending outbox records.
- Define RPO/RTO targets from pilot risk appetite and validate them empirically; do not claim zero data loss.

#### Observability
- Health metrics: file-lock contention p95, write failures, corrupted snapshots, queue age, pending outbox, failed jobs, RFQ reconciliation exceptions, disk usage, backup age, restore drill freshness and API/AI spend.
- Error logs show last ten items by default, followed by paginated histories.
- Read-model rebuilds and journal compaction are bounded and scheduled outside peak activity.
- Never expose exact secret paths, encryption keys or raw private JSON in health endpoints.

#### Repository hygiene — immediate P0
The current public repository tracks operational-looking files (for example `storage/private-domain/admin/setup.key`, `storage/private-domain/commerce/*.json` and journal files). Audit whether any contained real secrets, credentials or customer data, remove runtime artifacts from the tracked tree, apply `.gitignore` policies, and rotate anything genuinely exposed. Deleting from the latest commit does **not** erase history; history remediation may require coordinated credential rotation and a deliberate Git history rewrite. Never commit new pilot data.

### 10. Pilot capacity and stop conditions

File mode has no universal safe "number of nurseries" threshold. Set load targets with measured dataset volume, concurrent write patterns, disk throughput and backup/restore time. Suggested **test matrix**, not supported capacity claim:
- 10 / 25 / 50 active tenants.
- 1,000 / 10,000 / 50,000 published offers across tenants.
- 1 / 5 / 20 concurrent supplier write clients.
- Burst webhooks, RFQ fan-out, failed job retries and backup-in-progress behavior.

Hard upgrade triggers:
1. Any need for ACID consistency across many supplier inventory/payment/ledger aggregates.
2. Multi-node/horizontal writer scaling.
3. Unacceptable locking, I/O latency, queue delay or audit reconciliation based on agreed SLO.
4. Backup restore becomes too slow or cannot guarantee consistency.
5. Operational corruption or security isolation defects cannot be corrected safely in file mode.

Pilot should stay **enquiry + quotation + PO**, with manually reconciled fulfilment and seller-managed payment. If online checkout is essential earlier, implement a transactional SQL-backed commerce subset **before** accepting real money; do not force payment semantics into file mode solely to honor a no-DB preference.

### 11. SQL migration seam (define now; execute when gated)

Contract interfaces, not framework dependency:
- `TenantRepository`, `IdentityRepository`, `PlantCatalogueRepository`, `SupplierOfferRepository`, `InventoryRepository`, `RFQRepository`, `QuoteRepository`, `EventOutbox`, `AuditRepository`, `MessageRepository`.
- Preserve UUIDv7, `tenant_id`, aggregate `version`, event UUID, source provenance, timestamp and soft/archive state.
- Ship `File*Repository` initially; design `Sql*Repository` against a versioned schema. Avoid hardcoding `file_get_contents()` into controllers, domain services or AI tools.
- Build an offline export verifier that snapshots file aggregates, validates UUID uniqueness and references, replays journal entries and produces a signed manifest with row counts/checksums.
- For cutover, use a **maintenance/read-only window**, freeze writes, replay and verify counts/hashes, switch entire authoritative aggregates to SQL, and retain read-only rollback evidence. Never dual-write live payments or stock without a formally verified migration protocol.
- Use one chosen transactional SQL engine appropriate for the target Cloudways server; introduce Redis and dedicated workers only when measured queue/caching requirements justify them.

### 12. Acceptance tests before pilot exposure

1. Tenant A cannot read or write Tenant B profile/private files by changing URL, host, JSON payload or session.
2. Two concurrent edits with the same version produce one success and one 409 without lost update.
3. Worker replay after interrupted RFQ dispatch creates no duplicate invitation.
4. Out-of-order WhatsApp statuses never fabricate delivered/read events.
5. Failed or timed-out AI calls do not affect publication or commercial truth.
6. Partial disk writes, full disk and corrupt snapshot each fail closed with recoverable incident records.
7. Backup restore to isolated host reproduces all authorized supplier/RFQ state and queued events.
8. Public feeds contain no customer PII, unverified inventory or tenant-private documents.
9. Unauthorized AI tool calls and no-consent messaging are rejected and audited.
10. Import and media upload validation prevent traversal, executable uploads and uncontrolled resource use.

**Decision review:** Reassess file-first operation at the end of P2 (first real RFQ pilot), again before P3 WhatsApp scale, and **always before any online payment or automated reservation launch**.

---

## Part III: Implementation Backlog

**Plan version:** 2.0 | **Date:** 2026-11-04 | **Implementation status:** Not started by this documentation update.

This is a concrete execution backlog for Part I of this document and Part II of this document. P0–P4 operate initially on a **single authoritative file-backed PHP runtime**. P5 includes an explicit decision point for a transactional database before automated checkout. Each phase contains QA gates; no phase is complete because documentation exists.

### Global definition of done

- Endpoints, screens, authorization policy and error/empty/loading states implemented.
- New writes isolated by tenant and branch, idempotent if retryable, journaled and recoverable.
- Unit, integration, permission, adversarial tenant-isolation, concurrent-write and browser tests run on current commit.
- Responsive WCAG 2.2 AA target for public and supplier UIs; keyboard and touch navigation tested.
- Audit logs paginated with recent-ten-event summaries; no unbounded lists by default.
- Release health, backup restore, rollback and deployment checks documented and executed.
- No secrets, customer data or runtime JSON included in Git.
- Do not claim AI agent checkout or externally approved integrations unless validated.

### P0 — Containment, tenant foundation and recoverable filesystem

| ID | Scope | Acceptance |
|---|---|---|
| P0-01 | Inspect current tracked `storage/private-domain/`, `storage/journals/`, backup and lock artifacts | Exposure report with sensitivity classification; rotate compromised live credentials; clean tracked runtime artifacts without breaking a clean install |
| P0-02 | Add explicit runtime `.gitignore` rules and clean sample fixtures | All fresh installations initialize from placeholders; secrets never committed |
| P0-03 | Establish CI lint, regression and test matrix with pinned PHP version | CI catches syntax, regression, unsafe filesystem exposure and basic schema errors |
| P0-04 | Introduce `TenantContext` and platform/tenant authorization policy | Host, path and payload tampering cannot access another tenant |
| P0-05 | Implement UUIDv7 tenant, branch, user and membership stores | Unique, schema-versioned, encrypted where private; referential integrity verified |
| P0-06 | Add file repository interfaces and per-aggregate versioned write protocol | Concurrent edit conflict returns 409, no silent last-write-wins |
| P0-07 | Add jobs/outbox/dedup and deterministic reconciliation state | Interrupted job can be retried without duplicate business effect |
| P0-08 | Configure encrypted offsite backup/restore and health metrics | Restore drill with snapshot/journal checksums passes |
| P0-09 | Security hardening: csrf/session/MFA, private routes, proxy headers, uploads | Public URL cannot reach runtime files or private records |
| P0-10 | Preserve existing Sanjay Nursery storefront as compatibility baseline | Legacy v1.7.0 commerce, variants, themes and AI regressions pass |

**P0 release gate:** no known critical secret exposure, verified tenant isolation, integrity-tested backup/restore and existing storefront parity. No new supplier public sign-up before this gate.

### P1 — Verified supplier directory and storefronts

| ID | Scope | Acceptance |
|---|---|---|
| P1-01 | Supplier registration, claim and manual platform verification | No duplicate unverified store claim gets public "verified" status |
| P1-02 | Multi-branch supplier model, serviceability, contact preferences | Branch data never leaks into another supplier's admin |
| P1-03 | Canonical plant taxonomy and aliases reused from current catalogue | A taxon can map to many supplier-specific offers |
| P1-04 | Supplier listing CRUD, variant spec, photo rights, import previews | SKU, height, pot, grade and media claims validated; invalid uploads rejected |
| P1-05 | Availability claims and freshness timestamps | Unverified/stale stock is not labeled confirmed; buyer can request verification |
| P1-06 | Public nursery pages and category/location search | SEO-safe canonical URLs; approval required before publishing |
| P1-07 | Buyer enquiry form and supplier lead inbox | Correct tenant routing and staff assignment, spam/rate defenses |
| P1-08 | Responsive premium storefront and supplier dashboard shell | WCAG target, keyboard navigation, collapsible menus and readable data density |
| P1-09 | Derived public catalogue indexes / incremental rebuild | No private data in feeds; bounded memory and pagination |
| P1-10 | Supplier onboarding analytics | Completion, catalogue freshness and enquiry-response metrics visible |

**P1 release gate:** invited real suppliers can publish verified catalogues and receive enquiries without editing code. No required live checkout, payment, or false "in stock" badges.

### P2 — AI-assisted wholesale and quote broker

| ID | Scope | Acceptance |
|---|---|---|
| P2-01 | Buyer project and draft RFQ workflow | Authenticated owner/organization permissions; draft can be reviewed |
| P2-02 | CSV/XLSX/PDF BOQ import with safe file limits | Deterministic parser where possible, AI fallback with confidence and visible corrections |
| P2-03 | Botanical alias and variant specification normalization | No merging different grades/sizes as identical quoted items |
| P2-04 | Supplier matching by location, MOQ, eligibility, evidence | Explain ranking and mark unverified availability explicitly |
| P2-05 | Buyer-approved RFQ dispatch and idempotent fan-out | Replay does not duplicate seller invitations |
| P2-06 | Supplier quote builder with revisions and validity | Price + tax basis + freight + lead time snapshots immutable per version |
| P2-07 | Buyer comparison and negotiation activity | Comparable unit, size, grade, freight and delivery line items |
| P2-08 | Quote acceptance and buyer-approved PO output | One or more supplier-specific PO artifacts with audited approval |
| P2-09 | Broker-to-supplier consistency audit and repair queue | Interrupted fan-out/quote receipt detected; no silent lost requests |
| P2-10 | AI evaluation dataset and monitoring | Record BOQ correction rate, fabricated-field rate, latency and spend |

**P2 release gate:** real buyer uploads a BOQ, reviews it, solicits real quotes, accepts one or more, and exports a valid PO. Settlement and inventory holds remain manual/explicitly unverified.

### P3 — WhatsApp, supplier CRM and commercial experiments

| ID | Scope | Acceptance |
|---|---|---|
| P3-01 | Messaging transport interface + official provider adapter | Account/tenant mapping and provider webhook verification |
| P3-02 | Opt-in/out, template approval, quiet-hours/policy rules | Unauthorized marketing send blocked and auditable |
| P3-03 | Inbox, conversation assignment, reply drafts | Staff can see only permitted tenant/branch conversations |
| P3-04 | Durable send queue and provider receipts | Queued/submitted/sent/delivered/read/failed accurately distinguished |
| P3-05 | Quote and lead update notifications | Idempotent send and retry, no duplicate supplier contact |
| P3-06 | Optional WaBridge experiment behind disabled-by-default flag | Never required for production OTP, payments or critical order communications |
| P3-07 | Supplier CRM stages, follow-up tasks, quick replies | Leads traceable to source and response/quote conversion |
| P3-08 | Test Free/Grow/Pro subscription entitlements | Explicit billing and usage limits; pilot pricing treated as hypothesis |
| P3-09 | Audit / health / usage dashboards | Paginated logs, cost per tenant, queue and delivery latency visible |

**P3 release gate:** pilot suppliers receive traceable RFQs/WhatsApp messages, report delivery correctly and can manage leads; paying plan experiments measured.

### P4 — Agent-readiness, AI copilot and optimization

| ID | Scope | Acceptance |
|---|---|---|
| P4-01 | Versioned public product / seller offer feeds and schemas | Verified offers only, no private PII or phantom availability |
| P4-02 | Scoped, typed agent tools for search, availability, RFQ draft | Negative authorization and tenant-leak tests pass |
| P4-03 | ACP/UCP adapter feasibility and partner onboarding gates | No third-party checkout claim without actual partner access |
| P4-04 | Supplier AI copilot for catalogue/quotes and summaries | Protected commercial facts require explicit human approval |
| P4-05 | AI budgets, provider failover and strict logging/redaction | Tenant token caps, audit, timeout and failure isolation |
| P4-06 | Supplier ranking transparency and paid placement labels | Organic and sponsored ranking clearly distinguished |
| P4-07 | Multilingual buyer/supplier journeys | Pune pilot language quality tested on realistic tasks |
| P4-08 | Measured performance/capacity optimization | Search, supplier read/write, file contention and backup impact benchmarked |

**P4 release gate:** an external scoped client can safely discover offers and draft a buyer-approved RFQ; no autonomous financial commitment.

### P5 — Scale and transactional upgrade

| ID | Scope | Acceptance |
|---|---|---|
| P5-01 | Reassess file mode using actual SLOs and critical transaction requirements | Written go/no-go with load tests, lock contention and recovery evidence |
| P5-02 | Implement SQL-backed repositories if required | UUIDs/versions/event IDs preserved; source consistency validated |
| P5-03 | Cutover export/manifest, maintenance freeze, replay and verified import | No double-write authority; audit proof and rollback plan |
| P5-04 | Atomic stock reservations, expiry and anti-oversell controls | High-concurrency attempts cannot oversell |
| P5-05 | Single-supplier checkout, verified webhooks, refund reconciliation | Payment and order states reconcile under duplicate/out-of-order webhooks |
| P5-06 | Marketplace settlement and split-payment arrangements after approval | Seller-of-record, GST/invoice/refund, provider and contractual review complete |
| P5-07 | Multi-supplier checkout only after P5-05/06 success | Seller order splits, partial failures and fulfilment tested |
| P5-08 | Scale-specific search / workers / caching | Added only when capacity evidence requires it |

**P5 release gate:** verified end-to-end money/stock correctness under concurrency, controlled recovery and operational load. A database may become necessary *before* P5 if these capabilities are pulled forward.

### Important sequencing decisions

- WhatsApp inbound enquiries and RFQ notifications are a priority; advanced automation is not.
- BOQ understanding and quotation workflows come **before** complex retail checkout.
- A visually attractive nursery mall is insufficient without verified supplier response workflows.
- One supplier's private stock, prices, leads, documents and WhatsApp history remain isolated even if the same user belongs to multiple tenant organizations.
- The current repository's procedural PHP structure is preserved for staging parity; introducing a new framework is a separate migration decision.
- No database does **not** mean no durable records, no tests or unrestricted file reads.

### Validation scorecard

Starting hypotheses only: 100 verified supplier profiles, 40 complete listings sets, 25 RFQ-responsive nurseries, 15 paying sellers, 10 fulfilled wholesale transactions. Track supplier activation rate, stock freshness, RFQ-to-quote rate, comparable quote coverage, quote acceptance, response latency, buyer repeat rate, paid renewal and unit cost.

**Next executable work item:** P0 exposure inventory and tenant-scoped storage contracts, with old release regression green and a working restore drill before supplier sign-up goes live.

---

## Part IV: Implemented Ananta storefront and seller-offer pilot

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

---

## Part V: Release A — tenant identity and repository containment (implementation)

**Status:** Tenant foundation implementation pending CI/pilot acceptance; **not** full P0 sign-off, not the P2–P5 product. Intended staging-only and single writable host.

- Platform admins manage registered suppliers in \`/admin/marketplace\`, verify them and issue 24-hour, one-time supplier invitations.
- \`/supplier/accept\`, \`/supplier/login\`, \`/supplier\` support supplier users with their own credentials and independent tenant contexts.
- New encrypted, file-first identity store holds \`tenants\`, \`users\`, \`memberships\` and hashed invitation tokens under \`storage/private-domain/tenancy/identity.json\`.
- Roles: owner, manager, inventory and viewer. Every request loads fresh membership state, so suspension and revocation take effect without trusting old session roles.
- Supplier accounts can propose **draft** plant offers; platform admins alone publish. Supplier staff may report stock as \`reported\` / \`unknown\` / \`unavailable\`, never verify inventory or charge buyers.
- Cross-tenant edits and report operations are checked **inside** locked write callbacks. Existing offer ownership is immutable; no cross-tenant checkout is enabled.
- The original Sanjay Nursery checkout and admin routes remain intact.
- Committed operational-looking \`storage/private-domain\`, \`storage/journals\`, \`storage/locks\` and \`storage/indexes\` records are removed from the HEAD tree. Curated \`storage/public-domain\` seeds stay to support clean file-first installation. Historical Git objects remain until a separately approved history remediation.
- Security tests must establish two independently authenticated tenants, denial of cross-tenant edits, one-time invitation replay protection, membership revocation and supplier suspension.

**Incomplete security controls:** real-world supplier identity/document verification, email delivery and recovery, MFA, backups plus restoration drills, external penetration review, encrypted seller-offer registry, persistent media uploads, and real Cloudways browser QA. Do not onboard real suppliers or claim full production readiness before these gates close.

**Acceptance:** \`php tests/tenancy.php\`, \`bash scripts/full_regression.sh\`, release packaging/boot smoke, and repository-hygiene CI must pass. The workflow returning success only validates the automated subset—not real-world security or deployment.


---

## Part VI: Release A2 — persistent uploaded plant media

**Status:** Implementation candidate; subject to CI, privacy and staging browser acceptance.

For DigiOps split deployment (\`public_html/ananta/\` + \`private_html/ananta/\`), new uploaded WebP files live at \`private_html/ananta/storage/private-domain/media/products/\`, not in the replaceable \`release/public/assets\` tree. The existing \`/ananta/assets/images/products/{name}.webp\` URLs are handled by a strict filename-validating PHP route for persistent images missing from the static release. Only approved, rights-cleared MediaOS roles on active products may be served publicly. Administrators can preview private uploads with a no-store response. Retired or unapproved media return 404 to public clients. Legacy source-controlled static media remains compatible.

- File MIME and image size validation and WebP re-encoding remain mandatory; uploaded supplier files never become executable PHP.
- Server storage, upload retention, backups and restoration are independent of the DigiOps source artifact.
- The packaging includes optional Python image workers for environments without PHP GD/WebP, but the primary pipeline remains PHP-native.
- \`tests/media_persistence.php\` asserts traversal denial, admin-only previews, approved-public state, retirement withdrawal and a private artifact boundary.

**Remaining:** migration of any existing manually uploaded photos from the old public tree, image backup/restore drill, live WebP HTTP behavior and performance verification, and supplier-specific photo rights/workflow ownership. Do not claim full P0 media acceptance before those checks.


---

## Part VII: Release B1 — Supplier branches, private locations and stock review

**Implementation scope:** Branch-specific SupplierOS management within the existing file-first, single-server staging pilot.

| Acceptance requirement | Implementation / control |
| --- | --- |
| Tenant-scoped branch CRUD | Owners/managers create or revise branches; IDs are UUIDv7 and every mutation validates the current membership |
| Private branch data | Branches are stored via authenticated encryption under \`storage/private-domain/tenancy/branches.json\` |
| Manual branch verification | Platform admin approves/rejects/suspends a branch with internal review evidence |
| Review invalidation | A change to branch name, city, site address or PIN resets approval to pending |
| Branch-specific offers | Each new supplier-managed draft requires a valid branch owned by that supplier |
| Publication gating | Published branch offers require the supplier to be verified and branch to be approved |
| Immediate withdrawal | Pending, rejected or suspended branches disappear from public offer projections, including nursery pages and APIs |
| Inventory integrity | Supplier-reported quantities remain indicative; platform-confirmed stock requires evidence and expires after seven days |
| Legacy compatibility | Existing admin-curated offers without a branch remain enquiry-only and unchanged |
| Disclosure limits | Public projections reveal approved branch name and city only; no private address, PIN or evidence |
| Test gate | Two independent tenants, wrong-tenant access and mutation denial, approval/reapproval/suspension checks, public DTO redaction, full regression and DigiOps release smoke |

This does **not** implement bulk imports, unlimited multi-branch scale, digital KYC, map-based serviceability, supplier image rights attestations, B2B RFQs, WhatsApp API messaging or transactional stock reservations. Those remain explicit backlog items. Real-world supplier and branch proof must be reviewed manually before publishing.

---

## Part VIII: Release B2 — SupplierOS styling and CSV-to-draft imports

Purpose: repair deployed supplier screen layout and prepare safe, file-first bulk nursery onboarding.

Design acceptance:
- Supplier sign-in and invitation pages use a constrained editorial heading, separate secure form card, accessible stacked fields and clear desktop/mobile/dark layouts.
- Supplier CSS is isolated into supplier-os.css and only included for supplier routes. Consumer newsletter is not rendered on supplier authentication pages.
- CSS/JS cache busting is based on the file's SHA-256 content fingerprint, not just the unchanged product version. DigiOps smoke tests the /ananta/ sibling path.
- Footer Ananta wordmark remains legible against dark green.

Inventory import acceptance:
- Only authenticated supplier owner/manager members can upload an exact-column UTF-8 CSV: branch_id,product_slug,label,price_inr,quantity.
- Maximum 50 rows or 128 KiB per upload, tenant-owned branches, existing published plants, constrained fields and duplicate-row detection.
- Server-side session preview expires in 15 minutes; a CSRF-protected explicit confirmation is required.
- Draft inserts occur as one locked aggregate write with stable idempotency row keys; existing offers with the same supplier/branch/plant/grade are skipped.
- All imported records remain unverified, non-public and enquiry-only until independent branch and offer approval. Do not publish, verify stock or modify other tenants.
- Automated coverage includes HTML/CSS integration, invalid inputs, cross-tenant denial, retries, no public exposure and split DigiOps release packaging.

Pending: real-browser QA at common phone/tablet/desktop widths, password recovery/MFA, supplier KYC, serviceability, lead inbox, formal backup restoration, RFQ workflow and WhatsApp API integration.


---

## Part IX: Release B3 — private buyer sourcing, nursery inbox and indicative quotations

**Scope:** Build the first measurable multi-nursery buyer-to-supplier commercial flow while retaining the PHP-first file-backed Cloudways staging architecture. This release is NOT the full P2 BOQ engine or P5 transaction platform.

| Acceptance requirement | Implementation |
| --- | --- |
| Buyer sourcing entry points | /sourcing and contextual plant/nursery CTAs for authenticated customers |
| Requirement fields | One canonical published plant, quantity, size/grade, destination, optional notes |
| Explicit buyer consent | Buyer selects 1–5 verified, actively onboarded nurseries, grants request sharing; account email/phone only shared with separate consent |
| Encrypted record | File-first `private-domain/marketplace/sourcing-rfqs.json`, UUIDv7, authenticated encryption, locked aggregate mutations |
| Buyer quotation workspace | /account/sourcing and private detail, buyer-only read, compare independent quotes |
| Supplier inbox | /supplier/enquiries, only currently authorized tenant members; separate supplier projection hides all competing vendors |
| Supplier responses | Owner/manager can submit an indicative unit price and 0–365-day lead time or decline; no automatically confirmed stocks |
| Buyer lifecycle | Buyer may close RFQ and block further supplier responses |
| Platform oversight | Aggregate RFQ/invitation/quote counts in marketplace admin without public buyer details |
| Limits and safety | Per-buyer/IP request throttle, maximum 1,500 total RFQs during file-first pilot, 1–5 recipients, input constraints and CSRF |
| Regression tests | Encrypted storage, cross-buyer denial, invited/uninvited supplier isolation, quote confidentiality, contact consent, role gating, closure and Cloudways sourcing CSS |

**Deliberate limitations:** Single-plant RFQs rather than multi-row Excel/PDF BOQs; manual quotations without binding purchase order or settlement; no external WhatsApp transport or delivery-confirmation claims; no automated seller matching based on verified real-time inventory; no supplier-specific payment capture. Keep original TradeOS customer projects and Sanjay Nursery checkout isolated.

**Next acceptance gates:** A real two-supplier pilot and buyer test, real browser QA, opt-in policy/legal text, supplier account and document verification, recovery/MFA, backup restore, and measures of conversion from submitted RFQ to returned quotation.
