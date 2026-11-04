<?php
declare(strict_types=1);
/** B8.5 encrypted snapshots, isolated restore drill, no false go-live claims. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('P',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=b85-test');putenv('SANJAY_AGENT_SIGNING_KEY=b85-agent-test-0123456789');
$_SERVER['HTTP_HOST']='staging.example.invalid';$_SERVER['SCRIPT_NAME']='/ananta/index.php';$_SERVER['REMOTE_ADDR']='127.0.0.89';$_SERVER['HTTPS']='on';
$tag=bin2hex(random_bytes(6));$paths=[];
foreach(['CONNECT','CONNECT_RUNTIME','CONNECT_CRM','DISTRIBUTION','B8_ACCEPTANCE'] as $type){
 $path=$root.'/storage/tmp/b85-'.strtolower($type).'-'.$tag.'.json';
 putenv('ANANTA_TEST_'.$type.'_FILE='.$path);$paths[]=$path;
}
require $root.'/src/bootstrap.php';
$errors=[];$assert=static function(bool $v,string $why)use(&$errors){if(!$v)$errors[]=$why;};
$deny=static function(callable $f,string $why)use(&$errors){
 try{$f();$errors[]=$why;}catch(Throwable){}
};
$backup='';
try{
 $deny(static fn()=>b8_acceptance_evidence_save('mobile_browser','No proof available'),
    'Anonymous user could attest browser QA.');
 $_SESSION['admin_user']=['guid'=>uuidv7(),'email'=>'admin@qa.invalid','name'=>'QA','role'=>'admin'];
 connect_mutate(static function(array $data){
   $data['events'][]=['id'=>uuidv7(),'kind'=>'test_fixture','at'=>gmdate('c')];
   return $data;
 },'b85.seed');
 connect_runtime_mutate(static function(array $d){
   connect_runtime_append_message($d,'platform','919811112233','inbound','text','Test-private-message',gmdate('c'),'wamid.b85');
   return $d;
 },'b85.seed');
 $deny(static fn()=>b8_acceptance_evidence_save('illegal_case','Traceable staging evidence 1234'),
     'Unknown acceptance checklist item allowed.');
 $deny(static fn()=>b8_acceptance_evidence_save('mobile_browser','short'),
     'Evidence accepted without traceable reference.');
 b8_acceptance_evidence_save('mobile_browser','Staging QA issue #12345 with screenshots');
 $audit=discover_distribution_refresh();
 $snapshot=b8_acceptance_snapshot();
 $assert($snapshot['production_ready']===false,'Pilot report falsely asserts production readiness.');
 $assert($snapshot['field_acceptance_verified_independently']===false,
    'Admin attestation incorrectly treated as independent verification.');
 $assert($snapshot['operator_evidence']['mobile_browser']['status']==='operator_attested_not_independently_verified',
    'Evidence provenance missing.');
 $assert($snapshot['crm']['messages']===1,'CRM message metric missing.');
 $assert(!str_contains((string)file_get_contents($paths[4]),'Staging QA issue #12345'),
    'Operator evidence leaked into plaintext.');
 $backup=b8_acceptance_backup_create();
 $result=b8_acceptance_backup_verify($backup);
 $assert($result['verified']&&$result['live_data_modified']===false,'Isolated backup verification failed.');
 $assert(count($result['checked_files'])>=3,'Critical encrypted records absent from backup.');
 $assert(!file_exists($backup.'/data-key'),'Backup should never contain encryption keys.');
 $stored=connect_runtime_store();
 $assert(count($stored['messages'])===1,'Backup verification modified live message data.');
 $fixture=$backup.'/message-runtime.json';
 $original=(string)file_get_contents($fixture);
 file_put_contents($fixture,$original.'tamper');
 $deny(static fn()=>b8_acceptance_backup_verify($backup),'Checksum mismatch was not rejected.');
 file_put_contents($fixture,$original);
 $assert(b8_acceptance_backup_verify($backup)['verified']===true,'Restored backup rejected after tamper removal.');
 $template=(string)file_get_contents($root.'/templates/admin/b8-acceptance.php');
 foreach(['Machine checks','Field acceptance evidence','backup','off-server'] as $needle)
    $assert(str_contains($template,$needle),'Pilot dashboard missing '.$needle);
}catch(Throwable $e){$errors[]=$e->getMessage();}
foreach($paths as $path)@unlink($path);
if($backup&&is_dir($backup)){
 foreach(glob($backup.'/*')?:[] as $path)@unlink($path);
 @rmdir($backup);
}
if($errors){fwrite(STDERR,"FAIL B8.5 pilot:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: encrypted B8 backup checksums, isolated decryption restore, tampering denial, operator-evidence provenance, dashboard checks and no false production claims.\n";
