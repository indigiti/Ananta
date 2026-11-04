<?php
declare(strict_types=1);

/**
 * Ananta Sourcing B3: encrypted, file-first multi-recipient RFQs.
 * Commercial intent only. Prices are supplier-authored indications, not contracts,
 * payment instructions, accepted orders or stock reservations.
 */
function sourcing_file(): string {
    $test=trim((string)getenv('ANANTA_TEST_SOURCING_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$test!=='')return $test;
    return data_root().'/private-domain/marketplace/sourcing-rfqs.json';
}
function sourcing_store(): array {
    $d=confidential_json_read(sourcing_file(),'ananta:sourcing-rfqs:v1',[]);
    return array_replace(['schema_version'=>1,'rfqs'=>[]],$d);
}
function sourcing_mutate(callable $action,string $reason): array {
    if(confidential_key()===null)throw new RuntimeException('Private sourcing encryption key is required.');
    return confidential_json_mutate(sourcing_file(),'ananta:sourcing-rfqs:v1',
        static fn(array $d)=>$action(array_replace(['schema_version'=>1,'rfqs'=>[]],$d)),'sourcing.'.$reason);
}
function sourcing_buyer(): array {
    $guid=(string)(customer_identity()['guid']??'');
    if(!tenant_valid_id($guid))throw new RuntimeException('Buyer sign-in is required.');
    $buyer=customer_by_guid($guid);
    if(!$buyer||($buyer['status']??'active')!=='active')throw new RuntimeException('Buyer account is not active.');
    return $buyer;
}
function sourcing_verified_suppliers(): array {
    // Only actively onboarded nurseries should receive sourcing requests.
    // An admin-verified directory profile without a supplier member has no
    // authenticated inbox and must not appear as a deliverable RFQ destination.
    $identity=tenant_store();
    $active=[];
    foreach($identity['memberships'] as $member){
        $tenantId=(string)($member['tenant_id']??'');
        $user=$identity['users'][(string)($member['user_id']??'')]??null;
        $tenant=$identity['tenants'][$tenantId]??null;
        if(($member['active']??false)&&$user&&($user['active']??false)
           &&$tenant&&($tenant['status']??'')==='active'&&tenant_valid_id($tenantId)){
            $active[$tenantId]=true;
        }
    }
    $all=array_values(array_filter(marketplace_offers_store()['suppliers'],
        static fn($s)=>($s['status']??'')==='verified'&&isset($active[(string)($s['id']??'')])));
    usort($all,static fn($a,$b)=>strnatcasecmp((string)($a['name']??''),(string)($b['name']??'')));
    return array_map(static fn($s)=>['id'=>$s['id'],'name'=>$s['name'],'city'=>$s['city']],$all);
}
function sourcing_require_text(mixed $value,int $min,int $max,string $field): string {
    if(!is_string($value))throw new InvalidArgumentException('Invalid '.$field.'.');
    $text=trim($value);
    if(text_len($text)<$min||text_len($text)>$max||str_contains($text,"\0"))
        throw new InvalidArgumentException($field.' must contain '.$min.'–'.$max.' characters.');
    return $text;
}
function sourcing_parse_recipients(mixed $input): array {
    if(!is_array($input)||count($input)<1||count($input)>5)throw new InvalidArgumentException('Select 1–5 nurseries.');
    $approved=array_column(sourcing_verified_suppliers(),null,'id');
    $selected=[];
    foreach($input as $raw) {
        if(!is_string($raw)||!tenant_valid_id($raw)||!isset($approved[$raw]))
            throw new InvalidArgumentException('A selected nursery is not verified.');
        if(isset($selected[$raw]))throw new InvalidArgumentException('Select each nursery only once.');
        $selected[$raw]=true;
    }
    return array_keys($selected);
}
function sourcing_create(array $input): string {
    $buyer=sourcing_buyer();
    $rate=api_rate_limit('sourcing-request:'.$buyer['guid'],5,3600);
    if(!$rate['allowed'])throw new RuntimeException('Too many sourcing requests. Please try later.');
    $product=product_by_slug(trim((string)($input['product_slug']??'')));
    if(!$product||($product['status']??'')!=='active')throw new InvalidArgumentException('Choose a published plant.');
    $qtyText=trim((string)($input['quantity']??''));
    if(!ctype_digit($qtyText)||strlen($qtyText)>5||(int)$qtyText<1||(int)$qtyText>50000)
        throw new InvalidArgumentException('Quantity must be between 1 and 50,000.');
    $grade=sourcing_require_text($input['specification']??'',2,180,'Plant size and specification');
    $destination=sourcing_require_text($input['destination']??'',2,140,'Delivery destination');
    $notes=sourcing_require_text($input['notes']??'',0,600,'Additional notes');
    $recipients=sourcing_parse_recipients($input['supplier_ids']??null);
    if(($input['consent']??null)!=='yes')throw new InvalidArgumentException('Confirm consent to share this requirement with selected nurseries.');
    $shareContact=($input['share_contact']??'')==='yes';
    $buyerContact=$shareContact?[
        'name'=>(string)($buyer['name']??''),
        'email'=>(string)($buyer['email']??''),
        'phone'=>(string)($buyer['phone']??''),
    ]:null;
    $id=uuidv7();$now=gmdate('c');
    sourcing_mutate(static function(array $d)use($buyer,$product,$qtyText,$grade,$destination,$notes,$recipients,$shareContact,$buyerContact,$id,$now){
        if(count($d['rfqs'])>=1500)throw new RuntimeException('Sourcing pilot capacity reached.');
        $invites=[];
        // Verify recipient state again during the serialized write.
        $available=array_column(sourcing_verified_suppliers(),null,'id');
        foreach($recipients as $supplierId) {
            if(!isset($available[$supplierId]))throw new RuntimeException('Supplier verification changed. Please start again.');
            $invites[$supplierId]=[
                'status'=>'invited','quote'=>null,'quote_version'=>0,
                'updated_at'=>$now,
            ];
        }
        $d['rfqs'][$id]=[
            'id'=>$id,'buyer_guid'=>$buyer['guid'],
            'plant_guid'=>$product['guid'],'plant_name'=>$product['name'],
            'specification'=>$grade,'quantity'=>(int)$qtyText,
            'destination'=>$destination,'notes'=>$notes,
            'contact_shared'=>$shareContact,'buyer_contact'=>$buyerContact,
            'status'=>'open','recipients'=>$invites,'created_at'=>$now,'updated_at'=>$now,
        ];
        return $d;
    },'create');
    audit_event('sourcing.rfq.create',['rfq_id'=>$id,'buyer_id'=>$buyer['guid'],'supplier_count'=>count($recipients)]);
    return $id;
}
function sourcing_buyer_rows(): array {
    $buyer=sourcing_buyer();$all=sourcing_store()['rfqs'];
    $rows=array_values(array_filter($all,static fn($r)=>($r['buyer_guid']??'')===$buyer['guid']));
    usort($rows,static fn($a,$b)=>strcmp((string)$b['created_at'],(string)$a['created_at']));
    return array_slice($rows,0,100);
}
function sourcing_buyer_rfq(string $id): ?array {
    $buyer=sourcing_buyer();
    if(!tenant_valid_id($id))return null;
    $r=sourcing_store()['rfqs'][$id]??null;
    return $r&&($r['buyer_guid']??'')===$buyer['guid']?$r:null;
}
function sourcing_supplier_rows(string $tenantId): array {
    tenant_require('rfq.read',$tenantId);
    $rows=[];
    foreach(sourcing_store()['rfqs'] as $r){
        if(!isset($r['recipients'][$tenantId]))continue;
        $rows[]=sourcing_supplier_projection($r,$tenantId);
    }
    usort($rows,static fn($a,$b)=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));
    return array_slice($rows,0,100);
}
function sourcing_supplier_projection(array $rfq,string $tenantId): array {
    $entry=$rfq['recipients'][$tenantId]??null;
    if(!$entry)throw new RuntimeException('Supplier is not invited to this RFQ.');
    // Do not reveal other invited nursery IDs, quotes or buyer contact by default.
    return [
        'id'=>$rfq['id'],'plant_guid'=>$rfq['plant_guid'],'plant_name'=>$rfq['plant_name'],
        'specification'=>$rfq['specification'],'quantity'=>$rfq['quantity'],
        'destination'=>$rfq['destination'],'notes'=>$rfq['notes'],
        'status'=>$rfq['status'],'invitation'=>$entry,
        'contact_shared'=>$rfq['contact_shared'],
        'buyer_contact'=>($rfq['contact_shared']??false)?$rfq['buyer_contact']:null,
        'updated_at'=>$entry['updated_at'],'created_at'=>$rfq['created_at'],
    ];
}
function sourcing_supplier_rfq(string $id,string $tenantId): ?array {
    tenant_require('rfq.read',$tenantId);
    if(!tenant_valid_id($id))return null;
    $rfq=sourcing_store()['rfqs'][$id]??null;
    return $rfq&&isset($rfq['recipients'][$tenantId])?sourcing_supplier_projection($rfq,$tenantId):null;
}
/** Supplier sales roles submit a documented, buyer-visible indicative offer. */
function sourcing_supplier_respond(string $tenantId,string $id,array $input): void {
    $actor=tenant_require('rfq.quote',$tenantId);
    if(!tenant_valid_id($id))throw new InvalidArgumentException('Invalid request.');
    $decision=(string)($input['decision']??'');
    if(!in_array($decision,['quoted','declined'],true))throw new InvalidArgumentException('Select quote or decline.');
    $priceText=trim((string)($input['unit_price_inr']??''));
    $leadDaysText=trim((string)($input['lead_days']??''));
    $notes=sourcing_require_text($input['message']??'',0,600,'Supplier notes');
    $price=null;$days=null;
    if($decision==='quoted'){
        if(!ctype_digit($priceText)||strlen($priceText)>9||(int)$priceText<1||(int)$priceText>100000000)
            throw new InvalidArgumentException('Indicative unit price must be 1–100,000,000 INR.');
        if(!ctype_digit($leadDaysText)||strlen($leadDaysText)>3||(int)$leadDaysText>365)
            throw new InvalidArgumentException('Lead time must be 0–365 days.');
        $price=(int)$priceText;$days=(int)$leadDaysText;
    }
    $now=gmdate('c');
    sourcing_mutate(static function(array $d)use($tenantId,$id,$decision,$price,$days,$notes,$now,$actor){
        $rfq=$d['rfqs'][$id]??null;
        if(!$rfq||!isset($rfq['recipients'][$tenantId]))throw new RuntimeException('This request is not assigned to your nursery.');
        if(($rfq['status']??'')!=='open')throw new RuntimeException('Buyer closed this request.');
        $supplier=marketplace_supplier_by_id($tenantId);
        if(!$supplier||($supplier['status']??'')!=='verified')throw new RuntimeException('Nursery is not currently verified.');
        $entry=$rfq['recipients'][$tenantId];
        $entry['status']=$decision;
        $entry['quote']=$decision==='quoted'?[
            'unit_price_inr'=>$price,'lead_days'=>$days,'message'=>$notes,
            'total_inr'=>$price*(int)$rfq['quantity'],'updated_at'=>$now,
            'user_id'=>$actor['id'],
        ]:null;
        $entry['quote_version']=(int)($entry['quote_version']??0)+1;
        $entry['updated_at']=$now;
        $rfq['recipients'][$tenantId]=$entry;
        $rfq['updated_at']=$now;
        $d['rfqs'][$id]=$rfq;
        return $d;
    },'supplier.response');
    audit_event('sourcing.rfq.response',['rfq_id'=>$id,'tenant_id'=>$tenantId,'actor_id'=>$actor['id'],'decision'=>$decision]);
}
function sourcing_buyer_close(string $id): void {
    $buyer=sourcing_buyer();
    if(!tenant_valid_id($id))throw new InvalidArgumentException('Invalid request.');
    sourcing_mutate(static function(array $d)use($id,$buyer){
        $r=$d['rfqs'][$id]??null;
        if(!$r||($r['buyer_guid']??'')!==$buyer['guid'])throw new RuntimeException('Sourcing request not found.');
        $r['status']='closed';$r['updated_at']=gmdate('c');$d['rfqs'][$id]=$r;
        return $d;
    },'buyer.close');
    audit_event('sourcing.rfq.close',['rfq_id'=>$id,'buyer_id'=>$buyer['guid']]);
}
/** Platform-only aggregate; never use this method in supplier-facing templates. */
function sourcing_admin_summary(): array {
    require_admin();
    $all=sourcing_store()['rfqs'];$open=0;$quoted=0;$invites=0;
    foreach($all as $r){
        if(($r['status']??'')==='open')$open++;
        foreach($r['recipients']??[] as $entry){$invites++;if(($entry['status']??'')==='quoted')$quoted++;}
    }
    return ['total'=>count($all),'open'=>$open,'invites'=>$invites,'quoted'=>$quoted];
}
