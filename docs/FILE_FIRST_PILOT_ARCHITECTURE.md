# ANANTA — File-First Pilot Architecture
**ADR-001: No database during the controlled pilot**  
**Status:** Accepted as a proposed implementation constraint, contingent on security, recovery and measured load testing  
**Date:** 2026-11-04  
**Applies to:** One Cloudways-compatible PHP deployment, one authoritative writable filesystem, no multi-node writers.

## 1. Decision and scope

Keep existing PHP 8.4-compatible Ananta application and its file-backed foundation for a controlled directory / supplier-enquiry / RFQ / quotation pilot. Do **not** require Laravel, MySQL, PostgreSQL, Redis, RabbitMQ, Rust, Node.js or a resident daemon to begin.

Current `src/foundation.php` already exposes file locks, checksums, atomic JSON writes, journaling and authenticated encryption. Reuse/extend that infrastructure, and preserve existing `src/ai_engine.php` protected-field policy. **Neither existing capability is proof that file mode is safe for multi-seller payment or distributed transaction processing.**

The no-DB option is **conditional**, not permanent: make the persistence interface replaceable and retain documented migration gates.

## 2. Hosting topology

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

## 3. Logical filesystem layout (proposed)

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

## 4. File access and corruption prevention

### Single-aggregate mutation protocol

1. Validate authorization, tenant membership, schema version and maximum payload size.
2. Normalize the aggregate ID (UUIDv7) and resolve a fixed server-generated path.
3. Acquire a stable per-aggregate `flock(LOCK_EX)` lock file (do not lock a data file and then replace the data inode).
4. Read current aggregate, verify checksum/integrity and compare expected version.
5. Apply pure domain transition and increment a monotonically increasing `version`.
6. Write a new snapshot to a unique temporary file in the **same filesystem/directory**, flush, fsync when available, then atomically rename into place.
7. Append an audit/operation event with aggregate ID, version, correlation ID and idempotency key; ensure recoverable ordering.
8. Release the lock and respond with the new version. If disk write, checksum or journal fails, **fail closed** and reconcile.

Multiple-file writes are **not atomic** merely because each file uses `rename` or a lock. Define one aggregate as the authoritative unit for each critical transition; treat indexes, notifications and cross-tenant projections as asynchronous read models. Where authoritative snapshot + journal need stronger guarantees, record a pre-commit intent and replay/reconcile on startup/maintenance. Test power-loss and partial-write scenarios. Do not claim exactly-once delivery.

### Concurrency and freshness

- Every editing endpoint uses optimistic `expected_version`; return `409 conflict` on stale form submissions.
- Reject negative inventory or stale price overrides under the aggregate lock.
- Use one inventory aggregate per (tenant, branch, variant). The calculated available quantity is `on_hand - reserved` and `reserved` is zero in the initial enquiry-only release.
- A stock number is **reported** until a supplier confirms and timestamps it; after configured TTL it becomes **STALE**, not silently "in stock".
- Store offer pricing in integer paise (or another explicitly declared minor unit), with currency, validity period, tax basis and seller provenance. Do not assume a universal GST rate.
- For the pilot, availability should be a **claim and confirmation workflow**, not a hard stock reservation guarantee.

### Append-only events and job dispatch

- Outbox entries are generated when an authoritative aggregate changes; background workers publish them after commit.
- Message delivery is at-least-once; receivers deduplicate by event UUID / provider event ID; retries must be idempotent.
- Append-only NDJSON should use its own `flock` and fsync; partition by day/tenant to avoid a single unbounded hot file.
- Materialize paginated recent-event summaries, not full event logs on every dashboard request.
- Expire or archive indexes and noncritical operational records using documented retention, but retain legally required financial and security evidence.

## 5. Tenant isolation and routing

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

## 6. Reconciliation across supplier and platform files

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

## 7. AI and ingestion boundaries

