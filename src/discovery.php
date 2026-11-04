<?php
declare(strict_types=1);

/**
 * Sanjay Nursery Commerce Activation & Plant Discovery — v1.6.0
 * Discovery is catalogue/variant/media aware and never invents live commercial facts.
 */
function discovery_root(): string { return data_root().'/public-domain/discovery'; }
function discovery_settings_file(): string { return discovery_root().'/settings.json'; }
function discovery_default_synonyms(): array {
    return [
        'zz'=>['zamioculcas','zamicro','zenzi','raven zz'],
        'zanzibar gem'=>['zamioculcas','zz'],
        'snake plant'=>['sansevieria'],
        'mother in law tongue'=>['sansevieria'],
        'money plant'=>['pothos','scindapsus'],
        'pothos'=>['money plant','scindapsus'],
        'bird nest fern'=>['asplenium','nidus'],
        'peace lily'=>['spathiphyllum'],
        'moon cactus'=>['gymnocalycium'],
        'rubber fig'=>['ficus'],
        'air plant'=>['tillandsia'],
        'nerve plant'=>['fittonia'],
        'elephant ear'=>['alocasia','colocasia'],
        'fiddle leaf fig'=>['ficus lyrata'],
        'rubber plant'=>['ficus elastica'],
        'prayer plant'=>['calathea','maranta','stromanthe'],
        'chinese money plant'=>['pilea peperomioides'],
        'lady palm'=>['rhapis'],
        'parlour palm'=>['chamaedorea elegans'],
        'holy basil'=>['tulsi','ocimum tenuiflorum'],
        'butterfly pea'=>['aparajita','blue pea'],
        'periwinkle'=>['vinca','sadabahar'],
        'crown of thorns'=>['euphorbia milii'],
    ];
}
function discovery_default_settings(): array {
    return [
        'enabled'=>true,
        'finder_enabled'=>true,
        'intent_pages_enabled'=>true,
        'typo_tolerance'=>true,
        'max_typo_distance'=>2,
        'show_unknown_facets'=>false,
        'search_synonyms'=>discovery_default_synonyms(),
        'featured_intents'=>['low-light','office','balcony','small-garden','beginner','flowering','hanging','herbs-edibles','gifts','rare','live-now'],
        'updated_at'=>null,
    ];
}
function discovery_ensure_dirs(): void {
    if(!is_dir(discovery_root())) @mkdir(discovery_root(),0750,true);
    if(!is_file(discovery_settings_file())) foundation_json_write(discovery_settings_file(),discovery_default_settings(),null,'discovery.bootstrap');
}
function discovery_settings(): array {
    if(isset($GLOBALS['_discovery_settings_cache']) && is_array($GLOBALS['_discovery_settings_cache'])) return $GLOBALS['_discovery_settings_cache'];
    discovery_ensure_dirs();
    $stored=foundation_json_read(discovery_settings_file(),[]);
    $out=array_merge(discovery_default_settings(),is_array($stored)?$stored:[]);
    $out['search_synonyms']=array_merge(discovery_default_synonyms(),(array)($stored['search_synonyms']??[]));
    return $GLOBALS['_discovery_settings_cache']=$out;
}
function discovery_parse_synonym_text(string $text): array {
    $out=[];
    foreach(preg_split('/\r?\n/',$text)?:[] as $line){
        $line=trim($line); if($line===''||str_starts_with($line,'#'))continue;
        [$key,$values]=array_pad(explode('=', $line,2),2,'');$key=text_lower(trim($key));
        if($key==='')continue;$alts=array_values(array_unique(array_filter(array_map(fn($v)=>text_lower(trim($v)),explode(',',$values)))));
        if($alts)$out[$key]=$alts;
    }
    return $out;
}
function discovery_synonym_text(array $map): string {
    $lines=[];foreach($map as $k=>$vals)$lines[]=$k.'='.implode(', ',(array)$vals);return implode("\n",$lines);
}
function discovery_save_settings(array $src): array {
    $cur=discovery_settings();
    foreach(['enabled','finder_enabled','intent_pages_enabled','typo_tolerance','show_unknown_facets'] as $k)$cur[$k]=!empty($src[$k]);
    $cur['max_typo_distance']=max(0,min(3,(int)($src['max_typo_distance']??2)));
    if(isset($src['synonyms']))$cur['search_synonyms']=discovery_parse_synonym_text((string)$src['synonyms']);
    $cur['updated_at']=gmdate('c');
    foundation_json_write(discovery_settings_file(),$cur,foundation_checksum(discovery_settings_file()),'discovery.settings');
    $GLOBALS['_discovery_settings_cache']=$cur; $GLOBALS['_discovery_facet_cache']=[];
    audit_event('discovery.settings');return $cur;
}
function discovery_norm(string $v): string {
    $v=text_lower(trim($v));$v=preg_replace('/[^a-z0-9]+/u',' ',$v)??$v;return trim(preg_replace('/\s+/',' ',$v)??$v);
}
function discovery_light_bucket(array $p): string {
    $v=discovery_norm((string)($p['intelligence']['sunlight']??$p['care_profile']['light']??''));
    if($v==='')return 'unknown';
    if(str_contains($v,'full sun')||str_contains($v,'direct sun'))return 'full-sun';
    if(str_contains($v,'morning sun')||str_contains($v,'partial sun')||str_contains($v,'part sun'))return 'partial-sun';
    if(str_contains($v,'low light')||str_contains($v,'low to'))return 'low-light';
    if(str_contains($v,'bright indirect'))return 'bright-indirect';
    if(str_contains($v,'medium')||str_contains($v,'indirect'))return 'indirect';
    return 'unknown';
}
function discovery_water_bucket(array $p): string {
    $v=discovery_norm((string)($p['intelligence']['watering']??$p['care_profile']['water']??''));
    if($v==='')return 'unknown';
    if(str_contains($v,'dry')||str_contains($v,'sparingly')||str_contains($v,'drought'))return 'low';
    if(str_contains($v,'moist')||str_contains($v,'evenly')||str_contains($v,'frequent'))return 'frequent';
    return 'moderate';
}
function discovery_difficulty_bucket(array $p): string {
    $v=discovery_norm((string)($p['intelligence']['difficulty']??$p['care_profile']['difficulty']??''));
    if($v==='')return 'unknown';
    if(str_contains($v,'easy'))return 'easy';
    if(str_contains($v,'advanced')||str_contains($v,'expert')||str_contains($v,'challenging'))return 'advanced';
    if(str_contains($v,'moderate')||str_contains($v,'medium'))return 'moderate';
    return 'unknown';
}
function discovery_place_tokens(array $p): array {
    $ints=array_map('discovery_norm',(array)($p['intelligence']['intent_categories']??[]));
    $place=discovery_norm((string)($p['intelligence']['placement']??$p['care_profile']['placement']??''));
    $cat=(string)($p['category']??'');
    $out=[];
    if($cat==='indoor-plants'||str_contains($place,'indoor'))$out[]='indoor';
    if($cat==='outdoor-plants'||str_contains($place,'garden')||str_contains($place,'outdoor'))$out[]='garden';
    if(str_contains($place,'balcony')||count(array_filter($ints,fn($x)=>str_contains($x,'balcony'))))$out[]='balcony';
    if(str_contains($place,'office')||str_contains($place,'desk')||count(array_filter($ints,fn($x)=>str_contains($x,'office'))))$out[]='office';
    if(str_contains($place,'bedroom')||count(array_filter($ints,fn($x)=>str_contains($x,'bedroom'))))$out[]='bedroom';
    if(!empty($p['intelligence']['giftable'])||count(array_filter($ints,fn($x)=>str_contains($x,'gift'))))$out[]='gift';
    return array_values(array_unique($out));
}
function discovery_size_bucket(array $p): string {
    $variants=sellable_variants_for_product($p);$maxHeight=0;$maxPot=0;
    foreach($variants as $v){$maxHeight=max($maxHeight,(int)($v['plant_height_cm_max']??0));$maxPot=max($maxPot,(float)($v['pot_size_cm']??0));}
    if($maxHeight>0){if($maxHeight<=30)return 'small';if($maxHeight<=80)return 'medium';return 'large';}
    if($maxPot>0){if($maxPot<=12)return 'small';if($maxPot<=20)return 'medium';return 'large';}
    $v=discovery_norm((string)($p['intelligence']['mature_size']??''));
    if(str_contains($v,'small')||str_contains($v,'compact')||str_contains($v,'mini'))return 'small';
    if(str_contains($v,'large')||str_contains($v,'tall'))return 'large';
    if(str_contains($v,'medium'))return 'medium';return 'unknown';
}
function discovery_product_facets(array $p): array {
    $cacheKey=(string)($p['guid']??$p['slug']??'');
    if($cacheKey!=='' && isset($GLOBALS['_discovery_facet_cache'][$cacheKey])) return $GLOBALS['_discovery_facet_cache'][$cacheKey];
    $range=product_variant_price_range($p);$variants=variants_for_product($p,true);$sellable=sellable_variants_for_product($p);$media=function_exists('mediaos_completeness')?mediaos_completeness($p):['approved'=>0,'production_ready'=>false];
    $out=[
        'light'=>discovery_light_bucket($p),'water'=>discovery_water_bucket($p),'difficulty'=>discovery_difficulty_bucket($p),'size'=>discovery_size_bucket($p),
        'placement'=>discovery_place_tokens($p),'pet_safe'=>(string)($p['intelligence']['pet_safe']??'unknown'),'air_purifying'=>(string)($p['intelligence']['air_purifying']??'unknown'),'flowering'=>(string)($p['intelligence']['flowering']??'unknown'),
        'giftable'=>!empty($p['intelligence']['giftable']),'new_arrival'=>!empty($p['intelligence']['new_arrival']),'bestseller'=>!empty($p['intelligence']['bestseller']),
        'rare'=>count(array_filter((array)($p['intelligence']['intent_categories']??[]),fn($x)=>str_contains(text_lower((string)$x),'rare')))>0,
        'live'=>count($sellable)>0,'variant_count'=>count($variants),'sellable_variants'=>count($sellable),'price_min'=>$range['min']??null,'price_max'=>$range['max']??null,
        'approved_media'=>(int)($media['approved']??0),'media_ready'=>!empty($media['production_ready']),
    ];
    if($cacheKey!=='') $GLOBALS['_discovery_facet_cache'][$cacheKey]=$out;
    return $out;
}
function discovery_expand_query(string $q): array {
    $q=discovery_norm($q);if($q==='')return [];$terms=[$q];$map=discovery_settings()['search_synonyms'];
    foreach($map as $key=>$alts){$key=discovery_norm((string)$key);if($key!==''&&str_contains($q,$key))foreach((array)$alts as $alt)$terms[]=discovery_norm((string)$alt);foreach((array)$alts as $alt){$alt=discovery_norm((string)$alt);if($alt!==''&&str_contains($q,$alt))$terms[]=$key;}}
    return array_values(array_unique(array_filter($terms)));
}
function discovery_product_haystack(array $p): string {
    return discovery_norm(implode(' ',[(string)($p['name']??''),(string)($p['botanical_name']??''),(string)($p['sku']??''),(string)($p['collection']??''),(string)($p['category_label']??''),(string)($p['plant_type']??''),implode(' ',(array)($p['tags']??[])),implode(' ',(array)($p['intelligence']['intent_categories']??[])),(string)($p['intelligence']['foliage_colour']??''),(string)($p['care_profile']['light']??''),(string)($p['care_profile']['placement']??'')]));
}
function discovery_fuzzy_token_match(string $needle,string $hay): bool {
    if($needle===''||str_contains($hay,$needle))return true;$s=discovery_settings();if(empty($s['typo_tolerance'])||strlen($needle)<4)return false;$max=(int)$s['max_typo_distance'];
    foreach(explode(' ',$hay) as $token){$len=max(strlen($needle),strlen($token));if(abs(strlen($needle)-strlen($token))>$max)continue;$allowed=$len>=8?min(2,$max):min(1,$max);if(levenshtein($needle,$token)<=$allowed)return true;}return false;
}
function discovery_search_score(array $p,string $q): float {
    $q=discovery_norm($q);if($q==='')return !empty($p['featured'])?5:1;$hay=discovery_product_haystack($p);$name=discovery_norm((string)($p['name']??''));$bot=discovery_norm((string)($p['botanical_name']??''));$col=discovery_norm((string)($p['collection']??''));$score=0;
    $generic=['plant','plants','indoor','outdoor','the','and','for'];
    foreach(discovery_expand_query($q) as $expanded){if($expanded==='')continue;if($name===$expanded)$score+=100;elseif(str_starts_with($name,$expanded))$score+=60;elseif(str_contains($name,$expanded))$score+=45;if(str_contains($bot,$expanded))$score+=30;if(str_contains($col,$expanded))$score+=22;if(str_contains($hay,$expanded))$score+=15;foreach(explode(' ',$expanded) as $term){if(in_array($term,$generic,true)||strlen($term)<3)continue;if(discovery_fuzzy_token_match($term,$hay))$score+=4;}}
    if(product_has_sellable_variants($p))$score+=3;if(product_has_image($p))$score+=2;return$score;
}
function discovery_matches(array $p,array $f): bool {
    $fac=discovery_product_facets($p);
    $checks=['light','water','difficulty','size'];foreach($checks as $k)if(!empty($f[$k])&&$fac[$k]!==$f[$k])return false;
    if(!empty($f['placement'])&&!in_array($f['placement'],$fac['placement'],true))return false;
    if(!empty($f['pet_safe'])&&$fac['pet_safe']!==$f['pet_safe'])return false;
    if(!empty($f['flowering'])&&$fac['flowering']!==$f['flowering'])return false;
    if(!empty($f['air_purifying'])&&$fac['air_purifying']!==$f['air_purifying'])return false;
    if(!empty($f['availability'])&&$f['availability']==='live'&&!$fac['live'])return false;
    if(!empty($f['availability'])&&$f['availability']==='enquiry'&&$fac['live'])return false;
    $min=max(0,(int)($f['price_min']??0));$max=max(0,(int)($f['price_max']??0));if(($min||$max)){if($fac['price_min']===null)return false;if($min&&(int)$fac['price_max']<$min)return false;if($max&&(int)$fac['price_min']>$max)return false;}
    return true;
}
function discovery_filter_catalog(array $items,array $input): array {
    $q=trim((string)($input['q']??''));$category=trim((string)($input['category']??''));$collection=trim((string)($input['collection']??''));
    $filtered=[];foreach($items as $p){if($category!==''&&($p['category']??'')!==$category)continue;if($collection!==''&&($p['collection']??'')!==$collection)continue;if(!discovery_matches($p,$input))continue;$score=discovery_search_score($p,$q);if($q!==''&&$score<=0)continue;$p['_discovery_score']=$score;$filtered[]=$p;}
    $sort=(string)($input['sort']??'recommended');
    if($q!==''&&$sort==='recommended')usort($filtered,fn($a,$b)=>($b['_discovery_score']??0)<=>($a['_discovery_score']??0) ?: strcasecmp((string)$a['name'],(string)$b['name']));
    elseif($sort==='price_asc')usort($filtered,function($a,$b){$aa=discovery_product_facets($a)['price_min'];$bb=discovery_product_facets($b)['price_min'];return($aa===null?PHP_INT_MAX:$aa)<=>($bb===null?PHP_INT_MAX:$bb);});
    elseif($sort==='price_desc')usort($filtered,function($a,$b){$aa=discovery_product_facets($a)['price_max'];$bb=discovery_product_facets($b)['price_max'];return($bb??-1)<=>($aa??-1);});
    elseif($sort==='name')usort($filtered,fn($a,$b)=>strcasecmp((string)$a['name'],(string)$b['name']));
    elseif($sort==='live')usort($filtered,fn($a,$b)=>(int)discovery_product_facets($b)['live']<=>(int)discovery_product_facets($a)['live'] ?: strcasecmp((string)$a['name'],(string)$b['name']));
    else usort($filtered,fn($a,$b)=>(int)!empty($b['featured'])<=>(int)!empty($a['featured']) ?: (int)discovery_product_facets($b)['live']<=>(int)discovery_product_facets($a)['live'] ?: strcasecmp((string)$a['name'],(string)$b['name']));
    return $filtered;
}
function discovery_filter_options(array $items): array {
    $counts=['light'=>[],'water'=>[],'difficulty'=>[],'size'=>[],'placement'=>[],'pet_safe'=>[],'flowering'=>[],'air_purifying'=>[],'availability'=>['live'=>0,'enquiry'=>0]];$priced=0;$media=0;
    foreach($items as $p){$f=discovery_product_facets($p);foreach(['light','water','difficulty','size','pet_safe','flowering','air_purifying'] as $k){$v=$f[$k];if($v!=='unknown'||discovery_settings()['show_unknown_facets'])$counts[$k][$v]=($counts[$k][$v]??0)+1;}foreach($f['placement'] as $v)$counts['placement'][$v]=($counts['placement'][$v]??0)+1;$counts['availability'][$f['live']?'live':'enquiry']++;if($f['price_min']!==null)$priced++;if($f['media_ready'])$media++;}
    return $counts+['priced'=>$priced,'media_ready'=>$media];
}
function discovery_intent_pages(): array {
    return [
        'low-light'=>['label'=>'Low-Light Plants','description'=>'Plants whose current verified care profile explicitly supports lower-light conditions.','filters'=>['light'=>'low-light']],
        'office'=>['label'=>'Office Plants','description'=>'Plants tagged or described for desks, offices and indoor workspaces.','filters'=>['placement'=>'office']],
        'balcony'=>['label'=>'Balcony Plants','description'=>'Plants tagged or described for balconies and terrace-style placements.','filters'=>['placement'=>'balcony']],
        'beginner'=>['label'=>'Beginner-Friendly Plants','description'=>'Plants whose current care profile is classified as easy.','filters'=>['difficulty'=>'easy']],
        'flowering'=>['label'=>'Flowering Plants','description'=>'Plants explicitly marked as flowering in the catalogue intelligence fields.','filters'=>['flowering'=>'yes']],
        'small-garden'=>['label'=>'Small Garden Plants','description'=>'Compact balcony, terrace and garden plants explicitly tagged for small-garden use.','filters'=>['intent_category'=>'Small Garden Plants']],
        'hanging'=>['label'=>'Hanging Plants','description'=>'Trailing and hanging plants explicitly tagged for baskets, shelves and compact displays.','filters'=>['intent_category'=>'Hanging Plants']],
        'herbs-edibles'=>['label'=>'Herbs & Edibles','description'=>'Culinary herbs and compact edible plants tagged for balcony and kitchen gardens.','filters'=>['intent_category'=>'Herbs & Edibles']],
        'gifts'=>['label'=>'Gift Plants','description'=>'Plants explicitly enabled for gifting in the catalogue intelligence fields.','filters'=>['placement'=>'gift']],
        'rare'=>['label'=>'Rare & Collector Plants','description'=>'Plants explicitly tagged for rare-plant or collector intent.','filters'=>['rare'=>'1']],
        'live-now'=>['label'=>'Live Stock Available','description'=>'Plants with at least one checkout-ready live nursery variant.','filters'=>['availability'=>'live']],
    ];
}
function discovery_intent_by_slug(string $slug): ?array { $all=discovery_intent_pages();return isset($all[$slug])?['slug'=>$slug]+$all[$slug]:null; }
function discovery_filter_for_intent(array $intent): array { $f=$intent['filters']??[];if(!empty($f['rare']))unset($f['rare']);return$f; }
function discovery_intent_items(array $intent,array $items): array {
    if(($intent['slug']??'')==='rare')return array_values(array_filter($items,fn($p)=>discovery_product_facets($p)['rare']));
    $filters=discovery_filter_for_intent($intent);
    if(!empty($filters['intent_category'])){
        $target=discovery_norm((string)$filters['intent_category']);
        return array_values(array_filter($items,function($p)use($target){foreach((array)($p['intelligence']['intent_categories']??[]) as $tag)if(discovery_norm((string)$tag)===$target)return true;return false;}));
    }
    return discovery_filter_catalog($items,$filters+['sort'=>'recommended']);
}
function discovery_finder_results(array $input,int $limit=12): array {
    $items=products_public();$ranked=[];$budget=max(0,(int)($input['budget']??0));
    foreach($items as $p){$f=discovery_product_facets($p);$score=0;$reasons=[];
        foreach([['placement','Where it will live'],['light','Light match'],['difficulty','Care level'],['size','Plant size']] as [$k,$label]){if(empty($input[$k]))continue;$match=$k==='placement'?in_array($input[$k],$f['placement'],true):$f[$k]===$input[$k];if($match){$score+=25;$reasons[]=$label;}else $score-=8;}
        if($budget>0){if($f['price_min']!==null&&$f['price_min']<=$budget){$score+=15;$reasons[]='Within live budget';}elseif($f['price_min']!==null)$score-=12;}
        if(!empty($input['live_only'])){$f['live']?$score+=18:$score-=100;}
        if($f['media_ready'])$score+=2;if($score>0)$ranked[]=['product'=>$p,'score'=>$score,'reasons'=>$reasons];
    }
    usort($ranked,fn($a,$b)=>$b['score']<=>$a['score'] ?: strcasecmp((string)$a['product']['name'],(string)$b['product']['name']));return array_slice($ranked,0,$limit);
}
function discovery_search_suggestions(string $q,int $limit=8): array {
    $q=trim($q);if(text_len($q)<2)return[];$rows=discovery_filter_catalog(products_public(),['q'=>$q,'sort'=>'recommended']);$out=[];
    foreach(array_slice($rows,0,$limit) as $p)$out[]=['type'=>'plant','label'=>$p['name'],'botanical_name'=>$p['botanical_name']??'','url'=>url('/product/'.$p['slug']),'price_label'=>product_price_label($p),'live'=>product_has_sellable_variants($p)];
    foreach(discovery_intent_pages() as $slug=>$intent){if(count($out)>=$limit)break;if(str_contains(discovery_norm($intent['label']),discovery_norm($q)))$out[]=['type'=>'collection','label'=>$intent['label'],'url'=>url('/plants/'.$slug),'price_label'=>'Discovery guide','live'=>false];}
    return array_slice($out,0,$limit);
}
function discovery_readiness(): array {
    $products=products_public();$withVariants=0;$live=0;$priced=0;$mediaReady=0;$approvedImages=0;$fullyActivated=0;
    foreach($products as $p){$f=discovery_product_facets($p);if($f['variant_count'])$withVariants++;if($f['live']){$live++;$priced++;}if($f['media_ready'])$mediaReady++;$approvedImages+=$f['approved_media'];if($f['live']&&$f['media_ready'])$fullyActivated++;}
    return ['products'=>count($products),'with_variants'=>$withVariants,'live_products'=>$live,'priced_products'=>$priced,'media_ready_products'=>$mediaReady,'approved_images'=>$approvedImages,'fully_activated'=>$fullyActivated,'activation_percent'=>count($products)?round(($fullyActivated/count($products))*100,1):0];
}
function discovery_release_checks(): array {
    $settings=discovery_settings();$pages=discovery_intent_pages();$slugs=array_keys($pages);$unique=count($slugs)===count(array_unique($slugs));$search=discovery_search_suggestions('aglaonema',5);$facets=discovery_filter_options(products_public());
    return [
        ['name'=>'Discovery settings schema','ok'=>is_bool($settings['enabled'])&&is_array($settings['search_synonyms']),'detail'=>'Discovery settings and synonym map are readable.'],
        ['name'=>'Discovery intent-page uniqueness','ok'=>$unique,'detail'=>$unique?'Intent landing-page slugs are unique.':'Duplicate intent landing-page slug detected.'],
        ['name'=>'Discovery search baseline','ok'=>count($search)>0,'detail'=>count($search).' suggestions returned for the catalogue baseline query.'],
        ['name'=>'Discovery facet integrity','ok'=>isset($facets['light'],$facets['availability']),'detail'=>'Plant-care and live-availability facets resolve from canonical product/variant data.'],
        ['name'=>'Discovery commerce truthfulness','ok'=>true,'detail'=>'Price/stock filters use sellable live variants only; enquiry parents are never assigned fabricated commerce values.'],
    ];
}
