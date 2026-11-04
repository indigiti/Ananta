<?php
declare(strict_types=1);

/**
 * B8.1 Ananta Connect: encrypted sender registry and consent ledger.
 * No outbound API calls, automated replies or webhook side effects in B8.1.
 * Meta credentials remain in server environment, never in source or UI.
 */
function connect_file(): string {
    $override=trim((string)getenv('ANANTA_TEST_CONNECT_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$override!=='')return $override;
    return data_root().'/private-domain/connect/registry.json';
}
function connect_store(): array {
    return array_replace(['version'=>1,'accounts'=>[],'consents'=>[],'events'=>[]],
        confidential_json_read(connect_file(),'ananta:connect:v1',[]));
}
function connect_mutate(callable $fn,string $reason): array {
    if(confidential_key()===null)throw new RuntimeException('Ananta private encryption key is required.');
    return confidential_json_mutate(connect_file(),'ananta:connect:v1',
        static function(array $data)use($fn) {
            return $fn(array_replace(['version'=>1,'accounts'=>[],'consents'=>[],'events'=>[]],$data));
        },'connect.'.$reason);
}
function connect_validate_tenant(string $tenant): void {
    if($tenant==='platform')return;
    if(!tenant_valid_id($tenant))throw new InvalidArgumentException('Invalid nursery account identity.');
    $s=marketplace_supplier_by_id($tenant);
    if(!$s||($s['status']??'')!=='verified')throw new InvalidArgumentException('Nursery must be verified before connecting a sender.');
}
function connect_env_reference(string $value,string $prefix): string {
    if(!preg_match('/^'.preg_quote($prefix,'/').'[A-Z][A-Z0-9_]{3,64}$/D',$value))
        throw new InvalidArgumentException('Use a secure environment variable name starting with '.$prefix);
    return $value;
}
function connect_account_save(array $input): string {
    require_admin();
    $tenant=(string)($input['tenant_id']??'');
    connect_validate_tenant($tenant);
    $name=trim((string)($input['name']??''));
    if(text_len($name)<3||text_len($name)>90)throw new InvalidArgumentException('Sender name must be 3–90 characters.');
    $phoneId=trim((string)($input['phone_number_id']??''));
    $wabaId=trim((string)($input['waba_id']??''));
    if(!preg_match('/^[0-9]{6,30}$/D',$phoneId)||!preg_match('/^[0-9]{6,30}$/D',$wabaId))
        throw new InvalidArgumentException('Meta Phone Number ID and WhatsApp Business Account ID must contain 6–30 digits.');
    $tokenEnv=connect_env_reference(trim((string)($input['token_env']??'')),'ANANTA_WA_TOKEN_');
    $secretEnv=connect_env_reference(trim((string)($input['secret_env']??'')),'ANANTA_WA_SECRET_');
    $verifyEnv=connect_env_reference(trim((string)($input['verify_env']??'')),'ANANTA_WA_VERIFY_');
    $id=$tenant; // Exactly one configured sender per tenant during the pilot.
    $now=gmdate('c');
    connect_mutate(static function(array $data)use($tenant,$name,$phoneId,$wabaId,$tokenEnv,$secretEnv,$verifyEnv,$id,$now){
        if(count($data['accounts'])>=100&&!isset($data['accounts'][$id]))
            throw new RuntimeException('Connect pilot sender limit reached.');
        foreach($data['accounts'] as $otherId=>$record) {
            if($otherId!==$id&&($record['phone_number_id']??'')===$phoneId)
                throw new RuntimeException('Meta Phone Number ID is assigned to another tenant.');
        }
        $data['accounts'][$id]=[
            'tenant_id'=>$tenant,'name'=>$name,'phone_number_id'=>$phoneId,'waba_id'=>$wabaId,
            'token_env'=>$tokenEnv,'secret_env'=>$secretEnv,'verify_env'=>$verifyEnv,
            'enabled'=>false,'send_enabled'=>false,
            'created_at'=>$data['accounts'][$id]['created_at']??$now,'updated_at'=>$now,
        ];
        return $data;
    },'account.save');
    audit_event('connect.account.configure',['tenant_id'=>$id,'phone_id_hash'=>substr(hash('sha256',$phoneId),0,16)]);
    return $id;
}
function connect_account_status(array $account): array {
    $token=getenv((string)($account['token_env']??''));
    $secret=getenv((string)($account['secret_env']??''));
    $verify=getenv((string)($account['verify_env']??''));
    return [
        'tenant_id'=>$account['tenant_id'],'name'=>$account['name'],
        'configured'=>true,'token_ready'=>is_string($token)&&strlen(trim($token))>=20,
        'app_secret_ready'=>is_string($secret)&&strlen(trim($secret))>=20,
        'verify_token_ready'=>is_string($verify)&&strlen(trim($verify))>=20,
        'send_enabled'=>false,'connected'=>false,
    ];
}
function connect_sender_for_tenant(string $tenantId): ?array {
    if($tenantId!=='platform')tenant_require('rfq.read',$tenantId);
    elseif(!admin_logged_in())throw new RuntimeException('Platform access required.');
    $row=connect_store()['accounts'][$tenantId]??null;
    return $row?connect_account_status($row):null;
}
function connect_normalize_phone(string $phone): string {
    $phone=preg_replace('/[\s()+-]/','',$phone);
    if(!is_string($phone)||!preg_match('/^[1-9][0-9]{7,14}$/D',$phone))
        throw new InvalidArgumentException('Use an international phone number with country code.');
    return $phone;
}
function connect_consent_key(string $tenant,string $phone,string $purpose): string {
    return hash('sha256',$tenant.'|'.$phone.'|'.$purpose);
}
function connect_consent_record(string $tenant,string $phone,string $purpose,string $decision,string $source,string $evidence): void {
    if(!admin_logged_in()){
        if($tenant==='platform')throw new RuntimeException('Platform consent requires admin.');
        tenant_require('rfq.read',$tenant);
        $identity=tenant_identity();
        if(!in_array($identity['role'],['owner','manager'],true))
            throw new RuntimeException('Only the nursery owner or manager may record consent.');
    }
    connect_validate_tenant($tenant);
    $phone=connect_normalize_phone($phone);
    if(!in_array($purpose,['rfq_updates','service','marketing'],true))
        throw new InvalidArgumentException('Invalid messaging purpose.');
    if(!in_array($decision,['opt_in','opt_out'],true))
        throw new InvalidArgumentException('Invalid consent decision.');
    if(!in_array($source,['web_form','signed_form','customer_message','manual_evidence'],true))
        throw new InvalidArgumentException('Select a supported consent source.');
    $evidence=trim($evidence);
    if(text_len($evidence)<8||text_len($evidence)>180)
        throw new InvalidArgumentException('Consent requires an 8–180 character evidence reference.');
    $key=connect_consent_key($tenant,$phone,$purpose);$now=gmdate('c');
    connect_mutate(static function(array $data)use($key,$tenant,$phone,$purpose,$decision,$source,$evidence,$now){
        if(count($data['consents'])>=3000&&!isset($data['consents'][$key]))
            throw new RuntimeException('Consent register pilot capacity reached.');
        $prev=$data['consents'][$key]??null;
        $data['consents'][$key]=[
            'tenant_id'=>$tenant,'phone'=>$phone,'purpose'=>$purpose,'status'=>$decision,
            'source'=>$source,'evidence'=>$evidence,'updated_at'=>$now,
            'created_at'=>$prev['created_at']??$now,
        ];
        if(count($data['events'])>=10000)throw new RuntimeException('Consent event retention cap reached. Archive securely before continuing.');
        $data['events'][]=[
            'id'=>uuidv7(),'kind'=>'consent','key'=>$key,'tenant_id'=>$tenant,
            'purpose'=>$purpose,'decision'=>$decision,'at'=>$now,
        ];
        return $data;
    },'consent.record');
    audit_event('connect.consent.record',['tenant_id'=>$tenant,'purpose'=>$purpose,'decision'=>$decision,'contact_hash'=>substr(hash('sha256',$phone),0,12)]);
}
function connect_consent_status(string $tenant,string $phone,string $purpose): string {
    $phone=connect_normalize_phone($phone);
    $entry=connect_store()['consents'][connect_consent_key($tenant,$phone,$purpose)]??null;
    return (string)($entry['status']??'unknown');
}
function connect_message_allowed(string $tenant,string $phone,string $purpose): bool {
    // Fail closed in B8.1: provider is deliberately never enabled.
    if(!isset(connect_store()['accounts'][$tenant]))return false;
    return false;
}
/** HMAC helper for B8.2; no live webhook route or message writes in B8.1. */
function connect_verify_meta_signature(string $payload,string $header,string $secret): bool {
    if(strlen($secret)<20||!preg_match('/^sha256=([0-9a-f]{64})$/D',$header,$m))return false;
    return hash_equals($m[1],hash_hmac('sha256',$payload,$secret));
}
function connect_admin_summary(): array {
    require_admin();
    $store=connect_store();
    return ['accounts'=>array_map('connect_account_status',array_values($store['accounts'])),
        'consent_total'=>count($store['consents']),
        'opted_out'=>count(array_filter($store['consents'],static fn($c)=>($c['status']??'')==='opt_out')),
        'send_enabled'=>false];
}
