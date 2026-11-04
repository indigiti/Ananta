<?php
declare(strict_types=1);

/**
 * B4: buyer-reviewed multi-line BOQs, supplier revision history and
 * non-binding buyer-approved PO drafts, backed by encrypted RFQ persistence.
 *
 * Product matching is deterministic and verified against the catalogue.
 * No LLM output, file upload, quote or PO may create live inventory/payment.
 */
function sourcing_boq_headers(): array { return ['product_slug','quantity','specification']; }

function sourcing_boq_parse_csv(string $path): array {
    sourcing_buyer();
    if(!is_file($path)||is_link($path))throw new InvalidArgumentException('Upload a CSV file.');
    $size=filesize($path);
    if($size===false||$size<15||$size>131072)throw new InvalidArgumentException('CSV must be 1–128 KB.');
    $fh=fopen($path,'rb');
    if(!$fh)throw new RuntimeException('Cannot read CSV.');
    $rows=[];$unique=[];
    try{
        $headers=fgetcsv($fh,4096,',','"','');
        if(!is_array($headers))throw new InvalidArgumentException('CSV header missing.');
        $headers[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)($headers[0]??''));
        if(array_map(static fn($x)=>trim((string)$x),$headers)!==sourcing_boq_headers())
            throw new InvalidArgumentException('Columns must be product_slug,quantity,specification.');
        $line=1;
        while(($values=fgetcsv($fh,4096,',','"',''))!==false){
            $line++;
            if($line>31)throw new InvalidArgumentException('Maximum 30 BOQ lines.');
            if(count($values)===1&&trim((string)$values[0])==='')continue;
            if(count($values)!==3)throw new InvalidArgumentException('Row '.$line.': expected three fields.');
            [$slug,$q,$spec]=array_map(static fn($x)=>trim((string)$x),$values);
            foreach([$slug,$q,$spec] as $v){
                if(strlen($v)>500||preg_match('/[\x00-\x1F\x7F]/',$v)
                    ||(function_exists('mb_check_encoding')&&!mb_check_encoding($v,'UTF-8')))
                    throw new InvalidArgumentException('Row '.$line.': invalid text.');
            }
            $plant=product_by_slug($slug);
            if(!$plant||($plant['status']??'')!=='active')throw new InvalidArgumentException('Row '.$line.': plant is not in the current catalogue.');
            if(!ctype_digit($q)||strlen($q)>5||(int)$q<1||(int)$q>50000)throw new InvalidArgumentException('Row '.$line.': quantity must be 1–50,000.');
            $spec=sourcing_require_text($spec,2,180,'Plant specification');
            $key=(string)$plant['guid'].'|'.text_lower($spec);
            if(isset($unique[$key]))throw new InvalidArgumentException('Row '.$line.': duplicate plant and grade.');
            $unique[$key]=true;
            $rows[]=['id'=>uuidv7(),'product_guid'=>$plant['guid'],'product_slug'=>$plant['slug'],
                'plant_name'=>$plant['name'],'quantity'=>(int)$q,'specification'=>$spec];
        }
        if(!$rows)throw new InvalidArgumentException('No plant lines found.');
    }finally{fclose($fh);}
    return $rows;
}
function sourcing_boq_stage(string $csv): array {
    $buyer=sourcing_buyer();
    $lines=sourcing_boq_parse_csv($csv);
    $_SESSION['sourcing_boq_preview']=[
        'id'=>uuidv7(),'token'=>bin2hex(random_bytes(24)),'buyer_guid'=>$buyer['guid'],
        'expires_at'=>time()+900,'lines'=>$lines,
    ];
    return $_SESSION['sourcing_boq_preview'];
}
function sourcing_boq_pending(): ?array {
    $buyer=sourcing_buyer();
    $pending=$_SESSION['sourcing_boq_preview']??null;
    if(!is_array($pending)||($pending['buyer_guid']??'')!==$buyer['guid']||($pending['expires_at']??0)<time()){
        unset($_SESSION['sourcing_boq_preview']);return null;
    }
    return $pending;
}
function sourcing_boq_submit(array $input): string {
    $buyer=sourcing_buyer();
    $pending=sourcing_boq_pending();
    if(!$pending||!isset($input['preview_token'])||!is_string($input['preview_token'])
        ||!hash_equals((string)$pending['token'],$input['preview_token']))
        throw new RuntimeException('BOQ preview expired. Upload and review the file again.');
    if(($input['consent']??'')!=='yes')throw new InvalidArgumentException('Confirm sharing this requirement with selected nurseries.');
    $destination=sourcing_require_text($input['destination']??'',2,140,'Destination');
    $notes=sourcing_require_text($input['notes']??'',0,600,'Additional notes');
    $supplierIds=sourcing_parse_recipients($input['supplier_ids']??null);
    $shareContact=($input['share_contact']??'')==='yes';
    $contact=$shareContact?['name'=>(string)$buyer['name'],'email'=>(string)$buyer['email'],'phone'=>(string)($buyer['phone']??'')]:null;
    $rate=api_rate_limit('sourcing-boq:'.$buyer['guid'],5,3600);
    if(!$rate['allowed'])throw new RuntimeException('Sourcing request limit reached. Try later.');
    $lines=$pending['lines'];
    // The server-side preview is a validated catalogue snapshot; revalidate
    // immediately before committing so withdrawn plants cannot be ordered.
    if(count($lines)<1||count($lines)>30)throw new RuntimeException('Invalid BOQ preview.');
    $totalQuantity=0;
    foreach($lines as $line){
        $current=product_by_guid((string)$line['product_guid']);
        if(!$current||($current['status']??'')!=='active'||($current['slug']??'')!==$line['product_slug'])
            throw new RuntimeException('Catalogue changed. Please preview the BOQ again.');
        $totalQuantity+=(int)$line['quantity'];
    }
    $origin=(string)($pending['origin']??'csv');
    $sourceHash=(string)($pending['source_sha256']??'');
    $aiAssisted=($pending['ai_assisted']??false)===true;
    $now=gmdate('c');$id=uuidv7();$batchId=(string)$pending['id'];$resultId='';
    sourcing_mutate(static function(array $store)use($buyer,$supplierIds,$lines,$now,$id,$batchId,$destination,$notes,$shareContact,$contact,$totalQuantity,$origin,$sourceHash,$aiAssisted,&$resultId){
        foreach($store['rfqs'] as $existing){
            if(($existing['buyer_guid']??'')===$buyer['guid']&&($existing['batch_id']??'')===$batchId){
                $resultId=$existing['id'];return $store;
            }
        }
        if(count($store['rfqs'])>=1500)throw new RuntimeException('Sourcing pilot capacity reached.');
        $valid=array_column(sourcing_verified_suppliers(),null,'id');
        $recipients=[];
        foreach($supplierIds as $supplierId){
            if(!isset($valid[$supplierId]))throw new RuntimeException('Supplier verification changed. Please retry.');
            $recipients[$supplierId]=['status'=>'invited','quote'=>null,'quote_version'=>0,'revisions'=>[],'updated_at'=>$now];
        }
        $store['rfqs'][$id]=[
            'id'=>$id,'batch_id'=>$batchId,'buyer_guid'=>$buyer['guid'],'type'=>'boq',
            'source_origin'=>$origin,'source_sha256'=>$sourceHash,'ai_assisted'=>$aiAssisted,
            'plant_guid'=>$lines[0]['product_guid'],'plant_name'=>'Multi-plant BOQ ('.count($lines).' lines)',
            'specification'=>'Buyer-reviewed catalogue BOQ','quantity'=>$totalQuantity,'lines'=>$lines,
            'destination'=>$destination,'notes'=>$notes,'contact_shared'=>$shareContact,'buyer_contact'=>$contact,
            'status'=>'open','recipients'=>$recipients,'purchase_order'=>null,
            'created_at'=>$now,'updated_at'=>$now,
        ];
        $resultId=$id;
        return $store;
    },'boq.create');
    unset($_SESSION['sourcing_boq_preview']);
    audit_event('sourcing.boq.create',['rfq_id'=>$resultId,'buyer_id'=>$buyer['guid'],'lines'=>count($lines),'suppliers'=>count($supplierIds)]);
    return $resultId;
}
/** Each supplier prices every line; quote revisions are immutable snapshots. */
function sourcing_boq_quote(string $tenantId,string $id,array $input): void {
    $actor=tenant_require('rfq.quote',$tenantId);
    if(!tenant_valid_id($id))throw new InvalidArgumentException('Invalid RFQ.');
    $decision=(string)($input['decision']??'quoted');
    if(!in_array($decision,['quoted','declined'],true))throw new InvalidArgumentException('Invalid decision.');
    $notes=sourcing_require_text($input['message']??'',0,600,'Supplier notes');
    $daysText=trim((string)($input['lead_days']??''));
    $days=null;
    if($decision==='quoted'){
        if(!ctype_digit($daysText)||strlen($daysText)>3||(int)$daysText>365)throw new InvalidArgumentException('Lead time must be 0–365 days.');
        $days=(int)$daysText;
    }
    $submitted=$input['line_prices']??null;
    if($decision==='quoted'&&!is_array($submitted))throw new InvalidArgumentException('Price each BOQ line.');
    $expected=$input['quote_version']??null;
    if(!is_string($expected)||!ctype_digit($expected)||strlen($expected)>3)
        throw new InvalidArgumentException('Quote version missing. Reload the request.');
    $now=gmdate('c');
    sourcing_mutate(static function(array $store)use($tenantId,$id,$decision,$notes,$days,$submitted,$expected,$actor,$now){
        $rfq=$store['rfqs'][$id]??null;
        if(!$rfq||($rfq['type']??'')!=='boq'||!isset($rfq['recipients'][$tenantId]))
            throw new RuntimeException('Request is not assigned to this nursery.');
        if(($rfq['status']??'')!=='open')throw new RuntimeException('Buyer closed or approved this RFQ.');
        $supplier=marketplace_supplier_by_id($tenantId);
        if(!$supplier||($supplier['status']??'')!=='verified')throw new RuntimeException('Nursery verification is inactive.');
        $entry=$rfq['recipients'][$tenantId];
        $version=(int)($entry['quote_version']??0);
        if((int)$expected!==$version)throw new RuntimeException('Quotation changed. Reload and review the latest version.');
        if($version>=10)throw new RuntimeException('Pilot allows a maximum of ten revisions per nursery.');
        $linePrices=[];$total=0;
        if($decision==='quoted'){
            $lines=$rfq['lines']??[];
            if(count($lines)<1||count($lines)>30||count($submitted)!==count($lines))
                throw new InvalidArgumentException('Every line must have one price.');
            foreach($lines as $line){
                $lineId=(string)$line['id'];
                $raw=$submitted[$lineId]??null;
                if(!is_string($raw)||!ctype_digit($raw)||strlen($raw)>9||(int)$raw<1||(int)$raw>100000000)
                    throw new InvalidArgumentException('Every BOQ line needs a valid unit price (1–100,000,000 INR).');
                $unit=(int)$raw;$lineTotal=$unit*(int)$line['quantity'];$total+=$lineTotal;
                $linePrices[$lineId]=['unit_price_inr'=>$unit,'line_total_inr'=>$lineTotal];
            }
        }
        $snapshot=[
            'version'=>$version+1,'decision'=>$decision,'lead_days'=>$days,'message'=>$notes,
            'line_prices'=>$linePrices,'total_inr'=>$decision==='quoted'?$total:null,
            'updated_at'=>$now,'user_id'=>$actor['id'],
        ];
        $entry['revisions'][]=$snapshot;
        $entry['status']=$decision;
        $entry['quote']=$decision==='quoted'?$snapshot:null;
        $entry['quote_version']=$version+1;
        $entry['updated_at']=$now;
        $rfq['recipients'][$tenantId]=$entry;
        $rfq['updated_at']=$now;
        $store['rfqs'][$id]=$rfq;
        return $store;
    },'boq.quote');
    audit_event('sourcing.boq.quote',['rfq_id'=>$id,'tenant_id'=>$tenantId,'actor_id'=>$actor['id'],'decision'=>$decision]);
}
/**
 * Buyer approves a particular revision and freezes a non-binding PO draft.
 * It is NOT supplier-accepted, legally issued, invoiced or payment-enabled.
 */
