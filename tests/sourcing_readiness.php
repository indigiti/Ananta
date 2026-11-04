<?php
declare(strict_types=1);
/** B6 security and state-transition acceptance, isolated file-first fixtures. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('W',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=readiness-test');
putenv('SANJAY_AGENT_SIGNING_KEY=readiness-test-agent-01234567890');
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['HTTP_HOST']='qa.invalid';$_SERVER['REMOTE_ADDR']='127.0.0.84';
$suffix=bin2hex(random_bytes(7));
$paths=[
 'tenants'=>$root.'/storage/tmp/readiness-tenants-'.$suffix.'.json',
 'market'=>$root.'/storage/tmp/readiness-market-'.$suffix.'.json',
 'sourcing'=>$root.'/storage/tmp/readiness-rfqs-'.$suffix.'.json',
 'customers'=>$root.'/storage/tmp/readiness-buyers-'.$suffix.'.json',
];
putenv('ANANTA_TEST_TENANT_FILE='.$paths['tenants']);
putenv('ANANTA_TEST_MARKETPLACE_FILE='.$paths['market']);
putenv('ANANTA_TEST_SOURCING_FILE='.$paths['sourcing']);
putenv('ANANTA_TEST_CUSTOMERS_FILE='.$paths['customers']);
require $root.'/src/bootstrap.php';
$errors=[];$temporary=[];
$assert=static function(bool $yes,string $why)use(&$errors){if(!$yes)$errors[]=$why;};
$deny=static function(callable $test,string $why)use(&$errors){
 try{$test();$errors[]=$why;}catch(RuntimeException|InvalidArgumentException){}
};
try {
 $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'admin@qa.invalid','name'=>'Platform QA','role'=>'admin'];
 $a=marketplace_supplier_save(['name'=>'Planning Alpha Nursery','city'=>'Pune','phone'=>'919833300001','status'=>'verified']);
 $b=marketplace_supplier_save(['name'=>'Planning Beta Nursery','city'=>'Pune','phone'=>'919833300002','status'=>'verified']);
 $c=marketplace_supplier_save(['name'=>'Planning Uninvited Nursery','city'=>'Pune','phone'=>'919833300003','status'=>'verified']);
 $invA=tenant_invite_issue($a,'planning-alpha@example.invalid','owner');
 $invB=tenant_invite_issue($b,'planning-beta@example.invalid','manager');
 $invC=tenant_invite_issue($c,'planning-other@example.invalid','inventory');
 unset($_SESSION['admin_user']);
 tenant_invite_accept($invA,'Alpha Owner','Planning-Alpha-2026!');
 tenant_invite_accept($invB,'Beta Manager','Planning-Beta-2026!');
 tenant_invite_accept($invC,'Other Inventory','Planning-Other-2026!');
 $buyer=uuidv7();$other=uuidv7();
 confidential_json_write(commerce_customer_file(),[
   ['guid'=>$buyer,'name'=>'Planning Buyer','email'=>'buyer@qa.invalid','phone'=>'919000000088','status'=>'active'],
   ['guid'=>$other,'name'=>'Other Buyer','email'=>'other@qa.invalid','status'=>'active'],
 ],'sanjaynursery:customers:v1');
 $_SESSION['customer_user']=['guid'=>$buyer,'name'=>'Planning Buyer','email'=>'buyer@qa.invalid'];
 $products=products_public();
 if(count($products)<2)throw new RuntimeException('Two plant fixtures required.');
 [$p1,$p2]=array_values(array_slice($products,0,2));
 $file=tempnam(sys_get_temp_dir(),'readiness-boq-');$temporary[]=$file;
 file_put_contents($file,"product_slug,quantity,specification\n".$p1['slug'].",20,12 inch pot\n".$p2['slug'].",5,Grade B rooted\n");
 $stage=sourcing_boq_stage($file);
 $id=sourcing_boq_submit(['preview_token'=>$stage['token'],'consent'=>'yes','supplier_ids'=>[$a,$b],
    'destination'=>'Pune 412201','notes'=>'B6 readiness QA']);
 $deny(static fn()=>sourcing_readiness_buyer_decide($id,['decision'=>'acknowledge_planning',
    'readiness_version'=>'0','confirmation'=>'nonbinding_planning_only']),'Buyer acknowledged before a PO draft.');
 $assert(tenant_login('planning-alpha@example.invalid','Planning-Alpha-2026!'),'Alpha login failed.');
 $lines=sourcing_supplier_rfq($id,$a)['lines'];
 $assert(count($lines)===2,'Supplier did not receive commercial BOQ.');
 $prices=[$lines[0]['id']=>'200',$lines[1]['id']=>'600'];
 sourcing_boq_quote($a,$id,['decision'=>'quoted','quote_version'=>'0','lead_days'=>'7',
    'line_prices'=>$prices,'message'=>'Indicative quote']);
 tenant_logout();
 $poId=sourcing_boq_approve_draft($id,$a,1,'approve_nonbinding_draft');
 $po=sourcing_buyer_rfq($id)['purchase_order'];
 $assert(($po['binding']??null)===false,'B6 modified non-binding order safety flag.');
 $assert(sourcing_readiness_view($po)['status']==='awaiting_supplier','Initial readiness is not awaiting supplier.');
 $assert(sourcing_readiness_view($po)['version']===0,'Initial readiness version not zero.');
 $deny(static fn()=>sourcing_readiness_supplier_submit($a,$id,[]),'Anonymous supplier was allowed to respond.');
 $assert(tenant_login('planning-beta@example.invalid','Planning-Beta-2026!'),'Beta login failed.');
 $assert(sourcing_supplier_rfq($id,$b)['awarded_draft']===null,'Losing supplier saw selected draft.');
 $deny(static fn()=>sourcing_readiness_supplier_submit($b,$id,
   ['decision'=>'decline','readiness_version'=>'0','comment'=>'Cannot fulfil']), 'Losing supplier responded to selected draft.');
 tenant_logout();
 $assert(tenant_login('planning-other@example.invalid','Planning-Other-2026!'),'Inventory login failed.');
 $assert(sourcing_supplier_rfq($id,$c)===null,'Uninvited nursery saw private RFQ.');
 $deny(static fn()=>sourcing_readiness_supplier_submit($c,$id,
   ['decision'=>'decline','readiness_version'=>'0','comment'=>'No stock here']), 'Inventory/uninvited account accepted a PO readiness decision.');
 tenant_logout();
 $assert(tenant_login('planning-alpha@example.invalid','Planning-Alpha-2026!'),'Winning supplier login failed.');
 $alpha=sourcing_supplier_rfq($id,$a);
 $assert(($alpha['awarded_draft']['id']??'')===$poId,'Winning supplier cannot see its own selected draft.');
 $submit=[
   'decision'=>'propose','readiness_version'=>'0',
   'available_quantities'=>[$lines[0]['id']=>'15',$lines[1]['id']=>'5'],
   'dispatch_days'=>'8','freight_inr'=>'1500','delivery_mode'=>'supplier_delivery',
   'tax_treatment'=>'unconfirmed','comment'=>'Five units short in row one.',
 ];
 $deny(static fn()=>sourcing_readiness_supplier_submit($a,$id,array_replace($submit,[
    'available_quantities'=>[$lines[0]['id']=>'15']
 ])),'Incomplete per-line stock statement accepted.');
 $deny(static fn()=>sourcing_readiness_supplier_submit($a,$id,array_replace($submit,[
    'delivery_mode'=>'invalid-carrier'
 ])),'Invalid delivery responsibility accepted.');
 $deny(static fn()=>sourcing_readiness_supplier_submit($a,$id,array_replace($submit,[
    'freight_inr'=>'-15'
 ])),'Negative freight cost accepted.');
 sourcing_readiness_supplier_submit($a,$id,$submit);
 $status=sourcing_supplier_rfq($id,$a)['awarded_draft']['readiness'];
 $assert($status['status']==='supplier_proposed'&&$status['version']===1,'Initial supplier proposal status/version wrong.');
 $assert($status['proposal']['all_lines_reported_sufficient']===false,'Stock shortfall incorrectly treated as full coverage.');
 $assert($status['proposal']['line_availability'][$lines[0]['id']]['reported_available']===15,'Per-line stock statement lost.');
 $assert(!isset($status['events'][0]['actor_id']),'Buyer/supplier GUID leaked via supplier timeline.');
 $deny(static fn()=>sourcing_readiness_supplier_submit($a,$id,$submit),'Stale supplier readiness version accepted.');
 tenant_logout();
 $_SESSION['customer_user']['guid']=$other;
 $deny(static fn()=>sourcing_readiness_buyer_decide($id,[
  'decision'=>'request_changes','readiness_version'=>'1','note'=>'Check stock again',
 ]),'Other buyer updated private planning draft.');
 $_SESSION['customer_user']['guid']=$buyer;
 $deny(static fn()=>sourcing_readiness_buyer_decide($id,[
  'decision'=>'acknowledge_planning','readiness_version'=>'1',
  'confirmation'=>'nonbinding_planning_only','note'=>'',
 ]),'Buyer acknowledged insufficient stock.');
 $deny(static fn()=>sourcing_readiness_buyer_decide($id,[
  'decision'=>'acknowledge_planning','readiness_version'=>'0',
  'confirmation'=>'nonbinding_planning_only','note'=>'',
 ]),'Stale buyer version accepted.');
 sourcing_readiness_buyer_decide($id,[
  'decision'=>'request_changes','readiness_version'=>'1',
  'note'=>'Please reconfirm all 20 units of the first variety.',
 ]);
 $planning=sourcing_readiness_view(sourcing_buyer_rfq($id)['purchase_order']);
 $assert($planning['status']==='buyer_changes_requested'&&$planning['version']===2,'Buyer clarification state wrong.');
 $assert(count($planning['events'])===2,'Readiness event history incomplete.');
 $assert(tenant_login('planning-alpha@example.invalid','Planning-Alpha-2026!'),'Supplier re-login failed.');
 $back=sourcing_supplier_rfq($id,$a)['awarded_draft']['readiness'];
 $assert(($back['events'][1]['note']??'')==='Please reconfirm all 20 units of the first variety.',
     'Buyer clarification not available in private supplier timeline.');
 $next=$submit;
 $next['readiness_version']='2';
 $next['available_quantities'][$lines[0]['id']]='20';
 $next['freight_inr']='1300';
 $next['comment']='All quantities reportedly available, still unreserved.';
 sourcing_readiness_supplier_submit($a,$id,$next);
 $status=sourcing_supplier_rfq($id,$a)['awarded_draft']['readiness'];
 $assert($status['proposal']['all_lines_reported_sufficient']===true,'All-line coverage not recomputed.');
 $assert($status['version']===3,'Second supplier version incorrect.');
 tenant_logout();
 $deny(static fn()=>sourcing_readiness_buyer_decide($id,[
   'decision'=>'acknowledge_planning','readiness_version'=>'3','note'=>'',
 ]),'Buyer acknowledgement without explicit consent accepted.');
 sourcing_readiness_buyer_decide($id,[
  'decision'=>'acknowledge_planning','readiness_version'=>'3',
  'confirmation'=>'nonbinding_planning_only','note'=>'Terms understood for planning only.',
 ]);
 $rfq=sourcing_buyer_rfq($id);$planning=sourcing_readiness_view($rfq['purchase_order']);
 $assert($planning['status']==='buyer_acknowledged'&&$planning['version']===4,'Buyer planning acknowledgement not persisted.');
 $assert(($rfq['purchase_order']['binding']??true)===false,'Acknowledgement turned draft into binding order.');
 $assert(($rfq['purchase_order']['tax_freight_payment_unconfirmed']??false)===true,'PO disclosure flag disappeared.');
 $assert($rfq['status']==='buyer_approved_draft','Supplier readiness changed original RFQ/order state.');
 $assert(count($planning['events'])===4,'Readiness events not appended in order.');
 $storage=(string)file_get_contents($paths['sourcing']);
 $assert(!str_contains($storage,'Terms understood for planning only.'),'Buyer note stored without encryption.');
 $deny(static fn()=>sourcing_readiness_buyer_decide($id,[
   'decision'=>'request_changes','readiness_version'=>'4','note'=>'Cannot edit terminal acknowledgement',
 ]),'Terminal acknowledgement was reopened by buyer.');
 $assert(tenant_login('planning-alpha@example.invalid','Planning-Alpha-2026!'),'Supplier final login failed.');
 $deny(static fn()=>sourcing_readiness_supplier_submit($a,$id,array_replace($next,['readiness_version'=>'4'])),
   'Supplier mutated buyer-acknowledged planning terms.');
 tenant_logout();
 // Separate decline path confirms no follow-on binding workflow.
 $stage=sourcing_boq_stage($file);
 $second=sourcing_boq_submit(['preview_token'=>$stage['token'],'consent'=>'yes','supplier_ids'=>[$a],
     'destination'=>'Pune 412201','notes'=>'Decline flow']);
 $assert(tenant_login('planning-alpha@example.invalid','Planning-Alpha-2026!'),'Supplier decline quote login failed.');
 $secondLines=sourcing_supplier_rfq($second,$a)['lines'];
 sourcing_boq_quote($a,$second,['decision'=>'quoted','quote_version'=>'0','lead_days'=>'5',
    'line_prices'=>[$secondLines[0]['id']=>'200',$secondLines[1]['id']=>'600'],'message'=>'Decline test quote']);
 tenant_logout();
 sourcing_boq_approve_draft($second,$a,1,'approve_nonbinding_draft');
 $assert(tenant_login('planning-alpha@example.invalid','Planning-Alpha-2026!'),'Supplier decline login failed.');
 sourcing_readiness_supplier_submit($a,$second,['decision'=>'decline','readiness_version'=>'0',
   'comment'=>'This nursery cannot source the full grade required.']);
 $assert(sourcing_readiness_view(sourcing_buyer_rfq($second)['purchase_order'])['status']==='supplier_declined','Decline state did not persist.');
 $deny(static fn()=>sourcing_readiness_supplier_submit($a,$second,[
   'decision'=>'decline','readiness_version'=>'1','comment'=>'Repeated decline'
 ]),'Supplier changed terminal decline decision.');
 tenant_logout();
}catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($temporary as $p)@unlink($p);
foreach($paths as $p)@unlink($p);
if($errors){fwrite(STDERR,"FAIL B6 readiness:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: private supplier readiness, per-line stock shortfalls, freight/tax disclosures, version checks, buyer change requests, terminal planning acknowledgement, decline and encrypted event history.\n";
