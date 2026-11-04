<?php
declare(strict_types=1);

/**
 * B8-G Completion, staging: immutable GitHub 376-SKU baseline versus live
 * Cloudways file-first product/variant/media state. No catalogue writes here.
 * Merchant compliance reviews and Google read-only reports remain encrypted.
 */
function merchant_completion_baseline(): array {
    $path=dirname(__DIR__).'/resources/B8G_BASELINE_SKUS.json';
    $data=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($data)||($data['version']??null)!==1||count($data['items']??[])!==376)
        throw new RuntimeException('The 376-SKU baseline snapshot is invalid.');
    $bySku=[];
    foreach($data['items'] as $p){
        $sku=(string)($p['sku']??'');
        if($sku===''||isset($bySku[$sku])||empty($p['slug']))
            throw new RuntimeException('Baseline SKU identity mismatch.');
        $bySku[$sku]=(string)$p['slug'];
    }
    return $bySku;
}
function merchant_completion_reviews_file(): string {
    $test=trim((string)getenv('ANANTA_TEST_MERCHANT_REVIEWS_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$test!=='')return $test;
    return data_root().'/private-domain/discover/merchant-evidence.json';
}
function merchant_completion_reviews(): array {
    if(isset($GLOBALS['_merchant_reviews_request_cache']))return $GLOBALS['_merchant_reviews_request_cache'];
    return $GLOBALS['_merchant_reviews_request_cache']=array_replace(['version'=>1,'policy'=>[],'sku'=>[]],
        confidential_json_read(merchant_completion_reviews_file(),'ananta:discover:merchant-evidence:v1',[]));
}
function merchant_completion_mutate(callable $f,string $reason): array {
    if(confidential_key()===null)throw new RuntimeException('Merchant evidence requires encrypted storage.');
    $next=confidential_json_mutate(merchant_completion_reviews_file(),
        'ananta:discover:merchant-evidence:v1',
        static fn(array $d)=>$f(array_replace(['version'=>1,'policy'=>[],'sku'=>[]],$d)),
        'merchant.evidence.'.$reason);
    unset($GLOBALS['_merchant_reviews_request_cache']);
    return $next;
}
function merchant_completion_policy(): array {
    return merchant_completion_reviews()['policy'];
}
function merchant_completion_policy_reason_codes(): array {
    $p=merchant_completion_policy();$reasons=[];
    foreach(['seller','shipping','returns','domain','checkout'] as $part)
        if(empty($p[$part.'_reviewed']))$reasons[]='merchant_'.$part.'_unverified';
    return $reasons;
}
function merchant_completion_policy_save(array $input): void {
    require_admin();
    $origin=rtrim(absolute_url('/'),'/').'/';
    $url=trim((string)($input['returns_url']??''));
    $shippingUrl=trim((string)($input['shipping_url']??''));
    foreach([$url,$shippingUrl] as $link){
        if(!filter_var($link,FILTER_VALIDATE_URL)||parse_url($link,PHP_URL_SCHEME)!=='https'
            ||parse_url($link,PHP_URL_HOST)!==parse_url($origin,PHP_URL_HOST)
            ||strlen($link)>260)throw new InvalidArgumentException('Publish verified HTTPS policy URLs on the exact storefront domain.');
    }
    $reference=trim((string)($input['evidence']??''));
    if(strlen($reference)<12||strlen($reference)>200)
        throw new InvalidArgumentException('A traceable seller, shipping and returns evidence reference is required.');
    $flags=[];
    foreach(['seller','shipping','returns','domain','checkout'] as $key)
        $flags[$key.'_reviewed']=(($input[$key.'_reviewed']??'')==='yes');
    // A staging domain must never be accepted as a verified public merchant origin.
    $host=strtolower((string)parse_url($origin,PHP_URL_HOST));
    if($flags['domain_reviewed']&&(str_contains($host,'stage.')||str_contains($host,'staging.')
        ||str_ends_with($host,'.test')||str_ends_with($host,'.invalid')))
        throw new RuntimeException('A staging/test hostname cannot be a verified Merchant storefront.');
    $data=$flags+['seller_name'=>trim((string)($input['seller_name']??'Sanjay Nursery')),
        'returns_url'=>$url,'shipping_url'=>$shippingUrl,'evidence'=>$reference,
        'reviewed_at'=>gmdate('c'),'review_type'=>'administrator_attestation_not_google_approval'];
    if($data['seller_name']===''||strlen($data['seller_name'])>100)
        throw new InvalidArgumentException('The legal seller name is required.');
    merchant_completion_mutate(static function(array $d)use($data){$d['policy']=$data;return $d;},'policy');
    audit_event('merchant.policy.review',['seller'=>$data['seller_name'],'reviewed_at'=>$data['reviewed_at']]);
}
function merchant_completion_sku_record(string $sku): ?array {
    foreach(products_all() as $p){
        if((string)($p['sku']??'')===$sku)return ['variant'=>null,'product'=>$p];
        foreach(variants_for_product($p,false) as $v)
            if((string)($v['sku']??'')===$sku)return ['variant'=>$v,'product'=>$p];
    }
    return null;
}
function merchant_completion_review_save(array $input): void {
    require_admin();
    $sku=trim((string)($input['sku']??''));
    $record=merchant_completion_sku_record($sku);
    if(!$record)throw new InvalidArgumentException('SKU not present in local catalogue.');
    $mode=(string)($input['identifiers']??'');
    if(!in_array($mode,['none_verified','gtin','mpn'],true))
        throw new InvalidArgumentException('Explicit real-world identifier review is required.');
    $brand=trim((string)($input['brand']??''));
    $value=trim((string)($input['identifier_value']??''));
    if($brand===''||strlen($brand)>90)throw new InvalidArgumentException('Visible seller/product brand is required.');
    if($mode==='gtin'&&!preg_match('/^[0-9]{8,14}$/D',$value))
        throw new InvalidArgumentException('Valid GTIN digits are required.');
    if($mode==='mpn'&&(!preg_match('/^[A-Za-z0-9_.-]{3,70}$/D',$value)))
        throw new InvalidArgumentException('Valid manufacturer part number required.');
    $evidence=trim((string)($input['evidence']??''));
    if(strlen($evidence)<12||strlen($evidence)>240)
        throw new InvalidArgumentException('Evidence reference (12–240 chars) required.');
    $review=['sku'=>$sku,'identifiers'=>$mode,'identifier_value'=>$mode==='none_verified'?'':$value,
        'brand'=>$brand,'stock_reviewed'=>(($input['stock_reviewed']??'')==='yes'),
        'evidence'=>$evidence,'reviewed_at'=>gmdate('c'),
        'review_type'=>'operator_attestation_not_independent_stock_or_image_proof'];
    merchant_completion_mutate(static function(array $d)use($sku,$review){
        if(!isset($d['sku'][$sku])&&count($d['sku'])>=4000)
            throw new RuntimeException('Merchant pilot SKU review limit reached.');
        $d['sku'][$sku]=$review;return $d;
    },'sku');
    audit_event('merchant.sku.review',['sku'=>$sku,'identifier_mode'=>$mode]);
}
function merchant_completion_item_reasons(string $sku): array {
    $why=merchant_completion_policy_reason_codes();
    $r=merchant_completion_reviews()['sku'][$sku]??null;
    if(!$r)$why[]='identifier_review_missing';
    else{
        if(empty($r['stock_reviewed']))$why[]='stock_reconfirmation_missing';
        // A same-day stock attestation expires after 48 hours. It cannot
        // substitute for inventory tracking, physical count or reservation.
        if(strtotime((string)($r['reviewed_at']??''))<time()-172800)
            $why[]='stock_reconfirmation_expired';
    }
    return $why;
}
function merchant_completion_merchant_fields(string $sku): array {
    $r=merchant_completion_reviews()['sku'][$sku]??null;
    if(!$r)return [];
    $fields=['brand'=>$r['brand']];
    if($r['identifiers']==='none_verified')$fields['identifier_exists']='no';
    elseif($r['identifiers']==='gtin')$fields['gtin']=$r['identifier_value'];
    elseif($r['identifiers']==='mpn')$fields['mpn']=$r['identifier_value'];
    return $fields;
}
function merchant_completion_reconcile(int $page=1,int $perPage=40): array {
    $baseline=merchant_completion_baseline();$catalog=products_all();$seen=[];
    $reviews=merchant_completion_reviews();
    $stats=['baseline'=>count($baseline),'live'=>count($catalog),'baseline_missing'=>0,
        'baseline_modified'=>0,'added'=>0,'live_variants'=>0,'checkout_candidates'=>0,
        'feed_eligible'=>0,'priced'=>0,'tracked_stock'=>0,'approved_hero'=>0,'enquiry_only'=>0];
    $rows=[];$reasons=[];
    foreach($catalog as $p){
        $sku=(string)($p['sku']??'');$slug=(string)($p['slug']??'');
        $seen[$sku]=true;
        $stage=isset($baseline[$sku])?($baseline[$sku]===$slug?'baseline_present':'baseline_changed'):'new_since_baseline';
        if($stage==='new_since_baseline')$stats['added']++;
        if($stage==='baseline_changed')$stats['baseline_modified']++;
        if((int)($p['price']??0)>0)$stats['priced']++;
        if(!empty($p['inventory']['track'])&&(int)($p['inventory']['stock']??0)>0)$stats['tracked_stock']++;
        if(mediaos_primary_approved($p))$stats['approved_hero']++;
        if(product_is_enquiry($p))$stats['enquiry_only']++;
        $variants=variants_for_product($p,false);$stats['live_variants']+=count($variants);
        $objects=$variants?:[null];
        foreach($objects as $v){
            $id=$v?(string)$v['sku']:$sku;
            $why=$v?discover_variant_reasons($v,$p):discover_parent_reasons($p);
            if($why===[])$stats['feed_eligible']++;
            if($v?variant_is_sellable($v):product_is_sellable($p))$stats['checkout_candidates']++;
            foreach($why as $reason)$reasons[$reason]=($reasons[$reason]??0)+1;
            $rows[]=['sku'=>$id,'parent_sku'=>$sku,'name'=>(string)$p['name'],
                'slug'=>$slug,'type'=>$v?'variant':'parent','baseline'=>$stage,
                'price'=>(int)($v['price']??$p['price']??0),
                'stock'=>(int)($v['stock']??$p['inventory']['stock']??0),
                'tracked'=>(bool)($v['track_inventory']??$p['inventory']['track']??false),
                'image_approved'=>mediaos_primary_approved($p),
                'eligible'=>$why===[],'reasons'=>$why,
                'reviewed_at'=>$reviews['sku'][$id]['reviewed_at']??null];
        }
    }
    foreach($baseline as $sku=>$slug)if(!isset($seen[$sku]))$stats['baseline_missing']++;
    usort($rows,static fn($a,$b)=>strcmp($a['sku'],$b['sku']));
    arsort($reasons);
    $perPage=max(1,min(100,$perPage));$total=count($rows);$pages=max(1,(int)ceil($total/$perPage));
    return ['stats'=>$stats,'reason_counts'=>$reasons,'total_rows'=>$total,'pages'=>$pages,
        'page'=>max(1,min($pages,$page)),'rows'=>array_slice($rows,(max(1,min($pages,$page))-1)*$perPage,$perPage),
        'baseline_sha256'=>hash_file('sha256',dirname(__DIR__).'/resources/B8G_BASELINE_SKUS.json'),
        'source'=>'Immutable checked-in B8-G baseline compared to runtime products_all() and variants',
        'writeback_performed'=>false,'merchant_verified_by_google'=>false];
}
function merchant_completion_csv(): string {
    $report=merchant_completion_reconcile(1,100);
    $buf=fopen('php://temp','w+');
    fputcsv($buf,['sku','parent_sku','product','type','baseline','price_inr','stock','tracked',
        'image_approved','feed_eligible','reasons','reviewed_at']);
    for($i=1;$i<=$report['pages'];$i++){
        foreach(merchant_completion_reconcile($i,100)['rows'] as $row){
            $fields=[$row['sku'],$row['parent_sku'],$row['name'],$row['type'],$row['baseline'],
                $row['price'],$row['stock'],$row['tracked']?'yes':'no',
                $row['image_approved']?'yes':'no',$row['eligible']?'yes':'no',
                implode('|',$row['reasons']),(string)$row['reviewed_at']];
            // Prevent spreadsheet formula interpretation on export.
            foreach($fields as &$v)if(is_string($v)&&preg_match('/^[=+\-@\t\r]/',$v))$v="'".$v;
            unset($v);
            fputcsv($buf,$fields);
        }
    }
    rewind($buf);$out=stream_get_contents($buf);fclose($buf);return $out;
}
