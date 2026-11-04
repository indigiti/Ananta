<?php
declare(strict_types=1);
/** Scheduled feed audit; does not upload to Google or modify public products. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require __DIR__.'/bootstrap.php';
try {
    $snapshot=discover_distribution_refresh();
    echo json_encode(['timestamp'=>$snapshot['at'],'sku_count'=>$snapshot['merchant']['sku_count'],
      'feed_sha256'=>$snapshot['merchant']['feed_sha256'],
      'valid'=>$snapshot['merchant']['valid'],'issues'=>$snapshot['merchant']['issues'],
      'merchant_submission'=>'not_performed'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    exit($snapshot['merchant']['valid']?0:2);
}catch(Throwable $e){fwrite(STDERR,"Distribution audit failed: ".get_class($e)."\n");exit(1);}
