<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$catalog = json_decode((string)file_get_contents($root . '/storage/public-domain/catalog/products.json'), true);
$tax = json_decode((string)file_get_contents($root . '/storage/public-domain/catalog/taxonomies.json'), true);
$errors = [];
if (!is_array($catalog)) $errors[] = 'Catalog must be a JSON array.';
if (is_array($catalog) && count($catalog) !== 376) $errors[] = 'Expected 376 requested Sanjay Nursery variety records.';
if (!is_array($tax) || empty($tax['categories']) || count($tax['collections']??[])!==40) $errors[] = 'Taxonomy file is missing the required categories/40 plant collections.';
$slugs=[];$skus=[];$guids=[];
foreach ($catalog as $p) {
    foreach (['guid','slug','name','sku','category','status','inventory','customisation','seo','sales_mode','botanical_name','care_profile'] as $k) if (!array_key_exists($k,$p) || $p[$k]==='') $errors[] = "Missing {$k} in product ".($p['name']??'?');
    if (($p['sales_mode']??'')==='commerce' && (int)($p['price']??0)<=0) $errors[]='Purchasable product has no price: '.$p['name'];
    if (isset($slugs[$p['slug']])) $errors[] = 'Duplicate slug: '.$p['slug'];
    if (isset($skus[$p['sku']])) $errors[] = 'Duplicate SKU: '.$p['sku'];
    if (isset($guids[$p['guid']])) $errors[] = 'Duplicate GUID: '.$p['guid'];
    $slugs[$p['slug']] = true; $skus[$p['sku']] = true; $guids[$p['guid']] = true;
    if (!empty($p['image'])) { $img=$root.'/public/assets/images/products/'.$p['image']; if(!is_file($img))$errors[]='Missing image: '.$p['image']; }
}
$rulesPath=$root . '/storage/public-domain/catalog/configurator-rules.json';
$rules=is_file($rulesPath)?json_decode((string)file_get_contents($rulesPath),true):null;
if(!is_array($rules))$errors[]='Configurator rules store must be a JSON object.';
foreach($catalog as $p){if(empty($p['customisation']['enabled'])||($p['sales_mode']??'')!=='commerce')continue;$r=$rules[$p['slug']]??null;if(!$r||empty($r['enabled'])||($r['product_guid']??'')!==$p['guid'])$errors[]='Configurator rule mismatch for '.$p['slug'];}
$public = realpath($root.'/public'); $private = realpath($root.'/storage/private-domain');
if (!$private || ($public && str_starts_with($private,$public))) $errors[]='Private storage is not isolated from public document root.';
foreach ([$root.'/storage/revisions/catalog',$root.'/storage/journals',$root.'/storage/indexes'] as $d) if(!is_dir($d)||!is_writable($d)) $errors[]='Storage path unavailable: '.$d;
if ($errors) { fwrite(STDERR, implode("\n", array_unique($errors))."\n"); exit(1); }
echo "PASS: 376 Sanjay Nursery varieties, 40 collections, unique identity, enquiry-safe pricing, optional media and storage integrity verified.\n";
