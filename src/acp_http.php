<?php
declare(strict_types=1);

/**
 * ACP API over Cloudways PHP, mounted at /ananta/acp/checkout_sessions.
 * Never enables cross-site cookie authentication or bypasses API HMAC.
 */
function acp_http_headers(): array {
    $names=['content-type'=>'CONTENT_TYPE','authorization'=>'HTTP_AUTHORIZATION','api-version'=>'HTTP_API_VERSION',
        'timestamp'=>'HTTP_TIMESTAMP','signature'=>'HTTP_SIGNATURE',
        'request-id'=>'HTTP_REQUEST_ID','idempotency-key'=>'HTTP_IDEMPOTENCY_KEY'];
    $h=[];
    foreach($names as $key=>$server)$h[$key]=(string)($_SERVER[$server]??'');
    if($h['authorization']==='')$h['authorization']=(string)($_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
    if($h['authorization']===''&&function_exists('getallheaders')){
        foreach(getallheaders() as $k=>$value)if(strtolower((string)$k)==='authorization'){
            $h['authorization']=(string)$value;break;
        }
    }
    return $h;
}
function acp_http_execute(string $method,string $route,string $raw,array $headers): array {
    acp_api_authorize($headers,$method,$raw);
    if(strlen($raw)>65536)throw new InvalidArgumentException('ACP request exceeds 64 KB.');
    $body=[];
    if($method!=='GET'){
        if(trim($raw)==='')throw new InvalidArgumentException('ACP request JSON required.');
        $body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($body)||!str_starts_with(ltrim($raw),'{'))throw new InvalidArgumentException('Invalid ACP JSON object.');
    }
    $key=(string)($headers['idempotency-key']??'');
    if($route==='/checkout_sessions'&&$method==='POST')
        return ['http'=>201,'response'=>acp_session_create($body,$key)];
    if(!preg_match('#^/checkout_sessions/([0-9a-f-]{36})(?:/(complete|cancel))?$#D',$route,$m))
        return ['http'=>404,'response'=>['type'=>'invalid_request','code'=>'not_found','message'=>'Unknown ACP endpoint.']];
    $id=$m[1];$action=$m[2]??'';
    if($method==='GET'&&$action===''){
        $session=acp_session_get($id);
        return $session?['http'=>200,'response'=>$session]:
            ['http'=>404,'response'=>['type'=>'invalid_request','code'=>'not_found','message'=>'Checkout session not found.']];
    }
    if($method!=='POST')return ['http'=>405,'response'=>[
        'type'=>'invalid_request','code'=>'invalid_method','message'=>'Invalid checkout method.']];
    if($action==='')return ['http'=>200,'response'=>acp_session_update($id,$body,$key)];
    if($action==='cancel')return ['http'=>200,'response'=>acp_session_cancel($id,$body,$key)];
    if($action==='complete')return ['http'=>503,'response'=>acp_session_complete($id,$body,$key)];
    return ['http'=>404,'response'=>['type'=>'invalid_request','code'=>'not_found','message'=>'Unknown ACP operation.']];
}
function acp_http_handle(string $method,string $path): never {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    $length=(int)($_SERVER['CONTENT_LENGTH']??0);
    if($length>65536){
        http_response_code(413);echo '{"type":"invalid_request","code":"too_large","message":"Request too large"}';exit;
    }
    $raw=file_get_contents('php://input',false,null,0,65537);
    if(!is_string($raw)||strlen($raw)>65536){
        http_response_code(413);echo '{"type":"invalid_request","code":"too_large","message":"Request too large"}';exit;
    }
    if(!request_is_https()){
        http_response_code(403);echo '{"type":"invalid_request","code":"https_required","message":"HTTPS required"}';exit;
    }
    $h=acp_http_headers();
    $reqId=$h['request-id']??'';
    if(preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$reqId))header('Request-Id: '.$reqId);
    $idem=$h['idempotency-key']??'';
    if(preg_match('/^[A-Za-z0-9._:-]{12,128}$/D',$idem))header('Idempotency-Key: '.$idem);
    try{
        $r=acp_http_execute($method,substr($path,4),$raw,$h);
        $status=$r['http'];$response=$r['response'];
    }catch(UnexpectedValueException $e){
        $status=401;$response=['type'=>'invalid_request','code'=>'unauthorized',
            'message'=>'ACP authentication or signature failed.'];
    }catch(JsonException|InvalidArgumentException $e){
        $status=422;$response=['type'=>'invalid_request','code'=>'invalid_request',
            'message'=>'Invalid or unauthorized ACP request.'];
    }catch(RuntimeException $e){
        $status=503;$response=['type'=>'service_unavailable','code'=>'not_ready',
            'message'=>'ACP staging checkout or external activation is unavailable.'];
    }catch(Throwable $e){
        error_log('ACP request failed: '.get_class($e));
        $status=500;$response=['type'=>'processing_error','code'=>'unexpected',
            'message'=>'Unable to process ACP checkout request.'];
    }
    http_response_code($status);
    echo json_encode($response,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
