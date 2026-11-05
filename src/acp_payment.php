<?php
declare(strict_types=1);

/**
 * ACP.3: concrete Stripe Shared Payment Token TEST adapter, merchant event
 * outbox and immutable go-live gates. No live payment can be captured from this
 * file-first package; SQL atomic reservations + PSP certification required.
 */
function acp_psp_capabilities(): array {
    return ['payment'=>['handlers'=>[]], 'interventions'=>['supported'=>[]]];
}
function acp_psp_readiness(): array {
    $flags=[
        'openai_partner_approved'=>false,
        'india_market_onboarded'=>false,
        'atomic_sql_inventory_reservation'=>false,
        'merchant_gst_approved'=>false,
        'certified_live_psp'=>false,
        'signed_order_event_reconciliation'=>false,
    ];
    return ['go_live_allowed'=>false,'gates'=>$flags,
        'sandbox_stripe_adapter_available'=>true,
        'sandbox_adapter_enabled'=>(string)getenv('ANANTA_ACP_STRIPE_TEST_ONLY')==='1',
        'live_capture_enabled'=>false,'pci_card_data_accepted'=>false];
}
function acp_psp_validate_payment_data(array $data): array {
    $handler=(string)($data['handler_id']??'');
    $instrument=$data['instrument']??null;
    if($handler!=='ananta-stripe-test-card'||!is_array($instrument)
        ||($instrument['type']??'')!=='card')
        throw new InvalidArgumentException('ACP payment handler not recognized.');
    $credential=$instrument['credential']??null;
    if(!is_array($credential)||($credential['type']??'')!=='spt'
        ||!preg_match('/^spt_[A-Za-z0-9_-]{8,220}$/D',(string)($credential['token']??'')))
        throw new InvalidArgumentException('Expected a provider-issued Stripe Shared Payment Token.');
    // Raw card data is NEVER accepted or written to logs/disk.
    foreach(['card_number','cvc','pan','cvv','exp_month','exp_year'] as $sensitive)
        if(isset($data[$sensitive])||isset($instrument[$sensitive])||isset($credential[$sensitive]))
            throw new InvalidArgumentException('Raw card credentials are forbidden.');
    return ['handler_id'=>$handler,'token'=>(string)$credential['token']];
}
function acp_stripe_test_authorize(array $session,array $paymentData,?callable $transport=null): array {
    if((string)getenv('ANANTA_ACP_STRIPE_TEST_ONLY')!=='1')
        throw new RuntimeException('Stripe test adapter disabled.');
    $key=trim((string)getenv('ANANTA_ACP_STRIPE_TEST_SECRET'));
    if(!str_starts_with($key,'sk_test_')||strlen($key)<25)
        throw new RuntimeException('Only a Stripe TEST secret is accepted.');
    if(($session['status']??'')!=='not_ready_for_payment'
        ||strtotime((string)($session['expires_at']??''))<time())
        throw new RuntimeException('Invalid or expired ACP test session.');
    $credential=acp_psp_validate_payment_data($paymentData);
    $quote=acp_quote_items($session['items'],$session['fulfillment_details']);
    if(!$quote['is_serviceable']||$quote['amount_minor']<100)
        throw new RuntimeException('Provider test cannot proceed without deliverable verified items.');
    // This is an adapter CONFORMANCE TEST, not checkout completion.
    // Stripe's manual capture prevents an automatic final charge in staging.
    $payload=[
        'amount'=>(string)$quote['amount_minor'],'currency'=>'inr',
        'payment_method'=>$credential['token'],'confirm'=>'true','capture_method'=>'manual',
        'payment_method_types[0]'=>'card',
        'payment_method_options[card][request_delegated_payment]'=>'true',
        'metadata[acp_session_id]'=>$session['id'],
        'metadata[environment]'=>'ananta-acp-conformance',
    ];
    // Unique per session. No retry when network response is ambiguous.
    $idempotency='ananta-acp-test-'.$session['id'];
    if($transport){
        $r=$transport($payload,$idempotency);
    }else{
        if(!extension_loaded('curl'))throw new RuntimeException('Stripe test requires cURL.');
        $ch=curl_init('https://api.stripe.com/v1/payment_intents');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),
            CURLOPT_HTTPHEADER=>[
                'Authorization: Bearer '.$key,'Idempotency-Key: '.$idempotency,
                'Content-Type: application/x-www-form-urlencoded',
            ],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,
            CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($body===false||$code!==200||strlen($body)>32768)
            throw new RuntimeException('Stripe test response unconfirmed; reconcile in provider console.');
        $r=json_decode($body,true);
    }
    if(!is_array($r)||!preg_match('/^pi_[A-Za-z0-9]{8,100}$/D',(string)($r['id']??'')))
        throw new RuntimeException('Stripe test did not return a verified PaymentIntent ID.');
    if(($r['status']??'')!=='requires_capture')
        throw new RuntimeException('Stripe test intent is not in manual-capture authorization state.');
    // Don't persist token or PaymentIntent credentials, don't capture or issue
    // an order. Manual cancellation of the test intent remains PSP duty.
    return ['provider'=>'stripe','environment'=>'test',
        'intent_id'=>$r['id'],'authorization_state'=>'requires_capture',
        'captured'=>false,'order_created'=>false,
        'manual_test_intent_cancellation_required'=>true];
}
function acp_events_file(): string {
    $t=trim((string)getenv('ANANTA_TEST_ACP_EVENTS_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$t!=='')return $t;
    return data_root().'/private-domain/acp/order-events.json';
}
function acp_events_store(): array {
    return array_replace(['version'=>1,'events'=>[]],
        confidential_json_read(acp_events_file(),'ananta:acp:events:v1',[]));
}
function acp_events_mutate(callable $cb,string $reason): array {
    return confidential_json_mutate(acp_events_file(),'ananta:acp:events:v1',
        static fn(array $d)=>$cb(array_replace(['version'=>1,'events'=>[]],$d)),
        'acp.events.'.$reason);
}
function acp_webhook_destination(): string {
    $url=trim((string)getenv('ANANTA_ACP_EVENT_WEBHOOK_URL'));
    $allowed=trim((string)getenv('ANANTA_ACP_EVENT_WEBHOOK_ALLOWED_HOST'));
    $host=strtolower((string)parse_url($url,PHP_URL_HOST));
    if($allowed===''||$host===''||$host!==strtolower($allowed)
        ||!filter_var($url,FILTER_VALIDATE_URL)
        ||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https'
        ||parse_url($url,PHP_URL_PORT)!==null||parse_url($url,PHP_URL_USER)!==null
        ||filter_var($host,FILTER_VALIDATE_IP)||$host==='localhost')
        throw new RuntimeException('ACP order event endpoint is not an allowlisted partner HTTPS host.');
    return $url;
}
function acp_order_event_enqueue(string $checkoutId,string $type,array $order): string {
    if(PHP_SAPI!=='cli')throw new RuntimeException('Order event projection requires protected worker.');
    if(!in_array($type,['order_created','order_updated'],true)||!tenant_valid_id($checkoutId))
        throw new InvalidArgumentException('Invalid ACP order event.');
    $session=acp_session_record($checkoutId);
    if(!$session||($session['status']??'')!=='completed'||empty($session['order_id'])
        ||($order['id']??'')!==$session['order_id'])
        throw new RuntimeException('Only a settled, completed ACP checkout can publish order events.');
    $hash=hash('sha256',$checkoutId.'|'.$type.'|'.json_encode($order,JSON_THROW_ON_ERROR));
    $id='';
    acp_events_mutate(static function(array $d)use($checkoutId,$type,$order,$hash,&$id){
        foreach($d['events'] as $e)if($e['fingerprint']===$hash){$id=$e['id'];return $d;}
        if(count($d['events'])>=5000)throw new RuntimeException('Order event outbox capacity reached.');
        $id=uuidv7();$d['events'][$id]=[
            'id'=>$id,'checkout_id'=>$checkoutId,'type'=>$type,
            'fingerprint'=>$hash,'payload'=>['type'=>$type,'data'=>[
                'type'=>'order','checkout_session_id'=>$checkoutId,
                'permalink_url'=>(string)($order['permalink_url']??''),
                'order'=>$order,
            ]],
            'status'=>'queued','attempts'=>0,'retry_at'=>time(),'updated_at'=>gmdate('c'),
        ];
        return $d;
    },'enqueue');
    return $id;
}
function acp_events_worker_step(?callable $transport=null): array {
    if(PHP_SAPI!=='cli')throw new RuntimeException('ACP event worker is CLI only.');
    if((string)getenv('ANANTA_ACP_EVENT_WEBHOOK_ENABLE')!=='1')return ['state'=>'disabled'];
    $url=acp_webhook_destination();
    $secret=trim((string)getenv('ANANTA_ACP_EVENT_WEBHOOK_SECRET'));
    if(strlen($secret)<32)throw new RuntimeException('ACP webhook signing secret required.');
    $picked=null;
    acp_events_mutate(static function(array $d)use(&$picked){
        foreach($d['events'] as &$e){
            if($e['status']==='sending'&&($e['lease_until']??0)<time())
                $e['status']='needs_review';
            if($picked===null&&$e['status']==='queued'&&($e['retry_at']??0)<=time()){
                $e['status']='sending';$e['lease_until']=time()+60;$e['attempts']++;
                $picked=$e;
            }
        }unset($e);
        return $d;
    },'claim');
    if(!$picked)return ['state'=>'empty'];
    $json=json_encode($picked['payload'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    $signature=base64_encode(hash_hmac('sha256',$json,$secret,true));
    try{
        if($transport)$http=(int)$transport($url,$json,$signature,$picked['id']);
        else{
            if(!extension_loaded('curl'))throw new RuntimeException('cURL required.');
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json,
                CURLOPT_HTTPHEADER=>[
                    'Content-Type: application/json','Signature: '.$signature,
                    'Idempotency-Key: '.$picked['id']],
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_FOLLOWLOCATION=>false]);
            $body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($body===false)$http=0;
        }
    }catch(Throwable){$http=0;}
    $state=$http>=200&&$http<300?'delivered':
        ($http===400||$http===401||$http===403?'rejected':'needs_review');
    acp_events_mutate(static function(array $d)use($picked,$state){
        $e=$d['events'][$picked['id']]??null;
        if($e&&$e['status']==='sending'){
            $e['status']=$state;$e['http_status_redacted']=$state;
            $e['updated_at']=gmdate('c');unset($e['lease_until']);
            $d['events'][$picked['id']]=$e;
        }
        return $d;
    },'finish');
    return ['state'=>$state,'id'=>$picked['id']];
}
function acp_readiness_report(): array {
    return ['feed'=>acp_feed_report(),'checkout'=>[
        'spec_version'=>acp_version(),
        'staging_api_configured'=>acp_api_configured(),
        'staging_api_enabled'=>(string)getenv('ANANTA_ACP_ENABLE_STAGING_API')==='1',
        'sessions'=>count(acp_sessions_store()['sessions']),
        'live_completion_enabled'=>acp_checkout_live_enabled(),
    ],'payments'=>acp_psp_readiness(),
        'order_webhooks'=>[
            'queued'=>count(array_filter(acp_events_store()['events'],
                static fn($e)=>($e['status']??'')==='queued')),
            'live_events_sent_by_app'=>false],
        'independent_partner_conformance_verified'=>false];
}
