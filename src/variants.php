<?php
declare(strict_types=1);

/**
 * Sanjay Nursery Live Plant Variant Engine — v1.4.0
 * A product is the plant variety/content parent. A variant is the transactional nursery SKU/batch unit.
 */

function variant_file(): string { return data_root() . '/public-domain/catalog/variants.json'; }
function variant_revisions_dir(): string { return data_root() . '/revisions/variants'; }

function variants_ensure_dirs(): void {
    $dir=dirname(variant_file());
    if(!is_dir($dir)) @mkdir($dir,0750,true);
    if(!is_dir(variant_revisions_dir())) @mkdir(variant_revisions_dir(),0750,true);
    if(!is_file(variant_file())) foundation_json_write(variant_file(),[],null,'variants.bootstrap');
}

function variants_all(): array {
    if(isset($GLOBALS['_variants_cache']) && is_array($GLOBALS['_variants_cache'])) return $GLOBALS['_variants_cache'];
    variants_ensure_dirs();
    $rows=foundation_json_read(variant_file(),[]);
    return $GLOBALS['_variants_cache']=is_array($rows)?array_values($rows):[];
}

function variants_public(): array {
    return array_values(array_filter(variants_all(),fn($v)=>(($v['status']??'draft')==='active')));
}

function variant_by_guid(string $guid): ?array {
    foreach(variants_all() as $v) if(($v['guid']??'')===$guid) return $v;
    return null;
}

function variant_by_sku(string $sku): ?array {
    $sku=strtoupper(trim($sku));
    foreach(variants_all() as $v) if(strtoupper((string)($v['sku']??''))===$sku) return $v;
    return null;
}

function variants_for_product(array|string $product,bool $publicOnly=false): array {
    $guid=is_array($product)?(string)($product['guid']??''):$product;
    $rows=$publicOnly?variants_public():variants_all();
    $out=array_values(array_filter($rows,fn($v)=>(string)($v['product_guid']??'')===$guid));
    usort($out,function($a,$b){
        $sa=(float)($a['pot_size_cm']??0);$sb=(float)($b['pot_size_cm']??0);
        if($sa!==$sb)return $sa<=>$sb;
        return strcmp((string)($a['label']??''),(string)($b['label']??''));
    });
    return $out;
}

function variant_is_in_stock(array $v): bool {
    $availability=(string)($v['availability']??'in_stock');
    if($availability!=='in_stock')return false;
    if(empty($v['track_inventory']))return true;
    return (int)($v['stock']??0)>0;
}

function variant_is_sellable(array $v): bool {
    if(($v['status']??'draft')!=='active')return false;
    if(($v['sales_mode']??'enquiry')!=='commerce')return false;
    if((int)($v['price']??0)<=0)return false;
    if(trim((string)($v['sku']??''))==='')return false;
    if(($v['shipping_class']??'plant-standard')==='pickup-only')return false; // Pickup checkout is not enabled in v1.4.0.
    return variant_is_in_stock($v);
}

function variant_effective_image(array $v,?array $p=null): string {
    $file=trim((string)($v['image']??''));
    if($file!=='')return $file;
    if($p===null&&!empty($v['product_guid']))$p=product_by_guid((string)$v['product_guid']);
    if(is_array($p) && function_exists('mediaos_primary_file')) return trim((string)mediaos_primary_file($p));
    return trim((string)($p['image']??''));
}

function variant_has_image(array $v,?array $p=null): bool {
    $file=variant_effective_image($v,$p);
    return $file!=='' && is_file(dirname(__DIR__).'/public/assets/images/products/'.basename($file));
}

function variant_is_merchant_eligible(array $v,?array $p=null): bool {
    if(!variant_is_sellable($v) || empty($v['merchant_enabled']))return false;
    if($p===null)$p=product_by_guid((string)($v['product_guid']??''));
    if(!is_array($p) || (($p['status']??'draft')!=='active') || !variant_has_image($v,$p)) return false;
    // v1.6 Merchant eligibility always requires an approved, rights-cleared parent catalogue hero.
    // A variant-specific image may supplement the SKU, but cannot bypass the product photography approval gate.
    return function_exists('mediaos_primary_approved') && mediaos_primary_approved($p);
}

function sellable_variants_for_product(array $p): array {
    return array_values(array_filter(variants_for_product($p,true),'variant_is_sellable'));
}

function product_has_variants(array $p): bool { return count(variants_for_product($p,false))>0; }
function product_has_sellable_variants(array $p): bool { return count(sellable_variants_for_product($p))>0; }

function product_variant_price_range(array $p): ?array {
    $rows=sellable_variants_for_product($p);if(!$rows)return null;
    $prices=array_map(fn($v)=>(int)$v['price'],$rows);
    return ['min'=>min($prices),'max'=>max($prices),'count'=>count($rows)];
}

