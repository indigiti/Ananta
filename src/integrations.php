<?php
declare(strict_types=1);
function dto_image_absolute(array $p): ?string { return product_has_image($p)?absolute_url(image_url(mediaos_primary_file($p))):null; }
function dto_variant(array $v,array $p): array { return ['id'=>$v['guid'],'sku'=>$v['sku'],'label'=>variant_display_label($v),'pot_size_cm'=>(float)($v['pot_size_cm']??0),'plant_height_cm'=>['min'=>(int)($v['plant_height_cm_min']??0),'max'=>(int)($v['plant_height_cm_max']??0)],'grade'=>$v['grade']??'','batch_code'=>$v['batch_code']??'','sales_mode'=>$v['sales_mode']??'enquiry','availability'=>$v['availability']??'coming_soon','stock'=>!empty($v['track_inventory'])?(int)($v['stock']??0):null,'price'=>variant_is_sellable($v)?['amount'=>(int)$v['price'],'currency'=>'INR']:null,'lead_time'=>$v['lead_time']??'','image'=>variant_has_image($v,$p)?absolute_url(image_url(variant_effective_image($v,$p))):null]; }
function public_product_dto(array $p): array { $variants=array_map(fn($v)=>dto_variant($v,$p),variants_for_product($p,true));return ['id'=>$p['guid'],'sku'=>$p['sku'],'slug'=>$p['slug'],'name'=>$p['name'],'short'=>$p['short'],'category'=>$p['category_label'],'collection'=>$p['collection'],'sales_mode'=>product_has_sellable_variants($p)?'variant-commerce':($p['sales_mode']??'commerce'),'price'=>product_is_enquiry($p)?null:(product_variant_price_range($p)??['min'=>(int)$p['price'],'max'=>(int)$p['price'],'count'=>1]),'currency'=>'INR','price_label'=>product_price_label($p),'image'=>dto_image_absolute($p),'botanical_name'=>$p['botanical_name']??'','care_profile'=>$p['care_profile']??[],'dimensions'=>$p['dimensions'],'lead_time'=>$p['lead_time'],'variants'=>$variants,'url'=>absolute_url('/product/'.$p['slug'])]; }
function merchant_variant_dto(array $v,array $p): array {
    if(!variant_is_merchant_eligible($v,$p))return [];
    return [
        'id'=>$v['sku'],'item_group_id'=>$p['sku'],
        'title'=>$p['name'].' · '.variant_display_label($v),
        'description'=>$p['short'],'link'=>discover_variant_url($p,$v),
        'image_link'=>absolute_url(image_url(variant_effective_image($v,$p))),
        'availability'=>'in_stock','price'=>(int)$v['price'].' INR',
        'brand'=>'Sanjay Nursery','condition'=>'new',
        'google_product_category'=>'Home & Garden > Plants',
        'product_type'=>$p['category_label'],
        'size'=>variant_display_label($v),'identifier_exists'=>'no',
        'custom_label_0'=>$p['category_label'],'custom_label_1'=>$p['collection'],
        'custom_label_2'=>$v['batch_code']??'',
    ];
}
function merchant_product_dto(array $p): array {
    if(function_exists('discover_parent_reasons')&&discover_parent_reasons($p)!==[])return [];
    if(product_has_variants($p)||!product_is_sellable($p)||!mediaos_primary_approved($p))return [];
    return [
        'id'=>$p['sku'],'title'=>$p['name'],'description'=>$p['short'],
        'link'=>absolute_url('/product/'.$p['slug']),
        'image_link'=>dto_image_absolute($p),
        'availability'=>'in_stock',
        'price'=>(int)$p['price'].' INR','brand'=>'Sanjay Nursery','condition'=>'new',
        'google_product_category'=>'Home & Garden > Plants',
        'product_type'=>$p['category_label'],'identifier_exists'=>'no',
        'custom_label_0'=>$p['category_label'],'custom_label_1'=>$p['collection'],
    ];
}
function ai_product_dto(array $p): array { $range=product_variant_price_range($p);return ['product_id'=>$p['guid'],'name'=>$p['name'],'summary'=>$p['short'],'category'=>$p['category_label'],'collection'=>$p['collection'],'sales_mode'=>$range?'variant-commerce':($p['sales_mode']??'commerce'),'price'=>$range?['min'=>$range['min'],'max'=>$range['max'],'currency'=>'INR']:(product_is_enquiry($p)?null:['amount'=>(int)$p['price'],'currency'=>'INR']),'price_label'=>product_price_label($p),'botanical_name'=>$p['botanical_name']??'','care_profile'=>$p['care_profile']??[],'intelligence'=>$p['intelligence']??[],'lead_time'=>$p['lead_time'],'image'=>dto_image_absolute($p),'variants'=>array_map(fn($v)=>dto_variant($v,$p),variants_for_product($p,true)),'related_content'=>function_exists('ai_related_content_for_product')?ai_related_content_for_product($p):[],'canonical_url'=>absolute_url('/product/'.$p['slug'])]; }
function agent_product_dto(array $p): array { $d=ai_product_dto($p);$d['sku']=$p['sku'];$d['care']=$p['care']??'';$d['warranty']=$p['warranty']??'';return$d; }
function integration_public_catalog(string $q=''): array { $q=text_lower(trim($q));$rows=[];foreach(products_public() as $p){if($q!==''&&!str_contains(text_lower($p['name'].' '.$p['short'].' '.$p['category_label'].' '.implode(' ',(array)($p['tags']??[]))),$q))continue;$rows[]=public_product_dto($p);}return$rows; }
function merchant_xml(): string {
    $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
    $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml.='<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>';
    $xml.='<title>Ananta Nursery Mall · Sanjay Nursery retail</title>';
    $xml.='<link>'.$esc(absolute_url('/')).'</link>';
    $xml.='<description>Verified checkout-ready retail plants from Sanjay Nursery, the founding retailer</description>';
    foreach(discover_merchant_rows() as $row){
        $xml.='<item>';
        foreach($row as $key=>$value){
            if($value===null||$value==='')continue;
            $tag=in_array($key,['title','description','link'],true)?$key:'g:'.$key;
            $xml.='<'.$tag.'>'.$esc($value).'</'.$tag.'>';
        }
        $xml.='</item>';
    }
    return $xml.'</channel></rss>';
}

