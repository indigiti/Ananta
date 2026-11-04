<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('V',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=boq-ui-smoke');
$_SERVER['REQUEST_URI']='/ananta/sourcing/boq';$_SERVER['SCRIPT_NAME']='/ananta/index.php';
$_SERVER['REMOTE_ADDR']='127.0.0.45';$_SERVER['HTTP_HOST']='qa.invalid';
require $root.'/src/bootstrap.php';
$errors=[];$assert=static function(bool $ok,string $msg)use(&$errors){if(!$ok)$errors[]=$msg;};
$line=[
'id'=>uuidv7(),'product_guid'=>uuidv7(),'product_slug'=>'test-plant','plant_name'=>'Plant & Test',
'quantity'=>12,'specification'=>'12 inch pot, Grade A',
];
$preview=['id'=>uuidv7(),'token'=>str_repeat('a',48),'expires_at'=>time()+600,'lines'=>[$line]];
ob_start();
render('sourcing/boq',['title'=>'BOQ sourcing','robots'=>'noindex,nofollow',
'preview'=>$preview,'suppliers'=>[['id'=>uuidv7(),'name'=>'Test & Nursery','city'=>'Pune']]]);
$html=ob_get_clean();
foreach(['name="boq_file"','name="preview_token"','name="consent"','name="supplier_ids[]"',
'name="destination"','ananta-boq-table','/ananta/sourcing/boq/preview','/ananta/sourcing/boq/submit'] as $item)
    $assert(str_contains($html,$item),'BOQ page missing '.$item);
$assert(str_contains($html,'Plant &amp; Test'),'Unescaped BOQ plant label');
$assert(str_contains($html,'Test &amp; Nursery'),'Unescaped nursery label');
$assert(str_contains($html,asset('css/sourcing.css')),'BOQ CSS failed to load');
$assert(str_contains($html,'name="_csrf"'),'BOQ form missing CSRF');
$assert(str_contains($html,'non-binding'),'BOQ disclaimer missing');
$detail=(string)file_get_contents($root.'/templates/account/sourcing-detail.php');
$assert(str_contains($detail,'approve-draft'),'Buyer quote draft selection missing');
$assert(str_contains($detail,'quote_version'),'Buyer is not locking the selected revision');
$assert(str_contains($detail,'taxes'),'PO tax disclaimer missing');
$supplier=(string)file_get_contents($root.'/templates/supplier/enquiry-detail.php');
$assert(str_contains($supplier,'boq-quote'),'Supplier BOQ quote form missing');
$assert(str_contains($supplier,'line_prices'),'Line-by-line quotation input missing');
$router=(string)file_get_contents($root.'/public/index.php');
foreach(['/sourcing/boq','/sourcing/boq/preview','/sourcing/boq/submit','approve-draft','boq-quote'] as $route)
    $assert(str_contains($router,$route),'BOQ route missing '.$route);
if($errors){fwrite(STDERR,"FAIL BOQ UI:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: /ananta/ BOQ pages, preview, CSRF, buyer award confirmation, supplier per-line quoting and non-binding disclosures.\n";
