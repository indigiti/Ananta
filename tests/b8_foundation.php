<?php
declare(strict_types=1);
/** B8.1 acceptance: official sender provisioning, tenant consent, Merchant eligibility and SEO truth. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('M',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=b8-test');
putenv('SANJAY_AGENT_SIGNING_KEY=b8-agent-test-012345678901');
$_SERVER['SCRIPT_NAME']='/ananta/index.php';
$_SERVER['HTTP_HOST']='stage.example.invalid';
$_SERVER['REMOTE_ADDR']='127.0.0.81';
$tag=bin2hex(random_bytes(5));
$files=[
    'tenancy'=>$root.'/storage/tmp/b8-tenants-'.$tag.'.json',
    'market'=>$root.'/storage/tmp/b8-market-'.$tag.'.json',
    'connect'=>$root.'/storage/tmp/b8-connect-'.$tag.'.json',
];
putenv('ANANTA_TEST_TENANT_FILE='.$files['tenancy']);
putenv('ANANTA_TEST_MARKETPLACE_FILE='.$files['market']);
putenv('ANANTA_TEST_CONNECT_FILE='.$files['connect']);
require $root.'/src/bootstrap.php';
$errors=[];$assert=static function(bool $ok,string $msg)use(&$errors){if(!$ok)$errors[]=$msg;};
$deny=static function(callable $cb,string $msg)use(&$errors){
    try{$cb();$errors[]=$msg;}catch(InvalidArgumentException|RuntimeException){}
};
try {
    $deny(static fn()=>connect_account_save([]),'Anonymous user provisioned Meta sender.');
    $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'b8-admin@example.invalid','name'=>'B8 QA','role'=>'admin'];
    $a=marketplace_supplier_save(['name'=>'B8 First Nursery','city'=>'Pune','phone'=>'919111110001','status'=>'verified']);
    $b=marketplace_supplier_save(['name'=>'B8 Second Nursery','city'=>'Pune','phone'=>'919111110002','status'=>'verified']);
    $idA=tenant_invite_issue($a,'b8-a@example.invalid','owner');
    $idB=tenant_invite_issue($b,'b8-b@example.invalid','manager');
    $account=['tenant_id'=>$a,'name'=>'B8 First Official Sender',
        'phone_number_id'=>'123456789012345','waba_id'=>'987654321098765',
        'token_env'=>'ANANTA_WA_TOKEN_FIRST','secret_env'=>'ANANTA_WA_SECRET_FIRST',
        'verify_env'=>'ANANTA_WA_VERIFY_FIRST',
    ];
    $assert(connect_account_save($account)===$a,'Account mapping not saved.');
    $deny(static fn()=>connect_account_save(array_replace($account,[
        'tenant_id'=>$b
    ])),'Same Meta Phone ID assigned to multiple nurseries.');
    $deny(static fn()=>connect_account_save(array_replace($account,[
        'token_env'=>'https://example.com/steal-token'
    ])),'Unsafe credential reference accepted.');
    $deny(static fn()=>connect_account_save(array_replace($account,[
        'phone_number_id'=>'555; DROP TABLE'
    ])),'Non-digit Meta sender ID accepted.');
    $saved=connect_store()['accounts'][$a]??null;
    $assert(is_array($saved)&&($saved['enabled']??true)===false
        &&($saved['send_enabled']??true)===false,'New sender unexpectedly enabled.');
    $assert(!isset($saved['access_token'])&&!isset($saved['app_secret']),'Meta secrets stored in registry.');
    $summary=connect_admin_summary();
    $assert(count($summary['accounts'])===1&&$summary['send_enabled']===false,'Admin connection summary incorrect.');
    $raw=(string)file_get_contents($files['connect']);
    $assert(!str_contains($raw,'B8 First Official Sender'),'Sender registry stored in plaintext.');
    putenv('ANANTA_WA_TOKEN_FIRST='.str_repeat('T',48));
    putenv('ANANTA_WA_SECRET_FIRST='.str_repeat('S',40));
    putenv('ANANTA_WA_VERIFY_FIRST='.str_repeat('V',35));
    $status=connect_account_status($saved);
    $assert($status['token_ready']&&$status['app_secret_ready']&&$status['verify_token_ready'],'Environment secret readiness detection broken.');
    $assert($status['connected']===false&&$status['send_enabled']===false,'Local environment variables claimed Meta connectivity.');
    $payload='{"entry":[{"id":"999"}]}';
    $sig='sha256='.hash_hmac('sha256',$payload,str_repeat('S',40));
    $assert(connect_verify_meta_signature($payload,$sig,str_repeat('S',40)),'Valid Meta signature rejected.');
    $assert(!connect_verify_meta_signature($payload.'changed',$sig,str_repeat('S',40)),'Tampered payload signature accepted.');
    $assert(!connect_verify_meta_signature($payload,'sha1=abcdef',str_repeat('S',40)),'Unsupported signature algorithm accepted.');
    $phone='919811112233';
    connect_consent_record($a,$phone,'marketing','opt_in','signed_form','Signed document ref 12345');
    $assert(connect_consent_status($a,$phone,'marketing')==='opt_in','Consent grant not persisted.');
    $assert(connect_consent_status($b,$phone,'marketing')==='unknown','Consent leaked to competing nursery.');
    $assert(connect_consent_status($a,$phone,'service')==='unknown','Marketing permission incorrectly expanded to service.');
    connect_consent_record($a,$phone,'marketing','opt_out','customer_message','Reply STOP 18:02 IST');
    $assert(connect_consent_status($a,$phone,'marketing')==='opt_out','Contact opt-out not persisted.');
    $assert(connect_message_allowed($a,$phone,'marketing')===false,'Outbound sends enabled during foundation milestone.');
    $assert(!str_contains((string)file_get_contents($files['connect']),$phone),'Contact number leaked from encrypted registry.');
    $deny(static fn()=>connect_consent_record($a,'91bad','marketing','opt_in','signed_form','Evidence 123456'),
        'Invalid phone accepted.');
    unset($_SESSION['admin_user']);
    tenant_invite_accept($idA,'Owner First','B8-First-Password-2026!');
    tenant_invite_accept($idB,'Manager Second','B8-Second-Password-2026!');
    $assert(tenant_login('b8-b@example.invalid','B8-Second-Password-2026!'),'Tenant B login failed.');
    $deny(static fn()=>connect_consent_record($a,$phone,'service','opt_in','web_form','Form evidence reference'),
        'Tenant B wrote tenant A consent.');
    $deny(static fn()=>connect_sender_for_tenant($a),'Tenant B viewed tenant A sender account.');
    $assert(connect_consent_status($b,$phone,'marketing')==='unknown','Tenant B accessed other nursery consent.');
    tenant_logout();
    $assert(tenant_login('b8-a@example.invalid','B8-First-Password-2026!'),'Tenant A login failed.');
    connect_consent_record($a,'919888777666','rfq_updates','opt_in','web_form','RFQ form consent 42');
    $assert(connect_consent_status($a,'919888777666','rfq_updates')==='opt_in','Owner cannot record own tenant consent.');
    $own=connect_sender_for_tenant($a);
    $assert(($own['tenant_id']??'')===$a,'Own sender status unavailable.');
    tenant_logout();
    $report=discover_merchant_report();
    $assert($report['feed_rows']===$report['eligible'],'Eligible counts do not reconcile to the Merchant feed.');
    $assert($report['eligible']+$report['blocked']===$report['candidates'],'Candidate reconciliation failed.');
    $assert($report['merchant_api_connected']===false,'Merchant API falsely marked connected.');
    $feed=merchant_xml();
    $assert(str_contains($feed,'<rss ')&&str_contains($feed,'xmlns:g='),'Merchant XML root is missing.');
    $assert(!str_contains($feed,'#configure'),'Uncrawlable fragment URLs remain in Merchant XML.');
    $assert(!str_contains($feed,'<g:brand>Ananta</g:brand>'),'Marketplace impersonated the founding retail nursery as manufacturer.');
    if(class_exists('DOMDocument')){
        $dom=new DOMDocument();
        $ok=@$dom->loadXML($feed,LIBXML_NONET);
        $assert($ok===true,'Merchant XML is not well formed.');
        if($ok){
            $items=$dom->getElementsByTagName('item');
            $assert($items->length===$report['feed_rows'],'Feed item count differs from eligibility report.');
            $links=$dom->getElementsByTagName('link');
            foreach($links as $link)if(str_contains($link->textContent,'#configure'))
                $errors[]='Merchant item link uses an anchor, not crawlable SKU selection.';
        }
    }
    foreach(products_public() as $p){
        if(product_has_variants($p)){
            foreach(variants_for_product($p,true) as $v){
                $reasons=discover_variant_reasons($v,$p);
                if(!$reasons){
                    $assert(variant_is_merchant_eligible($v,$p),'Discover marked a non-eligible variant ready.');
                    $merchant=merchant_variant_dto($v,$p);
                    $assert(str_contains($merchant['link'],'?variant='),'Merchant SKU link has no server-readable variant.');
                    $assert($merchant['id']===$v['sku'],'Merchant SKU identity not stable.');
                }
                if(($v['sales_mode']??'')==='enquiry')
                    $assert(in_array('enquiry_only',$reasons,true),'Enquiry-only variant became purchasable.');
            }
        }else{
            if(discover_parent_reasons($p)===[])
                $assert(!empty(merchant_product_dto($p)),'Parent eligibility does not agree with serializer.');
        }
    }
    $productTemplate=(string)file_get_contents($root.'/templates/product.php');
    $assert(str_contains($productTemplate,'$selectedVariant'),'Product page does not preselect Merchant SKU.');
    $assert(str_contains($productTemplate,'?variant=' )===false,'Product template hardcodes an unvalidated query URL.');
    $schema=(string)file_get_contents($root.'/src/growth.php');
    $assert(str_contains($schema,'discover_variant_url($p,$v)'),'Structured data does not link to preselected SKU.');
    $assert(str_contains((string)file_get_contents($root.'/templates/admin/discover-merchant.php'),'Product-level audit'),'Merchant admin audit screen missing.');
    foreach(['connect','discover/merchant'] as $route){
        $assert(str_contains((string)file_get_contents($root.'/public/index.php'),'/admin/'.$route),'Admin route missing '.$route);
    }
} catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($files as $path)@unlink($path);
if($errors){fwrite(STDERR,"FAIL B8.1 foundation:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: encrypted Meta configuration, tenant-safe consent and opt-out, tamper-proof signature checks, disabled outbound, Merchant feed validity, SKU routing and diagnostics.\n";
