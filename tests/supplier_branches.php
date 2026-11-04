<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('B',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=branch-tests-only');
putenv('SANJAY_AGENT_SIGNING_KEY=branch-tests-agent-key-012345678901');
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['HTTP_HOST']='branch-test.example.invalid';
$_SERVER['REMOTE_ADDR']='127.0.0.93';
$nonce=bin2hex(random_bytes(6));
$paths=[
'branch'=>$root.'/storage/tmp/branch-'.$nonce.'.json',
'tenant'=>$root.'/storage/tmp/tenant-'.$nonce.'.json',
'market'=>$root.'/storage/tmp/market-'.$nonce.'.json',
];
putenv('ANANTA_TEST_BRANCHES_FILE='.$paths['branch']);
putenv('ANANTA_TEST_TENANT_FILE='.$paths['tenant']);
putenv('ANANTA_TEST_MARKETPLACE_FILE='.$paths['market']);
require $root.'/src/bootstrap.php';
$errors=[];
$check=static function(bool $ok,string $message)use(&$errors){if(!$ok)$errors[]=$message;};
try{
    $admin=['guid'=>uuidv7(),'email'=>'platform@example.invalid','name'=>'Platform Reviewer','role'=>'admin'];
    $_SESSION['admin_user']=$admin;
    $a=marketplace_supplier_save(['name'=>'QA Branch Nursery Alpha','city'=>'Pune','phone'=>'919988776655','status'=>'verified']);
    $b=marketplace_supplier_save(['name'=>'QA Branch Nursery Beta','city'=>'Solapur','phone'=>'919988776644','status'=>'verified']);
    $invA=tenant_invite_issue($a,'alpha-branches@example.invalid','owner');
    $invB=tenant_invite_issue($b,'beta-branches@example.invalid','owner');
    unset($_SESSION['admin_user']);
    tenant_invite_accept($invA,'Alpha Branch Owner','Alpha-Branch-Password-2026!');
    tenant_invite_accept($invB,'Beta Branch Owner','Beta-Branch-Password-2026!');
    $check(tenant_login('alpha-branches@example.invalid','Alpha-Branch-Password-2026!'),'Alpha branch login failed');
    $branchA=supplier_branch_save($a,['name'=>'Alpha Main Growing Farm','city'=>'Pune','address'=>'Private Alpha Road 117','pincode'=>'412201']);
    $check(tenant_valid_id($branchA),'Invalid branch UUID');
    $check(supplier_branch_by_id($branchA)['state']==='pending','Branch must begin pending');
    $check(count(supplier_branches_for_tenant($a))===1,'Alpha branch list mismatch');
    try{supplier_branches_for_tenant($b);$errors[]='Cross-tenant branch list leaked';}catch(RuntimeException){}
    try{supplier_branch_save($b,['name'=>'Cross Tenant Hijack','city'=>'Solapur']);$errors[]='Cross-tenant branch create allowed';}catch(RuntimeException){}
    $p=products_public()[0]??null;if(!$p)throw new RuntimeException('Missing baseline plant');
    $input=['supplier_id'=>$a,'branch_id'=>$branchA,'product_slug'=>$p['slug'],'label'=>'Grade A 150 cm 12-inch pot',
        'price_inr'=>'450','quantity'=>'9','availability'=>'unknown','status'=>'draft'];
    $offer=marketplace_offer_save($input);
    $check((marketplace_offer_by_id($offer)['branch_id']??'')===$branchA,'Offer branch binding missing');
    unset($_SESSION['supplier_user']);
    $_SESSION['admin_user']=$admin;
    try{marketplace_offer_save(array_merge($input,['id'=>$offer,'status'=>'published']));$errors[]='Unreviewed branch published';}catch(RuntimeException){}
    try{supplier_branch_review($b,$branchA,'approved','inspection REF-2026-11');$errors[]='Review wrong tenant accepted';}catch(RuntimeException){}
    supplier_branch_review($a,$branchA,'approved','inspection REF-2026-11');
    $check(supplier_branch_by_id($branchA)['state']==='approved','Branch approval did not stick');
    $adminOffer=array_merge($input,['id'=>$offer,'status'=>'published','availability'=>'confirmed','evidence_ref'=>'phone check QA-2026']);
    marketplace_offer_save($adminOffer);
    $public=marketplace_public_offers_dto($p);
    $selected=array_values(array_filter($public,static fn($o)=>($o['id']??'')===$offer));
    $check(count($selected)===1,'Approved branch offer missing from public DTO');
    $dto=$selected[0]??[];
    $check(($dto['branch']['name']??'')==='Alpha Main Growing Farm','Approved branch label not in DTO');
    $check(($dto['checkout_enabled']??true)===false,'Independent branch checkout activated');
    $json=json_encode($dto,JSON_THROW_ON_ERROR);
    foreach(['Private Alpha Road','412201','inspection REF','review_evidence','phone'] as $privateToken){
        $check(!str_contains($json,$privateToken),'Private branch data exposed: '.$privateToken);
    }
    $encrypted=(string)file_get_contents($paths['branch']);
    $check(!str_contains($encrypted,'Private Alpha Road'),'Branch file not encrypted');
    $check(!str_contains($encrypted,'inspection REF'),'Branch review evidence persisted unencrypted');
    unset($_SESSION['admin_user']);
    tenant_login('alpha-branches@example.invalid','Alpha-Branch-Password-2026!');
    supplier_branch_save($a,['id'=>$branchA,'name'=>'Alpha Updated Farm','city'=>'Pune','address'=>'Private Alpha Road 117','pincode'=>'412201']);
    $check(supplier_branch_by_id($branchA)['state']==='pending','Branch edit retained old approval');
    $public=marketplace_public_offers_dto($p);
    $check(!count(array_filter($public,static fn($o)=>($o['id']??'')===$offer)),'Pending branch public offer remained visible');
    try{marketplace_offer_save(array_merge($input,['id'=>$offer]));$errors[]='Supplier edited already published offer';}catch(RuntimeException){}
    try{marketplace_offer_report($offer,$b,'reported',100);$errors[]='Cross-tenant reported stock';}catch(RuntimeException){}
    tenant_logout();
    $check(tenant_login('beta-branches@example.invalid','Beta-Branch-Password-2026!'),'Beta branch login failed');
    try{supplier_branch_save($b,['id'=>$branchA,'name'=>'Hijacked Branch','city'=>'Solapur']);$errors[]='Cross-tenant branch overwrite';}catch(RuntimeException){}
    $branchB=supplier_branch_save($b,['name'=>'Beta Nursery Unit','city'=>'Solapur','pincode'=>'413001']);
    $check(count(supplier_branches_for_tenant($b))===1,'Beta branch isolated read failed');
    $check(($branchB!==$branchA),'Branch IDs collided');
    tenant_logout();
    $_SESSION['admin_user']=$admin;
    supplier_branch_review($a,$branchA,'approved','second inspection QA-222');
    $check(count(array_filter(marketplace_public_offers_dto($p),static fn($o)=>($o['id']??'')===$offer))===1,'Reapproved branch not visible');
    supplier_branch_review($a,$branchA,'suspended','suspended QA-2026');
    $check(count(array_filter(marketplace_public_offers_dto($p),static fn($o)=>($o['id']??'')===$offer))===0,'Suspended branch remained public');
    $check(supplier_branch_public($a,$branchA)===null,'Suspended public branch projection exposed');
    $code=(string)file_get_contents($root.'/public/index.php');
    $check(str_contains($code,"/supplier/branch/save")&&str_contains($code,"/admin/marketplace/branches/review"),'Branch web routes missing');
    $template=(string)file_get_contents($root.'/templates/supplier/dashboard.php');
    $check(str_contains($template,'Your nursery branches')&&str_contains($template,'Inventory and availability'),'Supplier branch UI missing');
} catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($paths as $path)@unlink($path);
if($errors){fwrite(STDERR,"FAIL supplier branch acceptance:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: two-tenant branches, encrypted private location, platform review, published-offer gate, branch edit invalidation, suspension and public DTO redaction.\n";