function product_variant_inventory_summary(array $p): array {
    $rows=variants_for_product($p,true);$sellable=0;$stock=0;$enquiry=0;$out=0;
    foreach($rows as $v){if(variant_is_sellable($v))$sellable++;else if(($v['sales_mode']??'enquiry')==='enquiry')$enquiry++;else$out++;if(!empty($v['track_inventory']))$stock+=(int)($v['stock']??0);}
    return ['variants'=>count($rows),'sellable'=>$sellable,'enquiry'=>$enquiry,'unavailable'=>$out,'tracked_stock'=>$stock];
}

function variant_display_label(array $v): string {
    $label=trim((string)($v['label']??''));
    if($label!=='')return $label;
    $parts=[];
    if((float)($v['pot_size_cm']??0)>0)$parts[]=rtrim(rtrim(number_format((float)$v['pot_size_cm'],1),'0'),'.').' cm pot';
    if(trim((string)($v['grade']??''))!=='')$parts[]=(string)$v['grade'];
    return $parts?implode(' · ',$parts):(string)($v['sku']??'Plant variant');
}

function variant_availability_label(array $v): string {
    $map=['in_stock'=>'In stock','out_of_stock'=>'Out of stock','preorder'=>'Pre-order','coming_soon'=>'Coming soon'];
    $base=$map[$v['availability']??'in_stock']??'Check availability';
    if(($v['availability']??'')==='in_stock'&&!empty($v['track_inventory']))$base.=' · '.(int)($v['stock']??0).' available';
    return $base;
}

function variants_save(array $rows,string $reason='variant.update',?string $expectedChecksum=null): void {
    variants_ensure_dirs();
    if(is_file(variant_file())){
        $raw=(string)file_get_contents(variant_file());
        if($raw!=='')file_put_contents(variant_revisions_dir().'/'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3)).'.json',$raw,LOCK_EX);
    }
    atomic_json_write(variant_file(),array_values($rows),$expectedChecksum,$reason);
    $GLOBALS['_variants_cache']=array_values($rows); $GLOBALS['_discovery_facet_cache']=[];
    audit_event($reason,['count'=>count($rows)]);
}

function variant_form_payload(array $src,array $product,?array $existing=null): array {
    $statusValue=(string)($src['status']??'draft');$status=in_array($statusValue,['draft','active','archived'],true)?$statusValue:'draft';
    $salesValue=(string)($src['sales_mode']??'enquiry');$sales=in_array($salesValue,['commerce','enquiry'],true)?$salesValue:'enquiry';
    $availabilityValue=(string)($src['availability']??'coming_soon');$availability=in_array($availabilityValue,['in_stock','out_of_stock','preorder','coming_soon'],true)?$availabilityValue:'coming_soon';
    $shippingValue=(string)($src['shipping_class']??'plant-standard');$shipping=in_array($shippingValue,['plant-standard','plant-large','pickup-only'],true)?$shippingValue:'plant-standard';
    $sku=strtoupper(trim((string)($src['sku']??'')));
    $batch=strtoupper(trim((string)($src['batch_code']??'')));
    $label=trim((string)($src['label']??''));
    $pot=(float)($src['pot_size_cm']??0);
    if($label===''&&$pot>0)$label=rtrim(rtrim(number_format($pot,1),'0'),'.').' cm pot';
    return [
        'guid'=>$existing['guid']??uuidv7(),
        'product_guid'=>$product['guid'],
        'sku'=>$sku,
        'status'=>$status,
        'label'=>$label,
        'pot_size_cm'=>max(0,$pot),
        'pot_size_label'=>trim((string)($src['pot_size_label']??'')),
        'plant_height_cm_min'=>max(0,(int)($src['plant_height_cm_min']??0)),
        'plant_height_cm_max'=>max(0,(int)($src['plant_height_cm_max']??0)),
        'grade'=>trim((string)($src['grade']??'')),
        'batch_code'=>$batch,
        'batch_date'=>trim((string)($src['batch_date']??'')),
        'price'=>max(0,(int)($src['price']??0)),
        'compare_at_price'=>max(0,(int)($src['compare_at_price']??0)),
        'sales_mode'=>$sales,
        'availability'=>$availability,
        'track_inventory'=>isset($src['track_inventory'])&&(string)$src['track_inventory']==='1',
        'stock'=>max(0,(int)($src['stock']??0)),
        'lead_time'=>trim((string)($src['lead_time']??'')),
        'shipping_class'=>$shipping,
        'local_pickup'=>isset($src['local_pickup'])&&(string)$src['local_pickup']==='1',
        'merchant_enabled'=>isset($src['merchant_enabled'])&&(string)$src['merchant_enabled']==='1',
        'image'=>trim((string)($src['image']??($existing['image']??''))),
        'notes'=>trim((string)($src['notes']??'')),
        'updated_at'=>gmdate('c'),
        'created_at'=>$existing['created_at']??gmdate('c'),
    ];
}

