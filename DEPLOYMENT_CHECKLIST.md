# Sanjay Nursery v1.7.0 — Web-Only Deployment Checklist

## Upload and initialize

1. Upload the package through the hosting control panel or FTP.
2. Set the site document root to `public/`.
3. Before sharing the staging URL, open `/admin/setup` and create the first administrator.
4. Sign in at `/admin/login`; confirm Admin OS shows version **1.7.0**.

## Health and error verification

5. Open **Admin → System Health** and run a health scan.
6. Confirm storage is writable, encryption/runtime config is ready and release checks pass.
7. Confirm the **AI Engine health** block is visible. Scan Only can be healthy without an external provider; Review/Safe Auto requires a configured provider.
8. Open **Admin → Error Monitor** and investigate unexplained active/critical incidents.
9. Confirm resolved non-critical incidents use the 90-day retention policy and critical history is retained.

## AI Engine activation

10. Open **Admin → AI & Search → Settings**.
11. For local audits only, activate **Scan Only**; no API key is required.
12. For AI enrichment, configure HTTPS API base URL, encrypted API key and a text model, then use **Review** or **Safe Auto**.
13. Use **Test Connection** / **Refresh Models** and choose a returned model.
14. Set request/token budgets, max jobs per run/hour and scheduling policy.
15. Queue the initial catalogue + SEO audit and process it from the web UI.
16. Confirm Review Queue / Change History / Restore functions before enabling Safe Auto.

## Content intelligence

17. Review suggested comparison and listicle opportunities.
18. Create draft comparison/listicle pages only from valid catalogue products.
19. Verify content before publishing; never convert unverified safety or commerce facts into claims.
20. Confirm published content appears in `/feeds/ai-content.json` and `/sitemap.xml`.
21. Confirm temporary `/compare?plants=...` combinations are `noindex,follow`.

## Agent discovery

22. Verify HTTP 200 for `/.well-known/agent-capabilities.json` and `/api/openapi.json`.
23. Confirm the capability manifest still marks purchase/payment/order submission as human-approval actions.

## Catalogue and commerce truth

24. Confirm the release contains no fabricated live variants, prices or stock.
25. Add verified live variants through the existing web Live Variant workflow.
26. Upload rights-cleared nursery photography and assign product media.
27. Confirm Merchant/product output stays gated by verified commerce data and approved media.

## Static asset verification

28. Confirm `/assets/css/app.css?v=1.7.0` and `/assets/js/app.js?v=1.7.0` return HTTP 200.
29. Verify light/dark theme, keyboard focus and responsive Admin/storefront controls on target devices.

**Normal deployment, monitoring, AI configuration and queue operation require no SSH/terminal commands.** `scripts/` and `tests/` remain for developer/CI validation only.
