<?php
$runtimeFile = dirname(__DIR__) . '/storage/private-domain/config/runtime.json';
$runtime = [];
if (is_file($runtimeFile)) {
    $decoded = json_decode((string)@file_get_contents($runtimeFile), true);
    if (is_array($decoded)) $runtime = $decoded;
}
$envOrRuntime = static function(string $env, string $runtimeKey, string $default='') use ($runtime): string {
    $value = trim((string)getenv($env));
    if ($value !== '') return $value;
    return trim((string)($runtime[$runtimeKey] ?? $default));
};
return [
    'name' => 'Sanjay Nursery',
    'tagline' => 'Plants · Pots · Greener Living',
    'environment' => getenv('APP_ENV') ?: 'staging',
    'currency' => 'INR',
    'version' => '1.7.0',
    'release_label' => 'Sanjay Nursery · AI Search, Catalogue & Content Intelligence · Staging',
    'support_email' => 'sanjaynursery@gmail.com',
    'session_idle_seconds' => (int)(getenv('SANJAY_SESSION_IDLE') ?: 1800),
    'session_absolute_seconds' => (int)(getenv('SANJAY_SESSION_ABSOLUTE') ?: 28800),
    'login_window_seconds' => (int)(getenv('SANJAY_LOGIN_WINDOW') ?: 900),
    'login_max_attempts' => (int)(getenv('SANJAY_LOGIN_MAX_ATTEMPTS') ?: 8),
    'customer_login_max_attempts' => (int)(getenv('SANJAY_CUSTOMER_LOGIN_MAX_ATTEMPTS') ?: 8),
    'tax_rate_percent' => (float)(getenv('SANJAY_TAX_RATE') ?: 18),
    'sandbox_webhook_secret' => $envOrRuntime('SANJAY_SANDBOX_WEBHOOK_SECRET','sandbox_webhook_secret'),
    'agent_signing_key' => $envOrRuntime('SANJAY_AGENT_SIGNING_KEY','agent_signing_key'),
    'monitor_retention_days' => 90,
    'monitor_scan_interval_seconds' => 300,
    'order_prefix' => 'SN',
    'invoice_prefix' => 'SNI',
    'catalog_mode' => 'plant_discovery_live_variant_catalogue',
];
