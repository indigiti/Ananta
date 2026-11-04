<?php
declare(strict_types=1);
$root=dirname(__DIR__);putenv('APP_ENV=staging');if(!getenv('SANJAY_DATA_KEY'))putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('U',32)));if(!getenv('SANJAY_SANDBOX_WEBHOOK_SECRET'))putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=commerce-ux-secret');if(!getenv('SANJAY_AGENT_SIGNING_KEY'))putenv('SANJAY_AGENT_SIGNING_KEY=commerce-ux-agent-key-0123456789');$_SERVER['HTTP_HOST']='staging.example.test';$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REMOTE_ADDR']='127.0.0.1';require $root.'/src/bootstrap.php';
$errors=[];$all=products_public();if(count($all)!==376)$errors[]='Expected 376 Sanjay Nursery varieties.';
$opts=commerceux_filter_options($all);if(!is_array($opts)||!isset($opts['materials'],$opts['finishes'],$opts['rooms']))$errors[]='Filter option registry shape is invalid.';
$agl=commerceux_filter_catalog($all,['collection'=>'Aglaonema']);if(count($agl)!==19)$errors[]='Aglaonema collection filter should return 19 varieties.';
$fit=commerceux_filter_catalog($all,['q'=>'Fittonia']);if(count($fit)!==14)$errors[]='Fittonia search should return 14 varieties.';
$succ=commerceux_filter_catalog($all,['category'=>'succulents-cacti']);if(count($succ)!==43)$errors[]='Succulent/cactus category count mismatch.';
$tax=array_column(taxonomies()['categories']??[],'slug');foreach(['indoor-plants','succulents-cacti','outdoor-plants','pots-planters','gardening-essentials','corporate-gifting'] as $slug)if(!in_array($slug,$tax,true))$errors[]='Missing nursery taxonomy: '.$slug;
if(count(taxonomies()['collections']??[])!==40)$errors[]='Expected 40 collection taxonomy values.';
$css=(string)file_get_contents($root.'/public/assets/css/app.css');$layout=(string)file_get_contents($root.'/templates/layout.php');$shop=(string)file_get_contents($root.'/templates/shop.php');$home=(string)file_get_contents($root.'/templates/home.php');
foreach(['sn-family-filter','sn-plant-placeholder','sn-pdp-care','sn-pagination'] as $needle)if(!str_contains($css,$needle))$errors[]='Plant catalogue CSS marker missing: '.$needle;
foreach(['Sanjay Nursery','indoor-plants','succulents-cacti','data-menu-toggle'] as $needle)if(!str_contains($layout,$needle))$errors[]='Nursery header marker missing: '.$needle;
foreach(['Plant Discovery','sn-family-scroll','Live availability','marketplace-product-grid'] as $needle)if(!str_contains(strtolower($shop),strtolower($needle)))$errors[]='Catalogue UX marker missing: '.$needle;
foreach(['The plant edit','curated plant varieties','Corporate Gifting','sn-home-hero'] as $needle)if(!str_contains($home,$needle))$errors[]='Nursery home marker missing: '.$needle;
if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);}echo "PASS: 376-variety catalogue, family navigation, v1.6 discovery controls, pagination markers and responsive plant UX verified.\n";
