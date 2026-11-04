<?php
declare(strict_types=1);

/**
 * Build Ananta's file-first DigiOps artifact.
 *
 * release/public/*  -> public_html/ananta/
 * release/private/* -> private_html/ananta/
 *
 * Never include tracked storage/private-domain, logs, locks, journal history,
 * credentials, existing user records or other runtime files in a release.
 */
$root=dirname(__DIR__);
$release=$root.'/release';
function ananta_release_remove(string $path): void {
    if(!file_exists($path)&&!is_link($path))return;
    if(is_link($path)||is_file($path)){if(!@unlink($path))throw new RuntimeException('Cannot remove: '.$path);return;}
    foreach(array_diff(scandir($path)?:[],['.','..']) as $child)ananta_release_remove($path.'/'.$child);
    if(!@rmdir($path))throw new RuntimeException('Cannot remove: '.$path);
}
function ananta_release_copy(string $src,string $dest): void {
    if(is_link($src))throw new RuntimeException('Symlinks forbidden in release: '.$src);
    if(is_dir($src)){
        if(!is_dir($dest)&&!mkdir($dest,0755,true)&&!is_dir($dest))throw new RuntimeException('Cannot create: '.$dest);
        foreach(array_diff(scandir($src)?:[],['.','..']) as $name)ananta_release_copy($src.'/'.$name,$dest.'/'.$name);
        return;
    }
    if(!is_file($src))throw new RuntimeException('Missing source: '.$src);
    $dir=dirname($dest);
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Cannot create: '.$dir);
    if(!copy($src,$dest))throw new RuntimeException('Cannot copy: '.$src);
}
if(!is_file($root.'/public/index.php')||!is_file($root.'/src/bootstrap.php')){
    throw new RuntimeException('Ananta application root is incomplete.');
}
ananta_release_remove($release);
mkdir($release.'/public',0755,true);
mkdir($release.'/private',0755,true);

// Preserve public assets, including only approved source-controlled media.
// The application itself remains entirely outside web root.
ananta_release_copy($root.'/public/assets',$release.'/public/assets');
ananta_release_copy($root.'/tools/digiops-public-index.php',$release.'/public/index.php');
ananta_release_copy($root.'/tools/digiops-public-htaccess',$release.'/public/.htaccess');
foreach(['src','config','templates','resources','public','python'] as $name){
    ananta_release_copy($root.'/'.$name,$release.'/private/'.$name);
}
ananta_release_copy($root.'/VERSION',$release.'/private/VERSION');

// Public-domain seed records are copied only on first install.
// Subsequent DigiOps deploys overwrite private/seed but NEVER private/storage.
$seeds=[
    'catalog/products.json',
    'catalog/taxonomies.json',
    'catalog/variants.json',
    'catalog/configurator-rules.json',
    'commerce/shipping-zones.json',
    'content/cms.json',
    'discovery/settings.json',
    'growth/settings.json',
    'media/mediaos.json',
    'themes/theme-settings.json',
];
foreach($seeds as $file){
    ananta_release_copy(
        $root.'/storage/public-domain/'.$file,
        $release.'/private/seed/storage/public-domain/'.$file
    );
}
$manifest=[
    'schema'=>'DIGIOPS-BUILD/1',
    'name'=>'Ananta',
    'version'=>trim((string)file_get_contents($root.'/VERSION')),
    'sourceSha'=>(string)(getenv('GITHUB_SHA')?:'local'),
    'branch'=>(string)(getenv('GITHUB_REF_NAME')?:'local'),
    'ciRunId'=>(string)(getenv('GITHUB_RUN_ID')?:''),
    'ciRunAttempt'=>(string)(getenv('GITHUB_RUN_ATTEMPT')?:''),
    'builtAt'=>gmdate('c'),
    'public'=>'public',
    'private'=>'private',
    'publicUrlPath'=>'/ananta/',
    'publicTarget'=>'public_html/ananta/',
    'privateTarget'=>'private_html/ananta/',
    'runtime'=>'php-file-first-staging',
];
$encoded=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
file_put_contents($release.'/RELEASE.json',$encoded);
if(!is_dir($release.'/private/build'))mkdir($release.'/private/build',0755,true);
file_put_contents($release.'/private/build/release.json',$encoded);
echo "Ananta DigiOps release built for /ananta/ (no database, no runtime storage bundled).\n";
