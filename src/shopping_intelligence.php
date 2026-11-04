<?php
declare(strict_types=1);

/**
 * Sanjay Nursery Shopping Intelligence & Personalisation Engine — v1.5.0
 * First-party, anonymous, file-backed staging implementation.
 * This module must fail open: storefront/cart/checkout remain usable if intelligence is disabled or unavailable.
 */

function si_root(): string { return data_root().'/private-domain/shopping-intelligence'; }
function si_settings_file(): string { return si_root().'/settings.json'; }
function si_profiles_file(): string { return si_root().'/profiles.json'; }
function si_rules_file(): string { return si_root().'/rules.json'; }
function si_relationships_file(): string { return si_root().'/relationships.json'; }
function si_experiments_file(): string { return si_root().'/experiments.json'; }
function si_events_dir(): string { return si_root().'/events'; }
function si_cleanup_file(): string { return si_root().'/cleanup.json'; }

function si_default_event_weights(): array {
    return [
        'product_impression'=>1,'product_click'=>3,'product_view'=>4,'engagement_20s'=>4,'gallery_view'=>3,
        'search_started'=>1,'search_submitted'=>5,'search_result_click'=>4,'search_no_result'=>5,'search_refined'=>3,
        'filter_used'=>3,'filter_removed'=>-1,'sort_used'=>1,'pagination'=>1,
        'wishlist_add'=>8,'wishlist_remove'=>-2,'cart_add'=>12,'cart_remove'=>-3,'cart_quantity_change'=>3,
        'purchase_completed'=>25,'recommendation_clicked'=>4,'cart_recommendation_add'=>12,'repeated_ignore'=>-2,
        'variant_selected'=>4,'checkout_started'=>8,'checkout_abandoned'=>-2,'coupon_applied'=>2,'coupon_failed'=>-1,
        'shipping_selected'=>2,'payment_started'=>8,'homepage_view'=>1,'category_view'=>2,'collection_view'=>2,'page_view'=>1,
    ];
}

function si_default_settings(): array {
    return [
        'enable_engine'=>true,
        'enable_anonymous_tracking'=>true,
        'enable_personalisation'=>true,
        'enable_search_learning'=>true,
        'enable_cart_intelligence'=>true,
        'enable_product_recommendations'=>true,
        'enable_session_intent'=>true,
        'enable_long_term_interests'=>true,
        'enable_recommendation_analytics'=>true,
        'enable_ai_insights'=>false,
        'consent_mode'=>'opt_in',
        'raw_event_retention_days'=>90,
        'profile_retention_days'=>180,
        'aggregated_retention_days'=>365,
        'visitor_cookie_days'=>180,
        'minimum_events_before_profiling'=>2,
        'interest_decay_days'=>30,
        'maximum_visitor_history'=>120,
        'debug_mode'=>false,
        'minimum_order_enabled'=>false,
        'minimum_order_value'=>499,
        'progress_bar'=>true,
        'show_remaining_amount'=>true,
        'quick_add'=>true,
        'maximum_recommendations'=>4,
        'maximum_recommendation_frequency'=>5,
        'cart_booster_tolerance_percent'=>30,
        'use_visitor_interests'=>true,
        'use_product_compatibility'=>true,
        'use_margin'=>false,
        'hide_purchased_products'=>true,
        'hide_out_of_stock'=>true,
        'allow_accessories'=>true,
        'allow_plants'=>true,
        'recommendation_weights'=>[
            'current_session_intent'=>35,
            'long_term_interest'=>25,
            'product_relationship'=>15,
            'price_affinity'=>10,
            'product_performance'=>10,
            'business_priority'=>5,
        ],
        'event_weights'=>si_default_event_weights(),
        'modules'=>[
            'recommended_for_you'=>true,'recently_viewed'=>true,'similar_plants'=>true,'pairs_well_with'=>true,
            'customers_also_bought'=>true,'complete_the_look'=>true,'plant_care_essentials'=>true,'trending'=>true,
            'best_sellers'=>true,'cart_completion'=>true,
        ],
    ];
}

function si_ensure_dirs(): void {
    foreach([si_root(),si_events_dir()] as $d){ if(!is_dir($d))@mkdir($d,0750,true); @chmod($d,0750); }
    foreach([[si_settings_file(),si_default_settings()],[si_profiles_file(),[]],[si_rules_file(),[]],[si_relationships_file(),[]],[si_experiments_file(),[]],[si_cleanup_file(),['last_cleanup_at'=>null]]] as [$file,$default]){
        if(!is_file($file)) foundation_json_write($file,$default,null,'shopping-intelligence.bootstrap');
    }
}

function si_settings(): array {
    si_ensure_dirs(); $stored=foundation_json_read(si_settings_file(),[]); $defaults=si_default_settings();
    $stored['event_weights']=array_merge($defaults['event_weights'],(array)($stored['event_weights']??[]));
    $stored['recommendation_weights']=array_merge($defaults['recommendation_weights'],(array)($stored['recommendation_weights']??[]));
    $stored['modules']=array_merge($defaults['modules'],(array)($stored['modules']??[]));
    return array_merge($defaults,$stored);
}

function si_bool(array $src,string $key,bool $default=false): bool { return array_key_exists($key,$src) ? !empty($src[$key]) : $default; }
function si_save_settings(array $input): array {
    $current=si_settings();$scope=(string)($input['_settings_scope']??'all');
    $globalBools=['enable_engine','enable_anonymous_tracking','enable_personalisation','enable_search_learning','enable_cart_intelligence','enable_product_recommendations','enable_session_intent','enable_long_term_interests','enable_recommendation_analytics','enable_ai_insights','debug_mode'];
    $cartBools=['minimum_order_enabled','progress_bar','show_remaining_amount','quick_add','use_visitor_interests','use_product_compatibility','use_margin','hide_purchased_products','hide_out_of_stock','allow_accessories','allow_plants'];
    if(in_array($scope,['all','settings'],true)) foreach($globalBools as $k) $current[$k]=!empty($input[$k]);
    if(in_array($scope,['all','cart'],true)) foreach($cartBools as $k) $current[$k]=!empty($input[$k]);
    if(in_array($scope,['all','settings'],true)){$mode=(string)($input['consent_mode']??$current['consent_mode']);$current['consent_mode']=in_array($mode,['opt_in','analytics_opt_in','essential_only'],true)?$mode:'opt_in';}
    $boundsMap=['raw_event_retention_days'=>[1,3650],'profile_retention_days'=>[1,3650],'aggregated_retention_days'=>[30,3650],'visitor_cookie_days'=>[1,730],'minimum_events_before_profiling'=>[1,100],'interest_decay_days'=>[1,365],'maximum_visitor_history'=>[20,1000],'minimum_order_value'=>[0,1000000],'maximum_recommendations'=>[1,12],'maximum_recommendation_frequency'=>[1,50],'cart_booster_tolerance_percent'=>[0,100]];
    foreach($boundsMap as $k=>$bounds){if(!array_key_exists($k,$input))continue;$v=(int)$input[$k];$current[$k]=max($bounds[0],min($bounds[1],$v));}
    if(in_array($scope,['all','settings','scoring'],true)&&isset($input['event_weights'])&&is_array($input['event_weights']))foreach($current['event_weights'] as $k=>$v){$n=(int)($input['event_weights'][$k]??$v);$current['event_weights'][$k]=max(-50,min(100,$n));}
    if(in_array($scope,['all','settings','scoring'],true)&&isset($input['recommendation_weights'])&&is_array($input['recommendation_weights'])){
        $sum=0;$next=[];foreach($current['recommendation_weights'] as $k=>$v){$n=max(0,min(100,(int)($input['recommendation_weights'][$k]??$v)));$next[$k]=$n;$sum+=$n;}
        if($sum!==100)throw new RuntimeException('Recommendation weights must total exactly 100%.');$current['recommendation_weights']=$next;
    }
    if(in_array($scope,['all','settings'],true)&&isset($input['modules'])&&is_array($input['modules']))foreach($current['modules'] as $k=>$v)$current['modules'][$k]=!empty($input['modules'][$k]);
    foundation_json_write(si_settings_file(),$current,foundation_checksum(si_settings_file()),'shopping-intelligence.settings');audit_event('shopping_intelligence.settings');return$current;
}

