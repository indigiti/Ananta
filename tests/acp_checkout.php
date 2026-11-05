<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('S',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=acp-staging-qa');
putenv('SANJAY_AGENT_SIGNING_KEY=acp-sign-qa-0123456789012345');
$_SERVER['SCRIPT_NAME']='/ananta/index.php';$_SERVER['HTTP_HOST']='qa.example.invalid';
$_SERVER['HTTPS']='on';$_SERVER['REMOTE_ADDR']='127.0.0.58';
$tag=bin2hex(random_bytes(7));
$paths=[
    'session'=>$root.'/storage/tmp/acp-session-'.$tag.'.json',
    'events'=>$root.'/storage/tmp/acp-events-'.$tag.'.json',
];
putenv('ANANTA_TEST_ACP_SESSION_FILE='.$paths['session']);
putenv('ANANTA_TEST_ACP_EVENTS_FILE='.$paths['events']);
putenv('ANANTA_ACP_API_KEY='.str_repeat('K',40));
putenv('ANANTA_ACP_HMAC_SECRET='.str_repeat('H',40));
require $root.'/src/bootstrap.php';
$errors=[];$pass=static function(bool $ok,string $why)use(&$errors){if(!$ok)$errors[]=$why;};
$deny=static function(callable $fn,string $why)use(&$errors){
    try{$fn();$errors[]=$why;}catch(RuntimeException|InvalidArgumentException){}
};
$signed=static function(string $raw,string $idem='idem_secure_valid_0123456'):array{
    return ['authorization'=>'Bearer '.str_repeat('K',40),
        'api-version'=>'2026-04-17','timestamp'=>gmdate('Y-m-d\TH:i:s\Z'),
        'signature'=>base64_encode(hash_hmac('sha256',$raw,str_repeat('H',40),true)),
        'idempotency-key'=>$idem,'request-id'=>'req_2026_abc_xyz'];
};
try{
    $body=['currency'=>'inr','capabilities'=>new stdClass(),
        'line_items'=>[['id'=>'NONEXISTENT-PLANT-XYZ','quantity'=>2]]];
    $raw=json_encode($body,JSON_THROW_ON_ERROR);
    $h=$signed($raw);
    $deny(static fn()=>acp_api_authorize($h,'POST',$raw),
        'ACP API accepted request while staging kill switch off.');
    putenv('ANANTA_ACP_ENABLE_STAGING_API=1');
    acp_api_authorize($h,'POST',$raw);
    $bad=$h;$bad['authorization']='Bearer wrong';
    $deny(static fn()=>acp_api_authorize($bad,'POST',$raw),
        'ACP API accepted wrong bearer token.');
    $bad=$h;$bad['signature']='forged';
    $deny(static fn()=>acp_api_authorize($bad,'POST',$raw),
        'ACP API accepted forged body HMAC.');
    $bad=$h;$bad['timestamp']='2025-01-01T00:00:00Z';
    $deny(static fn()=>acp_api_authorize($bad,'POST',$raw),
        'ACP API accepted replayed stale Timestamp.');
    $bad=$h;$bad['api-version']='2025-09-12';
    $deny(static fn()=>acp_api_authorize($bad,'POST',$raw),
        'ACP API accepted old incompatible protocol version.');
    $result=acp_http_execute('POST','/checkout_sessions',$raw,$h);
    $response=$result['response'];$session=$response['id'];
    $pass($result['http']===201,'ACP create must respond 201.');
    $pass(tenant_valid_id($session),'ACP checkout not identified by UUIDv7.');
    $pass($response['status']==='not_ready_for_payment','Unavailable item marked ready for payment.');
    $pass($response['currency']==='inr'&&$response['capabilities']['payment']['handlers']===[],
        'Staging checkout incorrectly advertises PSP/payment methods.');
    foreach(['line_items','totals','fulfillment_options','messages','links','capabilities'] as $key)
        $pass(array_key_exists($key,$response),'ACP required response field missing: '.$key);
    $same=acp_http_execute('POST','/checkout_sessions',$raw,$h);
    $pass($same['response']['id']===$session,'Repeated idempotent create generated a second session.');
    $pass(count(acp_sessions_store()['sessions'])===1,'ACP idempotency created duplicate records.');
    $differentRaw=json_encode(['currency'=>'inr','line_items'=>[
        ['id'=>'DIFFERENT-SKU','quantity'=>1]]],JSON_THROW_ON_ERROR);
    $deny(static fn()=>acp_http_execute('POST','/checkout_sessions',
        $differentRaw,$signed($differentRaw)), 'Idempotency payload conflict not detected.');
    $update=['fulfillment_details'=>['address'=>['line_one'=>'Rose Garden',
        'city'=>'Pune','state'=>'Maharashtra','country'=>'IN','postal_code'=>'411001']]];
    $updateRaw=json_encode($update,JSON_THROW_ON_ERROR);
    $u=acp_http_execute('POST','/checkout_sessions/'.$session,
        $updateRaw,$signed($updateRaw,'acp_update_request_2026'));
    $pass($u['response']['fulfillment_details']['address']['postal_code']==='411001',
        'Fulfillment address not persisted.');
    $view=acp_http_execute('GET','/checkout_sessions/'.$session,'',$signed('', 'acp_get_request_2026'));
    $pass($view['response']['id']===$session,'Authenticated checkout GET failed.');
    $pass($view['response']['status']==='not_ready_for_payment','Unknown SKU went ready after address.');
    $pd=['handler_id'=>'ananta-stripe-test-card','instrument'=>['type'=>'card',
        'credential'=>['type'=>'spt','token'=>'spt_1234567890123456']]];
    $pass(acp_psp_validate_payment_data($pd)['handler_id']==='ananta-stripe-test-card',
        'ACP SPT shape not recognized.');
    $deny(static fn()=>acp_psp_validate_payment_data(['handler_id'=>'ananta-stripe-test-card',
        'instrument'=>['type'=>'card','credential'=>['type'=>'spt',
        'token'=>'spt_1234567890123456','card_number'=>'4111111111111111']]]),
        'Raw card details accepted.');
    $deny(static fn()=>acp_session_complete($session,['payment_data'=>$pd],'acp_complete_attempt'),
        'Uncertified ACP completion created an order.');
    $pass(acp_psp_readiness()['live_capture_enabled']===false,
        'ACP live card capture switched on in staging.');
    $pass(acp_checkout_live_enabled()===false,'ACP hard go-live gate missing.');
    $pass(!str_contains((string)file_get_contents($paths['session']),'spt_1234567890'),
        'SPT credential stored to encrypted checkout ledger.');
    $cancelRaw='{}';
    $cancel=acp_http_execute('POST','/checkout_sessions/'.$session.'/cancel',
        $cancelRaw,$signed($cancelRaw,'acp_cancel_request_2026'));
    $pass($cancel['response']['status']==='canceled','ACP cancel not persisted.');
    $deny(static fn()=>acp_session_update($session,['buyer'=>['email'=>'test@example.in']],
        'acp_update_after_cancel'),'Closed ACP checkout was mutable.');
    $pass(count(commerce_orders())===0,'ACP staging flow wrote a binding order.');
    $deny(static fn()=>acp_order_event_enqueue($session,'order_created',
        ['id'=>uuidv7(),'permalink_url'=>'https://example.in/order/1']),
        'Unsettled ACP checkout emitted a signed order event.');
    $pass(acp_events_worker_step()['state']==='disabled','Event webhooks active by default.');
    putenv('ANANTA_ACP_EVENT_WEBHOOK_URL=https://127.0.0.1/api/event');
    putenv('ANANTA_ACP_EVENT_WEBHOOK_ALLOWED_HOST=127.0.0.1');
    $deny(static fn()=>acp_webhook_destination(),'SSRF/private IP webhook destination accepted.');
    $pass(count(acp_sessions_store()['sessions'])===1,'Unexpected ACP state mutation.');
}catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($paths as $p)@unlink($p);
if($errors){fwrite(STDERR,"FAIL ACP.2-ACP.3:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS ACP.2-3: signed authentication, version, timestamp, idempotency, enriched session, shipping, cancel and fail-closed payment/order event integration.\n";
