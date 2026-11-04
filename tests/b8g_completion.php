<?php
declare(strict_types=1);
/** Read-only baseline/live reconciliation, fail-closed commerce, Google source evidence. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('G',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=b8g-test');
putenv('SANJAY_AGENT_SIGNING_KEY=b8g-test-agent-key-123456789000000');
$_SERVER['SCRIPT_NAME']='/ananta/index.php';
$_SERVER['HTTP_HOST']='stage.example.invalid';
$_SERVER['REMOTE_ADDR']='127.0.0.81';
$_SERVER['HTTPS']='on';
$rand=bin2hex(random_bytes(7));$paths=[
 $root.'/storage/tmp/b8g-review-'.$rand.'.json',
 $root.'/storage/tmp/b8g-google-'.$rand.'.json',
];
putenv('ANANTA_TEST_MERCHANT_REVIEWS_FILE='.$paths[0]);
putenv('ANANTA_TEST_MERCHANT_GOOGLE_FILE='.$paths[1]);
require $root.'/src/bootstrap.php';
$errors=[];$assert=static function(bool $ok,string $msg)use(&$errors){if(!$ok)$errors[]=$msg;};
$deny=static function(callable $f,string $msg)use(&$errors){
 try{$f();$errors[]=$msg;}catch(InvalidArgumentException|RuntimeException){}
};
try{
 $baseline=merchant_completion_baseline();
 $assert(count($baseline)===376,'Immutable catalogue baseline must contain exactly 376 SKUs.');
 $catalog=products_all();$report=merchant_completion_reconcile();
 $assert($report['stats']['baseline']===376,'Baseline count not preserved.');
 $assert($report['stats']['live']===count($catalog),'Live catalogue count incorrect.');
 $assert($report['stats']['baseline_missing']===0,'Repository initial baseline missing SKUs.');
 $assert($report['stats']['enquiry_only']===376,'Initial 376 plants unexpectedly checkout-ready.');
 $assert($report['stats']['feed_eligible']===0,'Unpriced/unapproved baseline entered Google Shopping.');
 $assert($report['writeback_performed']===false,'Reconciliation falsely reports writeback.');
 $assert($report['total_rows']===376,'Live SKU reconciliation pagination incomplete.');
 $assert(count(merchant_completion_reconcile(1,40)['rows'])===40,
     'Paginated SKU audit not bounded.');
 $assert(count(merchant_completion_reconcile(10,40)['rows'])===16,
     'Last SKU reconciliation page wrong.');
 $csv=merchant_completion_csv();
 $assert(substr_count($csv,"\n")===377,'CSV must export header plus all 376 rows.');
 $assert(str_contains($csv,'enquiry_only'),'Missing exclusion reasons in SKU export.');
 $deny(static fn()=>merchant_completion_review_save([]),'Unauthenticated product-review write accepted.');
 $deny(static fn()=>merchant_completion_policy_save([]),'Unauthenticated seller attestation accepted.');
 $deny(static fn()=>merchant_openai_compatible_csv(),'Unauthenticated OpenAI export accepted.');
 $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'qa@example.invalid','name'=>'QA','role'=>'admin'];
 $first=$catalog[0];$sku=$first['sku'];
 $deny(static fn()=>merchant_completion_review_save(['sku'=>$sku,'identifiers'=>'gtin','brand'=>'Sanjay Nursery',
    'identifier_value'=>'bad','evidence'=>'Receipt 2026 QA-112','stock_reviewed'=>'yes']),
    'Invalid GTIN accepted.');
 $deny(static fn()=>merchant_completion_review_save(['sku'=>'NOT-A-SKU','identifiers'=>'none_verified',
    'brand'=>'Sanjay Nursery','evidence'=>'Receipt QA 2026 112','stock_reviewed'=>'yes']),
    'Unknown SKU accepted.');
 merchant_completion_review_save(['sku'=>$sku,'identifiers'=>'none_verified','brand'=>'Sanjay Nursery',
    'evidence'=>'Warehouse count 2026-11-05 physical batch QA','stock_reviewed'=>'yes']);
 $assert(merchant_completion_merchant_fields($sku)['identifier_exists']==='no',
    'Explicit identifier review not reflected.');
 $assert(!in_array('identifier_review_missing',merchant_completion_item_reasons($sku),true),
    'Reviewed item still marked missing identifiers.');
 $assert(discover_parent_reasons($first)!==[],'Enquiry-only SKU silently became eligible.');
 $assert(merchant_product_dto($first)===[],'Unpriced item emitted into Merchant feed.');
 $assert(!str_contains((string)file_get_contents($paths[0]),'Warehouse count 2026'),
    'Evidence plaintext stored to disk.');
 $policy=[
    'seller_name'=>'Sanjay Nursery','shipping_url'=>absolute_url('/shipping'),
    'returns_url'=>absolute_url('/returns'),'evidence'=>'Policy review staging QA 2026',
    'seller_reviewed'=>'yes','shipping_reviewed'=>'yes','returns_reviewed'=>'yes',
    'domain_reviewed'=>'yes',
 ];
 $deny(static fn()=>merchant_completion_policy_save($policy),'Staging domain wrongly allowed to masquerade as live merchant origin.');
 unset($policy['domain_reviewed']);
 merchant_completion_policy_save($policy);
 $assert(in_array('merchant_domain_unverified',merchant_completion_policy_reason_codes(),true),
    'Unverified public Merchant origin was ignored.');
 $assert(merchant_completion_reconcile()['stats']['feed_eligible']===0,'Attestation created fictional retail offer.');
 $deny(static fn()=>merchant_google_sync(),'Merchant API queried without credentials.');
 putenv('ANANTA_MERCHANT_ACCOUNT_ID=123456789');
 putenv('ANANTA_MERCHANT_OAUTH_TOKEN='.str_repeat('T',45));
 $n=0;
 $google=merchant_google_sync(static function($url,$token)use(&$n,$sku){
    $n++;
    if(!str_starts_with($url,'https://merchantapi.googleapis.com/products/v1/accounts/123456789/products'))
        throw new RuntimeException('Merchant API URL wrong.');
    if(strlen($token)<30)throw new RuntimeException('Missing OAuth token.');
    if($n===1)return ['products'=>[['offerId'=>$sku,'productStatus'=>[
        'destinationStatuses'=>[['reportingContext'=>'FREE_LISTINGS',
            'approvedCountries'=>['IN'],'disapprovedCountries'=>[]]],
        'itemLevelIssues'=>[],
    ]]],'nextPageToken'=>'page-two'];
    if($n===2)return ['products'=>[['offerId'=>'NOT-IN-OUR-FEED','productStatus'=>[
        'destinationStatuses'=>[['reportingContext'=>'SHOPPING_ADS',
            'disapprovedCountries'=>['IN']]],
        'itemLevelIssues'=>[['code'=>'priceMismatch','severity'=>'DISAPPROVED',
            'applicableCountries'=>['IN']]],
    ]]]];
    throw new RuntimeException('Third Merchant API page wrongly requested.');
 });
 $assert($n===2,'Merchant API pagination not followed.');
 $assert($google['totals']['google_processed']===2,'Processed item count incorrect.');
 $assert($google['totals']['approved_in']===1,'Google approved-country report not parsed.');
 $assert($google['status_by_sku']['NOT-IN-OUR-FEED']['free_listings_in']==='unknown',
    'Shopping Ads denial was wrongly treated as free listing status.');
 $assert($google['provider_verified']===false&&$google['fixture_response']===true,
    'Test fixture masqueraded as real provider verification.');
 $assert(merchant_google_report()['provider_verified']===false,
    'Stored fixture was labelled real Google approval.');
 $assert(!str_contains((string)file_get_contents($paths[1]),str_repeat('T',45)),
    'OAuth token written into encrypted Merchant status file.');
 $deny(static fn()=>merchant_openai_compatible_csv(),'Empty approved inventory exported to OpenAI partner upload.');
 foreach(['src/merchant_completion.php','src/merchant_google.php','templates/admin/discover-completion.php'] as $p)
     $assert(is_file($root.'/'.$p),'Required B8-G private module missing: '.$p);
}catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($paths as $path)@unlink($path);
if($errors){fwrite(STDERR,"FAIL B8-G completion:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: immutable 376-SKU reconciliation, safe Merchant exclusions, SKU evidence, staging-domain guard, encrypted reviews, read-only Google pagination/status and OpenAI no-empty-export.\n";
