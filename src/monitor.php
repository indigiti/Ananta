<?php
declare(strict_types=1);

function monitor_root(): string { return dirname(__DIR__) . '/storage/private-domain/monitor'; }
function monitor_incidents_file(): string { return monitor_root() . '/incidents.json'; }
function monitor_state_file(): string { return monitor_root() . '/state.json'; }
function monitor_fallback_file(): string { return monitor_root() . '/fallback.ndjson'; }

function monitor_ensure_dirs(): void {
    if (!is_dir(monitor_root())) @mkdir(monitor_root(), 0750, true);
    @chmod(monitor_root(), 0750);
}

function monitor_now(): string { return gmdate('c'); }
function monitor_normalize_path(string $path): string {
    $root = str_replace('\\','/',dirname(__DIR__));
    $path = str_replace('\\','/',$path);
    return $root !== '' ? str_replace($root,'[app]',$path) : $path;
}
function monitor_route(): string {
    $uri = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    if (function_exists('base_path')) {
        $base=base_path(); if($base && str_starts_with($uri,$base)) $uri=substr($uri,strlen($base))?:'/';
    }
    return '/'.trim($uri,'/');
}
function monitor_method(): string { return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI==='cli'?'CLI':'GET'))); }
function monitor_sanitize_message(string $message): string {
    $message=monitor_normalize_path($message);
    $message=preg_replace('/\s+/u',' ',trim($message)) ?? trim($message);
    return function_exists('mb_substr') ? mb_substr($message,0,1200) : substr($message,0,1200);
}
function monitor_fingerprint(string $source,string $category,string $title,string $route='',string $file=''): string {
    return substr(hash('sha256',$source.'|'.$category.'|'.strtolower(trim($title)).'|'.$route.'|'.$file),0,32);
}
function monitor_severity_rank(string $severity): int { return ['info'=>1,'warning'=>2,'error'=>3,'critical'=>4][$severity] ?? 2; }
function monitor_php_severity(int $errno): string {
    if (in_array($errno,[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR,E_RECOVERABLE_ERROR],true)) return 'critical';
    if (in_array($errno,[E_WARNING,E_CORE_WARNING,E_COMPILE_WARNING,E_USER_WARNING],true)) return 'error';
    if (in_array($errno,[E_NOTICE,E_USER_NOTICE,E_DEPRECATED,E_USER_DEPRECATED,E_STRICT],true)) return 'warning';
    return 'warning';
}
function monitor_error_label(int $errno): string {
    $map=[E_ERROR=>'PHP Error',E_WARNING=>'PHP Warning',E_PARSE=>'PHP Parse Error',E_NOTICE=>'PHP Notice',E_CORE_ERROR=>'PHP Core Error',E_CORE_WARNING=>'PHP Core Warning',E_COMPILE_ERROR=>'PHP Compile Error',E_COMPILE_WARNING=>'PHP Compile Warning',E_USER_ERROR=>'Application Error',E_USER_WARNING=>'Application Warning',E_USER_NOTICE=>'Application Notice',E_STRICT=>'PHP Strict',E_RECOVERABLE_ERROR=>'Recoverable Error',E_DEPRECATED=>'PHP Deprecated',E_USER_DEPRECATED=>'Application Deprecated'];
    return $map[$errno] ?? 'Runtime Error';
}

function monitor_incidents(): array {
    monitor_ensure_dirs();
    $rows=foundation_json_read(monitor_incidents_file(),[]);
    return is_array($rows)?$rows:[];
}

