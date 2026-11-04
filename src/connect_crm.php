<?php
declare(strict_types=1);

/**
 * B8.3 Ananta Connect CRM. Staging only, single-writer encrypted pilot.
 * Conversations are the only tenant-scoped contact authority. No bulk blast.
 */
function connect_crm_file(): string {
    $t=trim((string)getenv('ANANTA_TEST_CONNECT_CRM_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$t!=='')return $t;
    return data_root().'/private-domain/connect/crm.json';
}
function connect_crm_store(): array {
    return array_replace(['version'=>1,'leads'=>[],'tasks'=>[],'events'=>[]],
        confidential_json_read(connect_crm_file(),'ananta:connect:crm:v1',[]));
}
function connect_crm_mutate(callable $cb,string $reason): array {
    if(confidential_key()===null)throw new RuntimeException('Encrypted CRM storage required.');
    return confidential_json_mutate(connect_crm_file(),'ananta:connect:crm:v1',
        static fn(array $d)=>$cb(array_replace(['version'=>1,'leads'=>[],'tasks'=>[],'events'=>[]],$d)),
        'connect.crm.'.$reason);
}
function connect_crm_access(string $tenant,bool $write=false): array {
    if($tenant==='platform'){
        require_admin();return ['id'=>'platform-admin','role'=>'admin','tenant_id'=>'platform'];
    }
    return tenant_require($write?'connect.send':'connect.read',$tenant);
}
function connect_crm_assignee(string $tenant,string $userId): ?array {
    if(!tenant_valid_id($userId))return null;
    $d=tenant_store();
    $membership=$d['memberships'][$tenant.':'.$userId]??null;
    $u=$d['users'][$userId]??null;
    if(!$membership||!($membership['active']??false)||!$u||!($u['active']??false))return null;
    if(!in_array($membership['role']??'', ['owner','manager'],true))return null;
    return ['id'=>$userId,'name'=>(string)$u['name']];
}
function connect_crm_stages(): array {
    return ['new','contacted','qualified','quoted','won','lost'];
}
function connect_crm_lead(string $tenant,string $conversationId): ?array {
    connect_crm_access($tenant);
    $thread=connect_inbox_thread($tenant,$conversationId);
    if(!$thread)return null;
    $d=connect_crm_store();
    $lead=$d['leads'][$conversationId]??null;
    if($lead&&($lead['tenant_id']??'')!==$tenant)throw new RuntimeException('CRM tenant mismatch.');
    return $lead??['id'=>$conversationId,'tenant_id'=>$tenant,'status'=>'new',
        'assignee_id'=>'','tags'=>[],'notes'=>[],'revision'=>0];
}
function connect_crm_save_lead(string $tenant,string $conversationId,array $input): void {
    $actor=connect_crm_access($tenant,true);
    if(!connect_inbox_thread($tenant,$conversationId))throw new RuntimeException('Conversation not found.');
    $stage=(string)($input['status']??'');
    if(!in_array($stage,connect_crm_stages(),true))throw new InvalidArgumentException('Invalid CRM lead stage.');
    $assignee=trim((string)($input['assignee_id']??''));
    if($assignee!==''&&($tenant==='platform'||!connect_crm_assignee($tenant,$assignee)))
        throw new InvalidArgumentException('Assignee must be an active permitted user in this nursery.');
    $rawTags=trim((string)($input['tags']??''));
    if(strlen($rawTags)>180)throw new InvalidArgumentException('Tags too long.');
    $tags=[];
    foreach(explode(',',$rawTags) as $t){
        $t=trim($t);if($t==='')continue;
        if(!preg_match('/^[\p{L}\p{N} _-]{2,30}$/u',$t))throw new InvalidArgumentException('Invalid CRM tag.');
        $tags[$t]=$t;
    }
    if(count($tags)>8)throw new InvalidArgumentException('At most 8 tags.');
    $note=trim((string)($input['note']??''));
    if(text_len($note)>600||str_contains($note,"\0"))throw new InvalidArgumentException('Note too long.');
    $expected=(string)($input['revision']??'');
    if(!ctype_digit($expected)||strlen($expected)>5)throw new InvalidArgumentException('Reload CRM lead to edit.');
    $now=gmdate('c');
    connect_crm_mutate(static function(array $d)use($tenant,$conversationId,$stage,$assignee,$tags,$note,$expected,$actor,$now){
        $row=$d['leads'][$conversationId]??[
            'id'=>$conversationId,'tenant_id'=>$tenant,'revision'=>0,'notes'=>[],'created_at'=>$now,
        ];
        if($row['tenant_id']!==$tenant)throw new RuntimeException('Tenant mismatch.');
        if((int)$row['revision']!==(int)$expected)throw new RuntimeException('CRM record updated by another user. Reload.');
        if(!isset($d['leads'][$conversationId])&&count($d['leads'])>=2000)
            throw new RuntimeException('CRM pilot limit reached.');
        $row['status']=$stage;$row['assignee_id']=$assignee;$row['tags']=array_values($tags);
        if($note!==''){
            if(count($row['notes'])>=30)throw new RuntimeException('Note history retention limit reached.');
            $row['notes'][]=['id'=>uuidv7(),'body'=>$note,'actor_id'=>$actor['id'],'created_at'=>$now];
        }
        $row['revision']++;$row['updated_at']=$now;
        $d['leads'][$conversationId]=$row;
        return $d;
    },'lead.save');
    audit_event('connect.crm.lead.save',['tenant_id'=>$tenant,'thread_id'=>$conversationId,'actor_id'=>$actor['id']]);
}
function connect_crm_task_save(string $tenant,string $conversationId,array $input): string {
    $actor=connect_crm_access($tenant,true);
    if(!connect_inbox_thread($tenant,$conversationId))throw new RuntimeException('Conversation not found.');
    $title=trim((string)($input['title']??''));
    if(text_len($title)<4||text_len($title)>140||str_contains($title,"\0"))
        throw new InvalidArgumentException('Follow-up title must be 4–140 characters.');
    $date=(string)($input['due_date']??'');
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));
    if(!$dt||$dt->format('Y-m-d')!==$date||$dt->getTimestamp()<strtotime('today UTC')||
        $dt->getTimestamp()>strtotime('+365 days'))
        throw new InvalidArgumentException('Due date must be within 365 days.');
    $assigned=trim((string)($input['assignee_id']??''));
    if($assigned!==''&&($tenant==='platform'||!connect_crm_assignee($tenant,$assigned)))
        throw new InvalidArgumentException('Follow-up assignee not permitted.');
    $id=uuidv7();$now=gmdate('c');
    connect_crm_mutate(static function(array $d)use($tenant,$conversationId,$title,$date,$assigned,$id,$now,$actor){
        if(count($d['tasks'])>=3000)throw new RuntimeException('CRM task capacity reached.');
        $d['tasks'][$id]=['id'=>$id,'tenant_id'=>$tenant,'conversation_id'=>$conversationId,
            'title'=>$title,'due_date'=>$date,'assignee_id'=>$assigned,'status'=>'open',
            'created_at'=>$now,'updated_at'=>$now,'created_by'=>$actor['id']];
        return $d;
    },'task.create');
    audit_event('connect.crm.task.created',['tenant_id'=>$tenant,'task_id'=>$id]);
    return $id;
}
function connect_crm_task_complete(string $tenant,string $taskId): void {
    $actor=connect_crm_access($tenant,true);
    if(!tenant_valid_id($taskId))throw new InvalidArgumentException('Invalid follow-up.');
    connect_crm_mutate(static function(array $d)use($tenant,$taskId,$actor){
        $task=$d['tasks'][$taskId]??null;
        if(!$task||$task['tenant_id']!==$tenant)throw new RuntimeException('Follow-up not found.');
        if($task['status']!=='open')throw new RuntimeException('Follow-up already completed.');
        if($task['assignee_id']!==''&&$task['assignee_id']!==$actor['id']&&
            !in_array($actor['role'],['owner','admin'],true))
            throw new RuntimeException('Only assigned staff or owner can finish the follow-up.');
        $d['tasks'][$taskId]['status']='done';$d['tasks'][$taskId]['updated_at']=gmdate('c');
        $d['tasks'][$taskId]['completed_by']=$actor['id'];
        return $d;
    },'task.complete');
    audit_event('connect.crm.task.completed',['tenant_id'=>$tenant,'task_id'=>$taskId]);
}
function connect_crm_tasks(string $tenant,?string $conversationId=null): array {
    connect_crm_access($tenant);
    $rows=array_values(array_filter(connect_crm_store()['tasks'],static fn($t)=>
        $t['tenant_id']===$tenant&&($conversationId===null||$t['conversation_id']===$conversationId)));
    usort($rows,static fn($a,$b)=>strcmp($a['due_date'],$b['due_date']));
    return array_slice($rows,0,100);
}
function connect_crm_team(string $tenant): array {
    connect_crm_access($tenant);
    if($tenant==='platform')return [];
    $d=tenant_store();$people=[];
    foreach($d['memberships'] as $m){
        if($m['tenant_id']!==$tenant||!$m['active']||!in_array($m['role'],['owner','manager'],true))continue;
        $u=$d['users'][$m['user_id']]??null;
        if($u&&($u['active']??false))$people[]=['id'=>$u['id'],'name'=>$u['name'],'role'=>$m['role']];
    }
    return $people;
}
function connect_crm_report(string $tenant): array {
    connect_crm_access($tenant);
    $d=connect_crm_store();$stages=array_fill_keys(connect_crm_stages(),0);
    foreach($d['leads'] as $r)if($r['tenant_id']===$tenant)$stages[$r['status']]++;
    $tasks=connect_crm_tasks($tenant);$today=gmdate('Y-m-d');
    $open=count(array_filter($tasks,static fn($t)=>$t['status']==='open'));
    $overdue=count(array_filter($tasks,static fn($t)=>$t['status']==='open'&&$t['due_date']<$today));
    return ['stages'=>$stages,'open_tasks'=>$open,'overdue_tasks'=>$overdue,
        'message_statuses'=>connect_queue_summary($tenant),'conversations'=>count(connect_inbox_list($tenant,50))];
}
/**
 * Reconcile persisted sourcing events into the B8.2 queue with stable event IDs.
 * Never dispatch if contact sharing/consent/template/sender are absent.
 * Querying the RFQ store after the commercial write avoids notification of
 * uncommitted transactions. Reconciliation may run more than once safely.
 */
