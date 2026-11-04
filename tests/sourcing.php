<?php
declare(strict_types=1);

/** B3 two-supplier and buyer-owned sourcing RFQ acceptance tests. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('Q',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=sourcing-test');
putenv('SANJAY_AGENT_SIGNING_KEY=sourcing-test-agent-012345678901');
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['HTTP_HOST']='stage.example.invalid';$_SERVER['REMOTE_ADDR']='127.0.0.61';
$tag=bin2hex(random_bytes(6));
$files=[
    'tenants'=>$root.'/storage/tmp/b3-tenants-'.$tag.'.json',
    'market'=>$root.'/storage/tmp/b3-marketplace-'.$tag.'.json',
    'sourcing'=>$root.'/storage/tmp/b3-sourcing-'.$tag.'.json',
    'customers'=>$root.'/storage/tmp/b3-customers-'.$tag.'.json',
];
putenv('ANANTA_TEST_TENANT_FILE='.$files['tenants']);
putenv('ANANTA_TEST_MARKETPLACE_FILE='.$files['market']);
putenv('ANANTA_TEST_SOURCING_FILE='.$files['sourcing']);
putenv('ANANTA_TEST_CUSTOMERS_FILE='.$files['customers']);
require $root.'/src/bootstrap.php';
$issues=[];$assert=static function(bool $condition,string $message)use(&$issues){if(!$condition)$issues[]=$message;};
try {
    $admin=['guid'=>uuidv7(),'email'=>'platform@example.invalid','name'=>'Platform Admin','role'=>'admin'];
    $_SESSION['admin_user']=$admin;
    $alpha=marketplace_supplier_save(['name'=>'Pune QA Alpha Nursery','city'=>'Pune','phone'=>'919811110001','status'=>'verified']);
    $beta=marketplace_supplier_save(['name'=>'Pune QA Beta Nursery','city'=>'Pune','phone'=>'919811110002','status'=>'verified']);
    $gamma=marketplace_supplier_save(['name'=>'Pune QA Gamma Nursery','city'=>'Pune','phone'=>'919811110003','status'=>'verified']);
    $unonboarded=marketplace_supplier_save(['name'=>'Pune QA No-Login Nursery','city'=>'Pune','phone'=>'919811110004','status'=>'verified']);
    $invA=tenant_invite_issue($alpha,'rfq-alpha@example.invalid','owner');
    $invB=tenant_invite_issue($beta,'rfq-beta@example.invalid','manager');
    $invC=tenant_invite_issue($gamma,'rfq-gamma@example.invalid','inventory');
    unset($_SESSION['admin_user']);
    tenant_invite_accept($invA,'Owner Alpha','Sourcing-Test-Alpha-2026!');
    tenant_invite_accept($invB,'Manager Beta','Sourcing-Test-Beta-2026!');
    tenant_invite_accept($invC,'Inventory Gamma','Sourcing-Test-Gamma-2026!');
    $assert(!in_array($unonboarded,array_column(sourcing_verified_suppliers(),'id'),true),
        'Verified directory supplier with no active login was offered RFQ delivery.');
    try{sourcing_parse_recipients([$unonboarded]);$issues[]='Unonboarded directory listing accepted RFQ routing.';}catch(InvalidArgumentException){}
    $buyerGuid=uuidv7();$otherGuid=uuidv7();
    confidential_json_write(commerce_customer_file(),[
        ['guid'=>$buyerGuid,'name'=>'Original Buyer','email'=>'buyer-private@example.invalid','phone'=>'919888777666','status'=>'active','role'=>'customer'],
        ['guid'=>$otherGuid,'name'=>'Other Buyer','email'=>'other@example.invalid','phone'=>'','status'=>'active','role'=>'customer'],
    ],'sanjaynursery:customers:v1');
    $_SESSION['customer_user']=['guid'=>$buyerGuid,'name'=>'Original Buyer','email'=>'buyer-private@example.invalid'];
    $plant=products_public()[0]??null;
    if(!$plant)throw new RuntimeException('Missing plant catalogue fixture.');
    $input=[
        'product_slug'=>$plant['slug'],'quantity'=>'25',
        'specification'=>'120 cm, Grade A, healthy root ball',
        'destination'=>'Pune, 412201','notes'=>'Need nursery-ready stock.',
        'supplier_ids'=>[$alpha,$beta],'consent'=>'yes',
    ];
    $rfqId=sourcing_create($input);
    $assert(tenant_valid_id($rfqId),'RFQ ID not UUIDv7 compatible.');
    $all=sourcing_store()['rfqs'];
    $assert(count($all)===1,'Expected one private RFQ.');
    $assert(count($all[$rfqId]['recipients'])===2,'Supplier routing count incorrect.');
    $assert(($all[$rfqId]['buyer_contact']??null)===null,'Buyer contact disclosed without opt-in.');
    $assert(($all[$rfqId]['status']??'')==='open','Unexpected sourcing state.');
    $raw=(string)file_get_contents($files['sourcing']);
    $assert(!str_contains($raw,'buyer-private@example.invalid')&&!str_contains($raw,'Pune, 412201'),'Buyer data not encrypted.');
    $assert(!str_contains($raw,'Grade A'),'Plant requirement leaked in plaintext.');
    $assert(count(sourcing_buyer_rows())===1,'Buyer sourcing list missing request.');
    $assert(sourcing_buyer_rfq($rfqId)!==null,'Buyer cannot access their own RFQ.');
    $_SESSION['customer_user']['guid']=$otherGuid;
    $assert(sourcing_buyer_rfq($rfqId)===null,'Unrelated buyer accessed private RFQ.');
    $_SESSION['customer_user']['guid']=$buyerGuid;
    try{sourcing_create(array_merge($input,['supplier_ids'=>[$alpha,$alpha]]));$issues[]='Duplicate recipients accepted.';}catch(InvalidArgumentException){}
    try{sourcing_create(array_merge($input,['consent'=>'']));$issues[]='RFQ created without consent.';}catch(InvalidArgumentException){}
    try{sourcing_create(array_merge($input,['supplier_ids'=>array_fill(0,6,$alpha)]));$issues[]='More than five recipients accepted.';}catch(InvalidArgumentException){}
    $assert(tenant_login('rfq-alpha@example.invalid','Sourcing-Test-Alpha-2026!'),'Alpha supplier login failed.');
    $aView=sourcing_supplier_rfq($rfqId,$alpha);
    $assert(($aView['quantity']??0)===25,'Supplier request projection missing quantity.');
    $assert(!array_key_exists('recipients',$aView),'Competing supplier list leaked.');
    $assert(($aView['buyer_contact']??null)===null,'Private buyer contact leaked to supplier.');
    $assert(count(sourcing_supplier_rows($alpha))===1,'Alpha inbox count incorrect.');
    sourcing_supplier_respond($alpha,$rfqId,['decision'=>'quoted','unit_price_inr'=>'450','lead_days'=>'12','message'=>'Plants available after fresh confirmation.']);
    $assert(sourcing_supplier_rfq($rfqId,$alpha)['invitation']['status']==='quoted','Supplier quote not saved.');
    $assert(sourcing_buyer_rfq($rfqId)['recipients'][$alpha]['quote']['total_inr']===11250,'Quoted total incorrect.');
    try{sourcing_supplier_respond($alpha,$rfqId,['decision'=>'quoted','unit_price_inr'=>'-5','lead_days'=>'1']);$issues[]='Negative supplier price accepted.';}catch(InvalidArgumentException){}
    tenant_logout();
    $assert(tenant_login('rfq-beta@example.invalid','Sourcing-Test-Beta-2026!'),'Beta supplier login failed.');
    $bView=sourcing_supplier_rfq($rfqId,$beta);
    $assert(!isset($bView['recipients'][$alpha])&&!str_contains(json_encode($bView,JSON_THROW_ON_ERROR),'11250'),'Competing quote leaked.');
    $assert(count(sourcing_supplier_rows($beta))===1,'Beta inbox count incorrect.');
    sourcing_supplier_respond($beta,$rfqId,['decision'=>'declined','message'=>'Dispatch region not supported.']);
    $assert(sourcing_buyer_rfq($rfqId)['recipients'][$beta]['status']==='declined','Supplier decline not saved.');
    tenant_logout();
    $assert(tenant_login('rfq-gamma@example.invalid','Sourcing-Test-Gamma-2026!'),'Gamma supplier login failed.');
    $assert(count(sourcing_supplier_rows($gamma))===0,'Uninvited nursery discovered private RFQ.');
    $assert(!tenant_can('rfq.quote',$gamma),'Inventory-only role received RFQ quote permission.');
    $assert(sourcing_supplier_rfq($rfqId,$gamma)===null,'Uninvited nursery read private RFQ.');
    try{sourcing_supplier_respond($gamma,$rfqId,['decision'=>'quoted','unit_price_inr'=>'99','lead_days'=>'1']);$issues[]='Uninvited nursery submitted a quote.';}catch(RuntimeException){}
    tenant_logout();
    $_SESSION['customer_user']['guid']=$otherGuid;
    try{sourcing_buyer_close($rfqId);$issues[]='Other buyer closed sourcing RFQ.';}catch(RuntimeException){}
    $_SESSION['customer_user']['guid']=$buyerGuid;
    sourcing_buyer_close($rfqId);
    $assert(sourcing_buyer_rfq($rfqId)['status']==='closed','Buyer close did not persist.');
    $assert(tenant_login('rfq-alpha@example.invalid','Sourcing-Test-Alpha-2026!'),'Alpha second login failed.');
    try{sourcing_supplier_respond($alpha,$rfqId,['decision'=>'quoted','unit_price_inr'=>'999','lead_days'=>'3']);$issues[]='Closed RFQ accepted supplier quote.';}catch(RuntimeException){}
    tenant_logout();
    // Explicit contact-sharing opt-in: only selected suppliers may access buyer contacts.
    $sharedId=sourcing_create(array_merge($input,['share_contact'=>'yes','supplier_ids'=>[$beta]]));
    $assert(sourcing_buyer_rfq($sharedId)['contact_shared']===true,'Consent flag missing.');
    $assert(tenant_login('rfq-beta@example.invalid','Sourcing-Test-Beta-2026!'),'Beta second login failed.');
    $bContact=sourcing_supplier_rfq($sharedId,$beta);
    $assert(($bContact['buyer_contact']['email']??'')==='buyer-private@example.invalid','Opted-in buyer contact not routed.');
    tenant_logout();
    $assert(tenant_login('rfq-alpha@example.invalid','Sourcing-Test-Alpha-2026!'),'Alpha third login failed.');
    $assert(sourcing_supplier_rfq($sharedId,$alpha)===null,'Non-invited supplier read shared contact.');
    tenant_logout();
    $_SESSION['admin_user']=$admin;
    $stats=sourcing_admin_summary();
    $assert($stats['total']===2&&$stats['invites']===3&&$stats['quoted']===1,'Platform RFQ overview incorrect.');
    unset($_SESSION['admin_user']);
    $route=(string)file_get_contents($root.'/public/index.php');
    foreach(['/account/sourcing','/supplier/enquiries','/sourcing'] as $needle)
        $assert(str_contains($route,$needle),'Missing RFQ route: '.$needle);
} catch(Throwable $e){$issues[]=$e->getMessage();}
foreach($files as $file)@unlink($file);
if($issues){fwrite(STDERR,"FAIL RFQ acceptance:\n".implode("\n",array_unique($issues))."\n");exit(1);}
echo "PASS: encrypted buyer RFQs, multi-nursery routing, explicit contact consent, supplier isolation, quote & decline, buyer-only close, role checks and pilot safety.\n";
