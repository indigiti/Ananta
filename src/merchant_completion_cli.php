<?php
declare(strict_types=1);
/**
 * Cloudways private CLI:
 *   php src/merchant_completion_cli.php status
 *   php src/merchant_completion_cli.php google-readonly
 *   php src/merchant_completion_cli.php catalogue-csv /private/path/report.csv
 * No writeback to products, stock or Google feeds is performed.
 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require __DIR__.'/bootstrap.php';
$cmd=$argv[1]??'status';
try{
    if($cmd==='status'){
        $r=merchant_completion_reconcile();
        $g=merchant_google_report();
        $result=['baseline'=>$r['stats']['baseline'],'live_catalogue'=>$r['stats']['live'],
            'discrepancies'=>$r['stats'],'reason_counts'=>$r['reason_counts'],
            'google'=>['configured'=>$g['configured'],'provider_verified'=>$g['provider_verified'],
                'last_sync'=>$g['last']['synced_at']??null],
            'live_inventory_reconciled_read_only'=>true,'google_submission_performed'=>false];
    }elseif($cmd==='google-readonly'){
        $result=merchant_google_sync();
    }elseif($cmd==='catalogue-csv'&&isset($argv[2])){
        $path=$argv[2];$dir=realpath(dirname($path));
        if(!$dir||!is_dir($dir)||str_starts_with($dir,(string)realpath(dirname(__DIR__).'/public')))
            throw new RuntimeException('CSV may only be written to an existing private directory.');
        if(is_link($path)||is_file($path))throw new RuntimeException('CSV path must be new and not a symlink.');
        $data=merchant_completion_csv();
        if(file_put_contents($path,$data,LOCK_EX)===false)throw new RuntimeException('CSV write failed.');
        chmod($path,0600);
        $result=['private_csv_path'=>$path,'bytes'=>strlen($data),'product_updates'=>false];
    }else{fwrite(STDERR,"Usage: php src/merchant_completion_cli.php status|google-readonly|catalogue-csv <private-new-file>\n");exit(2);}
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){
    fwrite(STDERR,"B8-G reconciliation failed: ".get_class($e).": ".$e->getMessage()."\n");exit(1);
}