function validate_variant_record(array $v,?string $ignoreGuid=null): array {
    $errors=[];
    if(!product_by_guid((string)($v['product_guid']??'')))$errors[]='Parent plant variety is invalid.';
    if(trim((string)($v['sku']??''))==='')$errors[]='Variant SKU is required.';
    if(trim((string)($v['label']??''))==='')$errors[]='Variant label is required.';
    if(($v['sales_mode']??'enquiry')==='commerce'){
        if((int)($v['price']??0)<=0)$errors[]='A purchasable variant needs a price greater than zero.';
        if(($v['availability']??'')!=='in_stock')$errors[]='A purchasable variant must be marked In stock.';
        if(!empty($v['track_inventory'])&&(int)($v['stock']??0)<=0)$errors[]='A tracked purchasable variant needs stock greater than zero.';
        if(($v['shipping_class']??'plant-standard')==='pickup-only')$errors[]='Pickup-only variants remain enquiry-only until pickup checkout is enabled.';
    }
    $min=(int)($v['plant_height_cm_min']??0);$max=(int)($v['plant_height_cm_max']??0);
    if($min>0&&$max>0&&$max<$min)$errors[]='Maximum plant height cannot be lower than minimum height.';
    if(!empty($v['batch_date'])&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$v['batch_date']))$errors[]='Batch date must use YYYY-MM-DD.';
    foreach(variants_all() as $x){
        if($ignoreGuid&&($x['guid']??'')===$ignoreGuid)continue;
        if(strtoupper((string)($x['sku']??''))===strtoupper((string)($v['sku']??'')))$errors[]='Variant SKU is already in use.';
    }
    return array_values(array_unique($errors));
}

function upsert_variant(array $v,?string $expectedChecksum=null): void {
    $rows=variants_all();$found=false;
    foreach($rows as $i=>$x)if(($x['guid']??'')===($v['guid']??'')){$rows[$i]=$v;$found=true;break;}
    if(!$found)$rows[]=$v;
    variants_save($rows,$found?'variant.update':'variant.create',$expectedChecksum);
}

function delete_variant(string $guid,?string $expectedChecksum=null): bool {
    $rows=variants_all();$before=count($rows);$rows=array_values(array_filter($rows,fn($v)=>(string)($v['guid']??'')!==$guid));
    if(count($rows)===$before)return false;
    variants_save($rows,'variant.delete',$expectedChecksum);return true;
}

function variant_release_checks(): array {
    $rows=variants_all();$guids=[];$skus=[];$refs=true;$schema=true;$merchantSafe=true;
    foreach($rows as $v){
        $guids[]=(string)($v['guid']??'');$skus[]=strtoupper((string)($v['sku']??''));
        if(!product_by_guid((string)($v['product_guid']??'')))$refs=false;
        foreach(['guid','product_guid','sku','status','label','sales_mode','availability','price','stock','updated_at'] as $k)if(!array_key_exists($k,$v))$schema=false;
        if(variant_is_merchant_eligible($v) && !variant_is_sellable($v))$merchantSafe=false;
    }
    return [
        ['name'=>'Variant schema','ok'=>$schema,'detail'=>count($rows).' live-plant variants use the v1.4.0 schema.'],
        ['name'=>'Variant identity uniqueness','ok'=>count($guids)===count(array_unique($guids))&&count($skus)===count(array_unique($skus)),'detail'=>'Variant GUIDs and SKUs are unique.'],
        ['name'=>'Variant parent integrity','ok'=>$refs,'detail'=>'Every variant references an existing plant-variety parent.'],
        ['name'=>'Variant commerce safety','ok'=>$merchantSafe,'detail'=>'Merchant eligibility cannot bypass variant sellability rules.'],
    ];
}

function variant_order_stock_available(array $items): array {
    $errors=[];
    foreach($items as $item){
        $vg=(string)($item['variant_guid']??'');if($vg==='')continue;
        $v=variant_by_guid($vg);$qty=max(1,(int)($item['qty']??1));
        if(!$v||!variant_is_sellable($v)){$errors[]='A selected live plant variant is no longer available.';continue;}
        if(!empty($v['track_inventory'])&&(int)($v['stock']??0)<$qty)$errors[]=(string)($item['name']??'Plant').' · '.variant_display_label($v).' has insufficient stock.';
    }
    return array_values(array_unique($errors));
}

