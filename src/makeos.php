<?php
declare(strict_types=1);

/**
 * Sanjay Nursery MakeOS — v0.7.0
 * Frozen Design -> BOM -> material reservation -> workshop stages -> QA -> packing -> ready dispatch.
 */

function makeos_root(): string { return data_root() . '/private-domain/makeos'; }
function makeos_job_file(): string { return makeos_root() . '/jobs.json'; }
function makeos_job_context(): string { return 'sanjaynursery:makeos-jobs:v1'; }
function makeos_job_number(): string { return 'AWJ-'.gmdate('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8)); }
function makeos_stages(): array { return ['MATERIAL_PREP','CUTTING','JOINERY','SANDING','FINISHING','ASSEMBLY','QA','PACKING']; }
function makeos_stage_label(string $stage): string { return ucwords(strtolower(str_replace('_',' ',$stage))); }
function makeos_status_label(string $status): string { return ucwords(strtolower(str_replace('_',' ',$status))); }
function makeos_ensure_dirs(): void {
    $dir=makeos_root();
    if(!is_dir($dir) && !@mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Unable to create MakeOS storage directory.');
    @chmod($dir,0750);
}
function makeos_jobs(): array {
    makeos_ensure_dirs();
    try { return commerce_private_read(makeos_job_file(), makeos_job_context(), []); } catch(Throwable) { return []; }
}
function makeos_job_by_guid(string $guid): ?array { foreach(makeos_jobs() as $j) if(($j['guid']??'')===$guid) return $j; return null; }
function makeos_job_for_quote(string $quoteGuid): ?array { foreach(makeos_jobs() as $j) if(($j['source_quote_guid']??'')===$quoteGuid) return $j; return null; }
function makeos_source_label(array $j): string { return ($j['source_type']??'')==='ORDER_ITEM' ? (string)($j['source_order_number']??'Order') . ' · item ' . ((int)($j['source_item_index']??0)+1) : (string)($j['source_quote_number']??'Bespoke'); }
function makeos_job_for_order_item(string $orderGuid,int $itemIndex): ?array { $key='ORDER_ITEM:'.$orderGuid.':'.$itemIndex;foreach(makeos_jobs() as $j)if(($j['source_key']??'')===$key)return$j;return null; }
function makeos_jobs_for_order(string $orderGuid): array { return array_values(array_filter(makeos_jobs(),fn($j)=>($j['source_order_guid']??'')===$orderGuid)); }
function makeos_order_item_fingerprint(array $order,int $itemIndex): string { $item=$order['items'][$itemIndex]??null;if(!$item)throw new RuntimeException('Order item not found.');$stable=['order_guid'=>$order['guid']??'','order_number'=>$order['number']??'','item_index'=>$itemIndex,'item'=>$item];return hash('sha256',json_encode($stable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
function makeos_open_jobs(?string $status=null): array {
    $rows=makeos_jobs();
    if($status!==null && $status!=='') $rows=array_values(array_filter($rows,fn($j)=>($j['status']??'')===$status));
    usort($rows,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));
    return $rows;
}
function makeos_open_count(): int { return count(array_filter(makeos_jobs(),fn($j)=>!in_array($j['status']??'',['READY_TO_DISPATCH','CANCELLED'],true))); }

function makeos_freeze_integrity(array $quote): bool {
    $f=$quote['freeze']??null; if(!$f||empty($f['snapshot'])||empty($f['fingerprint']))return false;
    $calc=hash('sha256',json_encode($f['snapshot'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    return hash_equals((string)$f['fingerprint'],$calc);
}
function makeos_bom_hash(array $bom): string {
    $stable=[];
    foreach($bom as $line){$stable[]=[
        'guid'=>$line['guid']??'','type'=>$line['type']??'','name'=>$line['name']??'','spec'=>$line['spec']??'',
        'qty'=>(float)($line['qty']??0),'unit'=>$line['unit']??'','waste_percent'=>(float)($line['waste_percent']??0),
        'unit_cost'=>(int)($line['unit_cost']??0),'line_cost'=>(int)($line['line_cost']??0),
    ];}
    return hash('sha256',json_encode($stable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function makeos_seed_bom(array $quote): array {
    $r=bespoke_approved_revision($quote); if(!$r)return[];
    $lines=[];
    $material=trim((string)($r['material']??''));$finish=trim((string)($r['finish']??''));
    if($material!=='')$lines[]=['guid'=>uuidv7(),'type'=>'MATERIAL','name'=>$material,'spec'=>trim((string)($r['dimensions']??'')),'qty'=>1.0,'unit'=>'job lot','waste_percent'=>10.0,'unit_cost'=>0,'line_cost'=>0];
    if($finish!=='')$lines[]=['guid'=>uuidv7(),'type'=>'FINISH','name'=>$finish,'spec'=>'Approved Design Freeze finish system','qty'=>1.0,'unit'=>'job lot','waste_percent'=>5.0,'unit_cost'=>0,'line_cost'=>0];
    $lines[]=['guid'=>uuidv7(),'type'=>'HARDWARE','name'=>'Fixing / hardware kit','spec'=>'Confirm against approved drawing and wall substrate','qty'=>1.0,'unit'=>'set','waste_percent'=>0.0,'unit_cost'=>0,'line_cost'=>0];
    $lines[]=['guid'=>uuidv7(),'type'=>'PACKAGING','name'=>'Protective packing set','spec'=>'Edge, surface and moisture protection appropriate to finished piece','qty'=>1.0,'unit'=>'set','waste_percent'=>0.0,'unit_cost'=>0,'line_cost'=>0];
    return $lines;
}
function makeos_stage_records(): array { $out=[];foreach(makeos_stages() as $s)$out[]=['stage'=>$s,'status'=>'PENDING','started_at'=>null,'completed_at'=>null,'completed_by'=>null,'note'=>''];return$out; }

function makeos_create_from_frozen_quote(string $quoteGuid): array {
    require_admin();
    $existing=makeos_job_for_quote($quoteGuid); if($existing)return$existing;
    $quote=bespoke_quote_by_guid($quoteGuid); if(!$quote)throw new RuntimeException('Bespoke quote not found.');
    if(($quote['status']??'')!=='DESIGN_FROZEN'||empty($quote['freeze']))throw new RuntimeException('Design Freeze is required before production handoff.');
    if(!makeos_freeze_integrity($quote))throw new RuntimeException('Design Freeze integrity check failed.');
    $approved=bespoke_approved_revision($quote);if(!$approved)throw new RuntimeException('Approved design revision is missing.');
    $bom=makeos_seed_bom($quote);$now=gmdate('c');
    $job=[
        'guid'=>uuidv7(),'number'=>makeos_job_number(),'source_type'=>'BESPOKE_FREEZE','source_key'=>'BESPOKE:'.$quote['guid'],'source_fingerprint'=>$quote['freeze']['fingerprint'],'source_quote_guid'=>$quote['guid'],'source_quote_number'=>$quote['number'],
        'source_freeze_guid'=>$quote['freeze']['guid'],'source_freeze_number'=>$quote['freeze']['number'],'source_freeze_fingerprint'=>$quote['freeze']['fingerprint'],
        'source_snapshot'=>$quote['freeze']['snapshot'],'product_snapshot'=>$quote['product_snapshot'],'customer_guid'=>$quote['customer_guid'],
        'status'=>'PLANNED','current_stage'=>null,'bom'=>$bom,'bom_locked'=>false,'bom_fingerprint'=>null,'bom_locked_at'=>null,'bom_locked_by'=>null,
        'materials'=>['status'=>'NOT_RESERVED','reserved_at'=>null,'reserved_by'=>null,'note'=>''],
        'workshop'=>['artisan'=>'','station'=>'','priority'=>'NORMAL','planned_start'=>'','due_date'=>'','instructions'=>''],
        'stages'=>makeos_stage_records(),'qa'=>['status'=>'NOT_READY','checked_at'=>null,'checked_by'=>null,'checks'=>[],'note'=>'','rework_stage'=>null],
        'packing'=>['status'=>'NOT_READY','packed_at'=>null,'packed_by'=>null,'note'=>''],
        'timeline'=>[['ts'=>$now,'event'=>'PRODUCTION_JOB_CREATED','actor'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>'Production job created from immutable Design Freeze '.$quote['freeze']['number'].'.']],
        'created_at'=>$now,'updated_at'=>$now,
    ];
    commerce_private_mutate(makeos_job_file(),makeos_job_context(),function(array $rows)use($job){foreach($rows as $x){if(($x['source_quote_guid']??'')===$job['source_quote_guid'])return$rows;if(($x['number']??'')===$job['number'])throw new RuntimeException('Production job number collision.');}$rows[]=$job;return$rows;},'makeos.job.create');
    commerce_audit('makeos.job.create',['job_guid'=>$job['guid'],'quote_guid'=>$quoteGuid,'freeze_guid'=>$job['source_freeze_guid']]);
    return makeos_job_by_guid($job['guid'])??$job;
}

function makeos_seed_bom_from_order_item(array $item): array {
    $qty=max(1,(int)($item['qty']??1));$lines=[];$material=trim((string)($item['material']??''));$finish=trim((string)($item['finish']??''));
    if($material!=='')$lines[]=['guid'=>uuidv7(),'type'=>'MATERIAL','name'=>$material,'spec'=>'Order item material snapshot','qty'=>(float)$qty,'unit'=>'piece lot','waste_percent'=>10.0,'unit_cost'=>0,'line_cost'=>0];
    if($finish!=='')$lines[]=['guid'=>uuidv7(),'type'=>'FINISH','name'=>$finish,'spec'=>'Order item finish snapshot','qty'=>(float)$qty,'unit'=>'piece lot','waste_percent'=>5.0,'unit_cost'=>0,'line_cost'=>0];
    $lines[]=['guid'=>uuidv7(),'type'=>'HARDWARE','name'=>'Product hardware / fixing kit','spec'=>'Verify against SKU and configuration snapshot','qty'=>(float)$qty,'unit'=>'set','waste_percent'=>0.0,'unit_cost'=>0,'line_cost'=>0];
    $lines[]=['guid'=>uuidv7(),'type'=>'PACKAGING','name'=>'Protective packing set','spec'=>'Per-order item transport protection','qty'=>(float)$qty,'unit'=>'set','waste_percent'=>0.0,'unit_cost'=>0,'line_cost'=>0];
    return $lines;
}
function makeos_create_from_order_item(string $orderGuid,int $itemIndex): array {
    require_admin();$existing=makeos_job_for_order_item($orderGuid,$itemIndex);if($existing)return$existing;$order=admin_order($orderGuid);if(!$order)throw new RuntimeException('Order not found.');if(!in_array($order['status']??'',['PAID','CONFIRMED','IN_PRODUCTION'],true))throw new RuntimeException('Order must be paid/confirmed before manufacturing handoff.');$item=$order['items'][$itemIndex]??null;if(!$item)throw new RuntimeException('Order item not found.');$fingerprint=makeos_order_item_fingerprint($order,$itemIndex);$now=gmdate('c');$job=['guid'=>uuidv7(),'number'=>makeos_job_number(),'source_type'=>'ORDER_ITEM','source_key'=>'ORDER_ITEM:'.$orderGuid.':'.$itemIndex,'source_fingerprint'=>$fingerprint,'source_order_guid'=>$orderGuid,'source_order_number'=>$order['number'],'source_item_index'=>$itemIndex,'source_quote_guid'=>null,'source_quote_number'=>null,'source_freeze_guid'=>null,'source_freeze_number'=>null,'source_freeze_fingerprint'=>null,'source_snapshot'=>['order_guid'=>$orderGuid,'order_number'=>$order['number'],'item_index'=>$itemIndex,'item'=>$item],'product_snapshot'=>['name'=>$item['name']??'','sku'=>$item['sku']??'','image'=>$item['image']??'','base_price'=>$item['unit_price']??0],'customer_guid'=>$order['customer_guid'],'status'=>'PLANNED','current_stage'=>null,'bom'=>makeos_seed_bom_from_order_item($item),'bom_locked'=>false,'bom_fingerprint'=>null,'bom_locked_at'=>null,'bom_locked_by'=>null,'materials'=>['status'=>'NOT_RESERVED','reserved_at'=>null,'reserved_by'=>null,'note'=>''],'workshop'=>['artisan'=>'','station'=>'','priority'=>'NORMAL','planned_start'=>'','due_date'=>'','instructions'=>''],'stages'=>makeos_stage_records(),'qa'=>['status'=>'NOT_READY','checked_at'=>null,'checked_by'=>null,'checks'=>[],'note'=>'','rework_stage'=>null],'packing'=>['status'=>'NOT_READY','packed_at'=>null,'packed_by'=>null,'note'=>''],'timeline'=>[['ts'=>$now,'event'=>'PRODUCTION_JOB_CREATED','actor'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>'Production job created from immutable order item snapshot '.$order['number'].' / item '.($itemIndex+1).'.']],'created_at'=>$now,'updated_at'=>$now];
    commerce_private_mutate(makeos_job_file(),makeos_job_context(),function(array $rows)use($job){foreach($rows as $x){if(($x['source_key']??'')===$job['source_key'])return$rows;if(($x['number']??'')===$job['number'])throw new RuntimeException('Production job number collision.');}$rows[]=$job;return$rows;},'makeos.job.create_order_item');commerce_audit('makeos.job.create_order_item',['job_guid'=>$job['guid'],'order_guid'=>$orderGuid,'item_index'=>$itemIndex]);return makeos_job_by_guid($job['guid'])??$job;
}

function makeos_mutate_job(string $jobGuid, callable $fn, string $reason): array {
    $updated=null;
    commerce_private_mutate(makeos_job_file(),makeos_job_context(),function(array $rows)use($jobGuid,$fn,&$updated){foreach($rows as &$j){if(($j['guid']??'')!==$jobGuid)continue;$next=$fn($j);if(!is_array($next))throw new RuntimeException('Invalid MakeOS mutation.');$next['updated_at']=gmdate('c');$j=$next;$updated=$next;break;}return$rows;},$reason);
    if(!$updated)throw new RuntimeException('Production job not found.');return$updated;
}
function makeos_require_bom_editable(array $j): void { if(!empty($j['bom_locked'])||($j['status']??'')!=='PLANNED')throw new RuntimeException('BOM is locked for this job.'); }
function makeos_add_bom_line(string $jobGuid,array $input): array {
    require_admin();return makeos_mutate_job($jobGuid,function(array $j)use($input){makeos_require_bom_editable($j);$type=strtoupper(trim((string)($input['type']??'MATERIAL')));$allowed=['MATERIAL','HARDWARE','FINISH','LABOUR','PACKAGING','OTHER'];if(!in_array($type,$allowed,true))$type='OTHER';$name=trim((string)($input['name']??''));$qty=(float)($input['qty']??0);$unit=trim((string)($input['unit']??''));$waste=max(0,min(100,(float)($input['waste_percent']??0)));$unitCost=max(0,(int)($input['unit_cost']??0));if(text_len($name)<2||$qty<=0||$unit==='')throw new RuntimeException('BOM line needs a name, positive quantity and unit.');$effective=$qty*(1+$waste/100);$line=['guid'=>uuidv7(),'type'=>$type,'name'=>$name,'spec'=>trim((string)($input['spec']??'')),'qty'=>$qty,'unit'=>$unit,'waste_percent'=>$waste,'unit_cost'=>$unitCost,'line_cost'=>(int)round($effective*$unitCost)];$j['bom'][]=$line;$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'BOM_LINE_ADDED','actor'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>$name];return$j;},'makeos.bom.add');
}
function makeos_delete_bom_line(string $jobGuid,string $lineGuid): array {
    require_admin();return makeos_mutate_job($jobGuid,function(array $j)use($lineGuid){makeos_require_bom_editable($j);$before=count($j['bom']??[]);$j['bom']=array_values(array_filter($j['bom']??[],fn($x)=>($x['guid']??'')!==$lineGuid));if(count($j['bom'])===$before)throw new RuntimeException('BOM line not found.');$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'BOM_LINE_REMOVED','actor'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>'BOM line removed before lock.'];return$j;},'makeos.bom.delete');
}
function makeos_lock_bom(string $jobGuid): array {
    require_admin();return makeos_mutate_job($jobGuid,function(array $j){makeos_require_bom_editable($j);if(empty($j['bom']))throw new RuntimeException('Add at least one BOM line before locking.');$j['bom_fingerprint']=makeos_bom_hash($j['bom']);$j['bom_locked']=true;$j['bom_locked_at']=gmdate('c');$j['bom_locked_by']='admin:'.(admin_identity()['guid']??'unknown');$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'BOM_LOCKED','actor'=>$j['bom_locked_by'],'note'=>'BOM locked with fingerprint '.substr($j['bom_fingerprint'],0,16).'…'];return$j;},'makeos.bom.lock');
}
function makeos_update_assignment(string $jobGuid,array $input): array {
    require_admin();return makeos_mutate_job($jobGuid,function(array $j)use($input){if(in_array($j['status']??'', ['READY_TO_DISPATCH','CANCELLED'],true))throw new RuntimeException('Closed job cannot be reassigned.');$priority=strtoupper(trim((string)($input['priority']??'NORMAL')));if(!in_array($priority,['LOW','NORMAL','HIGH','URGENT'],true))$priority='NORMAL';$j['workshop']=['artisan'=>trim((string)($input['artisan']??'')),'station'=>trim((string)($input['station']??'')),'priority'=>$priority,'planned_start'=>trim((string)($input['planned_start']??'')),'due_date'=>trim((string)($input['due_date']??'')),'instructions'=>trim((string)($input['instructions']??''))];$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'WORKSHOP_ASSIGNMENT_UPDATED','actor'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>trim($j['workshop']['artisan'].' · '.$j['workshop']['station'],' ·')];return$j;},'makeos.workshop.assignment');
}
function makeos_reserve_materials(string $jobGuid,string $note=''): array {
    require_admin();return makeos_mutate_job($jobGuid,function(array $j)use($note){if(empty($j['bom_locked']))throw new RuntimeException('Lock the BOM before reserving materials.');if(!hash_equals((string)($j['bom_fingerprint']??''),makeos_bom_hash($j['bom']??[])))throw new RuntimeException('BOM integrity check failed.');if(($j['status']??'')!=='PLANNED')throw new RuntimeException('Materials can only be reserved for a planned job.');$j['materials']=['status'=>'RESERVED','reserved_at'=>gmdate('c'),'reserved_by'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>trim($note)];$j['status']='MATERIALS_RESERVED';$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'MATERIALS_RESERVED','actor'=>$j['materials']['reserved_by'],'note'=>trim($note)?:'BOM requirements reserved for production.'];return$j;},'makeos.materials.reserve');
}
function makeos_start_job(string $jobGuid): array {
    require_admin();$before=makeos_job_by_guid($jobGuid);if(!$before)throw new RuntimeException('Production job not found.');if(function_exists('procurement_materials')&&count(procurement_materials())>0&&count(procurement_reservations_for_job($jobGuid))===0)throw new RuntimeException('Reserve actual inventory lots before starting production.');if(($before['source_type']??'')==='ORDER_ITEM'){$o=order_by_guid((string)($before['source_order_guid']??''));if(!$o||!in_array($o['status']??'',['CONFIRMED','IN_PRODUCTION'],true))throw new RuntimeException('Confirm the order before starting its workshop job.');}
    $job=makeos_mutate_job($jobGuid,function(array $j){if(($j['status']??'')!=='MATERIALS_RESERVED')throw new RuntimeException('Reserve materials before starting production.');if(empty(trim((string)($j['workshop']['artisan']??'')))&&empty(trim((string)($j['workshop']['station']??''))))throw new RuntimeException('Assign an artisan or workshop station before starting.');$stages=$j['stages']??makeos_stage_records();$stages[0]['status']='ACTIVE';$stages[0]['started_at']=gmdate('c');$j['stages']=$stages;$j['current_stage']=$stages[0]['stage'];$j['status']='IN_PRODUCTION';$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'PRODUCTION_STARTED','actor'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>'Workshop production started.'];return$j;},'makeos.production.start');
    if(function_exists('procurement_consume_job_reservations')&&count(procurement_reservations_for_job($jobGuid))>0)procurement_consume_job_reservations($jobGuid);
    if(($job['source_type']??'')==='ORDER_ITEM'){$o=order_by_guid((string)$job['source_order_guid']);if($o&&($o['status']??'')==='CONFIRMED')order_transition($o['guid'],'IN_PRODUCTION','makeos','Workshop production started.');}
    return $job;
}
function makeos_complete_stage(string $jobGuid,string $stage,string $note=''): array {
    require_admin();$stage=strtoupper(trim($stage));$job=makeos_mutate_job($jobGuid,function(array $j)use($stage,$note){if(($j['status']??'')!=='IN_PRODUCTION')throw new RuntimeException('Job is not in active production.');if(($j['current_stage']??'')!==$stage)throw new RuntimeException('Only the active stage can be completed.');if($stage==='QA')throw new RuntimeException('Use the QA inspection action for the QA stage.');$idx=null;foreach($j['stages'] as $i=>$row)if(($row['stage']??'')===$stage){$idx=$i;break;}if($idx===null)throw new RuntimeException('Unknown production stage.');$j['stages'][$idx]['status']='COMPLETE';$j['stages'][$idx]['completed_at']=gmdate('c');$j['stages'][$idx]['completed_by']='admin:'.(admin_identity()['guid']??'unknown');$j['stages'][$idx]['note']=trim($note);$next=$j['stages'][$idx+1]??null;if(!$next){$j['current_stage']=null;$j['status']='READY_TO_DISPATCH';}else{$j['stages'][$idx+1]['status']='ACTIVE';$j['stages'][$idx+1]['started_at']=gmdate('c');$j['current_stage']=$next['stage'];}$j['timeline'][]=['ts'=>gmdate('c'),'event'=>$stage.'_COMPLETE','actor'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>trim($note)];if($stage==='PACKING'){$j['packing']=['status'=>'PACKED','packed_at'=>gmdate('c'),'packed_by'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>trim($note)];$j['status']='READY_TO_DISPATCH';$j['current_stage']=null;$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'READY_TO_DISPATCH','actor'=>'system','note'=>'Production and packing workflow complete.'];}return$j;},'makeos.stage.complete');
    if(($job['source_type']??'')==='ORDER_ITEM'&&($job['status']??'')==='READY_TO_DISPATCH'){$o=order_by_guid((string)$job['source_order_guid']);$jobs=makeos_jobs_for_order((string)$job['source_order_guid']);if($o&&count($jobs)===count($o['items']??[])&&count(array_filter($jobs,fn($x)=>($x['status']??'')==='READY_TO_DISPATCH'))===count($jobs)&&($o['status']??'')==='IN_PRODUCTION')order_transition($o['guid'],'READY_TO_DISPATCH','makeos','All manufacturing jobs passed QA and packing.');}
    return $job;
}
function makeos_qa_inspect(string $jobGuid,string $decision,array $checks,string $note='',string $reworkStage='ASSEMBLY'): array {
    require_admin();$decision=strtoupper(trim($decision));return makeos_mutate_job($jobGuid,function(array $j)use($jobGuid,$decision,$checks,$note,$reworkStage){if(($j['status']??'')!=='IN_PRODUCTION'||($j['current_stage']??'')!=='QA')throw new RuntimeException('QA inspection is only available at the active QA stage.');$clean=[];foreach(['dimensions','finish','stability','hardware','surface','configuration'] as $k)$clean[$k]=!empty($checks[$k]);$actor='admin:'.(admin_identity()['guid']??'unknown');if($decision==='PASS'){if(function_exists('quality_job_has_blocking_defects') && quality_job_has_blocking_defects($jobGuid)) throw new RuntimeException('Resolve all major/critical defects before QA can pass.');if(in_array(false,array_values($clean),true))throw new RuntimeException('All QA checks must pass before the job can move to packing.');$j['qa']=['status'=>'PASSED','checked_at'=>gmdate('c'),'checked_by'=>$actor,'checks'=>$clean,'note'=>trim($note),'rework_stage'=>null];$idx=null;foreach($j['stages'] as $i=>$row)if(($row['stage']??'')==='QA'){$idx=$i;break;}$j['stages'][$idx]['status']='COMPLETE';$j['stages'][$idx]['completed_at']=gmdate('c');$j['stages'][$idx]['completed_by']=$actor;$j['stages'][$idx]['note']=trim($note);$next=$j['stages'][$idx+1];$j['stages'][$idx+1]['status']='ACTIVE';$j['stages'][$idx+1]['started_at']=gmdate('c');$j['current_stage']=$next['stage'];$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'QA_PASSED','actor'=>$actor,'note'=>trim($note)?:'QA passed.'];return$j;}if($decision!=='FAIL')throw new RuntimeException('Choose PASS or FAIL for QA.');$allowed=array_values(array_filter(makeos_stages(),fn($s)=>!in_array($s,['QA','PACKING'],true)));$reworkStage=strtoupper(trim($reworkStage));if(!in_array($reworkStage,$allowed,true))$reworkStage='ASSEMBLY';$j['qa']=['status'=>'FAILED','checked_at'=>gmdate('c'),'checked_by'=>$actor,'checks'=>$clean,'note'=>trim($note),'rework_stage'=>$reworkStage];foreach($j['stages'] as &$row){if(($row['stage']??'')==='QA'){$row['status']='FAILED';$row['note']=trim($note);}}$j['status']='REWORK';$j['current_stage']=null;$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'QA_FAILED','actor'=>$actor,'note'=>(trim($note)?:'QA failed.').' Rework from '.makeos_stage_label($reworkStage).'.'];return$j;},'makeos.qa.inspect');
}
function makeos_resume_rework(string $jobGuid): array {
    require_admin();return makeos_mutate_job($jobGuid,function(array $j){if(($j['status']??'')!=='REWORK'||($j['qa']['status']??'')!=='FAILED')throw new RuntimeException('Job is not awaiting rework.');$target=(string)($j['qa']['rework_stage']??'ASSEMBLY');$found=false;foreach($j['stages'] as &$row){if(($row['stage']??'')===$target){$row['status']='ACTIVE';$row['started_at']=gmdate('c');$row['completed_at']=null;$row['completed_by']=null;$found=true;}elseif($found){$row['status']='PENDING';$row['started_at']=null;$row['completed_at']=null;$row['completed_by']=null;}}if(!$found)throw new RuntimeException('Rework stage is invalid.');$j['qa']['status']='REWORKING';$j['status']='IN_PRODUCTION';$j['current_stage']=$target;$j['timeline'][]=['ts'=>gmdate('c'),'event'=>'REWORK_STARTED','actor'=>'admin:'.(admin_identity()['guid']??'unknown'),'note'=>'Rework resumed from '.makeos_stage_label($target).'.'];return$j;},'makeos.rework.resume');
}
function makeos_bom_estimated_cost(array $job): int { return array_sum(array_map(fn($x)=>(int)($x['line_cost']??0),$job['bom']??[])); }

function makeos_customer_progress_for_quote(string $quoteGuid,?string $customerGuid=null): ?array {
    $j=makeos_job_for_quote($quoteGuid);if(!$j)return null;$customerGuid=$customerGuid?:((string)(customer_identity()['guid']??''));if(($j['customer_guid']??'')!==$customerGuid)return null;
    return ['number'=>$j['number'],'status'=>$j['status'],'status_label'=>makeos_status_label($j['status']),'current_stage'=>$j['current_stage'],'stage_label'=>$j['current_stage']?makeos_stage_label($j['current_stage']):null,'updated_at'=>$j['updated_at'],'progress'=>makeos_progress_percent($j)];
}
function makeos_progress_percent(array $j): int { $stages=$j['stages']??[];if(!$stages)return 0;$done=count(array_filter($stages,fn($s)=>($s['status']??'')==='COMPLETE'));if(($j['status']??'')==='READY_TO_DISPATCH')return 100;return (int)round(($done/count($stages))*100); }

function makeos_customer_progress_for_order(string $orderGuid,?string $customerGuid=null): array {
    $customerGuid=$customerGuid?:((string)(customer_identity()['guid']??''));$out=[];foreach(makeos_jobs_for_order($orderGuid) as $j){if(($j['customer_guid']??'')!==$customerGuid)continue;$out[]=['number'=>$j['number'],'item_index'=>$j['source_item_index'],'product_name'=>$j['product_snapshot']['name']??'Item','status'=>$j['status'],'status_label'=>makeos_status_label($j['status']),'current_stage'=>$j['current_stage'],'stage_label'=>$j['current_stage']?makeos_stage_label($j['current_stage']):null,'progress'=>makeos_progress_percent($j),'updated_at'=>$j['updated_at']];}return$out;
}

function makeos_release_checks(): array {
    $jobs=makeos_jobs();$schema=true;$sourceIntegrity=true;$bomIntegrity=true;$numbers=[];$sources=[];
    foreach($jobs as $j){$numbers[]=$j['number']??'';$sources[]=$j['source_key']??'';foreach(['guid','number','source_type','source_key','source_fingerprint','status','bom','stages','timeline'] as $k)if(!array_key_exists($k,$j))$schema=false;if(($j['source_type']??'')==='BESPOKE_FREEZE'){$q=bespoke_quote_by_guid((string)($j['source_quote_guid']??''));if(!$q||!makeos_freeze_integrity($q)||!hash_equals((string)($j['source_fingerprint']??''),(string)($q['freeze']['fingerprint']??'')))$sourceIntegrity=false;}elseif(($j['source_type']??'')==='ORDER_ITEM'){$o=order_by_guid((string)($j['source_order_guid']??''));try{$calc=$o?makeos_order_item_fingerprint($o,(int)($j['source_item_index']??-1)):'';}catch(Throwable){$calc='';}if(!$o||$calc===''||!hash_equals((string)($j['source_fingerprint']??''),$calc))$sourceIntegrity=false;}else{$sourceIntegrity=false;}if(!empty($j['bom_locked'])&&(!($j['bom_fingerprint']??'')||!hash_equals((string)$j['bom_fingerprint'],makeos_bom_hash($j['bom']??[]))))$bomIntegrity=false;}
    $encrypted=!is_file(makeos_job_file())||!str_contains((string)@file_get_contents(makeos_job_file()),'source_quote_guid');
    return [
        ['name'=>'MakeOS job encryption','ok'=>$encrypted,'detail'=>$encrypted?'Manufacturing jobs are encrypted at rest or not initialized.':'Plaintext MakeOS records detected.'],
        ['name'=>'MakeOS job schema','ok'=>$schema,'detail'=>$schema?'Manufacturing records contain required workflow fields.':'Malformed MakeOS job detected.'],
        ['name'=>'MakeOS job number uniqueness','ok'=>count($numbers)===count(array_unique($numbers)),'detail'=>count($numbers)===count(array_unique($numbers))?'Production job numbers are unique.':'Duplicate production job number detected.'],
        ['name'=>'MakeOS source uniqueness','ok'=>count($sources)===count(array_unique($sources)),'detail'=>count($sources)===count(array_unique($sources))?'Each immutable source has at most one production job.':'Duplicate job created from the same immutable source.'],
        ['name'=>'MakeOS source integrity','ok'=>$sourceIntegrity,'detail'=>$sourceIntegrity?'Every job remains bound to its immutable Design Freeze or order-item snapshot.':'A manufacturing source fingerprint no longer validates.'],
        ['name'=>'MakeOS BOM integrity','ok'=>$bomIntegrity,'detail'=>$bomIntegrity?'All locked BOM fingerprints validate.':'Locked BOM fingerprint mismatch detected.'],
        ['name'=>'MakeOS staging gate','ok'=>($GLOBALS['app']['environment']??'staging')!=='production','detail'=>'Workshop workflow remains staging/file-mode until PostgreSQL production cutover.'],
    ];
}

makeos_ensure_dirs();
