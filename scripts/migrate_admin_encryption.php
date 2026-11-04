<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$GLOBALS['app'] = require $root.'/config/app.php';
require_once $root.'/src/helpers.php';
require_once $root.'/src/foundation.php';
require_once $root.'/src/admin.php';

if (confidential_key() === null) {
    fwrite(STDERR, "SANJAY_DATA_KEY is missing/invalid. Provide a 32-byte key as base64 or 64-char hex.\n");
    exit(2);
}
$path = admin_user_file();
if (!is_file($path)) { echo "No admin user store exists; nothing to migrate.\n"; exit(0); }
$raw = foundation_json_read($path, []);
if (isset($raw['alg'], $raw['ciphertext'])) { echo "Admin user store is already encrypted.\n"; exit(0); }
if (!array_is_list($raw) || !$raw || !isset($raw[0]['password_hash'])) {
    fwrite(STDERR, "Admin user store is not a recognized v0.2.0 plaintext format.\n"); exit(3);
}
$backup = $path.'.legacy-'.gmdate('Ymd_His').'.bak';
if (!copy($path,$backup)) { fwrite(STDERR,"Unable to create migration backup.\n"); exit(4); }
@chmod($backup,0600);
confidential_json_write($path, $raw, 'sanjaynursery:admin-users:v1', foundation_checksum($path));
echo "PASS: encrypted ".count($raw)." administrator record(s). Backup: {$backup}\n";
