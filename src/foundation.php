<?php
declare(strict_types=1);

/**
 * Sanjay Nursery Secure Foundation Store — v0.2.1 hardening layer.
 *
 * Durable writes use a deterministic lock file, optimistic checksum guard,
 * transaction journal, fsync where available, atomic rename, revision support
 * in the domain layer, and explicit confidential-data encryption helpers.
 */

function foundation_root(): string { return dirname(__DIR__) . '/storage'; }
function foundation_lock_dir(): string { return foundation_root() . '/locks'; }
function foundation_tx_dir(): string { return foundation_root() . '/journals/transactions'; }
function foundation_security_dir(): string { return foundation_root() . '/private-domain/security'; }

function foundation_ensure_dirs(): void {
    foreach ([foundation_lock_dir(), foundation_tx_dir(), foundation_security_dir(), foundation_root().'/tmp'] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create foundation directory: '.$dir);
        }
        @chmod($dir, 0750);
    }
}

function foundation_checksum(string $path): ?string {
    if (!is_file($path)) return null;
    $hash = @hash_file('sha256', $path);
    return is_string($hash) ? $hash : null;
}

function foundation_lock_path(string $path): string {
    foundation_ensure_dirs();
    return foundation_lock_dir() . '/' . hash('sha256', realpath(dirname($path)) . '|' . basename($path)) . '.lock';
}

/** @return resource */
function foundation_lock(string $path) {
    $lockPath = foundation_lock_path($path);
    $fh = @fopen($lockPath, 'c+');
    if (!$fh) throw new RuntimeException('Unable to open foundation lock.');
    if (!flock($fh, LOCK_EX)) { fclose($fh); throw new RuntimeException('Unable to acquire foundation lock.'); }
    return $fh;
}

/** @param resource $fh */
function foundation_unlock($fh): void {
    @flock($fh, LOCK_UN);
    @fclose($fh);
}

function foundation_journal_append(array $record): void {
    foundation_ensure_dirs();
    $day = gmdate('Ymd');
    $path = foundation_tx_dir() . '/tx-' . $day . '.ndjson';
    $line = json_encode($record, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n";
    $fh = @fopen($path, 'ab');
    if (!$fh) throw new RuntimeException('Unable to append transaction journal.');
    if (!flock($fh, LOCK_EX)) { fclose($fh); throw new RuntimeException('Unable to lock transaction journal.'); }
    fwrite($fh, $line);
    fflush($fh);
    if (function_exists('fsync')) @fsync($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    @chmod($path, 0640);
}

function foundation_transaction_id(): string {
    return gmdate('YmdHis') . '-' . bin2hex(random_bytes(8));
}

/**
 * Safe canonical JSON write.
 * $expectedChecksum enables optimistic concurrency: a stale caller fails closed.
 * Returns the new SHA-256 checksum.
 */
function foundation_json_write(string $path, array $payload, ?string $expectedChecksum = null, string $reason = 'write'): string {
    foundation_ensure_dirs();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Unable to create data directory.');

    $lock = foundation_lock($path);
    $txid = foundation_transaction_id();
    $before = foundation_checksum($path);
    try {
        if ($expectedChecksum !== null && !hash_equals($expectedChecksum, (string)$before)) {
            foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'CONFLICT','reason'=>$reason,'target'=>basename($path),'before'=>$before]);
            throw new RuntimeException('Write conflict detected. Reload the record and try again.');
        }

        foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'BEGIN','reason'=>$reason,'target'=>basename($path),'before'=>$before]);

        $json = json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) . "\n";
        $tmp = tempnam($dir, '.awtx_');
        if ($tmp === false) throw new RuntimeException('Unable to create temporary object.');
        $fh = @fopen($tmp, 'wb');
        if (!$fh) { @unlink($tmp); throw new RuntimeException('Unable to open temporary object.'); }
        $written = fwrite($fh, $json);
        if ($written === false || $written !== strlen($json)) { fclose($fh); @unlink($tmp); throw new RuntimeException('Incomplete temporary write.'); }
        fflush($fh);
        if (function_exists('fsync')) @fsync($fh);
        fclose($fh);
        @chmod($tmp, 0640);

        $verify = @file_get_contents($tmp);
        if ($verify === false) { @unlink($tmp); throw new RuntimeException('Unable to verify temporary object.'); }
        json_decode($verify, true, 512, JSON_THROW_ON_ERROR);

        if (!@rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('Atomic rename failed.'); }
        @chmod($path, 0640);
        $after = foundation_checksum($path);
        if ($after === null) throw new RuntimeException('Post-write checksum failed.');
        foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'COMMIT','reason'=>$reason,'target'=>basename($path),'before'=>$before,'after'=>$after]);
        return $after;
    } catch (Throwable $e) {
        try { foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'ABORT','reason'=>$reason,'target'=>basename($path),'error'=>get_class($e)]); } catch (Throwable) {}
        throw $e;
    } finally {
        foundation_unlock($lock);
    }
}

