# Web-Only Installation — Sanjay Nursery v1.7.0

This staging build is designed so a hosting administrator can install, monitor and operate the application — including the AI Engine — without shell access.

### 1. Upload
Upload the extracted application folder using the hosting file manager or FTP. Keep `storage/`, `src/`, `config/` and all application folders together.

### 2. Document root
Set the domain/subdomain document root to the application's `public/` folder. Protected configuration, incidents, AI credentials, queues, reviews and history live outside `public/`.

### 3. Browser setup
Open:

`https://your-domain.example/admin/setup`

The first browser session receives the temporary setup claim. Create the administrator. The application generates its protected data-encryption key, sandbox webhook secret, agent signing key and installation identifier automatically.

### 4. Verify application health
Sign in, open **Admin → System Health**, and run a scan. Confirm storage, encryption and required release checks are healthy. Open **Admin → Error Monitor** if a check is degraded.

### 5. Activate AI Engine from the dashboard
Open **Admin → AI & Search → Settings**.

Choose one mode:

- **Scan Only** — no external AI provider is required. Catalogue/SEO/opportunity scans remain local and deterministic.
- **Review** — configure an OpenAI-compatible HTTPS API base URL, API key and text model. AI proposals are held for review.
- **Safe Auto** — same provider setup, but only allowlisted non-protected fields may auto-apply when they pass configured confidence thresholds.

The API key is encrypted with the application's protected data key and stored outside the public document root.

### 6. Test provider and select model
Use **Test Connection** or **Refresh Models** in the same settings page. The returned model IDs are made available in the Text Model field. Save the configuration and activate the engine.

No `.env` edit, SSH command or console worker is required.

### 7. Run initial intelligence audit
From **Admin → AI & Search → Overview**, queue the catalogue + SEO audit and use **Run queue**. Review:

- Catalogue Intelligence
- SEO Intelligence
- Content Opportunities
- Comparisons
- Listicles
- Review Queue
- Jobs
- Change History
- Agent Readiness

### 8. Scheduling
Choose Manual, Daily, Every 3 Days or Weekly. The bounded web runner processes small batches under a file lock and obeys per-run/per-hour limits. Changed-only enrichment avoids repeat provider calls when product data has not changed.

### 9. AI safety boundaries
AI Safe Auto cannot write price, stock, SKU, variants, delivery/availability, reviews/ratings, pet-safety or toxicity. High-risk botanical identity/size suggestions remain protected by higher-confidence review policy.

### 10. Comparisons and listicles
Create drafts from Admin → AI & Search. Published comparison/listicle pages are indexable, receive structured data and enter the sitemap. Ad-hoc `/compare?plants=...` pages remain `noindex,follow` to prevent index bloat. Admin marks published/draft content **STALE** when an underlying product is newer than that content item.

### 11. Monitoring and retention
Provider/queue failures feed Error Monitor. Recovery uses the existing incident lifecycle. Resolved non-critical incidents are purged after 90 days; critical incident history is retained.

### Security note
Complete `/admin/setup` immediately after deployment. Never place the application root itself under the public document root; only expose `public/`.
