<?php
declare(strict_types=1);

/**
 * Sanjay Nursery Configurator — v0.4.0
 * Server-authoritative product configuration, deterministic live pricing and saved configurations.
 */

function configurator_rules_file(): string { return data_root() . '/public-domain/catalog/configurator-rules.json'; }
function configurator_saved_file(): string { return commerce_root() . '/configurations.json'; }
function configurator_context(): string { return 'sanjaynursery:configurations:v1'; }

function configurator_rules(): array {
    $rows = foundation_json_read(configurator_rules_file(), []);
    return is_array($rows) ? $rows : [];
}

function configurator_schema_for_product(array $product): array {
    $rules = configurator_rules();
    $schema = $rules[(string)($product['slug'] ?? '')] ?? [];
    if (!$schema || empty($schema['enabled'])) return [];
    if (($schema['product_guid'] ?? '') !== ($product['guid'] ?? '')) return [];
    return $schema;
}

function configurator_rule_fingerprint(array $schema): string {
    $stable = [
        'product_guid'=>$schema['product_guid']??'',
        'pricing_version'=>$schema['pricing_version']??'',
        'base_price'=>(int)($schema['base_price']??0),
        'currency'=>$schema['currency']??'INR',
        'options'=>$schema['options']??[],
    ];
    return hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}

function configurator_default_selection(array $product, ?array $schema=null): array {
    $schema = $schema ?: configurator_schema_for_product($product);
    $out=[];
    foreach(($schema['options']??[]) as $opt){
        $key=(string)($opt['key']??''); if($key==='')continue;
        $type=(string)($opt['type']??'choice');
        if($type==='choice') $out[$key]=(string)($opt['choices'][0]['value']??'');
        elseif($type==='number') $out[$key]=(int)($opt['base']??$opt['min']??0);
        elseif($type==='boolean') $out[$key]=false;
        elseif($type==='text') $out[$key]='';
    }
    return $out;
}

function configurator_normalize_selection(array $product, array $input): array {
    $schema=configurator_schema_for_product($product);
    if(!$schema) return ['selection'=>[],'errors'=>['This product is not configurable.'],'schema'=>[]];
    $selection=[];$errors=[];
    foreach(($schema['options']??[]) as $opt){
        $key=(string)($opt['key']??'');$label=(string)($opt['label']??$key);$type=(string)($opt['type']??'choice');if($key==='')continue;
        if($type==='choice'){
            $choices=$opt['choices']??[];$allowed=array_column($choices,'value');$value=(string)($input[$key]??($choices[0]['value']??''));
            if(!in_array($value,$allowed,true)){ $errors[]="$label selection is invalid."; $value=(string)($choices[0]['value']??''); }
            $selection[$key]=$value;
        } elseif($type==='number'){
            $min=(int)($opt['min']??0);$max=(int)($opt['max']??$min);$step=max(1,(int)($opt['step']??1));$base=(int)($opt['base']??$min);$raw=$input[$key]??$base;
            if(!is_numeric($raw)){ $errors[]="$label must be numeric."; $value=$base; }
            else { $value=(int)$raw; if($value<$min||$value>$max){$errors[]="$label must be between $min and $max.";$value=max($min,min($max,$value));} $offset=$value-$min; if($offset%$step!==0){$errors[]="$label must use $step-unit increments.";$value=$min+(int)round($offset/$step)*$step;$value=max($min,min($max,$value));} }
            $selection[$key]=$value;
        } elseif($type==='boolean'){
            $v=$input[$key]??false;$selection[$key]=in_array($v,[1,'1',true,'true','on','yes'],true);
        } elseif($type==='text'){
            $value=trim((string)($input[$key]??''));$max=max(1,(int)($opt['max_length']??80));
            if(text_len($value)>$max){$errors[]="$label is too long.";$value=function_exists('mb_substr')?mb_substr($value,0,$max):substr($value,0,$max);}
            $selection[$key]=$value;
        }
    }
    return ['selection'=>$selection,'errors'=>$errors,'schema'=>$schema];
}

function configurator_choice_label(array $opt,string $value): string {
    foreach(($opt['choices']??[]) as $c) if((string)($c['value']??'')===$value) return (string)($c['label']??$value);
    return $value;
}

