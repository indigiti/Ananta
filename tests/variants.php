<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('V',32)));putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=variant-test-secret');putenv('SANJAY_AGENT_SIGNING_KEY=variant-test-agent-signing-key-0123456789');
$_SERVER['HTTP_HOST']='staging.example.test';$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REMOTE_ADDR']='127.0.0.1';
require $root.'/src/bootstrap.php';
$errors=[];function vcheck(bool $ok,string $msg):void{global $errors;if(!$ok)$errors[]=$msg;}
$backup=(string)file_get_contents(variant_file());$revBefore=glob(variant_revisions_dir().'/*.json')?:[];
try{
    $p=products_public()[0]??null;vcheck(is_array($p),'No parent plant available.');
    $bad=variant_form_payload(['sku'=>'V-BAD','label'=>'Bad commerce','status'=>'active','sales_mode'=>'commerce','availability'=>'in_stock','track_inventory'=>'1','stock'=>'0','price'=>'0'],$p,null);
    $be=validate_variant_record($bad);vcheck(count($be)>=2,'Incomplete commerce variant was not blocked.');
    $v=variant_form_payload(['sku'=>'V-TEST-12','label'=>'12 cm pot · Test','status'=>'active','pot_size_cm'=>'12','plant_height_cm_min'=>'18','plant_height_cm_max'=>'28','grade'=>'Select','batch_code'=>'QA-SEP26','batch_date'=>'2026-09-16','price'=>'499','compare_at_price'=>'599','sales_mode'=>'commerce','availability'=>'in_stock','track_inventory'=>'1','stock'=>'4','lead_time'=>'1–2 working days','shipping_class'=>'plant-standard','local_pickup'=>'1','merchant_enabled'=>'1'],$p,null);
    $ve=validate_variant_record($v);vcheck(!$ve,'Valid live variant rejected: '.implode(' ',$ve));if(!$ve)upsert_variant($v,foundation_checksum(variant_file()));
    $fresh=variant_by_sku('V-TEST-12');vcheck(is_array($fresh)&&variant_is_sellable($fresh),'Saved variant is not sellable.');
    vcheck(product_has_sellable_variants($p),'Parent did not inherit sellability from live variant.');$range=product_variant_price_range($p);vcheck(($range['min']??0)===499&&($range['max']??0)===499,'Parent price range failed.');
    $schema=growth_product_schema($p);vcheck(($schema['@type']??'')==='ProductGroup','Variant parent did not emit ProductGroup schema.');vcheck(count($schema['hasVariant']??[])===1&&isset($schema['hasVariant'][0]['offers']),'Variant schema Offer missing.');
    $dto=public_product_dto($p);vcheck(count($dto['variants']??[])===1&&($dto['variants'][0]['sku']??'')==='V-TEST-12','Public product DTO did not expose safe variant data.');
    vcheck(merchant_variant_dto($fresh,$p)===[],'Variant without approved image incorrectly entered Merchant feed.');
    $csv="parent_slug,sku,label,pot_size_cm,pot_size_label,height_min_cm,height_max_cm,grade,batch_code,batch_date,price,compare_at_price,sales_mode,availability,track_inventory,stock,lead_time,shipping_class,local_pickup,merchant_enabled,image,notes,status\n".$p['slug'].",V-CSV-10,10 cm pot,10,Nursery pot,15,22,Standard,CSV-B1,2026-09-16,0,0,enquiry,coming_soon,1,0,Confirm batch,plant-standard,1,0,,CSV test,draft";
    $r=variant_import_csv($csv);vcheck(($r['added']??0)===1&&($r['skipped']??0)===0,'CSV variant import failed.');
    $dupe=variant_form_payload(['sku'=>'V-TEST-12','label'=>'Duplicate','status'=>'draft'],$p,null);vcheck((bool)array_filter(validate_variant_record($dupe),fn($e)=>str_contains($e,'already in use')),'Duplicate variant SKU was not blocked.');
    foreach(variant_release_checks() as $c)vcheck(!empty($c['ok']),'Variant release check failed: '.$c['name']);
}catch(Throwable $e){$errors[]='Unhandled: '.$e->getMessage();}
file_put_contents(variant_file(),$backup,LOCK_EX);$revAfter=glob(variant_revisions_dir().'/*.json')?:[];foreach(array_diff($revAfter,$revBefore) as $f)@unlink($f);
if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);}echo "PASS: live-variant validation, parent linkage, price range, ProductGroup schema, public DTO safety, Merchant image gate and CSV import verified.\n";
