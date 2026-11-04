<?php
declare(strict_types=1);

/* Sanjay Nursery v1.7.0 — AI Search, Catalogue & Content Intelligence
 * Web-managed, file-mode staging implementation. No CLI worker is required.
 */
function ai_root(): string { return dirname(__DIR__).'/storage/private-domain/ai-engine'; }
function ai_public_root(): string { return dirname(__DIR__).'/storage/public-domain/ai'; }
function ai_settings_file(): string { return ai_root().'/settings.json'; }
function ai_credentials_file(): string { return ai_root().'/credentials.json'; }
function ai_jobs_file(): string { return ai_root().'/jobs.json'; }
function ai_reviews_file(): string { return ai_root().'/review-queue.json'; }
function ai_history_file(): string { return ai_root().'/history.json'; }
function ai_usage_file(): string { return ai_root().'/usage.json'; }
function ai_state_file(): string { return ai_root().'/state.json'; }
function ai_catalogue_scan_file(): string { return ai_root().'/catalogue-scan.json'; }
function ai_seo_scan_file(): string { return ai_root().'/seo-scan.json'; }
function ai_opportunities_file(): string { return ai_root().'/opportunities.json'; }
function ai_content_file(): string { return ai_public_root().'/content.json'; }
function ai_lock_file(): string { return dirname(__DIR__).'/storage/locks/ai-engine-runner.lock'; }

function ai_ensure_dirs(): void {
    foreach([ai_root(),ai_public_root(),dirname(ai_lock_file())] as $d){ if(!is_dir($d)) @mkdir($d,0750,true); @chmod($d,0750); }
}

function ai_defaults(): array {
    return [
        'enabled'=>false,
        'mode'=>'scan_only', // scan_only | review | safe_auto
        'provider'=>'openai_compatible',
        'api_base'=>'https://api.openai.com/v1',
        'text_model'=>'',
        'vision_model'=>'',
        'timeout_seconds'=>30,
        'daily_request_limit'=>250,
        'daily_token_limit'=>300000,
        'max_jobs_per_run'=>5,
        'max_jobs_per_hour'=>50,
        'scan_frequency'=>'daily',
        'scan_changed_only'=>true,
        'auto_web_runner'=>true,
        'runner_interval_seconds'=>180,
        'product_batch_size'=>10,
        'minimum_review_confidence'=>70,
        'auto_confidence'=>[
            'short'=>92,'seo.title'=>92,'seo.description'=>92,'tags'=>88,
            'intelligence.genus'=>98,'intelligence.species'=>98,
            'intelligence.growth_rate'=>96,'intelligence.foliage_colour'=>94,
            'intelligence.mature_size'=>98,'intelligence.placement'=>94,
            'intelligence.pune_guidance'=>94,
        ],
        'safe_auto_fields'=>['short','seo.title','seo.description','tags','intelligence.growth_rate','intelligence.foliage_colour','intelligence.placement','intelligence.pune_guidance'],
        'protected_fields'=>['price','compare_at_price','sku','inventory','variants','pet_safe','toxicity','availability','reviews','ratings','delivery'],
        'approved_sources'=>['catalogue','cms','nursery_documents'],
        'allow_general_web'=>false,
        'allow_competitor_sources'=>false,
        'last_saved_at'=>null,
    ];
}
function ai_settings(): array { ai_ensure_dirs(); return array_replace_recursive(ai_defaults(), foundation_json_read(ai_settings_file(),[])); }
function ai_credentials(): array { ai_ensure_dirs(); try{return confidential_json_read(ai_credentials_file(),'sanjaynursery:ai-credentials:v1',[]);}catch(Throwable){return [];} }
function ai_api_key(): string { return trim((string)(ai_credentials()['api_key']??'')); }
function ai_mask_key(string $key): string { if($key==='')return 'Not configured'; return strlen($key)<=8?'••••••••':substr($key,0,3).'••••••'.substr($key,-4); }
function ai_valid_base_url(string $url): bool {
    $p=parse_url(trim($url)); if(!is_array($p)||empty($p['scheme'])||empty($p['host']))return false;
    $scheme=strtolower((string)$p['scheme']); if($scheme==='https')return true;
    return $scheme==='http' && in_array(strtolower((string)$p['host']),['127.0.0.1','localhost','::1'],true);
}
function ai_save_settings(array $input): void {
    require_admin();$current=ai_settings();
    $base=rtrim(trim((string)($input['api_base']??$current['api_base'])),'/'); if(!ai_valid_base_url($base))throw new RuntimeException('AI API base URL must use HTTPS (localhost HTTP is allowed for development).');
    $mode=(string)($input['mode']??$current['mode']);if(!in_array($mode,['scan_only','review','safe_auto'],true))$mode='scan_only';
    $freq=(string)($input['scan_frequency']??$current['scan_frequency']);if(!in_array($freq,['manual','daily','every_3_days','weekly'],true))$freq='daily';
    $next=$current;
    $next['enabled']=!empty($input['enabled']);$next['mode']=$mode;$next['provider']='openai_compatible';$next['api_base']=$base;
    $next['text_model']=trim((string)($input['text_model']??''));$next['vision_model']=trim((string)($input['vision_model']??''));
    $next['timeout_seconds']=max(5,min(90,(int)($input['timeout_seconds']??30)));$next['daily_request_limit']=max(1,min(10000,(int)($input['daily_request_limit']??250)));
    $next['daily_token_limit']=max(1000,min(50000000,(int)($input['daily_token_limit']??300000)));$next['max_jobs_per_run']=max(1,min(50,(int)($input['max_jobs_per_run']??5)));
    $next['max_jobs_per_hour']=max(1,min(500,(int)($input['max_jobs_per_hour']??50)));$next['scan_frequency']=$freq;$next['scan_changed_only']=!empty($input['scan_changed_only']);
    $next['auto_web_runner']=!empty($input['auto_web_runner']);$next['runner_interval_seconds']=max(60,min(3600,(int)($input['runner_interval_seconds']??180)));
    $next['product_batch_size']=max(1,min(50,(int)($input['product_batch_size']??10)));$next['minimum_review_confidence']=max(50,min(100,(int)($input['minimum_review_confidence']??70)));
    $next['allow_general_web']=!empty($input['allow_general_web']);$next['allow_competitor_sources']=!empty($input['allow_competitor_sources']);$next['last_saved_at']=gmdate('c');
    foundation_json_write(ai_settings_file(),$next,null,'ai.settings.save');
    $key=trim((string)($input['api_key']??''));if($key!=='' && $key!=='********') confidential_json_write(ai_credentials_file(),['api_key'=>$key,'updated_at'=>gmdate('c')],'sanjaynursery:ai-credentials:v1',foundation_checksum(ai_credentials_file()));
    audit_event('ai.settings.save',['enabled'=>$next['enabled'],'mode'=>$next['mode'],'api_base_host'=>parse_url($base,PHP_URL_HOST)]);
}

