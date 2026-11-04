<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('I',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=supplier-import-test');
putenv('SANJAY_AGENT_SIGNING_KEY=supplier-import-agent-012345678901');
$_SERVER['HTTP_HOST']='stage.example.invalid';
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['REMOTE_ADDR']='127.0.0.96';
$nonce=bin2hex(random_bytes(6));
$paths=[
    'tenant'=>$root.'/storage/tmp/import-tenants-'.$nonce.'.json',
    'branch'=>$root.'/storage/tmp/import-branches-'.$nonce.'.json',
    'market'=>$root.'/storage/tmp/import-offers-'.$nonce.'.json',
];
putenv('ANANTA_TEST_TENANT_FILE='.$paths['tenant']);
putenv('ANANTA_TEST_BRANCHES_FILE='.$paths['branch']);
putenv('ANANTA_TEST_MARKETPLACE_FILE='.$paths['market']);
require $root.'/src/bootstrap.php';
$errors=[];$tempFiles=[];
$assert=static function(bool $ok,string $msg)use(&$errors){if(!$ok)$errors[]=$msg;};
$write=static function(string $content)use(&$tempFiles):string{
    $p=tempnam(sys_get_temp_dir(),'ananta-csv-');
    if($p===false)throw new RuntimeException('No temporary CSV storage.');
    file_put_contents($p,$content);
    $tempFiles[]=$p;
    return $p;
};
try {
    $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'reviewer@example.invalid','name'=>'QA Reviewer','role'=>'admin'];
    $a=marketplace_supplier_save(['name'=>'A Import Nursery','city'=>'Pune','phone'=>'919876543211','status'=>'verified']);
    $b=marketplace_supplier_save(['name'=>'B Import Nursery','city'=>'Pune','phone'=>'919876543212','status'=>'verified']);
    $invA=tenant_invite_issue($a,'import-a@example.invalid','owner');
    $invB=tenant_invite_issue($b,'import-b@example.invalid','owner');
    unset($_SESSION['admin_user']);
    tenant_invite_accept($invA,'Import Owner A','Import-Secret-2026-A!');
    tenant_invite_accept($invB,'Import Owner B','Import-Secret-2026-B!');
    $assert(tenant_login('import-a@example.invalid','Import-Secret-2026-A!'),'Tenant A login failed.');
    $branchA=supplier_branch_save($a,['name'=>'A dispatch branch','city'=>'Pune','address'=>'Private test block']);
    $p=products_public()[0]??null;
    if(!$p)throw new RuntimeException('Catalogue fixture missing.');
    $header=implode(',',supplier_csv_headers())."\n";
    $csv=$write($header.$branchA.','.$p['slug'].',"12 inch pot, grade A",800,9'."\n".
        $branchA.','.$p['slug'].',"16 inch pot, grade A",1400,3'."\n");
    $preview=supplier_csv_stage($csv,$a);
    $assert(count($preview['rows'])===2,'Preview row count incorrect.');
    $assert(($preview['rows'][0]['product_guid']??'')===$p['guid'],'Canonical product resolution failed.');
    $assert(supplier_csv_pending($a)['token']===$preview['token'],'Preview is not session-bound.');
    try{supplier_csv_commit($a,bin2hex(random_bytes(24)));$errors[]='Wrong preview token accepted.';}catch(RuntimeException){}
    $result=supplier_csv_commit($a,$preview['token']);
    $assert($result['created']===2&&$result['skipped']===0,'Atomic draft import count incorrect.');
    $assert(supplier_csv_pending($a)===null,'Completed preview not cleared.');
    $offers=array_values(array_filter(marketplace_offers_store(true)['offers'],static fn($o)=>($o['supplier_id']??'')===$a));
    $assert(count($offers)===2,'Expected two private offers.');
    foreach($offers as $o){
        $assert(($o['status']??'')==='draft','CSV imported a public listing.');
        $assert(($o['availability']??'')==='unknown','CSV confirmed unverified stock.');
        $assert(($o['branch_id']??'')===$branchA,'CSV branch ownership lost.');
        $assert(isset($o['import_key']),'Import row idempotency key missing.');
    }
    $retry=supplier_csv_insert_drafts($a,$preview['id'],$preview['rows']);
    $assert($retry['created']===0&&$retry['skipped']===2,'Identical retry duplicated offers.');
    $secondPreview=supplier_csv_stage($csv,$a);
    $secondResult=supplier_csv_commit($a,$secondPreview['token']);
    $assert($secondResult['created']===0&&$secondResult['skipped']===2,'Duplicate plant/grade/branch imported.');
    $assert(count(array_filter(marketplace_public_offers_dto($p),static fn($x)=>($x['supplier_id']??'')===$a))===0,'Draft CSV offers leaked to buyers.');
    $wrongHeader=$write("supplier,product,grade,price,qty\nA,B,C,100,1\n");
    try{supplier_csv_parse_file($wrongHeader,$a);$errors[]='Malformed CSV header passed.';}catch(InvalidArgumentException){}
    $unknownProduct=$write($header.$branchA.',not-a-real-ananta-plant,Sample pot,100,1'."\n");
    try{supplier_csv_parse_file($unknownProduct,$a);$errors[]='Unknown plant passed.';}catch(InvalidArgumentException){}
    $formula=$write($header.$branchA.','.$p['slug'].',"=HYPERLINK(bad)",100,1'."\n");
    // Labels are treated as text in the server and HTML escaped in the preview;
    // CSV export is not provided, so this value never becomes an executable formula.
    $assert(count(supplier_csv_parse_file($formula,$a))===1,'Ordinary literal label incorrectly rejected.');
    $duplicateRows=$write($header.$branchA.','.$p['slug'].',"QA pot",100,1'."\n".$branchA.','.$p['slug'].',"QA pot",100,1'."\n");
    try{supplier_csv_parse_file($duplicateRows,$a);$errors[]='Duplicate row passed.';}catch(InvalidArgumentException){}
    $tooMany=$write($header.str_repeat($branchA.','.$p['slug'].',"B grade 1",100,1'."\n",51));
    try{supplier_csv_parse_file($tooMany,$a);$errors[]='Oversized CSV accepted.';}catch(InvalidArgumentException){}
    $crossTenant=$write($header.$branchA.','.$p['slug'].',"Cross branch",120,1'."\n");
    tenant_logout();
    $assert(tenant_login('import-b@example.invalid','Import-Secret-2026-B!'),'Tenant B login failed.');
    try{supplier_csv_parse_file($crossTenant,$b);$errors[]='Tenant B imported tenant A branch.';}catch(RuntimeException){}
    try{supplier_csv_insert_drafts($a,$preview['id'],$preview['rows']);$errors[]='Tenant B inserted tenant A offers.';}catch(RuntimeException){}
    $assert(count(array_filter(marketplace_offers_store(true)['offers'],static fn($o)=>($o['supplier_id']??'')===$b))===0,'Other tenant gained offers.');
    $sample=(string)file_get_contents($root.'/templates/supplier/dashboard.php');
    foreach(['supplier/import/preview','supplier/import/confirm','supplier/import/template.csv'] as $marker){
        $assert(str_contains($sample,$marker),'Supplier import UI missing: '.$marker);
    }
} catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($tempFiles as $p)@unlink($p);
foreach($paths as $path)@unlink($path);
if($errors){fwrite(STDERR,"FAIL supplier CSV import:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: tenant-scoped CSV preview, atomic draft import, idempotent retries, invalid input, private stock and cross-nursery isolation.\n";
