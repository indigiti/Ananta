<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('R',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=sourcing-ui-test');
$_SERVER['SCRIPT_NAME']='/ananta/index.php';
$_SERVER['REQUEST_URI']='/ananta/sourcing';
$_SERVER['HTTP_HOST']='stage.example.invalid';
$_SERVER['REMOTE_ADDR']='127.0.0.68';
require $root.'/src/bootstrap.php';
$errors=[];$check=static function(bool $ok,string $msg)use(&$errors){if(!$ok)$errors[]=$msg;};
ob_start();
render('sourcing/request',['title'=>'Plant sourcing','robots'=>'noindex,nofollow',
    'products'=>products_public(),'suppliers'=>[['id'=>uuidv7(),'name'=>'Test Nursery & Co','city'=>'Pune']],
    'selectedSlug'=>products_public()[0]['slug']??'','formToken'=>str_repeat('a',48)]);
$html=ob_get_clean();
foreach(['<main id="main">','ananta-source-form','name="supplier_ids[]"','name="consent"','name="share_contact"',
         'name="quantity"','name="specification"','name="destination"','Sourcing requests'] as $token) {
    if($token==='Sourcing requests')continue;
    $check(str_contains($html,$token),'Missing sourcing UI element: '.$token);
}
$check(str_contains($html,'Test Nursery &amp; Co'),'Nursery name not HTML-escaped.');
$check(str_contains($html,asset('css/sourcing.css')),'Sourcing CSS not linked or fingerprinted.');
$check((bool)preg_match('/sourcing[.]css[?]v=1[.]7[.]0-[a-f0-9]{12}/',$html),'Sourcing asset fingerprint missing.');
$check(str_contains($html,'name="_csrf"'),'Sourcing form lacks CSRF protection.');
$check(str_contains($html,'name="_rfq_form_token"'),'One-time request token missing from form.');
$check(str_contains($html,'value="'.str_repeat('a',48).'"'),'One-time token not preserved in the form.');
$check(!str_contains($html,'name="unit_price_inr"'),'Buyer input can forge a supplier quotation.');
$_SERVER['REQUEST_URI']='/ananta/shop';
ob_start();
render('sourcing/request',['title'=>'Plant sourcing','robots'=>'noindex,nofollow',
    'products'=>products_public(),'suppliers'=>[],'selectedSlug'=>'']);
$shop=ob_get_clean();
$check(!str_contains($shop,'/assets/css/sourcing.css'),'Sourcing CSS loaded on normal storefront.');
foreach(['templates/account/sourcing.php','templates/account/sourcing-detail.php','templates/supplier/enquiries.php','templates/supplier/enquiry-detail.php'] as $path){
    $check(is_file($root.'/'.$path),'Missing RFQ template '.$path);
}
$router=(string)file_get_contents($root.'/public/index.php');
foreach(['/sourcing','/account/sourcing','/supplier/enquiries'] as $path)$check(str_contains($router,$path),'Missing web route '.$path);
if($errors){fwrite(STDERR,"FAIL RFQ UI:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: accessible RFQ request form, CSRF, escaped supplier names, fingerprinted /ananta/ CSS and account/supplier response templates.\n";
