<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

$errors=[];
$files=[monitor_incidents_file(),monitor_state_file(),runtime_config_file(),setup_claim_file()];
$backup=[];
foreach($files as $f)$backup[$f]=is_file($f)?(string)file_get_contents($f):null;
$restore=function()use($backup){foreach($backup as $f=>$raw){if($raw===null)@unlink($f);else{@mkdir(dirname($f),0750,true);file_put_contents($f,$raw,LOCK_EX);}}};
register_shutdown_function($restore);

try{
    @unlink(monitor_incidents_file());@unlink(monitor_state_file());@unlink(runtime_config_file());@unlink(setup_claim_file());
    monitor_ensure_dirs();

    $cfg=runtime_config_bootstrap();
    if(confidential_key()===null)$errors[]='Web-managed data key was not generated.';
    if(strlen((string)($cfg['sandbox_webhook_secret']??''))<32)$errors[]='Web-managed webhook secret is too short.';
    if(strlen((string)($cfg['agent_signing_key']??''))<32)$errors[]='Web-managed agent signing key is too short.';
    $public=realpath(dirname(__DIR__).'/public')?:'';$cfgPath=realpath(runtime_config_file())?:runtime_config_file();
    if($public!==''&&str_starts_with($cfgPath,$public))$errors[]='Runtime configuration is inside the public document root.';

    $claim=setup_claim_issue();
    if(empty($claim['ok'])||empty($claim['token'])||!setup_claim_validate((string)$claim['token']))$errors[]='Web setup claim could not be created/validated.';

    $fp='qa-warning-'.bin2hex(random_bytes(4));
    monitor_record_incident(['fingerprint'=>$fp,'source'=>'php','category'=>'qa','severity'=>'warning','title'=>'QA warning','message'=>'Synthetic monitoring test','route'=>'/qa','method'=>'GET']);
    $row=monitor_incidents()[$fp]??null;
    if(!$row||($row['status']??'')!=='active')$errors[]='Incident was not opened.';
    monitor_resolve($fp,'QA recovered');$row=monitor_incidents()[$fp]??null;
    if(!$row||($row['status']??'')!=='resolved')$errors[]='Incident was not resolved.';
    foundation_json_mutate(monitor_incidents_file(),function(array $rows)use($fp){$rows[$fp]['resolved_at']=gmdate('c',time()-91*86400);$rows[$fp]['last_seen_at']=$rows[$fp]['resolved_at'];return$rows;},'qa.age.warning');
    monitor_cleanup(true);
    if(isset(monitor_incidents()[$fp]))$errors[]='Resolved non-critical incident older than 90 days was not removed.';

    $critical='qa-critical-'.bin2hex(random_bytes(4));
    monitor_record_incident(['fingerprint'=>$critical,'source'=>'exception','category'=>'qa','severity'=>'critical','title'=>'QA critical','message'=>'Synthetic critical test','route'=>'/qa-critical','method'=>'GET']);
    monitor_resolve($critical,'QA recovered');
    foundation_json_mutate(monitor_incidents_file(),function(array $rows)use($critical){$rows[$critical]['resolved_at']=gmdate('c',time()-120*86400);$rows[$critical]['last_seen_at']=$rows[$critical]['resolved_at'];return$rows;},'qa.age.critical');
    monitor_cleanup(true);
    if(!isset(monitor_incidents()[$critical]))$errors[]='Critical incident was incorrectly removed by retention cleanup.';

    $name='QA health check '.bin2hex(random_bytes(3));$healthFp=monitor_fingerprint('health','release-check',$name,'','');
    monitor_sync_release_checks([['name'=>$name,'ok'=>false,'detail'=>'Synthetic fail']]);
    if((monitor_incidents()[$healthFp]['status']??'')!=='active')$errors[]='Failed health check did not open an incident.';
    monitor_sync_release_checks([['name'=>$name,'ok'=>true,'detail'=>'Synthetic recovery']]);
    if((monitor_incidents()[$healthFp]['status']??'')!=='resolved')$errors[]='Recovered health check did not auto-resolve its incident.';

    $routeFp=monitor_fingerprint('http','http-5xx','GET /qa-route','/qa-route','');
    monitor_record_incident(['fingerprint'=>$routeFp,'source'=>'http','category'=>'http-5xx','severity'=>'error','title'=>'HTTP 500 response','message'=>'Synthetic route failure','route'=>'/qa-route','method'=>'GET','http_status'=>500]);
    monitor_resolve_route('/qa-route','GET','Synthetic route recovery');
    if((monitor_incidents()[$routeFp]['status']??'')!=='resolved')$errors[]='Successful route did not auto-resolve its runtime/HTTP incident.';
    monitor_record_incident(['fingerprint'=>$routeFp,'source'=>'http','category'=>'http-5xx','severity'=>'error','title'=>'HTTP 500 response','message'=>'Synthetic route recurrence','route'=>'/qa-route','method'=>'GET','http_status'=>500]);
    $routeRow=monitor_incidents()[$routeFp]??[];
    if(($routeRow['status']??'')!=='active'||(int)($routeRow['reopened_count']??0)<1)$errors[]='Recurring route failure did not reopen the same incident.';
}catch(Throwable $e){$errors[]='Monitoring test exception: '.$e->getMessage();}

$restore();
if($errors){fwrite(STDERR,"FAIL: ".implode("\nFAIL: ",$errors)."\n");exit(1);} 
echo "PASS: web-managed bootstrap secrets, setup claim, incident open/resolve/reopen, health and route auto-resolution, 90-day non-critical cleanup and critical retention verified.\n";
