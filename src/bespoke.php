<?php
declare(strict_types=1);

/**
 * Sanjay Nursery Bespoke Studio — v0.4.5
 * Quote lifecycle, immutable design revisions, customer approval, deposit and design freeze.
 */

function bespoke_root(): string { return data_root() . '/private-domain/bespoke'; }
function bespoke_quote_file(): string { return bespoke_root() . '/quotes.json'; }
function bespoke_payment_file(): string { return bespoke_root() . '/deposit-payments.json'; }
function bespoke_quote_context(): string { return 'sanjaynursery:bespoke-quotes:v1'; }
function bespoke_payment_context(): string { return 'sanjaynursery:bespoke-deposits:v1'; }

function bespoke_ensure_dirs(): void {
    $dir=bespoke_root();
    if(!is_dir($dir) && !@mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Unable to create bespoke storage directory.');
    @chmod($dir,0750);
}

function bespoke_quotes(): array {
    bespoke_ensure_dirs();
    try { return commerce_private_read(bespoke_quote_file(), bespoke_quote_context(), []); }
    catch(Throwable) { return []; }
}
function bespoke_quote_by_guid(string $guid): ?array { foreach(bespoke_quotes() as $q) if(($q['guid']??'')===$guid) return $q; return null; }
function bespoke_customer_quotes(?string $customerGuid=null): array {
    $customerGuid=$customerGuid?:((string)(customer_identity()['guid']??''));
    $rows=array_values(array_filter(bespoke_quotes(),fn($q)=>($q['customer_guid']??'')===$customerGuid));
    usort($rows,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));
    return $rows;
}
function bespoke_customer_quote(string $guid): ?array {
    if(!customer_logged_in()) return null;
    $q=bespoke_quote_by_guid($guid);
    return $q && ($q['customer_guid']??'')===(string)customer_identity()['guid'] ? $q : null;
}
function bespoke_admin_quotes(?string $status=null): array {
    $rows=bespoke_quotes(); if($status!=='') $rows=array_values(array_filter($rows,fn($q)=>($q['status']??'')===$status));
    usort($rows,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??''))); return $rows;
}
function bespoke_quote_number(): string { return 'AWQ-'.gmdate('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8)); }
function bespoke_freeze_number(): string { return 'AWF-'.gmdate('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8)); }
function bespoke_revision_hash(array $revision): string {
    $stable=[
        'revision_no'=>(int)($revision['revision_no']??0),'product_guid'=>$revision['product_guid']??null,'title'=>$revision['title']??'',
        'scope'=>$revision['scope']??'','dimensions'=>$revision['dimensions']??'','material'=>$revision['material']??'','finish'=>$revision['finish']??'','drawing_reference'=>$revision['drawing_reference']??'',
        'configuration_snapshot'=>$revision['configuration_snapshot']??[],'pricing'=>$revision['pricing']??[],'deposit_percent'=>(int)($revision['deposit_percent']??0),
        'lead_time'=>$revision['lead_time']??'','terms'=>$revision['terms']??'','valid_until'=>$revision['valid_until']??'',
    ];
    return hash('sha256',json_encode($stable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function bespoke_latest_revision(array $quote): ?array { $rows=$quote['revisions']??[]; return $rows ? $rows[array_key_last($rows)] : null; }
function bespoke_approved_revision(array $quote): ?array {
    $n=(int)($quote['approved_revision']??0); if($n<1)return null;
    foreach($quote['revisions']??[] as $r)if((int)($r['revision_no']??0)===$n)return$r;return null;
}
function bespoke_status_label(string $status): string { return ucwords(strtolower(str_replace('_',' ',$status))); }

function bespoke_create_request(array $input): array {
    require_customer('/bespoke/request');
    $brief=trim((string)($input['brief']??''));$slug=trim((string)($input['slug']??''));$configGuid=trim((string)($input['configuration_guid']??''));
    if(text_len($brief)<12) return [false,'Tell us a little more about the piece or change you need.'];
    $product=$slug!==''?product_by_slug($slug):null;$saved=null;
    if($configGuid!==''){$saved=customer_saved_configuration($configGuid);if(!$saved)return[false,'Saved configuration not found or access denied.'];if(!$product)$product=product_by_slug((string)$saved['slug']);}
    if(!$product)return[false,'Choose a valid product as the starting point.'];
    if($saved && (string)($saved['slug']??'') !== (string)$product['slug']) return [false,'Saved configuration does not belong to the selected product.'];
    $cg=(string)customer_identity()['guid'];$customer=customer_by_guid($cg);
    $sourceConfig=[];$sourceSummary=[];$sourcePrice=(int)$product['price'];
    if($saved){$sourceConfig=$saved['selection']??[];$sourceSummary=$saved['summary']??[];$current=configurator_current_quote_for_saved($saved);if($current)$sourcePrice=(int)$current['unit_price'];}
    $q=['guid'=>uuidv7(),'number'=>bespoke_quote_number(),'customer_guid'=>$cg,'customer_snapshot'=>['name'=>$customer['name']??'','email'=>$customer['email']??'','phone'=>$customer['phone']??''],
        'product_guid'=>$product['guid'],'slug'=>$product['slug'],'product_snapshot'=>['name'=>$product['name'],'sku'=>$product['sku'],'image'=>$product['image'],'base_price'=>$product['price']],
        'source_configuration_guid'=>$saved['guid']??null,'source_configuration'=>$sourceConfig,'source_summary'=>$sourceSummary,'source_price'=>$sourcePrice,
        'brief'=>$brief,'space'=>trim((string)($input['space']??'')),'target_dimensions'=>trim((string)($input['target_dimensions']??'')),'budget'=>trim((string)($input['budget']??'')),
        'status'=>'REQUESTED','current_revision'=>0,'approved_revision'=>null,'revisions'=>[],'decisions'=>[],'deposit'=>['status'=>'NOT_READY','amount'=>0,'percent'=>0,'attempt_guid'=>null,'paid_at'=>null,'provider_reference'=>null],
        'freeze'=>null,'timeline'=>[['ts'=>gmdate('c'),'event'=>'REQUESTED','actor'=>'customer','note'=>'Bespoke request submitted.']], 'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
    commerce_private_mutate(bespoke_quote_file(),bespoke_quote_context(),function(array $rows)use($q){foreach($rows as $x)if(($x['number']??'')===$q['number'])throw new RuntimeException('Quote number collision.');$rows[]=$q;return$rows;},'bespoke.request.create');
    commerce_audit('bespoke.request.create',['quote_guid'=>$q['guid'],'product_guid'=>$product['guid'],'customer_guid'=>$cg]);
    return [true,$q];
}

function bespoke_admin_create_revision(string $quoteGuid,array $input): array {
    require_admin();$updated=null;
    commerce_private_mutate(bespoke_quote_file(),bespoke_quote_context(),function(array $rows)use($quoteGuid,$input,&$updated){
        foreach($rows as &$q){if(($q['guid']??'')!==$quoteGuid)continue;
            if(in_array($q['status']??'', ['DEPOSIT_PAID','DESIGN_FROZEN','CANCELLED'],true)) throw new RuntimeException('This quote can no longer be revised.');
            $piece=max(0,(int)($input['piece_amount']??0));$designFee=max(0,(int)($input['design_fee']??0));$delivery=max(0,(int)($input['delivery_estimate']??0));$total=$piece+$designFee+$delivery;$percent=max(10,min(100,(int)($input['deposit_percent']??50)));if($total<1)throw new RuntimeException('Enter a valid quoted amount.');
            $n=(int)($q['current_revision']??0)+1;$config=(array)($q['source_configuration']??[]);
            $revision=['guid'=>uuidv7(),'revision_no'=>$n,'state'=>'DRAFT','product_guid'=>$q['product_guid']??null,'title'=>trim((string)($input['title']??($q['product_snapshot']['name']??'Bespoke piece'))),
                'scope'=>trim((string)($input['scope']??'')),'dimensions'=>trim((string)($input['dimensions']??($q['target_dimensions']??''))),'material'=>trim((string)($input['material']??'')),'finish'=>trim((string)($input['finish']??'')),
                'configuration_snapshot'=>$config,'drawing_reference'=>trim((string)($input['drawing_reference']??'')),'pricing'=>['currency'=>'INR','piece'=>$piece,'design_fee'=>$designFee,'delivery_estimate'=>$delivery,'total'=>$total],
                'deposit_percent'=>$percent,'deposit_amount'=>(int)round($total*$percent/100),'lead_time'=>trim((string)($input['lead_time']??'')),'terms'=>trim((string)($input['terms']??'')),
                'valid_until'=>trim((string)($input['valid_until']??'')),'created_by'=>'admin:'.(admin_identity()['guid']??'unknown'),'created_at'=>gmdate('c'),'published_at'=>null];
            if(text_len($revision['scope'])<10)throw new RuntimeException('Add a clear scope for this design revision.');
            $revision['fingerprint']=bespoke_revision_hash($revision);$q['revisions'][]=$revision;$q['current_revision']=$n;$q['approved_revision']=null;$q['status']='IN_REVIEW';
            $q['deposit']=['status'=>'NOT_READY','amount'=>0,'percent'=>0,'attempt_guid'=>null,'paid_at'=>null,'provider_reference'=>null];$q['updated_at']=gmdate('c');$q['timeline'][]=['ts'=>gmdate('c'),'event'=>'REVISION_'.$n.'_CREATED','actor'=>'admin','note'=>'Design revision created.'];$updated=$q;break;
        }return$rows;
    },'bespoke.revision.create');
    if(!$updated)throw new RuntimeException('Bespoke quote not found.');commerce_audit('bespoke.revision.create',['quote_guid'=>$quoteGuid,'revision'=>$updated['current_revision']]);return$updated;
}

function bespoke_admin_publish_revision(string $quoteGuid): array {
    require_admin();$updated=null;
    commerce_private_mutate(bespoke_quote_file(),bespoke_quote_context(),function(array $rows)use($quoteGuid,&$updated){foreach($rows as &$q){if(($q['guid']??'')!==$quoteGuid)continue;if(in_array($q['status']??'', ['DEPOSIT_PAID','DESIGN_FROZEN','CANCELLED'],true))throw new RuntimeException('This quote cannot be published.');$idx=array_key_last($q['revisions']??[]);if($idx===null)throw new RuntimeException('Create a revision first.');$r=&$q['revisions'][$idx];if(($r['state']??'')!=='DRAFT')throw new RuntimeException('Only a draft revision can be published.');if(!hash_equals((string)$r['fingerprint'],bespoke_revision_hash($r)))throw new RuntimeException('Revision integrity check failed.');$r['state']='PUBLISHED';$r['published_at']=gmdate('c');$q['status']='AWAITING_APPROVAL';$q['updated_at']=gmdate('c');$q['timeline'][]=['ts'=>gmdate('c'),'event'=>'REVISION_'.$r['revision_no'].'_PUBLISHED','actor'=>'admin','note'=>'Revision sent for customer approval.'];$updated=$q;break;}return$rows;},'bespoke.revision.publish');
    if(!$updated)throw new RuntimeException('Bespoke quote not found.');commerce_audit('bespoke.revision.publish',['quote_guid'=>$quoteGuid,'revision'=>$updated['current_revision']]);return$updated;
}

function bespoke_customer_decide(string $quoteGuid,string $decision,string $note=''): array {
    require_customer('/account/quotes');$decision=strtoupper(trim($decision));if(!in_array($decision,['APPROVE','CHANGES'],true))throw new RuntimeException('Unsupported decision.');$cg=(string)customer_identity()['guid'];$updated=null;
    commerce_private_mutate(bespoke_quote_file(),bespoke_quote_context(),function(array $rows)use($quoteGuid,$decision,$note,$cg,&$updated){foreach($rows as &$q){if(($q['guid']??'')!==$quoteGuid)continue;if(($q['customer_guid']??'')!==$cg)throw new RuntimeException('Quote access denied.');if(($q['status']??'')!=='AWAITING_APPROVAL')throw new RuntimeException('This quote is not awaiting approval.');$idx=array_key_last($q['revisions']??[]);if($idx===null)throw new RuntimeException('No revision is available.');$r=&$q['revisions'][$idx];if(($r['state']??'')!=='PUBLISHED')throw new RuntimeException('Only the current published revision can be decided.');if(!hash_equals((string)$r['fingerprint'],bespoke_revision_hash($r)))throw new RuntimeException('Revision integrity check failed.');
        $ts=gmdate('c');if($decision==='APPROVE' && !empty($r['valid_until']) && gmdate('Y-m-d') > (string)$r['valid_until']) throw new RuntimeException('This proposal has expired. Ask the studio to publish a fresh revision.');if($decision==='APPROVE'){$r['state']='APPROVED';$q['approved_revision']=$r['revision_no'];$q['status']='APPROVED_AWAITING_DEPOSIT';$q['deposit']=['status'=>'DUE','amount'=>(int)$r['deposit_amount'],'percent'=>(int)$r['deposit_percent'],'attempt_guid'=>null,'paid_at'=>null,'provider_reference'=>null];$event='APPROVED';$msg='Customer approved revision '.$r['revision_no'].'.';}
        else {$note=trim($note);if(text_len($note)<5)throw new RuntimeException('Please explain the change you would like.');$r['state']='CHANGES_REQUESTED';$q['approved_revision']=null;$q['status']='CHANGES_REQUESTED';$q['deposit']=['status'=>'NOT_READY','amount'=>0,'percent'=>0,'attempt_guid'=>null,'paid_at'=>null,'provider_reference'=>null];$event='CHANGES_REQUESTED';$msg=$note;}
        $decisionRow=['guid'=>uuidv7(),'revision_no'=>$r['revision_no'],'decision'=>$decision,'note'=>trim($note),'customer_guid'=>$cg,'ts'=>$ts,'revision_fingerprint'=>$r['fingerprint']];$decisionRow['signature']=hash('sha256',json_encode($decisionRow,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$q['decisions'][]=$decisionRow;$q['timeline'][]=['ts'=>$ts,'event'=>$event,'actor'=>'customer','note'=>$msg];$q['updated_at']=$ts;$updated=$q;break;}return$rows;},'bespoke.customer.decision');
    if(!$updated)throw new RuntimeException('Bespoke quote not found.');commerce_audit('bespoke.customer.decision',['quote_guid'=>$quoteGuid,'decision'=>$decision]);return$updated;
}

/* ---------------- Deposit payment ---------------- */
function bespoke_deposit_payments(): array { try{return commerce_private_read(bespoke_payment_file(),bespoke_payment_context(),['attempts'=>[],'events'=>[]]);}catch(Throwable){return['attempts'=>[],'events'=>[]];} }
function bespoke_deposit_attempt_by_guid(string $guid): ?array { foreach(bespoke_deposit_payments()['attempts']??[] as $a)if(($a['guid']??'')===$guid)return$a;return null; }
function bespoke_deposit_attempt_for_quote(string $quoteGuid): ?array { $rows=bespoke_deposit_payments()['attempts']??[];for($i=count($rows)-1;$i>=0;$i--){$a=$rows[$i];if(($a['quote_guid']??'')===$quoteGuid&&in_array(($a['status']??''),['CREATED','FAILED'],true))return$a;}return null; }
function bespoke_create_deposit_attempt(array $quote): array {
    if(($quote['status']??'')!=='APPROVED_AWAITING_DEPOSIT'||($quote['deposit']['status']??'')!=='DUE')throw new RuntimeException('Deposit is not due for this quote.');
    $attempt=['guid'=>uuidv7(),'quote_guid'=>$quote['guid'],'quote_number'=>$quote['number'],'revision_no'=>$quote['approved_revision'],'amount'=>(int)$quote['deposit']['amount'],'currency'=>'INR','provider'=>'sandbox','status'=>'CREATED','idempotency_key'=>bin2hex(random_bytes(16)),'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
    commerce_private_mutate(bespoke_payment_file(),bespoke_payment_context(),function(array $s)use($attempt){$s+=['attempts'=>[],'events'=>[]];$s['attempts'][]=$attempt;return$s;},'bespoke.deposit.attempt');
    commerce_private_mutate(bespoke_quote_file(),bespoke_quote_context(),function(array $rows)use($quote,$attempt){foreach($rows as &$q)if(($q['guid']??'')===$quote['guid']){$q['deposit']['attempt_guid']=$attempt['guid'];$q['timeline'][]=['ts'=>gmdate('c'),'event'=>'DEPOSIT_STARTED','actor'=>'customer','note'=>'Sandbox deposit payment started.'];$q['updated_at']=gmdate('c');}return$rows;},'bespoke.deposit.link');
    commerce_audit('bespoke.deposit.attempt',['quote_guid'=>$quote['guid'],'attempt_guid'=>$attempt['guid'],'amount'=>$attempt['amount']]);return$attempt;
}
function bespoke_process_deposit_event(array $event,string $raw,string $signature): array {
    $secret=sandbox_webhook_secret();if($secret==='')throw new RuntimeException('Sandbox webhook secret is not configured.');$expected=hash_hmac('sha256',$raw,$secret);if(!hash_equals($expected,$signature))throw new RuntimeException('Webhook signature verification failed.');
    foreach(['event_id','attempt_guid','status'] as $k)if(empty($event[$k]))throw new RuntimeException('Webhook payload is incomplete.');$status=strtoupper((string)$event['status']);if(!in_array($status,['SUCCESS','FAILED'],true))throw new RuntimeException('Unsupported deposit event status.');$attempt=bespoke_deposit_attempt_by_guid((string)$event['attempt_guid']);if(!$attempt)throw new RuntimeException('Deposit attempt not found.');
    $duplicate=false;$processed=false;commerce_private_mutate(bespoke_payment_file(),bespoke_payment_context(),function(array $s)use($event,$status,&$duplicate,&$processed){$s+=['attempts'=>[],'events'=>[]];foreach($s['events'] as $e)if(($e['event_id']??'')===$event['event_id']){$duplicate=true;return$s;}foreach($s['attempts'] as &$a)if(($a['guid']??'')===$event['attempt_guid']){$a['status']=$status==='SUCCESS'?'PAID':'FAILED';$a['provider_reference']=$event['provider_reference']??null;$a['updated_at']=gmdate('c');}$s['events'][]=['event_id'=>$event['event_id'],'attempt_guid'=>$event['attempt_guid'],'status'=>$status,'provider_reference'=>$event['provider_reference']??null,'received_at'=>gmdate('c')];$processed=true;return$s;},'bespoke.deposit.webhook');
    if($duplicate)return['ok'=>true,'duplicate'=>true,'quote_guid'=>$attempt['quote_guid']];if(!$processed)throw new RuntimeException('Deposit event was not processed.');
    commerce_private_mutate(bespoke_quote_file(),bespoke_quote_context(),function(array $rows)use($attempt,$event,$status){foreach($rows as &$q)if(($q['guid']??'')===$attempt['quote_guid']){if($status==='SUCCESS'){if(($q['status']??'')!=='APPROVED_AWAITING_DEPOSIT'&&($q['status']??'')!=='DEPOSIT_PAID')throw new RuntimeException('Quote is not eligible for deposit settlement.');$approved=bespoke_approved_revision($q);if(!$approved||((int)$approved['deposit_amount']!==(int)$attempt['amount']))throw new RuntimeException('Deposit amount or revision no longer matches the approved quote.');$q['deposit']['status']='PAID';$q['deposit']['paid_at']=gmdate('c');$q['deposit']['provider_reference']=$event['provider_reference']??null;$q['status']='DEPOSIT_PAID';$q['timeline'][]=['ts'=>gmdate('c'),'event'=>'DEPOSIT_PAID','actor'=>'payment-webhook','note'=>'Sandbox deposit verified.'];}else{$q['deposit']['status']='FAILED';$q['timeline'][]=['ts'=>gmdate('c'),'event'=>'DEPOSIT_FAILED','actor'=>'payment-webhook','note'=>'Sandbox deposit failed.'];}$q['updated_at']=gmdate('c');}return$rows;},'bespoke.deposit.settle');
    commerce_audit('bespoke.deposit.webhook',['quote_guid'=>$attempt['quote_guid'],'attempt_guid'=>$attempt['guid'],'status'=>$status]);return['ok'=>true,'duplicate'=>false,'quote_guid'=>$attempt['quote_guid']];
}
function bespoke_sandbox_complete_deposit(string $attemptGuid,string $status): array {$a=bespoke_deposit_attempt_by_guid($attemptGuid);if(!$a)throw new RuntimeException('Deposit attempt not found.');$event=['event_id'=>'bdep_'.bin2hex(random_bytes(10)),'attempt_guid'=>$attemptGuid,'status'=>$status,'provider_reference'=>'bsp_'.bin2hex(random_bytes(6))];$raw=json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);return bespoke_process_deposit_event($event,$raw,sandbox_signature($raw));}

function bespoke_admin_freeze(string $quoteGuid,string $note=''): array {
    require_admin();$updated=null;
    commerce_private_mutate(bespoke_quote_file(),bespoke_quote_context(),function(array $rows)use($quoteGuid,$note,&$updated){foreach($rows as &$q){if(($q['guid']??'')!==$quoteGuid)continue;if(($q['status']??'')==='DESIGN_FROZEN'){ $updated=$q; return$rows; }if(($q['status']??'')!=='DEPOSIT_PAID'||($q['deposit']['status']??'')!=='PAID')throw new RuntimeException('Verified deposit is required before design freeze.');$r=bespoke_approved_revision($q);if(!$r)throw new RuntimeException('Approved revision is missing.');if(!hash_equals((string)$r['fingerprint'],bespoke_revision_hash($r)))throw new RuntimeException('Approved revision integrity check failed.');$snapshot=['quote_number'=>$q['number'],'customer_guid'=>$q['customer_guid'],'product_snapshot'=>$q['product_snapshot'],'revision'=>$r,'deposit'=>$q['deposit'],'brief'=>$q['brief'],'space'=>$q['space'],'target_dimensions'=>$q['target_dimensions']];$fingerprint=hash('sha256',json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$q['freeze']=['guid'=>uuidv7(),'number'=>bespoke_freeze_number(),'revision_no'=>$r['revision_no'],'snapshot'=>$snapshot,'fingerprint'=>$fingerprint,'note'=>trim($note),'frozen_by'=>'admin:'.(admin_identity()['guid']??'unknown'),'frozen_at'=>gmdate('c')];$q['status']='DESIGN_FROZEN';$q['timeline'][]=['ts'=>gmdate('c'),'event'=>'DESIGN_FROZEN','actor'=>'admin','note'=>trim($note)?:'Approved design specification frozen for production handoff.'];$q['updated_at']=gmdate('c');$updated=$q;break;}return$rows;},'bespoke.design.freeze');
    if(!$updated)throw new RuntimeException('Bespoke quote not found.');commerce_audit('bespoke.design.freeze',['quote_guid'=>$quoteGuid,'freeze_guid'=>$updated['freeze']['guid']??null]);return$updated;
}

function bespoke_admin_cancel(string $quoteGuid,string $note=''): array {
    require_admin();$updated=null;commerce_private_mutate(bespoke_quote_file(),bespoke_quote_context(),function(array $rows)use($quoteGuid,$note,&$updated){foreach($rows as &$q)if(($q['guid']??'')===$quoteGuid){if(in_array($q['status']??'', ['DEPOSIT_PAID','DESIGN_FROZEN'],true))throw new RuntimeException('A paid/frozen quote cannot be cancelled from this workflow.');$q['status']='CANCELLED';$q['timeline'][]=['ts'=>gmdate('c'),'event'=>'CANCELLED','actor'=>'admin','note'=>trim($note)];$q['updated_at']=gmdate('c');$updated=$q;}return$rows;},'bespoke.cancel');if(!$updated)throw new RuntimeException('Quote not found.');commerce_audit('bespoke.cancel',['quote_guid'=>$quoteGuid]);return$updated;
}

function bespoke_open_count(): int { return count(array_filter(bespoke_quotes(),fn($q)=>!in_array(($q['status']??''),['DESIGN_FROZEN','CANCELLED'],true))); }
function bespoke_release_checks(): array {
    $quotes=bespoke_quotes();$schema=true;$integrity=true;$freezeIntegrity=true;$numbers=[];
    foreach($quotes as $q){$numbers[]=$q['number']??'';foreach(['guid','number','customer_guid','product_guid','status','revisions','deposit'] as $k)if(!array_key_exists($k,$q))$schema=false;foreach($q['revisions']??[] as $r)if(empty($r['fingerprint'])||!hash_equals((string)$r['fingerprint'],bespoke_revision_hash($r)))$integrity=false;if(!empty($q['freeze'])){$f=$q['freeze'];$calc=hash('sha256',json_encode($f['snapshot']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));if(!hash_equals((string)($f['fingerprint']??''),$calc))$freezeIntegrity=false;}}
    $dupes=count($numbers)!==count(array_unique($numbers));$encrypted=!is_file(bespoke_quote_file())||!str_contains((string)@file_get_contents(bespoke_quote_file()),'customer_guid');
    return [
        ['name'=>'Bespoke quote encryption','ok'=>$encrypted,'detail'=>$encrypted?'Bespoke quote/customer data is encrypted at rest or not initialized.':'Plaintext bespoke customer data detected.'],
        ['name'=>'Bespoke quote schema','ok'=>$schema,'detail'=>$schema?'Existing bespoke records contain required workflow fields.':'Malformed bespoke quote record detected.'],
        ['name'=>'Bespoke quote number uniqueness','ok'=>!$dupes,'detail'=>$dupes?'Duplicate bespoke quote number detected.':'Bespoke quote numbers are unique.'],
        ['name'=>'Design revision integrity','ok'=>$integrity,'detail'=>$integrity?'All stored design revision fingerprints validate.':'Design revision fingerprint mismatch detected.'],
        ['name'=>'Design freeze integrity','ok'=>$freezeIntegrity,'detail'=>$freezeIntegrity?'All design freeze snapshots validate.':'Design freeze snapshot fingerprint mismatch detected.'],
        ['name'=>'Deposit workflow gate','ok'=>sandbox_webhook_secret()!=='','detail'=>sandbox_webhook_secret()!==''?'Signed sandbox deposit events are available.':'Configure sandbox webhook secret for deposit verification.'],
        ['name'=>'Bespoke staging gate','ok'=>($GLOBALS['app']['environment']??'staging')!=='production','detail'=>'Bespoke pricing/deposit workflow remains staging-only pending commercial/legal sign-off.'],
    ];
}

bespoke_ensure_dirs();
