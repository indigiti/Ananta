<?php
declare(strict_types=1);

/**
 * Ananta file-first supplier offer registry.
 * Platform-admin curated pilot only; not a tenant-authenticated supplier portal.
 * Seller offers are enquiries, NEVER additions to the legacy single-seller cart.
 */
function marketplace_registry_file(): string {
    return data_root() . '/private-domain/marketplace/supplier-offers.json';
}
function marketplace_offers_store(bool $refresh=false): array {
    static $cache=null;
    if (!$refresh && is_array($cache)) return $cache;
    $raw=foundation_json_read(marketplace_registry_file(),[]);
    return $cache=[
        'schema_version'=>1,
        'suppliers'=>array_values((array)($raw['suppliers']??[])),
        'offers'=>array_values((array)($raw['offers']??[])),
    ];
}
function marketplace_ensure_dir(): void {
    $dir=dirname(marketplace_registry_file());
    if (!is_dir($dir) && !@mkdir($dir,0750,true) && !is_dir($dir)) {
        throw new RuntimeException('Marketplace private storage is unavailable.');
    }
}
function marketplace_uuid_valid(string $id): bool {
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$id);
}
function marketplace_supplier_by_id(string $id): ?array {
    foreach(marketplace_offers_store()['suppliers'] as $supplier) {
        if (($supplier['id']??'')===$id) return $supplier;
    }
    return null;
}
function marketplace_offer_by_id(string $id): ?array {
    foreach(marketplace_offers_store()['offers'] as $offer) {
        if (($offer['id']??'')===$id) return $offer;
    }
    return null;
}
function marketplace_supplier_save(array $input): string {
    require_admin();
    $id=strtolower(trim((string)($input['id']??'')));
    if ($id!==''&&!marketplace_uuid_valid($id)) throw new InvalidArgumentException('Invalid supplier ID.');
    $name=trim((string)($input['name']??''));
    $city=trim((string)($input['city']??''));
    $phone=preg_replace('/[^0-9]/','',(string)($input['phone']??''))??'';
    $status=trim((string)($input['status']??'draft'));
    if (text_len($name)<2||text_len($name)>120||text_len($city)<2||text_len($city)>100) throw new InvalidArgumentException('Provide a valid supplier name and city.');
    if (!in_array($status,['draft','verified','suspended'],true)) throw new InvalidArgumentException('Invalid supplier status.');
    if ($phone!==''&&(strlen($phone)<10||strlen($phone)>15)) throw new InvalidArgumentException('Use an internationally dialable phone number.');
    if ($status==='verified'&&$phone==='') throw new InvalidArgumentException('Verified supplier needs a public contact phone.');
    marketplace_ensure_dir();
    $savedId=$id!==''?$id:uuidv7();
    foundation_json_mutate(marketplace_registry_file(),function(array $raw)use($savedId,$id,$name,$city,$phone,$status) {
        $suppliers=array_values((array)($raw['suppliers']??[]));$found=false;
        foreach($suppliers as &$row) {
            if(($row['id']??'')===$savedId) {
                $row['name']=$name;$row['city']=$city;$row['phone']=$phone;$row['status']=$status;$row['updated_at']=gmdate('c');$found=true;break;
            }
        }
        unset($row);
        if($id!==''&&!$found)throw new InvalidArgumentException('Supplier not found.');
        if(!$found)$suppliers[]=['id'=>$savedId,'name'=>$name,'city'=>$city,'phone'=>$phone,'status'=>$status,'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
        $raw['schema_version']=1;$raw['suppliers']=$suppliers;$raw['offers']=array_values((array)($raw['offers']??[]));
        return $raw;
    },'marketplace.supplier.save');
    marketplace_offers_store(true);
    audit_event('marketplace.supplier.save',['supplier_id'=>$savedId,'status'=>$status]);
    return $savedId;
}
function marketplace_offer_save(array $input): string {
    require_admin();
    $id=strtolower(trim((string)($input['id']??'')));
    if($id!==''&&!marketplace_uuid_valid($id))throw new InvalidArgumentException('Invalid offer ID.');
    $supplierId=strtolower(trim((string)($input['supplier_id']??'')));
    $supplier=marketplace_supplier_by_id($supplierId);
    if(!$supplier)throw new InvalidArgumentException('Select a registered supplier.');
    $slug=trim((string)($input['product_slug']??''));
    $product=product_by_slug($slug);
    if(!$product||($product['status']??'active')!=='active')throw new InvalidArgumentException('Select a published plant variety.');
    $label=trim((string)($input['label']??''));
    if(text_len($label)<2||text_len($label)>140)throw new InvalidArgumentException('Provide the precise pot size, height or grade.');
    $status=trim((string)($input['status']??'draft'));
    $availability=trim((string)($input['availability']??'unknown'));
    if(!in_array($status,['draft','published','withdrawn'],true))throw new InvalidArgumentException('Invalid offer publication status.');
    if(!in_array($availability,['unknown','reported','confirmed','unavailable'],true))throw new InvalidArgumentException('Invalid availability status.');
    if($status==='published'&&($supplier['status']??'')!=='verified')throw new InvalidArgumentException('Verify the supplier before publishing.');
    $priceText=trim((string)($input['price_inr']??''));
    $quantityText=trim((string)($input['quantity']??''));
    if($priceText!==''&&(!ctype_digit($priceText)||(int)$priceText<1||(int)$priceText>10000000))throw new InvalidArgumentException('Price must be a positive INR amount.');
    if($quantityText!==''&&(!ctype_digit($quantityText)||(int)$quantityText>1000000))throw new InvalidArgumentException('Invalid quantity.');
    $price=$priceText!==''?(int)$priceText:null;
    $quantity=$quantityText!==''?(int)$quantityText:null;
    $evidence=trim((string)($input['evidence_ref']??''));
    if($availability==='confirmed'&&($quantity===null||$quantity<1||text_len($evidence)<8||text_len($evidence)>200)) {
        throw new InvalidArgumentException('Confirmed availability needs positive stock and a private evidence reference (8–200 characters).');
    }
    $savedId=$id!==''?$id:uuidv7();
    $now=gmdate('c');
    marketplace_ensure_dir();
    foundation_json_mutate(marketplace_registry_file(),function(array $raw)use($savedId,$id,$supplierId,$product,$label,$status,$availability,$price,$quantity,$evidence,$now) {
        $offers=array_values((array)($raw['offers']??[]));$found=false;
        $row=['id'=>$savedId,'supplier_id'=>$supplierId,'product_guid'=>(string)$product['guid'],
            'label'=>$label,'status'=>$status,'availability'=>$availability,'price_inr'=>$price,
            'quantity'=>$quantity,'evidence_ref'=>$evidence,'checked_at'=>$now,'updated_at'=>$now];
        foreach($offers as &$existing) {
            if(($existing['id']??'')===$savedId) {
                $row['created_at']=$existing['created_at']??$now;
                $existing=$row;$found=true;break;
            }
        }
        unset($existing);
        if($id!==''&&!$found)throw new InvalidArgumentException('Offer not found.');
        if(!$found){$row['created_at']=$now;$offers[]=$row;}
        $raw['schema_version']=1;$raw['offers']=$offers;$raw['suppliers']=array_values((array)($raw['suppliers']??[]));
        return $raw;
    },'marketplace.offer.save');
    marketplace_offers_store(true);
    audit_event('marketplace.offer.save',['offer_id'=>$savedId,'supplier_id'=>$supplierId,'status'=>$status]);
    return $savedId;
}
function marketplace_offer_public_status(array $offer): array {
    $state=(string)($offer['availability']??'unknown');
    $when=strtotime((string)($offer['checked_at']??''))?:0;
    $fresh=$when>0&&time()-$when<=7*86400&&$when<=time()+300;
    if($state==='confirmed'&&$fresh)return ['label'=>'Recently confirmed','key'=>'confirmed','note'=>'Seller quantity checked recently; reconfirm before ordering.'];
    if($state==='unavailable')return ['label'=>'Not currently available','key'=>'unavailable','note'=>'Ask the nursery about the next batch.'];
    if($state==='reported'&&$fresh)return ['label'=>'Supplier-reported','key'=>'reported','note'=>'Stock reported by the nursery; not reserved or checkout-verified.'];
    return ['label'=>'Check availability','key'=>'unknown','note'=>'Availability is missing or needs reconfirmation.'];
}
function marketplace_offers_for_product(array|string $product): array {
    $guid=is_array($product)?(string)($product['guid']??''):(string)$product;
    if(!marketplace_uuid_valid($guid))return [];
    $store=marketplace_offers_store();
    $verified=[];
    foreach($store['suppliers'] as $s)if(($s['status']??'')==='verified')$verified[(string)$s['id']]=$s;
    $out=[];
    foreach($store['offers'] as $o) {
        if(($o['product_guid']??'')!==$guid||($o['status']??'')!=='published')continue;
        $s=$verified[(string)($o['supplier_id']??'')]??null;
        if(!$s)continue;
        $o['supplier']=['id'=>$s['id'],'name'=>$s['name'],'city'=>$s['city'],'phone'=>$s['phone']];
        $o['public_status']=marketplace_offer_public_status($o);
        unset($o['evidence_ref']);
        $out[]=$o;
    }
    usort($out,static function($a,$b) {
        $rank=['confirmed'=>0,'reported'=>1,'unknown'=>2,'unavailable'=>3];
        return ($rank[$a['public_status']['key']]??4)<=>($rank[$b['public_status']['key']]??4)
            ?:strcmp((string)$a['supplier']['name'],(string)$b['supplier']['name']);
    });
    return $out;
}
function marketplace_offer_contact_url(array $offer,array $product): string {
    $phone=preg_replace('/[^0-9]/','',(string)($offer['supplier']['phone']??''))??'';
    if(strlen($phone)===10)$phone='91'.$phone;
    if(strlen($phone)<11||strlen($phone)>15)return url('/contact');
    $message='Hello, I found '.(string)$product['name'].' ('.(string)$offer['label'].') on Ananta. Could you confirm current price, quantity and delivery availability?';
    return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
}
function marketplace_public_offers_dto(array $product): array {
    $out=[];
    foreach(marketplace_offers_for_product($product) as $o) {
        $out[]=['id'=>$o['id'],'supplier_id'=>$o['supplier']['id'],'supplier_name'=>$o['supplier']['name'],
            'supplier_city'=>$o['supplier']['city'],'variant_label'=>$o['label'],
            'indicative_price_inr'=>$o['price_inr'],'reported_quantity'=>$o['public_status']['key']==='confirmed'?$o['quantity']:null,
            'availability'=>$o['public_status']['key'],'availability_label'=>$o['public_status']['label'],
            'checked_at'=>$o['checked_at'],'sales_mode'=>'enquiry',
            'checkout_enabled'=>false];
    }
    return $out;
}
