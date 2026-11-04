#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

find . -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null

# The active catalogue contains enquiry-first plant varieties. Commerce workflows
# that require priced pot-size SKUs remain deferred until live batch data is assigned.
for t in foundation_hardening smoke variants commerce_core procurement quality cms growth themes mediaos shopping_intelligence intelligence security commerce_ux discovery catalogue_expansion monitoring theme_ui ai_engine ui_performance release_regression marketplace_offers tenancy media_persistence; do
  echo "== $t =="
  php "tests/$t.php"
done

echo "PASS: full Sanjay Nursery v1.7.0 AI Search, Catalogue & Content Intelligence regression suite"
echo "PASS: live-variant commerce, plant photography approval, discovery/search/Finder and consent-aware intelligence controls verified with isolated QA fixtures. Store/cart/checkout remain fail-open from discovery/intelligence services."
