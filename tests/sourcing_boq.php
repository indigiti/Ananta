<?php
declare(strict_types=1);

/** B4 acceptance: CSV preview, tenant isolation, versioned quotes, buyer-approved draft. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('J',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=boq-qa');putenv('SANJAY_AGENT_SIGNING_KEY=boq-agent-012345678901');
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['HTTP_HOST']='boq.test.invalid';$_SERVER['REMOTE_ADDR']='127.0.0.48';
$nonce=bin2hex(random_bytes(7));
$files=[
 'market'=>$root.'/storage/tmp/boq-market-'.$nonce.'.json',
 'tenants'=>$root.'/storage/tmp/boq-tenant-'.$nonce.'.json',
 'sourcing'=>$root.'/storage/tmp/boq-sourcing-'.$nonce.'.json',
 'customers'=>$root.'/storage/tmp/boq-customers-'.$nonce.'.json',
];
putenv('ANANTA_TEST_MARKETPLACE_FILE='.$files['market']);
putenv('ANANTA_TEST_TENANT_FILE='.$files['tenants']);
putenv('ANANTA_TEST_SOURCING_FILE='.$files['sourcing']);
putenv('ANANTA_TEST_CUSTOMERS_FILE='.$files['customers']);
require $root.'/src/bootstrap.php';
$errors=[];$temps=[];
$assert=static function(bool $yes,string $msg)use(&$errors){if(!$yes)$errors[]=$msg;};
$csv=static function(string $text)use(&$temps):string{
 $f=tempnam(sys_get_temp_dir(),'ananta-boq-');
 if($f===false)throw new RuntimeException('No CSV fixture storage');
 file_put_contents($f,$text);$temps[]=$f;return $f;
};
try{
 $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'platform@boq.invalid','role'=>'admin','name'=>'QA platform'];
 $a=marketplace_supplier_save(['name'=>'Boq Alpha Nursery','city'=>'Pune','phone'=>'919000100001','status'=>'verified']);
 $b=marketplace_supplier_save(['name'=>'Boq Beta Nursery','city'=>'Solapur','phone'=>'919000100002','status'=>'verified']);
 $c=marketplace_supplier_save(['name'=>'Uninvited Nursery','city'=>'Pune','phone'=>'919000100003','status'=>'verified']);
 $tokenA=tenant_invite_issue($a,'alpha-boq@example.invalid','owner');
 $tokenB=tenant_invite_issue($b,'beta-boq@example.invalid','manager');
 $tokenC=tenant_invite_issue($c,'gamma-boq@example.invalid','inventory');
 unset($_SESSION['admin_user']);
 tenant_invite_accept($tokenA,'Alpha Buyer Sales','Alpha-BOQ-Pass-2026!');
 tenant_invite_accept($tokenB,'Beta Buyer Sales','Beta-BOQ-Pass-2026!');
 tenant_invite_accept($tokenC,'Gamma Inventory','Gamma-BOQ-Pass-2026!');
 $buyer=uuidv7();$other=uuidv7();
 confidential_json_write(commerce_customer_file(),[
   ['guid'=>$buyer,'name'=>'Private Buyer','email'=>'buyer-boq@qa.invalid','phone'=>'919999000011','status'=>'active'],
   ['guid'=>$other,'name'=>'Other Buyer','email'=>'other-boq@qa.invalid','status'=>'active'],
 ],'sanjaynursery:customers:v1');
 $_SESSION['customer_user']=['guid'=>$buyer,'name'=>'Private Buyer','email'=>'buyer-boq@qa.invalid'];
 $products=products_public();
 if(count($products)<2)throw new RuntimeException('Two canonical plants are required.');
 [$p1,$p2]=array_values(array_slice($products,0,2));
 $csvContents="product_slug,quantity,specification\n".$p1['slug'].",20,120 cm grade A\n".$p2['slug'].",15,10 inch pot\n";
 $file=$csv($csvContents);
 $stage=sourcing_boq_stage($file);
 $assert(count($stage['lines'])===2,'Two matching catalogue lines not resolved.');
 $assert(tenant_valid_id($stage['lines'][0]['id']),'BOQ row missing UUID.');
 $assert(sourcing_boq_pending()['id']===$stage['id'],'Buyer preview not found.');
 try{sourcing_boq_submit(['preview_token'=>'wrong','consent'=>'yes']);$errors[]='Invalid preview token accepted.';}catch(RuntimeException){}
 $input=['preview_token'=>$stage['token'],'consent'=>'yes','supplier_ids'=>[$a,$b],'destination'=>'Pune 412201','notes'=>'QA only'];
 $id=sourcing_boq_submit($input);
 $assert(tenant_valid_id($id),'BOQ ID invalid.');
 $record=sourcing_buyer_rfq($id);
 $assert(($record['type']??'')==='boq','RFQ is not BOQ type.');
 $assert(count($record['lines'])===2,'BOQ lines not persisted.');
 $assert(($record['purchase_order']??null)===null,'Created a PO without buyer approval.');
 $assert(($record['buyer_contact']??null)===null,'Buyer contact leaked without consent.');
 $assert(sourcing_boq_pending()===null,'Preview not invalidated after submit.');
 $encrypted=(string)file_get_contents($files['sourcing']);
 $assert(!str_contains($encrypted,'120 cm grade A')&&!str_contains($encrypted,'Pune 412201'),'BOQ data not encrypted at rest.');
 $_SESSION['customer_user']['guid']=$other;
 $assert(sourcing_buyer_rfq($id)===null,'Other buyer read BOQ.');
 try{sourcing_boq_approve_draft($id,$a,1,'approve_nonbinding_draft');$errors[]='Other buyer awarded BOQ.';}catch(RuntimeException){}
 $_SESSION['customer_user']['guid']=$buyer;
 $dup=$csv("product_slug,quantity,specification\n".$p1['slug'].",4,Grade A\n".$p1['slug'].",10,Grade A\n");
 try{sourcing_boq_parse_csv($dup);$errors[]='Duplicate BOQ line accepted.';}catch(InvalidArgumentException){}
 $unknown=$csv("product_slug,quantity,specification\nunknown-plant-abc,10,Grade A\n");
 try{sourcing_boq_parse_csv($unknown);$errors[]='Invented plant accepted.';}catch(InvalidArgumentException){}
 $many=$csv("product_slug,quantity,specification\n".str_repeat($p1['slug'].",1,Grade B\n",31));
 try{sourcing_boq_parse_csv($many);$errors[]='31 BOQ lines accepted.';}catch(InvalidArgumentException){}
 $missing=$csv("item,qty,grade\n".$p1['slug'].",10,Grade A\n");
 try{sourcing_boq_parse_csv($missing);$errors[]='Invalid CSV headers accepted.';}catch(InvalidArgumentException){}
 $assert(tenant_login('alpha-boq@example.invalid','Alpha-BOQ-Pass-2026!'),'Alpha login failed.');
 $view=sourcing_supplier_rfq($id,$a);
 $assert(count($view['lines'])===2,'Supplier missing selected BOQ lines.');
 $assert(!isset($view['recipients']),'Supplier sees other nursery IDs.');
 $assert(($view['buyer_contact']??null)===null,'Supplier sees non-consented contact.');
 $prices=[$stage['lines'][0]['id']=>'320',$stage['lines'][1]['id']=>'500'];
 sourcing_boq_quote($a,$id,['decision'=>'quoted','quote_version'=>'0','lead_days'=>'8','line_prices'=>$prices,'message'=>'Ready subject to stock check.']);
 $v1=sourcing_supplier_rfq($id,$a)['invitation'];
 $assert(($v1['quote']['total_inr']??0)===13900,'BOQ price aggregation incorrect.');
 $assert(count($v1['revisions'])===1&&$v1['quote_version']===1,'Initial quote version missing.');
 try{sourcing_supplier_respond($a,$id,['decision'=>'quoted','unit_price_inr'=>'1','lead_days'=>'1']);$errors[]='Legacy endpoint bypassed BOQ price validation.';}catch(RuntimeException){}
 try{sourcing_boq_quote($a,$id,['decision'=>'quoted','quote_version'=>'0','lead_days'=>'1','line_prices'=>$prices]);$errors[]='Stale quote revision accepted.';}catch(RuntimeException){}
 $revised=[$stage['lines'][0]['id']=>'300',$stage['lines'][1]['id']=>'480'];
 sourcing_boq_quote($a,$id,['decision'=>'quoted','quote_version'=>'1','lead_days'=>'10','line_prices'=>$revised,'message'=>'Revised grade pricing.']);
 $v2=sourcing_supplier_rfq($id,$a)['invitation'];
 $assert(count($v2['revisions'])===2&&$v2['quote_version']===2,'Quote revision history not retained.');
 $assert($v2['revisions'][0]['total_inr']===13900,'Previous quote snapshot changed.');
 tenant_logout();
 $assert(tenant_login('beta-boq@example.invalid','Beta-BOQ-Pass-2026!'),'Beta login failed.');
 $betaView=sourcing_supplier_rfq($id,$b);
 $assert(count($betaView['lines'])===2,'Beta did not get BOQ lines.');
 $assert(!str_contains(json_encode($betaView,JSON_THROW_ON_ERROR),'13900'),'Alpha price leaked to Beta.');
 sourcing_boq_quote($b,$id,['decision'=>'declined','quote_version'=>'0','message'=>'Not serviceable.']);
 tenant_logout();
 $assert(tenant_login('gamma-boq@example.invalid','Gamma-BOQ-Pass-2026!'),'Gamma login failed.');
 $assert(sourcing_supplier_rfq($id,$c)===null,'Uninvited supplier read BOQ.');
 try{sourcing_boq_quote($c,$id,['decision'=>'quoted','quote_version'=>'0','lead_days'=>'1','line_prices'=>$prices]);$errors[]='Inventory or uninvited user submitted quote.';}catch(RuntimeException){}
 tenant_logout();
 try{sourcing_boq_approve_draft($id,$a,1,'approve_nonbinding_draft');$errors[]='Stale buyer quote approval succeeded.';}catch(RuntimeException){}
 try{sourcing_boq_approve_draft($id,$a,2,'not_confirmed');$errors[]='PO approved without explicit consent.';}catch(InvalidArgumentException){}
 $po=sourcing_boq_approve_draft($id,$a,2,'approve_nonbinding_draft');
 $rfq=sourcing_buyer_rfq($id);
 $assert($rfq['status']==='buyer_approved_draft','BOQ not closed after draft approval.');
 $assert($rfq['purchase_order']['id']===$po,'Buyer-approved PO draft missing.');
 $assert($rfq['purchase_order']['binding']===false,'Non-binding draft misrepresented as legal PO.');
 $assert($rfq['purchase_order']['tax_freight_payment_unconfirmed']===true,'Unconfirmed taxes/payment flag missing.');
 $assert($rfq['purchase_order']['quote_version']===2,'Wrong quote revision used for award.');
 $assert(sourcing_boq_approve_draft($id,$a,2,'approve_nonbinding_draft')===$po,'Double-click duplicate PO generated.');
 try{sourcing_boq_approve_draft($id,$b,1,'approve_nonbinding_draft');$errors[]='Second supplier award accepted.';}catch(RuntimeException){}
 try{sourcing_buyer_close($id);$errors[]='Approved draft changed to closed.';}catch(RuntimeException){}
 $assert(tenant_login('alpha-boq@example.invalid','Alpha-BOQ-Pass-2026!'),'Alpha second login failed.');
 $awarded=sourcing_supplier_rfq($id,$a);
 $assert(($awarded['awarded_draft']['id']??'')===$po,'Awarded supplier not informed of selection.');
 try{sourcing_boq_quote($a,$id,['decision'=>'quoted','quote_version'=>'2','lead_days'=>'1','line_prices'=>$prices]);$errors[]='Quote changed after award.';}catch(RuntimeException){}
 tenant_logout();
 $assert(tenant_login('beta-boq@example.invalid','Beta-BOQ-Pass-2026!'),'Beta second login failed.');
 $assert(sourcing_supplier_rfq($id,$b)['awarded_draft']===null,'Winning supplier leaked to losing nursery.');
 tenant_logout();
} catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($temps as $temp)@unlink($temp);
foreach($files as $f)@unlink($f);
if($errors){fwrite(STDERR,"FAIL B4 BOQ: \n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: human-reviewed encrypted multi-line BOQ, CSV validation, buyer/tenant isolation, revision history, stale version denial and non-binding draft PO approval.\n";
