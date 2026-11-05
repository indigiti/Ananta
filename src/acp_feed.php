<?php
declare(strict_types=1);

/**
 * ACP.1 — OpenAI native product feed, generated ONLY from B8-G merchant-eligible
 * stock. Exact spec reference: developers.openai.com/commerce/specs/file-upload/products
 * Never claim feed acceptance, eligibility for India, or checkout activation.
 */
function acp_version(): string { return '2026-04-17'; }
function acp_checkout_live_enabled(): bool {
    // This flag cannot be turned on by a dashboard operator. External legal,
    // PSP and SQL stock-reservation certification are separate release gates.
    return false;
}
function acp_feed_columns(): array {
    return ['item_id','title','description','url','brand','seller_name','seller_url',
        'image_url','price','availability','target_countries','store_country',
        'is_eligible_search','is_eligible_checkout','gtin','mpn','group_id',
        'product_category','condition'];
}
function acp_feed_seller(): string {
    $policy=merchant_completion_policy();
    return trim((string)($policy['seller_name']??''));
}
function acp_feed_native_rows(): array {
    $out=[];$seller=acp_feed_seller();
    if($seller==='')return [];
    $storefront=rtrim(absolute_url('/'),'/').'/';
    // This is a *partner submission candidate*, never auto-submitted or public.
    foreach(discover_merchant_rows() as $m){
        $row=['item_id'=>(string)($m['id']??''),
            'title'=>(string)($m['title']??''),
            'description'=>(string)($m['description']??''),
            'url'=>(string)($m['link']??''),
            'brand'=>(string)($m['brand']??''),
            'seller_name'=>$seller,
            'seller_url'=>$storefront,
            'image_url'=>(string)($m['image_link']??''),
            'price'=>(string)($m['price']??''),
            'availability'=>(string)($m['availability']??''),
            'target_countries'=>'IN','store_country'=>'IN',
            'is_eligible_search'=>'true',
            // ChatGPT Instant Checkout requires partner and PSP certification;
            // advertising this catalogue as buyable would be deceptive.
            'is_eligible_checkout'=>'false',
            'gtin'=>(string)($m['gtin']??''),
            'mpn'=>(string)($m['mpn']??''),
            'group_id'=>(string)($m['item_group_id']??''),
            'product_category'=>(string)($m['product_type']??''),
            'condition'=>'new'];
        if(acp_feed_validate_row($row)!==[])
            continue; // Record reasons in ACP readiness diagnostics.
        $out[]=$row;
    }
    return $out;
}
function acp_feed_validate_row(array $row): array {
    $errors=[];
    foreach(['item_id','title','description','url','brand','seller_name','seller_url',
        'image_url','price','availability','target_countries','is_eligible_search',
        'is_eligible_checkout'] as $field) {
        if(trim((string)($row[$field]??''))==='')$errors[]='missing_'.$field;
    }
    if(!preg_match('/^[A-Za-z0-9_.:-]{1,100}$/D',(string)($row['item_id']??'')))
        $errors[]='invalid_item_id';
    foreach(['title'=>150,'description'=>5000,'brand'=>70,'seller_name'=>150] as $key=>$max){
        if(text_len((string)($row[$key]??''))>$max)$errors[]='long_'.$key;
    }
    foreach(['url','image_url','seller_url'] as $key){
        $url=(string)($row[$key]??'');
        if(!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https'
          ||parse_url($url,PHP_URL_USER)!==null||parse_url($url,PHP_URL_PASS)!==null)
            $errors[]='invalid_'.$key;
    }
    $host=strtolower((string)parse_url((string)($row['seller_url']??''),PHP_URL_HOST));
    $landing=strtolower((string)parse_url((string)($row['url']??''),PHP_URL_HOST));
    if($host===''||$host!==$landing)$errors[]='foreign_landing_domain';
    if(!preg_match('/^[1-9][0-9]*(?:\\.[0-9]{1,2})? INR$/D',(string)($row['price']??'')))
        $errors[]='invalid_price';
    if(($row['availability']??'')!=='in_stock')$errors[]='not_in_stock';
    if(($row['target_countries']??'')!=='IN')$errors[]='unsupported_market';
    if(($row['is_eligible_search']??'')!=='true'||($row['is_eligible_checkout']??'')!=='false')
        $errors[]='unsupported_eligibility_flags';
    if(($row['gtin']??'')!==''&&!preg_match('/^[0-9]{8,14}$/D',(string)$row['gtin']))
        $errors[]='invalid_gtin';
    if(($row['mpn']??'')!==''&&text_len((string)$row['mpn'])>70)$errors[]='invalid_mpn';
    return array_values(array_unique($errors));
}
function acp_feed_csv(): string {
    require_admin();
    $rows=acp_feed_native_rows();
    if($rows===[])throw new RuntimeException('No eligible ACP discovery rows: do not send an empty partner file.');
    $buf=fopen('php://temp','w+');$columns=acp_feed_columns();
    fputcsv($buf,$columns);
    foreach($rows as $row){
        $values=[];
        foreach($columns as $field){
            $value=(string)($row[$field]??'');
            if(preg_match('/^[=+\\-@\\t\\r]/u',$value))$value="'".$value;
            $values[]=$value;
        }
        fputcsv($buf,$values);
    }
    rewind($buf);$csv=(string)stream_get_contents($buf);fclose($buf);return $csv;
}
function acp_feed_report(): array {
    $merchant=discover_merchant_rows();
    $rows=acp_feed_native_rows();
    $issues=[];
    foreach($merchant as $m){
        $row=['item_id'=>(string)($m['id']??''),'title'=>(string)($m['title']??''),
            'description'=>(string)($m['description']??''),'url'=>(string)($m['link']??''),
            'brand'=>(string)($m['brand']??''),'seller_name'=>acp_feed_seller(),
            'seller_url'=>rtrim(absolute_url('/'),'/').'/',
            'image_url'=>(string)($m['image_link']??''),'price'=>(string)($m['price']??''),
            'availability'=>(string)($m['availability']??''),'target_countries'=>'IN',
            'is_eligible_search'=>'true','is_eligible_checkout'=>'false'];
        foreach(acp_feed_validate_row($row) as $error)$issues[$error]=($issues[$error]??0)+1;
    }
    return ['spec'=>'OpenAI product feed v2026','protocol_version'=>acp_version(),
        'merchant_eligible_skus'=>count($merchant),'valid_partner_feed_rows'=>count($rows),
        'rejected_from_partner_feed'=>count($merchant)-count($rows),'errors'=>$issues,
        'market'=>'IN','currency'=>'INR','partner_registered'=>false,
        'external_submission_performed'=>false,'approved_by_openai'=>false,
        'checkout_enabled'=>acp_checkout_live_enabled(),
        'source'=>'B8-G reviewed merchant rows; no invented inventory'];
}
