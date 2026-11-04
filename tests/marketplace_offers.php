<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
if(!getenv('SANJAY_DATA_KEY'))putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('M',32)));
if(!getenv('SANJAY_SANDBOX_WEBHOOK_SECRET'))putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=marketplace-test-secret');
if(!getenv('SANJAY_AGENT_SIGNING_KEY'))putenv('SANJAY_AGENT_SIGNING_KEY=marketplace-test-agent-key-0123456789');
$_SERVER['HTTP_HOST']='staging.example.test';
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['REMOTE_ADDR']='127.0.0.1';
require $root.'/src/bootstrap.php';
$errors=[];
$check=static function(bool $ok,string $message)use(&$errors):void{if(!$ok)$errors[]=$message;};
$check(marketplace_uuid_valid('018d8671-e71a-7ddb-8d4e-67407d863ce3'),'valid UUID rejected');
$check(!marketplace_uuid_valid('../../etc/passwd'),'traversal input passed as UUID');
$check(!marketplace_uuid_valid('a-b-c'),'non-UUID passed validation');
$now=gmdate('c');
$old=gmdate('c',time()-8*86400);
$recent=marketplace_offer_public_status(['availability'=>'confirmed','quantity'=>10,'checked_at'=>$now]);
$stale=marketplace_offer_public_status(['availability'=>'confirmed','quantity'=>10,'checked_at'=>$old]);
$reported=marketplace_offer_public_status(['availability'=>'reported','checked_at'=>$now]);
$unknown=marketplace_offer_public_status(['availability'=>'unknown','checked_at'=>$now]);
$unavailable=marketplace_offer_public_status(['availability'=>'unavailable','checked_at'=>$now]);
$check($recent['key']==='confirmed','fresh confirmed stock label incorrect');
$check($stale['key']==='unknown','expired confirmation must not remain confirmed');
$check($reported['key']==='reported','reported must not be confirmed');
$check($unknown['key']==='unknown','unknown stock must not be sellable');
$check($unavailable['key']==='unavailable','unavailable label missing');
$store=marketplace_offers_store();
$check(isset($store['suppliers'],$store['offers'])&&is_array($store['offers']),'registry shape invalid');
$p=products_public()[0]??null;
$check(is_array($p),'missing baseline plant');
if(is_array($p)){
    $public=marketplace_public_offers_dto($p);
    foreach($public as $o){
        $check(($o['checkout_enabled']??null)===false,'external seller must never use legacy checkout');
        $check(($o['sales_mode']??'')==='enquiry','external seller must be enquiry-only');
        $check(!array_key_exists('evidence_ref',$o),'private evidence leaked to public DTO');
        $check(!array_key_exists('phone',$o),'supplier contact must not be in public agent offer DTO');
    }
}
foreach(['templates/product.php'=>'ananta-buy-from','templates/_product-card.php'=>'ananta-card-offers','templates/layout.php'=>'ananta-premium.css','templates/nurseries.php'=>'ananta-directory-grid','templates/admin/marketplace.php'=>'marketplace/offers/save'] as $path=>$marker){
    $check(str_contains((string)file_get_contents($root.'/'.$path),$marker),'missing UI or admin marker: '.$path);
}
if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: multi-nursery offer visibility, fresh/stale stock semantics, no external checkout, and UI contracts.\n";