function ai_usage(): array { $u=foundation_json_read(ai_usage_file(),[]);$today=gmdate('Y-m-d');if(($u['date']??'')!==$today)$u=['date'=>$today,'requests'=>0,'input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0,'jobs'=>0,'updated_at'=>gmdate('c')];return$u; }
function ai_usage_add(int $requests,int $inputTokens=0,int $outputTokens=0,int $jobs=0): void { $today=gmdate('Y-m-d');foundation_json_mutate(ai_usage_file(),function(array $u)use($today,$requests,$inputTokens,$outputTokens,$jobs){if(($u['date']??'')!==$today)$u=['date'=>$today,'requests'=>0,'input_tokens'=>0,'output_tokens'=>0,'total_tokens'=>0,'jobs'=>0];$u['requests']=(int)($u['requests']??0)+$requests;$u['input_tokens']=(int)($u['input_tokens']??0)+$inputTokens;$u['output_tokens']=(int)($u['output_tokens']??0)+$outputTokens;$u['total_tokens']=(int)($u['total_tokens']??0)+$inputTokens+$outputTokens;$u['jobs']=(int)($u['jobs']??0)+$jobs;$u['updated_at']=gmdate('c');return$u;},'ai.usage'); }
function ai_budget_available(): array { $s=ai_settings();$u=ai_usage();$ok=(int)$u['requests']<(int)$s['daily_request_limit']&&(int)$u['total_tokens']<(int)$s['daily_token_limit'];return ['ok'=>$ok,'usage'=>$u,'request_limit'=>(int)$s['daily_request_limit'],'token_limit'=>(int)$s['daily_token_limit']]; }

function ai_provider_ready(): bool { $s=ai_settings();return $s['text_model']!==''&&ai_api_key()!==''&&ai_valid_base_url((string)$s['api_base']); }
function ai_provider_required(): bool { $s=ai_settings();return !empty($s['enabled'])&&in_array((string)$s['mode'],['review','safe_auto'],true); }
function ai_enrichment_ready(): bool { $s=ai_settings();return ai_provider_required()&&ai_provider_ready(); }
function ai_http_json(string $method,string $url,array $payload=[]): array {
    if(!ai_valid_base_url($url))throw new RuntimeException('Blocked AI provider URL.');
    $s=ai_settings();$budget=ai_budget_available();if(empty($budget['ok']))throw new RuntimeException('AI daily request/token budget has been reached.');
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL is required for AI provider connections.');
    $ch=curl_init($url);$headers=['Accept: application/json','Authorization: Bearer '.ai_api_key()];
    $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>(int)$s['timeout_seconds'],CURLOPT_CONNECTTIMEOUT=>min(10,(int)$s['timeout_seconds']),CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0];
    if(strtoupper($method)!=='GET'){$body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$opts[CURLOPT_POSTFIELDS]=$body;$headers[]='Content-Type: application/json';$opts[CURLOPT_HTTPHEADER]=$headers;}
    if(defined('CURLOPT_PROTOCOLS'))$opts[CURLOPT_PROTOCOLS]=CURLPROTO_HTTPS|CURLPROTO_HTTP;
    curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$errno=curl_errno($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    ai_usage_add(1);
    if($raw===false||$errno){$fp=monitor_record_incident(['source'=>'ai','category'=>'provider','severity'=>'error','title'=>'AI provider connection failed','message'=>$err?:'Unknown cURL failure','route'=>'/admin/ai/settings','method'=>'SYSTEM']);throw new RuntimeException('AI provider connection failed: '.($err?:'unknown error'));}
    $data=json_decode((string)$raw,true);if($status<200||$status>=300){$msg=is_array($data)?(string)($data['error']['message']??$data['message']??('HTTP '.$status)):('HTTP '.$status);monitor_record_incident(['source'=>'ai','category'=>'provider','severity'=>$status>=500?'error':'warning','title'=>'AI provider request failed','message'=>$msg,'route'=>'/admin/ai/settings','method'=>'SYSTEM','http_status'=>$status]);throw new RuntimeException('AI provider returned '.$status.': '.$msg);}
    monitor_resolve(monitor_fingerprint('ai','provider','AI provider request failed','/admin/ai/settings',''),'AI provider request recovered.');
    if(!is_array($data))throw new RuntimeException('AI provider returned invalid JSON.');
    $usage=(array)($data['usage']??[]);if($usage)ai_usage_add(0,(int)($usage['prompt_tokens']??$usage['input_tokens']??0),(int)($usage['completion_tokens']??$usage['output_tokens']??0));
    return$data;
}
function ai_provider_models(): array { $s=ai_settings();if(ai_api_key()==='')throw new RuntimeException('Save an API key first.');$d=ai_http_json('GET',rtrim((string)$s['api_base'],'/').'/models');$rows=[];foreach((array)($d['data']??[]) as $r){$id=trim((string)($r['id']??''));if($id!=='')$rows[]=$id;}sort($rows,SORT_NATURAL|SORT_FLAG_CASE);return array_values(array_unique($rows)); }
function ai_provider_test(): array { $models=ai_provider_models();return ['ok'=>true,'models'=>$models,'count'=>count($models)]; }
function ai_chat_json(string $system,string $user): array {
    if(!ai_enrichment_ready())throw new RuntimeException('AI enrichment is not ready. Activate Review or Safe Auto mode and configure the provider in AI Engine → Settings.');
    $s=ai_settings();$payload=['model'=>$s['text_model'],'temperature'=>0.2,'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]],'response_format'=>['type'=>'json_object']];
    $d=ai_http_json('POST',rtrim((string)$s['api_base'],'/').'/chat/completions',$payload);$content=(string)($d['choices'][0]['message']['content']??'');$out=json_decode($content,true);if(!is_array($out))throw new RuntimeException('AI model did not return valid JSON.');return$out;
}

function ai_catalogue_hash(array $p): string { $stable=['name'=>$p['name']??'','slug'=>$p['slug']??'','category'=>$p['category']??'','collection'=>$p['collection']??'','short'=>$p['short']??'','description'=>$p['description']??'','seo'=>$p['seo']??[],'botanical_name'=>$p['botanical_name']??'','care_profile'=>$p['care_profile']??[],'intelligence'=>$p['intelligence']??[],'image'=>$p['image']??'','updated_at'=>$p['updated_at']??''];return hash('sha256',json_encode($stable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); }
function ai_nonempty(mixed $v): bool { if(is_array($v))return count(array_filter($v,fn($x)=>ai_nonempty($x)))>0;if(is_bool($v))return true;return trim((string)$v)!==''; }
function ai_score_fields(array $fields): int { if(!$fields)return 100;$ok=0;foreach($fields as $v)if(ai_nonempty($v))$ok++;return (int)round(($ok/count($fields))*100); }
function ai_product_completeness(array $p): array {
    $i=(array)($p['intelligence']??[]);$cp=(array)($p['care_profile']??[]);$seo=(array)($p['seo']??[]);$variants=function_exists('variants_for_product')?variants_for_product($p,true):[];
    $scores=[
      'catalogue'=>ai_score_fields([$p['short']??'',$p['description']??'',$p['tags']??[],$p['care']??'',$p['collection']??'',$p['botanical_name']??'']),
      'seo'=>ai_score_fields([$seo['title']??'',$seo['description']??'',$p['slug']??'',product_has_image($p)?'image':'']),
      'entity'=>ai_score_fields([$p['botanical_name']??'',$p['plant_type']??'',$i['genus']??'',$i['species']??'',$i['sunlight']??($cp['light']??''),$i['watering']??($cp['water']??''),$i['difficulty']??($cp['difficulty']??''),$i['growth_rate']??'',$i['mature_size']??'',$i['humidity_requirement']??($cp['humidity']??''),$i['placement']??($cp['placement']??''),$i['pune_guidance']??'']),
      'commerce'=>ai_score_fields([count($variants)?'variants':'',product_is_sellable($p)?'sellable':'',count(array_filter($variants,'variant_is_merchant_eligible'))?'merchant':'']),
      'media'=>ai_score_fields([product_has_image($p)?'primary':'',!empty($p['gallery'])?'gallery':'']),
    ];
    $weights=['catalogue'=>25,'seo'=>20,'entity'=>30,'commerce'=>15,'media'=>10];$overall=0;foreach($weights as $k=>$w)$overall+=$scores[$k]*$w/100;
    $missing=[];$map=['image'=>product_has_image($p),'live_variant'=>count($variants)>0,'merchant_variant'=>count(array_filter($variants,'variant_is_merchant_eligible'))>0,'genus'=>!empty($i['genus']),'species'=>!empty($i['species']),'growth_rate'=>!empty($i['growth_rate']),'mature_size'=>!empty($i['mature_size']),'pune_guidance'=>!empty($i['pune_guidance'])];foreach($map as $k=>$v)if(!$v)$missing[]=$k;
    return ['guid'=>$p['guid'],'slug'=>$p['slug'],'name'=>$p['name'],'hash'=>ai_catalogue_hash($p),'scores'=>$scores,'overall'=>(int)round($overall),'missing'=>$missing,'updated_at'=>$p['updated_at']??null];
}
function ai_scan_catalogue(): array { $rows=[];$sum=0;foreach(products_all() as $p){$r=ai_product_completeness($p);$rows[$p['guid']]=$r;$sum+=$r['overall'];}$out=['generated_at'=>gmdate('c'),'count'=>count($rows),'average'=>count($rows)?(int)round($sum/count($rows)):0,'products'=>$rows];foundation_json_write(ai_catalogue_scan_file(),$out,null,'ai.catalogue.scan');return$out; }
function ai_catalogue_scan(): array { $d=foundation_json_read(ai_catalogue_scan_file(),[]);return $d?:ai_scan_catalogue(); }
function ai_catalogue_rows(): array { $rows=array_values((array)(ai_catalogue_scan()['products']??[]));usort($rows,fn($a,$b)=>($a['overall']??0)<=>($b['overall']??0));return$rows; }

function ai_seo_scan(): array {
    $issues=[];$ok=0;$total=0;
    foreach(products_public() as $p){$total++;$row=[];$seo=(array)($p['seo']??[]);if(text_len(trim((string)($seo['title']??'')))<20)$row[]='SEO title is missing or too short';if(text_len(trim((string)($seo['description']??'')))<70)$row[]='Meta description is missing or too short';if(!product_has_image($p))$row[]='No approved primary image';if(empty($p['botanical_name']))$row[]='Botanical identity missing';if(empty($p['intelligence']['pune_guidance']))$row[]='Pune context missing';if(!$row)$ok++;else$issues[]=['type'=>'product','label'=>$p['name'],'url'=>'/product/'.$p['slug'],'severity'=>count($row)>=3?'high':'medium','issues'=>$row];}
    foreach(cms_items() as $c){$total++;$row=[];if(text_len((string)($c['seo']['title']??''))<20)$row[]='SEO title weak';if(text_len((string)($c['seo']['description']??''))<70)$row[]='Meta description weak';$q=growth_content_quality($c);if($q['score']<$q['publish_threshold'])$row[]='Editorial quality below publish threshold';if(!$row)$ok++;else$issues[]=['type'=>'content','label'=>$c['title'],'url'=>cms_content_route($c),'severity'=>'medium','issues'=>$row];}
    $score=$total?(int)round($ok/$total*100):100;$out=['generated_at'=>gmdate('c'),'score'=>$score,'total'=>$total,'healthy'=>$ok,'issues'=>$issues];foundation_json_write(ai_seo_scan_file(),$out,null,'ai.seo.scan');return$out;
}
function ai_seo_report(): array { $d=foundation_json_read(ai_seo_scan_file(),[]);return $d?:ai_seo_scan(); }

function ai_flatten_text(mixed $value): string { if(is_array($value)){ $parts=[];foreach($value as $v){$t=ai_flatten_text($v);if($t!=='')$parts[]=$t;}return implode(' ',$parts);}if(is_scalar($value))return trim((string)$value);return ''; }
function ai_product_text(array $p): string { return text_lower(implode(' ',[(string)($p['name']??''),(string)($p['category_label']??''),(string)($p['collection']??''),(string)($p['short']??''),(string)($p['care']??''),ai_flatten_text($p['tags']??[]),ai_flatten_text($p['intelligence']??[]) ])); }
function ai_listicle_candidates(string $topic,int $limit=10): array {
    $q=text_lower($topic);$tokens=array_values(array_filter(preg_split('/[^a-z0-9]+/',$q)?:[],fn($x)=>strlen($x)>2));$scored=[];
    foreach(products_public() as $p){$t=ai_product_text($p);$score=0;foreach($tokens as $token)if(str_contains($t,$token))$score+=8;$i=(array)($p['intelligence']??[]);
        if(str_contains($q,'low light')&&str_contains(text_lower((string)($i['sunlight']??'')),'low'))$score+=40;
        if((str_contains($q,'beginner')||str_contains($q,'easy'))&&str_contains(text_lower((string)($i['difficulty']??'')),'easy'))$score+=35;
        if(str_contains($q,'indoor')&&str_contains(text_lower((string)($i['indoor_outdoor']??$p['category_label']??'')),'indoor'))$score+=25;
        if(str_contains($q,'balcony')&&str_contains($t,'balcony'))$score+=25;
        if(str_contains($q,'flower')&&(($i['flowering']??'unknown')==='yes'||str_contains($t,'flower')))$score+=25;
        if(product_has_image($p))$score+=4;if(product_is_sellable($p))$score+=3;$score+=min(10,(int)(ai_product_completeness($p)['overall']/10));
        $scored[]=['score'=>$score,'p'=>$p];
    }
    usort($scored,fn($a,$b)=>$b['score']<=>$a['score']?:strcmp((string)$a['p']['name'],(string)$b['p']['name']));return array_map(fn($x)=>$x['p'],array_slice($scored,0,max(2,min(30,$limit))));
}
function ai_build_opportunities(): array {
    $templates=[
      'Best Indoor Plants for Pune Homes','Low-Light Indoor Plants for Pune Apartments','Low-Maintenance Plants for Beginners','Balcony Plants for Pune','Plants for Office Desks','Flowering Plants for Small Gardens','Large Indoor Plants for Living Rooms','Monsoon Plant Care Picks for Pune','Plants for Compact Urban Gardens','Easy-Care Foliage Plants',
    ];$list=[];foreach($templates as $topic){$items=ai_listicle_candidates($topic,12);$list[]=['id'=>substr(hash('sha256',$topic),0,16),'type'=>'listicle','title'=>$topic,'candidate_count'=>count($items),'product_slugs'=>array_column($items,'slug'),'state'=>count($items)>=5?'high':'medium'];}
    $groups=[];foreach(products_public() as $p){$k=trim((string)($p['collection']??$p['category']??''));if($k!=='')$groups[$k][]=$p;}$comp=[];foreach($groups as $k=>$items){if(count($items)<2)continue;for($i=0;$i<min(count($items)-1,4);$i++){$a=$items[$i];$b=$items[$i+1];$comp[]=['id'=>substr(hash('sha256',$a['guid'].$b['guid']),0,16),'type'=>'comparison','title'=>$a['name'].' vs '.$b['name'],'product_slugs'=>[$a['slug'],$b['slug']],'state'=>'high'];if(count($comp)>=30)break 2;}}
    $out=['generated_at'=>gmdate('c'),'listicles'=>$list,'comparisons'=>$comp];foundation_json_write(ai_opportunities_file(),$out,null,'ai.opportunities');return$out;
}
function ai_opportunities(): array { $d=foundation_json_read(ai_opportunities_file(),[]);return $d?:ai_build_opportunities(); }

function ai_reviews(): array { $r=foundation_json_read(ai_reviews_file(),[]);return array_values(is_array($r)?$r:[]); }
function ai_history(): array { $r=foundation_json_read(ai_history_file(),[]);return array_values(is_array($r)?$r:[]); }
function ai_get_path(array $a,string $path): mixed { $v=$a;foreach(explode('.',$path) as $k){if(!is_array($v)||!array_key_exists($k,$v))return null;$v=$v[$k];}return$v; }
function ai_set_path(array &$a,string $path,mixed $value): void { $keys=explode('.',$path);$ref=&$a;$last=count($keys)-1;foreach($keys as $idx=>$k){if($idx===$last){$ref[$k]=$value;break;}if(!isset($ref[$k])||!is_array($ref[$k]))$ref[$k]=[];$ref=&$ref[$k];} }
function ai_add_history(array $entry): string { $id='AIH-'.strtoupper(bin2hex(random_bytes(5)));$entry=array_merge(['id'=>$id,'created_at'=>gmdate('c'),'actor'=>(admin_identity()['email']??'system')],$entry);foundation_json_mutate(ai_history_file(),function(array $rows)use($id,$entry){$rows[$id]=$entry;if(count($rows)>2000)$rows=array_slice($rows,-2000,null,true);return$rows;},'ai.history');return$id; }
function ai_create_review(array $product,array $changes,string $source='ai'): array { $id='AIR-'.strtoupper(bin2hex(random_bytes(5)));$row=['id'=>$id,'status'=>'pending','source'=>$source,'product_guid'=>$product['guid'],'product_name'=>$product['name'],'product_slug'=>$product['slug'],'base_hash'=>ai_catalogue_hash($product),'changes'=>$changes,'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];foundation_json_mutate(ai_reviews_file(),function(array $rows)use($id,$row){$rows[$id]=$row;return$rows;},'ai.review.create');return$row; }
function ai_enrichment_prompt(array $p): array {
    $payload=['name'=>$p['name'],'category'=>$p['category_label']??'','collection'=>$p['collection']??'','botanical_name'=>$p['botanical_name']??'','short'=>$p['short']??'','description'=>$p['description']??'','care_profile'=>$p['care_profile']??[],'intelligence'=>$p['intelligence']??[],'seo'=>$p['seo']??[],'tags'=>$p['tags']??[]];
    $system='You are a conservative plant catalogue editor for a Pune, India nursery. Return JSON only. Improve language and entity completeness from the supplied record. Never invent price, stock, SKU, availability, ratings, reviews, delivery promises, toxicity, pet-safety, medical, air-purifying, or other safety claims. For uncertain botanical genus/species, return an empty string. Pune guidance must be general climate-care wording, not a guaranteed claim. Every proposed field must have confidence 0-100.';
    $user="Product JSON:\n".json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\nReturn exactly: {\"values\":{\"short\":\"\",\"seo_title\":\"\",\"seo_description\":\"\",\"tags\":[],\"genus\":\"\",\"species\":\"\",\"growth_rate\":\"\",\"foliage_colour\":\"\",\"mature_size\":\"\",\"placement\":\"\",\"pune_guidance\":\"\"},\"confidence\":{same keys as numbers},\"notes\":\"\"}.";
    return[$system,$user];
}
function ai_product_enrich(string $guid): array {
    if(!ai_enrichment_ready())throw new RuntimeException('AI enrichment is disabled in the current mode or the provider is not ready.');
    $p=product_by_guid($guid);if(!$p)throw new RuntimeException('Product not found.');[$system,$user]=ai_enrichment_prompt($p);$r=ai_chat_json($system,$user);$v=(array)($r['values']??[]);$c=(array)($r['confidence']??[]);$map=['short'=>'short','seo_title'=>'seo.title','seo_description'=>'seo.description','tags'=>'tags','genus'=>'intelligence.genus','species'=>'intelligence.species','growth_rate'=>'intelligence.growth_rate','foliage_colour'=>'intelligence.foliage_colour','mature_size'=>'intelligence.mature_size','placement'=>'intelligence.placement','pune_guidance'=>'intelligence.pune_guidance'];$changes=[];
    foreach($map as $key=>$path){if(!array_key_exists($key,$v))continue;$after=$v[$key];if(is_string($after))$after=trim($after);if($after===''||$after===[])continue;$before=ai_get_path($p,$path);if($before===$after)continue;$conf=max(0,min(100,(int)($c[$key]??0)));$changes[]=['path'=>$path,'before'=>$before,'after'=>$after,'confidence'=>$conf,'protected'=>in_array($path,['intelligence.genus','intelligence.species','intelligence.mature_size'],true)];}
    if(!$changes)return ['status'=>'unchanged','changes'=>[]];$review=ai_create_review($p,$changes,'provider');$settings=ai_settings();if($settings['mode']==='safe_auto')ai_apply_review($review['id'],true);return ['status'=>'proposed','review_id'=>$review['id'],'changes'=>$changes];
}
function ai_apply_review(string $id,bool $auto=false): array {
    $reviews=foundation_json_read(ai_reviews_file(),[]);$row=$reviews[$id]??null;if(!$row||($row['status']??'pending')!=='pending')throw new RuntimeException('Review item is not pending.');$p=product_by_guid((string)$row['product_guid']);if(!$p)throw new RuntimeException('Product no longer exists.');if(!hash_equals((string)$row['base_hash'],ai_catalogue_hash($p))&&!$auto)throw new RuntimeException('Product changed after this suggestion was created. Rescan before applying.');
    $settings=ai_settings();$applied=[];$before=$p;
    foreach((array)$row['changes'] as $ch){$path=(string)($ch['path']??'');$confidence=(int)($ch['confidence']??0);$protected=!empty($ch['protected']);if($auto){if($protected||!in_array($path,(array)$settings['safe_auto_fields'],true))continue;$threshold=(int)($settings['auto_confidence'][$path]??100);if($confidence<$threshold)continue;}else{if($confidence<(int)$settings['minimum_review_confidence'])continue;}ai_set_path($p,$path,$ch['after']??null);$applied[]=$path;}
    if(!$applied){if($auto)return ['status'=>'review_required','applied'=>[]];throw new RuntimeException('No suggested field met the current application threshold.');}
    $p['updated_at']=gmdate('c');upsert_product($p,foundation_checksum(catalog_file()));$historyId=ai_add_history(['type'=>'product_update','product_guid'=>$p['guid'],'product_name'=>$p['name'],'review_id'=>$id,'auto'=>$auto,'paths'=>$applied,'before'=>$before,'after'=>$p]);
    foundation_json_mutate(ai_reviews_file(),function(array $rows)use($id,$auto,$historyId,$applied){if(isset($rows[$id])){$rows[$id]['status']=$auto?'auto_applied':'applied';$rows[$id]['history_id']=$historyId;$rows[$id]['applied_paths']=$applied;$rows[$id]['updated_at']=gmdate('c');}return$rows;},'ai.review.apply');return ['status'=>'applied','history_id'=>$historyId,'applied'=>$applied];
}
function ai_reject_review(string $id): void { require_admin();foundation_json_mutate(ai_reviews_file(),function(array $rows)use($id){if(isset($rows[$id])){$rows[$id]['status']='rejected';$rows[$id]['updated_at']=gmdate('c');}return$rows;},'ai.review.reject'); }
function ai_restore_history(string $id): void { require_admin();$rows=foundation_json_read(ai_history_file(),[]);$h=$rows[$id]??null;if(!$h||($h['type']??'')!=='product_update')throw new RuntimeException('Restorable AI history item not found.');$before=(array)($h['before']??[]);if(empty($before['guid']))throw new RuntimeException('Revision snapshot is invalid.');upsert_product($before,foundation_checksum(catalog_file()));ai_add_history(['type'=>'product_restore','product_guid'=>$before['guid'],'product_name'=>$before['name']??'','restored_history_id'=>$id]); }

function ai_jobs(): array { $r=foundation_json_read(ai_jobs_file(),[]);$rows=array_values(is_array($r)?$r:[]);usort($rows,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));return$rows; }
function ai_enqueue_job(string $type,array $payload=[],int $priority=50): string { ai_ensure_dirs();$id='AIJ-'.strtoupper(bin2hex(random_bytes(5)));$row=['id'=>$id,'type'=>$type,'payload'=>$payload,'priority'=>$priority,'status'=>'queued','attempts'=>0,'created_at'=>gmdate('c'),'started_at'=>null,'completed_at'=>null,'last_error'=>''];foundation_json_mutate(ai_jobs_file(),function(array $rows)use($id,$row){$rows[$id]=$row;return$rows;},'ai.job.enqueue');return$id; }
function ai_queue_catalogue_scan(bool $includeEnrichment=false): array { $ids=[ai_enqueue_job('catalogue_scan',[],100),ai_enqueue_job('seo_scan',[],90),ai_enqueue_job('opportunity_scan',[],80)];if($includeEnrichment){if(!ai_enrichment_ready())throw new RuntimeException('Switch AI Engine to Review or Safe Auto and configure the provider before enrichment jobs can be queued.');$previous=foundation_json_read(ai_catalogue_scan_file(),[]);$settings=ai_settings();$candidates=[];foreach(products_all() as $p){$prev=$previous['products'][$p['guid']]['hash']??'';if(!empty($settings['scan_changed_only'])&&$prev!==''&&hash_equals($prev,ai_catalogue_hash($p)))continue;$candidates[]=['p'=>$p,'score'=>ai_product_completeness($p)['overall']];}usort($candidates,fn($a,$b)=>$a['score']<=>$b['score']);foreach(array_slice($candidates,0,(int)$settings['product_batch_size']) as $c)$ids[]=ai_enqueue_job('product_enrich',['guid'=>$c['p']['guid']],60);}return$ids; }
function ai_job_execute(array $job): array { $type=(string)$job['type'];$payload=(array)($job['payload']??[]);return match($type){'catalogue_scan'=>['scan'=>ai_scan_catalogue()],'seo_scan'=>['seo'=>ai_seo_scan()],'opportunity_scan'=>['opportunities'=>ai_build_opportunities()],'product_enrich'=>ai_product_enrich((string)($payload['guid']??'')),default=>throw new RuntimeException('Unsupported AI job type: '.$type)}; }
function ai_process_jobs(?int $limit=null): array {
    $settings=ai_settings();$limit=$limit??(int)$settings['max_jobs_per_run'];$limit=max(1,min(50,$limit));$processed=0;$failed=0;$completed=[];
    $all=foundation_json_read(ai_jobs_file(),[]);$hourAgo=time()-3600;$recent=0;foreach($all as $j){$started=strtotime((string)($j['started_at']??''));if($started!==false&&$started>=$hourAgo)$recent++;}$remaining=max(0,(int)$settings['max_jobs_per_hour']-$recent);if($remaining<=0)return ['processed'=>0,'failed'=>0,'completed'=>[],'throttled'=>true];$limit=min($limit,$remaining);$queued=array_filter($all,fn($j)=>($j['status']??'')==='queued');uasort($queued,fn($a,$b)=>(int)($b['priority']??0)<=>(int)($a['priority']??0)?:strcmp((string)$a['created_at'],(string)$b['created_at']));
    foreach(array_slice($queued,0,$limit,true) as $id=>$job){$processed++;foundation_json_mutate(ai_jobs_file(),function(array $rows)use($id){if(isset($rows[$id])){$rows[$id]['status']='running';$rows[$id]['started_at']=gmdate('c');$rows[$id]['attempts']=(int)($rows[$id]['attempts']??0)+1;}return$rows;},'ai.job.start');try{$result=ai_job_execute($job);foundation_json_mutate(ai_jobs_file(),function(array $rows)use($id,$result){if(isset($rows[$id])){$rows[$id]['status']='completed';$rows[$id]['completed_at']=gmdate('c');$rows[$id]['result_summary']=array_keys($result);}return$rows;},'ai.job.complete');$completed[]=$id;ai_usage_add(0,0,0,1);}catch(Throwable $e){$failed++;foundation_json_mutate(ai_jobs_file(),function(array $rows)use($id,$e){if(isset($rows[$id])){$rows[$id]['status']='failed';$rows[$id]['completed_at']=gmdate('c');$rows[$id]['last_error']=substr($e->getMessage(),0,800);}return$rows;},'ai.job.fail');monitor_record_incident(['source'=>'ai','category'=>'job','severity'=>'error','title'=>'AI job failed','message'=>$job['type'].': '.$e->getMessage(),'route'=>'/admin/ai/jobs','method'=>'SYSTEM']);}}
    return ['processed'=>$processed,'failed'=>$failed,'completed'=>$completed];
}
function ai_retry_job(string $id): void { require_admin();foundation_json_mutate(ai_jobs_file(),function(array $rows)use($id){if(isset($rows[$id])){$rows[$id]['status']='queued';$rows[$id]['last_error']='';$rows[$id]['completed_at']=null;}return$rows;},'ai.job.retry'); }

function ai_content_store(): array { ai_ensure_dirs();$d=foundation_json_read(ai_content_file(),[]);return array_replace(['comparisons'=>[],'listicles'=>[],'updated_at'=>null],$d); }
function ai_content_write(array $s,string $reason): void { $s['updated_at']=gmdate('c');foundation_json_write(ai_content_file(),$s,null,$reason); }
function ai_content_slug(string $v): string { return cms_slugify($v); }
function ai_comparison_by_slug(string $slug,bool $publicOnly=true): ?array { foreach(ai_content_store()['comparisons'] as $r)if(($r['slug']??'')===$slug&&(!$publicOnly||($r['status']??'')==='PUBLISHED'))return$r;return null; }
function ai_listicle_by_slug(string $slug,bool $publicOnly=true): ?array { foreach(ai_content_store()['listicles'] as $r)if(($r['slug']??'')===$slug&&(!$publicOnly||($r['status']??'')==='PUBLISHED'))return$r;return null; }
function ai_content_product_rows(array $row): array { $out=[];foreach((array)($row['product_slugs']??[]) as $slug){$p=product_by_slug((string)$slug);if($p)$out[]=$p;}return$out; }
function ai_create_comparison(array $slugs,string $title=''): array { require_admin();$slugs=array_values(array_unique(array_filter(array_map('trim',$slugs))));if(count($slugs)<2||count($slugs)>4)throw new RuntimeException('Choose 2 to 4 products for a comparison.');$products=[];foreach($slugs as $s){$p=product_by_slug($s);if(!$p)throw new RuntimeException('Comparison product not found: '.$s);$products[]=$p;}$title=trim($title)?:implode(' vs ',array_column($products,'name'));$slug=ai_content_slug($title);$store=ai_content_store();foreach($store['comparisons'] as $r)if(($r['slug']??'')===$slug)throw new RuntimeException('A comparison with this slug already exists.');$row=['guid'=>uuidv7(),'type'=>'COMPARISON','title'=>$title,'slug'=>$slug,'status'=>'DRAFT','product_slugs'=>$slugs,'intro'=>'Compare verified catalogue attributes, care guidance and current commerce readiness side by side. Live price and availability are shown only when verified variants exist.','seo'=>['title'=>$title.' | Sanjay Nursery Pune','description'=>'Compare '.implode(', ',array_column($products,'name')).' for light, watering, care difficulty, size and live nursery availability at Sanjay Nursery Pune.'],'created_at'=>gmdate('c'),'updated_at'=>gmdate('c'),'published_at'=>null];$store['comparisons'][]=$row;ai_content_write($store,'ai.comparison.create');audit_event('ai.comparison.create',['slug'=>$slug]);return$row; }
function ai_create_listicle(string $topic,int $limit=10): array { require_admin();$topic=trim($topic);if($topic==='')throw new RuntimeException('Listicle topic is required.');$items=ai_listicle_candidates($topic,$limit);if(count($items)<2)throw new RuntimeException('Not enough catalogue candidates for this listicle.');$store=ai_content_store();$slug=ai_content_slug($topic);foreach($store['listicles'] as $r)if(($r['slug']??'')===$slug)throw new RuntimeException('A listicle with this slug already exists.');$row=['guid'=>uuidv7(),'type'=>'LISTICLE','title'=>$topic,'slug'=>$slug,'status'=>'DRAFT','product_slugs'=>array_column($items,'slug'),'intro'=>'A data-led Sanjay Nursery selection built from catalogue care attributes and current product records. Verify live pot size, price and availability before purchase.','seo'=>['title'=>$topic.' | Sanjay Nursery Pune','description'=>'Explore '.$topic.' with practical care attributes, product links and current Sanjay Nursery availability.'],'created_at'=>gmdate('c'),'updated_at'=>gmdate('c'),'published_at'=>null];$store['listicles'][]=$row;ai_content_write($store,'ai.listicle.create');audit_event('ai.listicle.create',['slug'=>$slug,'items'=>count($items)]);return$row; }
function ai_publish_content(string $kind,string $guid,bool $publish=true): array { require_admin();if(!in_array($kind,['comparisons','listicles'],true))throw new RuntimeException('Invalid AI content type.');$store=ai_content_store();$out=null;foreach($store[$kind] as &$r)if(($r['guid']??'')===$guid){if(count((array)($r['product_slugs']??[]))<2)throw new RuntimeException('At least two valid products are required.');$r['status']=$publish?'PUBLISHED':'DRAFT';$r['published_at']=$publish?($r['published_at']??gmdate('c')):null;$r['updated_at']=gmdate('c');$out=$r;break;}unset($r);if(!$out)throw new RuntimeException('Content item not found.');ai_content_write($store,'ai.content.publish');return$out; }
function ai_delete_content(string $kind,string $guid): void { require_admin();if(!in_array($kind,['comparisons','listicles'],true))return;$store=ai_content_store();$store[$kind]=array_values(array_filter($store[$kind],fn($r)=>($r['guid']??'')!==$guid));ai_content_write($store,'ai.content.delete'); }
function ai_comparison_matrix(array $products): array { $rows=[
 'Botanical name'=>fn($p)=>$p['botanical_name']??'Not verified','Light'=>fn($p)=>$p['intelligence']['sunlight']??$p['care_profile']['light']??'Not specified','Watering'=>fn($p)=>$p['intelligence']['watering']??$p['care_profile']['water']??'Not specified','Care difficulty'=>fn($p)=>$p['intelligence']['difficulty']??$p['care_profile']['difficulty']??'Not specified','Growth rate'=>fn($p)=>$p['intelligence']['growth_rate']??'Not verified','Mature size'=>fn($p)=>$p['intelligence']['mature_size']??'Not verified','Placement'=>fn($p)=>$p['intelligence']['placement']??$p['care_profile']['placement']??'Not specified','Pune guidance'=>fn($p)=>$p['intelligence']['pune_guidance']??'General care guidance only','Pet safety'=>fn($p)=>(($p['intelligence']['pet_safe']??'unknown')==='unknown'?'Not verified':ucfirst((string)$p['intelligence']['pet_safe'])),'Availability'=>fn($p)=>product_has_sellable_variants($p)?'Live variants available':'Enquire for current batch','Price'=>fn($p)=>product_price_label($p),
 ];$out=[];foreach($rows as $label=>$fn){$vals=[];foreach($products as $p)$vals[]=$fn($p);$out[]=['label'=>$label,'values'=>$vals];}return$out; }
function ai_content_is_stale(array $row): bool { $updated=strtotime((string)($row['updated_at']??'1970-01-01'))?:0;foreach(ai_content_product_rows($row) as $p){$pu=strtotime((string)($p['updated_at']??'1970-01-01'))?:0;if($pu>$updated)return true;}return false; }
function ai_content_sitemap_urls(): array { $out=[];$s=ai_content_store();$pc=array_values(array_filter($s['comparisons'],fn($r)=>($r['status']??'')==='PUBLISHED'));$pl=array_values(array_filter($s['listicles'],fn($r)=>($r['status']??'')==='PUBLISHED'));if($pc)$out[]=['loc'=>'/compare','lastmod'=>substr((string)max(array_column($pc,'updated_at')),0,10)];foreach($pc as $r)$out[]=['loc'=>'/compare/'.$r['slug'],'lastmod'=>substr((string)$r['updated_at'],0,10)];if($pl)$out[]=['loc'=>'/lists','lastmod'=>substr((string)max(array_column($pl,'updated_at')),0,10)];foreach($pl as $r)$out[]=['loc'=>'/lists/'.$r['slug'],'lastmod'=>substr((string)$r['updated_at'],0,10)];return$out; }
function ai_related_content_for_product(array $p,int $limit=4): array { $out=[];$slug=(string)$p['slug'];$s=ai_content_store();foreach(['comparisons'=>'comparison','listicles'=>'listicle'] as $bucket=>$type)foreach($s[$bucket] as $r)if(($r['status']??'')==='PUBLISHED'&&in_array($slug,(array)$r['product_slugs'],true)){$out[]=['type'=>$type,'title'=>$r['title'],'url'=>($type==='comparison'?'/compare/':'/lists/').$r['slug']];if(count($out)>=$limit)return$out;}return$out; }

function ai_agent_capabilities(): array { return ['schema_version'=>'1.0','site'=>'Sanjay Nursery','version'=>$GLOBALS['app']['version'],'catalogue'=>['search'=>absolute_url('/api/agent/catalog/search'),'feed'=>absolute_url('/feeds/agent-products.json'),'ai_feed'=>absolute_url('/feeds/ai-products.json'),'content_feed'=>absolute_url('/feeds/ai-content.json')],'actions'=>['catalog_search'=>['auth'=>'none'],'order_read'=>['auth'=>'bearer','scope'=>'orders:read']],'human_approval_required'=>['purchase','payment','order_submission'],'currency'=>'INR','truthfulness'=>['price'=>'verified commerce data only','availability'=>'verified live variants only','safety_claims'=>'never inferred by agent feed']]; }
function ai_openapi_spec(): array { return ['openapi'=>'3.1.0','info'=>['title'=>'Sanjay Nursery Agent API','version'=>$GLOBALS['app']['version']],'servers'=>[['url'=>absolute_url('/')]],'paths'=>['/api/agent/catalog/search'=>['get'=>['summary'=>'Search public plant catalogue','parameters'=>[['name'=>'q','in'=>'query','schema'=>['type'=>'string']]],'responses'=>['200'=>['description'=>'Catalogue results']]]],'/api/agent/orders/{id}'=>['get'=>['summary'=>'Read a customer order with a scoped bearer token','security'=>[['bearerAuth'=>[]]],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'string']]],'responses'=>['200'=>['description'=>'Order']]]]],'components'=>['securitySchemes'=>['bearerAuth'=>['type'=>'http','scheme'=>'bearer']]]]; }

function ai_metrics(): array { $scan=ai_catalogue_scan();$seo=ai_seo_report();$jobs=ai_jobs();$reviews=ai_reviews();$content=ai_content_store();$usage=ai_usage();return ['enabled'=>!empty(ai_settings()['enabled']),'mode'=>ai_settings()['mode'],'provider_ready'=>ai_provider_ready(),'catalogue_health'=>(int)($scan['average']??0),'seo_health'=>(int)($seo['score']??0),'pending_reviews'=>count(array_filter($reviews,fn($r)=>($r['status']??'')==='pending')),'queued_jobs'=>count(array_filter($jobs,fn($j)=>($j['status']??'')==='queued')),'failed_jobs'=>count(array_filter($jobs,fn($j)=>($j['status']??'')==='failed')),'comparisons'=>count($content['comparisons']),'listicles'=>count($content['listicles']),'published_comparisons'=>count(array_filter($content['comparisons'],fn($r)=>($r['status']??'')==='PUBLISHED')),'published_listicles'=>count(array_filter($content['listicles'],fn($r)=>($r['status']??'')==='PUBLISHED')),'usage'=>$usage,'last_catalogue_scan'=>$scan['generated_at']??null,'last_seo_scan'=>$seo['generated_at']??null]; }
function ai_health(): array { $m=ai_metrics();$s=ai_settings();$provider=empty($s['enabled'])?'OFF':((string)$s['mode']==='scan_only'?'NOT REQUIRED':(ai_provider_ready()?'READY':'SETUP'));return ['status'=>(ai_provider_required()&&!ai_provider_ready())?'DEGRADED':'HEALTHY','provider'=>$provider,'text_model'=>$s['text_model']?:'Not selected','queued_jobs'=>$m['queued_jobs'],'failed_jobs'=>$m['failed_jobs'],'catalogue_health'=>$m['catalogue_health'],'seo_health'=>$m['seo_health'],'last_catalogue_scan'=>$m['last_catalogue_scan'],'daily_requests'=>(int)($m['usage']['requests']??0),'daily_tokens'=>(int)($m['usage']['total_tokens']??0)]; }
function ai_release_checks(): array { $s=ai_settings();$content=ai_content_store();$privateOutside=!str_starts_with(realpath(ai_root())?:'',realpath(dirname(__DIR__).'/public')?:'__public__');$jobsOk=is_dir(ai_root())&&is_writable(ai_root());$contentOk=is_array($content['comparisons'])&&is_array($content['listicles']);$providerOk=!ai_provider_required()||ai_provider_ready();return [
 ['name'=>'AI engine private boundary','ok'=>$privateOutside,'detail'=>'AI credentials, queues, reviews and usage remain outside the public document root.'],
 ['name'=>'AI job store','ok'=>$jobsOk,'detail'=>'Web-managed AI job queue is writable.'],
 ['name'=>'AI content store schema','ok'=>$contentOk,'detail'=>'Comparison and listicle stores are structurally valid.'],
 ['name'=>'AI provider readiness','ok'=>$providerOk,'detail'=>$providerOk?'Provider is not required in Scan Only mode, or is fully configured for enrichment.':'Review/Safe Auto is enabled but API key/model/provider configuration is incomplete.'],
 ['name'=>'AI protected-field policy','ok'=>count(array_intersect(['price','sku','inventory','availability'],(array)$s['protected_fields']))===4,'detail'=>'Commercial source-of-truth fields are protected from AI auto-write.'],
 ['name'=>'Agent capability manifest','ok'=>isset(ai_agent_capabilities()['human_approval_required']),'detail'=>'Agent capabilities explicitly require human approval for purchase/payment actions.'],
 ]; }

function ai_scheduler_due(): bool { $s=ai_settings();if(empty($s['enabled'])||$s['scan_frequency']==='manual')return false;$state=foundation_json_read(ai_state_file(),[]);$last=strtotime((string)($state['last_scheduled_scan']??'1970-01-01'));$seconds=match($s['scan_frequency']){'daily'=>86400,'every_3_days'=>259200,'weekly'=>604800,default=>PHP_INT_MAX};return time()-$last>=$seconds; }
function ai_schedule_if_due(): void { if(!ai_scheduler_due())return;ai_queue_catalogue_scan(ai_enrichment_ready());foundation_json_mutate(ai_state_file(),function(array $s){$s['last_scheduled_scan']=gmdate('c');return$s;},'ai.schedule'); }
function ai_shutdown_runner(): void {
    if(PHP_SAPI==='cli'||empty(ai_settings()['auto_web_runner'])||empty(ai_settings()['enabled']))return;
    ai_schedule_if_due();$state=foundation_json_read(ai_state_file(),[]);$interval=(int)ai_settings()['runner_interval_seconds'];$last=strtotime((string)($state['last_runner']??'1970-01-01'));if(time()-$last<$interval)return;
    $fh=@fopen(ai_lock_file(),'c+');if(!$fh||!flock($fh,LOCK_EX|LOCK_NB)){if($fh)fclose($fh);return;}
    try{foundation_json_mutate(ai_state_file(),function(array $s){$s['last_runner']=gmdate('c');return$s;},'ai.runner.state');if(function_exists('fastcgi_finish_request'))@fastcgi_finish_request();@ignore_user_abort(true);@set_time_limit(20);ai_process_jobs(1);}catch(Throwable $e){monitor_record_incident(['source'=>'ai','category'=>'scheduler','severity'=>'warning','title'=>'AI web runner failed','message'=>$e->getMessage(),'route'=>'/admin/ai/jobs','method'=>'SYSTEM']);}finally{flock($fh,LOCK_UN);fclose($fh);}
}

ai_ensure_dirs();
