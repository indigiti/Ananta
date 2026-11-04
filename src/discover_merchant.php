<?php
declare(strict_types=1);

/**
 * B8.1 Ananta Discover: explainable Google Merchant eligibility.
 * Only first-party, checkout-ready nursery products enter Merchant feed.
 * Independent seller enquiries remain discoverable via regular SEO/AI feeds,
 * never misrepresented as online-purchasable products.
 */
function discover_variant_url(array $product,array $variant): string {
    return absolute_url('/product/'.rawurlencode((string)$product['slug']))
        .'?variant='.rawurlencode((string)$variant['sku']);
}
function discover_variant_reasons(array $variant,array $product): array {
    $reasons=[];
    if(($product['status']??'')!=='active')$reasons[]='parent_inactive';
    if(($variant['status']??'')!=='active')$reasons[]='variant_inactive';
    if(($variant['sales_mode']??'')!=='commerce')$reasons[]='enquiry_only';
    if(($variant['availability']??'')!=='in_stock')$reasons[]='not_in_stock';
    if(!empty($variant['track_inventory'])&&(int)($variant['stock']??0)<1)$reasons[]='zero_stock';
    if((int)($variant['price']??0)<1)$reasons[]='missing_price';
    if(trim((string)($variant['sku']??''))==='')$reasons[]='missing_sku';
    if(empty($variant['merchant_enabled']))$reasons[]='merchant_disabled';
    if(($variant['shipping_class']??'')==='pickup-only')$reasons[]='pickup_only';
    if(!mediaos_primary_approved($product))$reasons[]='unapproved_parent_image';
    if(!variant_has_image($variant,$product))$reasons[]='missing_variant_image';
    return $reasons;
}
function discover_parent_reasons(array $product): array {
    $reasons=[];
    if(($product['status']??'')!=='active')$reasons[]='parent_inactive';
    if(product_has_variants($product))$reasons[]='variant_product';
    if(product_is_enquiry($product))$reasons[]='enquiry_only';
    if((int)($product['price']??0)<1)$reasons[]='missing_price';
    // Conservative: no unverifiable in-stock or made-to-order claims.
    $inventory=(array)($product['inventory']??[]);
    if(empty($inventory['track'])||!empty($inventory['made_to_order']))$reasons[]='unverified_inventory';
    elseif((int)($inventory['stock']??0)<1)$reasons[]='zero_stock';
    if(!mediaos_primary_approved($product))$reasons[]='unapproved_parent_image';
    if(trim((string)($product['sku']??''))==='')$reasons[]='missing_sku';
    return $reasons;
}
function discover_merchant_rows(): array {
    $rows=[];
    foreach(products_public() as $p){
        if(product_has_variants($p)){
            foreach(variants_for_product($p,true) as $v){
                if(discover_variant_reasons($v,$p)!==[])continue;
                $rows[]=merchant_variant_dto($v,$p);
            }
        }else{
            if(discover_parent_reasons($p)!==[])continue;
            $row=merchant_product_dto($p);
            if($row)$rows[]=$row;
        }
    }
    return array_values(array_filter($rows));
}
function discover_merchant_report(): array {
    $count=0;$passed=0;$reasons=[];$items=[];
    foreach(products_public() as $p) {
        if(product_has_variants($p)) {
            foreach(variants_for_product($p,true) as $v) {
                $count++;
                $why=discover_variant_reasons($v,$p);
                $eligible=$why===[];
                if($eligible)$passed++;
                foreach($why as $reason)$reasons[$reason]=($reasons[$reason]??0)+1;
                $items[]=[
                    'sku'=>$v['sku']??'','product'=>$p['name'],
                    'type'=>'variant','eligible'=>$eligible,'reasons'=>$why,
                    'url'=>discover_variant_url($p,$v),
                ];
            }
        }else{
            $count++;$why=discover_parent_reasons($p);$eligible=$why===[];
            if($eligible)$passed++;
            foreach($why as $reason)$reasons[$reason]=($reasons[$reason]??0)+1;
            $items[]=[
                'sku'=>$p['sku']??'','product'=>$p['name'],
                'type'=>'single','eligible'=>$eligible,'reasons'=>$why,
                'url'=>absolute_url('/product/'.$p['slug']),
            ];
        }
    }
    arsort($reasons);
    return [
        'candidates'=>$count,'eligible'=>$passed,
        'blocked'=>$count-$passed,'reason_counts'=>$reasons,
        'items'=>$items,'feed_rows'=>count(discover_merchant_rows()),
        'merchant_center_submission'=>'disabled',
        'seller_of_record'=>'Sanjay Nursery (founding retail checkout only)',
        'merchant_api_connected'=>false,
    ];
}
function discover_ai_readiness(): array {
    $products=products_public();$withImage=0;$botanical=0;$named=0;$seo=0;
    foreach($products as $p){
        if(mediaos_primary_approved($p))$withImage++;
        if(trim((string)($p['botanical_name']??''))!=='')$botanical++;
        if(trim((string)($p['name']??''))!=='')$named++;
        if(trim((string)($p['seo']['description']??''))!=='')$seo++;
    }
    return [
        'public_products'=>count($products),'approved_hero_images'=>$withImage,
        'botanical_names'=>$botanical,'named_products'=>$named,'seo_descriptions'=>$seo,
        'ai_feed_available'=>true,'agent_feed_available'=>true,
        'guaranteed_ai_citations'=>false,
    ];
}