function foundation_json_read(string $path, array $default = []): array {
    if (!is_file($path)) return $default;
    $raw = @file_get_contents($path);
    if ($raw === false) return $default;
    try {
        $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($value) ? $value : $default;
    } catch (Throwable) {
        return $default;
    }
}

function foundation_incomplete_transactions(): array {
    foundation_ensure_dirs();
    $state = [];
    foreach (glob(foundation_tx_dir().'/tx-*.ndjson') ?: [] as $file) {
        $fh = @fopen($file, 'rb'); if (!$fh) continue;
        while (($line = fgets($fh)) !== false) {
            $row = json_decode($line, true); if (!is_array($row) || empty($row['txid'])) continue;
            $state[$row['txid']] = $row['state'] ?? null;
        }
        fclose($fh);
    }
    return array_keys(array_filter($state, fn($s)=>$s==='BEGIN'));
}

function runtime_config_file(): string { return foundation_root() . '/private-domain/config/runtime.json'; }

function runtime_config(): array {
    return foundation_json_read(runtime_config_file(), []);
}

function runtime_config_write(array $config): void {
    $config['updated_at'] = gmdate('c');
    foundation_json_write(runtime_config_file(), $config, foundation_checksum(runtime_config_file()), 'runtime.config');
    @chmod(runtime_config_file(), 0640);
}

function runtime_config_bootstrap(): array {
    $cfg = runtime_config();
    $changed = false;
    if (empty($cfg['installation_id'])) { $cfg['installation_id'] = bin2hex(random_bytes(16)); $changed = true; }
    if (empty($cfg['data_key'])) { $cfg['data_key'] = base64_encode(random_bytes(32)); $changed = true; }
    if (empty($cfg['sandbox_webhook_secret'])) { $cfg['sandbox_webhook_secret'] = bin2hex(random_bytes(32)); $changed = true; }
    if (empty($cfg['agent_signing_key'])) { $cfg['agent_signing_key'] = base64_encode(random_bytes(32)); $changed = true; }
    if (empty($cfg['created_at'])) { $cfg['created_at'] = gmdate('c'); $changed = true; }
    if ($changed) runtime_config_write($cfg);
    return $cfg;
}

function confidential_key(): ?string {
    $raw = trim((string)getenv('SANJAY_DATA_KEY'));
    if ($raw === '') $raw = trim((string)(runtime_config()['data_key'] ?? ''));
    if ($raw === '') return null;
    if (preg_match('/^[a-f0-9]{64}$/i', $raw)) {
        $bin = hex2bin($raw); return $bin === false ? null : $bin;
    }
    $bin = base64_decode($raw, true);
    return is_string($bin) && strlen($bin) === 32 ? $bin : null;
}

function confidential_crypto_available(): bool {
    return function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt') || function_exists('openssl_encrypt');
}

function confidential_encrypt(array $payload, string $context): array {
    $key = confidential_key();
    if ($key === null) throw new RuntimeException('Protected data-encryption key is unavailable. Complete web setup first.');
    $plain = json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $context, $nonce, $key);
        return ['v'=>1,'alg'=>'XCHACHA20-POLY1305','nonce'=>base64_encode($nonce),'ciphertext'=>base64_encode($cipher)];
    }
    if (function_exists('openssl_encrypt')) {
        $iv = random_bytes(12); $tag='';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $context, 16);
        if ($cipher === false) throw new RuntimeException('Authenticated encryption failed.');
        return ['v'=>1,'alg'=>'AES-256-GCM','nonce'=>base64_encode($iv),'tag'=>base64_encode($tag),'ciphertext'=>base64_encode($cipher)];
    }
    throw new RuntimeException('No authenticated encryption provider is available.');
}

function confidential_decrypt(array $envelope, string $context): array {
    $key = confidential_key();
    if ($key === null) throw new RuntimeException('Protected data-encryption key is unavailable. Complete web setup first.');
    $alg = (string)($envelope['alg'] ?? '');
    $nonce = base64_decode((string)($envelope['nonce'] ?? ''), true);
    $cipher = base64_decode((string)($envelope['ciphertext'] ?? ''), true);
    if (!is_string($nonce) || !is_string($cipher)) throw new RuntimeException('Invalid encrypted envelope.');
    if ($alg === 'XCHACHA20-POLY1305' && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $context, $nonce, $key);
        if ($plain === false) throw new RuntimeException('Confidential object authentication failed.');
    } elseif ($alg === 'AES-256-GCM' && function_exists('openssl_decrypt')) {
        $tag = base64_decode((string)($envelope['tag'] ?? ''), true);
        if (!is_string($tag)) throw new RuntimeException('Invalid encrypted envelope tag.');
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $context);
        if ($plain === false) throw new RuntimeException('Confidential object authentication failed.');
    } else {
        throw new RuntimeException('Unsupported confidential envelope algorithm.');
    }
    $out = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($out)) throw new RuntimeException('Decrypted payload is invalid.');
    return $out;
}

