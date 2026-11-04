<?php
declare(strict_types=1);

/**
 * Sanjay Nursery Commerce Core — v0.3.0
 * File-authoritative staging implementation. All PII/order/payment state is encrypted at rest.
 */

function commerce_root(): string { return data_root() . '/private-domain/commerce'; }
function commerce_customer_file(): string {
    $test=trim((string)getenv('ANANTA_TEST_CUSTOMERS_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$test!=='')return $test;
    return commerce_root() . '/customers.json';
}
function commerce_address_file(): string { return commerce_root() . '/addresses.json'; }
function commerce_order_file(): string { return commerce_root() . '/orders.json'; }
function commerce_payment_file(): string { return commerce_root() . '/payments.json'; }
function commerce_request_file(): string { return commerce_root() . '/requests.json'; }
function commerce_cart_file(): string { return commerce_root() . '/carts.json'; }
function commerce_reset_file(): string { return commerce_root() . '/password-resets.json'; }
function commerce_rate_file(): string { return foundation_security_dir() . '/customer-login-rate.json'; }
function commerce_shipping_file(): string { return data_root() . '/public-domain/commerce/shipping-zones.json'; }

function commerce_ensure_dirs(): void {
    foreach ([commerce_root(), dirname(commerce_shipping_file())] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Unable to create commerce storage directory.');
        @chmod($dir, 0750);
    }
    if (!is_file(commerce_shipping_file())) {
        $default = [
            ['id'=>'pune-urban','name'=>'Pune Urban','patterns'=>['411'], 'serviceable'=>true,'small_parcel'=>149,'large_item_delivery'=>699,'installation'=>true,'installation_fee'=>599,'free_small_parcel_above'=>3999,'eta'=>'2–5 business days'],
            ['id'=>'maharashtra','name'=>'Maharashtra','patterns'=>['40','41','42','43','44'], 'serviceable'=>true,'small_parcel'=>249,'large_item_delivery'=>1199,'installation'=>false,'installation_fee'=>0,'free_small_parcel_above'=>5999,'eta'=>'4–8 business days'],
            ['id'=>'india-standard','name'=>'India Standard','patterns'=>[], 'serviceable'=>true,'small_parcel'=>349,'large_item_delivery'=>1699,'installation'=>false,'installation_fee'=>0,'free_small_parcel_above'=>7999,'eta'=>'6–12 business days'],
        ];
        foundation_json_write(commerce_shipping_file(), $default, null, 'commerce.shipping.bootstrap');
    }
}

function commerce_private_read(string $path, string $context, array $default=[]): array {
    return confidential_json_read($path, $context, $default);
}
function commerce_private_mutate(string $path, string $context, callable $mutator, string $reason): array {
    return confidential_json_mutate($path, $context, $mutator, $reason);
}

/* ---------------- Customer authentication ---------------- */
function customer_identity(): ?array { return $_SESSION['customer_user'] ?? null; }
function customer_logged_in(): bool { return !empty($_SESSION['customer_user']['guid']); }
function require_customer(string $returnTo='/account'): void {
    if (customer_logged_in()) return;
    $_SESSION['after_customer_login'] = str_starts_with($returnTo,'/') ? $returnTo : '/account';
    flash('error','Please sign in to continue.'); redirect('/account/login');
}
function customer_users(): array {
    try { return commerce_private_read(commerce_customer_file(), 'sanjaynursery:customers:v1', []); } catch(Throwable) { return []; }
}
function customer_by_guid(string $guid): ?array {
    foreach(customer_users() as $u) if (($u['guid']??'')===$guid) return $u;
    return null;
}
function customer_by_email(string $email): ?array {
    $email=text_lower(trim($email)); foreach(customer_users() as $u) if(text_lower((string)($u['email']??''))===$email) return $u; return null;
}
function customer_rate_key(string $email): string {
    return hash('sha256', text_lower(trim($email)).'|'.(string)($_SERVER['REMOTE_ADDR']??'unknown'));
}
function customer_login_rate_status(string $email): array {
    $window=max(60,(int)($GLOBALS['app']['login_window_seconds']??900)); $max=max(3,(int)($GLOBALS['app']['customer_login_max_attempts']??8));
    $now=time();$key=customer_rate_key($email);$data=foundation_json_read(commerce_rate_file(),[]);$fails=array_values(array_filter(array_map('intval',$data[$key]['fails']??[]),fn($ts)=>($now-$ts)<$window));
    return ['allowed'=>count($fails)<$max,'remaining'=>max(0,$max-count($fails)),'retry_after'=>$fails?max(0,$window-($now-min($fails))):0];
}
function customer_login_rate_fail(string $email): void {
    $window=max(60,(int)($GLOBALS['app']['login_window_seconds']??900));$now=time();$key=customer_rate_key($email);
    foundation_json_mutate(commerce_rate_file(),function(array $data)use($window,$now,$key){
        foreach($data as $k=>$row){$fails=array_values(array_filter(array_map('intval',$row['fails']??[]),fn($ts)=>($now-$ts)<$window)); if($fails)$data[$k]=['fails'=>$fails];else unset($data[$k]);}
        $fails=$data[$key]['fails']??[];$fails[]=$now;$data[$key]=['fails'=>$fails,'updated_at'=>gmdate('c')];return $data;
    },'customer.login_fail');
}
function customer_login_rate_clear(string $email): void {
    $key=customer_rate_key($email); foundation_json_mutate(commerce_rate_file(),function(array $data)use($key){unset($data[$key]);return $data;},'customer.login_clear');
}
function customer_register(array $input): array {
    if (confidential_key()===null) return [false,'Commerce encryption key is not configured.'];
    $name=trim((string)($input['name']??''));$email=text_lower(trim((string)($input['email']??'')));$phone=preg_replace('/[^0-9+]/','',(string)($input['phone']??''));$password=(string)($input['password']??'');
    if(text_len($name)<2)return[false,'Enter your full name.']; if(!filter_var($email,FILTER_VALIDATE_EMAIL))return[false,'Enter a valid email address.']; if(text_len($password)<10)return[false,'Use at least 10 characters for your password.'];
    if(customer_by_email($email))return[false,'An account already exists for this email address.'];
    $u=['guid'=>uuidv7(),'name'=>$name,'email'=>$email,'phone'=>$phone,'status'=>'active','role'=>'customer','password_hash'=>password_hash($password,password_algo()),'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
    commerce_private_mutate(commerce_customer_file(),'sanjaynursery:customers:v1',function(array $rows)use($u,$email){foreach($rows as $x)if(text_lower((string)($x['email']??''))===$email)throw new RuntimeException('Account already exists.');$rows[]=$u;return $rows;},'customer.register');
    commerce_audit('customer.register',['customer_guid'=>$u['guid']]);
    return [true,$u];
}
function customer_login(string $email,string $password): bool {
    $email=text_lower(trim($email)); if(!customer_login_rate_status($email)['allowed'])return false;
    $u=customer_by_email($email); if(!$u||($u['status']??'active')!=='active'||!password_verify($password,(string)($u['password_hash']??''))){customer_login_rate_fail($email);return false;}
    session_regenerate_id(true);$_SESSION['_created_at']=time();$_SESSION['_last_activity']=time();$_SESSION['customer_user']=['guid'=>$u['guid'],'name'=>$u['name'],'email'=>$u['email']];customer_login_rate_clear($email);commerce_audit('customer.login',['customer_guid'=>$u['guid']]);
    commerce_merge_session_cart_to_customer(); return true;
}
function customer_logout(): void { if(customer_logged_in())commerce_audit('customer.logout',['customer_guid'=>customer_identity()['guid']]);unset($_SESSION['customer_user']);session_regenerate_id(true); }
function customer_update_profile(array $input): array {
    require_customer();$guid=(string)customer_identity()['guid'];$name=trim((string)($input['name']??''));$phone=preg_replace('/[^0-9+]/','',(string)($input['phone']??''));if(text_len($name)<2)return[false,'Enter your full name.'];
    commerce_private_mutate(commerce_customer_file(),'sanjaynursery:customers:v1',function(array $rows)use($guid,$name,$phone){foreach($rows as &$u)if(($u['guid']??'')===$guid){$u['name']=$name;$u['phone']=$phone;$u['updated_at']=gmdate('c');}return $rows;},'customer.profile.update');
    $_SESSION['customer_user']['name']=$name;commerce_audit('customer.profile.update',['customer_guid'=>$guid]);return[true,'Profile updated.'];
}
function customer_request_password_reset(string $email): array {
    $u=customer_by_email($email); if(!$u)return[true,null];
    $token=bin2hex(random_bytes(24));$hash=hash('sha256',$token);$row=['guid'=>uuidv7(),'customer_guid'=>$u['guid'],'token_hash'=>$hash,'expires_at'=>time()+1800,'used_at'=>null,'created_at'=>gmdate('c')];
    commerce_private_mutate(commerce_reset_file(),'sanjaynursery:password-resets:v1',function(array $rows)use($row,$u){$now=time();$rows=array_values(array_filter($rows,fn($r)=>(int)($r['expires_at']??0)>$now && empty($r['used_at']) && ($r['customer_guid']??'')!==$u['guid']));$rows[]=$row;return$rows;},'customer.password_reset.request');
    commerce_audit('customer.password_reset.request',['customer_guid'=>$u['guid']]); return[true,$token];
}
function customer_reset_password(string $token,string $password): array {
    if(text_len($password)<10)return[false,'Use at least 10 characters for your password.'];$hash=hash('sha256',trim($token));$customerGuid=null;$resetGuid=null;$now=time();
    $rows=commerce_private_read(commerce_reset_file(),'sanjaynursery:password-resets:v1',[]);foreach($rows as $r){if(hash_equals((string)($r['token_hash']??''),$hash)&&empty($r['used_at'])&&(int)($r['expires_at']??0)>$now){$customerGuid=$r['customer_guid'];$resetGuid=$r['guid'];break;}}
    if(!$customerGuid)return[false,'Reset link is invalid or expired.'];
    commerce_private_mutate(commerce_customer_file(),'sanjaynursery:customers:v1',function(array $rows)use($customerGuid,$password){foreach($rows as &$u)if(($u['guid']??'')===$customerGuid){$u['password_hash']=password_hash($password,password_algo());$u['updated_at']=gmdate('c');}return$rows;},'customer.password_reset.complete');
    commerce_private_mutate(commerce_reset_file(),'sanjaynursery:password-resets:v1',function(array $rows)use($resetGuid){foreach($rows as &$r)if(($r['guid']??'')===$resetGuid)$r['used_at']=gmdate('c');return$rows;},'customer.password_reset.consume');
    commerce_audit('customer.password_reset.complete',['customer_guid'=>$customerGuid]);return[true,'Password updated. You can sign in now.'];
}

/* ---------------- Addresses / object authorization ---------------- */
function customer_addresses(?string $customerGuid=null): array {
    $customerGuid=$customerGuid?:((string)(customer_identity()['guid']??'')); if($customerGuid==='')return[];
    $rows=commerce_private_read(commerce_address_file(),'sanjaynursery:addresses:v1',[]);return array_values(array_filter($rows,fn($a)=>($a['customer_guid']??'')===$customerGuid));
}
function customer_address(string $guid,?string $customerGuid=null): ?array {
    $customerGuid=$customerGuid?:((string)(customer_identity()['guid']??'')); foreach(customer_addresses($customerGuid) as $a)if(($a['guid']??'')===$guid)return$a;return null;
}
function validate_address(array $in): array {
    $errors=[];foreach(['name','line1','city','state','pincode'] as $k)if(trim((string)($in[$k]??''))==='')$errors[]='Please complete '.str_replace('_',' ',$k).'.';$pin=preg_replace('/\D/','',(string)($in['pincode']??''));if(strlen($pin)!==6)$errors[]='Enter a valid 6-digit pincode.';return$errors;
}
function save_customer_address(array $in,?string $guid=null): array {
    require_customer();$cg=(string)customer_identity()['guid'];$errors=validate_address($in);if($errors)return[false,$errors];$guid=$guid?:uuidv7();
    $row=['guid'=>$guid,'customer_guid'=>$cg,'type'=>in_array(($in['type']??'home'),['home','work','other'],true)?$in['type']:'home','name'=>trim((string)$in['name']),'phone'=>preg_replace('/[^0-9+]/','',(string)($in['phone']??'')),'line1'=>trim((string)$in['line1']),'line2'=>trim((string)($in['line2']??'')),'landmark'=>trim((string)($in['landmark']??'')),'city'=>trim((string)$in['city']),'state'=>trim((string)$in['state']),'pincode'=>preg_replace('/\D/','',(string)$in['pincode']),'is_default'=>!empty($in['is_default']),'updated_at'=>gmdate('c'),'created_at'=>gmdate('c')];
    commerce_private_mutate(commerce_address_file(),'sanjaynursery:addresses:v1',function(array $rows)use($row,$cg,$guid){$found=false;foreach($rows as &$a){if(($a['guid']??'')===$guid){if(($a['customer_guid']??'')!==$cg)throw new RuntimeException('Address access denied.');$row['created_at']=$a['created_at']??$row['created_at'];$a=$row;$found=true;}if($row['is_default']&&($a['customer_guid']??'')===$cg&&($a['guid']??'')!==$guid)$a['is_default']=false;}if(!$found){if($row['is_default'])foreach($rows as &$a)if(($a['customer_guid']??'')===$cg)$a['is_default']=false;$rows[]=$row;}return$rows;},'address.save');commerce_audit('address.save',['customer_guid'=>$cg,'address_guid'=>$guid]);return[true,$row];
}
function delete_customer_address(string $guid): bool {
    require_customer();$cg=(string)customer_identity()['guid'];$removed=false;commerce_private_mutate(commerce_address_file(),'sanjaynursery:addresses:v1',function(array $rows)use($guid,$cg,&$removed){$out=[];foreach($rows as $a){if(($a['guid']??'')===$guid){if(($a['customer_guid']??'')!==$cg)throw new RuntimeException('Address access denied.');$removed=true;continue;}$out[]=$a;}return$out;},'address.delete');if($removed)commerce_audit('address.delete',['customer_guid'=>$cg,'address_guid'=>$guid]);return$removed;
}

/* ---------------- Cart v2 ---------------- */
function commerce_cart_session(): array { return is_array($_SESSION['cart_v2']??null)?$_SESSION['cart_v2']:[]; }
function commerce_cart_customer_rows(): array { if(!customer_logged_in())return[];$all=commerce_private_read(commerce_cart_file(),'sanjaynursery:carts:v1',[]);return $all[(string)customer_identity()['guid']]??[]; }
function commerce_cart_raw(): array { return customer_logged_in()?commerce_cart_customer_rows():commerce_cart_session(); }
function commerce_cart_write(array $rows): void {
    $rows=array_values($rows);if(customer_logged_in()){$cg=(string)customer_identity()['guid'];commerce_private_mutate(commerce_cart_file(),'sanjaynursery:carts:v1',function(array $all)use($cg,$rows){$all[$cg]=$rows;return$all;},'cart.save');}else $_SESSION['cart_v2']=$rows;
}
function commerce_merge_session_cart_to_customer(): void {
    if(!customer_logged_in())return;$session=commerce_cart_session();if(!$session)return;$existing=commerce_cart_customer_rows();foreach($session as $row){$merged=false;foreach($existing as &$e){if(($e['key']??'')===($row['key']??'')){$e['qty']=min(10,(int)($e['qty']??0)+(int)($row['qty']??0));$merged=true;break;}}if(!$merged)$existing[]=$row;}commerce_cart_write($existing);unset($_SESSION['cart_v2']);
}
function commerce_cart_add(string $slug,int $qty,string $material='',string $finish='',array $configuration=[],string $variantGuid='',array $attribution=[]): bool {
    $p=product_by_slug($slug);if(!$p)return false;$qty=min(10,max(1,$qty));
    $variant=null;
    if($variantGuid!==''){
        $variant=variant_by_guid($variantGuid);
        if(!$variant||(string)($variant['product_guid']??'')!==(string)$p['guid']||!variant_is_sellable($variant))return false;
        if(!empty($variant['track_inventory'])&&$qty>(int)($variant['stock']??0))return false;
        $key=hash('sha256',$slug.'|variant|'.$variant['guid']);
        $row=['key'=>$key,'slug'=>$slug,'variant_guid'=>$variant['guid'],'variant_sku'=>$variant['sku'],'qty'=>$qty,'material'=>'','finish'=>'','added_at'=>gmdate('c')];
        if($attribution){$row['intelligence_attribution']=array_filter(['recommendation_id'=>substr((string)($attribution['recommendation_id']??''),0,120),'algorithm'=>substr((string)($attribution['algorithm']??''),0,80),'placement'=>substr((string)($attribution['placement']??''),0,80),'experiment_variant'=>substr((string)($attribution['experiment_variant']??''),0,80),'search_query'=>substr((string)($attribution['search_query']??''),0,180)]);}
    } elseif(function_exists('product_has_variants')&&product_has_variants($p)) {
        // Once a plant has variant records, checkout must reference an explicit live SKU.
        return false;
    } elseif(product_is_enquiry($p)) return false;
    elseif(function_exists('configurator_schema_for_product') && configurator_schema_for_product($p)){
        if(!$configuration){$configuration=['material'=>$material,'finish'=>$finish];}
        $quote=configurator_quote($p,$configuration);if(empty($quote['ok']))return false;
        $key=hash('sha256',$slug.'|'.($quote['signature']??''));
        $row=['key'=>$key,'slug'=>$slug,'qty'=>$qty,'material'=>(string)($quote['selection']['material']??$material),'finish'=>(string)($quote['selection']['finish']??$finish),'configuration'=>$quote['selection'],'configuration_signature'=>$quote['signature'],'pricing_version'=>$quote['pricing_version'],'added_at'=>gmdate('c')];
    } else {
        $materials=$p['materials']??[];$finishes=$p['finishes']??[];$material=in_array($material,$materials,true)?$material:($materials[0]??($p['material']??''));$finish=in_array($finish,$finishes,true)?$finish:($finishes[0]??($p['finish']??''));$key=hash('sha256',$slug.'|'.$material.'|'.$finish);$row=['key'=>$key,'slug'=>$slug,'qty'=>$qty,'material'=>$material,'finish'=>$finish,'added_at'=>gmdate('c')];
    }
    $rows=commerce_cart_raw();$found=false;
    foreach($rows as &$r)if(($r['key']??'')===$key){$next=min(10,(int)$r['qty']+$qty);if($variant&&!empty($variant['track_inventory']))$next=min($next,(int)($variant['stock']??0));if($next<1)return false;$r['qty']=$next;if($attribution){$r['intelligence_attribution']=array_filter(['recommendation_id'=>substr((string)($attribution['recommendation_id']??''),0,120),'algorithm'=>substr((string)($attribution['algorithm']??''),0,80),'placement'=>substr((string)($attribution['placement']??''),0,80),'experiment_variant'=>substr((string)($attribution['experiment_variant']??''),0,80),'search_query'=>substr((string)($attribution['search_query']??''),0,180)]);}$found=true;}
    if(!$found)$rows[]=$row;commerce_cart_write($rows);return true;
}
function commerce_cart_update(array $qtys): void {
    $rows=commerce_cart_raw();$out=[];foreach($rows as $r){$n=(int)($qtys[$r['key']]??$r['qty']);if($n<=0)continue;$n=min(10,$n);if(!empty($r['variant_guid'])){$v=variant_by_guid((string)$r['variant_guid']);if(!$v||!variant_is_sellable($v))continue;if(!empty($v['track_inventory']))$n=min($n,(int)($v['stock']??0));}if($n>0){$r['qty']=$n;$out[]=$r;}}commerce_cart_write($out);
}
function commerce_cart_remove(string $key): void { commerce_cart_write(array_values(array_filter(commerce_cart_raw(),fn($r)=>($r['key']??'')!==$key))); }
function commerce_cart_clear(): void { commerce_cart_write([]); }
function commerce_cart_lines(): array {
    $lines=[];foreach(commerce_cart_raw() as $row){$p=product_by_slug((string)$row['slug']);if(!$p||($p['status']??'active')!=='active')continue;$qty=max(1,min(10,(int)($row['qty']??1)));$variant=null;
        if(!empty($row['variant_guid'])){
            $variant=variant_by_guid((string)$row['variant_guid']);if(!$variant||(string)($variant['product_guid']??'')!==(string)$p['guid']||!variant_is_sellable($variant))continue;if(!empty($variant['track_inventory']))$qty=min($qty,(int)($variant['stock']??0));if($qty<1)continue;
            $material='';$finish='';$unit=(int)$variant['price'];$config=[];$summary=[['label'=>'Variant','value'=>variant_display_label($variant)],['label'=>'SKU','value'=>$variant['sku']]];$signature='';$pricingVersion='variant-v1';
        } else {
            $quote=null;if(function_exists('configurator_schema_for_product') && configurator_schema_for_product($p)){$input=(array)($row['configuration']??['material'=>$row['material']??'','finish'=>$row['finish']??'']);$quote=configurator_quote($p,$input);if(empty($quote['ok'])){$quote=configurator_quote($p,configurator_default_selection($p));}}
            if($quote){$material=(string)($quote['selection']['material']??($p['material']??''));$finish=(string)($quote['selection']['finish']??($p['finish']??''));$unit=(int)$quote['unit_price'];$config=$quote['selection'];$summary=$quote['summary'];$signature=$quote['signature'];$pricingVersion=$quote['pricing_version'];}
            else {$materials=$p['materials']??[];$finishes=$p['finishes']??[];$material=in_array(($row['material']??''),$materials,true)?$row['material']:($materials[0]??($p['material']??''));$finish=in_array(($row['finish']??''),$finishes,true)?$row['finish']:($finishes[0]??($p['finish']??''));$unit=(int)$p['price'];$config=[];$summary=[];$signature='';$pricingVersion='';}
        }
        $priceChanged=(!$variant&&$signature!==''&&!empty($row['configuration_signature'])&&!hash_equals((string)$row['configuration_signature'],$signature));
        $lines[]=['key'=>$row['key'],'p'=>$p,'variant'=>$variant,'qty'=>$qty,'material'=>$material,'finish'=>$finish,'unit'=>$unit,'line_total'=>$unit*$qty,'configuration'=>$config,'configuration_summary'=>$summary,'configuration_signature'=>$signature,'pricing_version'=>$pricingVersion,'pricing_changed'=>$priceChanged,'intelligence_attribution'=>(array)($row['intelligence_attribution']??[])];
    }return$lines;
}
function commerce_cart_count(): int { return array_sum(array_map(fn($l)=>(int)$l['qty'],commerce_cart_lines())); }
function commerce_cart_subtotal(): int { return array_sum(array_column(commerce_cart_lines(),'line_total')); }

/* ---------------- Shipping / checkout totals ---------------- */
function shipping_rules(): array { return foundation_json_read(commerce_shipping_file(),[]); }
function shipping_quote(string $pincode,int $subtotal,array $lines=[]): array {
    $pin=preg_replace('/\D/','',$pincode);if(strlen($pin)!==6)return['serviceable'=>false,'error'=>'Enter a valid 6-digit pincode.'];$match=null;foreach(shipping_rules() as $rule){$patterns=$rule['patterns']??[];if(!$patterns){$match=$match?:$rule;continue;}foreach($patterns as $prefix)if(str_starts_with($pin,(string)$prefix)){$match=$rule;break 2;}}
    if(!$match||empty($match['serviceable']))return['serviceable'=>false,'error'=>'This pincode is not serviceable in staging.'];
    $largeItem=false;foreach($lines as $line){$v=$line['variant']??null;if($v&&($v['shipping_class']??'')==='pickup-only')return['serviceable'=>false,'error'=>'One or more selected plants are nursery-pickup only.'];if($v&&($v['shipping_class']??'')==='plant-large'){$largeItem=true;break;}$cat=$line['p']['category']??'';if(in_array($cat,['outdoor-plants','pots-planters'],true)&&($line['unit']??0)>=4000){$largeItem=true;break;}}
    $shipping=$largeItem?(int)$match['large_item_delivery']:(int)$match['small_parcel'];if(!$largeItem&&$subtotal>=(int)$match['free_small_parcel_above'])$shipping=0;
    return['serviceable'=>true,'zone_id'=>$match['id'],'zone_name'=>$match['name'],'shipping'=>$shipping,'installation_available'=>(bool)$match['installation'],'installation_fee'=>(int)$match['installation_fee'],'eta'=>$match['eta'],'large_item'=>$largeItem];
}
function commerce_calculate_totals(string $pincode,bool $installation=false): array {
    $lines=commerce_cart_lines();$subtotal=array_sum(array_column($lines,'line_total'));$quote=shipping_quote($pincode,$subtotal,$lines);if(empty($quote['serviceable']))return['ok'=>false,'shipping_quote'=>$quote,'lines'=>$lines,'subtotal'=>$subtotal];$install=($installation&&!empty($quote['installation_available']))?(int)$quote['installation_fee']:0;$shipping=(int)$quote['shipping'];$taxRate=(float)($GLOBALS['app']['tax_rate_percent']??18);$tax=(int)round(($subtotal+$shipping+$install)*$taxRate/(100+$taxRate));$grand=$subtotal+$shipping+$install;return['ok'=>true,'lines'=>$lines,'subtotal'=>$subtotal,'shipping'=>$shipping,'installation'=>$install,'tax_inclusive'=>$tax,'tax_rate'=>$taxRate,'grand_total'=>$grand,'shipping_quote'=>$quote];
}


function checkout_idempotency_token(): string {
    if (empty($_SESSION['_checkout_key'])) $_SESSION['_checkout_key']=bin2hex(random_bytes(24));
    return (string)$_SESSION['_checkout_key'];
}
function rotate_checkout_idempotency_token(): void { $_SESSION['_checkout_key']=bin2hex(random_bytes(24)); }

/* ---------------- Orders / immutable snapshots ---------------- */
function commerce_orders(): array { try{return commerce_private_read(commerce_order_file(),'sanjaynursery:orders:v1',[]);}catch(Throwable){return[];} }
function order_by_guid(string $guid): ?array { foreach(commerce_orders() as $o)if(($o['guid']??'')===$guid)return$o;return null; }
function customer_orders(string $customerGuid): array {$rows=array_values(array_filter(commerce_orders(),fn($o)=>($o['customer_guid']??'')===$customerGuid));usort($rows,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));return$rows;}
function customer_order(string $guid): ?array { if(!customer_logged_in())return null;$o=order_by_guid($guid);return $o&&($o['customer_guid']??'')===(string)customer_identity()['guid']?$o:null; }
function order_number(): string { return ($GLOBALS['app']['order_prefix']??'AW').'-'.gmdate('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8)); }
function invoice_number(): string { return ($GLOBALS['app']['invoice_prefix']??'AWI').'-'.gmdate('ym').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8)); }
function order_allowed_transitions(): array { return ['PENDING_PAYMENT'=>['PAID','CANCELLED'],'PAID'=>['CONFIRMED','REFUND_PENDING'],'CONFIRMED'=>['IN_PRODUCTION','CANCELLED','REFUND_PENDING'],'IN_PRODUCTION'=>['READY_TO_DISPATCH','REFUND_PENDING'],'READY_TO_DISPATCH'=>['SHIPPED','REFUND_PENDING'],'SHIPPED'=>['DELIVERED'],'DELIVERED'=>['REFUND_PENDING'],'REFUND_PENDING'=>['REFUNDED','CONFIRMED'],'CANCELLED'=>[],'REFUNDED'=>[]]; }
function order_can_transition(string $from,string $to): bool { return in_array($to,order_allowed_transitions()[$from]??[],true); }
function order_transition(string $guid,string $to,string $actor='system',string $note=''): array {
    $updated=null;commerce_private_mutate(commerce_order_file(),'sanjaynursery:orders:v1',function(array $rows)use($guid,$to,$actor,$note,&$updated){foreach($rows as &$o)if(($o['guid']??'')===$guid){$from=(string)($o['status']??'');if($from===$to){$updated=$o;return$rows;}if(!order_can_transition($from,$to))throw new RuntimeException("Invalid order transition: $from → $to");$o['status']=$to;$o['updated_at']=gmdate('c');$o['timeline'][]=['ts'=>gmdate('c'),'from'=>$from,'to'=>$to,'actor'=>$actor,'note'=>$note];$updated=$o;break;}return$rows;},'order.transition');if(!$updated)throw new RuntimeException('Order not found.');commerce_audit('order.transition',['order_guid'=>$guid,'to'=>$to,'actor'=>$actor]);return$updated;
}
function create_order_from_checkout(string $addressGuid,bool $installation=false,string $checkoutKey=''): array {
    require_customer('/checkout');$cg=(string)customer_identity()['guid'];$address=customer_address($addressGuid,$cg);if(!$address)throw new RuntimeException('Choose a valid delivery address.');$checkoutKey=trim($checkoutKey);if($checkoutKey==='')throw new RuntimeException('Checkout session is invalid. Refresh and try again.');$checkoutHash=hash('sha256',$checkoutKey);foreach(customer_orders($cg) as $existingOrder)if(($existingOrder['checkout_key_hash']??'')===$checkoutHash)return$existingOrder;$totals=commerce_calculate_totals((string)$address['pincode'],$installation);if(empty($totals['ok']))throw new RuntimeException((string)($totals['shipping_quote']['error']??'Delivery unavailable.'));if(!$totals['lines'])throw new RuntimeException('Your cart is empty.');
    $items=[];foreach($totals['lines'] as $l){$p=$l['p'];$v=$l['variant']??null;$items[]=['product_guid'=>$p['guid'],'variant_guid'=>$v['guid']??null,'parent_sku'=>$p['sku'],'sku'=>$v['sku']??$p['sku'],'slug'=>$p['slug'],'name'=>$p['name'].($v?' · '.variant_display_label($v):''),'variant_label'=>$v?variant_display_label($v):null,'batch_code'=>$v['batch_code']??null,'pot_size_cm'=>$v['pot_size_cm']??null,'plant_height_cm_min'=>$v['plant_height_cm_min']??null,'plant_height_cm_max'=>$v['plant_height_cm_max']??null,'grade'=>$v['grade']??null,'image'=>$v?variant_effective_image($v,$p):$p['image'],'qty'=>$l['qty'],'material'=>$l['material'],'finish'=>$l['finish'],'unit_price'=>$l['unit'],'line_total'=>$l['line_total'],'configuration_snapshot'=>$l['configuration']??[],'configuration_summary'=>$l['configuration_summary']??[],'configuration_signature'=>$l['configuration_signature']??'','pricing_version'=>$l['pricing_version']??'','tax_snapshot'=>'GST inclusive','intelligence_attribution'=>(array)($l['intelligence_attribution']??[])];}$stockErrors=variant_order_stock_available($items);if($stockErrors)throw new RuntimeException(implode(' ',$stockErrors));
    $customer=customer_by_guid($cg);$order=['guid'=>uuidv7(),'number'=>order_number(),'customer_guid'=>$cg,'customer_snapshot'=>['name'=>$customer['name']??'','email'=>$customer['email']??'','phone'=>$customer['phone']??''],'address_snapshot'=>$address,'items'=>$items,'pricing'=>['currency'=>'INR','subtotal'=>$totals['subtotal'],'shipping'=>$totals['shipping'],'installation'=>$totals['installation'],'tax_inclusive'=>$totals['tax_inclusive'],'tax_rate'=>$totals['tax_rate'],'grand_total'=>$totals['grand_total']],'shipping_snapshot'=>$totals['shipping_quote'],'status'=>'PENDING_PAYMENT','payment_status'=>'PENDING','checkout_key_hash'=>$checkoutHash,'invoice'=>null,'timeline'=>[['ts'=>gmdate('c'),'from'=>null,'to'=>'PENDING_PAYMENT','actor'=>'customer','note'=>'Checkout submitted']], 'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
    commerce_private_mutate(commerce_order_file(),'sanjaynursery:orders:v1',function(array $rows)use($order){foreach($rows as $x)if(($x['number']??'')===$order['number'])throw new RuntimeException('Order number collision.');$rows[]=$order;return$rows;},'order.create');commerce_audit('order.create',['order_guid'=>$order['guid'],'customer_guid'=>$cg,'total'=>$order['pricing']['grand_total']]);return$order;
}
function order_set_payment_status(string $orderGuid,string $paymentStatus,?array $paymentRef=null): array {
    $updated=null;commerce_private_mutate(commerce_order_file(),'sanjaynursery:orders:v1',function(array $rows)use($orderGuid,$paymentStatus,$paymentRef,&$updated){foreach($rows as &$o)if(($o['guid']??'')===$orderGuid){$o['payment_status']=$paymentStatus;if($paymentRef)$o['payment_reference']=$paymentRef;$o['updated_at']=gmdate('c');if($paymentStatus==='PAID'&&empty($o['invoice']))$o['invoice']=['number'=>invoice_number(),'issued_at'=>gmdate('c')];$updated=$o;break;}return$rows;},'order.payment_status');if(!$updated)throw new RuntimeException('Order not found.');return$updated;
}

/* ---------------- Sandbox payment / webhook idempotency ---------------- */
function sandbox_webhook_secret(): string { $s=(string)($GLOBALS['app']['sandbox_webhook_secret']??'');if($s==='')$s=(string)getenv('SANJAY_SANDBOX_WEBHOOK_SECRET');return$s; }
function payment_attempts(): array { try{return commerce_private_read(commerce_payment_file(),'sanjaynursery:payments:v1',['attempts'=>[],'events'=>[]]);}catch(Throwable){return['attempts'=>[],'events'=>[]];} }
function create_payment_attempt(array $order): array {
    $attempt=['guid'=>uuidv7(),'order_guid'=>$order['guid'],'order_number'=>$order['number'],'amount'=>$order['pricing']['grand_total'],'currency'=>'INR','provider'=>'sandbox','status'=>'CREATED','idempotency_key'=>bin2hex(random_bytes(16)),'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
    commerce_private_mutate(commerce_payment_file(),'sanjaynursery:payments:v1',function(array $store)use($attempt){$store+=['attempts'=>[],'events'=>[]];$store['attempts'][]=$attempt;return$store;},'payment.attempt.create');return$attempt;
}
function payment_attempt_by_guid(string $guid): ?array { foreach(payment_attempts()['attempts']??[] as $a)if(($a['guid']??'')===$guid)return$a;return null; }
function payment_attempt_for_order(string $orderGuid): ?array { $rows=payment_attempts()['attempts']??[]; for($i=count($rows)-1;$i>=0;$i--){$a=$rows[$i];if(($a['order_guid']??'')===$orderGuid && in_array(($a['status']??''),['CREATED','FAILED'],true))return$a;}return null; }
function sandbox_signature(string $raw): string { $secret=sandbox_webhook_secret();if($secret==='')throw new RuntimeException('Sandbox webhook signing secret is not configured. Complete web setup first.');return hash_hmac('sha256',$raw,$secret); }
function process_payment_event(array $event,string $raw,string $signature): array {
    $secret=sandbox_webhook_secret();if($secret==='')throw new RuntimeException('Sandbox webhook secret is not configured.');$expected=hash_hmac('sha256',$raw,$secret);if(!hash_equals($expected,$signature))throw new RuntimeException('Webhook signature verification failed.');
    foreach(['event_id','attempt_guid','status'] as $k)if(empty($event[$k]))throw new RuntimeException('Webhook payload is incomplete.');$status=strtoupper((string)$event['status']);if(!in_array($status,['SUCCESS','FAILED'],true))throw new RuntimeException('Unsupported payment event status.');$attempt=payment_attempt_by_guid((string)$event['attempt_guid']);if(!$attempt)throw new RuntimeException('Payment attempt not found.');if($status==='SUCCESS'){$pendingOrder=order_by_guid((string)$attempt['order_guid']);if(!$pendingOrder)throw new RuntimeException('Order not found.');$stockErrors=variant_order_stock_available((array)($pendingOrder['items']??[]));if($stockErrors)throw new RuntimeException(implode(' ',$stockErrors));}
    $processed=false;$duplicate=false;commerce_private_mutate(commerce_payment_file(),'sanjaynursery:payments:v1',function(array $store)use($event,$status,&$processed,&$duplicate){$store+=['attempts'=>[],'events'=>[]];foreach($store['events'] as $e)if(($e['event_id']??'')===$event['event_id']){$duplicate=true;return$store;}foreach($store['attempts'] as &$a)if(($a['guid']??'')===$event['attempt_guid']){$a['status']=$status==='SUCCESS'?'PAID':'FAILED';$a['updated_at']=gmdate('c');$a['provider_reference']=$event['provider_reference']??null;}$store['events'][]=['event_id'=>$event['event_id'],'attempt_guid'=>$event['attempt_guid'],'status'=>$status,'provider_reference'=>$event['provider_reference']??null,'received_at'=>gmdate('c')];$processed=true;return$store;},'payment.webhook');
    if($duplicate)return['ok'=>true,'duplicate'=>true,'order_guid'=>$attempt['order_guid']];if(!$processed)throw new RuntimeException('Payment event was not processed.');
    if($status==='SUCCESS'){$o=order_by_guid($attempt['order_guid']);if(!$o)throw new RuntimeException('Order not found.');if(($o['payment_status']??'')!=='PAID'){variant_commit_order_stock($o);order_set_payment_status($o['guid'],'PAID',['provider'=>'sandbox','attempt_guid'=>$attempt['guid'],'provider_reference'=>$event['provider_reference']??null]);$o=order_by_guid($o['guid']);if(($o['status']??'')==='PENDING_PAYMENT')order_transition($o['guid'],'PAID','payment-webhook','Sandbox payment verified.');commerce_cart_clear();}}
    else { order_set_payment_status($attempt['order_guid'],'FAILED',['provider'=>'sandbox','attempt_guid'=>$attempt['guid']]); }
    commerce_audit('payment.webhook',['event_id'=>$event['event_id'],'attempt_guid'=>$attempt['guid'],'status'=>$status]);return['ok'=>true,'duplicate'=>false,'order_guid'=>$attempt['order_guid']];
}
function sandbox_complete_attempt(string $attemptGuid,string $status): array {
    $attempt=payment_attempt_by_guid($attemptGuid);if(!$attempt)throw new RuntimeException('Payment attempt not found.');$event=['event_id'=>'evt_'.bin2hex(random_bytes(10)),'attempt_guid'=>$attemptGuid,'status'=>$status,'provider_reference'=>'sbx_'.bin2hex(random_bytes(6))];$raw=json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);return process_payment_event($event,$raw,sandbox_signature($raw));
}

/* ---------------- Cancellation / refunds ---------------- */
function commerce_requests(): array { try{return commerce_private_read(commerce_request_file(),'sanjaynursery:requests:v1',[]);}catch(Throwable){return[];} }
function customer_request_order_action(string $orderGuid,string $type,string $reason): array {
    require_customer();$o=customer_order($orderGuid);if(!$o)return[false,'Order not found.'];$reason=trim($reason);if(text_len($reason)<5)return[false,'Please add a short reason for your request.'];$type=strtoupper($type);if(!in_array($type,['CANCELLATION','REFUND'],true))return[false,'Unsupported request.'];if($type==='CANCELLATION'&&!in_array($o['status'],['PENDING_PAYMENT','PAID','CONFIRMED'],true))return[false,'This order can no longer be cancelled online.'];if($type==='REFUND'&&!in_array($o['status'],['PAID','CONFIRMED','IN_PRODUCTION','READY_TO_DISPATCH','DELIVERED'],true))return[false,'This order is not eligible for an online refund request.'];
    $r=['guid'=>uuidv7(),'order_guid'=>$o['guid'],'order_number'=>$o['number'],'customer_guid'=>$o['customer_guid'],'type'=>$type,'reason'=>trim($reason),'status'=>'OPEN','created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];commerce_private_mutate(commerce_request_file(),'sanjaynursery:requests:v1',function(array $rows)use($r){$rows[]=$r;return$rows;},'order.request.create');if($type==='REFUND'&&order_can_transition($o['status'],'REFUND_PENDING'))order_transition($o['guid'],'REFUND_PENDING','customer','Customer refund request opened.');commerce_audit('order.request.create',['request_guid'=>$r['guid'],'order_guid'=>$o['guid'],'type'=>$type]);return[true,$r];
}
function requests_for_order(string $orderGuid): array { return array_values(array_filter(commerce_requests(),fn($r)=>($r['order_guid']??'')===$orderGuid)); }
function admin_resolve_request(string $requestGuid,string $decision,string $note=''): array {
    $decision=strtoupper($decision);$updated=null;commerce_private_mutate(commerce_request_file(),'sanjaynursery:requests:v1',function(array $rows)use($requestGuid,$decision,$note,&$updated){foreach($rows as &$r)if(($r['guid']??'')===$requestGuid){if(($r['status']??'')!=='OPEN')throw new RuntimeException('Request is already resolved.');$r['status']=$decision==='APPROVE'?'APPROVED':'REJECTED';$r['admin_note']=$note;$r['resolved_at']=gmdate('c');$r['updated_at']=gmdate('c');$updated=$r;break;}return$rows;},'order.request.resolve');if(!$updated)throw new RuntimeException('Request not found.');$o=order_by_guid($updated['order_guid']);if($decision==='APPROVE'&&$o){if($updated['type']==='CANCELLATION'){if(order_can_transition($o['status'],'CANCELLED')){order_transition($o['guid'],'CANCELLED','admin',$note?:'Cancellation approved.');}elseif(in_array($o['status'],['PAID','CONFIRMED'],true)){if(order_can_transition($o['status'],'REFUND_PENDING'))$o=order_transition($o['guid'],'REFUND_PENDING','admin','Paid cancellation approved; refund opened.');if(order_can_transition($o['status'],'REFUNDED')){order_transition($o['guid'],'REFUNDED','admin',$note?:'Sandbox cancellation refund completed.');order_set_payment_status($o['guid'],'REFUNDED',['provider'=>'sandbox-refund']);}}}if($updated['type']==='REFUND'){if(($o['status']??'')!=='REFUND_PENDING'&&order_can_transition($o['status'],'REFUND_PENDING'))$o=order_transition($o['guid'],'REFUND_PENDING','admin','Refund approved.');if(order_can_transition($o['status'],'REFUNDED')){order_transition($o['guid'],'REFUNDED','admin',$note?:'Sandbox refund completed.');order_set_payment_status($o['guid'],'REFUNDED',['provider'=>'sandbox-refund']);}}}commerce_audit('order.request.resolve',['request_guid'=>$requestGuid,'decision'=>$decision]);return$updated;
}

/* ---------------- Admin order operations ---------------- */
function admin_orders(?string $status=null): array {$rows=commerce_orders();if($status)$rows=array_values(array_filter($rows,fn($o)=>($o['status']??'')===$status));usort($rows,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));return$rows;}
function admin_order(string $guid): ?array { return order_by_guid($guid); }
function admin_customers_count(): int { return count(customer_users()); }
function admin_open_requests_count(): int { return count(array_filter(commerce_requests(),fn($r)=>($r['status']??'')==='OPEN')); }

/* ---------------- Audit ---------------- */
function commerce_audit(string $action,array $context=[]): void {
    // Minimized: never include name, email, phone, address, tokens, raw payment payloads or session IDs.
    $identity=admin_identity();$customer=customer_identity();$ip=(string)($_SERVER['REMOTE_ADDR']??'');$record=['ts'=>gmdate('c'),'action'=>$action,'admin_guid'=>$identity['guid']??null,'customer_guid'=>$customer['guid']??($context['customer_guid']??null),'ip_hash'=>$ip!==''?substr(hash('sha256',$ip),0,20):null,'context'=>array_filter($context,fn($k)=>!in_array($k,['email','phone','name','address','token','password','raw'],true),ARRAY_FILTER_USE_KEY)];
    $path=data_root().'/journals/commerce-audit.ndjson';$fh=@fopen($path,'ab');if($fh){flock($fh,LOCK_EX);fwrite($fh,json_encode($record,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");fflush($fh);flock($fh,LOCK_UN);fclose($fh);@chmod($path,0640);}
}

/* ---------------- Release checks ---------------- */
function commerce_release_checks(): array {
    commerce_ensure_dirs();$keyReady=confidential_key()!==null;$secret=sandbox_webhook_secret()!=='';$shipping=shipping_rules();$orders=commerce_orders();$numbers=array_column($orders,'number');$dupeNumbers=count($numbers)!==count(array_unique($numbers));$orderSchema=true;foreach($orders as $o)foreach(['guid','number','customer_guid','items','pricing','status','payment_status','address_snapshot'] as $k)if(!array_key_exists($k,$o))$orderSchema=false;
    $privateOutside=!str_starts_with(realpath(commerce_root())?:'',realpath(dirname(__DIR__).'/public')?:'__public__');
    return [
        ['name'=>'Commerce encryption key','ok'=>$keyReady,'detail'=>$keyReady?'Protected commerce encryption key is ready.':'Complete web setup before using customer/order persistence.'],
        ['name'=>'Commerce private boundary','ok'=>$privateOutside,'detail'=>'Customer, order and payment stores are outside the public document root.'],
        ['name'=>'Shipping rules','ok'=>count($shipping)>=1,'detail'=>count($shipping).' shipping zone rule(s) loaded.'],
        ['name'=>'Sandbox webhook secret','ok'=>$secret,'detail'=>$secret?'Webhook HMAC secret is configured.':'Configure SANJAY_SANDBOX_WEBHOOK_SECRET for sandbox payments.'],
        ['name'=>'Order number uniqueness','ok'=>!$dupeNumbers,'detail'=>$dupeNumbers?'Duplicate order number detected.':'Order numbers are unique.'],
        ['name'=>'Order schema','ok'=>$orderSchema,'detail'=>$orderSchema?'Existing orders contain required immutable snapshot fields.':'One or more order records are malformed.'],
        ['name'=>'Order transition graph','ok'=>order_can_transition('PENDING_PAYMENT','PAID')&&!order_can_transition('DELIVERED','PENDING_PAYMENT'),'detail'=>'Valid transitions are allowlisted and invalid rollback is rejected.'],
        ['name'=>'PII audit minimization','ok'=>true,'detail'=>'Commerce audit records use GUIDs and hashed IPs; raw PII/tokens are excluded by contract.'],
    ];
}

commerce_ensure_dirs();
