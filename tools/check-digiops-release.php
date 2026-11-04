<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$r=$root.'/release';
$required=[
    'public/index.php',
    'public/.htaccess',
    'public/assets/css/app.css',
    'public/assets/css/ananta-premium.css',
    'public/assets/js/app.js',
    'private/public/index.php',
    'private/src/bootstrap.php',
    'private/src/digiops_seed.php',
    'private/src/marketplace_offers.php',
    'private/config/app.php',
    'private/templates/home.php',
    'private/templates/product.php',
    'private/resources/LIVE_VARIANT_IMPORT_TEMPLATE.csv',
    'private/seed/storage/public-domain/catalog/products.json',
    'private/seed/storage/public-domain/catalog/taxonomies.json',
    'private/build/release.json',
    'RELEASE.json',
];
foreach($required as $path)if(!is_file($r.'/'.$path))throw new RuntimeException('Release missing: '.$path);
if(is_dir($r.'/private/storage'))throw new RuntimeException('Live private/storage MUST NOT be in an artifact.');
if(is_dir($r.'/public/src')||is_dir($r.'/public/config')||is_dir($r.'/public/storage')){
    throw new RuntimeException('Public payload exposed application internals.');
}
$manifest=json_decode((string)file_get_contents($r.'/RELEASE.json'),true,512,JSON_THROW_ON_ERROR);
if(($manifest['schema']??'')!=='DIGIOPS-BUILD/1'||($manifest['name']??'')!=='Ananta'
    ||($manifest['publicTarget']??'')!=='public_html/ananta/'
    ||($manifest['privateTarget']??'')!=='private_html/ananta/'
    ||($manifest['publicUrlPath']??'')!=='/ananta/'){
    throw new RuntimeException('Release manifest does not match DigiOps target configuration.');
}
if($manifest!==json_decode((string)file_get_contents($r.'/private/build/release.json'),true,512,JSON_THROW_ON_ERROR)){
    throw new RuntimeException('Public/private release identities differ.');
}
if(!str_contains((string)file_get_contents($r.'/public/.htaccess'),'RewriteBase /ananta/')){
    throw new RuntimeException('Cloudways subpath rewrite configuration missing.');
}
$seed=json_decode((string)file_get_contents($r.'/private/seed/storage/public-domain/catalog/products.json'),true,512,JSON_THROW_ON_ERROR);
if(!is_array($seed)||count($seed)<1)throw new RuntimeException('Initial catalogue seed empty.');
$iter=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($r,FilesystemIterator::SKIP_DOTS));
$forbidden=['setup.key','users.json','customers.json','addresses.json','admin-audit.ndjson','commerce-audit.ndjson'];
foreach($iter as $file){
    if($file->isLink())throw new RuntimeException('Symlink in release: '.$file->getPathname());
    if(in_array($file->getFilename(),$forbidden,true))throw new RuntimeException('Persistent or sensitive runtime record included: '.$file->getFilename());
    if(str_ends_with($file->getFilename(),'.lock'))throw new RuntimeException('Runtime lock included.');
}
echo 'PASS: DigiOps split payload, /ananta/ rewrite, complete curated catalog, private source boundary and absent live secrets/state ('.count($seed).' plant records).'.PHP_EOL;
