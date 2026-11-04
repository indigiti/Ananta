<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('I',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=intelligence-commerce-test-secret');
putenv('SANJAY_AGENT_SIGNING_KEY=intelligence-test-agent-signing-key-0123456789');
$_SERVER['HTTP_HOST']='staging.example.test';$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_METHOD']='GET';
require $root.'/src/bootstrap.php';
$errors=[];function sicheck(bool $ok,string $msg):void{global $errors;if(!$ok)$errors[]=$msg;}
function si_test_tree(string $dir):array{$out=[];if(!is_dir($dir))return$out;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($it as $f)if($f->isFile())$out[substr($f->getPathname(),strlen($dir)+1)]=(string)file_get_contents($f->getPathname());return$out;}
function si_test_rm(string $dir):void{if(!is_dir($dir))return;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($dir);}
$siBackup=si_test_tree(si_root());$variantBackup=is_file(variant_file())?(string)file_get_contents(variant_file()):'[]';$revBefore=glob(variant_revisions_dir().'/*.json')?:[];$sessionBackup=$_SESSION;
try{
    // Clean isolated intelligence store.
    si_test_rm(si_root());si_ensure_dirs();
    foundation_json_write(si_settings_file(),si_default_settings(),foundation_checksum(si_settings_file()),'shopping-intelligence.test-settings');
    foundation_json_write(si_profiles_file(),[],foundation_checksum(si_profiles_file()),'shopping-intelligence.test-profiles');
    foundation_json_write(si_rules_file(),[],foundation_checksum(si_rules_file()),'shopping-intelligence.test-rules');
    foundation_json_write(si_relationships_file(),[],foundation_checksum(si_relationships_file()),'shopping-intelligence.test-relations');
    foundation_json_write(si_experiments_file(),[],foundation_checksum(si_experiments_file()),'shopping-intelligence.test-experiments');
    foreach(glob(si_events_dir().'/*.ndjson')?:[] as $f)@unlink($f);

    $products=products_public();sicheck(count($products)===376,'Expected 376 parent varieties.');
    foreach($products as $p){$i=(array)($p['intelligence']??[]);foreach(['plant_type','sunlight','watering','difficulty','humidity_requirement','placement','cart_booster','intent_categories'] as $k)if(!array_key_exists($k,$i)){$errors[]='Missing intelligence attribute '.$k.' for '.$p['slug'];break;}}
    $s=si_settings();sicheck(array_sum($s['recommendation_weights'])===100,'Recommendation weights do not total 100%.');
    sicheck($s['raw_event_retention_days']===90&&$s['profile_retention_days']===180,'Retention defaults differ from release contract.');

    $visitor='V-7F52A9B1';$session='S-ABCDEF12';$no=['essential'=>true,'analytics'=>false,'personalisation'=>false,'marketing'=>false];$yes=['essential'=>true,'analytics'=>true,'personalisation'=>true,'marketing'=>false];
    $ignored=si_record_event(['visitor_id'=>$visitor,'session_id'=>$session,'type'=>'product_view','consent'=>$no,'props'=>['product_slug'=>$products[0]['slug']]]);
    sicheck(($ignored['ignored']??'')==='consent','Consent-off event was not ignored.');
    si_record_event(['visitor_id'=>$visitor,'session_id'=>$session,'type'=>'search_submitted','consent'=>$yes,'props'=>['search_query'=>'low light indoor plants','result_count'=>12]]);
    si_record_event(['visitor_id'=>$visitor,'session_id'=>$session,'type'=>'product_view','consent'=>$yes,'props'=>['product_slug'=>$products[0]['slug']]]);
    si_record_event(['visitor_id'=>$visitor,'session_id'=>$session,'type'=>'search_refined','consent'=>$yes,'props'=>['search_query'=>'low light indoor plants','filter_name'=>'light','filter_value'=>'Low light']]);
    $profile=si_profile($visitor);sicheck(is_array($profile),'Personalised profile was not created.');
    sicheck(($profile['events']??0)===3,'Profile event count mismatch.');
    sicheck(isset($profile['interests']['Low-Light Plants']),'Session/search intent did not produce Low-Light Plants interest.');
    sicheck(isset($profile['interests']['Aglaonema']),'Product view did not produce collection affinity.');
    sicheck(in_array('Indoor Plant Lovers',si_profile_segments($profile),true),'Automatic segment assignment missing.');
    sicheck(si_interest_decay_factor(5)===1.0&&si_interest_decay_factor(20)===.75&&si_interest_decay_factor(60)===.45&&si_interest_decay_factor(120)===.20,'Interest decay schedule mismatch.');

    // Create one checkout-ready temporary SKU to verify cart-gap ranking and recommendation attribution.
    $target=$products[1];
    $v=variant_form_payload(['sku'=>'SI-QA-149','label'=>'12 cm pot · Intelligence QA','status'=>'active','pot_size_cm'=>'12','price'=>'149','sales_mode'=>'commerce','availability'=>'in_stock','track_inventory'=>'1','stock'=>'7','lead_time'=>'1–2 working days','shipping_class'=>'plant-standard'],$target,null);
    $ve=validate_variant_record($v);sicheck(!$ve,'Temporary recommendation variant invalid: '.implode(' ',$ve));if(!$ve)upsert_variant($v,foundation_checksum(variant_file()));
    si_save_settings(['_settings_scope'=>'cart','minimum_order_enabled'=>'1','minimum_order_value'=>'199','progress_bar'=>'1','show_remaining_amount'=>'1','quick_add'=>'1','maximum_recommendations'=>'4','cart_booster_tolerance_percent'=>'30','use_visitor_interests'=>'1','use_product_compatibility'=>'1','hide_purchased_products'=>'1','hide_out_of_stock'=>'1','allow_accessories'=>'1','allow_plants'=>'1']);
    $_SESSION['cart_v2']=[];
    $cartRecs=si_cart_recommendations($visitor,$session);sicheck(($cartRecs['gap']??0)===199,'Minimum-order gap calculation failed.');
    sicheck(count($cartRecs['recommendations']??[])>=1,'Checkout-ready cart recommendation was not returned.');
    $rec=$cartRecs['recommendations'][0]??null;if($rec){$dto=si_recommendation_dto($rec);sicheck(!empty($dto['recommendation_id'])&&($dto['variant']['price']??0)>0,'Recommendation attribution/variant DTO incomplete.');
        $ok=commerce_cart_add($dto['slug'],1,'','',[],$dto['variant']['guid'],['recommendation_id'=>$dto['recommendation_id'],'algorithm'=>$dto['algorithm'],'placement'=>$dto['placement'],'experiment_variant'=>$dto['experiment_variant'],'search_query'=>'low light indoor plants']);sicheck($ok,'Attributed Quick Add failed.');
        $raw=commerce_cart_raw()[0]??[];sicheck(($raw['intelligence_attribution']['recommendation_id']??'')===$dto['recommendation_id'],'Recommendation attribution did not persist into cart row.');
        $line=commerce_cart_lines()[0]??[];sicheck(($line['intelligence_attribution']['recommendation_id']??'')===$dto['recommendation_id'],'Recommendation attribution did not flow into cart line.');
    }
    $rule=si_save_rule(['name'=>'Aglaonema cart boost','enabled'=>'1','category'=>'Aglaonema','cart_max'=>'599','boost_products'=>$products[0]['slug'],'priority'=>'high']);sicheck(!empty($rule['guid']),'Rule builder failed.');
    $rel=si_save_relationship(['source_slug'=>$products[0]['slug'],'target_slug'=>$products[1]['slug'],'type'=>'similar_product','weight'=>90]);sicheck(($rel['weight']??0)===90,'Product relationship graph save failed.');
    $exp=si_save_experiment(['name'=>'Cart recommendation count','key'=>'cart-rec-count','placement'=>'cart_completion','variant_a'=>'4 recommendations','variant_b'=>'2 recommendations','enabled'=>'1']);$assignment=si_experiment_assignment($visitor,'cart_completion');sicheck(!empty($exp['guid'])&&in_array($assignment['variant']??'', ['A','B'],true),'Experiment assignment failed.');
    $suggest=si_search_suggestions('agla',$visitor);sicheck(count($suggest)>0,'Search suggestions failed.');
    foreach(si_release_checks() as $c)sicheck(!empty($c['ok']),'Intelligence release check failed: '.$c['name'].' — '.$c['detail']);
    si_reset_profile($visitor,false);$reset=si_profile($visitor);sicheck(($reset['events']??-1)===0&&empty($reset['interests']),'Profile reset did not clear interests.');
    si_reset_profile($visitor,true);sicheck(si_profile($visitor)===null,'Profile delete failed.');
}catch(Throwable $e){$errors[]='Unhandled: '.$e->getMessage();}
finally{
    file_put_contents(variant_file(),$variantBackup,LOCK_EX);$revAfter=glob(variant_revisions_dir().'/*.json')?:[];foreach(array_diff($revAfter,$revBefore) as $f)@unlink($f);
    si_test_rm(si_root());@mkdir(si_root(),0750,true);foreach($siBackup as $rel=>$content){$file=si_root().'/'.$rel;@mkdir(dirname($file),0750,true);file_put_contents($file,$content,LOCK_EX);}@mkdir(si_events_dir(),0750,true);
    $_SESSION=$sessionBackup;
}
if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);}echo "PASS: consent gating, anonymous profiling, intent/decay/segments, cart-gap recommendations, attribution, rules, relationships, experiments, search suggestions and privacy reset/delete verified.\n";