function connect_crm_reconcile_rfqs(int $limit=30): array {
    if(PHP_SAPI!=='cli')throw new RuntimeException('RFQ notification reconciler is CLI-only.');
    if((string)getenv('ANANTA_CONNECT_RFQ_AUTOMATION')!=='1')return ['queued'=>0,'skipped'=>'disabled'];
    $rows=sourcing_store()['rfqs'];$queued=0;$skipped=0;
    $template=trim((string)getenv('ANANTA_WA_RFQ_TEMPLATE'));
    if(!connect_template_allowed($template,'en'))return ['queued'=>0,'skipped'=>'template_unapproved'];
    foreach($rows as $r){
        if($queued>=$limit)break;
        if(empty($r['contact_shared'])||empty($r['buyer_contact']['phone'])){$skipped++;continue;}
        foreach($r['recipients']??[] as $tenant=>$receipt){
            if($queued>=$limit)break;
            $phone=(string)$r['buyer_contact']['phone'];
            try{$phone=connect_normalize_phone($phone);}catch(Throwable){$skipped++;continue;}
            // Only explicitly opted-in buyer contact. Never notify losing suppliers.
            if(!connect_message_allowed($tenant,$phone,'rfq_updates')){$skipped++;continue;}
            if(!in_array($receipt['status']??'',['quoted','declined'],true))continue;
            $revision=(int)($receipt['quote_version']??0);
            $event='rfq:'.(string)$r['id'].':'.$tenant.':'.($receipt['status']??'').':v'.$revision;
            // Safe internal system enqueue: validated recipient-specific business event.
            try{
                connect_crm_enqueue_system($tenant,$phone,$template,'en',$event);
                $queued++;
            }catch(RuntimeException){$skipped++;}
        }
    }
    return ['queued'=>$queued,'skipped'=>$skipped];
}
function connect_crm_enqueue_system(string $tenant,string $phone,string $template,string $lang,string $event): string {
    if(PHP_SAPI!=='cli')throw new RuntimeException('System notifications require CLI.');
    if(!tenant_valid_id($tenant)||!preg_match('/^rfq:[a-f0-9-]{36}:[a-f0-9-]{36}:(?:quoted|declined):v[0-9]+$/D',$event))
        throw new InvalidArgumentException('Invalid RFQ event key.');
    if(!connect_message_allowed($tenant,$phone,'rfq_updates')||!connect_template_allowed($template,$lang))
        throw new RuntimeException('RFQ notification policy rejected.');
    return connect_enqueue_system_record($tenant,$phone,'rfq_updates',$template,$lang,$event);
}
