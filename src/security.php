<?php
declare(strict_types=1);
function security_rate_file(): string { return data_root().'/private-domain/security/api-rate.json'; }
function security_rate_key(string $bucket): string { $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');return hash('sha256',$bucket.'|'.$ip); }
function api_rate_limit(string $bucket,int $limit=120,int $window=60): array {
    $limit=max(1,$limit);$window=max(10,$window);$now=time();$key=security_rate_key($bucket);$result=['allowed'=>true,'remaining'=>$limit,'retry_after'=>0];
    foundation_json_mutate(security_rate_file(),function(array $rows)use($key,$limit,$window,$now,&$result){foreach($rows as $k=>$v){$hits=array_values(array_filter(array_map('intval',$v['hits']??[]),fn($t)=>$now-$t<$window));if($hits)$rows[$k]=['hits'=>$hits,'updated_at'=>gmdate('c')];else unset($rows[$k]);}$hits=array_values(array_filter(array_map('intval',$rows[$key]['hits']??[]),fn($t)=>$now-$t<$window));if(count($hits)>=$limit){$result=['allowed'=>false,'remaining'=>0,'retry_after'=>max(1,$window-($now-min($hits)))];$rows[$key]=['hits'=>$hits,'updated_at'=>gmdate('c')];return$rows;}$hits[]=$now;$rows[$key]=['hits'=>$hits,'updated_at'=>gmdate('c')];$result=['allowed'=>true,'remaining'=>max(0,$limit-count($hits)),'retry_after'=>0];return$rows;},'security.api_rate');return$result;
}
function enforce_api_rate_limit(string $bucket,int $limit=120,int $window=60): void { $r=api_rate_limit($bucket,$limit,$window);header('X-RateLimit-Limit: '.$limit);header('X-RateLimit-Remaining: '.$r['remaining']);if(!$r['allowed']){header('Retry-After: '.$r['retry_after']);http_response_code(429);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>'rate_limited','retry_after'=>$r['retry_after']]);exit;} }
function security_release_checks(): array { $private=realpath(data_root().'/private-domain')?:'';$public=realpath(dirname(__DIR__).'/public')?:'';$boundary=$private!==''&&!str_starts_with($private,$public);$headers=['Content-Security-Policy','X-Content-Type-Options','Referrer-Policy','Permissions-Policy'];return [
 ['name'=>'Security private storage boundary','ok'=>$boundary,'detail'=>'Private encrypted stores remain outside the public document root.'],
 ['name'=>'API rate-limit persistence','ok'=>is_dir(dirname(security_rate_file()))||@mkdir(dirname(security_rate_file()),0750,true),'detail'=>'API/agent throttling store is available in protected private storage.'],
 ['name'=>'Staging production fail-closed','ok'=>($GLOBALS['app']['environment']??'staging')!=='production','detail'=>'File-authoritative staging package refuses production mode.'],
]; }
