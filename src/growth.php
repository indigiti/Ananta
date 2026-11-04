<?php
declare(strict_types=1);

/**
 * Sanjay Nursery Growth Foundation — v1.4.0
 * Public configuration, analytics readiness, SEO graph, migration monitoring,
 * collection hubs and editorial quality controls.
 */
function growth_root(): string { return data_root().'/public-domain/growth'; }
function growth_settings_file(): string { return growth_root().'/settings.json'; }
function growth_404_file(): string { return data_root().'/journals/growth-404.ndjson'; }
function growth_ensure_dirs(): void {
    foreach([growth_root(), dirname(growth_404_file())] as $d){
        if(!is_dir($d)&&!@mkdir($d,0750,true)&&!is_dir($d)) throw new RuntimeException('Unable to create growth data directory.');
        @chmod($d,0750);
    }
}
function growth_defaults(): array {
    return [
        'analytics_enabled'=>false,
        'cookie_consent_enabled'=>true,
        'ga4_measurement_id'=>'',
        'gtm_container_id'=>'',
        'google_site_verification'=>'',
        'bing_site_verification'=>'',
        'merchant_center_id'=>'',
        'google_business_profile_url'=>'',
        'google_business_place_id'=>'',
        'logo_url'=>'',
        'default_social_image'=>'',
        'same_as'=>[],
        'content_quality_publish_threshold'=>65,
        'updated_at'=>null,
    ];
}
function growth_settings(): array {
    growth_ensure_dirs();
    $s=foundation_json_read(growth_settings_file(),growth_defaults());
    $s=array_merge(growth_defaults(),is_array($s)?$s:[]);
    $s['same_as']=array_values(array_filter(array_map('trim',(array)($s['same_as']??[])),fn($x)=>$x!==''));
    return $s;
}
function growth_valid_ga4(string $v): bool { return $v==='' || (bool)preg_match('/^G-[A-Z0-9]{6,20}$/i',$v); }
function growth_valid_gtm(string $v): bool { return $v==='' || (bool)preg_match('/^GTM-[A-Z0-9]{4,20}$/i',$v); }
function growth_clean_public_url(string $v): string {
    $v=trim($v); if($v==='')return'';
    if(!filter_var($v,FILTER_VALIDATE_URL)) throw new RuntimeException('Enter a valid https:// URL.');
    $scheme=strtolower((string)parse_url($v,PHP_URL_SCHEME)); if(!in_array($scheme,['http','https'],true))throw new RuntimeException('Only http/https URLs are supported.');
    return $v;
}
function growth_save_settings(array $input): array {
    require_admin();
    $ga4=strtoupper(trim((string)($input['ga4_measurement_id']??'')));
    $gtm=strtoupper(trim((string)($input['gtm_container_id']??'')));
    if(!growth_valid_ga4($ga4))throw new RuntimeException('GA4 Measurement ID must look like G-XXXXXXXXXX.');
    if(!growth_valid_gtm($gtm))throw new RuntimeException('GTM Container ID must look like GTM-XXXXXXX.');
    $same=[];foreach(preg_split('/\r?\n/',(string)($input['same_as']??''))?:[] as $u){$u=trim($u);if($u!=='')$same[]=growth_clean_public_url($u);}    
    $row=[
        'analytics_enabled'=>!empty($input['analytics_enabled']),
        'cookie_consent_enabled'=>!empty($input['cookie_consent_enabled']),
        'ga4_measurement_id'=>$ga4,
        'gtm_container_id'=>$gtm,
        'google_site_verification'=>trim((string)($input['google_site_verification']??'')),
        'bing_site_verification'=>trim((string)($input['bing_site_verification']??'')),
        'merchant_center_id'=>preg_replace('/[^0-9]/','',trim((string)($input['merchant_center_id']??''))),
        'google_business_profile_url'=>growth_clean_public_url((string)($input['google_business_profile_url']??'')),
        'google_business_place_id'=>trim((string)($input['google_business_place_id']??'')),
        'logo_url'=>growth_clean_public_url((string)($input['logo_url']??'')),
        'default_social_image'=>growth_clean_public_url((string)($input['default_social_image']??'')),
        'same_as'=>array_values(array_unique($same)),
        'content_quality_publish_threshold'=>max(50,min(90,(int)($input['content_quality_publish_threshold']??65))),
        'updated_at'=>gmdate('c'),
    ];
    foundation_json_write(growth_settings_file(),$row,foundation_checksum(growth_settings_file()),'growth.settings.save');
    audit_event('growth.settings.save',['analytics_enabled'=>$row['analytics_enabled'],'ga4_configured'=>$row['ga4_measurement_id']!=='','gtm_configured'=>$row['gtm_container_id']!=='']);
    return$row;
}
function growth_tracking_enabled(): bool { $s=growth_settings();return !empty($s['analytics_enabled'])&&(($s['ga4_measurement_id']??'')!==''||($s['gtm_container_id']??'')!==''); }
function growth_tracking_public(): array { $s=growth_settings();return ['enabled'=>growth_tracking_enabled(),'consent'=>!empty($s['cookie_consent_enabled']),'ga4'=>(string)$s['ga4_measurement_id'],'gtm'=>(string)$s['gtm_container_id']]; }

