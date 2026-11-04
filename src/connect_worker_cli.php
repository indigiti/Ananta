<?php
declare(strict_types=1);
/**
 * Cloudways cron: /usr/bin/php /home/.../private_html/ananta/src/connect_worker_cli.php
 * Queue is inert without tenant activation, opt-in and ANANTA_CONNECT_ALLOW_SEND=1.
 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require __DIR__.'/bootstrap.php';
$steps=(int)($argv[1]??5);
if($steps<1||$steps>20){fwrite(STDERR,"Usage: php connect_worker_cli.php [1-20]\n");exit(2);}
for($i=0;$i<$steps;$i++){
    $r=connect_worker_step();
    echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    if($r['result']==='empty')break;
}
