<?php
declare(strict_types=1);

/**
 * B6 — supplier fulfilment readiness for one buyer-selected BOQ draft.
 * No legal order acceptance, physical stock reservation, invoice or payment.
 * State changes are serialized inside the B3 encrypted sourcing file.
 */
function sourcing_readiness_statuses(): array {
    return ['awaiting_supplier','supplier_proposed','supplier_declined','buyer_changes_requested','buyer_acknowledged'];
}
function sourcing_readiness_require_draft(array $rfq,string $supplierId): array {
    $po=$rfq['purchase_order']??null;
    if(($rfq['type']??'')!=='boq'||!is_array($po)
        ||($po['supplier_id']??'')!==$supplierId
        ||($rfq['status']??'')!=='buyer_approved_draft'
        ||($po['binding']??true)!==false
        ||!tenant_valid_id((string)($po['id']??'')))
        throw new RuntimeException('No buyer-approved planning draft belongs to this nursery.');
    return $po;
}
function sourcing_readiness_view(array $po): array {
    $r=$po['readiness']??null;
    if(!is_array($r))return [
        'status'=>'awaiting_supplier','version'=>0,'proposal'=>null,
        'events'=>[],'updated_at'=>$po['created_at']??null,
    ];
    return array_replace([
        'status'=>'awaiting_supplier','version'=>0,'proposal'=>null,'events'=>[],
        'updated_at'=>$po['created_at']??null,
    ],$r);
}
function sourcing_readiness_supplier_view(array $po): array {
    $state=sourcing_readiness_view($po);
    // Supplier should not learn a buyer's internal GUID or other audit actor IDs.
    $state['events']=array_map(static function(array $e):array {
        return [
            'action'=>(string)($e['action']??''),
            'at'=>(string)($e['at']??''),
            'version'=>(int)($e['version']??0),
            'note'=>(string)($e['detail']['note']??''),
        ];
    },(array)$state['events']);
    return $state;
}
function sourcing_readiness_version(mixed $value): int {
    if(!is_string($value)||!ctype_digit($value)||strlen($value)>3)
        throw new InvalidArgumentException('Readiness version missing. Reload the page.');
    return (int)$value;
}
function sourcing_readiness_event(array &$state,string $type,string $actorType,string $actorId,string $at,array $detail=[]): void {
    if(count($state['events'])>=25)throw new RuntimeException('Pilot readiness history limit reached; contact the platform before proceeding.');
    $state['events'][]=[
        'id'=>uuidv7(),'action'=>$type,'actor_type'=>$actorType,'actor_id'=>$actorId,
        'at'=>$at,'version'=>$state['version'],
        'detail'=>$detail,
    ];
}
/**
 * Supplier states own availability (no platform verification), pickup/delivery
 * and freight estimate; tax treatment is a declaration, not a GST calculation.
 */
