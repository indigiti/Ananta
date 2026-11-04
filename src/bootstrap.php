<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$GLOBALS['app'] = require $root . '/config/app.php';
if (($GLOBALS['app']['environment'] ?? 'staging') === 'production') {
    throw new RuntimeException('This Sanjay Nursery v1.7.0 package is staging/file-mode only. Production must use the PostgreSQL-authoritative release path.');
}
require_once $root . '/src/helpers.php';
require_once $root . '/src/foundation.php';
require_once $root . '/src/monitor.php';
if (PHP_SAPI !== 'cli') {
    @ini_set('display_errors','0');
    @ini_set('log_errors','1');
    monitor_register_handlers();
}
require_once $root . '/src/security.php';
require_once $root . '/src/admin.php';
require_once $root . '/src/tenancy.php';
require_once $root . '/src/supplier_branches.php';
require_once $root . '/src/variants.php';
require_once $root . '/src/marketplace_offers.php';
require_once $root . '/src/supplier_bulk_import.php';
require_once $root . '/src/sourcing.php';
require_once $root . '/src/commerce.php';
require_once $root . '/src/configurator.php';
require_once $root . '/src/bespoke.php';
require_once $root . '/src/makeos.php';
require_once $root . '/src/procurement.php';
require_once $root . '/src/quality.php';
require_once $root . '/src/fulfilment.php';
require_once $root . '/src/cms.php';
require_once $root . '/src/growth.php';
require_once $root . '/src/themes.php';
require_once $root . '/src/mediaos.php';
require_once $root . '/src/media_persistence.php';
require_once $root . '/src/designos.php';
require_once $root . '/src/intelligence.php';
require_once $root . '/src/shopping_intelligence.php';
require_once $root . '/src/passport.php';
require_once $root . '/src/tradeos.php';
require_once $root . '/src/integrations.php';
require_once $root . '/src/discovery.php';
require_once $root . '/src/commerceux.php';
require_once $root . '/src/ai_engine.php';
mediaos_ensure_catalog();
designos_ensure_dirs();
intelligence_ensure_dirs();
si_ensure_dirs();
discovery_ensure_dirs();
passport_ensure_dirs();
trade_ensure_dirs();
monitor_ensure_dirs();
monitor_maybe_maintain();
if (PHP_SAPI !== 'cli') register_shutdown_function('ai_shutdown_runner');
function request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
    $forwarded = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    return $forwarded === 'https';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('sanjaynursery_staging');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => request_is_https(),
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_path' => '/',
    ]);
}

// Session lifetime enforcement. Admin sessions are invalidated rather than silently extended forever.
$now = time();
$created = (int)($_SESSION['_created_at'] ?? 0);
$last = (int)($_SESSION['_last_activity'] ?? 0);
$idle = max(300, (int)($GLOBALS['app']['session_idle_seconds'] ?? 1800));
$absolute = max($idle, (int)($GLOBALS['app']['session_absolute_seconds'] ?? 28800));
if (($created && ($now - $created) > $absolute) || ($last && ($now - $last) > $idle)) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_regenerate_id(true);
}
if (empty($_SESSION['_created_at'])) $_SESSION['_created_at'] = $now;
$_SESSION['_last_activity'] = $now;

foundation_ensure_dirs();
ensure_storage_dirs();
require_once $root . '/src/digiops_seed.php';
ananta_seed_missing_public_data($root);

$catalogPath = $root . '/storage/public-domain/catalog/products.json';
$products = foundation_json_read($catalogPath, []);
$GLOBALS['products'] = is_array($products) ? $products : [];
$GLOBALS['catalog_checksum'] = foundation_checksum($catalogPath);

if (PHP_SAPI === 'cli-server') {
    $GLOBALS['base_path'] = '';
} else {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = rtrim(dirname($script), '/.');
    $GLOBALS['base_path'] = ($dir === '' || $dir === '/') ? '' : $dir;
}
