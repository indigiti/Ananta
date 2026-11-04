<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('G',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=growth-test-secret');
putenv('SANJAY_AGENT_SIGNING_KEY=growth-test-agent-signing-key-01234567890123456789');
$_SERVER['HTTP_HOST']='staging.example.test';$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_METHOD']='GET';
require $root.'/src/bootstrap.php';
$errors=[];function gcheck(bool $ok,string $msg):void{global $errors;if(!$ok)$errors[]=$msg;}
$_SESSION['admin_user']=['guid'=>'019-growth-admin','email'=>'growth@example.invalid','name'=>'Growth Admin','role'=>'admin'];
$settingsPath=growth_settings_file();$settingsBackup=is_file($settingsPath)?file_get_contents($settingsPath):null;
$journal=growth_404_file();$journalBackup=is_file($journal)?file_get_contents($journal):null;
try{
    $map=growth_collection_map();gcheck(count($map)>=24,'Expected category + plant-family collection hubs.');
    gcheck(isset($map['indoor-plants']),'Indoor Plants hub missing.');gcheck(isset($map['aglaonema']),'Aglaonema hub missing.');
    gcheck(count($map)===count(array_unique(array_keys($map))),'Collection hub slugs are not unique.');
    $p=products_public()[0]??[];gcheck((bool)$p,'Catalogue unexpectedly empty.');
    $schema=growth_product_schema($p);gcheck(($schema['@type']??'')==='Product','Product schema missing.');
    gcheck(!isset($schema['offers']),'Enquiry-only product incorrectly exposes schema Offer.');
    gcheck(merchant_product_dto($p)===[],'Enquiry-only product incorrectly eligible for Merchant feed.');
    $saved=growth_save_settings([
        'analytics_enabled'=>'1','cookie_consent_enabled'=>'1','ga4_measurement_id'=>'G-ABC12345','gtm_container_id'=>'',
        'google_site_verification'=>'test-google-token','bing_site_verification'=>'test-bing-token','merchant_center_id'=>'123456789',
        'google_business_profile_url'=>'https://www.google.com/maps','google_business_place_id'=>'ChIJTEST',
        'logo_url'=>'https://staging.example.test/logo.png','default_social_image'=>'https://staging.example.test/social.jpg',
        'same_as'=>"https://www.instagram.com/example\nhttps://www.youtube.com/@example",'content_quality_publish_threshold'=>'70'
    ]);
    gcheck($saved['analytics_enabled']===true&&$saved['ga4_measurement_id']==='G-ABC12345','Growth analytics settings did not persist correctly.');
    gcheck(growth_tracking_enabled(),'Configured analytics did not become enabled.');
    growth_record_404('/legacy-missing-plant');$summary=growth_404_summary(20);
    gcheck((bool)array_filter($summary,fn($r)=>($r['path']??'')==='/legacy-missing-plant'),'404 migration journal did not record missing path.');
    $checks=growth_release_checks();foreach($checks as $c)gcheck(!empty($c['ok']),'Growth release check failed: '.$c['name'].' — '.$c['detail']);
} catch(Throwable $e){$errors[]='Unhandled: '.$e->getMessage();}
finally{
    if($settingsBackup===null)@unlink($settingsPath);else file_put_contents($settingsPath,$settingsBackup,LOCK_EX);
    if($journalBackup===null)@unlink($journal);else file_put_contents($journal,$journalBackup,LOCK_EX);
}
if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: Growth settings, clean collection hubs, safe product schema/Merchant eligibility, analytics readiness and 404 migration monitoring verified.\n";
