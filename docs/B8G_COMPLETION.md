# B8-G Completion — controlled catalogue remediation and Google Merchant diagnostics

Release scope: staging; no Google/OpenAI account approval or deployment claimed.

## Baseline and Cloudways inventory

The immutable resources/B8G_BASELINE_SKUS.json contains 376 enquiry-only SKU/slug pairs from commit 8a33863fb795. The deployed Ananta admin dashboard at /ananta/admin/discover/completion compares them against its persistent products_all(), variant records, stock tracking and approved MediaOS hero images. It never overwrites live stock or copies the repository seed into Cloudways storage.

Export an admin-only catalogue reconciliation CSV, including new, renamed and missing SKUs and every eligible-item blocker. View 40 records per page. Review actual product price, tracked inventory, variant status, seller identity, image rights and buyer checkout path before enabling any Shopping submission.

CLI within the private Cloudways release: php src/merchant_completion_cli.php status
Private report CSV: php src/merchant_completion_cli.php catalogue-csv /private/path/new-report.csv

## Fail-closed Merchant eligibility

Positive actual INR price, active commerce mode, tracked positive stock, an approved/rights-cleared hero and live SKU are necessary but insufficient. Each SKU also requires explicit brand and GTIN/MPN/no-identifier review, a referenced stock check renewed within 48 hours, and administrator-reviewed seller-of-record, public merchant domain, shipping, returns and functional checkout. Seller/operator attestation is stored encrypted and audit-logged; it is NOT equivalent to Merchant Center approval. Missing evidence causes zero-priced/unverified products to be excluded from /feeds/google-merchant.xml.

ProductGroup variants continue to use unique server-readable ?variant=SKU links while the shared ProductGroup parent URL remains canonical, following Google's recommended single-page ProductGroup strategy. Do not invent per-variant canonical URLs or shipping costs. Configure verified India shipping and returns in the relevant Merchant Center account. A staging/test domain cannot be certified as the public merchant domain.

## Google Merchant activation and monitoring

An authorized Merchant Center operator must verify the genuine public domain, legal seller, delivery eligibility, live-plant returns policy, photography rights and purchasing path, then register the hosted HTTPS XML as a scheduled primary source. A zero-row feed is not a ready catalogue.

Use ANANTA_MERCHANT_ACCOUNT_ID and ANANTA_MERCHANT_OAUTH_TOKEN (content-scope OAuth) in private Cloudways environment only; never commit tokens. The dashboard's Fetch actual Google status or php src/merchant_completion_cli.php google-readonly calls Merchant API processed-products list with pagination. It reports offerId, India FREE_LISTINGS destination status and item-level issue codes. Shopping Ads status is not treated as Free Listings approval. Unknown, missing and unprocessed items are never marked approved. The current release has no Google product write or data-source creation API and needs external OAuth authorization and token rotation.

## OpenAI partner feed eligibility

Existing public AI/agent JSON feeds continue to describe catalogue items, including honest enquiry-only descriptions. The admin-only openai.csv preview exports Google-compatible CSV rows containing only evidence-reviewed purchasable SKUs, blocking an empty export. OpenAI direct partner onboarding and supported INR/India market must be confirmed independently; there is no SFTP upload, feed registration, guaranteed citation or approval. Keep OAI-SearchBot crawl policy accurate at the actual host root; an /ananta/robots.txt application route is not the host-root policy.

## Release checks

PHP syntax and full regression (including tests/b8g_completion.php), package inclusion of resources/B8G_BASELINE_SKUS.json and private PHP modules, source/offer status tests and Cloudways split-path smoke tests must pass before merge. Following deployment run the actual Cloudways status command, review the discrepancy CSV and verify the public storefront with external browser, Merchant Center and Search Console evidence. No merge implies a completed production Merchant activation.