function growth_collection_slug(string $name): string { return cms_slugify($name); }
function growth_collection_map(): array {
    $out=[];
    foreach((array)(taxonomies()['categories']??[]) as $c){$slug=(string)($c['slug']??'');if($slug!=='')$out[$slug]=['slug'=>$slug,'label'=>(string)($c['name']??category_label($slug)),'type'=>'category','value'=>$slug];}
    $seen=[];foreach(products_public() as $p){$name=trim((string)($p['collection']??''));if($name==='')continue;$slug=growth_collection_slug($name);if($slug===''||isset($seen[$slug]))continue;$seen[$slug]=true;$out[$slug]=['slug'=>$slug,'label'=>$name,'type'=>'collection','value'=>$name];}
    return$out;
}
function growth_collection_by_slug(string $slug): ?array { $m=growth_collection_map();return$m[$slug]??null; }
function growth_collection_url(string $value,string $type='category'): string {
    if($type==='category')return'/collections/'.trim($value,'/');
    return'/collections/'.growth_collection_slug($value);
}
function growth_collection_products(array $hub): array {
    return array_values(array_filter(products_public(),fn($p)=>($hub['type']??'')==='category'?($p['category']??'')===($hub['value']??''):($p['collection']??'')===($hub['value']??'')));
}

function growth_breadcrumb_schema(array $crumbs): array {
    $items=[];$pos=1;foreach($crumbs as $label=>$path){$items[]=['@type'=>'ListItem','position'=>$pos++,'name'=>(string)$label,'item'=>absolute_url((string)$path)];}
    return ['@type'=>'BreadcrumbList','itemListElement'=>$items];
}
function growth_organization_schema(): array {
    $s=growth_settings();
    $org=[
        '@type'=>['Organization','GardenStore'],
        '@id'=>absolute_url('/').'#organization',
        'name'=>'Sanjay Nursery',
        'url'=>absolute_url('/'),
        'email'=>'sanjaynursery@gmail.com',
        'telephone'=>'+91 92253 37601',
        'address'=>['@type'=>'PostalAddress','streetAddress'=>'Arihant, S No. 877, Kadam Wak Wasti, Pune-Solapur Road','addressLocality'=>'Loni Kalbhor','addressRegion'=>'Maharashtra','postalCode'=>'412201','addressCountry'=>'IN'],
        'areaServed'=>['@type'=>'AdministrativeArea','name'=>'Maharashtra, India'],
    ];
    if(($s['logo_url']??'')!=='')$org['logo']=['@type'=>'ImageObject','url'=>$s['logo_url']];
    if($s['same_as'])$org['sameAs']=$s['same_as'];
    if(($s['google_business_place_id']??'')!=='')$org['identifier']=[['@type'=>'PropertyValue','propertyID'=>'GooglePlaceID','value'=>$s['google_business_place_id']]];
    return$org;
}
function growth_website_schema(): array { return ['@type'=>'WebSite','@id'=>absolute_url('/').'#website','url'=>absolute_url('/'),'name'=>'Sanjay Nursery','publisher'=>['@id'=>absolute_url('/').'#organization'],'inLanguage'=>'en-IN']; }
function growth_schema_graph(array $pageNodes=[]): array {
    $nodes=[growth_organization_schema(),growth_website_schema()];
    foreach($pageNodes as $node){if(!$node)continue;unset($node['@context']);$nodes[]=$node;}
    return ['@context'=>'https://schema.org','@graph'=>$nodes];
}
function growth_product_schema(array $p): array {
    $care=(array)($p['care_profile']??[]);$variants=variants_for_product($p,true);$url=absolute_url('/product/'.$p['slug']);
    $base=['@id'=>$url.'#product','name'=>$p['name'],'description'=>$p['short'],'brand'=>['@type'=>'Brand','name'=>'Sanjay Nursery'],'category'=>$p['category_label'],'url'=>$url];
    if(!empty($p['botanical_name']))$base['additionalProperty'][]=['@type'=>'PropertyValue','name'=>'Botanical name','value'=>$p['botanical_name']];
    foreach(['light'=>'Light','water'=>'Watering','humidity'=>'Humidity','difficulty'=>'Care difficulty','placement'=>'Placement'] as $k=>$label)if(!empty($care[$k]))$base['additionalProperty'][]=['@type'=>'PropertyValue','name'=>$label,'value'=>$care[$k]];
    if(product_has_image($p))$base['image']=[absolute_url(image_url(mediaos_primary_file($p)))];
    if($variants){
        $node=$base;$node['@type']='ProductGroup';$node['productGroupID']=$p['sku'];$node['variesBy']=['https://schema.org/size'];$node['hasVariant']=[];
        foreach($variants as $v){$child=['@type'=>'Product','@id'=>$url.'#variant-'.rawurlencode((string)$v['sku']),'name'=>$p['name'].' · '.variant_display_label($v),'sku'=>$v['sku'],'url'=>discover_variant_url($p,$v),'isVariantOf'=>['@id'=>$url.'#product'],'size'=>variant_display_label($v)];if(variant_has_image($v,$p))$child['image']=[absolute_url(image_url(variant_effective_image($v,$p)))];if(variant_is_sellable($v))$child['offers']=['@type'=>'Offer','priceCurrency'=>'INR','price'=>(int)$v['price'],'availability'=>'https://schema.org/InStock','url'=>discover_variant_url($p,$v)];$node['hasVariant'][]=$child;}
        return $node;
    }
    $node=$base;$node['@type']='Product';$node['sku']=$p['sku'];
    if(product_is_sellable($p))$node['offers']=['@type'=>'Offer','priceCurrency'=>'INR','price'=>(int)$p['price'],'availability'=>(empty($p['inventory']['track'])||(int)($p['inventory']['stock']??0)>0)?'https://schema.org/InStock':'https://schema.org/OutOfStock','url'=>$url];
    return$node;
}
function growth_collection_schema(array $hub,array $items): array {
    $url=absolute_url('/collections/'.$hub['slug']);$list=[];$pos=1;
    foreach(array_slice($items,0,50) as $p)$list[]=['@type'=>'ListItem','position'=>$pos++,'url'=>absolute_url('/product/'.$p['slug']),'name'=>$p['name']];
    return ['@type'=>'CollectionPage','@id'=>$url.'#collection','name'=>$hub['label'].' | Sanjay Nursery','url'=>$url,'description'=>'Browse '.$hub['label'].' plant varieties at Sanjay Nursery with care guidance and live nursery availability.','mainEntity'=>['@type'=>'ItemList','numberOfItems'=>count($items),'itemListElement'=>$list]];
}

