<?php
declare(strict_types=1);

/**
 * First-install seed for a single-writer DigiOps staging deployment.
 *
 * Immutable seed data is shipped under private/seed; live JSON always lives
 * under private/storage and is never included in future release overlays.
 * Existing files (even empty or older ones) are never overwritten.
 */
function ananta_seed_missing_public_data(string $root): void
{
    $seedRoot=$root.'/seed/storage/public-domain';
    if(!is_dir($seedRoot)) return; // Ordinary developer checkout uses existing storage.

    $names=[
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
    $locks=$root.'/storage/locks';
    if(!is_dir($locks)&&!@mkdir($locks,0750,true)&&!is_dir($locks)){
        throw new RuntimeException('Cannot create catalogue initialization lock directory.');
    }
    $lock=fopen($locks.'/ananta-seed-public.lock','c');
    if($lock===false) throw new RuntimeException('Cannot open catalogue initialization lock.');
    try{
        if(!flock($lock,LOCK_EX)) throw new RuntimeException('Cannot lock catalogue initialization.');
        foreach($names as $relative){
            $source=$seedRoot.'/'.$relative;
            $target=$root.'/storage/public-domain/'.$relative;
            if(!is_file($source)) throw new RuntimeException('Missing packaged seed: '.$relative);
            if(file_exists($target)||is_link($target))continue;
            $dir=dirname($target);
            if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir)){
                throw new RuntimeException('Cannot create seed destination: '.$relative);
            }
            $temporary=tempnam($dir,'.ananta-first-install-');
            if($temporary===false) throw new RuntimeException('Cannot stage catalogue seed.');
            try{
                if(!copy($source,$temporary))throw new RuntimeException('Cannot copy catalogue seed: '.$relative);
                @chmod($temporary,0640);
                if(file_exists($target)||is_link($target))continue;
                if(!rename($temporary,$target))throw new RuntimeException('Cannot publish catalogue seed: '.$relative);
            }finally{
                if(is_file($temporary))@unlink($temporary);
            }
        }
    }finally{
        @flock($lock,LOCK_UN);
        fclose($lock);
    }
}