- Use deterministic matching and supplier eligibility rules in PHP.
- AI parses BOQ rows, normalizes plant synonyms and proposes matches with references to observed records; require row review and final dispatch confirmation.
- Each provider call has a tenant-scoped token/request budget, timeout, retries, source hash and review log.
- Never send full customer PII, financial documents or private WhatsApp transcripts to external models unless authorization, purpose and contractual/data-handling safeguards permit it.
- Source-backed AI fields: `source_id`, `verified_at`, `freshness`, `confidence`, `needs_review`.
- All consequential tool actions are typed and permissioned; no arbitrary filesystem or HTTP access from the model.

## 8. WhatsApp adapter and webhook durability

Interfaces:
- `MessagingProvider::sendTemplate(tenant, recipient, template, variables, idempotencyKey)`
- `MessagingProvider::sendSessionMessage(tenant, recipient, body, idempotencyKey)`
- `MessagingProvider::verifyWebhook(headers, rawBody)`
- `MessagingProvider::normalizeWebhook(rawBody)`

Persist incoming webhook event and deduplication identifier before durable acknowledgement, with bounded file size and strict permission checks. Map provider account to tenant through a trusted account registry. Handle out-of-order provider status receipts with immutable status history and a monotonic/allowlisted status reducer.

**Transport policy:** Official WhatsApp Business Platform for customer-facing production; WaBridge may be optional for a controlled noncritical test, subject to separate risk review. Do not represent a queued message as sent or delivered. Track consent, template category, opt-out, messaging costs and replay protection.

## 9. Operational safeguards

### Backup and restore
- Backup both snapshots **and** journals/critical queue metadata, plus application code version and schema version; capture a consistent point-in-time (pause writes or use a documented coordinated snapshot protocol).
- Encrypt and ship backups offsite; retain multiple generations. Do not store backup archives in Git.
- Verify checksums, run periodic restore drills to isolated staging and replay pending outbox records.
- Define RPO/RTO targets from pilot risk appetite and validate them empirically; do not claim zero data loss.

### Observability
- Health metrics: file-lock contention p95, write failures, corrupted snapshots, queue age, pending outbox, failed jobs, RFQ reconciliation exceptions, disk usage, backup age, restore drill freshness and API/AI spend.
- Error logs show last ten items by default, followed by paginated histories.
- Read-model rebuilds and journal compaction are bounded and scheduled outside peak activity.
- Never expose exact secret paths, encryption keys or raw private JSON in health endpoints.

### Repository hygiene — immediate P0
The current public repository tracks operational-looking files (for example `storage/private-domain/admin/setup.key`, `storage/private-domain/commerce/*.json` and journal files). Audit whether any contained real secrets, credentials or customer data, remove runtime artifacts from the tracked tree, apply `.gitignore` policies, and rotate anything genuinely exposed. Deleting from the latest commit does **not** erase history; history remediation may require coordinated credential rotation and a deliberate Git history rewrite. Never commit new pilot data.

## 10. Pilot capacity and stop conditions

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

## 11. SQL migration seam (define now; execute when gated)

Contract interfaces, not framework dependency:
- `TenantRepository`, `IdentityRepository`, `PlantCatalogueRepository`, `SupplierOfferRepository`, `InventoryRepository`, `RFQRepository`, `QuoteRepository`, `EventOutbox`, `AuditRepository`, `MessageRepository`.
- Preserve UUIDv7, `tenant_id`, aggregate `version`, event UUID, source provenance, timestamp and soft/archive state.
- Ship `File*Repository` initially; design `Sql*Repository` against a versioned schema. Avoid hardcoding `file_get_contents()` into controllers, domain services or AI tools.
- Build an offline export verifier that snapshots file aggregates, validates UUID uniqueness and references, replays journal entries and produces a signed manifest with row counts/checksums.
- For cutover, use a **maintenance/read-only window**, freeze writes, replay and verify counts/hashes, switch entire authoritative aggregates to SQL, and retain read-only rollback evidence. Never dual-write live payments or stock without a formally verified migration protocol.
- Use one chosen transactional SQL engine appropriate for the target Cloudways server; introduce Redis and dedicated workers only when measured queue/caching requirements justify them.

## 12. Acceptance tests before pilot exposure

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