function confidential_json_write(string $path, array $payload, string $context, ?string $expectedChecksum = null): string {
    return foundation_json_write($path, confidential_encrypt($payload, $context), $expectedChecksum, 'confidential.write');
}

function confidential_json_read(string $path, string $context, array $default=[]): array {
    if (!is_file($path)) return $default;
    $env = foundation_json_read($path, []);
    if (!$env) return $default;
    return confidential_decrypt($env, $context);
}

/**
 * Atomic read-modify-write under one exclusive lock. Callback receives current array.
 * Useful for rate limits and other small coordination records.
 */
function foundation_json_mutate(string $path, callable $mutator, string $reason='mutate'): array {
    foundation_ensure_dirs();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Unable to create data directory.');
    $lock = foundation_lock($path);
    $txid = foundation_transaction_id();
    try {
        $current = foundation_json_read($path, []);
        $next = $mutator($current);
        if (!is_array($next)) throw new RuntimeException('Foundation mutator must return an array.');
        $before = foundation_checksum($path);
        foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'BEGIN','reason'=>$reason,'target'=>basename($path),'before'=>$before]);
        $json = json_encode($next, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        $tmp = tempnam($dir, '.awtx_'); if ($tmp===false) throw new RuntimeException('Unable to create temporary object.');
        $fh = @fopen($tmp,'wb'); if(!$fh){@unlink($tmp); throw new RuntimeException('Unable to open temporary object.');}
        if (fwrite($fh,$json)!==strlen($json)) { fclose($fh); @unlink($tmp); throw new RuntimeException('Incomplete temporary write.'); }
        fflush($fh); if(function_exists('fsync')) @fsync($fh); fclose($fh); @chmod($tmp,0640);
        json_decode((string)file_get_contents($tmp), true, 512, JSON_THROW_ON_ERROR);
        if(!@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Atomic rename failed.');}
        @chmod($path,0640); $after=foundation_checksum($path);
        foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'COMMIT','reason'=>$reason,'target'=>basename($path),'before'=>$before,'after'=>$after]);
        return $next;
    } catch(Throwable $e) {
        try { foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'ABORT','reason'=>$reason,'target'=>basename($path),'error'=>get_class($e)]); } catch(Throwable) {}
        throw $e;
    } finally { foundation_unlock($lock); }
}

/**
 * Atomic encrypted read-modify-write under one exclusive lock.
 * Used by customer/order/payment stores where concurrent requests must not lose updates.
 */
function confidential_json_mutate(string $path, string $context, callable $mutator, string $reason='confidential.mutate'): array {
    foundation_ensure_dirs();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Unable to create confidential data directory.');
    $lock = foundation_lock($path);
    $txid = foundation_transaction_id();
    try {
        $current = [];
        if (is_file($path)) {
            $env = foundation_json_read($path, []);
            if ($env) $current = confidential_decrypt($env, $context);
        }
        $next = $mutator($current);
        if (!is_array($next)) throw new RuntimeException('Confidential mutator must return an array.');
        $before = foundation_checksum($path);
        foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'BEGIN','reason'=>$reason,'target'=>basename($path),'before'=>$before]);
        $env = confidential_encrypt($next, $context);
        $json = json_encode($env, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        $tmp = tempnam($dir, '.awctx_'); if ($tmp===false) throw new RuntimeException('Unable to create confidential temporary object.');
        $fh = @fopen($tmp,'wb'); if(!$fh){@unlink($tmp); throw new RuntimeException('Unable to open confidential temporary object.');}
        if (fwrite($fh,$json)!==strlen($json)) { fclose($fh); @unlink($tmp); throw new RuntimeException('Incomplete confidential temporary write.'); }
        fflush($fh); if(function_exists('fsync')) @fsync($fh); fclose($fh); @chmod($tmp,0640);
        $check = foundation_json_read($tmp, []); confidential_decrypt($check, $context);
        if(!@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Atomic confidential rename failed.');}
        @chmod($path,0640); $after=foundation_checksum($path);
        foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'COMMIT','reason'=>$reason,'target'=>basename($path),'before'=>$before,'after'=>$after]);
        return $next;
    } catch(Throwable $e) {
        try { foundation_journal_append(['ts'=>gmdate('c'),'txid'=>$txid,'state'=>'ABORT','reason'=>$reason,'target'=>basename($path),'error'=>get_class($e)]); } catch(Throwable) {}
        throw $e;
    } finally { foundation_unlock($lock); }
}