function sourcing_boq_approve_draft(string $id,string $supplierId,int $version,string $confirmation): string {
    $buyer=sourcing_buyer();
    if(!tenant_valid_id($id)||!tenant_valid_id($supplierId)||$confirmation!=='approve_nonbinding_draft')
        throw new InvalidArgumentException('Explicit draft-only approval is required.');
    $poId='';
    sourcing_mutate(static function(array $store)use($id,$supplierId,$version,$buyer,&$poId){
        $rfq=$store['rfqs'][$id]??null;
        if(!$rfq||($rfq['buyer_guid']??'')!==$buyer['guid']||($rfq['type']??'')!=='boq')
            throw new RuntimeException('BOQ request not found.');
        $existing=$rfq['purchase_order']??null;
        if($existing){
            if(($existing['supplier_id']??'')===$supplierId&&($existing['quote_version']??0)===$version){
                $poId=$existing['id'];return $store;
            }
            throw new RuntimeException('This BOQ already has a buyer-approved draft.');
        }
        if(($rfq['status']??'')!=='open')throw new RuntimeException('Request is not available for approval.');
        $entry=$rfq['recipients'][$supplierId]??null;
        $quote=$entry['quote']??null;
        if(!$entry||($entry['status']??'')!=='quoted'||!is_array($quote)
            ||(int)($entry['quote_version']??-1)!==$version||($quote['version']??null)!==$version)
            throw new RuntimeException('Quotation changed or is unavailable. Refresh before approving.');
        $supplier=marketplace_supplier_by_id($supplierId);
        if(!$supplier||($supplier['status']??'')!=='verified')throw new RuntimeException('Nursery is no longer verified.');
        $poId=uuidv7();$now=gmdate('c');
        $rfq['purchase_order']=[
            'id'=>$poId,'supplier_id'=>$supplierId,'supplier_name'=>$supplier['name'],
            'quote_version'=>$version,'lines'=>$rfq['lines'],
            'line_prices'=>$quote['line_prices'],'total_inr'=>$quote['total_inr'],
            'lead_days'=>$quote['lead_days'],'status'=>'buyer_approved_draft',
            'binding'=>false,'tax_freight_payment_unconfirmed'=>true,
            'created_at'=>$now,'buyer_guid'=>$buyer['guid'],
        ];
        $rfq['status']='buyer_approved_draft';$rfq['updated_at']=$now;
        $store['rfqs'][$id]=$rfq;
        return $store;
    },'boq.approve_draft');
    audit_event('sourcing.boq.approve_draft',['rfq_id'=>$id,'po_id'=>$poId,'buyer_id'=>$buyer['guid'],'supplier_id'=>$supplierId,'quote_version'=>$version]);
    return $poId;
}
