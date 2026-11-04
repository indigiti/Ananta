# ANANTA — Implementation Backlog (File-First, AI-Native)
**Plan version:** 2.0 | **Date:** 2026-11-04 | **Implementation status:** Not started by this documentation update.

This is a concrete execution backlog for `ANANTA_AI_NATIVE_BLUEPRINT_V2.md` and `FILE_FIRST_PILOT_ARCHITECTURE.md`. P0–P4 operate initially on a **single authoritative file-backed PHP runtime**. P5 includes an explicit decision point for a transactional database before automated checkout. Each phase contains QA gates; no phase is complete because documentation exists.

## Global definition of done

- Endpoints, screens, authorization policy and error/empty/loading states implemented.
- New writes isolated by tenant and branch, idempotent if retryable, journaled and recoverable.
- Unit, integration, permission, adversarial tenant-isolation, concurrent-write and browser tests run on current commit.
- Responsive WCAG 2.2 AA target for public and supplier UIs; keyboard and touch navigation tested.
- Audit logs paginated with recent-ten-event summaries; no unbounded lists by default.
- Release health, backup restore, rollback and deployment checks documented and executed.
- No secrets, customer data or runtime JSON included in Git.
- Do not claim AI agent checkout or externally approved integrations unless validated.

## P0 — Containment, tenant foundation and recoverable filesystem

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

## P1 — Verified supplier directory and storefronts

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

## P2 — AI-assisted wholesale and quote broker

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

## P3 — WhatsApp, supplier CRM and commercial experiments

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

## P4 — Agent-readiness, AI copilot and optimization

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

## P5 — Scale and transactional upgrade

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

## Important sequencing decisions

- WhatsApp inbound enquiries and RFQ notifications are a priority; advanced automation is not.
- BOQ understanding and quotation workflows come **before** complex retail checkout.
- A visually attractive nursery mall is insufficient without verified supplier response workflows.
- One supplier's private stock, prices, leads, documents and WhatsApp history remain isolated even if the same user belongs to multiple tenant organizations.
- The current repository's procedural PHP structure is preserved for staging parity; introducing a new framework is a separate migration decision.
- No database does **not** mean no durable records, no tests or unrestricted file reads.

## Validation scorecard

Starting hypotheses only: 100 verified supplier profiles, 40 complete listings sets, 25 RFQ-responsive nurseries, 15 paying sellers, 10 fulfilled wholesale transactions. Track supplier activation rate, stock freshness, RFQ-to-quote rate, comparable quote coverage, quote acceptance, response latency, buyer repeat rate, paid renewal and unit cost.

**Next executable work item:** P0 exposure inventory and tenant-scoped storage contracts, with old release regression green and a working restore drill before supplier sign-up goes live.
