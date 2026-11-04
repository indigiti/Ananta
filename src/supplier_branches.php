<?php
declare(strict_types=1);

/**
 * SupplierOS branch records: encrypted private storage, tenant-scoped access,
 * and separate platform review. A verified supplier is NOT an approved branch.
 * No external checkout or stock reservation is enabled by these records.
 */
function supplier_branches_file(): string {
    $test=trim((string)getenv('ANANTA_TEST_BRANCHES_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$test!=='')return $test;
    return data_root().'/private-domain/tenancy/branches.json';
}
function supplier_branches_store(): array {
    $value=confidential_json_read(supplier_branches_file(),'ananta:supplier-branches:v1',[]);
    return array_replace(['schema_version'=>1,'branches'=>[]],$value);
}
function supplier_branches_mutate(callable $mutator,string $reason): array {
    if(confidential_key()===null)throw new RuntimeException('Protected storage encryption key required.');
    return confidential_json_mutate(supplier_branches_file(),'ananta:supplier-branches:v1',static function(array $old)use($mutator){
        return $mutator(array_replace(['schema_version'=>1,'branches'=>[]],$old));
    },'supplier.branches.'.$reason);
}
function supplier_branch_by_id(string $branchId): ?array {
    if(!tenant_valid_id($branchId))return null;
    $row=supplier_branches_store()['branches'][$branchId]??null;
    return is_array($row)?$row:null;
}
/** Platform-private; never return this directly to a public endpoint. */
function supplier_branches_all(): array {
    tenant_require_admin();
    $rows=array_values(supplier_branches_store()['branches']);
    usort($rows,static fn($a,$b)=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));
    return $rows;
}
function supplier_branches_for_tenant(string $tenantId): array {
    if(!admin_logged_in())tenant_require('branches.read',$tenantId);
    if(!tenant_valid_id($tenantId))throw new InvalidArgumentException('Invalid supplier identifier.');
    $rows=array_values(array_filter(supplier_branches_store()['branches'],
        static fn($b)=>($b['tenant_id']??'')===$tenantId));
    usort($rows,static fn($a,$b)=>strcmp((string)$a['name'],(string)$b['name']));
    return $rows;
}
function supplier_branch_validate_fields(array $input): array {
    $name=trim((string)($input['name']??''));
    $city=trim((string)($input['city']??''));
    $address=trim((string)($input['address']??''));
    $pincode=trim((string)($input['pincode']??''));
    if(text_len($name)<2||text_len($name)>110||text_len($city)<2||text_len($city)>100)
        throw new InvalidArgumentException('Branch name and city must contain 2–110 characters.');
    if(text_len($address)>250)throw new InvalidArgumentException('Branch address is too long.');
    if($pincode!==''&&!preg_match('/^[1-9][0-9]{5}$/D',$pincode))
        throw new InvalidArgumentException('Use a valid 6-digit Indian PIN or leave it blank.');
    return ['name'=>$name,'city'=>$city,'address'=>$address,'pincode'=>$pincode];
}
/** Owner/manager can create or revise branches, but any edit clears previous approval. */
function supplier_branch_save(string $tenantId,array $input): string {
    tenant_require('branches.manage',$tenantId);
    $id=strtolower(trim((string)($input['id']??'')));
    if($id!==''&&!tenant_valid_id($id))throw new InvalidArgumentException('Invalid branch identifier.');
    $values=supplier_branch_validate_fields($input);
    $now=gmdate('c');$saved=$id!==''?$id:uuidv7();
    supplier_branches_mutate(static function(array $store)use($tenantId,$id,$saved,$values,$now){
        $found=$store['branches'][$saved]??null;
        if($id!==''&&!is_array($found))throw new RuntimeException('Branch not found.');
        if($found&&($found['tenant_id']??'')!==$tenantId)throw new RuntimeException('Cross-tenant branch modification denied.');
        if(!$found&&count(array_filter($store['branches'],static fn($b)=>($b['tenant_id']??'')===$tenantId))>=25)
            throw new RuntimeException('Branch limit reached for this pilot.');
        $supplier=marketplace_supplier_by_id($tenantId);
        if(!$supplier||($supplier['status']??'')!=='verified')throw new RuntimeException('Supplier verification is required.');
        $row=($found??[])+['id'=>$saved,'tenant_id'=>$tenantId,'created_at'=>$now];
        foreach($values as $key=>$value)$row[$key]=$value;
        if(!$found||array_intersect_assoc($values,$found)!==$values){
            $row['state']='pending';$row['reviewed_at']=null;$row['review_evidence']='';
        }
        $row['updated_at']=$now;
        $store['branches'][$saved]=$row;
        return $store;
    },'save');
    $user=tenant_identity();
    audit_event('supplier.branch.save',['tenant_id'=>$tenantId,'branch_id'=>$saved,'actor_id'=>$user['id']??null]);
    return $saved;
}
/** Platform reviewer alone approves, rejects or suspends a nursery branch. */
function supplier_branch_review(string $tenantId,string $branchId,string $decision,string $evidence): void {
    tenant_require_admin();
    if(!tenant_valid_id($tenantId)||!tenant_valid_id($branchId))throw new InvalidArgumentException('Invalid branch identity.');
    if(!in_array($decision,['approved','rejected','suspended'],true))throw new InvalidArgumentException('Invalid review outcome.');
    $evidence=trim($evidence);
    if(text_len($evidence)<8||text_len($evidence)>200)throw new InvalidArgumentException('An internal review reference of 8–200 characters is required.');
    supplier_branches_mutate(static function(array $store)use($tenantId,$branchId,$decision,$evidence){
        $row=$store['branches'][$branchId]??null;
        if(!$row||($row['tenant_id']??'')!==$tenantId)throw new RuntimeException('Branch does not belong to supplier.');
        $supplier=marketplace_supplier_by_id($tenantId);
        if($decision==='approved'&&(!$supplier||($supplier['status']??'')!=='verified'))
            throw new RuntimeException('Supplier verification is required before branch approval.');
        $row['state']=$decision;$row['review_evidence']=$evidence;$row['reviewed_at']=gmdate('c');$row['updated_at']=gmdate('c');
        $store['branches'][$branchId]=$row;
        return $store;
    },'review');
    audit_event('supplier.branch.review',['tenant_id'=>$tenantId,'branch_id'=>$branchId,'decision'=>$decision]);
}
/** Server-side offer binding: no user-supplied tenant may pick another supplier's branch. */
function supplier_branch_for_offer(string $tenantId,string $branchId,bool $requireApproved): array {
    $row=supplier_branch_by_id($branchId);
    if(!$row||($row['tenant_id']??'')!==$tenantId)throw new RuntimeException('Branch does not belong to the selected nursery.');
    $state=$row['state']??'pending';
    if($requireApproved&&$state!=='approved')throw new RuntimeException('Platform branch approval is required for published offers.');
    if(in_array($state,['suspended','rejected'],true))throw new RuntimeException('Suspended or rejected branch cannot accept offers.');
    return $row;
}
/** Public projections never contain exact address, PIN or private review evidence. */
function supplier_branch_public(string $tenantId,string $branchId): ?array {
    // Public discovery must fail closed, not take down the storefront, if private
    // storage is temporarily unreadable or its encryption key is unavailable.
    try{$row=supplier_branch_by_id($branchId);}catch(Throwable){return null;}
    if(!$row||($row['tenant_id']??'')!==$tenantId||($row['state']??'')!=='approved')return null;
    return ['id'=>$row['id'],'name'=>$row['name'],'city'=>$row['city']];
}
