<?php
declare(strict_types=1);

/** B8-G read-only Google processed-products diagnostics and optional partner CSV. */
function merchant_google_file(): string {
    $test=trim((string)getenv('ANANTA_TEST_MERCHANT_GOOGLE_FILE'));
    if(PHP_SAPI==='cli'&&($GLOBALS['app']['environment']??'')==='staging'&&$test!=='')return $test;
    return data_root().'/private-domain/discover/google-processed-status.json';
}
function merchant_google_store(): array {
    return array_replace(['version'=>1,'last'=>null],
        confidential_json_read(merchant_google_file(),'ananta:discover:google-status:v1',[]));
}
function merchant_google_configured(): bool {
    return (bool)preg_match('/^[0-9]{5,20}$/D',trim((string)getenv('ANANTA_MERCHANT_ACCOUNT_ID')))
        &&strlen(trim((string)getenv('ANANTA_MERCHANT_OAUTH_TOKEN')))>30;
}
function merchant_google_get_page(string $url,string $token): array {
    if(!extension_loaded('curl'))throw new RuntimeException('cURL required for Merchant API.');
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>[
        'Authorization: Bearer '.$token,'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>18,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $body=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    curl_close($curl);
    if($body===false||$code!==200||strlen($body)>1048576)
        throw new RuntimeException('Merchant API read failed or returned oversized page.');
    $data=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new RuntimeException('Invalid Merchant API response.');
    return $data;
}
function merchant_google_sync(?callable $fetchPage=null): array {
    if(PHP_SAPI!=='cli')require_admin();
    $account=trim((string)getenv('ANANTA_MERCHANT_ACCOUNT_ID'));
    $token=trim((string)getenv('ANANTA_MERCHANT_OAUTH_TOKEN'));
    if(!preg_match('/^[0-9]{5,20}$/D',$account)||strlen($token)<30)
        throw new RuntimeException('Merchant account ID and content-scoped OAuth access token are required.');
    $observed=[];$pageToken='';$pages=0;$all=0;
    do{
        $url='https://merchantapi.googleapis.com/products/v1/accounts/'.$account.'/products?pageSize=250'.
            ($pageToken!==''?'&pageToken='.rawurlencode($pageToken):'');
        $response=$fetchPage?$fetchPage($url,$token):merchant_google_get_page($url,$token);
        if(!is_array($response)||!is_array($response['products']??[]))
            throw new RuntimeException('Invalid Merchant API product page.');
        $pages++;
        if($pages>20)throw new RuntimeException('Merchant processed-products pagination limit exceeded.');
        foreach($response['products'] as $p){
            if(!is_array($p))throw new RuntimeException('Invalid processed product.');
            $sku=trim((string)($p['offerId']??''));
            if($sku===''||strlen($sku)>120)continue;
            $all++;
            $status=(array)($p['productStatus']??[]);
            $destination=(array)($status['destinationStatuses']??[]);
            $state='unknown';$statuses=[];
            foreach($destination as $row){
                if(!is_array($row))continue;
                $context=(string)($row['reportingContext']??'');
                // Record each reporting context; an ad approval is NOT a free-listing approval.
                $countryState=in_array('IN',(array)($row['approvedCountries']??[]),true)?'approved':
                    (in_array('IN',(array)($row['disapprovedCountries']??[]),true)?'disapproved':
                    (in_array('IN',(array)($row['pendingCountries']??[]),true)?'pending':'unknown'));
                if($context!=='')$statuses[$context]=$countryState;
            }
            if(($statuses['FREE_LISTINGS']??'unknown')!=='unknown')$state=$statuses['FREE_LISTINGS'];
            elseif(($statuses['FREE_LOCAL_LISTINGS']??'unknown')!=='unknown')$state=$statuses['FREE_LOCAL_LISTINGS'];
            $issues=[];
            foreach((array)($status['itemLevelIssues']??[]) as $issue){
                if(!is_array($issue)||count($issues)>=12)break;
                $countries=(array)($issue['applicableCountries']??[]);
                if($countries&& !in_array('IN',$countries,true))continue;
                $issues[]=['code'=>substr((string)($issue['code']??'unknown'),0,80),
                    'severity'=>substr((string)($issue['severity']??'unknown'),0,35),
                    'context'=>substr((string)($issue['reportingContext']??''),0,55)];
            }
            $observed[$sku]=['sku'=>$sku,'free_listings_in'=>$state,
                'destinations'=>$statuses,'issues'=>$issues];
        }
        $next=(string)($response['nextPageToken']??'');
        if($next!==''&&($next===$pageToken||strlen($next)>2048))
            throw new RuntimeException('Merchant API pagination token invalid.');
        $pageToken=$next;
    }while($pageToken!=='');
    $feedIds=array_fill_keys(array_map(static fn($p)=>(string)$p['id'],discover_merchant_rows()),true);
    $totals=['feed_skus'=>count($feedIds),'google_processed'=>count($observed),
        'feed_missing_in_google'=>0,'google_outside_current_feed'=>0,
        'approved_in'=>0,'pending_in'=>0,'disapproved_in'=>0,'unknown_in'=>0,'issue_count'=>0];
    foreach($feedIds as $id=>$v)if(!isset($observed[$id]))$totals['feed_missing_in_google']++;
    foreach($observed as $sku=>$r){
        if(!isset($feedIds[$sku]))$totals['google_outside_current_feed']++;
        $name=$r['free_listings_in'];
        $totals[($name==='approved'?'approved_in':($name==='pending'?'pending_in':($name==='disapproved'?'disapproved_in':'unknown_in')))]++;
        $totals['issue_count']+=count($r['issues']);
    }
    $summary=['account_id'=>$account,'synced_at'=>gmdate('c'),'pages'=>$pages,
        'totals'=>$totals,'status_by_sku'=>$observed,
        'source'=>'Google Merchant API products.list (processed products, country IN)',
        'provider_verified'=>$fetchPage===null,'fixture_response'=>$fetchPage!==null,
        'feed_submitted_by_app'=>false,'merchant_approval_proved_only_by_google'=>true];
    confidential_json_mutate(merchant_google_file(),'ananta:discover:google-status:v1',
        static fn(array $d)=>['version'=>1,'last'=>$summary], 'merchant.google.readonly.sync');
    audit_event('merchant.google.readonly.sync',['account_id'=>$account,'products'=>count($observed),'pages'=>$pages]);
    return $summary;
}
function merchant_google_report(): array {
    $last=merchant_google_store()['last'];
    return ['configured'=>merchant_google_configured(),'last'=>$last,
        'fresh'=>$last!==null&&strtotime((string)$last['synced_at'])>time()-86400,
        'provider_verified'=>$last!==null&&!empty($last['provider_verified']),
        'sync_type'=>'read_only_processed_products','submission_performed'=>false];
}
function merchant_openai_compatible_csv(): string {
    // OpenAI product feed onboarding is independently approved. This is an
    // administrator-only local export of core Google-compatible fields, not SFTP.
    require_admin();
    $rows=discover_merchant_rows();
    if(!$rows)throw new RuntimeException('No verified purchasable SKUs: partner export is blocked.');
    $required=['id','title','description','link','image_link','availability','price','brand'];
    $cols=array_merge($required,['identifier_exists','gtin','mpn','item_group_id','size','condition']);
    $handle=fopen('php://temp','w+');fputcsv($handle,$cols);
    foreach($rows as $r){
        foreach($required as $key)if(trim((string)($r[$key]??''))===''){
            fclose($handle);throw new RuntimeException('Incomplete product row cannot be exported.');
        }
        $values=array_map(static fn($c)=>(string)($r[$c]??''),$cols);
        foreach($values as &$value)if(preg_match('/^[=+\-@\t\r]/',$value))$value="'".$value;
        unset($value);fputcsv($handle,$values);
    }
    rewind($handle);$csv=stream_get_contents($handle);fclose($handle);return $csv;
}