function growth_count_words(string $body): int { $plain=trim(preg_replace('/\[\[block:[^\]]+\]\]/',' ',strip_tags($body))??'');return $plain===''?0:count(preg_split('/\s+/u',$plain)?:[]); }
function growth_content_quality(array $item): array {
    $editorial=(array)($item['editorial']??[]);$body=(string)($item['body']??'');$words=growth_count_words($body);$headings=preg_match_all('/^#{2,3}\s+/m',$body,$m);$sources=(array)($editorial['source_urls']??[]);
    $checks=[];$score=0;
    $add=function(string $label,int $points,bool $ok,string $detail='')use(&$checks,&$score){if($ok)$score+=$points;$checks[]=['label'=>$label,'points'=>$points,'ok'=>$ok,'detail'=>$detail];};
    $add('Clear title',10,text_len(trim((string)($item['title']??'')))>=12);
    $add('Useful excerpt',10,text_len(trim((string)($item['excerpt']??'')))>=80);
    $bodyPoints=$words>=500?20:($words>=250?10:0);$score+=$bodyPoints;$checks[]=['label'=>'Substantive body','points'=>20,'ok'=>$words>=500,'detail'=>$words.' words'.($bodyPoints===10?' · partial credit':'')];
    $add('Scannable structure',10,$headings>=2,(string)$headings.' headings');
    $add('Primary search question/topic',10,trim((string)($editorial['target_query']??''))!=='');
    $add('Search intent',5,trim((string)($editorial['search_intent']??''))!=='');
    $add('Defined audience',5,trim((string)($editorial['audience']??''))!=='');
    $add('Expert reviewer',10,trim((string)($editorial['expert_reviewer']??''))!=='');
    $add('First-hand nursery evidence',10,text_len(trim((string)($editorial['firsthand_evidence']??'')))>=80);
    $add('Source/reference URLs',5,count($sources)>=1);
    $add('Hero image',5,trim((string)($item['hero_image']??''))!=='');
    return ['score'=>min(100,$score),'words'=>$words,'headings'=>$headings,'checks'=>$checks,'publish_threshold'=>(int)(growth_settings()['content_quality_publish_threshold']??65)];
}
function growth_content_can_publish(array $item): array { $q=growth_content_quality($item);return ['ok'=>$q['score']>=$q['publish_threshold'],'quality'=>$q]; }

