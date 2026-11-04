<?php
declare(strict_types=1);

/**
 * B8.4 Google/AI distribution: locally reconciled canonical Merchant XML feed,
 * Search Console READ-ONLY metrics and merchant-/AI-schema diagnostics.
 * External submission and indexing assertions are deliberately absent.
 */
function discover_distribution_file(): string {
    $test=trim((string)getenv('ANANTA_TEST_DISTRIBUTION_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$test!=='')return $test;
    return data_root().'/private-domain/discover/distribution.json';
}
function discover_distribution_store(): array {
    return array_replace(['schema_version'=>1,'snapshots'=>[],'gsc'=>null],
        confidential_json_read(discover_distribution_file(),'ananta:discover:distribution:v1',[]));
}
function discover_distribution_mutate(callable $fn,string $reason): array {
    if(confidential_key()===null)throw new RuntimeException('Encrypted distribution state required.');
    return confidential_json_mutate(discover_distribution_file(),'ananta:discover:distribution:v1',
        static fn(array $v)=>$fn(array_replace(['schema_version'=>1,'snapshots'=>[],'gsc'=>null],$v)),
        'discover.distribution.'.$reason);
}
function discover_distribution_schema_audit(): array {
    $checks=[];$issues=[];$feed=discover_merchant_rows();$seen=[];
    foreach($feed as $row){
        $id=(string)($row['id']??'');$url=(string)($row['link']??'');
        if($id===''||isset($seen[$id]))$issues[]='duplicate_or_missing_offer_id';
        $seen[$id]=true;
        if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https')
            $issues[]='insecure_or_invalid_offer_url';
        if(!str_contains($url,'/product/')||str_contains($url,'#'))
            $issues[]='uncrawlable_offer_url';
        if(!preg_match('/^[1-9][0-9]* INR$/D',(string)($row['price']??'')))
            $issues[]='invalid_checkout_price';
        if(!filter_var((string)($row['image_link']??''),FILTER_VALIDATE_URL))
            $issues[]='invalid_primary_image_url';
    }
    foreach(products_public() as $p){
        $node=growth_product_schema($p);
        if(product_has_variants($p)){
            $children=$node['hasVariant']??[];
            foreach(variants_for_product($p,true) as $v){
                if(discover_variant_reasons($v,$p)!==[])continue;
                $match=null;
                foreach($children as $child)if(($child['sku']??'')===$v['sku']){$match=$child;break;}
                if(!$match||($match['url']??'')!==discover_variant_url($p,$v)
                    ||(int)($match['offers']['price']??0)!==(int)$v['price'])
                    $issues[]='schema_variant_mismatch';
            }
        }elseif(discover_parent_reasons($p)===[]){
            if(($node['@type']??'')!=='Product'||(int)($node['offers']['price']??0)!==(int)$p['price'])
                $issues[]='schema_parent_price_mismatch';
        }
    }
    $xml=merchant_xml();
    if(class_exists('DOMDocument')){
        $d=new DOMDocument();
        $old=libxml_use_internal_errors(true);
        try{$valid=$d->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
        finally{libxml_clear_errors();libxml_use_internal_errors($old);}
        if(!$valid||$d->getElementsByTagName('item')->length!==count($feed))
            $issues[]='invalid_or_mismatched_xml';
    }else $issues[]='missing_php_xml_extension';
    $issues=array_count_values($issues);
    return ['checked_at'=>gmdate('c'),'sku_count'=>count($feed),'issues'=>$issues,
        'valid'=>$issues===[],'feed_sha256'=>hash('sha256',$xml),'feed_bytes'=>strlen($xml),
        'feed_url'=>absolute_url('/feeds/google-merchant.xml'),
        'submission_state'=>'not_submitted_by_ananta','google_approvals_verified'=>false];
}
function discover_distribution_ai_audit(): array {
    $products=products_public();$invalid=0;$noBotanical=0;$noImage=0;
    foreach($products as $p){
        $ai=ai_product_dto($p);$agent=agent_product_dto($p);
        if(($ai['product_id']??'')!==($agent['product_id']??'')||
            ($ai['canonical_url']??'')!==absolute_url('/product/'.$p['slug']))
            $invalid++;
        if(trim((string)($ai['botanical_name']??''))==='')$noBotanical++;
        if(empty($ai['image']))$noImage++;
    }
    return ['public_products'=>count($products),'canonical_mismatches'=>$invalid,
        'missing_botanical_names'=>$noBotanical,'missing_images'=>$noImage,
        'ai_feed'=>absolute_url('/feeds/ai-products.json'),
        'agent_feed'=>absolute_url('/feeds/agent-products.json'),
        'schema_verified_locally'=>$invalid===0,
        'ai_citation_guarantee'=>false,'search_indexing_verified'=>false];
}
function discover_distribution_refresh(): array {
    if(PHP_SAPI!=='cli')require_admin();
    $audit=discover_distribution_schema_audit();
    $ai=discover_distribution_ai_audit();
    $snapshot=['id'=>uuidv7(),'at'=>gmdate('c'),'merchant'=>$audit,'ai'=>$ai];
    discover_distribution_mutate(static function(array $d)use($snapshot){
        $prev=$d['snapshots'];$last=$prev?end($prev):null;
        if($last&&($last['merchant']['feed_sha256']??'')===$snapshot['merchant']['feed_sha256']
            &&substr((string)$last['at'],0,10)===gmdate('Y-m-d'))return $d;
        $d['snapshots'][]=$snapshot;
        $d['snapshots']=array_slice($d['snapshots'],-40);
        return $d;
    },'snapshot.refresh');
    audit_event('discover.distribution.audit',['sku_count'=>$audit['sku_count'],'valid'=>$audit['valid']]);
    return $snapshot;
}
function discover_distribution_gsc_property(): string {
    $property=trim((string)getenv('ANANTA_GSC_PROPERTY'));
    if($property==='')return '';
    // A DNS-property is deliberately rejected in v1 because the application
    // cannot verify it is owned by the configured public Ananta storefront.
    $host=(string)parse_url(absolute_url('/'),PHP_URL_HOST);
    if(!str_starts_with($property,'https://')||strlen($property)>220
        ||parse_url($property,PHP_URL_HOST)!==$host||
        rtrim($property,'/')!==rtrim(absolute_url('/'),'/')){
        throw new RuntimeException('Search Console URL-prefix property must match the current HTTPS storefront origin.');
    }
    return $property;
}
/** Read-only Search Analytics query, no Google write API or feed submission. */
function discover_distribution_gsc_sync(?callable $request=null): array {
    if(PHP_SAPI!=='cli')require_admin();
    $property=discover_distribution_gsc_property();
    $token=trim((string)getenv('ANANTA_GSC_ACCESS_TOKEN'));
    if($property===''||strlen($token)<30)
        throw new RuntimeException('Search Console OAuth read-only token and matching URL-prefix property required.');
    $end=gmdate('Y-m-d',strtotime('-3 days'));$start=gmdate('Y-m-d',strtotime('-30 days'));
    $url='https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($property).'/searchAnalytics/query';
    $payload=json_encode(['startDate'=>$start,'endDate'=>$end,'dimensions'=>['date'],
        'rowLimit'=>35],JSON_THROW_ON_ERROR);
    if($request){$response=$request($url,$payload,$token);}
    else {
        if(!extension_loaded('curl'))throw new RuntimeException('PHP cURL is required for Search Console read-only sync.');
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>15,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($body===false||$code!==200||strlen($body)>131072)throw new RuntimeException('Search Console API query failed.');
        $response=json_decode($body,true);
    }
    if(!is_array($response)||!is_array($response['rows']??[]))
        throw new RuntimeException('Invalid Search Console API response.');
    $clicks=0.0;$impressions=0.0;
    foreach($response['rows'] as $r){
        if(!is_array($r)||!is_numeric($r['clicks']??null)||!is_numeric($r['impressions']??null))
            throw new RuntimeException('Invalid Search Console observation.');
        $clicks+=(float)$r['clicks'];$impressions+=(float)$r['impressions'];
    }
    $summary=['property'=>$property,'from'=>$start,'to'=>$end,'clicks'=>(int)$clicks,
        'impressions'=>(int)$impressions,'synced_at'=>gmdate('c'),
        'source'=>'Google Search Console Search Analytics API','provider_verified'=>true];
    discover_distribution_mutate(static function(array $d)use($summary){$d['gsc']=$summary;return $d;},'gsc.sync');
    audit_event('discover.gsc.readonly.sync',['days'=>28,'clicks'=>$summary['clicks']]);
    return $summary;
}
function discover_distribution_report(): array {
    require_admin();
    $d=discover_distribution_store();
    $last=$d['snapshots']?end($d['snapshots']):null;
    return ['last'=>$last,'gsc'=>$d['gsc'],'history'=>array_slice($d['snapshots'],-15),
        'current'=>discover_distribution_schema_audit(),'ai'=>discover_distribution_ai_audit(),
        'mode'=>'scheduled_hosted_xml','merchant_api_upload_enabled'=>false,
        'merchant_center_approval_verified'=>false,
        'search_console_configured'=>trim((string)getenv('ANANTA_GSC_PROPERTY'))!=='' &&
            strlen(trim((string)getenv('ANANTA_GSC_ACCESS_TOKEN')))>30];
}
