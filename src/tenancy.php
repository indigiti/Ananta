<?php
declare(strict_types=1);

/**
 * Ananta tenant identity foundation — encrypted, single-writer file-first pilot.
 * A supplier UUID is the tenant UUID. Platform-only provisioning and invitations
 * are deliberately separate from the public marketplace directory.
 */
function tenant_store_file(): string {
    $override=trim((string)getenv('ANANTA_TEST_TENANT_FILE'));
    if(PHP_SAPI==='cli'&&$override!==''&&($GLOBALS['app']['environment']??'')==='staging')return $override;
    return data_root().'/private-domain/tenancy/identity.json';
}
function tenant_store(): array {
    $d=confidential_json_read(tenant_store_file(),'ananta:tenant-identity:v1',[]);
    return array_replace(['schema_version'=>1,'tenants'=>[],'users'=>[],'memberships'=>[],'invites'=>[]],$d);
}
function tenant_mutate(callable $f,string $reason): array {
    if(confidential_key()===null)throw new RuntimeException('Ananta encryption key must be configured before supplier access.');
    return confidential_json_mutate(tenant_store_file(),'ananta:tenant-identity:v1',
        static function(array $d)use($f){$d=array_replace(['schema_version'=>1,'tenants'=>[],'users'=>[],'memberships'=>[],'invites'=>[]],$d);return $f($d);},
        'tenancy.'.$reason);
}
function tenant_valid_id(string $id): bool {
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id);
}
function tenant_user_email(string $email): string {
    $email=text_lower(trim($email));
    if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Provide a valid email address.');
    return $email;
}
function tenant_roles(): array {
    return [
        'owner'=>['offers.read','offers.draft','availability.report','branches.read','branches.manage','rfq.read','rfq.quote','team.read'],
        'manager'=>['offers.read','offers.draft','availability.report','branches.read','branches.manage','rfq.read','rfq.quote'],
        'inventory'=>['offers.read','availability.report','branches.read','rfq.read'],
        'viewer'=>['offers.read','branches.read','rfq.read'],
    ];
}
function tenant_require_admin(): void {
    if(!admin_logged_in())throw new RuntimeException('Platform administrator authorization required.');
}
function tenant_sync_supplier(string $supplierId,string $state): void {
    tenant_require_admin();
    if(!tenant_valid_id($supplierId))throw new InvalidArgumentException('Invalid supplier tenant identity.');
    if(!in_array($state,['draft','verified','suspended'],true))throw new InvalidArgumentException('Invalid tenant state.');
    tenant_mutate(static function(array $d)use($supplierId,$state){
        $d['tenants'][$supplierId]=[
            'id'=>$supplierId,'status'=>$state==='verified'?'active':$state,
            'updated_at'=>gmdate('c'),'created_at'=>$d['tenants'][$supplierId]['created_at']??gmdate('c'),
        ];
        return $d;
    },'supplier.sync');
}
function tenant_invite_issue(string $supplierId,string $email,string $role='owner'): string {
    tenant_require_admin();
    if(!tenant_valid_id($supplierId))throw new InvalidArgumentException('Invalid supplier.');
    $supplier=marketplace_supplier_by_id($supplierId);
    if(!$supplier||($supplier['status']??'')!=='verified')throw new RuntimeException('Verify the supplier before inviting team members.');
    if(!array_key_exists($role,tenant_roles()))throw new InvalidArgumentException('Invalid membership role.');
    $email=tenant_user_email($email);
    // Existing admin-curated suppliers may predate the tenancy identity store.
    if(!isset(tenant_store()['tenants'][$supplierId]))tenant_sync_supplier($supplierId,'verified');
    $token=bin2hex(random_bytes(32));
    $digest=hash('sha256',$token);
    tenant_mutate(static function(array $d)use($supplierId,$email,$role,$digest){
        $tenant=$d['tenants'][$supplierId]??null;
        if(!$tenant||($tenant['status']??'')!=='active')throw new RuntimeException('Tenant is not active.');
        foreach($d['memberships'] as $m){
            $u=$d['users'][$m['user_id']??'']??null;
            if(($m['tenant_id']??'')===$supplierId&&($m['active']??false)&&$u&&($u['email']??'')===$email){
                throw new RuntimeException('User already belongs to this supplier.');
            }
        }
        $d['invites'][$digest]=['tenant_id'=>$supplierId,'email'=>$email,'role'=>$role,'expires_at'=>time()+86400,'used_at'=>null,'created_at'=>gmdate('c')];
        return $d;
    },'invite.issue');
    audit_event('tenancy.invite.issue',['tenant_id'=>$supplierId,'role'=>$role]); // Never log token or email
    return $token; // Caller must show once to a trusted administrator.
}
function tenant_invite_preview(string $token): ?array {
    if(!preg_match('/^[0-9a-f]{64}$/D',$token))return null;
    $d=tenant_store();
    $invite=$d['invites'][hash('sha256',$token)]??null;
    if(!$invite||($invite['used_at']??null)!==null||($invite['expires_at']??0)<time())return null;
    $tenant=$d['tenants'][$invite['tenant_id']]??null;
    $supplier=marketplace_supplier_by_id((string)$invite['tenant_id']);
    if(!$tenant||($tenant['status']??'')!=='active'||!$supplier||($supplier['status']??'')!=='verified')return null;
    return ['tenant_id'=>$invite['tenant_id'],'supplier_name'=>$supplier['name'],'email'=>$invite['email'],'role'=>$invite['role']];
}
function tenant_invite_accept(string $token,string $name,string $password): string {
    if(!tenant_invite_preview($token))throw new RuntimeException('Invitation invalid, expired or supplier suspended.');
    $name=trim($name);
    if(text_len($name)<2||text_len($name)>100)throw new InvalidArgumentException('Enter a name between 2 and 100 characters.');
    if(strlen($password)<12||strlen($password)>200)throw new InvalidArgumentException('Password must be 12–200 characters.');
    $digest=hash('sha256',$token);
    $resultId='';
    tenant_mutate(static function(array $d)use($digest,$name,$password,&$resultId){
        $invite=$d['invites'][$digest]??null;
        if(!$invite||($invite['used_at']??null)!==null||($invite['expires_at']??0)<time())throw new RuntimeException('Invitation expired or already redeemed.');
        $supplier=marketplace_supplier_by_id((string)$invite['tenant_id']);
        if(($d['tenants'][$invite['tenant_id']]['status']??'')!=='active'||!$supplier||($supplier['status']??'')!=='verified')throw new RuntimeException('Supplier access is not active.');
        $account=null;
        foreach($d['users'] as $u)if(($u['email']??'')===$invite['email']){$account=$u;break;}
        if($account){
            // Existing identities must prove possession of the current password,
            // otherwise an invitation could hijack a previously registered user.
            if(!password_verify($password,(string)$account['password_hash']))throw new RuntimeException('An existing account must use its current password to accept this invitation.');
            $userId=$account['id'];
        }else{
            $userId=uuidv7();
            $d['users'][$userId]=['id'=>$userId,'name'=>$name,'email'=>$invite['email'],
                'password_hash'=>password_hash($password,PASSWORD_ARGON2ID),'active'=>true,'created_at'=>gmdate('c')];
        }
        $key=$invite['tenant_id'].':'.$userId;
        if(isset($d['memberships'][$key])&&($d['memberships'][$key]['active']??false))throw new RuntimeException('Membership already active.');
        $d['memberships'][$key]=['tenant_id'=>$invite['tenant_id'],'user_id'=>$userId,
            'role'=>$invite['role'],'active'=>true,'updated_at'=>gmdate('c')];
        $d['invites'][$digest]['used_at']=gmdate('c');
        $resultId=$userId;
        return $d;
    },'invite.accept');
    audit_event('tenancy.invite.accept',['user_id'=>$resultId]); // no raw token/password
    return $resultId;
}
function tenant_login(string $email,string $password): bool {
    try{$email=tenant_user_email($email);}catch(Throwable){return false;}
    $status=api_rate_limit('supplier-login:'.hash('sha256',$email),8,900);
    if(!$status['allowed'])return false;
    $d=tenant_store();$user=null;
    foreach($d['users'] as $u)if(($u['email']??'')===$email){$user=$u;break;}
    if(!$user||!($user['active']??false)||!password_verify($password,(string)($user['password_hash']??'')))return false;
    $active=[];
    foreach($d['memberships'] as $m){
        if(($m['user_id']??'')!==$user['id']||!($m['active']??false))continue;
        $supplier=marketplace_supplier_by_id((string)$m['tenant_id']);
        if(($d['tenants'][$m['tenant_id']]['status']??'')==='active'&&$supplier&&($supplier['status']??'')==='verified')$active[]=$m['tenant_id'];
    }
    if(!$active)return false;
    session_regenerate_id(true);
    $_SESSION['supplier_user']=['user_id'=>$user['id'],'tenant_id'=>$active[0]];
    $_SESSION['_created_at']=time();$_SESSION['_last_activity']=time();
    audit_event('tenancy.login',['tenant_id'=>$active[0],'user_id'=>$user['id']]);
    return true;
}
function tenant_identity(): ?array {
    $session=$_SESSION['supplier_user']??null;
    if(!is_array($session)||!tenant_valid_id((string)($session['user_id']??''))||!tenant_valid_id((string)($session['tenant_id']??'')))return null;
    $d=tenant_store();
    $u=$d['users'][$session['user_id']]??null;
    $m=$d['memberships'][$session['tenant_id'].':'.$session['user_id']]??null;
    $t=$d['tenants'][$session['tenant_id']]??null;
    $s=marketplace_supplier_by_id($session['tenant_id']);
    if(!$u||!($u['active']??false)||!$m||!($m['active']??false)||!$t||($t['status']??'')!=='active'||!$s||($s['status']??'')!=='verified'){
        // Do not let an old session regain access after later reactivation.
        unset($_SESSION['supplier_user']);
        return null;
    }
    if(!isset(tenant_roles()[$m['role']??'']))return null;
    return ['id'=>$u['id'],'email'=>$u['email'],'name'=>$u['name'],'tenant_id'=>$s['id'],
        'supplier_name'=>$s['name'],'role'=>$m['role']];
}
function tenant_can(string $permission,?string $tenantId=null): bool {
    $identity=tenant_identity();
    return $identity!==null&&($tenantId===null||hash_equals($identity['tenant_id'],$tenantId))
        &&in_array($permission,tenant_roles()[$identity['role']]??[],true);
}
function tenant_require(string $permission,string $tenantId): array {
    $identity=tenant_identity();
    if(!$identity||!tenant_can($permission,$tenantId))throw new RuntimeException('Supplier access denied.');
    return $identity;
}
function tenant_switch(string $tenantId): bool {
    if(!tenant_valid_id($tenantId)||empty($_SESSION['supplier_user']['user_id']))return false;
    $original=$_SESSION['supplier_user'];
    $_SESSION['supplier_user']['tenant_id']=$tenantId;
    if(!tenant_identity()){
        // A denied tenant-switch must not destroy the caller's previously valid session.
        $_SESSION['supplier_user']=$original;
        return false;
    }
    session_regenerate_id(true);
    return true;
}
function tenant_logout(): void {
    $id=tenant_identity();
    if($id)audit_event('tenancy.logout',['tenant_id'=>$id['tenant_id'],'user_id'=>$id['id']]);
    unset($_SESSION['supplier_user']);
    session_regenerate_id(true);
}
function tenant_revoke(string $tenantId,string $userId): void {
    tenant_require_admin();
    tenant_mutate(static function(array $d)use($tenantId,$userId){
        $key=$tenantId.':'.$userId;
        if(!isset($d['memberships'][$key]))throw new RuntimeException('Membership not found.');
        $d['memberships'][$key]['active']=false;
        $d['memberships'][$key]['updated_at']=gmdate('c');
        return $d;
    },'membership.revoke');
    audit_event('tenancy.membership.revoke',['tenant_id'=>$tenantId,'user_id'=>$userId]);
}