function variant_commit_order_stock(array $order): void {
    $needed=[];
    foreach((array)($order['items']??[]) as $item){$vg=(string)($item['variant_guid']??'');if($vg!=='')$needed[$vg]=($needed[$vg]??0)+max(1,(int)($item['qty']??1));}
    if(!$needed)return;
    $rows=variants_all();$seen=[];
    foreach($rows as &$v){$g=(string)($v['guid']??'');if(!isset($needed[$g]))continue;$seen[$g]=true;if(empty($v['track_inventory']))continue;$stock=(int)($v['stock']??0);if($stock<$needed[$g])throw new RuntimeException('Live inventory changed before payment completion for SKU '.($v['sku']??$g).'.');$v['stock']=$stock-$needed[$g];if($v['stock']<=0)$v['availability']='out_of_stock';$v['updated_at']=gmdate('c');}
    foreach($needed as $g=>$qty)if(empty($seen[$g]))throw new RuntimeException('Variant stock record disappeared before payment completion.');
    variants_save($rows,'variant.stock.commit',foundation_checksum(variant_file()));audit_event('variant.stock.order_commit',['order_guid'=>$order['guid']??null,'variant_lines'=>count($needed)]);
}

function variant_import_csv(string $csv): array {
    $csv=trim($csv);if($csv==='')return ['added'=>0,'updated'=>0,'skipped'=>0,'errors'=>['CSV is empty.']];
    $lines=preg_split('/\r?\n/',$csv)?:[];$header=str_getcsv((string)array_shift($lines));$header=array_map(fn($h)=>strtolower(trim((string)$h)),$header);
    $required=['parent_slug','sku','label'];foreach($required as $k)if(!in_array($k,$header,true))return ['added'=>0,'updated'=>0,'skipped'=>count($lines),'errors'=>['Missing required column: '.$k]];
    $rows=variants_all();$skuIndex=[];foreach($rows as $i=>$v)$skuIndex[strtoupper((string)($v['sku']??''))]=$i;
    $seen=[];$added=0;$updated=0;$skipped=0;$errors=[];
    $truthy=fn($v)=>in_array(strtolower(trim((string)$v)),['1','true','yes','y','on'],true)?'1':'';
    foreach($lines as $n=>$line){if(trim($line)==='')continue;$vals=str_getcsv($line);$row=[];foreach($header as $i=>$key)$row[$key]=$vals[$i]??'';$slug=trim((string)($row['parent_slug']??''));$product=product_by_slug($slug);$sku=strtoupper(trim((string)($row['sku']??'')));if(!$product){$skipped++;$errors[]='Row '.($n+2).': unknown parent_slug '.$slug;continue;}if($sku===''||isset($seen[$sku])){$skipped++;$errors[]='Row '.($n+2).': empty or duplicate SKU '.$sku;continue;}$seen[$sku]=true;$existing=isset($skuIndex[$sku])?$rows[$skuIndex[$sku]]:null;
        $src=['sku'=>$sku,'label'=>$row['label']??'','status'=>$row['status']?:'draft','pot_size_cm'=>$row['pot_size_cm']??0,'pot_size_label'=>$row['pot_size_label']??'','plant_height_cm_min'=>$row['height_min_cm']??0,'plant_height_cm_max'=>$row['height_max_cm']??0,'grade'=>$row['grade']??'','batch_code'=>$row['batch_code']??'','batch_date'=>$row['batch_date']??'','price'=>$row['price']??0,'compare_at_price'=>$row['compare_at_price']??0,'sales_mode'=>$row['sales_mode']?:'enquiry','availability'=>$row['availability']?:'coming_soon','track_inventory'=>$truthy($row['track_inventory']??''),'stock'=>$row['stock']??0,'lead_time'=>$row['lead_time']??'','shipping_class'=>$row['shipping_class']?:'plant-standard','local_pickup'=>$truthy($row['local_pickup']??''),'merchant_enabled'=>$truthy($row['merchant_enabled']??''),'image'=>$row['image']??'','notes'=>$row['notes']??''];
        $v=variant_form_payload($src,$product,$existing);$ve=validate_variant_record($v,$existing['guid']??null);if($ve){$skipped++;$errors[]='Row '.($n+2).' '.$sku.': '.implode(' ',$ve);continue;}
        if($existing){$rows[$skuIndex[$sku]]=$v;$updated++;}else{$skuIndex[$sku]=count($rows);$rows[]=$v;$added++;}
    }
    if($added||$updated)variants_save($rows,'variant.csv.import',foundation_checksum(variant_file()));
    return ['added'=>$added,'updated'=>$updated,'skipped'=>$skipped,'errors'=>array_slice($errors,0,50)];
}
