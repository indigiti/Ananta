<?php
declare(strict_types=1);

/**
 * ACP.2 checkout-session API for Ananta staging.
 * Implements protocol 2026-04-17 create/get/update/cancel plus a fail-closed
 * complete endpoint. Prices, discounts, tax and shipping are NEVER agent-supplied.
 * No order is issued without a future SQL reservation and certified PSP.
 */
function acp_sessions_file(): string {
    $test=trim((string)getenv('ANANTA_TEST_ACP_SESSION_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$test!=='')return $test;
    return data_root().'/private-domain/acp/checkout-sessions.json';
}
function acp_sessions_store(): array {
    return array_replace(['version'=>1,'sessions'=>[],'idempotency'=>[]],
        confidential_json_read(acp_sessions_file(),'ananta:acp:sessions:v1',[]));
}
function acp_sessions_mutate(callable $cb,string $reason): array {
    if(confidential_key()===null)throw new RuntimeException('ACP encrypted storage unavailable.');
    return confidential_json_mutate(acp_sessions_file(),'ananta:acp:sessions:v1',
        static fn(array $d)=>$cb(array_replace(['version'=>1,'sessions'=>[],'idempotency'=>[]],$d)),
        'acp.'.$reason);
}
function acp_api_configured(): bool {
    return strlen(trim((string)getenv('ANANTA_ACP_API_KEY')))>=32
        &&strlen(trim((string)getenv('ANANTA_ACP_HMAC_SECRET')))>=32;
}
function acp_api_authorize(array $headers,string $method,string $raw): void {
    if((string)getenv('ANANTA_ACP_ENABLE_STAGING_API')!=='1'||!acp_api_configured())
        throw new RuntimeException('ACP staging API is not activated.');
    if(($GLOBALS['app']['environment']??'')!=='staging')
        throw new RuntimeException('File-first ACP API cannot be used in production.');
    $key=(string)getenv('ANANTA_ACP_API_KEY');
    $auth=(string)($headers['authorization']??'');
    if(!str_starts_with($auth,'Bearer ')||!hash_equals($key,substr($auth,7)))
        throw new InvalidArgumentException('ACP authorization rejected.');
    $api=(string)($headers['api-version']??'');
    if($api!==acp_version())throw new InvalidArgumentException('Unsupported ACP API-Version.');
    $timestamp=(string)($headers['timestamp']??'');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D',$timestamp))
        throw new InvalidArgumentException('Invalid ACP Timestamp.');
    $at=strtotime($timestamp);
    if($at===false||abs(time()-$at)>300)throw new InvalidArgumentException('Stale ACP request.');
    $secret=(string)getenv('ANANTA_ACP_HMAC_SECRET');
    // Merchant-specific integration handshake may specify another algorithm;
    // until then require an explicit base64 HMAC-SHA256 of the raw request body.
    $expected=base64_encode(hash_hmac('sha256',$raw,$secret,true));
    if(!hash_equals($expected,(string)($headers['signature']??'')))
        throw new InvalidArgumentException('Invalid ACP body signature.');
    if($method!=='GET'){
        $idem=(string)($headers['idempotency-key']??'');
        if(!preg_match('/^[A-Za-z0-9._:-]{12,128}$/D',$idem))
            throw new InvalidArgumentException('Idempotency-Key must be a stable 12–128 character token.');
    }
}
function acp_idempotency_hash(string $operation,string $key): string {
    return hash('sha256',$operation.'|'.$key);
}
function acp_idempotent(array &$d,string $operation,string $key,array $request,callable $execute): array {
    $hash=acp_idempotency_hash($operation,$key);
    $body=hash('sha256',json_encode($request,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    $prior=$d['idempotency'][$hash]??null;
    if($prior!==null){
        if(!hash_equals((string)$prior['request_hash'],$body))
            throw new InvalidArgumentException('Idempotency key reused with different request.');
        return $prior['response'];
    }
    if(count($d['idempotency'])>=5000)
        throw new RuntimeException('ACP idempotency pilot ledger capacity reached.');
    $response=$execute($d);
    $d['idempotency'][$hash]=['request_hash'=>$body,'response'=>$response,'created_at'=>gmdate('c')];
    return $response;
}
function acp_items_validate(array $body): array {
    $lines=$body['line_items']??null;
    if(!is_array($lines)||!array_is_list($lines)||count($lines)<1||count($lines)>20)
        throw new InvalidArgumentException('ACP line_items must contain 1–20 items.');
    $items=[];
    foreach($lines as $item){
        if(!is_array($item))throw new InvalidArgumentException('Invalid line item.');
        $id=(string)($item['id']??$item['item']['id']??'');
        if(!preg_match('/^[A-Za-z0-9_.:-]{1,100}$/D',$id)||isset($items[$id]))
            throw new InvalidArgumentException('Invalid or duplicate ACP item ID.');
        $quantity=$item['quantity']??1;
        if(!is_int($quantity)||$quantity<1||$quantity>100)
            throw new InvalidArgumentException('Item quantity outside allowed bounds.');
        $items[$id]=$quantity;
    }
    if(($body['currency']??'inr')!=='inr')
        throw new InvalidArgumentException('ACP checkout supports INR only.');
    return $items;
}
function acp_fulfillment_validate(mixed $data): ?array {
    if($data===null)return null;
    if(!is_array($data))throw new InvalidArgumentException('Invalid fulfillment details.');
    $address=$data['address']??null;
    if(!is_array($address))throw new InvalidArgumentException('Shipping address required.');
    $country=strtoupper((string)($address['country']??''));
    $pin=(string)($address['postal_code']??'');
    if($country!=='IN'||!preg_match('/^[0-9]{6}$/D',$pin))
        throw new InvalidArgumentException('ACP staging supports Indian six-digit PIN codes only.');
    foreach(['line_one','city','state'] as $key){
        $v=trim((string)($address[$key]??''));
        if($v===''||text_len($v)>180)
            throw new InvalidArgumentException('Invalid fulfillment address field: '.$key);
        $address[$key]=$v;
    }
    $address['country']='IN';$address['postal_code']=$pin;
    // Customer PII stays encrypted in the ACP session store. Never log it.
    return ['address'=>$address];
}
function acp_quote_items(array $items,?array $fulfillment): array {
    $lookup=[];
    foreach(discover_merchant_rows() as $row)$lookup[(string)$row['id']]=$row;
    $lines=[];$shippingRows=[];$messages=[];$subtotal=0;
    foreach($items as $sku=>$quantity){
        $row=$lookup[$sku]??null;
        if(!$row){
            $messages[]=['type'=>'error','code'=>'invalid','message'=>'Item unavailable for verified online purchase.','param'=>'$.line_items'];
            continue;
        }
        if(!preg_match('/^([1-9][0-9]*) INR$/D',(string)$row['price'],$m)){
            $messages[]=['type'=>'error','code'=>'invalid','message'=>'Item price cannot be verified.','param'=>'$.line_items'];
            continue;
        }
        $record=merchant_completion_sku_record($sku);
        if(!$record){
            $messages[]=['type'=>'error','code'=>'invalid','message'=>'Item missing from authoritative catalogue.','param'=>'$.line_items'];
            continue;
        }
        $v=$record['variant'];$p=$record['product'];
        $stock=(int)($v['stock']??$p['inventory']['stock']??0);
        $tracked=$v? !empty($v['track_inventory']):!empty($p['inventory']['track']);
        if(!$tracked||$quantity>$stock){
            $messages[]=['type'=>'error','code'=>'out_of_stock','message'=>'Insufficient verified stock for requested quantity.','param'=>'$.line_items'];
            continue;
        }
        $price=(int)$m[1];$lineTotal=$price*$quantity;
        if($lineTotal>10000000||$subtotal+$lineTotal>20000000)
            throw new InvalidArgumentException('ACP pilot cart amount cap exceeded.');
        $subtotal+=$lineTotal;
        $lines[]=['id'=>$sku,'item'=>['id'=>$sku,'name'=>(string)$row['title'],'unit_amount'=>$price*100],
            'quantity'=>$quantity,'totals'=>[
                ['type'=>'subtotal','display_text'=>'Line subtotal','amount'=>$lineTotal*100],
                ['type'=>'total','display_text'=>'Line total','amount'=>$lineTotal*100],
            ]];
        $shippingRows[]=['p'=>$p,'variant'=>$v,'unit'=>$price,'qty'=>$quantity];
    }
    $shipping=0;$options=[];$selected=[];$canShip=false;
    if($fulfillment!==null&&$lines!==[]){
        $pin=$fulfillment['address']['postal_code'];
        $quote=shipping_quote($pin,$subtotal,$shippingRows);
        if(!empty($quote['serviceable'])){
            $canShip=true;$shipping=(int)$quote['shipping'];
            $options[]=['type'=>'shipping','id'=>'ananta-shipping-standard',
                'title'=>'Nursery delivery','totals'=>[
                    ['type'=>'fulfillment','display_text'=>'Delivery','amount'=>$shipping*100],
                ]];
            $selected[]=['type'=>'shipping','shipping'=>['option_id'=>'ananta-shipping-standard',
                'item_ids'=>array_keys($items)]];
        }else{
            $messages[]=['type'=>'error','code'=>'invalid',
                'message'=>'Delivery is not verified for this postal code.','param'=>'$.fulfillment_details.address.postal_code'];
        }
    }
    // Financial truth is recomputed only from Ananta prices and shipping rules.
    // Existing Ananta "tax included" calculations aren't certified per-plant GST;
    // never fabricate a jurisdiction-specific tax invoice.
    $totals=[
        ['type'=>'items_base_amount','display_text'=>'Products','amount'=>$subtotal*100],
        ['type'=>'subtotal','display_text'=>'Products subtotal (tax treatment subject to confirmation)','amount'=>$subtotal*100],
        ['type'=>'fulfillment','display_text'=>'Estimated delivery','amount'=>$shipping*100],
        ['type'=>'total','display_text'=>'Estimated total','amount'=>($subtotal+$shipping)*100],
    ];
    if($fulfillment===null)$messages[]=['type'=>'error','code'=>'missing',
        'message'=>'A delivery address is required to quote shipping.','param'=>'$.fulfillment_details'];
    $messages[]=['type'=>'warning','code'=>'invalid',
        'message'=>'Agentic checkout is not yet enabled: PSP, taxes and atomic inventory reservations require certification.'];
    return ['line_items'=>$lines,'fulfillment_options'=>$options,
        'selected_fulfillment_options'=>$selected,'totals'=>$totals,
        'messages'=>$messages,'amount_minor'=>($subtotal+$shipping)*100,
        'is_serviceable'=>$canShip&&count($lines)===count($items)];
}
function acp_session_public(array $s): array {
    $quote=acp_quote_items($s['items'],$s['fulfillment_details']);
    $status=($s['status']==='canceled'||$s['status']==='completed')?$s['status']:
        (strtotime((string)$s['expires_at'])<time()?'expired':'not_ready_for_payment');
    $links=[];$policy=merchant_completion_policy();
    foreach(['returns_url'=>'refund_policy','shipping_url'=>'shipping_policy'] as $field=>$type){
        $u=(string)($policy[$field]??'');
        if(filter_var($u,FILTER_VALIDATE_URL))$links[]=['type'=>$type,'url'=>$u];
    }
    $response=['id'=>$s['id'],'protocol'=>['version'=>acp_version()],
        'status'=>$status,'currency'=>'inr','line_items'=>$quote['line_items'],
        'fulfillment_options'=>$quote['fulfillment_options'],
        'selected_fulfillment_options'=>$quote['selected_fulfillment_options'],
        'totals'=>$quote['totals'],'messages'=>$quote['messages'],'links'=>$links,
        'capabilities'=>acp_psp_capabilities(),
        'created_at'=>$s['created_at'],'updated_at'=>$s['updated_at'],'expires_at'=>$s['expires_at'],
    ];
    if($s['fulfillment_details']!==null)$response['fulfillment_details']=$s['fulfillment_details'];
    if($s['buyer']!==null)$response['buyer']=$s['buyer'];
    if($status==='canceled')$response['messages']=[['type'=>'info','message'=>'Checkout session canceled.']];
    return $response;
}
function acp_session_create(array $body,string $key): array {
    $items=acp_items_validate($body);
    $fulfillment=acp_fulfillment_validate($body['fulfillment_details']??null);
    $buyer=$body['buyer']??null;
    if($buyer!==null&&(!is_array($buyer)||strlen(json_encode($buyer))>5000))
        throw new InvalidArgumentException('Invalid buyer details.');
    $request=['items'=>$items,'fulfillment'=>$fulfillment,'buyer'=>$buyer];
    $answer=null;
    acp_sessions_mutate(static function(array $d)use($request,$key,&$answer){
        $answer=acp_idempotent($d,'create',$key,$request,
            static function(array &$d)use($request){
                if(count($d['sessions'])>=1000)throw new RuntimeException('ACP pilot session capacity reached.');
                $id=uuidv7();$now=gmdate('c');
                $record=['id'=>$id,'status'=>'not_ready_for_payment',
                    'buyer'=>$request['buyer'],'items'=>$request['items'],
                    'fulfillment_details'=>$request['fulfillment'],
                    'created_at'=>$now,'updated_at'=>$now,
                    'expires_at'=>gmdate('c',time()+1800),'revision'=>0];
                $d['sessions'][$id]=$record;
                return acp_session_public($record);
            });
        return $d;
    },'create');
    return $answer;
}
function acp_session_record(string $id): ?array {
    if(!tenant_valid_id($id))return null;
    return acp_sessions_store()['sessions'][$id]??null;
}
function acp_session_get(string $id): ?array {
    $s=acp_session_record($id);return $s?acp_session_public($s):null;
}
function acp_session_update(string $id,array $body,string $key): array {
    if(!tenant_valid_id($id))throw new RuntimeException('ACP checkout session not found.');
    $request=['body'=>$body];
    $answer=null;
    acp_sessions_mutate(static function(array $d)use($id,$body,$key,$request,&$answer){
        $answer=acp_idempotent($d,'update:'.$id,$key,$request,
            static function(array &$d)use($id,$body){
                $s=$d['sessions'][$id]??null;
                if(!$s)throw new RuntimeException('ACP checkout session not found.');
                if($s['status']!=='not_ready_for_payment'||strtotime($s['expires_at'])<time())
                    throw new RuntimeException('Checkout session is closed or expired.');
                if(array_key_exists('line_items',$body)) {
                    $s['items']=acp_items_validate(['line_items'=>$body['line_items'],'currency'=>'inr']);
                }
                if(array_key_exists('fulfillment_details',$body))
                    $s['fulfillment_details']=acp_fulfillment_validate($body['fulfillment_details']);
                if(array_key_exists('buyer',$body)){
                    if(!is_array($body['buyer'])||strlen(json_encode($body['buyer']))>5000)
                        throw new InvalidArgumentException('Invalid buyer update.');
                    $s['buyer']=$body['buyer'];
                }
                $s['revision']++;$s['updated_at']=gmdate('c');$d['sessions'][$id]=$s;
                return acp_session_public($s);
            });
        return $d;
    },'update');
    return $answer;
}
function acp_session_cancel(string $id,array $body,string $key): array {
    if(!tenant_valid_id($id))throw new RuntimeException('ACP checkout session not found.');
    $answer=null;
    acp_sessions_mutate(static function(array $d)use($id,$body,$key,&$answer){
        $answer=acp_idempotent($d,'cancel:'.$id,$key,$body,
            static function(array &$d)use($id){
                $s=$d['sessions'][$id]??null;
                if(!$s)throw new RuntimeException('ACP checkout session not found.');
                if($s['status']==='completed'||$s['status']==='canceled')
                    throw new RuntimeException('Checkout session is no longer cancelable.');
                $s['status']='canceled';$s['updated_at']=gmdate('c');$s['revision']++;
                $d['sessions'][$id]=$s;return acp_session_public($s);
            });
        return $d;
    },'cancel');
    return $answer;
}
function acp_session_complete(string $id,array $body,string $key): array {
    $s=acp_session_record($id);
    if(!$s)throw new RuntimeException('ACP checkout session not found.');
    if(!is_array($body['payment_data']??null))
        throw new InvalidArgumentException('ACP completion requires payment_data.');
    // No payment token or card fields should be stored, logged or passed to
    // the sandbox payment webhook. This stage NEVER issues an order.
    acp_psp_validate_payment_data($body['payment_data']);
    if(!acp_checkout_live_enabled())
        throw new RuntimeException('ACP live completion unavailable: PSP certification, GST, buyer authorization and transactional inventory reservations are pending.');
    throw new RuntimeException('ACP fulfillment completion has not been certified.');
}