function configurator_quote(array $product,array $input): array {
    $n=configurator_normalize_selection($product,$input);$schema=$n['schema'];$selection=$n['selection'];$errors=$n['errors'];
    if(!$schema) return ['ok'=>false,'errors'=>$errors,'unit_price'=>(int)($product['price']??0),'selection'=>[],'adjustments'=>[],'summary'=>[]];
    $base=(int)($schema['base_price']??$product['price']??0);$price=$base;$adjustments=[];$summary=[];
    foreach(($schema['options']??[]) as $opt){
        $key=(string)$opt['key'];$type=(string)($opt['type']??'choice');$label=(string)($opt['label']??$key);$value=$selection[$key]??null;$delta=0;$display='';
        if($type==='choice'){
            foreach(($opt['choices']??[]) as $c) if((string)($c['value']??'')===(string)$value){$delta=(int)($c['delta']??0);$display=(string)($c['label']??$value);break;}
        } elseif($type==='number'){
            $baseVal=(int)($opt['base']??$opt['min']??0);$step=max(1,(int)($opt['step']??1));$deltaPer=(int)($opt['delta_per_step']??0);$steps=(int)round(((int)$value-$baseVal)/$step);$delta=$steps*$deltaPer;$display=(string)$value.' '.(string)($opt['unit']??'');
        } elseif($type==='boolean'){
            if($value){$delta=(int)($opt['delta']??0);$display='Included';} else $display='Not selected';
        } elseif($type==='text'){
            if((string)$value!==''){$delta=(int)($opt['delta']??0);$display=(string)$value;} else $display='None';
        }
        $price+=$delta;if($delta!==0)$adjustments[]=['key'=>$key,'label'=>$label,'delta'=>$delta];
        $summary[]=['key'=>$key,'label'=>$label,'value'=>$display,'raw'=>$value];
    }
    $price=max(0,$price);$fingerprint=configurator_rule_fingerprint($schema);$sigPayload=['product_guid'=>$product['guid'],'rule'=>$fingerprint,'selection'=>$selection,'unit_price'=>$price];
    $signature=hash('sha256',json_encode($sigPayload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    return ['ok'=>count($errors)===0,'errors'=>$errors,'product_guid'=>$product['guid'],'slug'=>$product['slug'],'base_price'=>$base,'unit_price'=>$price,'currency'=>$schema['currency']??'INR','pricing_version'=>$schema['pricing_version']??'','rule_fingerprint'=>$fingerprint,'selection'=>$selection,'summary'=>$summary,'adjustments'=>$adjustments,'signature'=>$signature,'notes'=>$schema['notes']??''];
}

function configurator_quote_by_slug(string $slug,array $input): array {
    $p=product_by_slug($slug); if(!$p) return ['ok'=>false,'errors'=>['Product not found.']];
    return configurator_quote($p,$input);
}

function configurator_input_from_post(array $post): array {
    $out=[];
    foreach($post as $k=>$v){
        if(in_array($k,['_csrf','slug','qty','configuration_guid','label','back'],true))continue;
        if(is_scalar($v))$out[$k]=$v;
    }
    return $out;
}

function customer_saved_configurations(?string $customerGuid=null): array {
    if(!$customerGuid){ if(!customer_logged_in())return[];$customerGuid=(string)customer_identity()['guid']; }
    try{$all=commerce_private_read(configurator_saved_file(),configurator_context(),[]);}catch(Throwable){return[];}
    $rows=array_values(array_filter($all,fn($r)=>($r['customer_guid']??'')===$customerGuid));
    usort($rows,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));return$rows;
}

function customer_saved_configuration(string $guid,?string $customerGuid=null): ?array {
    $customerGuid=$customerGuid?:((string)(customer_identity()['guid']??''));foreach(customer_saved_configurations($customerGuid) as $r)if(($r['guid']??'')===$guid)return$r;return null;
}

function save_customer_configuration(string $slug,array $input,string $label='',?string $guid=null): array {
    require_customer('/account/configurations');$p=product_by_slug($slug);if(!$p)return[false,'Product not found.'];$quote=configurator_quote($p,$input);if(empty($quote['ok']))return[false,implode(' ',$quote['errors']??['Configuration is invalid.'])];
    $cg=(string)customer_identity()['guid'];$guid=$guid?:uuidv7();$label=trim($label);if($label==='')$label=$p['name'].' · '.date('d M');if(text_len($label)>80)$label=function_exists('mb_substr')?mb_substr($label,0,80):substr($label,0,80);
    $row=['guid'=>$guid,'customer_guid'=>$cg,'product_guid'=>$p['guid'],'slug'=>$p['slug'],'product_name'=>$p['name'],'image'=>$p['image'],'label'=>$label,'selection'=>$quote['selection'],'summary'=>$quote['summary'],'unit_price'=>$quote['unit_price'],'currency'=>$quote['currency'],'pricing_version'=>$quote['pricing_version'],'rule_fingerprint'=>$quote['rule_fingerprint'],'signature'=>$quote['signature'],'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
    commerce_private_mutate(configurator_saved_file(),configurator_context(),function(array $rows)use($row,$cg,$guid){$found=false;foreach($rows as &$r)if(($r['guid']??'')===$guid){if(($r['customer_guid']??'')!==$cg)throw new RuntimeException('Configuration access denied.');$row['created_at']=$r['created_at']??$row['created_at'];$r=$row;$found=true;break;}if(!$found)$rows[]=$row;return$rows;},'configuration.save');
    commerce_audit('configuration.save',['configuration_guid'=>$guid,'product_guid'=>$p['guid'],'unit_price'=>$quote['unit_price']]);return[true,$row];
}

function delete_customer_configuration(string $guid): bool {
    require_customer('/account/configurations');$cg=(string)customer_identity()['guid'];$removed=false;
    commerce_private_mutate(configurator_saved_file(),configurator_context(),function(array $rows)use($guid,$cg,&$removed){$out=[];foreach($rows as $r){if(($r['guid']??'')===$guid){if(($r['customer_guid']??'')!==$cg)throw new RuntimeException('Configuration access denied.');$removed=true;continue;}$out[]=$r;}return$out;},'configuration.delete');
    if($removed)commerce_audit('configuration.delete',['configuration_guid'=>$guid]);return$removed;
}

function configurator_current_quote_for_saved(array $saved): ?array {
    $p=product_by_slug((string)($saved['slug']??''));if(!$p)return null;return configurator_quote($p,(array)($saved['selection']??[]));
}

function configurator_release_checks(): array {
    $rules=configurator_rules();$public=array_values(array_filter(products_public(),fn($p)=>!empty($p['customisation']['enabled'])&&product_is_sellable($p)));$missing=[];$bad=[];
    foreach($public as $p){$schema=$rules[$p['slug']]??null;if(!$schema||empty($schema['enabled'])||($schema['product_guid']??'')!==$p['guid']){$missing[]=$p['slug'];continue;}foreach(($schema['options']??[]) as $opt){if(empty($opt['key'])||empty($opt['type']))$bad[]=$p['slug'];if(($opt['type']??'')==='choice'&&empty($opt['choices']))$bad[]=$p['slug'];}}
    $deterministic=true;if($public){$p=$public[0];$q1=configurator_quote($p,configurator_default_selection($p));$q2=configurator_quote($p,configurator_default_selection($p));$deterministic=($q1['signature']??'')!==''&&($q1['signature']??'')===($q2['signature']??'')&&($q1['unit_price']??-1)===($q2['unit_price']??-2);}
    $savedEncrypted=!is_file(configurator_saved_file())||!str_contains((string)@file_get_contents(configurator_saved_file()),'customer_guid');
    return [
      ['name'=>'Configurator rule coverage','ok'=>count($missing)===0,'detail'=>count($missing)===0?count($public).' sellable customisable products have configurable pricing rules.':count($missing).' sellable customisable product(s) are missing configurator rules.'],
      ['name'=>'Configurator rule schema','ok'=>count($bad)===0,'detail'=>count($bad)===0?'Configurator option schemas are structurally valid.':'Invalid configurator option schema detected.'],
      ['name'=>'Deterministic live pricing','ok'=>$deterministic,'detail'=>$deterministic?'Repeated quotes produce identical server-authoritative prices and signatures.':'Configurator pricing is not deterministic.'],
      ['name'=>'Saved configuration encryption','ok'=>$savedEncrypted,'detail'=>$savedEncrypted?'Saved customer configurations are encrypted at rest or not yet initialized.':'Plaintext customer configuration store detected.'],
      ['name'=>'Configurator staging gate','ok'=>($GLOBALS['app']['environment']??'staging')!=='production','detail'=>'Configurator pricing is staging-only until workshop/commercial rule sign-off.'],
    ];
}
