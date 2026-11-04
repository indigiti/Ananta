# Sanjay Nursery v1.6.3 — Web Operations, Health & Error Lifecycle Audit

## Scope

Audit of the uploaded v1.6.2 staging package with emphasis on browser-only installation, system health visibility, error lifecycle management, automatic recovery detection and log retention.

## Pre-build findings

1. `/health` and `/ready` already exposed useful machine-readable diagnostics, but the Admin dashboard did not surface overall system state or incident history.
2. First-run Admin setup was browser-based only in part: administrator creation still required `SANJAY_DATA_KEY`, and deployment documentation also required webhook/signing environment variables.
3. The setup-key design required access to a private file or a preconfigured server environment value, so a genuinely no-console first run was not available.
4. Release checks existed, but failed checks were transient PASS/FAIL results. They were not persisted as incidents and had no automatic resolved/reopened lifecycle.
5. PHP runtime warnings/errors, uncaught exceptions and HTTP 5xx responses had no unified Admin OS incident view.
6. No explicit 90-day non-critical error retention policy existed.
7. The dashboard was primarily a commerce/catalogue control room and did not answer operational questions such as: Is the application healthy? What is failing? Did a previous failure recover?

## v1.6.3 implementation

### Web-only setup

- `/admin/setup` now creates a temporary 30-minute setup claim tied to the first setup browser session.
- The first administrator can be created without reading a setup-key file or configuring environment variables.
- The setup flow automatically generates:
  - a 32-byte confidential-data encryption key;
  - sandbox webhook signing secret;
  - agent token signing key;
  - installation identifier.
- The generated configuration is stored at `storage/private-domain/config/runtime.json`, outside `public/` with restricted permissions.
- Existing environment variables remain supported as optional infrastructure overrides for compatibility.
- After the first administrator is created, the setup claim is removed and `/admin/setup` redirects to sign-in.

### System Health

New Admin route: `/admin/system`

The health view combines existing release checks with runtime/server state:

- release checks passing / failed;
- active / critical / resolved incident counts;
- PHP version and SAPI;
- memory usage and configured memory limit;
- disk free space and percentage;
- storage and monitor writeability;
- confidential encryption readiness;
- web-managed runtime configuration readiness;
- key PHP extension visibility;
- last scan time.

Health scans are cached for five minutes so the Admin OS does not execute every deep release check on every page request. Incident counters are refreshed against the live incident store even while the release-check snapshot is cached.

### Error Monitor

New Admin route: `/admin/errors`

Incidents are fingerprinted and deduplicated across:

- failed health/release checks;
- PHP runtime warnings/errors;
- uncaught exceptions;
- fatal shutdown errors;
- HTTP 5xx responses.

Each incident stores source, severity, status, route/method, safe application-relative file location, first/last seen time, occurrence count, reopen count and resolution information.

### Automatic resolve and reopen

- A failed release check creates or updates a health incident.
- When the same check passes on a later scan, the incident is automatically marked **Resolved**.
- Runtime/HTTP incidents are associated with their route and method. After that route completes successfully without another monitored runtime error, matching active runtime/HTTP incidents are automatically marked **Resolved**.
- If the same fingerprint is seen again, the existing incident is reopened and `reopened_count` increments rather than creating duplicate noise.

### Retention

- Automatic housekeeping runs at most once per day.
- Resolved **non-critical** incidents older than 90 days are deleted.
- Active incidents are not removed merely because they are old; they remain visible until recovery is observed.
- Critical incidents are retained even after resolution.
- A web action is available to run retention cleanup immediately, but normal cleanup requires no manual intervention.

## Dashboard changes

The main `/admin` dashboard now includes:

- overall HEALTHY / DEGRADED / CRITICAL state;
- passing-check and active/critical incident summary;
- direct links to System Health and Error Monitor;
- recent active incidents;
- PHP/storage/encryption/disk snapshot;
- existing commerce/catalogue metrics and quick actions preserved.

## Verification completed

- PHP lint: all PHP source/templates/tests passed.
- Full automated regression suite: passed, including the new monitoring regression.
- Monitoring regression verifies:
  - web-managed secret generation;
  - setup-claim creation/validation;
  - incident create/resolve/reopen;
  - health-check auto-resolution;
  - route recovery auto-resolution;
  - 90-day resolved non-critical deletion;
  - critical incident retention.
- Clean HTTP installation test using only the web flow:
  - `/admin/setup` returned 200;
  - web setup generated runtime configuration and encrypted admin store;
  - login succeeded;
  - Dashboard, System Health and Error Monitor rendered successfully;
  - `/health` returned HTTP 200 with version 1.6.3 and encryption/webhook readiness;
  - `/ready` returned HTTP 200 with 102 critical readiness checks evaluated;
  - System Health reported HEALTHY and 0 failed checks in the clean verification copy.

## Operational boundary

No application-level console setup is required for a new staging install. The hosting environment still has to provide a compatible PHP runtime and either OpenSSL or Sodium; those are server capabilities rather than application configuration and are surfaced by System Health. The domain document root should remain pointed at `public/` so private runtime configuration cannot be served directly.