function sourcing_readiness_supplier_submit(string $tenantId,string $rfqId,array $input): void {
    $actor=tenant_require('rfq.quote',$tenantId);
    if(!tenant_valid_id($rfqId))throw new InvalidArgumentException('Invalid BOQ request.');
    $decision=$input['decision']??null;
    if(!in_array($decision,['propose','decline'],true))
        throw new InvalidArgumentException('Choose a readiness proposal or decline.');
    $expected=sourcing_readiness_version($input['readiness_version']??null);
    $comment=sourcing_require_text($input['comment']??'',0,500,'Supplier note');
    if($decision==='decline'&&text_len($comment)<5)
        throw new InvalidArgumentException('Give a short reason for declining.');
    $proposed=null;
    if($decision==='propose'){
        $leadText=$input['dispatch_days']??null;
        $freightText=$input['freight_inr']??null;
        if(!is_string($leadText)||!ctype_digit($leadText)||strlen($leadText)>3||(int)$leadText>365)
            throw new InvalidArgumentException('Dispatch window must be 0–365 days.');
        if(!is_string($freightText)||!ctype_digit($freightText)||strlen($freightText)>8||(int)$freightText>2000000)
            throw new InvalidArgumentException('Freight estimate must be 0–2,000,000 INR.');
        $mode=$input['delivery_mode']??null;
        if(!in_array($mode,['supplier_delivery','buyer_pickup','third_party_transport'],true))
            throw new InvalidArgumentException('Choose delivery responsibility.');
        $tax=$input['tax_treatment']??null;
        if(!in_array($tax,['supplier_states_included','supplier_states_extra','unconfirmed'],true))
            throw new InvalidArgumentException('Choose how tax treatment is described.');
        $proposed=[
            'dispatch_days'=>(int)$leadText,'freight_inr'=>(int)$freightText,
            'delivery_mode'=>$mode,'tax_treatment'=>$tax,'comment'=>$comment,
            'line_availability'=>[],
            'stock_reported_at'=>gmdate('c'),
            'tax_legal_review_required'=>true,'binding'=>false,
        ];
        if(!is_array($input['available_quantities']??null))
            throw new InvalidArgumentException('Reconfirm all BOQ quantities.');
    }
    $now=gmdate('c');
    sourcing_mutate(static function(array $db)use($tenantId,$rfqId,$decision,$expected,$actor,$now,$input,&$proposed,$comment){
        $rfq=$db['rfqs'][$rfqId]??null;
        if(!$rfq||!isset($rfq['recipients'][$tenantId]))
            throw new RuntimeException('RFQ is not assigned to this nursery.');
        $po=sourcing_readiness_require_draft($rfq,$tenantId);
        $nursery=marketplace_supplier_by_id($tenantId);
        if(!$nursery||($nursery['status']??'')!=='verified')
            throw new RuntimeException('Nursery verification is no longer active.');
        $state=sourcing_readiness_view($po);
        if((int)$state['version']!==$expected)
            throw new RuntimeException('Readiness changed. Reload the page before responding.');
        if(!in_array($state['status'],['awaiting_supplier','buyer_changes_requested','supplier_proposed'],true))
            throw new RuntimeException('Buyer has acknowledged or supplier has declined this planning draft.');
        if($decision==='propose'){
            $lines=$po['lines']??[];
            $values=$input['available_quantities'];
            if(count($lines)<1||count($lines)>30||count($values)!==count($lines))
                throw new InvalidArgumentException('Report stock for every BOQ line.');
            $lineAvailability=[];$sufficient=true;
            foreach($lines as $line){
                $id=(string)$line['id'];
                $raw=$values[$id]??null;
                if(!is_string($raw)||!ctype_digit($raw)||strlen($raw)>5||(int)$raw>50000)
                    throw new InvalidArgumentException('Invalid supplier-reported quantity on a BOQ line.');
                $available=(int)$raw;
                $required=(int)$line['quantity'];
                $lineAvailability[$id]=[
                    'required'=>$required,'reported_available'=>$available,
                    'sufficient'=>$available>=$required,
                ];
                if($available<$required)$sufficient=false;
            }
            $proposed['line_availability']=$lineAvailability;
            $proposed['all_lines_reported_sufficient']=$sufficient;
            $proposed['reported_by_user_id']=$actor['id'];
            $proposed['submitted_at']=$now;
            $state['proposal']=$proposed;
            $state['status']='supplier_proposed';
        }else{
            $state['status']='supplier_declined';
            $state['proposal']=null;
        }
        $state['version']++;
        $state['updated_at']=$now;
        sourcing_readiness_event($state,$decision==='propose'?'supplier_proposed':'supplier_declined',
            'supplier',$actor['id'],$now,['note'=>$comment,'all_lines_sufficient'=>$proposed['all_lines_reported_sufficient']??false]);
        $po['readiness']=$state;
        $rfq['purchase_order']=$po;
        $rfq['updated_at']=$now;
        $db['rfqs'][$rfqId]=$rfq;
        return $db;
    },'readiness.supplier');
    audit_event('sourcing.readiness.supplier',[
        'rfq_id'=>$rfqId,'tenant_id'=>$tenantId,'actor_id'=>$actor['id'],'decision'=>$decision,
    ]);
}
/** Buyer can request correction or acknowledge terms FOR PLANNING ONLY. */
function sourcing_readiness_buyer_decide(string $rfqId,array $input): void {
    $buyer=sourcing_buyer();
    if(!tenant_valid_id($rfqId))throw new InvalidArgumentException('Invalid BOQ request.');
    $decision=$input['decision']??null;
    if(!in_array($decision,['request_changes','acknowledge_planning'],true))
        throw new InvalidArgumentException('Choose acknowledgement or request changes.');
    $confirmation=$input['confirmation']??null;
    if($decision==='acknowledge_planning'&&$confirmation!=='nonbinding_planning_only')
        throw new InvalidArgumentException('Explicit non-binding acknowledgement is required.');
    $note=sourcing_require_text($input['note']??'',0,500,'Buyer note');
    if($decision==='request_changes'&&text_len($note)<5)
        throw new InvalidArgumentException('Explain the requested change.');
    $expected=sourcing_readiness_version($input['readiness_version']??null);
    $now=gmdate('c');
    sourcing_mutate(static function(array $db)use($rfqId,$buyer,$decision,$confirmation,$expected,$note,$now){
        $rfq=$db['rfqs'][$rfqId]??null;
        if(!$rfq||($rfq['buyer_guid']??'')!==$buyer['guid']||($rfq['type']??'')!=='boq')
            throw new RuntimeException('Buyer BOQ not found.');
        $po=$rfq['purchase_order']??null;
        if(!is_array($po)||($po['binding']??true)!==false||($rfq['status']??'')!=='buyer_approved_draft')
            throw new RuntimeException('Approved planning draft is unavailable.');
        $supplier=marketplace_supplier_by_id((string)($po['supplier_id']??''));
        if(!$supplier||($supplier['status']??'')!=='verified')
            throw new RuntimeException('The supplier is no longer verified.');
        $state=sourcing_readiness_view($po);
        if($state['version']!==$expected)
            throw new RuntimeException('Supplier readiness changed. Reload and review its terms.');
        if($state['status']!=='supplier_proposed'||!is_array($state['proposal']))
            throw new RuntimeException('No current supplier readiness proposal to review.');
        if($decision==='acknowledge_planning'&&!$state['proposal']['all_lines_reported_sufficient'])
            throw new RuntimeException('The supplier reported insufficient stock. Request changes instead.');
        $state['status']=$decision==='acknowledge_planning'?'buyer_acknowledged':'buyer_changes_requested';
        $state['version']++;
        $state['updated_at']=$now;
        sourcing_readiness_event($state,$state['status'],'buyer',$buyer['guid'],$now,
            ['note'=>$note,'nonbinding'=>true]);
        $po['readiness']=$state;
        $rfq['purchase_order']=$po;
        $rfq['updated_at']=$now;
        $db['rfqs'][$rfqId]=$rfq;
        return $db;
    },'readiness.buyer');
    audit_event('sourcing.readiness.buyer',['rfq_id'=>$rfqId,'buyer_id'=>$buyer['guid'],'decision'=>$decision]);
}
