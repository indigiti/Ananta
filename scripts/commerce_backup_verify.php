<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$arg=$argv[1]??'';if($arg===''){fwrite(STDERR,"Usage: php scripts/commerce_backup_verify.php <backup-dir>\n");exit(2);} $dir=realpath($arg);if(!$dir||!is_dir($dir)){fwrite(STDERR,"Backup directory not found.\n");exit(2);} $manifest=json_decode((string)file_get_contents($dir.'/manifest.json'),true);if(!is_array($manifest)){fwrite(STDERR,"Invalid manifest.\n");exit(2);} $bad=[];foreach($manifest['files']??[] as $name=>$hash){$p=$dir.'/'.$name;if(!is_file($p)||!hash_equals((string)$hash,(string)hash_file('sha256',$p)))$bad[]=$name;}if($bad){fwrite(STDERR,"FAIL: checksum mismatch: ".implode(', ',$bad)."\n");exit(1);}echo "PASS: encrypted commerce backup checksums verified for ".count($manifest['files']??[])." file(s).\n";