function monitor_fallback(array $record): void {
    monitor_ensure_dirs();
    $line=json_encode($record,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
    @file_put_contents(monitor_fallback_file(),$line,FILE_APPEND|LOCK_EX);
    @chmod(monitor_fallback_file(),0640);
}

function monitor_record_incident(array $input): string {
    if (!empty($GLOBALS['_monitor_writing'])) return '';
    $GLOBALS['_monitor_writing']=true;
    try {
        monitor_ensure_dirs();
        $source=(string)($input['source']??'runtime');
        $category=(string)($input['category']??'application');
        $title=monitor_sanitize_message((string)($input['title']??'Application incident'));
        $message=monitor_sanitize_message((string)($input['message']??$title));
        $route=(string)($input['route']??monitor_route());
        $method=(string)($input['method']??monitor_method());
        $file=monitor_normalize_path((string)($input['file']??''));
        $line=(int)($input['line']??0);
        $severity=(string)($input['severity']??'error');
        if(!in_array($severity,['info','warning','error','critical'],true))$severity='error';
        $fingerprint=(string)($input['fingerprint']??monitor_fingerprint($source,$category,$title,$route,$file));
        $now=monitor_now();
        foundation_json_mutate(monitor_incidents_file(),function(array $rows)use($fingerprint,$source,$category,$title,$message,$route,$method,$file,$line,$severity,$now,$input){
            $row=$rows[$fingerprint]??[];
            $existingSeverity=(string)($row['severity']??'info');
            $status=(string)($row['status']??'active');
            $reopened=(int)($row['reopened_count']??0);
            if($status==='resolved')$reopened++;
            $rows[$fingerprint]=array_merge($row,[
                'id'=>$fingerprint,
                'source'=>$source,
                'category'=>$category,
                'severity'=>monitor_severity_rank($severity)>=monitor_severity_rank($existingSeverity)?$severity:$existingSeverity,
                'status'=>'active',
                'title'=>$title,
                'message'=>$message,
                'route'=>$route,
                'method'=>$method,
                'file'=>$file,
                'line'=>$line,
                'first_seen_at'=>$row['first_seen_at']??$now,
                'last_seen_at'=>$now,
                'resolved_at'=>null,
                'resolution'=>null,
                'count'=>(int)($row['count']??0)+1,
                'reopened_count'=>$reopened,
                'critical_retained'=>($severity==='critical'||($row['critical_retained']??false)),
                'http_status'=>(int)($input['http_status']??($row['http_status']??0)),
                'check_name'=>(string)($input['check_name']??($row['check_name']??'')),
            ]);
            return $rows;
        },'monitor.incident.record');
        return $fingerprint;
    } catch(Throwable $e) {
        monitor_fallback(['ts'=>monitor_now(),'kind'=>'monitor-write-failure','error'=>get_class($e),'message'=>monitor_sanitize_message($e->getMessage()),'incident'=>$input]);
        return '';
    } finally { $GLOBALS['_monitor_writing']=false; }
}

function monitor_resolve(string $fingerprint,string $reason='Recovered automatically'): bool {
    if($fingerprint==='')return false;
    try {
        $changed=false;$now=monitor_now();
        foundation_json_mutate(monitor_incidents_file(),function(array $rows)use($fingerprint,$reason,$now,&$changed){
            if(isset($rows[$fingerprint])&&($rows[$fingerprint]['status']??'active')!=='resolved'){
                $rows[$fingerprint]['status']='resolved';$rows[$fingerprint]['resolved_at']=$now;$rows[$fingerprint]['resolution']=$reason;$changed=true;
            }
            return $rows;
        },'monitor.incident.resolve');
        return $changed;
    }catch(Throwable){return false;}
}

function monitor_resolve_route(string $route,string $method,string $reason='Successful recovery request'): int {
    try {
        $count=0;$now=monitor_now();
        foundation_json_mutate(monitor_incidents_file(),function(array $rows)use($route,$method,$reason,$now,&$count){
            foreach($rows as &$row){
                if(($row['status']??'active')==='resolved')continue;
                if(($row['route']??'')!==$route||($row['method']??'')!==$method)continue;
                if(!in_array(($row['source']??''),['php','http','exception'],true))continue;
                $row['status']='resolved';$row['resolved_at']=$now;$row['resolution']=$reason;$count++;
            } unset($row); return $rows;
        },'monitor.route.resolve');
        return $count;
    }catch(Throwable){return 0;}
}

function monitor_error_handler(int $errno,string $errstr,string $errfile='',int $errline=0): bool {
    if(!(error_reporting() & $errno)) return false;
    $GLOBALS['_monitor_request_error']=true;
    monitor_record_incident(['source'=>'php','category'=>'runtime','severity'=>monitor_php_severity($errno),'title'=>monitor_error_label($errno),'message'=>$errstr,'file'=>$errfile,'line'=>$errline]);
    return false;
}
function monitor_exception_handler(Throwable $e): void {
    $GLOBALS['_monitor_request_error']=true;
    monitor_record_incident(['source'=>'exception','category'=>'runtime','severity'=>'critical','title'=>get_class($e),'message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'http_status'=>500]);
    if(!headers_sent()){http_response_code(500);header('Content-Type: text/html; charset=utf-8');}
    echo '<!doctype html><meta charset="utf-8"><title>Application error</title><div style="font-family:system-ui;padding:40px;max-width:680px;margin:auto"><h1>Application error</h1><p>The incident has been recorded in Admin OS → Error Monitor.</p></div>';
}
function monitor_shutdown_handler(): void {
    $fatal=error_get_last();
    if($fatal && in_array((int)$fatal['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
        $GLOBALS['_monitor_request_error']=true;
        monitor_record_incident(['source'=>'php','category'=>'fatal','severity'=>'critical','title'=>monitor_error_label((int)$fatal['type']),'message'=>(string)$fatal['message'],'file'=>(string)$fatal['file'],'line'=>(int)$fatal['line'],'http_status'=>500]);
    }
    if(PHP_SAPI==='cli')return;
    $status=http_response_code();$route=monitor_route();$method=monitor_method();
    if($status>=500){
        monitor_record_incident(['source'=>'http','category'=>'http-5xx','severity'=>$status>=503?'critical':'error','title'=>'HTTP '.$status.' response','message'=>$method.' '.$route.' returned HTTP '.$status,'route'=>$route,'method'=>$method,'http_status'=>$status,'fingerprint'=>monitor_fingerprint('http','http-5xx',$method.' '.$route,$route,'')]);
    }elseif(empty($GLOBALS['_monitor_request_error'])){
        monitor_resolve_route($route,$method,'Route completed successfully after the recorded failure.');
    }
}
function monitor_register_handlers(): void {
    if(!empty($GLOBALS['_monitor_handlers_registered']))return;
    $GLOBALS['_monitor_handlers_registered']=true;
    set_error_handler('monitor_error_handler');
    set_exception_handler('monitor_exception_handler');
    register_shutdown_function('monitor_shutdown_handler');
}

function monitor_check_severity(string $name): string {
    $criticalPatterns=['encryption','data key','private storage','production fail','transaction journal','order schema','identity decryptability','security','integrity','uniqueness','commerce safety','state synchronization','blocking-defect','boundary'];
    $n=strtolower($name);foreach($criticalPatterns as $p)if(str_contains($n,$p))return'critical';
    return 'warning';
}
function monitor_sync_release_checks(array $checks): void {
    $seen=[];
    foreach($checks as $check){
        $name=(string)($check['name']??'Unnamed check');
        $fp=monitor_fingerprint('health','release-check',$name,'','');$seen[$fp]=true;
        if(empty($check['ok'])){
            monitor_record_incident(['source'=>'health','category'=>'release-check','severity'=>monitor_check_severity($name),'title'=>$name,'message'=>(string)($check['detail']??'Health check failed.'),'route'=>'/admin/system','method'=>'SYSTEM','check_name'=>$name,'fingerprint'=>$fp]);
        }else monitor_resolve($fp,'Health check passed on a later scan.');
    }
}

function monitor_format_bytes(float|int $bytes): string {
    $bytes=max(0,(float)$bytes);$units=['B','KB','MB','GB','TB'];$i=0;while($bytes>=1024&&$i<count($units)-1){$bytes/=1024;$i++;}
    return number_format($bytes,$i===0?0:1).' '.$units[$i];
}
function monitor_ini_bytes(string $value): int {
    $v=trim($value);if($v===''||$v==='-1')return -1;$last=strtolower(substr($v,-1));$n=(float)$v;return (int)match($last){'g'=>$n*1073741824,'m'=>$n*1048576,'k'=>$n*1024,default=>$n};
}
function monitor_health_snapshot(bool $sync=true): array {
    $checks=function_exists('release_checks')?release_checks():[];
    if($sync)monitor_sync_release_checks($checks);
    $failed=array_values(array_filter($checks,fn($c)=>empty($c['ok'])));
    $inc=monitor_incidents();$active=array_values(array_filter($inc,fn($r)=>($r['status']??'active')==='active'));
    $critical=array_values(array_filter($active,fn($r)=>($r['severity']??'')==='critical'));
    $root=dirname(__DIR__);$diskFree=@disk_free_space($root);$diskTotal=@disk_total_space($root);
    $diskPct=($diskFree!==false&&$diskTotal)?round(($diskFree/$diskTotal)*100,1):null;
    $memoryLimit=(string)ini_get('memory_limit');$memoryBytes=monitor_ini_bytes($memoryLimit);$memoryNow=memory_get_usage(true);$memoryPct=$memoryBytes>0?round(($memoryNow/$memoryBytes)*100,1):null;
    $state=foundation_json_read(monitor_state_file(),[]);
    $status=count($critical)>0?'CRITICAL':(count($failed)>0||count($active)>0?'DEGRADED':'HEALTHY');
    return [
        'status'=>$status,'checks_total'=>count($checks),'checks_pass'=>count($checks)-count($failed),'checks_failed'=>count($failed),
        'active_incidents'=>count($active),'critical_incidents'=>count($critical),'resolved_incidents'=>count(array_filter($inc,fn($r)=>($r['status']??'')==='resolved')),
        'php_version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'memory_usage'=>monitor_format_bytes($memoryNow),'memory_limit'=>$memoryLimit,'memory_percent'=>$memoryPct,
        'disk_free'=>$diskFree===false?'Unavailable':monitor_format_bytes($diskFree),'disk_total'=>$diskTotal===false?'Unavailable':monitor_format_bytes($diskTotal),'disk_free_percent'=>$diskPct,
        'storage_writable'=>is_writable(dirname(__DIR__).'/storage'),'monitor_writable'=>is_writable(monitor_root()),'crypto_ready'=>confidential_key()!==null&&confidential_crypto_available(),
        'runtime_config'=>is_file(runtime_config_file()),'opcache'=>(bool)ini_get('opcache.enable'),'last_scan_at'=>$state['last_scan_at']??null,'checks'=>$checks,
        'extensions'=>['sodium'=>extension_loaded('sodium'),'openssl'=>extension_loaded('openssl'),'gd'=>extension_loaded('gd'),'mbstring'=>extension_loaded('mbstring'),'fileinfo'=>extension_loaded('fileinfo'),'json'=>extension_loaded('json')],
    ];
}
function monitor_refresh_incident_counts(array $snapshot): array {
    $inc=monitor_incidents();$active=array_values(array_filter($inc,fn($r)=>($r['status']??'active')==='active'));
    $critical=array_values(array_filter($active,fn($r)=>($r['severity']??'')==='critical'));
    $snapshot['active_incidents']=count($active);$snapshot['critical_incidents']=count($critical);$snapshot['resolved_incidents']=count(array_filter($inc,fn($r)=>($r['status']??'')==='resolved'));
    $snapshot['status']=count($critical)>0?'CRITICAL':(((int)($snapshot['checks_failed']??0)>0||count($active)>0)?'DEGRADED':'HEALTHY');
    return $snapshot;
}
function monitor_run_health_scan(bool $force=false): array {
    monitor_ensure_dirs();$state=foundation_json_read(monitor_state_file(),[]);$last=strtotime((string)($state['last_scan_at']??''));$interval=max(60,(int)($GLOBALS['app']['monitor_scan_interval_seconds']??300));
    if(!$force&&$last&&$last>time()-$interval&&!empty($state['last_snapshot'])&&is_array($state['last_snapshot']))return monitor_refresh_incident_counts($state['last_snapshot']);
    $snapshot=monitor_health_snapshot(true);
    $state['last_scan_at']=monitor_now();$snapshot['last_scan_at']=$state['last_scan_at'];$state['last_status']=$snapshot['status'];$state['last_failed_checks']=$snapshot['checks_failed'];$state['last_snapshot']=$snapshot;
    foundation_json_write(monitor_state_file(),$state,foundation_checksum(monitor_state_file()),'monitor.scan.state');
    return $snapshot;
}
function monitor_cleanup(bool $force=false): array {
    monitor_ensure_dirs();$state=foundation_json_read(monitor_state_file(),[]);$last=strtotime((string)($state['last_cleanup_at']??''));
    if(!$force&&$last&&$last>time()-86400)return['removed'=>0,'retained_critical'=>0,'skipped'=>true];
    $days=max(1,(int)($GLOBALS['app']['monitor_retention_days']??90));$cut=time()-($days*86400);$removed=0;$keptCritical=0;
    foundation_json_mutate(monitor_incidents_file(),function(array $rows)use($cut,&$removed,&$keptCritical){
        foreach($rows as $id=>$row){$critical=($row['severity']??'')==='critical'||!empty($row['critical_retained']);if($critical){$keptCritical++;continue;}if(($row['status']??'active')!=='resolved')continue;$ts=strtotime((string)($row['resolved_at']??$row['last_seen_at']??''));if($ts&&$ts<$cut){unset($rows[$id]);$removed++;}}
        return$rows;
    },'monitor.retention.cleanup');
    $state['last_cleanup_at']=monitor_now();$state['last_cleanup_removed']=$removed;foundation_json_write(monitor_state_file(),$state,foundation_checksum(monitor_state_file()),'monitor.cleanup.state');
    return['removed'=>$removed,'retained_critical'=>$keptCritical,'skipped'=>false];
}
function monitor_maybe_maintain(): void { try{monitor_cleanup(false);}catch(Throwable){} }
function monitor_list_incidents(string $status='',string $severity='',string $source='',string $q=''): array {
    $rows=array_values(monitor_incidents());$q=strtolower(trim($q));
    $rows=array_values(array_filter($rows,function($r)use($status,$severity,$source,$q){
        if($status!==''&&($r['status']??'')!==$status)return false;if($severity!==''&&($r['severity']??'')!==$severity)return false;if($source!==''&&($r['source']??'')!==$source)return false;
        if($q!==''&&!str_contains(strtolower((string)($r['title']??'').' '.(string)($r['message']??'').' '.(string)($r['route']??'')),$q))return false;return true;
    }));
    usort($rows,function($a,$b){$sa=($a['status']??'active')==='active'?0:1;$sb=($b['status']??'active')==='active'?0:1;if($sa!==$sb)return$sa<=>$sb;$sev=monitor_severity_rank((string)($b['severity']??'warning'))<=>monitor_severity_rank((string)($a['severity']??'warning'));if($sev!==0)return$sev;return strcmp((string)($b['last_seen_at']??''),(string)($a['last_seen_at']??''));});
    return $rows;
}
function monitor_recent_incidents(int $limit=6): array { return array_slice(monitor_list_incidents('active'),0,max(1,$limit)); }
