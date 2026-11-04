<?php
declare(strict_types=1);

/**
 * B8.2 official Meta Cloud API transport, signed inbound webhooks, encrypted
 * per-tenant CRM and a lease-based outbound queue. No unofficial transports.
 *
 * Meta does not promise exactly-once sending: an expired in-flight lease
 * becomes needs_review instead of being sent a second time.
 */
function connect_runtime_file(): string {
    $test=trim((string)getenv('ANANTA_TEST_CONNECT_RUNTIME_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$test!=='')return $test;
    return data_root().'/private-domain/connect/messages.json';
}
function connect_runtime_defaults(): array {
    return ['schema_version'=>1,'conversations'=>[],'messages'=>[],'outbox'=>[],
        'provider_index'=>[],'webhook_dedupe'=>[],'delivery_events'=>[],
        'blocked_contacts'=>[],'orphan_receipts'=>[]];
}
function connect_runtime_store(): array {
    return array_replace(connect_runtime_defaults(),
        confidential_json_read(connect_runtime_file(),'ananta:connect:runtime:v1',[]));
}
function connect_runtime_mutate(callable $action,string $reason): array {
    if(confidential_key()===null)throw new RuntimeException('Encrypted messaging storage is not configured.');
    return confidential_json_mutate(connect_runtime_file(),'ananta:connect:runtime:v1',
        static fn(array $d)=>$action(array_replace(connect_runtime_defaults(),$d)), 'connect.runtime.'.$reason);
}
function connect_account_by_phone_id(string $phoneId): ?array {
    if(!preg_match('/^[0-9]{6,30}$/D',$phoneId))return null;
    foreach(connect_store()['accounts'] as $account){
        if(($account['phone_number_id']??'')===$phoneId)return $account;
    }
    return null;
}
function connect_account_secrets(array $account): array {
    return [
        'token'=>(string)(getenv((string)($account['token_env']??''))?:''),
        'app_secret'=>(string)(getenv((string)($account['secret_env']??''))?:''),
        'verify'=>(string)(getenv((string)($account['verify_env']??''))?:''),
    ];
}
/** The incoming HMAC is always checked against the exact RAW request body. */
function connect_webhook_verify(string $phoneId,string $mode,string $token,string $challenge): ?string {
    $account=connect_account_by_phone_id($phoneId);
    if(!$account||$mode!=='subscribe'||strlen($challenge)>256)return null;
    $saved=connect_account_secrets($account)['verify'];
    if(strlen($saved)<20||!hash_equals($saved,$token))return null;
    return $challenge;
}
function connect_conversation_key(string $tenant,string $phone): string {
    return hash('sha256',$tenant.'|'.connect_normalize_phone($phone));
}
function connect_runtime_conversation(array &$data,string $tenant,string $phone,string $when): string {
    $phone=connect_normalize_phone($phone);
    $key=connect_conversation_key($tenant,$phone);
    if(!isset($data['conversations'][$key])){
        if(count($data['conversations'])>=2000)throw new RuntimeException('CRM pilot conversation limit reached.');
        $data['conversations'][$key]=['id'=>uuidv7(),'tenant_id'=>$tenant,'phone'=>$phone,
            'created_at'=>$when,'updated_at'=>$when,'last_message_at'=>$when,'unread'=>0];
    }
    return $key;
}
function connect_runtime_append_message(array &$data,string $tenant,string $phone,string $direction,
    string $type,string $body,string $time,?string $providerId=null,?string $jobId=null): string {
    if(count($data['messages'])>=12000)throw new RuntimeException('CRM pilot message limit reached.');
    $key=connect_runtime_conversation($data,$tenant,$phone,$time);
    $id=uuidv7();
    $data['messages'][$id]=['id'=>$id,'tenant_id'=>$tenant,'conversation_key'=>$key,
        'phone'=>$phone,'direction'=>$direction,'type'=>$type,'body'=>$body,
        'provider_id'=>$providerId,'outbox_id'=>$jobId,'status'=>$direction==='inbound'?'received':'queued',
        'created_at'=>$time,'updated_at'=>$time];
    $conversation=&$data['conversations'][$key];
    $conversation['last_message_at']=$time;$conversation['updated_at']=$time;
    if($direction==='inbound')$conversation['unread']=min(999,(int)($conversation['unread']??0)+1);
    return $id;
}
function connect_webhook_payload(string $phoneId,string $raw,string $signature): array {
    $account=connect_account_by_phone_id($phoneId);
    if(!$account)throw new InvalidArgumentException('Unknown webhook sender.');
    if(strlen($raw)<2||strlen($raw)>262144)throw new InvalidArgumentException('Webhook body limit exceeded.');
    $secret=connect_account_secrets($account)['app_secret'];
    if(!connect_verify_meta_signature($raw,$signature,$secret))
        throw new InvalidArgumentException('Webhook signature rejected.');
    if(empty($account['enabled']))throw new RuntimeException('Signed webhook receiver not activated.');
    try{$payload=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}
    catch(JsonException){throw new InvalidArgumentException('Invalid webhook JSON.');}
    if(!is_array($payload)||($payload['object']??'')!=='whatsapp_business_account')
        throw new InvalidArgumentException('Unexpected webhook object.');
    $events=[];$entries=$payload['entry']??null;
    if(!is_array($entries)||count($entries)>10)throw new InvalidArgumentException('Invalid webhook entry collection.');
    foreach($entries as $entry){
        if(!is_array($entry)||($entry['id']??'')!==(string)$account['waba_id'])
            throw new InvalidArgumentException('Webhook WhatsApp Business Account mismatch.');
        if(!is_array($entry)||!is_array($entry['changes']??null)||count($entry['changes'])>15)
            throw new InvalidArgumentException('Malformed webhook changes.');
        foreach($entry['changes'] as $change){
            if(!is_array($change)||($change['field']??'')!=='messages')continue;
            $value=$change['value']??null;
            if(!is_array($value)||($value['metadata']['phone_number_id']??null)!==$phoneId)
                throw new InvalidArgumentException('Webhook phone number mismatch.');
            $received=$value['messages']??[];
            $statuses=$value['statuses']??[];
            if(!is_array($received)||!is_array($statuses)||count($received)+count($statuses)>200)
                throw new InvalidArgumentException('Webhook event limit exceeded.');
            foreach($received as $m) {
                if(!is_array($m))continue;
                $id=(string)($m['id']??'');$from=(string)($m['from']??'');
                if(!preg_match('/^[a-zA-Z0-9._:-]{5,200}$/D',$id))continue;
                try{$phone=connect_normalize_phone($from);}catch(Throwable){continue;}
                $type=(string)($m['type']??'unsupported');
                if(!in_array($type,['text','image','document','audio','video','sticker','location','interactive','button','reaction','unsupported'],true))$type='unsupported';
                $body=$type==='text'?(string)($m['text']['body']??''):'['.$type.' message]';
                $body=function_exists('mb_substr')?mb_substr($body,0,2500,'UTF-8'):substr($body,0,2500);
                $time=(int)($m['timestamp']??time());
                if($time<1546300800||$time>time()+86400)$time=time();
                $events[]=['kind'=>'inbound','id'=>$id,'from'=>$phone,'type'=>$type,
                    'body'=>$body,'at'=>gmdate('c',$time)];
            }
            foreach($statuses as $s){
                if(!is_array($s))continue;
                $id=(string)($s['id']??'');$status=(string)($s['status']??'');
                if(!preg_match('/^[a-zA-Z0-9._:-]{5,200}$/D',$id)||
                   !in_array($status,['sent','delivered','read','failed'],true))continue;
                $time=(int)($s['timestamp']??time());
                if($time<1546300800||$time>time()+86400)$time=time();
                $phone=(string)($s['recipient_id']??'');
                try{$phone=connect_normalize_phone($phone);}catch(Throwable){$phone='';}
                $ref=(string)($s['biz_opaque_callback_data']??'');
                $events[]=['kind'=>'status','id'=>$id,'status'=>$status,'recipient'=>$phone,
                    'reference'=>tenant_valid_id($ref)?$ref:'','at'=>gmdate('c',$time)];
            }
        }
    }
    if(count($events)>250)throw new InvalidArgumentException('Too many webhook events.');
    $tenant=(string)$account['tenant_id'];
    $result=['new_inbound'=>0,'receipts'=>0,'duplicates'=>0,'unknown_receipts'=>0];
    connect_runtime_mutate(static function(array $data)use($events,$tenant,&$result){
        foreach($events as $event){
            if($event['kind']==='inbound'){
                $key='in:'.$tenant.':'.$event['id'];
                if(isset($data['webhook_dedupe'][$key])){$result['duplicates']++;continue;}
                if(count($data['webhook_dedupe'])>=30000)throw new RuntimeException('Webhook ledger retention cap reached.');
                $data['webhook_dedupe'][$key]=$event['at'];
                connect_runtime_append_message($data,$tenant,$event['from'],'inbound',$event['type'],
                    $event['body'],$event['at'],$event['id']);
                // STOP/UNSUBSCRIBE messages suppress all future outbound categories.
                if($event['type']==='text'&&preg_match('/^\s*(STOP|UNSUBSCRIBE|CANCEL|OPT\s*OUT)\s*[.!]?\s*$/i',$event['body'])){
                    $data['blocked_contacts'][connect_conversation_key($tenant,$event['from'])]=[
                        'tenant_id'=>$tenant,'at'=>$event['at'],'reason'=>'customer_opt_out',
                    ];
                }
                $result['new_inbound']++;
                continue;
            }
            $key='status:'.$tenant.':'.$event['id'].':'.$event['status'].':'.$event['at'];
            if(isset($data['webhook_dedupe'][$key])){$result['duplicates']++;continue;}
            if(count($data['webhook_dedupe'])>=30000)throw new RuntimeException('Webhook ledger retention cap reached.');
            $data['webhook_dedupe'][$key]=$event['at'];
            $jobId=$data['provider_index'][$event['id']]??null;
            if(!$jobId&&$event['reference']!==''&&isset($data['outbox'][$event['reference']])){
                $candidate=$data['outbox'][$event['reference']];
                if(($candidate['tenant_id']??'')===$tenant&&
                   ($event['recipient']===''||$candidate['phone']===$event['recipient'])){
                    $jobId=$event['reference'];$data['provider_index'][$event['id']]=$jobId;
                }
            }
            if(!$jobId||!isset($data['outbox'][$jobId])||
                ($data['outbox'][$jobId]['tenant_id']??'')!==$tenant||
                ($event['recipient']!==''&&$data['outbox'][$jobId]['phone']!==$event['recipient'])){
                $result['unknown_receipts']++;
                if(count($data['orphan_receipts'])<1000){
                    $data['orphan_receipts'][]=['tenant_id'=>$tenant,'provider_id'=>$event['id'],
                        'status'=>$event['status'],'recipient'=>$event['recipient'],
                        'reference'=>$event['reference'],'at'=>$event['at']];
                }
                continue;
            }
            $job=&$data['outbox'][$jobId];
            $rank=['queued'=>0,'leased'=>0,'needs_review'=>0,'submitted'=>1,'sent'=>2,'delivered'=>3,'read'=>4,'failed'=>2];
            $prior=(string)$job['status'];
            // Older delayed delivery reports must never downgrade read/delivered.
            $eligible=($event['status']==='failed'&&!in_array($prior,['delivered','read'],true))
                ||($event['status']!=='failed'&&($rank[$event['status']]??0)>($rank[$prior]??0));
            if($eligible){$job['status']=$event['status'];$job['updated_at']=$event['at'];}
            $job['provider_id']=$event['id'];
            $messageId=$job['message_id'];
            if(isset($data['messages'][$messageId])){
                $data['messages'][$messageId]['provider_id']=$event['id'];
                if($eligible)$data['messages'][$messageId]['status']=$event['status'];
                $data['messages'][$messageId]['updated_at']=$event['at'];
            }
            if(count($data['delivery_events'])>=30000)throw new RuntimeException('Delivery-event retention cap reached.');
            $data['delivery_events'][]=['id'=>uuidv7(),'tenant_id'=>$tenant,
                'job_id'=>$jobId,'provider_status'=>$event['status'],'at'=>$event['at']];
            $result['receipts']++;
            unset($job);
        }
        return $data;
    },'webhook.accept');
    return $result;
}
/** Never grant approval implicitly from an incoming message. */
function connect_template_allowed(string $name,string $language): bool {
    if(!preg_match('/^[a-z][a-z0-9_]{1,80}$/D',$name)||
       !preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/D',$language))return false;
    $list=explode(',',(string)getenv('ANANTA_WA_APPROVED_TEMPLATES'));
    foreach($list as $entry)if(trim($entry)===$name.':'.$language)return true;
    return false;
}
function connect_enqueue(string $tenant,string $phone,string $purpose,string $template,string $lang,string $unique): string {
    if($tenant==='platform')require_admin();
    else tenant_require('connect.send',$tenant);
    $phone=connect_normalize_phone($phone);
    if(!in_array($purpose,['service','rfq_updates','marketing'],true))throw new InvalidArgumentException('Invalid send purpose.');
    if(!preg_match('/^[a-zA-Z0-9:_-]{8,120}$/D',$unique))throw new InvalidArgumentException('Provide a stable unique event reference.');
    if(!connect_template_allowed($template,$lang))throw new RuntimeException('Template not in the explicitly approved allowlist.');
    if(!connect_message_allowed($tenant,$phone,$purpose))throw new RuntimeException('Sender inactive or contact consent missing.');
    $id='';
    $key=hash('sha256',$tenant.'|'.$unique);
    connect_runtime_mutate(static function(array $data)use($tenant,$phone,$purpose,$template,$lang,$key,&$id){
        foreach($data['outbox'] as $job){
            if($job['idempotency_hash']===$key){
                if($job['tenant_id']!==$tenant||$job['phone']!==$phone||
                    $job['template']!==$template||$job['purpose']!==$purpose||$job['language']!==$lang)
                    throw new RuntimeException('Idempotency reference reused with different content.');
                $id=$job['id'];return $data;
            }
        }
        if(count($data['outbox'])>=3000)throw new RuntimeException('Outbound pilot queue limit reached.');
        $now=gmdate('c');$id=uuidv7();
        $messageId=connect_runtime_append_message($data,$tenant,$phone,'outbound','template',
            $template,$now,null,$id);
        $data['outbox'][$id]=['id'=>$id,'tenant_id'=>$tenant,'phone'=>$phone,'purpose'=>$purpose,
            'template'=>$template,'language'=>$lang,'idempotency_hash'=>$key,
            'message_id'=>$messageId,'status'=>'queued','attempts'=>0,'lease_until'=>0,
            'provider_id'=>null,'created_at'=>$now,'updated_at'=>$now,'next_attempt_at'=>time()];
        return $data;
    },'outbox.enqueue');
    audit_event('connect.queue.enqueued',['tenant_id'=>$tenant,'job_id'=>$id,'purpose'=>$purpose]);
    return $id;
}
/** Leases are committed BEFORE network I/O. Expired leases are NOT retried. */
function connect_worker_claim(): ?array {
    $picked=null;$now=time();
    connect_runtime_mutate(static function(array $data)use(&$picked,$now){
        foreach($data['outbox'] as &$job){
            if($job['status']==='leased'&&($job['lease_until']??0)<$now){
                $job['status']='needs_review';$job['updated_at']=gmdate('c',$now);
                $data['messages'][$job['message_id']]['status']='needs_review';
            }
        }
        unset($job);
        foreach($data['outbox'] as &$job){
            if($job['status']!=='queued'||($job['next_attempt_at']??0)>$now)continue;
            $job['status']='leased';$job['attempts']++;
            $job['lease_until']=$now+90;$job['updated_at']=gmdate('c',$now);
            $data['messages'][$job['message_id']]['status']='leased';
            $picked=$job;break;
        }
        unset($job);
        return $data;
    },'outbox.claim');
    return $picked;
}
function connect_transport_meta(array $job,array $account): array {
    $version=(string)(getenv('ANANTA_WA_GRAPH_VERSION')?:'v24.0');
    if(!preg_match('/^v[0-9]{2}\.[0-9]$/D',$version))
        throw new RuntimeException('Invalid Graph API version.');
    if(!extension_loaded('curl'))throw new RuntimeException('cURL extension required.');
    $token=connect_account_secrets($account)['token'];
    if(strlen($token)<20)throw new RuntimeException('Meta token is not configured.');
    $phoneId=$account['phone_number_id'];
    $url='https://graph.facebook.com/'.$version.'/'.$phoneId.'/messages';
    $payload=json_encode(['messaging_product'=>'whatsapp','to'=>$job['phone'],'type'=>'template',
        'template'=>['name'=>$job['template'],'language'=>['code'=>$job['language']]],
        'biz_opaque_callback_data'=>$job['id']],JSON_THROW_ON_ERROR);
    $h=curl_init($url);
    curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true]);
    $body=curl_exec($h);$error=curl_error($h);$code=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);
    curl_close($h);
    if($body===false)return ['http'=>0,'error'=>'transport_uncertain'];
    $parsed=json_decode($body,true);
    $id=(string)($parsed['messages'][0]['id']??'');
    if(strlen($body)>32768)return ['http'=>0,'error'=>'response_too_large'];
    return ['http'=>$code,'provider_id'=>$id,'error'=>$error?:null];
}
function connect_worker_step(?callable $transport=null): array {
    if(PHP_SAPI!=='cli')throw new RuntimeException('Queue worker is CLI-only.');
    $job=connect_worker_claim();
    if(!$job)return ['result'=>'empty'];
    $tenant=$job['tenant_id'];$account=connect_store()['accounts'][$tenant]??null;
    $allowed=$account&&connect_message_allowed($tenant,$job['phone'],$job['purpose'])
        &&connect_template_allowed($job['template'],$job['language']);
    if(!$allowed){
        connect_worker_finish($job['id'],'blocked',null);
        return ['result'=>'blocked','id'=>$job['id']];
    }
    try{$response=$transport?$transport($job,$account):connect_transport_meta($job,$account);}
    catch(Throwable){$response=['http'=>0,'error'=>'transport_uncertain'];}
    $code=(int)($response['http']??0);$provider=(string)($response['provider_id']??'');
    if($code>=200&&$code<300&&preg_match('/^[a-zA-Z0-9._:-]{5,200}$/D',$provider))$state='submitted';
    elseif($code>=400&&$code<500&&$code!==429)$state='failed';
    else $state='needs_review'; // Timeouts, rate limits or server errors may be ambiguous; no blind retries.
    connect_worker_finish($job['id'],$state,$provider!==''?$provider:null);
    return ['result'=>$state,'id'=>$job['id']];
}
function connect_worker_finish(string $id,string $status,?string $providerId): void {
    if(!in_array($status,['submitted','retry','failed','needs_review','blocked'],true))
        throw new InvalidArgumentException('Invalid queue transition.');
    connect_runtime_mutate(static function(array $data)use($id,$status,$providerId){
        $job=$data['outbox'][$id]??null;
        if(!$job)throw new RuntimeException('Queue item not found.');
        // A webhook may have already advanced the message to delivered/read.
        if($job['status']!=='leased')return $data;
        if($status==='retry'){
            $job['status']='queued';
            $job['next_attempt_at']=time()+min(3600,60*(2**min(5,(int)$job['attempts'])));
            if((int)$job['attempts']>=4)$job['status']='needs_review';
        }else $job['status']=$status;
        $job['lease_until']=0;$job['updated_at']=gmdate('c');
        if($status==='submitted'&&$providerId!==null){
            if(isset($data['provider_index'][$providerId])&&$data['provider_index'][$providerId]!==$id)
                throw new RuntimeException('Provider message ID already assigned to another job.');
            $job['provider_id']=$providerId;$data['provider_index'][$providerId]=$id;
            // Receipts may reach the receiver before the HTTP response is persisted.
            $rank=['submitted'=>1,'sent'=>2,'failed'=>2,'delivered'=>3,'read'=>4];
            $remaining=[];
            foreach($data['orphan_receipts'] as $receipt){
                if(($receipt['tenant_id']??'')!==$job['tenant_id']||$receipt['provider_id']!==$providerId||
                    ($receipt['recipient']!==''&&$receipt['recipient']!==$job['phone'])){
                    $remaining[]=$receipt;continue;
                }
                $new=(string)$receipt['status'];$old=(string)$job['status'];
                if(($rank[$new]??0)>($rank[$old]??0)){
                    $job['status']=$new;$job['updated_at']=$receipt['at'];
                }
            }
            $data['orphan_receipts']=$remaining;
        }
        $data['outbox'][$id]=$job;
        if(isset($data['messages'][$job['message_id']])){
            $data['messages'][$job['message_id']]['status']=$job['status'];
            $data['messages'][$job['message_id']]['provider_id']=$job['provider_id']??null;
            $data['messages'][$job['message_id']]['updated_at']=$job['updated_at'];
        }
        return $data;
    },'outbox.finish');
}
function connect_inbox_list(string $tenant,int $limit=30): array {
    if($tenant==='platform')require_admin();else tenant_require('connect.read',$tenant);
    $items=array_values(array_filter(connect_runtime_store()['conversations'],
        static fn($c)=>($c['tenant_id']??'')===$tenant));
    usort($items,static fn($a,$b)=>strcmp((string)$b['last_message_at'],(string)$a['last_message_at']));
    return array_slice($items,0,max(1,min(50,$limit)));
}
function connect_inbox_thread(string $tenant,string $id): ?array {
    if($tenant==='platform')require_admin();else tenant_require('connect.read',$tenant);
    if(!tenant_valid_id($id))return null;
    $d=connect_runtime_store();$conversation=null;$key='';
    foreach($d['conversations'] as $k=>$c){
        if(($c['id']??'')===$id&&($c['tenant_id']??'')===$tenant){$key=$k;$conversation=$c;break;}
    }
    if(!$conversation)return null;
    $messages=array_values(array_filter($d['messages'],
        static fn($m)=>($m['tenant_id']??'')===$tenant&&($m['conversation_key']??'')===$key));
    usort($messages,static fn($a,$b)=>strcmp($a['created_at'],$b['created_at']));
    return ['conversation'=>$conversation,'messages'=>array_slice($messages,-60)];
}
function connect_inbox_mark_read(string $tenant,string $id): void {
    if($tenant==='platform')require_admin();else tenant_require('connect.read',$tenant);
    connect_runtime_mutate(static function(array $d)use($tenant,$id){
        foreach($d['conversations'] as &$c){
            if(($c['id']??'')===$id&&($c['tenant_id']??'')===$tenant){$c['unread']=0;return $d;}
        }
        throw new RuntimeException('Conversation not found.');
    },'inbox.read');
}
function connect_queue_summary(string $tenant): array {
    if($tenant==='platform')require_admin();else tenant_require('connect.read',$tenant);
    $items=array_values(array_filter(connect_runtime_store()['outbox'],
        static fn($r)=>($r['tenant_id']??'')===$tenant));
    $counts=['queued'=>0,'leased'=>0,'submitted'=>0,'sent'=>0,'delivered'=>0,
        'read'=>0,'failed'=>0,'blocked'=>0,'needs_review'=>0];
    foreach($items as $r){$s=(string)$r['status'];if(isset($counts[$s]))$counts[$s]++;}
    return $counts;
}