function si_event_types(): array {
    return ['page_view','homepage_view','category_view','collection_view','product_impression','product_view','product_click','search_started','search_submitted','search_result_click','search_no_result','search_refined','filter_used','filter_removed','sort_used','pagination','recommendation_seen','recommendation_clicked','gallery_view','image_zoom','variant_selected','quantity_changed','wishlist_add','wishlist_remove','cart_add','cart_remove','cart_quantity_change','cart_open','cart_recommendation_seen','cart_recommendation_add','minimum_order_progress','checkout_started','checkout_abandoned','coupon_applied','coupon_failed','shipping_selected','payment_started','purchase_completed','engagement_20s','repeated_ignore'];
}
function si_valid_visitor_id(string $v): bool { return (bool)preg_match('/^V-[A-Z0-9]{8,32}$/',$v); }
function si_valid_session_id(string $v): bool { return (bool)preg_match('/^S-[A-Z0-9]{8,32}$/',$v); }
function si_clean_text(mixed $v,int $max=160): string { $s=trim(strip_tags((string)$v));if(text_len($s)>$max)$s=text_substr($s,0,$max);return$s; }
function si_safe_props(array $props): array {
    $allow=['path','referrer_path','product_slug','product_guid','product_sku','variant_guid','variant_sku','category','collection','search_query','query','result_count','filter_name','filter_value','sort','price','cart_total','cart_gap','quantity','recommendation_id','algorithm','placement','experiment_variant','reason','module','duration_seconds','minimum','consent_analytics','consent_personalisation','consent_marketing','source'];
    $out=[];foreach($allow as $k)if(array_key_exists($k,$props)){$v=$props[$k];if(is_bool($v)||is_int($v)||is_float($v))$out[$k]=$v;else$out[$k]=si_clean_text($v,180);}return$out;
}
function si_consent_normalize(array $c): array { return ['essential'=>true,'analytics'=>!empty($c['analytics']),'personalisation'=>!empty($c['personalisation']),'marketing'=>!empty($c['marketing'])]; }
function si_event_allowed_by_consent(string $type,array $consent): bool {
    $settings=si_settings(); if(!$settings['enable_engine']||!$settings['enable_anonymous_tracking'])return false;
    if($settings['consent_mode']==='essential_only')return false;
    if(!empty($consent['analytics']))return true;
    if(!empty($consent['personalisation']) && in_array($type,['product_view','product_click','search_submitted','search_refined','filter_used','wishlist_add','wishlist_remove','cart_add','cart_remove','variant_selected','recommendation_clicked'],true))return true;
    return false;
}
function si_event_path(?string $day=null): string { return si_events_dir().'/events-'.($day?:gmdate('Ymd')).'.ndjson'; }
function si_append_event(array $row): void {
    si_ensure_dirs();$path=si_event_path();$fh=@fopen($path,'ab');if(!$fh)throw new RuntimeException('Unable to open intelligence event store.');if(!flock($fh,LOCK_EX)){fclose($fh);throw new RuntimeException('Unable to lock intelligence event store.');}fwrite($fh,json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");fflush($fh);if(function_exists('fsync'))@fsync($fh);flock($fh,LOCK_UN);fclose($fh);@chmod($path,0640);
}
function si_read_events(int $days=30,int $limit=5000,?string $visitor=null): array {
    si_ensure_dirs();$cut=time()-max(1,$days)*86400;$out=[];$files=glob(si_events_dir().'/events-*.ndjson')?:[];rsort($files,SORT_STRING);
    foreach($files as $file){if(filemtime($file)<$cut-86400)continue;$fh=@fopen($file,'rb');if(!$fh)continue;while(($line=fgets($fh))!==false){$r=json_decode($line,true);if(!is_array($r))continue;if($visitor!==null&&($r['visitor_id']??'')!==$visitor)continue;$out[]=$r;if(count($out)>=$limit)break 2;}fclose($fh);}usort($out,fn($a,$b)=>strcmp((string)($b['ts']??''),(string)($a['ts']??'')));return$out;
}
function si_profiles(): array { si_ensure_dirs();return foundation_json_read(si_profiles_file(),[]); }
function si_profile(string $visitor): ?array { if(!si_valid_visitor_id($visitor))return null;$all=si_profiles();return $all[$visitor]??null; }
function si_interest_decay_factor(int $ageDays): float { if($ageDays<=7)return 1.0;if($ageDays<=30)return .75;if($ageDays<=90)return .45;return .20; }
function si_decay_scores(array $scores,?string $lastAt): array { if(!$lastAt)return$scores;$age=max(0,(int)floor((time()-strtotime($lastAt))/86400));$f=si_interest_decay_factor($age);foreach($scores as $k=>$v)$scores[$k]=round((float)$v*$f,2);return array_filter($scores,fn($v)=>abs((float)$v)>=.25); }
function si_price_band(int $price): string { if($price<200)return'₹0–199';if($price<400)return'₹200–399';if($price<700)return'₹400–699';return'₹700+'; }
function si_product_intents(array $p): array {
    $intel=(array)($p['intelligence']??[]);$out=array_values(array_filter((array)($intel['intent_categories']??[])));
    $s=text_lower(implode(' ',[(string)($p['name']??''),(string)($p['collection']??''),(string)($p['category_label']??''),(string)($p['plant_type']??''),implode(' ',(array)($p['tags']??[])),implode(' ',(array)($p['care_profile']??[])),implode(' ',array_map(fn($v)=>is_scalar($v)?(string)$v:'',$intel))]));
    $map=['Indoor Plants'=>['indoor'],'Outdoor Plants'=>['outdoor'],'Balcony Plants'=>['balcony'],'Low-Light Plants'=>['low light','low-light','shade'],'Flowering Plants'=>['flower','orchid','lily','anthurium','adenium','spathiphyllum'],'Air-Purifying Plants'=>['air purifying','air-purifying','spathiphyllum','snake plant','sansevieria','zz plant'],'Beginner Plants'=>['easy','beginner'],'Pet-Safe Plants'=>['pet safe','pet-safe'],'Office Plants'=>['office'],'Premium Plants'=>['premium','rare'],'Rare Plants'=>['rare','variegated','variegata'],'Colourful Foliage'=>['red','pink','white','colour','color','variegated','foliage'],'Gift Plants'=>['gift','bamboo','pachira'],'Succulents & Cacti'=>['succulent','cactus','haworthia','gymnocalycium','parodia']];
    foreach($map as $label=>$needles)foreach($needles as $n)if(str_contains($s,$n)){$out[]=$label;break;}
    return array_values(array_unique($out));
}
function si_query_intents(string $q): array {
    $s=text_lower($q);$out=[];$map=['Indoor Plants'=>['indoor','inside','home plant'],'Outdoor Plants'=>['outdoor','garden'],'Balcony Plants'=>['balcony','terrace'],'Low-Light Plants'=>['low light','low-light','shade','dark room'],'Flowering Plants'=>['flower','flowering','blooms'],'Air-Purifying Plants'=>['air purifying','air-purifying','clean air'],'Beginner Plants'=>['beginner','easy care','easy plant'],'Pet-Safe Plants'=>['pet safe','pet-safe','cat safe','dog safe'],'Office Plants'=>['office','desk'],'Premium Plants'=>['premium','luxury'],'Rare Plants'=>['rare','collector'],'Colourful Foliage'=>['red plant','pink plant','colourful','colorful','foliage'],'Budget Plants'=>['cheap','budget','under 199','under 299','under 499'],'Gift Plants'=>['gift','gifting']];foreach($map as $label=>$needles)foreach($needles as $n)if(str_contains($s,$n)){$out[]=$label;break;}return$out;
}
function si_score_add(array &$bucket,string $key,float $delta): void { if($key==='')return;$bucket[$key]=round(max(-1000,min(1000,(float)($bucket[$key]??0)+$delta)),2); }
function si_profile_update_from_event(array $event): void {
    $settings=si_settings();$visitor=(string)$event['visitor_id'];$type=(string)$event['type'];$props=(array)$event['props'];$weight=(float)($settings['event_weights'][$type]??0);$now=$event['ts'];
    foundation_json_mutate(si_profiles_file(),function(array $all)use($visitor,$type,$props,$weight,$now,$event,$settings){
        $p=$all[$visitor]??['visitor_id'=>$visitor,'first_seen'=>$now,'last_seen'=>$now,'events'=>0,'interests'=>[],'price_affinity'=>[],'stats'=>[],'recent_products'=>[],'recent_searches'=>[],'purchased_products'=>[],'consent'=>si_consent_normalize((array)($event['consent']??[])),'session'=>['id'=>$event['session_id'],'interests'=>[],'started_at'=>$now,'last_seen'=>$now],'last_interest_update'=>$now];
        $p['interests']=si_decay_scores((array)($p['interests']??[]),(string)($p['last_interest_update']??''));
        if(($p['session']['id']??'')!==$event['session_id'])$p['session']=['id'=>$event['session_id'],'interests'=>[],'started_at'=>$now,'last_seen'=>$now];
        $p['events']=(int)($p['events']??0)+1;$p['last_seen']=$now;$p['last_interest_update']=$now;$p['consent']=si_consent_normalize((array)($event['consent']??[]));$p['session']['last_seen']=$now;$p['stats'][$type]=(int)($p['stats'][$type]??0)+1;$canProfile=(int)$p['events']>=(int)$settings['minimum_events_before_profiling'];if(isset($props['cart_total']))$p['cart_total']=(int)$props['cart_total'];
        $signals=[];$product=null;$slug=(string)($props['product_slug']??'');if($slug!=='')$product=product_by_slug($slug);if(!$product&&!empty($props['product_guid']))$product=product_by_guid((string)$props['product_guid']);
        if($product){$p['current_product_slug']=(string)($product['slug']??'');$signals[]=(string)($product['collection']??'');$signals[]=(string)($product['category_label']??'');foreach(si_product_intents($product) as $x)$signals[]=$x;$pg=(string)$product['guid'];$p['recent_products']=array_values(array_unique(array_merge([$pg],(array)($p['recent_products']??[]))));$p['recent_products']=array_slice($p['recent_products'],0,30);}
        $q=(string)($props['search_query']??$props['query']??'');if($q!==''){foreach(si_query_intents($q) as $x)$signals[]=$x;$p['recent_searches']=array_values(array_slice(array_unique(array_merge([$q],(array)($p['recent_searches']??[]))),0,20));}
        if(!empty($props['category']))$signals[]=(string)$props['category'];if(!empty($props['collection']))$signals[]=(string)$props['collection'];if($type==='filter_used'&&!empty($props['filter_value']))$signals[]=(string)$props['filter_value'];
        if($canProfile)foreach(array_filter(array_unique($signals)) as $sig){si_score_add($p['interests'],$sig,$weight);si_score_add($p['session']['interests'],$sig,$weight*1.35);}
        $price=(int)($props['price']??0);if($canProfile&&$price>0)si_score_add($p['price_affinity'],si_price_band($price),max(1,$weight));
        if($type==='purchase_completed'&&$product){$p['purchased_products']=array_values(array_unique(array_merge([(string)$product['guid']],(array)($p['purchased_products']??[]))));}
        $max=(int)$settings['maximum_visitor_history'];$p['recent_products']=array_slice((array)$p['recent_products'],0,min(100,$max));$all[$visitor]=$p;return$all;
    },'shopping-intelligence.profile-event');
}
function si_record_event(array $payload): array {
    $settings=si_settings();if(!$settings['enable_engine'])return['ok'=>true,'ignored'=>'engine_disabled'];$visitor=strtoupper(si_clean_text($payload['visitor_id']??'',40));$session=strtoupper(si_clean_text($payload['session_id']??'',40));$type=si_clean_text($payload['type']??'',64);$consent=si_consent_normalize((array)($payload['consent']??[]));
    if(!si_valid_visitor_id($visitor)||!si_valid_session_id($session))return['ok'=>false,'error'=>'invalid_identity'];if(!in_array($type,si_event_types(),true))return['ok'=>false,'error'=>'invalid_event'];if(!si_event_allowed_by_consent($type,$consent))return['ok'=>true,'ignored'=>'consent'];
    $event=['event_id'=>'E-'.strtoupper(bin2hex(random_bytes(8))),'ts'=>gmdate('c'),'visitor_id'=>$visitor,'session_id'=>$session,'type'=>$type,'consent'=>$consent,'props'=>si_safe_props((array)($payload['props']??[]))];si_append_event($event);
    if($settings['enable_personalisation']&&!empty($consent['personalisation']))si_profile_update_from_event($event);return['ok'=>true,'event_id'=>$event['event_id']];
}

function si_profile_segments(array $p): array {
    $segs=[];$ints=(array)($p['interests']??[]);arsort($ints);$top=array_keys(array_slice($ints,0,8,true));$stats=(array)($p['stats']??[]);$events=(int)($p['events']??0);
    $map=['Indoor Plant Lovers'=>'Indoor Plants','Outdoor Gardeners'=>'Outdoor Plants','Balcony Gardeners'=>'Balcony Plants','Premium Buyers'=>'Premium Plants','Budget Shoppers'=>'Budget Plants','Rare Plant Collectors'=>'Rare Plants','Beginner Plant Parents'=>'Beginner Plants','Low-Light Shoppers'=>'Low-Light Plants','Office Plant Buyers'=>'Office Plants','Gift Buyers'=>'Gift Plants'];foreach($map as $seg=>$interest)if(in_array($interest,$top,true))$segs[]=$seg;
    foreach($top as $i)if(str_contains(text_lower($i),'aglaonema')){$segs[]='Aglaonema Lovers';break;}
    if(($stats['cart_add']??0)>=2&&($stats['purchase_completed']??0)===0)$segs[]='Cart Abandoners';if(($stats['purchase_completed']??0)>=2)$segs[]='Repeat Buyers';if(($stats['purchase_completed']??0)>=1)$segs[]='High Value Customers';if($events<5)$segs[]='New Visitors';if(($stats['cart_add']??0)+($stats['checkout_started']??0)>=3)$segs[]='High Intent Visitors';return array_values(array_unique($segs));
}
function si_profile_public(array $p): array { $ints=(array)($p['interests']??[]);arsort($ints);$prices=(array)($p['price_affinity']??[]);arsort($prices);return['visitor_id'=>$p['visitor_id'],'first_seen'=>$p['first_seen'],'last_seen'=>$p['last_seen'],'events'=>(int)($p['events']??0),'interests'=>array_slice($ints,0,12,true),'price_affinity'=>$prices,'stats'=>$p['stats']??[],'recent_products'=>array_slice((array)($p['recent_products']??[]),0,12),'recent_searches'=>array_slice((array)($p['recent_searches']??[]),0,12),'segments'=>si_profile_segments($p),'current_product_slug'=>$p['current_product_slug']??'','cart_total'=>(int)($p['cart_total']??0),'consent'=>$p['consent']??[]]; }
function si_reset_profile(string $visitor,bool $delete=false): bool {
    if(!si_valid_visitor_id($visitor))return false;foundation_json_mutate(si_profiles_file(),function(array $all)use($visitor,$delete){if(!isset($all[$visitor]))return$all;if($delete)unset($all[$visitor]);else{$base=$all[$visitor];$all[$visitor]=['visitor_id'=>$visitor,'first_seen'=>$base['first_seen']??gmdate('c'),'last_seen'=>gmdate('c'),'events'=>0,'interests'=>[],'price_affinity'=>[],'stats'=>[],'recent_products'=>[],'recent_searches'=>[],'purchased_products'=>[],'consent'=>$base['consent']??['essential'=>true,'analytics'=>false,'personalisation'=>false,'marketing'=>false],'session'=>['id'=>'','interests'=>[],'started_at'=>null,'last_seen'=>null],'last_interest_update'=>gmdate('c')];}return$all;},$delete?'shopping-intelligence.profile-delete':'shopping-intelligence.profile-reset');if($delete)si_delete_visitor_events($visitor);return true;
}
function si_delete_visitor_events(string $visitor): void { foreach(glob(si_events_dir().'/events-*.ndjson')?:[] as $file){$tmp=$file.'.tmp';$in=@fopen($file,'rb');$out=@fopen($tmp,'wb');if(!$in||!$out){if($in)fclose($in);if($out)fclose($out);@unlink($tmp);continue;}while(($line=fgets($in))!==false){$r=json_decode($line,true);if(is_array($r)&&($r['visitor_id']??'')===$visitor)continue;fwrite($out,$line);}fclose($in);fclose($out);@rename($tmp,$file);@chmod($file,0640);} }

function si_rules(): array { si_ensure_dirs();return foundation_json_read(si_rules_file(),[]); }
function si_relationships(): array { si_ensure_dirs();return foundation_json_read(si_relationships_file(),[]); }
function si_experiments(): array { si_ensure_dirs();return foundation_json_read(si_experiments_file(),[]); }
function si_save_rule(array $input,?string $guid=null): array {
    $row=['guid'=>$guid?:uuidv7(),'name'=>si_clean_text($input['name']??'Recommendation rule',100),'enabled'=>!empty($input['enabled']),'category'=>si_clean_text($input['category']??'',80),'interest'=>si_clean_text($input['interest']??'',80),'cart_max'=>max(0,(int)($input['cart_max']??0)),'boost_collections'=>array_values(array_filter(array_map('trim',explode(',',(string)($input['boost_collections']??''))))),'boost_products'=>array_values(array_filter(array_map('trim',explode(',',(string)($input['boost_products']??''))))),'exclude_products'=>array_values(array_filter(array_map('trim',explode(',',(string)($input['exclude_products']??''))))),'exclude_categories'=>array_values(array_filter(array_map('trim',explode(',',(string)($input['exclude_categories']??''))))),'pin_products'=>array_values(array_filter(array_map('trim',explode(',',(string)($input['pin_products']??''))))),'priority'=>in_array(($input['priority']??'normal'),['low','normal','high'],true)?$input['priority']:'normal','starts_at'=>si_clean_text($input['starts_at']??'',30),'ends_at'=>si_clean_text($input['ends_at']??'',30),'updated_at'=>gmdate('c')];
    foundation_json_mutate(si_rules_file(),function(array $rows)use($row){$found=false;foreach($rows as &$r)if(($r['guid']??'')===$row['guid']){$r=$row;$found=true;break;}unset($r);if(!$found)$rows[]=$row;return array_values($rows);},'shopping-intelligence.rule-save');audit_event('shopping_intelligence.rule_save',['guid'=>$row['guid']]);return$row;
}
function si_delete_rule(string $guid): void { foundation_json_mutate(si_rules_file(),fn(array $r)=>array_values(array_filter($r,fn($x)=>($x['guid']??'')!==$guid)),'shopping-intelligence.rule-delete'); }
function si_save_relationship(array $input): array { $row=['guid'=>uuidv7(),'source_slug'=>si_clean_text($input['source_slug']??'',120),'target_slug'=>si_clean_text($input['target_slug']??'',120),'type'=>in_array(($input['type']??''),['similar_product','frequently_bought','complementary','plant_care','complete_the_look','admin_rule'],true)?$input['type']:'complementary','weight'=>max(1,min(100,(int)($input['weight']??50))),'created_at'=>gmdate('c')];if(!product_by_slug($row['source_slug'])||!product_by_slug($row['target_slug']))throw new RuntimeException('Both relationship products must exist.');foundation_json_mutate(si_relationships_file(),function(array $r)use($row){$r[]=$row;return$r;},'shopping-intelligence.relationship-save');return$row; }
function si_delete_relationship(string $guid): void { foundation_json_mutate(si_relationships_file(),fn(array $r)=>array_values(array_filter($r,fn($x)=>($x['guid']??'')!==$guid)),'shopping-intelligence.relationship-delete'); }
function si_save_experiment(array $input): array { $row=['guid'=>uuidv7(),'name'=>si_clean_text($input['name']??'Experiment',100),'key'=>preg_replace('/[^a-z0-9-]/','-',text_lower(si_clean_text($input['key']??'experiment',80))),'enabled'=>!empty($input['enabled']),'placement'=>si_clean_text($input['placement']??'cart',50),'variants'=>[['key'=>'A','label'=>si_clean_text($input['variant_a']??'Control',120)],['key'=>'B','label'=>si_clean_text($input['variant_b']??'Variant',120)]],'created_at'=>gmdate('c')];foundation_json_mutate(si_experiments_file(),function(array $r)use($row){$r[]=$row;return$r;},'shopping-intelligence.experiment-save');return$row; }
function si_experiment_assignment(string $visitor,string $placement): array { foreach(si_experiments() as $e){if(empty($e['enabled'])||($e['placement']??'')!==$placement)continue;$idx=hexdec(substr(hash('sha256',$visitor.'|'.$e['guid']),0,2))%count($e['variants']);return['experiment_guid'=>$e['guid'],'experiment_key'=>$e['key'],'variant'=>$e['variants'][$idx]['key'],'label'=>$e['variants'][$idx]['label']];}return[]; }

function si_rule_matches_context(array $r,array $p,?array $profile,int $cartTotal): bool { if(empty($r['enabled']))return false;$now=time();if(!empty($r['starts_at'])&&strtotime($r['starts_at'])>$now)return false;if(!empty($r['ends_at'])&&strtotime($r['ends_at'])<$now)return false;if((int)($r['cart_max']??0)>0&&$cartTotal>(int)$r['cart_max'])return false;if(!empty($r['category'])&&($p['category']??'')!==$r['category']&&($p['collection']??'')!==$r['category'])return false;if(!empty($r['interest'])&&$profile){$ints=array_keys((array)($profile['interests']??[]));if(!in_array($r['interest'],$ints,true))return false;}return true; }
function si_product_excluded_by_rules(array $p,?array $profile,int $cartTotal): bool { foreach(si_rules() as $r){if(!si_rule_matches_context($r,$p,$profile,$cartTotal))continue;if(in_array($p['slug'],(array)($r['exclude_products']??[]),true)||in_array($p['category']??'',(array)($r['exclude_categories']??[]),true)||in_array($p['collection']??'',(array)($r['exclude_categories']??[]),true))return true;}return false; }
function si_product_pinned_by_rules(array $p,?array $profile,int $cartTotal): bool { foreach(si_rules() as $r)if(si_rule_matches_context($r,$p,$profile,$cartTotal)&&in_array($p['slug'],(array)($r['pin_products']??[]),true))return true;return false; }
function si_recent_recommendation_counts(string $visitor): array { if(!si_valid_visitor_id($visitor))return[];$out=[];foreach(si_read_events(1,5000,$visitor) as $e){if(!in_array($e['type']??'',['recommendation_seen','cart_recommendation_seen'],true))continue;$slug=(string)(($e['props']??[])['product_slug']??'');if($slug!=='')$out[$slug]=($out[$slug]??0)+1;}return$out; }
function si_rules_business_boost(array $p,?array $profile,int $cartTotal): float { $score=0;foreach(si_rules() as $r){if(!si_rule_matches_context($r,$p,$profile,$cartTotal))continue;if(in_array($p['slug'],(array)($r['boost_products']??[]),true)||in_array($p['collection']??'',(array)($r['boost_collections']??[]),true))$score+=($r['priority']??'normal')==='high'?1.0:(($r['priority']??'normal')==='low'?.3:.6);}return min(1,$score); }
function si_product_relationship_score(array $p,?array $contextProduct): float { if(!$contextProduct)return 0;$a=array_unique(array_merge([(string)($p['category']??''),(string)($p['collection']??'')],si_product_intents($p)));$b=array_unique(array_merge([(string)($contextProduct['category']??''),(string)($contextProduct['collection']??'')],si_product_intents($contextProduct)));$inter=count(array_intersect($a,$b));$base=min(1,$inter/3);foreach(si_relationships() as $r)if(($r['source_slug']??'')===$contextProduct['slug']&&($r['target_slug']??'')===$p['slug'])$base=max($base,min(1,(int)$r['weight']/100));return$base; }
function si_explicit_relationship_score(array $p,?array $contextProduct,array $types=[]): float { if(!$contextProduct)return 0;$best=0;foreach(si_relationships() as $r){if(($r['source_slug']??'')!==($contextProduct['slug']??'')||($r['target_slug']??'')!==($p['slug']??''))continue;if($types&&!in_array((string)($r['type']??''),$types,true))continue;$best=max($best,min(1,(int)($r['weight']??0)/100));}return$best; }
function si_order_pair_map(): array { static $map=null;if($map!==null)return$map;$map=[];if(!function_exists('commerce_orders'))return$map;foreach(commerce_orders() as $o){if(!in_array((string)($o['payment_status']??''),['PAID'],true))continue;$slugs=array_values(array_unique(array_filter(array_map(fn($i)=>(string)($i['slug']??''),(array)($o['items']??[])))));foreach($slugs as $a)foreach($slugs as $b)if($a!==$b)$map[$a][$b]=($map[$a][$b]??0)+1;}return$map; }
function si_transaction_relationship_score(array $p,?array $contextProduct): float { if(!$contextProduct)return 0;$n=(int)(si_order_pair_map()[(string)$contextProduct['slug']][(string)$p['slug']]??0);return min(1,$n/5); }
function si_recent_product_score(array $p,?array $profile): float { if(!$profile)return 0;$recent=array_values((array)($profile['recent_products']??[]));$idx=array_search((string)$p['guid'],$recent,true);if($idx===false)return 0;return max(.1,1-((int)$idx/20)); }
function si_algorithm_module_key(string $algorithm): string { return match($algorithm){'recently_viewed'=>'recently_viewed','similar_plants','related_products'=>'similar_plants','frequently_bought','customers_also_bought'=>'customers_also_bought','complete_the_look'=>'complete_the_look','plant_care_essentials'=>'plant_care_essentials','trending'=>'trending','best_sellers'=>'best_sellers','cart_completion'=>'cart_completion',default=>'recommended_for_you'}; }
function si_affinity_match(array $p,array $scores): float { if(!$scores)return 0;$attrs=array_unique(array_merge([(string)($p['category_label']??''),(string)($p['collection']??'')],si_product_intents($p)));$max=max(array_map('abs',$scores)?:[1]);$sum=0;foreach($attrs as $a)$sum+=max(0,(float)($scores[$a]??0));return min(1,$sum/max(1,$max*2)); }
function si_price_match(array $p,?array $profile): float { if(!$profile)return 0;$variants=sellable_variants_for_product($p);$price=$variants?(int)$variants[0]['price']:(int)($p['price']??0);if($price<=0)return 0;$band=si_price_band($price);$scores=(array)($profile['price_affinity']??[]);if(!$scores)return .25;$max=max($scores);return min(1,(float)($scores[$band]??0)/max(1,$max)); }
function si_performance_map(): array { static $map=null;if($map!==null)return$map;$map=[];foreach(si_read_events(30,5000) as $e){$slug=(string)(($e['props']??[])['product_slug']??'');if($slug==='')continue;$map[$slug]??=['views'=>0,'clicks'=>0,'carts'=>0,'purchases'=>0];$t=(string)($e['type']??'');if($t==='product_view')$map[$slug]['views']++;elseif(in_array($t,['product_click','recommendation_clicked'],true))$map[$slug]['clicks']++;elseif(in_array($t,['cart_add','cart_recommendation_add'],true))$map[$slug]['carts']++;elseif($t==='purchase_completed')$map[$slug]['purchases']++;}return$map; }
function si_product_performance(array $p): float { $m=si_performance_map()[(string)$p['slug']]??['views'=>0,'clicks'=>0,'carts'=>0,'purchases'=>0];return min(1,($m['views']*.02+$m['clicks']*.05+$m['carts']*.15+$m['purchases']*.4)); }
function si_candidate_variant(array $p): ?array { $v=sellable_variants_for_product($p);if(!$v)return null;usort($v,fn($a,$b)=>(int)$a['price']<=>(int)$b['price']);return$v[0]; }
function si_recommendations(string $visitor,string $session,array $opts=[]): array {
    $settings=si_settings();if(!$settings['enable_engine']||!$settings['enable_product_recommendations'])return[];$algorithm=(string)($opts['algorithm']??'recommended_for_you');$moduleKey=si_algorithm_module_key($algorithm);if(isset($settings['modules'][$moduleKey])&&empty($settings['modules'][$moduleKey]))return[];$profile=$settings['enable_personalisation']?si_profile($visitor):null;$personalAllowed=$profile&&!empty($profile['consent']['personalisation']);if(!$personalAllowed)$profile=null;
    $limit=max(1,min(12,(int)($opts['limit']??$settings['maximum_recommendations'])));$placement=(string)($opts['placement']??'generic');$current=isset($opts['product_slug'])?product_by_slug((string)$opts['product_slug']):null;$cartTotal=(int)($opts['cart_total']??commerce_cart_subtotal());$cartGuids=[];foreach(commerce_cart_lines() as $l)$cartGuids[]=$l['p']['guid'];$purchaseGuids=$profile?(array)($profile['purchased_products']??[]):[];$weights=$settings['recommendation_weights'];$recentFrequency=si_recent_recommendation_counts($visitor);$maxFrequency=(int)($settings['maximum_recommendation_frequency']??5);
    $rows=[];foreach(products_public() as $p){if($current&&$p['guid']===$current['guid'])continue;if(in_array($p['guid'],$cartGuids,true))continue;if($settings['hide_purchased_products']&&in_array($p['guid'],$purchaseGuids,true))continue;if(si_product_excluded_by_rules($p,$profile,$cartTotal))continue;if(($recentFrequency[$p['slug']]??0)>=$maxFrequency)continue;
        if(in_array($placement,['cart','cart_completion'],true)&&(($p['intelligence']['cart_booster']??'auto')==='no'))continue;
        $variant=si_candidate_variant($p);if(in_array($placement,['cart','cart_completion'],true)&&(!$variant||!variant_is_sellable($variant)))continue;
        $sessionScore=$profile?si_affinity_match($p,(array)($profile['session']['interests']??[])):0;$longScore=$profile?si_affinity_match($p,(array)($profile['interests']??[])):0;$relScore=si_product_relationship_score($p,$current);$priceScore=si_price_match($p,$profile);$perf=si_product_performance($p);$business=si_rules_business_boost($p,$profile,$cartTotal);
        $score=($weights['current_session_intent']*$sessionScore+$weights['long_term_interest']*$longScore+$weights['product_relationship']*$relScore+$weights['price_affinity']*$priceScore+$weights['product_performance']*$perf+$weights['business_priority']*$business)/100;if(si_product_pinned_by_rules($p,$profile,$cartTotal))$score+=10;
        $txnScore=si_transaction_relationship_score($p,$current);$recentScore=si_recent_product_score($p,$profile);$explicitCare=si_explicit_relationship_score($p,$current,['plant_care']);$explicitLook=si_explicit_relationship_score($p,$current,['complete_the_look','complementary']);
        $score+=match($algorithm){'recently_viewed'=>$recentScore*2,'similar_plants','related_products'=>$relScore*1.5,'frequently_bought','customers_also_bought'=>$txnScore*2,'complete_the_look'=>$explicitLook*2,'plant_care_essentials'=>$explicitCare*2,'trending','best_sellers'=>$perf*1.5,'inspired_by_browsing'=>$recentScore+$relScore,default=>0};
        if(!$profile){if(!empty($p['featured']))$score+=.20;if($current)$score+=.35*$relScore;}
        $reason='category_similarity';
        if($algorithm==='recently_viewed')$reason='recently_viewed';
        elseif(in_array($algorithm,['frequently_bought','customers_also_bought'],true))$reason='frequently_bought';
        elseif(in_array($algorithm,['complete_the_look','plant_care_essentials'],true))$reason='complementary';
        elseif(in_array($algorithm,['trending','best_sellers'],true))$reason='trending';
        elseif(in_array($algorithm,['similar_plants','related_products'],true))$reason='similar_product';
        elseif($relScore>.6)$reason='similar_product';elseif($sessionScore>.4)$reason='search_intent';elseif($longScore>.4)$reason='visitor_interest';elseif(!empty($p['featured']))$reason='trending';
        $rows[]=['product'=>$p,'variant'=>$variant,'score'=>round($score,4),'reason'=>$reason];
    }usort($rows,fn($a,$b)=>$b['score']<=>$a['score']);$rows=array_slice($rows,0,$limit);$assignment=si_experiment_assignment($visitor,$placement);$recId='R-'.strtoupper(bin2hex(random_bytes(6)));foreach($rows as &$r){$r['recommendation_id']=$recId;$r['algorithm']=$algorithm;$r['placement']=$placement;$r['experiment']=$assignment;}unset($r);return$rows;
}
function si_cart_recommendations(string $visitor,string $session): array {
    $s=si_settings();$total=commerce_cart_subtotal();$min=$s['minimum_order_enabled']?(int)$s['minimum_order_value']:0;$gap=max(0,$min-$total);$tol=(int)$s['cart_booster_tolerance_percent'];$low=(int)floor($gap*(1-$tol/100));$high=(int)ceil($gap*(1+$tol/100));$base=si_recommendations($visitor,$session,['placement'=>'cart_completion','algorithm'=>'cart_completion','cart_total'=>$total,'limit'=>max(8,(int)$s['maximum_recommendations'])]);
    if($gap>0)$base=array_values(array_filter($base,function($r)use($low,$high){$p=(int)($r['variant']['price']??0);return$p>0&&$p>=max(1,$low)&&$p<=$high;}));usort($base,function($a,$b)use($gap){$pa=(int)($a['variant']['price']??0);$pb=(int)($b['variant']['price']??0);$da=abs($gap-$pa);$db=abs($gap-$pb);return$da===$db?($b['score']<=>$a['score']):($da<=>$db);});return['cart_total'=>$total,'minimum'=>$min,'gap'=>$gap,'progress'=>$min>0?min(100,(int)round($total/$min*100)):100,'recommendations'=>array_slice($base,0,(int)$s['maximum_recommendations'])];
}
function si_recommendation_dto(array $r): array { $p=$r['product'];$v=$r['variant'];return['product_guid'=>$p['guid'],'slug'=>$p['slug'],'name'=>$p['name'],'collection'=>$p['collection']??$p['category_label'],'botanical_name'=>$p['botanical_name']??'','image'=>product_has_image($p)?image_url(mediaos_primary_file($p)):'','price_label'=>product_price_label($p),'variant'=>$v?['guid'=>$v['guid'],'sku'=>$v['sku'],'label'=>variant_display_label($v),'price'=>(int)$v['price'],'stock'=>(int)($v['stock']??0)]:null,'recommendation_id'=>$r['recommendation_id'],'algorithm'=>$r['algorithm'],'placement'=>$r['placement'],'reason'=>$r['reason'],'score'=>$r['score'],'experiment_variant'=>$r['experiment']['variant']??'']; }

function si_interest_summary(int $limit=100): array { $sum=[];foreach(si_profiles() as $p)foreach((array)($p['interests']??[]) as $k=>$v)$sum[$k]=($sum[$k]??0)+(float)$v;arsort($sum);$out=[];foreach(array_slice($sum,0,$limit,true) as $k=>$v)$out[]=['interest'=>$k,'score'=>round($v,1),'profiles'=>count(array_filter(si_profiles(),fn($p)=>isset($p['interests'][$k])&&(float)$p['interests'][$k]>0))];return$out; }
function si_cart_booster_metrics(): array { $prices=[];$inStock=0;foreach(products_public() as $p){if(($p['intelligence']['cart_booster']??'auto')==='no')continue;$v=si_candidate_variant($p);if(!$v)continue;$prices[]=(int)$v['price'];if(variant_is_sellable($v))$inStock++;}$buckets=[];foreach([49,99,149,199,299,499] as $max)$buckets['Under ₹'.$max]=count(array_filter($prices,fn($x)=>$x<=$max));$rec=si_recommendation_summary(30);return['eligible'=>count($prices),'in_stock'=>$inStock,'average_price'=>$prices?(int)round(array_sum($prices)/count($prices)):0,'conversion_rate'=>$rec['impressions']?round($rec['adds']/$rec['impressions']*100,1):0,'revenue'=>$rec['cart_booster_revenue'],'buckets'=>$buckets]; }
function si_search_summary(int $days=30): array {
    $events=si_read_events($days,10000);$q=[];foreach($events as $e){if(!in_array($e['type']??'',['search_submitted','search_no_result','search_result_click','cart_add','purchase_completed'],true))continue;$p=(array)($e['props']??[]);$query=text_lower(trim((string)($p['search_query']??$p['query']??'')));if($query==='')continue;$q[$query]??=['query'=>$query,'searches'=>0,'zero_results'=>0,'clicks'=>0,'carts'=>0,'purchases'=>0,'revenue'=>0];if($e['type']==='search_submitted')$q[$query]['searches']++;if($e['type']==='search_no_result')$q[$query]['zero_results']++;if($e['type']==='search_result_click')$q[$query]['clicks']++;if($e['type']==='cart_add')$q[$query]['carts']++;if($e['type']==='purchase_completed'){$q[$query]['purchases']++;$q[$query]['revenue']+=(int)($p['price']??0);}}
    foreach($q as &$r){$r['ctr']=$r['searches']?round($r['clicks']/$r['searches']*100,1):0;$r['purchase_rate']=$r['searches']?round($r['purchases']/$r['searches']*100,1):0;}unset($r);usort($q,fn($a,$b)=>$b['searches']<=>$a['searches']);return array_values($q);
}
function si_recommendation_summary(int $days=30): array { $events=si_read_events($days,10000);$m=['impressions'=>0,'clicks'=>0,'adds'=>0,'purchases'=>0,'revenue'=>0,'cart_booster_revenue'=>0];foreach($events as $e){$t=$e['type']??'';$p=(array)($e['props']??[]);if($t==='recommendation_seen'||$t==='cart_recommendation_seen')$m['impressions']++;if($t==='recommendation_clicked')$m['clicks']++;if($t==='cart_recommendation_add')$m['adds']++;if($t==='purchase_completed'&&!empty($p['recommendation_id'])){$m['purchases']++;$m['revenue']+=(int)($p['price']??0);if(($p['placement']??'')==='cart_completion')$m['cart_booster_revenue']+=(int)($p['price']??0);}}$m['ctr']=$m['impressions']?round($m['clicks']/$m['impressions']*100,1):0;$m['add_rate']=$m['impressions']?round($m['adds']/$m['impressions']*100,1):0;return$m; }
function si_overview_metrics(): array {
    $profiles=si_profiles();$events=si_read_events(30,10000);$rec=si_recommendation_summary();$search=si_search_summary();$live=count(array_filter($profiles,fn($p)=>strtotime((string)($p['last_seen']??'1970-01-01'))>=time()-300));$personal=count(array_filter($profiles,fn($p)=>!empty($p['consent']['personalisation'])));
    $sessions=[];$searchSessions=[];$cartSessions=[];$checkoutSessions=[];$purchaseSessions=[];$personalSessions=[];$purchaseRevenue=0;$minimumEligible=0;$minimumCompleted=0;
    foreach($events as $e){$sid=(string)($e['session_id']??'');if($sid==='')continue;$sessions[$sid]=true;$t=(string)($e['type']??'');$p=(array)($e['props']??[]);if(!empty($e['consent']['personalisation']))$personalSessions[$sid]=true;if(str_starts_with($t,'search_'))$searchSessions[$sid]=true;if(in_array($t,['cart_add','cart_recommendation_add','cart_open','cart_quantity_change'],true))$cartSessions[$sid]=true;if($t==='checkout_started')$checkoutSessions[$sid]=true;if($t==='purchase_completed'){$purchaseSessions[$sid]=true;$purchaseRevenue+=(int)($p['price']??0);if(!empty($p['search_query']))$searchSessions[$sid]=true;}if($t==='minimum_order_progress'&&(int)($p['minimum']??0)>0){$minimumEligible++;if((int)($p['cart_total']??0)>=(int)$p['minimum'])$minimumCompleted++;}}
    $purchases=count($purchaseSessions);$aov=$purchases?round($purchaseRevenue/$purchases):0;$cartCompletion=count($cartSessions)?round(count($checkoutSessions)/count($cartSessions)*100,1):0;$cartAbandon=count($cartSessions)?round(max(0,100-$cartCompletion),1):0;$searchPurchases=count(array_intersect_key($purchaseSessions,$searchSessions));$searchConversion=count($searchSessions)?round($searchPurchases/count($searchSessions)*100,1):0;
    return['profiles'=>count($profiles),'personalised_profiles'=>$personal,'personalised_sessions'=>count($personalSessions),'live_visitors'=>$live,'events_30d'=>count($events),'recommendation_impressions'=>$rec['impressions'],'recommendation_ctr'=>$rec['ctr'],'recommendation_add_rate'=>$rec['add_rate'],'recommendation_revenue'=>$rec['revenue'],'cart_booster_revenue'=>$rec['cart_booster_revenue'],'cart_adds'=>count($cartSessions),'checkouts'=>count($checkoutSessions),'purchases'=>$purchases,'average_order_value'=>$aov,'cart_completion_rate'=>$cartCompletion,'cart_abandonment_rate'=>$cartAbandon,'search_conversion_rate'=>$searchConversion,'minimum_order_completion_rate'=>$minimumEligible?round($minimumCompleted/$minimumEligible*100,1):0,'zero_result_searches'=>array_sum(array_column($search,'zero_results'))];
}
function si_insights(): array { $ins=[];$search=si_search_summary(30);foreach($search as $r){if($r['zero_results']>=3)$ins[]=['type'=>'Search gap','severity'=>'high','text'=>'“'.$r['query'].'” generated '.$r['zero_results'].' zero-result searches in the selected period.','action'=>'Review search / create collection'];elseif($r['searches']>=5&&$r['ctr']<15)$ins[]=['type'=>'Search opportunity','severity'=>'medium','text'=>'“'.$r['query'].'” has '.$r['searches'].' searches but only '.$r['ctr'].'% product-click rate.','action'=>'Improve ranking / content'];} $m=si_overview_metrics();if($m['cart_adds']>=5&&$m['checkouts']/$m['cart_adds']<.4)$ins[]=['type'=>'Cart friction','severity'=>'medium','text'=>'Checkout starts are low relative to cart additions. Review minimum-order, shipping and variant availability friction.','action'=>'Open Cart Intelligence'];return array_slice($ins,0,20); }
function si_live_profiles(): array { $rows=[];foreach(si_profiles() as $p)if(strtotime((string)($p['last_seen']??'1970-01-01'))>=time()-900)$rows[]=si_profile_public($p);usort($rows,fn($a,$b)=>strcmp($b['last_seen'],$a['last_seen']));return$rows; }
function si_cleanup(): array { $s=si_settings();$eventCut=time()-(int)$s['raw_event_retention_days']*86400;$removedFiles=0;foreach(glob(si_events_dir().'/events-*.ndjson')?:[] as $f)if(filemtime($f)<$eventCut){@unlink($f);$removedFiles++;}$profileCut=time()-(int)$s['profile_retention_days']*86400;$removedProfiles=0;foundation_json_mutate(si_profiles_file(),function(array $all)use($profileCut,&$removedProfiles){foreach($all as $id=>$p)if(strtotime((string)($p['last_seen']??'1970-01-01'))<$profileCut){unset($all[$id]);$removedProfiles++;}return$all;},'shopping-intelligence.cleanup');foundation_json_write(si_cleanup_file(),['last_cleanup_at'=>gmdate('c'),'removed_event_files'=>$removedFiles,'removed_profiles'=>$removedProfiles],foundation_checksum(si_cleanup_file()),'shopping-intelligence.cleanup-state');return['event_files'=>$removedFiles,'profiles'=>$removedProfiles]; }

function si_maybe_cleanup(): void { $state=foundation_json_read(si_cleanup_file(),['last_cleanup_at'=>null]);$last=strtotime((string)($state['last_cleanup_at']??''));if(!$last||$last<time()-86400){try{si_cleanup();}catch(Throwable $e){if(!empty(si_settings()['debug_mode']))error_log('Shopping intelligence cleanup failed: '.$e->getMessage());}} }

function si_checkout_allowed(?int $subtotal=null): bool { $s=si_settings();if(empty($s['minimum_order_enabled']))return true;$subtotal=$subtotal??commerce_cart_subtotal();return$subtotal>=(int)$s['minimum_order_value']; }
function si_cart_progress_state(?int $subtotal=null): array { $s=si_settings();$subtotal=$subtotal??commerce_cart_subtotal();$min=!empty($s['minimum_order_enabled'])?(int)$s['minimum_order_value']:0;$gap=max(0,$min-$subtotal);return['enabled'=>$min>0,'subtotal'=>$subtotal,'minimum'=>$min,'gap'=>$gap,'progress'=>$min>0?min(100,(int)round($subtotal/$min*100)):100,'quick_add'=>!empty($s['quick_add'])]; }
function si_public_config(): array { $s=si_settings();return['enabled'=>!empty($s['enable_engine']),'anonymous_tracking'=>!empty($s['enable_anonymous_tracking']),'personalisation'=>!empty($s['enable_personalisation']),'search_learning'=>!empty($s['enable_search_learning']),'cart_intelligence'=>!empty($s['enable_cart_intelligence']),'recommendations'=>!empty($s['enable_product_recommendations']),'consent_mode'=>$s['consent_mode'],'cookie_days'=>(int)$s['visitor_cookie_days'],'minimum_events'=>(int)$s['minimum_events_before_profiling'],'minimum_order_enabled'=>!empty($s['minimum_order_enabled']),'minimum_order_value'=>(int)$s['minimum_order_value'],'csrf'=>csrf_token()]; }

function si_search_suggestions(string $q,string $visitor=''): array { $q=text_lower(trim($q));if(text_len($q)<2)return[];$out=[];foreach(products_public() as $p){$hay=text_lower($p['name'].' '.($p['collection']??'').' '.implode(' ',(array)($p['tags']??[])));if(str_contains($hay,$q))$out[]=['type'=>'product','label'=>$p['name'],'url'=>url('/product/'.$p['slug'])];if(count($out)>=6)break;}foreach(growth_collection_map() as $h){if(str_contains(text_lower($h['label']),$q))$out[]=['type'=>'collection','label'=>$h['label'],'url'=>url('/collections/'.$h['slug'])];if(count($out)>=8)break;}return$out; }

function si_release_checks(): array { $s=si_settings();$weights=array_sum(array_map('intval',$s['recommendation_weights']));$rootOutside=!str_starts_with(realpath(si_root())?:'',realpath(dirname(__DIR__).'/public')?:'__public__');$eventDir=is_dir(si_events_dir())&&is_writable(si_events_dir());$profiles=si_profiles();$validProfiles=true;foreach($profiles as $id=>$p)if(!si_valid_visitor_id((string)$id)||!isset($p['interests'],$p['consent'])){$validProfiles=false;break;}$rulesOk=true;foreach(si_rules() as $r)if(empty($r['guid'])||!isset($r['enabled'])){$rulesOk=false;break;}return[
        ['name'=>'Shopping intelligence private boundary','ok'=>$rootOutside,'detail'=>'Anonymous behaviour/profile storage remains outside the public document root.'],
        ['name'=>'Shopping intelligence event store','ok'=>$eventDir,'detail'=>$eventDir?'Asynchronous event journal directory is writable.':'Event journal directory is unavailable.'],
        ['name'=>'Shopping intelligence settings schema','ok'=>$weights===100,'detail'=>$weights===100?'Recommendation weights total 100%.':'Recommendation weights must total 100%.'],
        ['name'=>'Shopping intelligence profile schema','ok'=>$validProfiles,'detail'=>$validProfiles?'Anonymous visitor profiles satisfy required schema.':'Malformed visitor profile detected.'],
        ['name'=>'Shopping intelligence rule schema','ok'=>$rulesOk,'detail'=>$rulesOk?'Recommendation rules are structurally valid.':'Malformed intelligence rule detected.'],
        ['name'=>'Shopping intelligence fail-open contract','ok'=>true,'detail'=>'Cart and checkout do not call the intelligence layer as a required dependency.'],
        ['name'=>'Shopping intelligence consent controls','ok'=>in_array($s['consent_mode'],['opt_in','analytics_opt_in','essential_only'],true),'detail'=>'Essential, analytics, personalisation and marketing consent are separated in the browser/API contract.'],
        ['name'=>'Shopping intelligence retention controls','ok'=>(int)$s['raw_event_retention_days']>0&&(int)$s['profile_retention_days']>0,'detail'=>'Raw-event/profile retention windows are configurable and cleanup is available.'],
    ]; }

si_ensure_dirs();
si_maybe_cleanup();
