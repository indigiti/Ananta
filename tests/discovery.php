<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
if(!getenv('SANJAY_DATA_KEY'))putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('D',32)));
if(!getenv('SANJAY_SANDBOX_WEBHOOK_SECRET'))putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=discovery-secret');
if(!getenv('SANJAY_AGENT_SIGNING_KEY'))putenv('SANJAY_AGENT_SIGNING_KEY=discovery-agent-key-0123456789');
$_SERVER['HTTP_HOST']='staging.example.test';$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REMOTE_ADDR']='127.0.0.1';
require $root.'/src/bootstrap.php';
$errors=[];$all=products_public();
if(count($all)!==376)$errors[]='Expected 376 parent plant varieties.';
if(count(variants_all())!==0)$errors[]='Clean discovery baseline must not ship fabricated live variants.';

$agl=discovery_filter_catalog($all,['q'=>'aglaonema']);
if(count($agl)!==19)$errors[]='Aglaonema search should return exactly 19 requested varieties.';
$typo=discovery_filter_catalog($all,['q'=>'agloneema']);
if(count($typo)!==19)$errors[]='Typo-tolerant Aglaonema search is not stable.';
$fit=discovery_filter_catalog($all,['q'=>'fitonia']);
if(count($fit)!==14)$errors[]='Typo-tolerant Fittonia search should return 14 varieties.';
$zz=discovery_filter_catalog($all,['q'=>'zz']);
if(count($zz)<6||count(array_filter($zz,fn($p)=>str_contains(text_lower((string)$p['collection']),'zamioculcas')))===0)$errors[]='ZZ synonym search does not resolve the Zamioculcas collection.';
$snake=discovery_filter_catalog($all,['q'=>'snake plant']);
if(count($snake)<10||count($snake)>20)$errors[]='Snake Plant synonym search leaked generic “plant” matches.';
foreach($snake as $p)if(!str_contains(text_lower((string)$p['collection']),'sansevieria')&&!str_contains(text_lower((string)$p['name']),'snake')){$errors[]='Snake Plant search returned an unrelated variety: '.($p['name']??'unknown');break;}

$low=discovery_filter_catalog($all,['light'=>'low-light']);
if(!$low)$errors[]='Low-light facet returned no verified catalogue matches.';
foreach($low as $p)if(discovery_product_facets($p)['light']!=='low-light'){$errors[]='Low-light filter admitted a conflicting care profile.';break;}
$live=discovery_filter_catalog($all,['availability'=>'live']);
if($live)$errors[]='Live-stock filter fabricated products on a zero-variant baseline.';
$priced=discovery_filter_catalog($all,['price_max'=>500]);
if($priced)$errors[]='Price filter assigned prices to enquiry-only parent varieties.';

$finder=discovery_finder_results(['placement'=>'indoor','light'=>'indirect'],12);
if(!$finder)$errors[]='Plant Finder returned no matches for a supported indoor/indirect scenario.';
foreach($finder as $row){if(empty($row['product'])||($row['score']??0)<=0){$errors[]='Plant Finder returned an invalid scored row.';break;}}
$liveFinder=discovery_finder_results(['placement'=>'indoor','light'=>'indirect','live_only'=>1],12);
if($liveFinder)$errors[]='Plant Finder live-only mode fabricated stock.';

$pages=discovery_intent_pages();if(count($pages)!==count(array_unique(array_keys($pages))))$errors[]='Intent-page slugs are not unique.';
if(count(discovery_intent_items(['slug'=>'low-light']+$pages['low-light'],$all))!==count($low))$errors[]='Low-light intent page and catalogue facet disagree.';
$ready=discovery_readiness();
foreach(['products'=>376,'with_variants'=>0,'live_products'=>0,'priced_products'=>0,'media_ready_products'=>0,'approved_images'=>0,'fully_activated'=>0] as $k=>$v)if((int)($ready[$k]??-1)!==$v)$errors[]="Discovery readiness mismatch for $k.";

$roles=mediaos_required_roles();if($roles!==['hero','full_plant','foliage','pot_scale'])$errors[]='Plant photography production gate must require hero/full plant/foliage/pot-scale roles.';
if(mediaos_role_approved(['file'=>'sample.webp','status'=>'approved','rights'=>'unverified']))$errors[]='Media approval accepted unverified image rights.';
if(!mediaos_role_approved(['file'=>'sample.webp','status'=>'approved','rights'=>'owned']))$errors[]='Media approval rejected an otherwise valid rights-cleared role.';

$shop=(string)file_get_contents($root.'/templates/shop.php');$finderTpl=(string)file_get_contents($root.'/templates/plant-finder.php');$product=(string)file_get_contents($root.'/templates/product.php');$js=(string)file_get_contents($root.'/public/assets/js/app.js');
foreach(['Plant Discovery','Use Plant Finder','Live availability','Only verified live-variant prices participate','No verified match'] as $needle)if(!str_contains($shop,$needle))$errors[]='Discovery storefront marker missing: '.$needle;
foreach(['Where will it live?','What light does the space receive?','live-SKU budget','unknown facts are never guessed'] as $needle)if(!str_contains($finderTpl,$needle))$errors[]='Plant Finder marker missing: '.$needle;
foreach(['data-variant-select','Check delivery pincode','Add selected live plant to bag'] as $needle)if(!str_contains($product,$needle))$errors[]='Variant-aware PDP marker missing: '.$needle;
foreach(['/api/discovery/search-suggestions','variant_guid','filter_used'] as $needle)if(!str_contains($js,$needle))$errors[]='Discovery JavaScript marker missing: '.$needle;

if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);} 
echo "PASS: v1.6 discovery search, synonyms/typos, truthful facets, Plant Finder, media approval gates and variant-aware buying UX verified.\n";
