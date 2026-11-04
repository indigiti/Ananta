<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('P',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=media-persistence-test');
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['HTTP_HOST']='staging.example.test';
$_SERVER['REMOTE_ADDR']='127.0.0.55';
require $root.'/src/bootstrap.php';
$errors=[];
$assert=static function(bool $condition,string $message)use(&$errors){if(!$condition)$errors[]=$message;};
$assert(media_persistent_path('../secret.webp')===null,'Path traversal accepted.');
$assert(media_persistent_path('invalid.php')===null,'Unexpected PHP file accepted.');
$assert(media_existing_path('../../etc/passwd')===null,'Existing media traversal accepted.');
$p=products_public()[0]??null;
if(!$p)throw new RuntimeException('Missing catalogue fixture.');
$dir=media_persistent_dir();
if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Cannot create media fixture directory.');
$file='test-persistence-'.bin2hex(random_bytes(5)).'.webp';
$path=media_persistent_path($file);
$im=imagecreatetruecolor(24,24);$color=imagecolorallocate($im,90,145,88);imagefilledrectangle($im,0,0,23,23,$color);
if(!imagewebp($im,$path,75))throw new RuntimeException('Cannot write test WebP.');
imagedestroy($im);
try{
    $assert(media_existing_path($file)===$path,'Persistent file cannot be resolved.');
    $assert(!media_approved_for_public($file),'Unapproved uploaded media was public.');
    $store=mediaos_store();
    $store['products'][$p['guid']]['roles']['hero']=['file'=>$file,'alt'=>'Sample plant','status'=>'approved','rights'=>'owned','sort'=>1];
    $GLOBALS['_mediaos_store_cache']=$store;
    $assert(media_approved_for_public($file),'Approved rights-cleared media not public.');
    $assert(mediaos_primary_approved($p),'Approved persistent hero not detected.');
    $store['products'][$p['guid']]['roles']['hero']['status']='retired';
    $GLOBALS['_mediaos_store_cache']=$store;
    $assert(!media_approved_for_public($file),'Retired media remained publicly accessible.');
    $assert(!mediaos_primary_approved($p),'Retired image remained product eligible.');
    $entry=(string)file_get_contents($root.'/public/index.php');
    $assert(str_contains($entry,'media_serve_persistent($m[1])'),'Persistent media route missing.');
    $packer=(string)file_get_contents($root.'/tools/package-digiops-release.php');
    $assert(!str_contains($packer,"ananta_release_copy($root.'/storage'"),'Runtime storage is being copied to release.');
}finally{@unlink($path);unset($GLOBALS['_mediaos_store_cache']);}
if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: persisted WebP path, traversal rejection, rights-controlled public access and private DigiOps runtime boundary.\n";
