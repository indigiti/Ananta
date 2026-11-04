<?php
declare(strict_types=1);
/** Sanjay Nursery Editorial CMS + Growth Workflow — v1.4.0 */
function cms_root(): string { return data_root().'/public-domain/content'; }
function cms_file(): string { return cms_root().'/cms.json'; }
function cms_ensure_dirs(): void { $d=cms_root();if(!is_dir($d)&&!@mkdir($d,0750,true)&&!is_dir($d))throw new RuntimeException('Unable to create CMS content directory.');@chmod($d,0750); }
function cms_empty(): array { return ['content'=>[],'blocks'=>[],'redirects'=>[],'updated_at'=>null]; }
function cms_store(): array { cms_ensure_dirs();$s=foundation_json_read(cms_file(),cms_empty());return array_merge(cms_empty(),$s); }
function cms_write(array $store,string $reason='cms.update'): void { $store['updated_at']=gmdate('c');foundation_json_write(cms_file(),$store,foundation_checksum(cms_file()),$reason); }
function cms_types(): array { return ['JOURNAL'=>'Journal','GUIDE'=>'Guide','ROOM_STORY'=>'Room Story','PAGE'=>'Page','FAQ'=>'FAQ']; }
function cms_route_prefix(string $type): string { return match($type){'JOURNAL'=>'/journal/','GUIDE'=>'/guides/','ROOM_STORY'=>'/stories/','FAQ'=>'/faq/','PAGE'=>'/pages/',default=>'/pages/'}; }
function cms_content_route(array $row): string { return cms_route_prefix((string)($row['type']??'PAGE')).(string)($row['slug']??''); }
function cms_items(?string $type=null,?string $status=null): array { $r=cms_store()['content'];if($type)$r=array_values(array_filter($r,fn($x)=>($x['type']??'')===$type));if($status)$r=array_values(array_filter($r,fn($x)=>($x['status']??'')===$status));usort($r,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));return$r; }
function cms_item_by_guid(string $guid): ?array { foreach(cms_store()['content'] as $x)if(($x['guid']??'')===$guid)return$x;return null; }
function cms_public_by_slug(string $type,string $slug): ?array { foreach(cms_store()['content'] as $x)if(($x['type']??'')===$type&&($x['slug']??'')===$slug&&($x['status']??'')==='PUBLISHED')return$x;return null; }
function cms_slugify(string $v): string { $v=text_lower(trim($v));$v=preg_replace('/[^a-z0-9]+/','-',$v);return trim((string)$v,'-'); }
function cms_save_item(array $input,?string $guid=null): array {
    require_admin();
    $store=cms_store();
    $existing=$guid?cms_item_by_guid($guid):null;
    $title=trim((string)($input['title']??''));
    $type=strtoupper(trim((string)($input['type']??'PAGE')));
    $slug=cms_slugify((string)($input['slug']??$title));
    if($title===''||$slug==='')throw new RuntimeException('Title and slug are required.');
    if(!array_key_exists($type,cms_types()))throw new RuntimeException('Unsupported content type.');
    foreach($store['content'] as $x)if(($x['type']??'')===$type&&($x['slug']??'')===$slug&&($x['guid']??'')!==($guid??''))throw new RuntimeException('This content route already exists.');
    $body=trim((string)($input['body']??''));
    $sources=[];
    foreach(preg_split('/\r?\n/',(string)($input['source_urls']??''))?:[] as $u){$u=trim($u);if($u!==''&&filter_var($u,FILTER_VALIDATE_URL))$sources[]=$u;}
    $oldEd=(array)($existing['editorial']??[]);
    $row=[
        'guid'=>$guid ?: uuidv7(),
        'type'=>$type,
        'title'=>$title,
        'slug'=>$slug,
        'status'=>$existing['status']??'DRAFT',
        'excerpt'=>trim((string)($input['excerpt']??'')),
        'body'=>$body,
        'hero_image'=>trim((string)($input['hero_image']??'')),
        'seo'=>[
            'title'=>trim((string)($input['seo_title']??'')) ?: $title,
            'description'=>trim((string)($input['seo_description']??'')) ?: trim((string)($input['excerpt']??'')),
            'noindex'=>!empty($input['noindex'])
        ],
        'editorial'=>[
            'target_query'=>trim((string)($input['target_query']??($oldEd['target_query']??''))),
            'search_intent'=>trim((string)($input['search_intent']??($oldEd['search_intent']??''))),
            'audience'=>trim((string)($input['audience']??($oldEd['audience']??''))),
            'expert_reviewer'=>trim((string)($input['expert_reviewer']??($oldEd['expert_reviewer']??''))),
            'firsthand_evidence'=>trim((string)($input['firsthand_evidence']??($oldEd['firsthand_evidence']??''))),
            'source_urls'=>$sources ?: (array)($oldEd['source_urls']??[]),
            'reviewed_at'=>trim((string)($input['expert_reviewer']??''))!==''?gmdate('c'):($oldEd['reviewed_at']??null),
        ],
        'created_at'=>$existing['created_at']??gmdate('c'),
        'updated_at'=>gmdate('c'),
        'published_at'=>$existing['published_at']??null,
        'author'=>$existing['author']??(admin_identity()['name']??'Sanjay Nursery Studio')
    ];
    $found=false;
    foreach($store['content'] as &$x)if(($x['guid']??'')===$row['guid']){$x=$row;$found=true;break;}
    if(!$found)$store['content'][]=$row;
    cms_write($store,'cms.item.save');
    audit_event('cms.item.save',['content_guid'=>$row['guid'],'type'=>$type,'quality_score'=>function_exists('growth_content_quality')?growth_content_quality($row)['score']:null]);
    return$row;
}
function cms_publish(string $guid,bool $publish=true): array { require_admin();$store=cms_store();$updated=null;foreach($store['content'] as &$x)if(($x['guid']??'')===$guid){if($publish&&function_exists('growth_content_can_publish')){$gate=growth_content_can_publish($x);if(empty($gate['ok']))throw new RuntimeException('Content quality score '.$gate['quality']['score'].' is below the publish threshold '.$gate['quality']['publish_threshold'].'. Complete the editorial evidence fields before publishing.');}$x['status']=$publish?'PUBLISHED':'DRAFT';$x['published_at']=$publish?($x['published_at']??gmdate('c')):null;$x['updated_at']=gmdate('c');$updated=$x;break;}if(!$updated)throw new RuntimeException('Content not found.');cms_write($store,'cms.item.publish');audit_event('cms.item.publish',['content_guid'=>$guid,'published'=>$publish]);return$updated; }
function cms_save_block(string $key,string $title,string $body): array { require_admin();$key=cms_slugify($key);if($key===''||trim($body)==='')throw new RuntimeException('Block key and body are required.');$store=cms_store();$row=['guid'=>uuidv7(),'key'=>$key,'title'=>trim($title),'body'=>trim($body),'updated_at'=>gmdate('c')];$found=false;foreach($store['blocks'] as &$b)if(($b['key']??'')===$key){$row['guid']=$b['guid'];$b=$row;$found=true;break;}if(!$found)$store['blocks'][]=$row;cms_write($store,'cms.block.save');return$row; }
function cms_blocks_map(): array { $out=[];foreach(cms_store()['blocks'] as $b)$out[$b['key']]=$b;return$out; }
function cms_render_body(string $body): string { $blocks=cms_blocks_map();$body=preg_replace_callback('/\[\[block:([a-z0-9-]+)\]\]/',function($m)use($blocks){$b=$blocks[$m[1]]??null;return $b?"\n\n".$b['title']."\n".$b['body']."\n\n":'';},$body);$paras=preg_split('/\n\s*\n/',trim($body));$html='';foreach($paras as $p){$p=trim($p);if($p==='')continue;if(str_starts_with($p,'## ')){$html.='<h2>'.e(substr($p,3)).'</h2>';continue;}if(str_starts_with($p,'### ')){$html.='<h3>'.e(substr($p,4)).'</h3>';continue;}$lines=preg_split('/\n/',$p);if(count($lines)>1&&count(array_filter($lines,fn($l)=>str_starts_with(trim($l),'- ')))===count($lines)){$html.='<ul>';foreach($lines as $l)$html.='<li>'.e(substr(trim($l),2)).'</li>';$html.='</ul>';}else{$html.='<p>'.nl2br(e($p)).'</p>';}}return$html; }
function cms_add_redirect(string $from,string $to,int $code=301): array { require_admin();$from='/'.trim(parse_url(trim($from),PHP_URL_PATH)??'','/');$to='/'.trim(parse_url(trim($to),PHP_URL_PATH)??'','/');if($from==='/'||$to==='/'&&$from===$to||$from===$to)throw new RuntimeException('Redirect source and destination must be different valid paths.');if(!in_array($code,[301,302],true))$code=301;$store=cms_store();foreach($store['redirects'] as $r)if(($r['from']??'')===$from)throw new RuntimeException('Redirect source already exists.');$row=['guid'=>uuidv7(),'from'=>$from,'to'=>$to,'code'=>$code,'created_at'=>gmdate('c')];$store['redirects'][]=$row;cms_write($store,'cms.redirect.add');return$row; }
function cms_redirect_for_path(string $path): ?array { foreach(cms_store()['redirects'] as $r)if(($r['from']??'')===$path)return$r;return null; }
function cms_sitemap_urls(): array { $today=gmdate('Y-m-d');$urls=[['loc'=>'/','lastmod'=>$today],['loc'=>'/shop','lastmod'=>$today],['loc'=>'/materials','lastmod'=>$today],['loc'=>'/about','lastmod'=>$today],['loc'=>'/contact','lastmod'=>$today],['loc'=>'/journal','lastmod'=>$today]];if(function_exists('growth_collection_map'))foreach(growth_collection_map() as $hub)$urls[]=['loc'=>'/collections/'.$hub['slug'],'lastmod'=>$today];if(function_exists('discovery_intent_pages')){if(!empty(discovery_settings()['finder_enabled']))$urls[]=['loc'=>'/plant-finder','lastmod'=>$today];foreach(discovery_intent_pages() as $slug=>$intent)if(count(discovery_intent_items(['slug'=>$slug]+$intent,products_public()))>0)$urls[]=['loc'=>'/plants/'.$slug,'lastmod'=>$today];}foreach(products_public() as $p){$row=['loc'=>'/product/'.$p['slug'],'lastmod'=>substr((string)($p['updated_at']??gmdate('c')),0,10)];if(product_has_image($p))$row['image']=image_url(mediaos_primary_file($p));$urls[]=$row;}foreach(cms_items(null,'PUBLISHED') as $c)if(empty($c['seo']['noindex']))$urls[]=['loc'=>cms_content_route($c),'lastmod'=>substr((string)($c['updated_at']??gmdate('c')),0,10),'image'=>trim((string)($c['hero_image']??''))!==''?asset('images/products/'.basename((string)$c['hero_image'])):''];if(function_exists('ai_content_sitemap_urls'))foreach(ai_content_sitemap_urls() as $u)$urls[]=$u;return$urls; }
function cms_release_checks(): array { $s=cms_store();$routes=[];$schema=true;$seo=true;foreach($s['content'] as $c){foreach(['guid','type','title','slug','status','seo','updated_at'] as $k)if(!array_key_exists($k,$c))$schema=false;$route=cms_content_route($c);$routes[]=$route;if(($c['status']??'')==='PUBLISHED'&&(trim((string)($c['seo']['title']??''))===''||trim((string)($c['seo']['description']??''))===''))$seo=false;}$redirectOk=true;$map=[];foreach($s['redirects'] as $r)$map[$r['from']]=$r['to'];foreach($map as $from=>$to){$seen=[$from=>true];$cur=$to;for($i=0;$i<20;$i++){if(isset($seen[$cur])){$redirectOk=false;break;}$seen[$cur]=true;if(!isset($map[$cur]))break;$cur=$map[$cur];}}return [
 ['name'=>'CMS schema','ok'=>$schema,'detail'=>'CMS entries contain required durable fields.'],
 ['name'=>'CMS route uniqueness','ok'=>count($routes)===count(array_unique($routes)),'detail'=>'Published/editorial routes are unique.'],
 ['name'=>'CMS published SEO completeness','ok'=>$seo,'detail'=>'Published indexable content has SEO title and description.'],
 ['name'=>'CMS redirect loop protection','ok'=>$redirectOk,'detail'=>'Redirect graph contains no detected loops.'],
 ['name'=>'CMS sitemap coverage','ok'=>count(cms_sitemap_urls())>=count(products_public())+count(function_exists('growth_collection_map')?growth_collection_map():[])+4,'detail'=>'Sitemap includes core routes, indexable collection hubs, products and published CMS content.'],
 ]; }
cms_ensure_dirs();
