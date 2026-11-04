<?php
declare(strict_types=1);

/**
 * NurserySetu SupplierOS B2 — controlled CSV-to-draft onboarding.
 *
 * No external seller checkout, no stock verification, no auto-publication.
 * Preview lives only in the authenticated supplier's server-side session.
 * Commit is an atomic marketplace mutation with idempotent import row keys.
 */
function supplier_csv_headers(): array {
    return ['branch_id','product_slug','label','price_inr','quantity'];
}
function supplier_csv_parse_file(string $file,string $tenantId): array {
    tenant_require('offers.draft',$tenantId);
    if(!is_file($file)||is_link($file))throw new InvalidArgumentException('Choose a CSV file.');
    $size=filesize($file);
    if($size===false||$size<10||$size>131072)throw new InvalidArgumentException('CSV size must be 1–128 KB.');
    $handle=fopen($file,'rb');
    if(!$handle)throw new RuntimeException('Cannot open CSV upload.');
    $rows=[];$unique=[];
    try{
        $header=fgetcsv($handle,4096,',','"','');
        if(!is_array($header))throw new InvalidArgumentException('The CSV header is missing.');
        $header[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)($header[0]??''));
        $header=array_map(static fn($x)=>trim((string)$x),$header);
        if($header!==supplier_csv_headers())throw new InvalidArgumentException('Expected CSV columns: '.implode(', ',supplier_csv_headers()).'.');
        $line=1;
        while(($cells=fgetcsv($handle,4096,',','"',''))!==false){
            $line++;
            if($line>51)throw new InvalidArgumentException('Maximum 50 data rows per import.');
            if(count($cells)===1&&trim((string)$cells[0])==='')continue;
            if(count($cells)!==5)throw new InvalidArgumentException('Row '.$line.': exactly five columns are required.');
            $fields=array_map(static fn($x)=>trim((string)$x),$cells);
            foreach($fields as $value){
                if(strlen($value)>500||preg_match('/[\x00-\x1F\x7F]/',$value))throw new InvalidArgumentException('Row '.$line.': invalid or oversized field.');
                if(function_exists('mb_check_encoding')&&!mb_check_encoding($value,'UTF-8'))throw new InvalidArgumentException('Row '.$line.': use UTF-8 CSV.');
            }
            [$branchId,$slug,$label,$price,$quantity]=$fields;
            if(!tenant_valid_id($branchId))throw new InvalidArgumentException('Row '.$line.': invalid branch UUID.');
            supplier_branch_for_offer($tenantId,$branchId,false);
            $plant=product_by_slug($slug);
            if(!$plant||($plant['status']??'active')!=='active')throw new InvalidArgumentException('Row '.$line.': plant slug not found.');
            if(text_len($label)<2||text_len($label)>140)throw new InvalidArgumentException('Row '.$line.': label must be 2–140 characters.');
            if($price!==''&&(!ctype_digit($price)||strlen($price)>8||(int)$price<1||(int)$price>10000000))throw new InvalidArgumentException('Row '.$line.': invalid indicative INR price.');
            if($quantity!==''&&(!ctype_digit($quantity)||strlen($quantity)>7||(int)$quantity>1000000))throw new InvalidArgumentException('Row '.$line.': invalid quantity.');
            $key=$branchId.'|'.$plant['guid'].'|'.text_lower($label);
            if(isset($unique[$key]))throw new InvalidArgumentException('Row '.$line.': duplicate plant/grade/branch in the same import.');
            $unique[$key]=true;
            $rows[]=[
                'branch_id'=>$branchId,
                'product_guid'=>(string)$plant['guid'],
                'product_name'=>(string)$plant['name'],
                'product_slug'=>(string)$plant['slug'],
                'label'=>$label,
                'price_inr'=>$price!==''?(int)$price:null,
                'quantity'=>$quantity!==''?(int)$quantity:null,
            ];
        }
        if(!$rows)throw new InvalidArgumentException('The CSV contains no plant offers.');
        return $rows;
    }finally{
        fclose($handle);
    }
}
function supplier_csv_stage(string $file,string $tenantId): array {
    $identity=tenant_require('offers.draft',$tenantId);
    $rows=supplier_csv_parse_file($file,$tenantId);
    $id=uuidv7();
    $_SESSION['supplier_csv_import']=[
        'id'=>$id,
        'token'=>bin2hex(random_bytes(24)),
        'tenant_id'=>$tenantId,
        'user_id'=>$identity['id'],
        'expires_at'=>time()+900,
        'rows'=>$rows,
    ];
    return $_SESSION['supplier_csv_import'];
}
function supplier_csv_pending(string $tenantId): ?array {
    $identity=tenant_identity();
    $draft=$_SESSION['supplier_csv_import']??null;
    if(!is_array($draft))return null;
    if(!$identity||($identity['tenant_id']??'')!==$tenantId||$draft['tenant_id']!==$tenantId
        ||$draft['user_id']!==$identity['id']||$draft['expires_at']<time()){
        unset($_SESSION['supplier_csv_import']);
        return null;
    }
    return $draft;
}
/** Draft-only: one locked aggregate update. Retry of same batch is idempotent. */
function supplier_csv_insert_drafts(string $tenantId,string $importId,array $rows): array {
    tenant_require('offers.draft',$tenantId);
    if(!tenant_valid_id($tenantId)||!tenant_valid_id($importId)||count($rows)<1||count($rows)>50)
        throw new InvalidArgumentException('Invalid import batch.');
    $normalized=[];
    foreach($rows as $n=>$row){
        if(!is_array($row))throw new InvalidArgumentException('Invalid import row.');
        $branchId=(string)($row['branch_id']??'');
        supplier_branch_for_offer($tenantId,$branchId,false);
        $guid=(string)($row['product_guid']??'');
        $product=product_by_guid($guid);
        if(!$product||($product['status']??'')!=='active')throw new InvalidArgumentException('A plant is no longer published. Preview the import again.');
        $label=(string)($row['label']??'');
        if(text_len($label)<2||text_len($label)>140||preg_match('/[\x00-\x1F\x7F]/',$label))throw new InvalidArgumentException('Invalid plant specification.');
        $price=$row['price_inr']??null;
        $quantity=$row['quantity']??null;
        if($price!==null&&(!is_int($price)||$price<1||$price>10000000))throw new InvalidArgumentException('Invalid price.');
        if($quantity!==null&&(!is_int($quantity)||$quantity<0||$quantity>1000000))throw new InvalidArgumentException('Invalid quantity.');
        $normalized[]=['branch_id'=>$branchId,'product_guid'=>$guid,'label'=>$label,'price_inr'=>$price,'quantity'=>$quantity,
            'batch_key'=>hash('sha256',$tenantId.'|'.$importId.'|'.$n)];
    }
    marketplace_ensure_dir();
    $result=['created'=>0,'skipped'=>0];
    foundation_json_mutate(marketplace_registry_file(),static function(array $raw)use($normalized,$tenantId,&$result){
        $supplierValid=false;
        foreach((array)($raw['suppliers']??[]) as $supplier){
            if(($supplier['id']??'')===$tenantId&&($supplier['status']??'')==='verified'){$supplierValid=true;break;}
        }
        if(!$supplierValid)throw new RuntimeException('Supplier was suspended before the import could be saved.');
        $offers=array_values((array)($raw['offers']??[]));
        $seen=[];$batchIds=[];
        $tenantCount=0;
        foreach($offers as $offer){
            if(($offer['supplier_id']??'')!==$tenantId)continue;
            $tenantCount++;
            if(isset($offer['import_key']))$batchIds[(string)$offer['import_key']]=true;
            if(($offer['status']??'draft')!=='withdrawn')
                $seen[(string)($offer['branch_id']??'').'|'.(string)($offer['product_guid']??'').'|'.text_lower((string)($offer['label']??''))]=true;
        }
        if($tenantCount>1500)throw new RuntimeException('Supplier offer limit reached for the file-first pilot.');
        $now=gmdate('c');
        foreach($normalized as $item){
            if(isset($batchIds[$item['batch_key']])){$result['skipped']++;continue;}
            $key=$item['branch_id'].'|'.$item['product_guid'].'|'.text_lower($item['label']);
            if(isset($seen[$key])){$result['skipped']++;continue;}
            if($tenantCount+$result['created']>=1500)throw new RuntimeException('Supplier pilot offer limit reached.');
            $offers[]=[
                'id'=>uuidv7(),'supplier_id'=>$tenantId,'branch_id'=>$item['branch_id'],'product_guid'=>$item['product_guid'],
                'label'=>$item['label'],'status'=>'draft','availability'=>'unknown',
                'price_inr'=>$item['price_inr'],'quantity'=>$item['quantity'],
                'evidence_ref'=>'','import_key'=>$item['batch_key'],'checked_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
            ];
            $seen[$key]=true;$result['created']++;
        }
        $raw['schema_version']=1;$raw['offers']=$offers;
        return $raw;
    },'marketplace.supplier.csv_drafts');
    marketplace_offers_store(true);
    $identity=tenant_identity();
    audit_event('marketplace.supplier.csv_import',['tenant_id'=>$tenantId,'user_id'=>$identity['id']??null,
        'created'=>$result['created'],'skipped'=>$result['skipped']]);
    return $result;
}
function supplier_csv_commit(string $tenantId,string $token): array {
    tenant_require('offers.draft',$tenantId);
    $pending=supplier_csv_pending($tenantId);
    if(!$pending||!hash_equals((string)$pending['token'],$token))throw new RuntimeException('CSV preview expired. Upload your file again.');
    $result=supplier_csv_insert_drafts($tenantId,(string)$pending['id'],(array)$pending['rows']);
    unset($_SESSION['supplier_csv_import']);
    return $result;
}
