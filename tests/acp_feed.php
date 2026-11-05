<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('A',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=acp-one-test-secret');
putenv('SANJAY_AGENT_SIGNING_KEY=acp-one-test-key-123456789012345');
$_SERVER['SCRIPT_NAME']='/ananta/index.php';$_SERVER['HTTP_HOST']='qa.example.invalid';
$_SERVER['HTTPS']='on';$_SERVER['REMOTE_ADDR']='127.0.0.41';
require $root.'/src/bootstrap.php';
$errors=[];
$assert=static function(bool $condition,string $message)use(&$errors){if(!$condition)$errors[]=$message;};
$deny=static function(callable $fn,string $message)use(&$errors){
    try{$fn();$errors[]=$message;}catch(RuntimeException|InvalidArgumentException){}
};
try {
    $report=acp_feed_report();
    $assert($report['protocol_version']==='2026-04-17','Wrong ACP stable version.');
    $assert($report['approved_by_openai']===false&&$report['checkout_enabled']===false,
        'Unverified partner checkout was advertised as active.');
    $assert(count(acp_feed_native_rows())===0,'Enquiry catalogue leaked into purchasable ACP feed.');
    $deny(static fn()=>acp_feed_csv(),'ACP product export available without admin.');
    $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'acp@example.invalid','name'=>'ACP QA','role'=>'admin'];
    $deny(static fn()=>acp_feed_csv(),'Empty ACP partner file was exported.');
    $row=[
        'item_id'=>'SKU001','title'=>'Neem tree plant','description'=>'Live neem sapling in a pot',
        'url'=>'https://nursery.example.in/product/neem','brand'=>'Sanjay Nursery',
        'seller_name'=>'Sanjay Nursery','seller_url'=>'https://nursery.example.in/',
        'image_url'=>'https://nursery.example.in/assets/neem.jpg',
        'price'=>'299 INR','availability'=>'in_stock','target_countries'=>'IN','store_country'=>'IN',
        'is_eligible_search'=>'true','is_eligible_checkout'=>'false','gtin'=>'','mpn'=>'',
        'group_id'=>'','product_category'=>'Garden Plants','condition'=>'new'
    ];
    $assert(acp_feed_validate_row($row)===[],'Valid native ACP candidate rejected.');
    $assert(in_array('unsupported_eligibility_flags',acp_feed_validate_row(array_replace($row,
        ['is_eligible_checkout'=>'true'])),true),'Premature checkout eligibility accepted.');
    $assert(in_array('foreign_landing_domain',acp_feed_validate_row(array_replace($row,
        ['url'=>'https://other.example.in/buy'])),true),'Foreign seller landing accepted.');
    $assert(in_array('invalid_price',acp_feed_validate_row(array_replace($row,
        ['price'=>'0 INR'])),true),'Zero-priced ACP plant accepted.');
    $assert(in_array('invalid_image_url',acp_feed_validate_row(array_replace($row,
        ['image_url'=>'http://nursery.example.in/n.jpg'])),true),'Insecure product photo accepted.');
    $assert(in_array('invalid_gtin',acp_feed_validate_row(array_replace($row,['gtin'=>'fake'])),true),
        'Invented GTIN accepted.');
    $assert(in_array('target_countries',acp_feed_columns(),true),'Country column absent.');
    $assert(in_array('seller_name',acp_feed_columns(),true),'Merchant-of-record absent.');
    $assert(in_array('is_eligible_checkout',acp_feed_columns(),true),'ACP checkout flag absent.');
}catch(Throwable $e){$errors[]=$e->getMessage();}
if($errors){fwrite(STDERR,"FAIL ACP.1:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS ACP.1: native feed schema, staged consent/partner flags, merchant origin, image and INR constraints, no fictional sellable SKU.\n";
