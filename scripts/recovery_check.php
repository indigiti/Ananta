<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root.'/src/foundation.php';
foundation_ensure_dirs();
$incomplete = foundation_incomplete_transactions();
$tmp = [];
foreach (glob($root.'/storage/*/.awtx_*') ?: [] as $p) $tmp[]=$p;
foreach (glob($root.'/storage/*/*/.awtx_*') ?: [] as $p) $tmp[]=$p;
if ($incomplete) {
    fwrite(STDERR, "FAIL: incomplete transaction IDs:\n - ".implode("\n - ",$incomplete)."\n");
    exit(1);
}
echo "PASS: no incomplete foundation transactions detected.\n";
if ($tmp) echo "NOTE: temporary transaction files detected: ".count($tmp)."\n";
