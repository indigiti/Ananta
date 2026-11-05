<?php
declare(strict_types=1);
/** ACP staging operations. Production payments intentionally unavailable. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require __DIR__.'/bootstrap.php';
$cmd=$argv[1]??'status';
try {
    if($cmd==='status')$out=acp_readiness_report();
    elseif($cmd==='events-worker')$out=acp_events_worker_step();
    else{fwrite(STDERR,"Usage: php src/acp_cli.php status|events-worker\n");exit(2);}
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){
    fwrite(STDERR,"ACP worker: ".get_class($e)."\n");exit(1);
}
