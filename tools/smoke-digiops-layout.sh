#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
mkdir -p "$work/public_html/ananta" "$work/private_html/ananta"
cp -a release/public/. "$work/public_html/ananta/"
cp -a release/private/. "$work/private_html/ananta/"
test ! -d "$work/private_html/ananta/storage"
export APP_ENV=staging
export SANJAY_DATA_KEY="$(php -r 'echo base64_encode(str_repeat("R", 32));')"
export SANJAY_SANDBOX_WEBHOOK_SECRET=ananta-digiops-smoke-secret
export SANJAY_AGENT_SIGNING_KEY=ananta-digiops-smoke-agent-key-0123456789
entry="$work/public_html/ananta/index.php"
php -r '$_SERVER["REQUEST_URI"]="/ananta/";$_SERVER["SCRIPT_NAME"]="/ananta/index.php";$_SERVER["HTTP_HOST"]="stage.example.test";$_SERVER["REMOTE_ADDR"]="127.0.0.1";require $argv[1];' "$entry" >"$work/home.html"
grep -q 'Ananta' "$work/home.html"
grep -q '/ananta/assets/css/app.css' "$work/home.html"
grep -q '/ananta/assets/css/ananta-premium.css' "$work/home.html"
test -s "$work/public_html/ananta/assets/css/supplier-os.css"
test -s "$work/public_html/ananta/assets/css/sourcing.css"
test -s "$work/private_html/ananta/src/sourcing_boq.php"
test -s "$work/private_html/ananta/templates/sourcing/boq.php"
php -r '$_SERVER["REQUEST_URI"]="/ananta/supplier/login";$_SERVER["SCRIPT_NAME"]="/ananta/index.php";$_SERVER["HTTP_HOST"]="stage.example.test";$_SERVER["REMOTE_ADDR"]="127.0.0.1";require $argv[1];' "$entry" >"$work/supplier-login.html"
grep -q 'ananta-auth-panel' "$work/supplier-login.html"
grep -q 'ananta-supplier-surface' "$work/supplier-login.html"
grep -Eq 'supplier-os[.]css[?]v=1[.]7[.]0-[a-f0-9]{12}' "$work/supplier-login.html"
if grep -q 'class="sn-newsletter"' "$work/supplier-login.html"; then echo "Unexpected consumer newsletter on supplier login"; exit 1; fi
test -s "$work/private_html/ananta/storage/public-domain/catalog/products.json"
first="$(sha256sum "$work/private_html/ananta/storage/public-domain/catalog/products.json" | cut -d' ' -f1)"
php -r '$_SERVER["REQUEST_URI"]="/ananta/nurseries";$_SERVER["SCRIPT_NAME"]="/ananta/index.php";$_SERVER["HTTP_HOST"]="stage.example.test";$_SERVER["REMOTE_ADDR"]="127.0.0.1";require $argv[1];' "$entry" >"$work/nurseries.html"
grep -q 'Sanjay Nursery' "$work/nurseries.html"
second="$(sha256sum "$work/private_html/ananta/storage/public-domain/catalog/products.json" | cut -d' ' -f1)"
test "$first" = "$second"
echo "PASS: DigiOps sibling-path boot, fingerprinted SupplierOS CSS, nursery route and preserved first-install seed."
