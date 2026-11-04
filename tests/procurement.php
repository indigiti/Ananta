<?php
declare(strict_types=1);
$root=dirname(__DIR__);putenv('APP_ENV=staging');putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('P',32)));putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=procurement-test-secret');$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_URI']='/';$_SERVER['SCRIPT_NAME']='/index.php';require $root.'/src/bootstrap.php';
$errors=[];function pcheck(bool $ok,string $msg):void{global $errors;if(!$ok)$errors[]=$msg;}
@unlink(procurement_file());@unlink(makeos_job_file());$_SESSION['admin_user']=['guid'=>'019-test-admin','email'=>'admin@example.invalid','name'=>'Admin','role'=>'admin'];
try{
$s=procurement_supplier_save(['name'=>'Ash Timber Co','email'=>'supply@example.invalid','lead_days'=>5]);pcheck(!empty($s['guid']),'Supplier save failed.');
$m=procurement_material_save(['code'=>'ASH-25','name'=>'Ash board 25 mm','type'=>'MATERIAL','unit'=>'sq.ft','reorder_level'=>5,'preferred_supplier_guid'=>$s['guid']]);pcheck(($m['code']??'')==='ASH-25','Material save failed.');
$po=procurement_po_create($s['guid'],[['material_guid'=>$m['guid'],'qty'=>10,'unit_cost'=>500]],'Regression PO');pcheck(($po['status']??'')==='DRAFT'&&$po['total']===5000,'PO creation failed.');
$po=procurement_po_issue($po['guid']);pcheck($po['status']==='ISSUED','PO issue failed.');
$grn=procurement_grn_receive($po['guid'],[$po['lines'][0]['guid']=>6],'CH-001');pcheck(count($grn['lines'])===1,'GRN receive failed.');$po=procurement_po_by_guid($po['guid']);pcheck($po['status']==='PART_RECEIVED','Partial receipt status failed.');
$grn2=procurement_grn_receive($po['guid'],[$po['lines'][0]['guid']=>4],'CH-002');$po=procurement_po_by_guid($po['guid']);pcheck($po['status']==='RECEIVED','Full receipt status failed.');
$stock=procurement_stock_summary();pcheck(abs($stock[0]['available']-10)<0.0001,'Stock ledger quantity mismatch.');
$job=['guid'=>uuidv7(),'number'=>'AWJ-TEST-001','source_type'=>'ORDER_ITEM','source_key'=>'TEST:1','source_fingerprint'=>'abc','status'=>'PLANNED','bom_locked'=>true,'bom_fingerprint'=>'x','bom'=>[['guid'=>uuidv7(),'type'=>'MATERIAL','name'=>'Ash','spec'=>'','qty'=>1,'unit'=>'sq.ft','waste_percent'=>0,'unit_cost'=>500,'line_cost'=>500]],'stages'=>[],'timeline'=>[],'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];$job['bom_fingerprint']=makeos_bom_hash($job['bom']);commerce_private_mutate(makeos_job_file(),makeos_job_context(),fn($rows)=>[$job],'test.synthetic.job');
$r=procurement_reserve_for_job($job['guid'],$m['guid'],7);pcheck(abs($r['qty']-7)<0.001&&count($r['allocations'])===2,'FIFO lot reservation failed.');$stock=procurement_stock_summary();pcheck(abs($stock[0]['available']-3)<0.001&&abs($stock[0]['reserved']-7)<0.001,'Reservation did not update lots.');
$v=procurement_job_cost_variance($job['guid']);pcheck($v['actual']===3500&&$v['variance']===3000,'Cost variance incorrect.');$c=procurement_consume_job_reservations($job['guid']);pcheck($c['count']===1,'Consumption failed.');$store=procurement_store();$cons=array_sum(array_column($store['lots'],'qty_consumed'));pcheck(abs($cons-7)<0.001,'Lot consumption incorrect.');
try{procurement_reserve_for_job($job['guid'],$m['guid'],4);$errors[]='Insufficient stock was not rejected.';}catch(Throwable){}
$checks=procurement_release_checks();foreach($checks as $c)pcheck(!empty($c['ok']),'Release check failed: '.$c['name']);$raw=(string)file_get_contents(procurement_file());pcheck(!str_contains($raw,'Ash Timber Co')&&!str_contains($raw,'purchase_orders'),'Procurement plaintext leaked.');
}catch(Throwable $e){$errors[]='Unhandled: '.$e->getMessage();}
if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);}echo "PASS: supplier/material masters, PO issue, partial/full GRN, lot creation, FIFO reservation, stock invariants, consumption, cost variance and encrypted persistence verified.\n";