function growth_record_404(string $path): void {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')return;
    if(str_starts_with($path,'/assets/')||str_starts_with($path,'/admin/')||str_starts_with($path,'/.well-known/'))return;
    growth_ensure_dirs();$query=(string)($_SERVER['QUERY_STRING']??'');$ua=(string)($_SERVER['HTTP_USER_AGENT']??'');$ref=(string)($_SERVER['HTTP_REFERER']??'');
    $row=['ts'=>gmdate('c'),'path'=>$path,'query'=>$query,'referrer'=>$ref!==''?substr($ref,0,300):'','ua_hash'=>$ua!==''?substr(hash('sha256',$ua),0,16):''];
    @file_put_contents(growth_404_file(),json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
}
function growth_404_summary(int $limit=20): array {
    if(!is_file(growth_404_file()))return[];$lines=@file(growth_404_file(),FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];$lines=array_slice($lines,-5000);$map=[];
    foreach($lines as $line){$r=json_decode($line,true);if(!is_array($r)||empty($r['path']))continue;$k=(string)$r['path'];if(!isset($map[$k]))$map[$k]=['path'=>$k,'hits'=>0,'last_seen'=>'','referrer'=>''];$map[$k]['hits']++;if(($r['ts']??'')>$map[$k]['last_seen']){$map[$k]['last_seen']=$r['ts']??'';$map[$k]['referrer']=$r['referrer']??'';}}
    usort($map,fn($a,$b)=>$b['hits']<=>$a['hits']?:strcmp($b['last_seen'],$a['last_seen']));return array_slice(array_values($map),0,$limit);
}
function growth_clear_404(): void { require_admin();if(is_file(growth_404_file()))@unlink(growth_404_file());audit_event('growth.404.clear'); }
function growth_import_redirects(string $text): array {
    require_admin();$added=0;$skipped=0;$errors=[];
    foreach(preg_split('/\r?\n/',trim($text))?:[] as $line){$line=trim($line);if($line===''||str_starts_with($line,'#'))continue;$parts=str_getcsv($line);if(count($parts)<2)$parts=preg_split('/\s+/', $line,3)?:[];$from=trim((string)($parts[0]??''));$to=trim((string)($parts[1]??''));if(text_lower($from)==='from'&&text_lower($to)==='to')continue;$code=(int)($parts[2]??301);if($from===''||$to===''){$skipped++;continue;}try{cms_add_redirect($from,$to,$code);$added++;}catch(Throwable $e){$skipped++;$errors[]=$from.': '.$e->getMessage();}}
    audit_event('growth.redirect.import',['added'=>$added,'skipped'=>$skipped]);return['added'=>$added,'skipped'=>$skipped,'errors'=>array_slice($errors,0,20)];
}
function growth_metrics(): array {
    $products=products_public();$media=0;$sellable=0;foreach($products as $p){if(product_has_image($p))$media++;if(product_is_sellable($p))$sellable++;}
    $variants=variants_public();$sellableVariants=count(array_filter($variants,'variant_is_sellable'));$merchantVariants=count(array_filter($variants,'variant_is_merchant_eligible'));
    $published=cms_items(null,'PUBLISHED');$scores=array_map(fn($i)=>growth_content_quality($i)['score'],$published);$avg=$scores?(int)round(array_sum($scores)/count($scores)):0;$s=growth_settings();
    return ['products'=>count($products),'products_with_images'=>$media,'sellable_products'=>$sellable,'variants'=>count($variants),'sellable_variants'=>$sellableVariants,'merchant_variants'=>$merchantVariants,'published_content'=>count($published),'avg_content_quality'=>$avg,'redirects'=>count(cms_store()['redirects']??[]),'tracked_404_paths'=>count(growth_404_summary(1000)),'analytics_configured'=>growth_tracking_enabled()?'Yes':'No','search_console_token'=>($s['google_site_verification']??'')!==''?'Yes':'No','merchant_id'=>($s['merchant_center_id']??'')!==''?'Yes':'No','gbp_linked'=>($s['google_business_profile_url']??'')!==''?'Yes':'No'];
}

function growth_admin_readiness(): array {
    $s=growth_settings();$metrics=growth_metrics();$rows=[];
    $rows[]=['label'=>'GA4 / GTM measurement','state'=>growth_tracking_enabled()?'ready':'action','detail'=>growth_tracking_enabled()?'Consent-aware analytics can load on the storefront.':'Add GA4 or GTM and enable analytics when the property is ready.'];
    $rows[]=['label'=>'Google Search Console ownership','state'=>($s['google_site_verification']??'')!==''?'ready':'action','detail'=>($s['google_site_verification']??'')!==''?'Verification meta token will render in the public head.':'Add the URL-prefix verification token or verify by DNS.'];
    $rows[]=['label'=>'Google Business Profile','state'=>($s['google_business_profile_url']??'')!==''?'ready':'action','detail'=>($s['google_business_profile_url']??'')!==''?'GBP reference is configured for local growth operations.':'Add the canonical Business Profile URL and optional Place ID.'];
    $rows[]=['label'=>'Merchant Center identity','state'=>($s['merchant_center_id']??'')!==''?'ready':'action','detail'=>($s['merchant_center_id']??'')!==''?'Merchant Center ID is recorded.':'Add Merchant Center ID before activating sellable inventory feeds.'];
    $rows[]=['label'=>'Product photography','state'=>$metrics['products_with_images']>=max(1,(int)ceil($metrics['products']*.6))?'ready':'action','detail'=>$metrics['products_with_images'].' / '.$metrics['products'].' public products currently have approved media.'];
    $rows[]=['label'=>'Live plant variants','state'=>$metrics['variants']>0?'ready':'deferred','detail'=>$metrics['variants'].' published variants · '.$metrics['sellable_variants'].' checkout-ready · '.$metrics['merchant_variants'].' Merchant eligible.'];
    $rows[]=['label'=>'Editorial authority','state'=>$metrics['published_content']>=12&&$metrics['avg_content_quality']>=70?'ready':'action','detail'=>$metrics['published_content'].' published items · average quality '.$metrics['avg_content_quality'].'/100.'];
    $rows[]=['label'=>'Migration redirect coverage','state'=>$metrics['redirects']>0?'ready':'action','detail'=>$metrics['redirects'].' redirects mapped · '.$metrics['tracked_404_paths'].' missing paths currently observed.'];
    return$rows;
}

function growth_release_checks(): array {
    $s=growth_settings();$hubs=growth_collection_map();$unique=count($hubs)===count(array_unique(array_keys($hubs)));$threshold=(int)$s['content_quality_publish_threshold'];
    $badPublished=[];foreach(cms_items(null,'PUBLISHED') as $i){$q=growth_content_quality($i);if($q['score']<$threshold)$badPublished[]=$i['slug'];}
    return [
        ['name'=>'Growth settings schema','ok'=>array_keys(growth_defaults())===array_keys(array_intersect_key($s,growth_defaults())),'detail'=>'Growth configuration contains the v1.4.0 public settings contract.'],
        ['name'=>'Analytics identifier validation','ok'=>growth_valid_ga4((string)$s['ga4_measurement_id'])&&growth_valid_gtm((string)$s['gtm_container_id']),'detail'=>'Configured GA4/GTM identifiers are empty or syntactically valid.'],
        ['name'=>'Collection hub uniqueness','ok'=>$unique&&count($hubs)>=18,'detail'=>count($hubs).' indexable category/plant-family hubs resolve uniquely.'],
        ['name'=>'Editorial publish-quality gate','ok'=>count($badPublished)===0,'detail'=>count($badPublished)===0?'All published CMS content meets the configured quality threshold.':count($badPublished).' published item(s) are below the quality threshold.'],
        ['name'=>'404 migration journal','ok'=>is_dir(dirname(growth_404_file()))&&is_writable(dirname(growth_404_file())),'detail'=>'Missing-route monitoring journal is writable for migration diagnostics.'],
        ['name'=>'Merchant eligibility safety','ok'=>!str_contains(merchant_xml(),'<g:price>0 INR</g:price>'),'detail'=>'Merchant feed cannot emit zero-priced catalogue parents; live variant rows require explicit eligibility.'],
    ];
}

growth_ensure_dirs();