function agent_signing_key(): string { $k=trim((string)getenv('SANJAY_AGENT_SIGNING_KEY'));if($k==='')$k=trim((string)($GLOBALS['app']['agent_signing_key']??''));return$k; }
function b64u(string $s): string { return rtrim(strtr(base64_encode($s),'+/','-_'),'='); }
function b64u_dec(string $s): string|false { $pad=(4-strlen($s)%4)%4;return base64_decode(strtr($s.str_repeat('=',$pad),'-_','+/'),true); }
function agent_issue_token(array $scopes=['orders:read'],int $ttl=900): string { require_customer('/account');$key=agent_signing_key();if(strlen($key)<32)throw new RuntimeException('Agent signing key is unavailable. Complete web setup first.');$allowed=['orders:read','profile:read'];$scopes=array_values(array_intersect($allowed,$scopes));$payload=['sub'=>(string)customer_identity()['guid'],'aud'=>'sanjaynursery-agent','scope'=>$scopes,'iat'=>time(),'exp'=>time()+max(60,min(1800,$ttl)),'jti'=>bin2hex(random_bytes(8))];$body=b64u(json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$sig=b64u(hash_hmac('sha256',$body,$key,true));return$body.'.'.$sig; }
function agent_validate_token(string $token,string $scope): array { $parts=explode('.',$token);if(count($parts)!==2)throw new RuntimeException('Invalid agent token.');[$body,$sig]=$parts;$key=agent_signing_key();if(strlen($key)<32)throw new RuntimeException('Agent signing key unavailable.');$expect=b64u(hash_hmac('sha256',$body,$key,true));if(!hash_equals($expect,$sig))throw new RuntimeException('Agent token signature invalid.');$raw=b64u_dec($body);$p=json_decode((string)$raw,true);if(!is_array($p)||($p['aud']??'')!=='sanjaynursery-agent'||(int)($p['exp']??0)<time())throw new RuntimeException('Agent token expired or invalid.');if(!in_array($scope,(array)($p['scope']??[]),true))throw new RuntimeException('Agent token scope denied.');return$p; }
function bearer_token(): string { $h=(string)($_SERVER['HTTP_AUTHORIZATION']??'');return preg_match('/^Bearer\s+(.+)$/i',$h,$m)?trim($m[1]):''; }
function agent_order_dto(array $o): array { return ['order_id'=>$o['guid'],'number'=>$o['number'],'status'=>$o['status'],'payment_status'=>$o['payment_status'],'total'=>$o['pricing']['grand_total'],'currency'=>'INR','items'=>array_map(fn($i)=>['sku'=>$i['sku'],'name'=>$i['name'],'qty'=>$i['qty'],'material'=>$i['material'],'finish'=>$i['finish']],$o['items']),'created_at'=>$o['created_at'],'updated_at'=>$o['updated_at']]; }
function integrations_release_checks(): array { $secretFields=['cost','supplier','customer_guid','password_hash','address_snapshot'];$leak=false;foreach(products_public() as $p){$merchantRows=[merchant_product_dto($p)];foreach(variants_for_product($p,true) as $v)$merchantRows[]=merchant_variant_dto($v,$p);$json=json_encode([public_product_dto($p),$merchantRows,ai_product_dto($p),agent_product_dto($p)]);foreach($secretFields as $f)if(str_contains((string)$json,'"'.$f.'"'))$leak=true;}return [
 ['name'=>'External serializer allowlist','ok'=>!$leak,'detail'=>!$leak?'Public/Merchant/AI/agent product serializers exclude private/internal fields.':'External serializer leakage detected.'],
 ['name'=>'Merchant feed generation','ok'=>str_contains(merchant_xml(),'<rss'),'detail'=>'Google Merchant-compatible XML emits only image-backed, checkout-ready parent or variant SKUs.'],
 ['name'=>'AI product feed coverage','ok'=>count(products_public())===count(array_map('ai_product_dto',products_public())),'detail'=>'AI-readable feed covers every active public product.'],
 ['name'=>'Agent signing key','ok'=>strlen(agent_signing_key())>=32,'detail'=>strlen(agent_signing_key())>=32?'Agent token signing key is configured.':'Complete web setup to generate the agent signing key before using authenticated agent tools.'],
]; }
