<?php
declare(strict_types=1);
/** B8.4 local feed/SEO/AI evidence and opt-in Google Search Console read-only sync. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('D',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=b84-test');putenv('SANJAY_AGENT_SIGNING_KEY=b84-agent-test-0123456789');
$_SERVER['SCRIPT_NAME']='/ananta/index.php';$_SERVER['HTTP_HOST']='stage.example.invalid';$_SERVER['REMOTE_ADDR']='127.0.0.74';$_SERVER['HTTPS']='on';
$file=$root.'/storage/tmp/b84-distribution-'.bin2hex(random_bytes(5)).'.json';
putenv('ANANTA_TEST_DISTRIBUTION_FILE='.$file);
require $root.'/src/bootstrap.php';
$errors=[];$ok=static function(bool $test,string $why)use(&$errors){if(!$test)$errors[]=$why;};
$deny=static function(callable $cb,string $why)use(&$errors){
 try{$cb();$errors[]=$why;}catch(RuntimeException|InvalidArgumentException){}
};
try{
 $deny(static fn()=>discover_distribution_report(),'Unauthenticated distribution report accessible.');
 $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'qa@invalid.example','name'=>'QA','role'=>'admin'];
 $audit=discover_distribution_schema_audit();$actual=discover_merchant_rows();
 $ok($audit['sku_count']===count($actual),'Feed count mismatch.');
 $ok($audit['feed_sha256']===hash('sha256',merchant_xml()),'Feed SHA mismatch.');
 $ok($audit['submission_state']==='not_submitted_by_ananta','Local audit falsely claims Google submission.');
 $ok($audit['google_approvals_verified']===false,'Unverified Google approval claimed.');
 $ai=discover_distribution_ai_audit();$ok($ai['public_products']===count(products_public()),'AI feed count mismatch.');
 $ok($ai['ai_citation_guarantee']===false,'Guaranteed AI citation falsely claimed.');
 $snapshot=discover_distribution_refresh();
 $ok($snapshot['id']!==''&&count(discover_distribution_store()['snapshots'])===1,'Distribution snapshot not saved.');
 discover_distribution_refresh();
 $ok(count(discover_distribution_store()['snapshots'])===1,'Same-day duplicate snapshot was not deduplicated.');
 $raw=(string)file_get_contents($file);
 $ok(!str_contains($raw,'google_approvals_verified'),'Unencrypted audit snapshot stored to disk.');
 $ok(discover_distribution_report()['mode']==='scheduled_hosted_xml','Unsupported Merchant uploader was marked active.');
 $deny(static fn()=>discover_distribution_gsc_sync(),'Search Console queried without OAuth credentials.');
 putenv('ANANTA_GSC_PROPERTY=https://attacker.example/');
 putenv('ANANTA_GSC_ACCESS_TOKEN='.str_repeat('A',44));
 $deny(static fn()=>discover_distribution_gsc_sync(),'Wrong-domain Search Console property accepted.');
 putenv('ANANTA_GSC_PROPERTY='.absolute_url('/'));
 $calls=0;
 $gsc=discover_distribution_gsc_sync(static function($url,$body,$token)use(&$calls){
     $calls++;
     if(!str_starts_with($url,'https://www.googleapis.com/webmasters/v3/sites/'))
         throw new RuntimeException('Unexpected API hostname.');
     if(!str_contains($body,'"dimensions":["date"]'))throw new RuntimeException('Incorrect Search Console dimensions.');
     if(strlen($token)<30)throw new RuntimeException('Missing OAuth credential.');
     return ['rows'=>[
         ['clicks'=>4,'impressions'=>105],['clicks'=>6,'impressions'=>100],
     ]];
 });
 $ok($calls===1&&$gsc['clicks']===10&&$gsc['impressions']===205,'Read-only Google Search Console data aggregation failed.');
 $ok(discover_distribution_report()['gsc']['source']==='Google Search Console Search Analytics API','Provider provenance not recorded.');
 $ok(!str_contains((string)file_get_contents($file),str_repeat('A',44)),'OAuth token was persisted.');
 $views=(string)file_get_contents($root.'/templates/admin/discover-merchant.php');
 foreach(['Rich Results Test','Search Console','Google and AI distribution','feed_sha256'] as $needle)
     $ok(str_contains($views,$needle),'Discovery UI missing '.$needle);
}catch(Throwable $e){$errors[]=$e->getMessage();}
@unlink($file);
if($errors){fwrite(STDERR,"FAIL B8.4 distribution:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: Merchant feed reconciliation, XML/schema/AI local validation, encrypted digest snapshots and read-only GSC authentication boundaries.\n";
