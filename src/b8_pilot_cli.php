<?php
declare(strict_types=1);
/**
 * Cloudways: php /path/to/private_html/ananta/src/b8_pilot_cli.php status
 *           php .../b8_pilot_cli.php backup
 *           php .../b8_pilot_cli.php verify /private/backup/b8-...
 * No automatic external messaging, payments or Merchant API calls.
 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require __DIR__.'/bootstrap.php';
$action=$argv[1]??'status';
try{
    if($action==='status')$out=b8_acceptance_snapshot();
    elseif($action==='backup')$out=['backup_path'=>b8_acceptance_backup_create(),
        'offsite_copy_complete'=>false,'restore_verified'=>false];
    elseif($action==='verify'&&isset($argv[2]))$out=b8_acceptance_backup_verify($argv[2]);
    else{fwrite(STDERR,"Usage: php src/b8_pilot_cli.php status|backup|verify <backup-dir>\n");exit(2);}
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,"B8 pilot command failed: ".get_class($e)."\n");exit(1);}
