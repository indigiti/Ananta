<?php
declare(strict_types=1);

/** Persistent image storage for DigiOps public/private split deployments. */
function media_persistent_dir(): string { return data_root().'/private-domain/media/products'; }
function media_persistent_path(string $name): ?string {
    if(!preg_match('/^[a-z0-9][a-z0-9-]{0,100}\.webp$/D',$name))return null;
    return media_persistent_dir().'/'.$name;
}
function media_existing_path(string $name): ?string {
    if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,120}\.(?:webp|png|jpe?g)$/D',$name))return null;
    $persist=media_persistent_path($name);
    if($persist&&is_file($persist)&&!is_link($persist))return $persist;
    $static=dirname(__DIR__).'/public/assets/images/products/'.$name;
    return is_file($static)&&!is_link($static)?$static:null;
}
function media_approved_for_public(string $name): bool {
    // Only previously approved, rights-cleared MediaOS assets are public.
    if(media_persistent_path($name)===null)return false;
    $records=(array)(mediaos_store()['products']??[]);
    foreach($records as $guid=>$p){
        $product=product_by_guid((string)$guid);
        if(!$product||($product['status']??'')!=='active')continue;
        foreach((array)($p['roles']??[]) as $role){
            if(($role['file']??'')===$name&&mediaos_role_approved($role))return true;
        }
    }
    return false;
}
function media_serve_persistent(string $name): never {
    $path=media_persistent_path($name);
    if(!$path||!is_file($path)||is_link($path)){
        http_response_code(404);exit;
    }
    $public=media_approved_for_public($name);
    if(!$public&&!admin_logged_in()){
        http_response_code(404);exit;
    }
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'&&($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD'){
        http_response_code(405);header('Allow: GET, HEAD');exit;
    }
    header('Content-Type: image/webp');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-site');
    header('Cache-Control: '.($public?'public, max-age=3600':'private, no-store'));
    header('Content-Length: '.filesize($path));
    if(($_SERVER['REQUEST_METHOD']??'GET')==='GET')readfile($path);
    exit;
}
