<?php
declare(strict_types=1);
/** B8.2: signed official webhook, tenant CRM, consent-gated queue and receipts. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('C',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=b82-test');
putenv('SANJAY_AGENT_SIGNING_KEY=b82-agent-test-01234567890');
$_SERVER['SCRIPT_NAME']='/ananta/index.php';
$_SERVER['HTTP_HOST']='stage.example.invalid';
$_SERVER['REMOTE_ADDR']='127.0.0.87';
$tag=bin2hex(random_bytes(6));
$files=[
  'tenants'=>$root.'/storage/tmp/b82-tenant-'.$tag.'.json',
  'market'=>$root.'/storage/tmp/b82-market-'.$tag.'.json',
  'connect'=>$root.'/storage/tmp/b82-connect-'.$tag.'.json',
  'runtime'=>$root.'/storage/tmp/b82-runtime-'.$tag.'.json',
];
putenv('ANANTA_TEST_TENANT_FILE='.$files['tenants']);
putenv('ANANTA_TEST_MARKETPLACE_FILE='.$files['market']);
putenv('ANANTA_TEST_CONNECT_FILE='.$files['connect']);
putenv('ANANTA_TEST_CONNECT_RUNTIME_FILE='.$files['runtime']);
putenv('ANANTA_WA_TOKEN_TEST='.str_repeat('T',44));
putenv('ANANTA_WA_SECRET_TEST='.str_repeat('S',40));
putenv('ANANTA_WA_VERIFY_TEST='.str_repeat('V',35));
putenv('ANANTA_WA_APPROVED_TEMPLATES=rfq_update:en,service_notice:en');
putenv('ANANTA_CONNECT_ALLOW_SEND=1');
require $root.'/src/bootstrap.php';
$errors=[];$assert=static function(bool $yes,string $why)use(&$errors){if(!$yes)$errors[]=$why;};
$deny=static function(callable $f,string $why)use(&$errors){
    try{$f();$errors[]=$why;}catch(InvalidArgumentException|RuntimeException){}
};
$sign=static fn(string $data):string=>'sha256='.hash_hmac('sha256',$data,str_repeat('S',40));
$callback=static function(string $waba,string $phoneId,array $messages=[],array $statuses=[]):string{
    return json_encode(['object'=>'whatsapp_business_account',
        'entry'=>[['id'=>$waba,'changes'=>[['field'=>'messages','value'=>[
          'messaging_product'=>'whatsapp','metadata'=>['phone_number_id'=>$phoneId],
          'messages'=>$messages,'statuses'=>$statuses,
        ]]]]]],JSON_THROW_ON_ERROR);
};
try{
    $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'qa@example.invalid','name'=>'QA','role'=>'admin'];
    $a=marketplace_supplier_save(['name'=>'Connect Alpha Nursery','city'=>'Pune','phone'=>'919800000001','status'=>'verified']);
    $b=marketplace_supplier_save(['name'=>'Connect Beta Nursery','city'=>'Pune','phone'=>'919800000002','status'=>'verified']);
    $ta=tenant_invite_issue($a,'connect-alpha@example.invalid','owner');
    $tb=tenant_invite_issue($b,'connect-beta@example.invalid','manager');
    foreach([[$a,'121212121212','313131313131'],[$b,'454545454545','565656565656']] as [$tenant,$phoneId,$waba]){
        connect_account_save(['tenant_id'=>$tenant,'name'=>'Meta Sender '.$phoneId,
            'phone_number_id'=>$phoneId,'waba_id'=>$waba,
            'token_env'=>'ANANTA_WA_TOKEN_TEST','secret_env'=>'ANANTA_WA_SECRET_TEST',
            'verify_env'=>'ANANTA_WA_VERIFY_TEST']);
        connect_account_activate($tenant,'inbound',true);
        connect_account_activate($tenant,'outbound',true);
    }
    $assert(connect_webhook_verify('121212121212','subscribe',str_repeat('V',35),'challenge123')==='challenge123',
        'Meta verify callback failed.');
    $assert(connect_webhook_verify('121212121212','subscribe','wrong','challenge123')===null,
        'Wrong verify token accepted.');
    $payload=$callback('313131313131','121212121212',[
        ['id'=>'wamid.inbound-A-001','from'=>'919811112233','type'=>'text',
         'text'=>['body'=>'Please share plants and rates.'],'timestamp'=>time()],
    ]);
    $deny(static fn()=>connect_webhook_payload('121212121212',$payload,'sha256='.str_repeat('0',64)),
        'Bad webhook HMAC accepted.');
    $deny(static fn()=>connect_webhook_payload('454545454545',$payload,$sign($payload)),
        'Signed callback misrouted into another tenant.');
    $wrong=$callback('999999999999','121212121212');
    $deny(static fn()=>connect_webhook_payload('121212121212',$wrong,$sign($wrong)),
        'Wrong WABA identity accepted.');
    $first=connect_webhook_payload('121212121212',$payload,$sign($payload));
    $assert($first['new_inbound']===1,'Inbound message not persisted.');
    $dupe=connect_webhook_payload('121212121212',$payload,$sign($payload));
    $assert($dupe['duplicates']===1&&count(connect_runtime_store()['messages'])===1,
        'Webhook replay created duplicate conversation message.');
    $assert(!str_contains((string)file_get_contents($files['runtime']),'Please share plants'),
        'CRM plaintext leaked to disk.');
    unset($_SESSION['admin_user']);
    tenant_invite_accept($ta,'Alpha Owner','Connect-Alpha-2026-Secure!');
    tenant_invite_accept($tb,'Beta Manager','Connect-Beta-2026-Secure!');
    $assert(tenant_login('connect-beta@example.invalid','Connect-Beta-2026-Secure!'),'Beta login failed.');
    $assert(connect_inbox_list($b)===[],'Tenant B saw tenant A conversation.');
    $aThreadId=array_values(connect_runtime_store()['conversations'])[0]['id'];
    $assert(connect_inbox_thread($b,$aThreadId)===null,'Competing nursery read private thread.');
    $deny(static fn()=>connect_inbox_mark_read($b,$aThreadId),'Tenant B marked another tenant read.');
    $deny(static fn()=>connect_enqueue($a,'919811112233','service','rfq_update','en','unique-alpha-first'),
        'Tenant B enqueued for tenant A.');
    tenant_logout();
    $assert(tenant_login('connect-alpha@example.invalid','Connect-Alpha-2026-Secure!'),'Alpha login failed.');
    $list=connect_inbox_list($a);
    $assert(count($list)===1&&$list[0]['unread']===1,'Tenant inbox missing unread message.');
    $assert(count(connect_inbox_thread($a,$aThreadId)['messages'])===1,'Tenant messages missing.');
    $deny(static fn()=>connect_enqueue($a,'919811112233','service','rfq_update','en','unique-alpha-first'),
        'Unconsented outbound message accepted.');
    connect_consent_record($a,'919811112233','service','opt_in','signed_form','Signed evidence reference 200');
    $deny(static fn()=>connect_enqueue($a,'919811112233','service','unapproved','en','unique-alpha-other'),
        'Unknown template accepted.');
    $id=connect_enqueue($a,'919811112233','service','rfq_update','en','unique-alpha-first');
    $assert(tenant_valid_id($id),'Outbox message ID is invalid.');
    $assert(connect_enqueue($a,'919811112233','service','rfq_update','en','unique-alpha-first')===$id,
        'Repeated idempotent request duplicated outbox.');
    $deny(static fn()=>connect_enqueue($a,'919800001111','service','rfq_update','en','unique-alpha-first'),
        'Idempotency key collision changed payload.');
    $assert(count(connect_runtime_store()['outbox'])===1,'Unexpected queue records.');
    $attempt=connect_worker_step(static fn($job,$account)=>['http'=>200,'provider_id'=>'wamid.outbound-001']);
    $assert($attempt['result']==='submitted','Queue did not record provider submission.');
    $assert(connect_queue_summary($a)['submitted']===1,'Provider acceptance not visible.');
    $status=$callback('313131313131','121212121212',[],[
       ['id'=>'wamid.outbound-001','recipient_id'=>'919811112233','status'=>'delivered','timestamp'=>time()],
    ]);
    $rc=connect_webhook_payload('121212121212',$status,$sign($status));
    $assert($rc['receipts']===1,'Delivered provider receipt not persisted.');
    $statusRead=$callback('313131313131','121212121212',[],[
       ['id'=>'wamid.outbound-001','recipient_id'=>'919811112233','status'=>'read','timestamp'=>time()],
    ]);
    connect_webhook_payload('121212121212',$statusRead,$sign($statusRead));
    connect_webhook_payload('121212121212',$status,$sign($status));
    $assert(connect_runtime_store()['outbox'][$id]['status']==='read',
        'Late delivered receipt downgraded read receipt.');
    $outbound=array_values(array_filter(connect_inbox_thread($a,$aThreadId)['messages'],
        static fn($m)=>($m['direction']??'')==='outbound'));
    $assert(count($outbound)===1&&$outbound[0]['status']==='read',
        'CRM sent-message status did not reconcile.');
    connect_inbox_mark_read($a,$aThreadId);
    $assert(connect_inbox_list($a)[0]['unread']===0,'Mark read did not persist.');
    $stop=$callback('313131313131','121212121212',[
       ['id'=>'wamid.inbound-A-STOP','from'=>'919811112233','type'=>'text',
         'text'=>['body'=>'STOP'],'timestamp'=>time()],
    ]);
    connect_webhook_payload('121212121212',$stop,$sign($stop));
    $assert(!connect_message_allowed($a,'919811112233','service'),
        'Signed STOP failed to prevent outbound messaging.');
    $deny(static fn()=>connect_enqueue($a,'919811112233','service','rfq_update','en','unique-alpha-stop'),
        'STOP contact remained send-eligible.');
    connect_consent_record($a,'919888776655','service','opt_in','signed_form','Signed reference 3000');
    $ambig=connect_enqueue($a,'919888776655','service','rfq_update','en','unique-alpha-uncertain');
    $result=connect_worker_step(static fn()=>['http'=>503,'error'=>'server_uncertain']);
    $assert($result['result']==='needs_review','Ambiguous Meta failure was blindly retried.');
    $assert(connect_runtime_store()['outbox'][$ambig]['status']==='needs_review',
        'Ambiguous provider outcome lost.');
    $assert(connect_worker_step()['result']==='empty','Unsafe message was retried.');
    $assert(count(connect_runtime_store()['outbox'])===2,'Uncertain send produced duplicate jobs.');
    tenant_logout();
}catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($files as $path)@unlink($path);
if($errors){fwrite(STDERR,"FAIL B8.2 transport:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: signed Meta inbound/verification, WABA and tenant isolation, encrypted CRM, idempotent outbox, status reconciliation, STOP policy and uncertain-send safeguards.\n";
