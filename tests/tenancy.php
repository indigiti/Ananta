<?php
declare(strict_types=1);
/** Isolated tenant-security acceptance tests; no production records modified. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('T',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=tenant-regression');
putenv('SANJAY_AGENT_SIGNING_KEY=tenant-regression-agent-key-0123456789');
$nonce=bin2hex(random_bytes(5));
$private=$root.'/storage/tmp/tenancy-test-'.$nonce.'.json';
$market=$root.'/storage/tmp/marketplace-test-'.$nonce.'.json';
putenv('ANANTA_TEST_TENANT_FILE='.$private);
putenv('ANANTA_TEST_MARKETPLACE_FILE='.$market);
$_SERVER['HTTP_HOST']='staging.example.test';
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['REMOTE_ADDR']='127.0.0.88';
require $root.'/src/bootstrap.php';
$errors=[];
$assert=static function(bool $ok,string $message)use(&$errors){if(!$ok)$errors[]=$message;};
try{
    $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'qa-admin@example.test','name'=>'QA administrator','role'=>'admin'];
    $a=marketplace_supplier_save(['name'=>'Alpha Verified Nursery','city'=>'Pune','phone'=>'919999111222','status'=>'verified']);
    $b=marketplace_supplier_save(['name'=>'Beta Verified Nursery','city'=>'Solapur','phone'=>'919999333444','status'=>'verified']);
    $assert($a!==$b&&tenant_valid_id($a)&&tenant_valid_id($b),'Supplier tenant UUIDs invalid.');
    $d=tenant_store();
    $assert(($d['tenants'][$a]['status']??'')==='active'&&($d['tenants'][$b]['status']??'')==='active','Tenant activation not synchronized.');
    $inviteA=tenant_invite_issue($a,'owner-a@example.test','owner');
    $inviteB=tenant_invite_issue($b,'staff-b@example.test','inventory');
    $assert(strlen($inviteA)===64&&tenant_invite_preview($inviteA)['tenant_id']===$a,'Invitation scope invalid.');
    unset($_SESSION['admin_user']);
    $userA=tenant_invite_accept($inviteA,'Alpha Owner','Long-Unique-Pass-Alpha-123!');
    $userB=tenant_invite_accept($inviteB,'Beta Inventory','Long-Unique-Pass-Beta-123!');
    try{tenant_invite_accept($inviteA,'Replay User','Long-Replay-Password-1234!');$errors[]='Invitation replay accepted.';}catch(RuntimeException){}
    $assert(tenant_login('owner-a@example.test','Long-Unique-Pass-Alpha-123!'),'Alpha login failed.');
    $identity=tenant_identity();
    $assert(($identity['tenant_id']??'')===$a,'Tenant A context mismatch.');
    $assert(tenant_can('offers.draft',$a),'Owner cannot create drafts.');
    $assert(!tenant_can('offers.read',$b),'Owner read another tenant.');
    $assert(!tenant_switch($b),'Cross-tenant context switch succeeded.');
    $p=products_public()[0]??null;
    if(!$p)throw new RuntimeException('No canonical catalogue product for QA.');
    $input=['supplier_id'=>$a,'product_slug'=>$p['slug'],'label'=>'100 cm pot, 12 inch · QA',
        'status'=>'draft','availability'=>'unknown','price_inr'=>'150','quantity'=>'5'];
    $offerA=marketplace_offer_save($input);
    $assert(marketplace_offer_by_id($offerA)['supplier_id']===$a,'Draft offer tenant ID wrong.');
    try{marketplace_offer_save(array_merge($input,['status'=>'published']));$errors[]='Supplier directly published offer.';}catch(RuntimeException){}
    marketplace_offer_report($offerA,$a,'reported',4);
    $assert(marketplace_offer_by_id($offerA)['availability']==='reported','Supplier stock report failed.');
    tenant_logout();
    $assert(tenant_login('staff-b@example.test','Long-Unique-Pass-Beta-123!'),'Beta login failed.');
    $assert(!tenant_can('offers.draft',$b),'Inventory role gained draft permission.');
    try{tenant_require('offers.read',$a);$errors[]='Beta accessed Alpha scope.';}catch(RuntimeException){}
    try{marketplace_offer_report($offerA,$b,'reported',999);$errors[]='Cross-tenant stock mutation succeeded.';}catch(RuntimeException){}
    try{
        marketplace_offer_save(['id'=>$offerA,'supplier_id'=>$b,'product_slug'=>$p['slug'],
            'label'=>'Cross-tenant tampering','status'=>'draft','availability'=>'unknown']);
        $errors[]='Cross-tenant offer overwrite succeeded.';
    }catch(RuntimeException){}
    $assert((marketplace_offer_by_id($offerA)['quantity']??null)===4,'Cross-tenant attack changed inventory.');
    $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'qa-admin@example.test','name'=>'QA','role'=>'admin'];
    tenant_revoke($b,$userB);
    unset($_SESSION['admin_user']);
    $assert(tenant_identity()===null,'Revoked membership remained effective.');
    $assert(!tenant_can('offers.read',$b),'Revoked membership could read.');
    $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'qa-admin@example.test','name'=>'QA','role'=>'admin'];
    marketplace_supplier_save(['id'=>$a,'name'=>'Alpha Verified Nursery','city'=>'Pune','phone'=>'919999111222','status'=>'suspended']);
    unset($_SESSION['admin_user']);
    $assert(!tenant_login('owner-a@example.test','Long-Unique-Pass-Alpha-123!'),'Suspended supplier could sign in.');
    $assert(!tenant_valid_id('../../private-domain'),'Path input accepted as UUID.');
    $raw=(string)file_get_contents($private);
    $assert(!str_contains($raw,'owner-a@example.test')&&!str_contains($raw,'Long-Unique-Pass-Alpha'),'Private identity store not encrypted.');
    $assert(!str_contains($raw,$inviteA),'Invitation bearer token persisted in plaintext.');
    $assert((marketplace_offer_by_id($offerA)['supplier_id']??'')===$a,'Supplier ownership drifted.');
}catch(Throwable $e){$errors[]=$e->getMessage();}
@unlink($private);@unlink($market);
if($errors){fwrite(STDERR,"FAIL tenant foundation:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: encrypted supplier identities, one-time invitations, roles, tenant isolation, scoped drafts, availability reporting, revocation and suspension.\n";
