<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/src/bootstrap.php';
if (confidential_key()===null) { fwrite(STDERR,"SANJAY_DATA_KEY is required.\n"); exit(2); }
$stamp=gmdate('Ymd_His');$dir=$root.'/storage/backups/commerce-'.$stamp;
if(!mkdir($dir,0750,true)&&!is_dir($dir)){fwrite(STDERR,"Unable to create backup directory.\n");exit(2);} 
$files=[commerce_customer_file(),commerce_address_file(),commerce_cart_file(),commerce_order_file(),commerce_payment_file(),commerce_request_file(),commerce_reset_file()];
$manifest=[];
foreach($files as $src){if(!is_file($src))continue;$dst=$dir.'/'.basename($src);if(!copy($src,$dst))throw new RuntimeException('Backup copy failed: '.basename($src));@chmod($dst,0640);$manifest[basename($src)]=hash_file('sha256',$dst);} 
file_put_contents($dir.'/manifest.json',json_encode(['created_at'=>gmdate('c'),'files'=>$manifest],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n",LOCK_EX);@chmod($dir.'/manifest.json',0640);
echo "Commerce backup created: $dir\n";
