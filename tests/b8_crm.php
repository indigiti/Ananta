<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('L',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=b83-test');putenv('SANJAY_AGENT_SIGNING_KEY=b83-agent-012345678901');
$_SERVER['SCRIPT_NAME']='/ananta/index.php';$_SERVER['HTTP_HOST']='stage.invalid';$_SERVER['REMOTE_ADDR']='127.0.0.79';
$tag=bin2hex(random_bytes(6));$files=[];
foreach(['TENANT','MARKETPLACE','CONNECT','CONNECT_RUNTIME','CONNECT_CRM','SOURCING'] as $kind){
 $path=$root.'/storage/tmp/b83-'.strtolower($kind).'-'.$tag.'.json';
 putenv('ANANTA_TEST_'.$kind.'_FILE='.$path);$files[]=$path;
}
require $root.'/src/bootstrap.php';
$errors=[];$ok=static function(bool $test,string $why)use(&$errors){if(!$test)$errors[]=$why;};
$deny=static function(callable $f,string $why)use(&$errors){
 try{$f();$errors[]=$why;}catch(RuntimeException|InvalidArgumentException){}
};
try{
 $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'admin@qa.invalid','name'=>'QA Admin','role'=>'admin'];
 $a=marketplace_supplier_save(['name'=>'CRM A Nursery','city'=>'Pune','phone'=>'919811110001','status'=>'verified']);
 $b=marketplace_supplier_save(['name'=>'CRM B Nursery','city'=>'Pune','phone'=>'919811110002','status'=>'verified']);
 $inviteA=tenant_invite_issue($a,'crm-a@qa.invalid','owner');
 $inviteB=tenant_invite_issue($b,'crm-b@qa.invalid','manager');
 unset($_SESSION['admin_user']);
 tenant_invite_accept($inviteA,'Owner A','Secure-CRM-A-2026!');tenant_invite_accept($inviteB,'Manager B','Secure-CRM-B-2026!');
 connect_runtime_mutate(static function(array $d)use($a,$b){
    connect_runtime_append_message($d,$a,'919811112233','inbound','text','Need 12 plants',gmdate('c'),'wamid.crm.a1');
    connect_runtime_append_message($d,$b,'919822223344','inbound','text','Need 80 plants',gmdate('c'),'wamid.crm.b1');
    return $d;
 },'test.seed');
 $convs=array_values(connect_runtime_store()['conversations']);$idA=$convs[0]['tenant_id']===$a?$convs[0]['id']:$convs[1]['id'];
 $idB=$convs[0]['tenant_id']===$b?$convs[0]['id']:$convs[1]['id'];
 $deny(static fn()=>connect_crm_lead($a,$idA),'Anonymous CRM read allowed.');
 $ok(tenant_login('crm-b@qa.invalid','Secure-CRM-B-2026!'),'B login failed');
 $ok(connect_crm_lead($b,$idA)===null,'Tenant B saw tenant A CRM.');
 $deny(static fn()=>connect_crm_save_lead($a,$idA,['revision'=>'0','status'=>'qualified']),'Cross-tenant edit accepted.');
 $deny(static fn()=>connect_crm_task_save($a,$idA,['title'=>'Call about quote','due_date'=>gmdate('Y-m-d')]),'Other nursery created task.');
 tenant_logout();
 $ok(tenant_login('crm-a@qa.invalid','Secure-CRM-A-2026!'),'A login failed');
 $identity=tenant_identity();
 $team=connect_crm_team($a);$ok(count($team)===1&&$team[0]['id']===$identity['id'],'Active nursery user not assignable.');
 $deny(static fn()=>connect_crm_save_lead($a,$idA,['revision'=>'0','status'=>'fake']),'Bad lead status accepted');
 $deny(static fn()=>connect_crm_save_lead($a,$idA,['revision'=>'0','status'=>'quoted','assignee_id'=>uuidv7()]),'Unknown assignee accepted');
 connect_crm_save_lead($a,$idA,['revision'=>'0','status'=>'qualified','assignee_id'=>$identity['id'],
    'tags'=>'wholesale, lead','note'=>'Discussed delivery terms']);
 $lead=connect_crm_lead($a,$idA);
 $ok($lead['status']==='qualified'&&$lead['revision']===1,'Lead state was not persisted.');
 $ok($lead['assignee_id']===$identity['id'],'Assignment lost.');
 $ok(count($lead['notes'])===1,'Private note missing.');
 $deny(static fn()=>connect_crm_save_lead($a,$idA,['revision'=>'0','status'=>'quoted']),'Stale lead revision accepted.');
 $deny(static fn()=>connect_crm_task_save($a,$idA,['title'=>'Bad date','due_date'=>'2020-01-01']),
    'Past due date allowed.');
 $task=connect_crm_task_save($a,$idA,['title'=>'Follow up on plant grades','due_date'=>gmdate('Y-m-d'),'assignee_id'=>$identity['id']]);
 $ok(count(connect_crm_tasks($a,$idA))===1,'Task list missing.');
 $report=connect_crm_report($a);
 $ok($report['stages']['qualified']===1&&$report['open_tasks']===1,'CRM report did not reconcile.');
 $deny(static fn()=>connect_crm_task_complete($b,$task),'Wrong tenant completed task.');
 connect_crm_task_complete($a,$task);
 $ok(connect_crm_tasks($a,$idA)[0]['status']==='done','Follow-up completion lost.');
 $deny(static fn()=>connect_crm_task_complete($a,$task),'Completed task was replayed.');
 $raw=(string)file_get_contents($files[4]);
 $ok(!str_contains($raw,'Discussed delivery terms')&&!str_contains($raw,'Follow up on plant grades'),
     'CRM private records stored unencrypted.');
 $ok(connect_crm_lead($a,$idB)===null,'Other nursery CRM accessible.');
 $old=(string)getenv('ANANTA_CONNECT_RFQ_AUTOMATION');
 putenv('ANANTA_CONNECT_RFQ_AUTOMATION=0');
 $ok(connect_crm_reconcile_rfqs()['queued']===0,'Disabled automated RFQ sent messages.');
 putenv('ANANTA_CONNECT_RFQ_AUTOMATION='.$old);
 $ok(str_contains((string)file_get_contents($root.'/public/index.php'),'/supplier/connect/report'),'CRM reporting route missing.');
}catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($files as $path)@unlink($path);
if($errors){fwrite(STDERR,"FAIL B8.3 CRM:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: encrypted CRM assignments, optimistic edits, tenant isolation, follow-ups, reporting and RFQ automation OFF by default.\n";
