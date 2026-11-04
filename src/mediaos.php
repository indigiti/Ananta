<?php
declare(strict_types=1);

/** Plant-native MediaOS for v1.6.0. */
function mediaos_file(): string { return data_root().'/public-domain/media/mediaos.json'; }
function mediaos_roles(): array {
    return [
        'hero'=>'Catalogue hero',
        'full_plant'=>'Full plant',
        'foliage'=>'Foliage close-up',
        'pot_scale'=>'Pot / scale reference',
        'side'=>'Side / form',
        'nursery_batch'=>'Nursery batch proof',
        'lifestyle'=>'Lifestyle / placement',
        'care_context'=>'Care / growing context',
    ];
}
function mediaos_required_roles(): array { return ['hero','full_plant','foliage','pot_scale']; }
function mediaos_default_store(): array { return ['version'=>2,'products'=>[],'updated_at'=>null]; }
function mediaos_store(): array {
    if(isset($GLOBALS['_mediaos_store_cache']) && is_array($GLOBALS['_mediaos_store_cache'])) return $GLOBALS['_mediaos_store_cache'];
    $d=foundation_json_read(mediaos_file(),mediaos_default_store());
    return $GLOBALS['_mediaos_store_cache']=(isset($d['products'])&&is_array($d['products']))?array_replace(mediaos_default_store(),$d):mediaos_default_store();
}
function mediaos_safe_alt(array $product,string $role): string {
    $roleLabel=mediaos_roles()[$role]??ucwords(str_replace('_',' ',$role));
    return trim((string)($product['name']??'Sanjay Nursery plant').' — '.$roleLabel);
}
function mediaos_seed_product(array $product): array {
    $files=array_values(array_unique(array_filter(array_merge([(string)($product['image']??'')],(array)($product['gallery']??[])))));
    $roles=[];$roleOrder=['hero','full_plant','foliage','pot_scale','side','lifestyle'];
    foreach($files as $i=>$file){
        $role=$roleOrder[min($i,count($roleOrder)-1)];if(isset($roles[$role]))$role='care_context';
        $roles[$role]=['file'=>$file,'alt'=>mediaos_safe_alt($product,$role),'status'=>'staging','rights'=>'unverified','focal_x'=>50,'focal_y'=>50,'sort'=>$i+1,'batch_code'=>'','shot_date'=>'','caption'=>''];
    }
    if(!isset($roles['hero'])&&!empty($product['image']))$roles['hero']=['file'=>$product['image'],'alt'=>mediaos_safe_alt($product,'hero'),'status'=>'staging','rights'=>'unverified','focal_x'=>50,'focal_y'=>50,'sort'=>1,'batch_code'=>'','shot_date'=>'','caption'=>''];
    return ['product_guid'=>$product['guid'],'slug'=>$product['slug'],'roles'=>$roles,'updated_at'=>gmdate('c')];
}
function mediaos_ensure_catalog(): void {
    $store=mediaos_store();$changed=false;
    foreach(products_all() as $p){$g=(string)($p['guid']??'');if($g===''||isset($store['products'][$g]))continue;$store['products'][$g]=mediaos_seed_product($p);$changed=true;}
    if($changed){$store['updated_at']=gmdate('c');atomic_json_write(mediaos_file(),$store,foundation_checksum(mediaos_file()),'mediaos.seed');$GLOBALS['_mediaos_store_cache']=$store;$GLOBALS['_discovery_facet_cache']=[];}
}
function mediaos_product(string $productGuid): array {
    $store=mediaos_store();if(isset($store['products'][$productGuid]))return$store['products'][$productGuid];$p=product_by_guid($productGuid);return$p?mediaos_seed_product($p):['product_guid'=>$productGuid,'roles'=>[]];
}
function mediaos_role(array $product,string $role='hero'): ?array {
    $entry=mediaos_product((string)($product['guid']??''));$row=$entry['roles'][$role]??null;
    if(!$row&&$role==='hero'&&!empty($product['image']))$row=['file'=>$product['image'],'alt'=>$product['name']??'Plant image','focal_x'=>50,'focal_y'=>50,'status'=>'staging','rights'=>'unverified','batch_code'=>'','shot_date'=>'','caption'=>''];
    return is_array($row)?$row:null;
}
function mediaos_primary_file(array $product): string { return (string)(mediaos_role($product,'hero')['file']??($product['image']??'')); }
function mediaos_primary_alt(array $product): string { return (string)(mediaos_role($product,'hero')['alt']??($product['name']??'Plant image')); }
function mediaos_role_approved(array $role): bool { return ($role['status']??'')==='approved'&&in_array(($role['rights']??''),['owned','licensed','generated'],true)&&trim((string)($role['file']??''))!==''; }
function mediaos_primary_approved(array $product): bool { $r=mediaos_role($product,'hero');return is_array($r)&&mediaos_role_approved($r)&&is_file(dirname(__DIR__).'/public/assets/images/products/'.basename((string)$r['file'])); }
function mediaos_gallery(array $product,bool $approvedOnly=false): array {
    $entry=mediaos_product((string)($product['guid']??''));$out=[];
    foreach(mediaos_roles() as $role=>$label){$r=$entry['roles'][$role]??null;if(!is_array($r)||empty($r['file']))continue;if($approvedOnly&&!mediaos_role_approved($r))continue;$out[]=['role'=>$role,'label'=>$label]+$r;}
    usort($out,fn($a,$b)=>(int)($a['sort']??99)<=>(int)($b['sort']??99));return$out;
}
function mediaos_save_product(string $productGuid,array $src): void {
    $p=product_by_guid($productGuid);if(!$p)throw new RuntimeException('Product not found.');$available=array_column(media_files(),'name');$entry=mediaos_product($productGuid);$entry['slug']=$p['slug'];$roles=[];
    foreach(mediaos_roles() as $role=>$label){
        $file=trim((string)($src['file'][$role]??''));if($file==='')continue;if(!in_array($file,$available,true))throw new RuntimeException('Unknown media file for '.$label.'.');
        $alt=trim((string)($src['alt'][$role]??''))?:mediaos_safe_alt($p,$role);
        $status=in_array(($src['status'][$role]??'staging'),['staging','approved','retired'],true)?$src['status'][$role]:'staging';
        $rights=in_array(($src['rights'][$role]??'unverified'),['unverified','owned','licensed','generated'],true)?$src['rights'][$role]:'unverified';
        if($status==='approved'&&$rights==='unverified')throw new RuntimeException($label.' cannot be approved while rights remain unverified.');
        $shot=trim((string)($src['shot_date'][$role]??''));if($shot!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$shot))throw new RuntimeException($label.' shot date must be YYYY-MM-DD.');
        $roles[$role]=['file'=>$file,'alt'=>$alt,'status'=>$status,'rights'=>$rights,'focal_x'=>max(0,min(100,(int)($src['focal_x'][$role]??50))),'focal_y'=>max(0,min(100,(int)($src['focal_y'][$role]??50))),'sort'=>(int)($src['sort'][$role]??50),'batch_code'=>strtoupper(trim((string)($src['batch_code'][$role]??''))),'shot_date'=>$shot,'caption'=>trim((string)($src['caption'][$role]??''))];
    }
    $entry['roles']=$roles;$entry['updated_at']=gmdate('c');$store=mediaos_store();$store['version']=2;$store['products'][$productGuid]=$entry;$store['updated_at']=gmdate('c');atomic_json_write(mediaos_file(),$store,foundation_checksum(mediaos_file()),'mediaos.update');$GLOBALS['_mediaos_store_cache']=$store;$GLOBALS['_discovery_facet_cache']=[];audit_event('mediaos.update',['product_guid'=>$productGuid]);
}
function mediaos_completeness(array $product): array {
    $gallery=mediaos_gallery($product);$approvedRows=array_values(array_filter($gallery,'mediaos_role_approved'));$approvedRoles=array_column($approvedRows,'role');$required=mediaos_required_roles();$missing=array_values(array_diff($required,$approvedRoles));
    return ['count'=>count($gallery),'approved'=>count($approvedRows),'missing'=>$missing,'production_ready'=>empty($missing),'hero_approved'=>in_array('hero',$approvedRoles,true),'required_count'=>count($required)];
}
function mediaos_release_checks(): array {
    mediaos_ensure_catalog();$store=mediaos_store();$refs=true;$alts=true;$coverage=true;$prodReady=0;$rights=true;$available=array_column(media_files(),'name');
    foreach(products_all() as $p){$e=$store['products'][$p['guid']]??null;if(!$e){$coverage=false;continue;}foreach((array)($e['roles']??[]) as $r){if(!in_array((string)($r['file']??''),$available,true))$refs=false;if(trim((string)($r['alt']??''))==='')$alts=false;if(($r['status']??'')==='approved'&&($r['rights']??'unverified')==='unverified')$rights=false;}if(mediaos_completeness($p)['production_ready'])$prodReady++;}
    return [
        ['name'=>'MediaOS catalog coverage','ok'=>$coverage,'detail'=>$coverage?'Every catalogue product has a MediaOS record.':'One or more products are missing MediaOS metadata.'],
        ['name'=>'MediaOS file integrity','ok'=>$refs,'detail'=>$refs?'All role assignments resolve to published media files.':'A MediaOS role references a missing file.'],
        ['name'=>'MediaOS accessibility metadata','ok'=>$alts,'detail'=>$alts?'All assigned media has alt text.':'One or more assigned images is missing alt text.'],
        ['name'=>'MediaOS approval rights gate','ok'=>$rights,'detail'=>$rights?'Approved media is rights-cleared.':'Approved media with unverified rights detected.'],
        ['name'=>'Production media approval','ok'=>true,'detail'=>$prodReady.' / '.count(products_all()).' products have the four required approved plant-photo roles (hero, full plant, foliage, pot/scale).'],
    ];
}
