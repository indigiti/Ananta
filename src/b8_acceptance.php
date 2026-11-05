<?php
declare(strict_types=1);

/**
 * B8.5 staging pilot acceptance + encrypted data recovery.
 * Local machine checks and operator attestations are deliberately distinct.
 */
function b8_acceptance_file(): string {
    $t=trim((string)getenv('ANANTA_TEST_B8_ACCEPTANCE_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$t!=='')return $t;
    return data_root().'/private-domain/b8/acceptance.json';
}
function b8_acceptance_store(): array {
    return array_replace(['schema'=>1,'evidence'=>[]],
        confidential_json_read(b8_acceptance_file(),'ananta:b8:acceptance:v1',[]));
}
function b8_acceptance_evidence_steps(): array {
    return [
        'meta_inbound_signed'=>'Real signed WhatsApp message received for an approved sender',
        'meta_outbound_receipt'=>'Approved outbound template produced genuine Meta delivery/read receipt',
        'tenant_isolation_browser'=>'Independent nursery users verified on separate browser accounts',
        'merchant_center_fetched'=>'Merchant Center fetched the approved HTTPS product XML source',
        'merchant_center_diagnostics'=>'Merchant Center critical product and shipping issues reviewed',
        'search_console_access'=>'Search Console verified storefront property and analytics permissions',
        'rich_result_live'=>'Google Rich Results Test performed on live canonical product URLs',
        'checkout_pilot'=>'Real checkout path completed with authorized sandbox/test payment (no financial claims)',
        'mobile_browser'=>'Mobile and desktop UX checked on real supported browser/device combinations',
        'backup_offsite'=>'Encrypted backups copied to independent storage and isolated restore verified',
    ];
}
function b8_acceptance_evidence_save(string $step,string $reference): void {
    require_admin();
    if(!array_key_exists($step,b8_acceptance_evidence_steps()))
        throw new InvalidArgumentException('Unknown acceptance evidence step.');
    $reference=trim($reference);
    if(text_len($reference)<12||text_len($reference)>300||preg_match('/[\x00-\x1f\x7f]/',$reference))
        throw new InvalidArgumentException('Provide a 12–300 character traceable evidence reference without secrets.');
    $admin=(string)($_SESSION['admin_user']['guid']??'');
    if(!tenant_valid_id($admin))throw new RuntimeException('Valid platform administrator required.');
    confidential_json_mutate(b8_acceptance_file(),'ananta:b8:acceptance:v1',
        static function(array $data)use($step,$reference,$admin){
            $d=array_replace(['schema'=>1,'evidence'=>[]],$data);
            $d['evidence'][$step]=['reference'=>$reference,'at'=>gmdate('c'),'actor_id'=>$admin,
                'status'=>'operator_attested_not_independently_verified'];
            return $d;
        },'b8.acceptance.evidence');
    audit_event('b8.acceptance.operator_attestation',['step'=>$step,'actor_id'=>$admin]);
}
function b8_acceptance_backup_sources(): array {
    return [
        'sender-registry.json'=>[connect_file(),'ananta:connect:v1'],
        'message-runtime.json'=>[connect_runtime_file(),'ananta:connect:runtime:v1'],
        'crm-ledger.json'=>[connect_crm_file(),'ananta:connect:crm:v1'],
        'distribution-audit.json'=>[discover_distribution_file(),'ananta:discover:distribution:v1'],
        'merchant-evidence.json'=>[merchant_completion_reviews_file(),'ananta:discover:merchant-evidence:v1'],
        'google-product-status.json'=>[merchant_google_file(),'ananta:discover:google-status:v1'],
        'acp-checkout-sessions.json'=>[acp_sessions_file(),'ananta:acp:sessions:v1'],
        'acp-order-events.json'=>[acp_events_file(),'ananta:acp:events:v1'],
        'pilot-evidence.json'=>[b8_acceptance_file(),'ananta:b8:acceptance:v1'],
    ];
}
function b8_acceptance_backup_root(): string {
    return dirname(__DIR__).'/storage/backups';
}
/**
 * CLI-only backup. Existing encrypted envelopes are copied to a protected
 * location; cryptographic keys are NEVER written to the archive.
 */
function b8_acceptance_backup_create(): string {
    if(PHP_SAPI!=='cli')throw new RuntimeException('Backup requires Cloudways CLI.');
    if(confidential_key()===null)throw new RuntimeException('Private encryption key is not configured.');
    $root=b8_acceptance_backup_root();
    if(!is_dir($root)&&!mkdir($root,0700,true)&&!is_dir($root))throw new RuntimeException('Backup root unavailable.');
    $dir=$root.'/b8-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5));
    if(!mkdir($dir,0700))throw new RuntimeException('Unable to create backup.');
    @chmod($dir,0700);$manifest=['version'=>1,'created_at'=>gmdate('c'),'files'=>[]];
    try{
        foreach(b8_acceptance_backup_sources() as $name=>[$source,$context]){
            if(!is_file($source))continue;
            // Validate original BEFORE including it. Corrupt encrypted data is not a backup.
            confidential_json_read($source,$context,[]);
            $dest=$dir.'/'.$name;
            if(!copy($source,$dest))throw new RuntimeException('Encrypted snapshot copy failed.');
            @chmod($dest,0600);
            // Validate the copied bytes, not only the original file.
            confidential_json_read($dest,$context,[]);
            $manifest['files'][$name]=['sha256'=>hash_file('sha256',$dest),'bytes'=>filesize($dest)];
        }
        if(!$manifest['files'])throw new RuntimeException('No encrypted B8 data exists to back up.');
        $json=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(file_put_contents($dir.'/manifest.json',$json."\n",LOCK_EX)===false)
            throw new RuntimeException('Backup manifest write failed.');
        @chmod($dir.'/manifest.json',0600);
        return $dir;
    }catch(Throwable $e){
        foreach(glob($dir.'/*')?:[] as $f)@unlink($f);
        @rmdir($dir);throw $e;
    }
}
/** Isolated read/decrypt drill. Does not overwrite ANY live runtime records. */
function b8_acceptance_backup_verify(string $path): array {
    if(PHP_SAPI!=='cli')throw new RuntimeException('Restore verification requires Cloudways CLI.');
    $dir=realpath($path);
    if(!$dir||!is_dir($dir)||is_link($path))throw new InvalidArgumentException('Backup directory unavailable.');
    $manifestFile=$dir.'/manifest.json';
    if(!is_file($manifestFile)||is_link($manifestFile)||filesize($manifestFile)>65536)
        throw new InvalidArgumentException('Backup manifest missing or invalid.');
    $manifest=json_decode((string)file_get_contents($manifestFile),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($manifest)||($manifest['version']??null)!==1||empty($manifest['files']))
        throw new InvalidArgumentException('Invalid backup manifest.');
    $sources=b8_acceptance_backup_sources();
    $checked=[];
    foreach($manifest['files'] as $name=>$meta){
        if(!isset($sources[$name])||!is_array($meta)||!preg_match('/^[a-z-]+\.json$/D',$name))
            throw new RuntimeException('Unrecognized backup object.');
        $file=$dir.'/'.$name;
        if(!is_file($file)||is_link($file)||realpath(dirname($file))!==$dir||
            !hash_equals((string)($meta['sha256']??''),(string)hash_file('sha256',$file)))
            throw new RuntimeException('Encrypted backup integrity mismatch.');
        if((int)($meta['bytes']??0)!==filesize($file))throw new RuntimeException('Backup size mismatch.');
        $data=confidential_json_read($file,$sources[$name][1],[]);
        if(!is_array($data))throw new RuntimeException('Encrypted backup could not be restored.');
        $checked[]=$name;
    }
    return ['verified'=>true,'checked_files'=>$checked,'verified_at'=>gmdate('c'),
        'restoration'=>'isolated_decryption_only','live_data_modified'=>false];
}
function b8_acceptance_snapshot(): array {
    if(PHP_SAPI!=='cli')require_admin();
    $runtime=connect_runtime_store();$crm=connect_crm_store();
    $benchStart=microtime(true);
    $distribution=discover_distribution_schema_audit();
    $feedAuditMs=round((microtime(true)-$benchStart)*1000,2);
    $cases=[
        'encryption'=>confidential_key()!==null&&confidential_crypto_available(),
        'php_curl'=>extension_loaded('curl'),
        'php_json'=>extension_loaded('json'),
        'no_broadcast_by_default'=>(string)getenv('ANANTA_CONNECT_RFQ_AUTOMATION')!=='1',
        'merchant_local_audit'=>$distribution['valid'],
        'merchant_feed_reconciled'=>$distribution['sku_count']===count(discover_merchant_rows()),
        'runtime_outside_webroot'=>!str_starts_with(realpath(dirname(connect_runtime_file()))?:'',
            realpath(dirname(__DIR__).'/public')?:'/NO_PUBLIC'),
    ];
    $queue=array_count_values(array_map(static fn($job)=>(string)($job['status']??''),array_values($runtime['outbox'])));
    $review=(int)($queue['needs_review']??0);
    $last=discover_distribution_store();
    return [
        'at'=>gmdate('c'),'machine_checks'=>$cases,
        'checks_pass'=>count(array_filter($cases)),'checks_total'=>count($cases),
        'queue'=>[
            'total'=>count($runtime['outbox']),'needs_review'=>$review,
            'failed'=>(int)($queue['failed']??0),'delivered'=>(int)($queue['delivered']??0),
            'read'=>(int)($queue['read']??0),'unknown_provider_receipts'=>count($runtime['orphan_receipts']),
        ],
        'crm'=>['conversations'=>count($runtime['conversations']),'messages'=>count($runtime['messages']),
            'leads'=>count($crm['leads']),'tasks'=>count($crm['tasks'])],
        'feed'=>['eligible'=>$distribution['sku_count'],'issues'=>$distribution['issues'],
            'fingerprint'=>$distribution['feed_sha256'],
            'last_recorded_snapshot'=>$last['snapshots']?end($last['snapshots'])['at']:null],
        'performance_sample'=>['feed_audit_ms_single_sample'=>$feedAuditMs,
            'message_ledger_bytes'=>is_file(connect_runtime_file())?filesize(connect_runtime_file()):0,
            'crm_ledger_bytes'=>is_file(connect_crm_file())?filesize(connect_crm_file()):0,
            'is_p95_or_field_latency_measurement'=>false],
        'operator_evidence'=>b8_acceptance_store()['evidence'],
        'evidence_required'=>b8_acceptance_evidence_steps(),
        'field_acceptance_verified_independently'=>false,
        'production_ready'=>false,
    ];
}
