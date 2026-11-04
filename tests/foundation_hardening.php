<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/src/foundation.php';
$errors=[];
$key=base64_encode(random_bytes(32));
putenv('SANJAY_DATA_KEY='.$key);
$path=$root.'/storage/tmp/foundation-test.json';
@unlink($path);
try {
    $c1=foundation_json_write($path,['value'=>1],null,'test.create');
    $c2=foundation_json_write($path,['value'=>2],$c1,'test.update');
    if($c1===$c2) $errors[]='Checksum did not change after update.';
    try { foundation_json_write($path,['value'=>3],$c1,'test.stale'); $errors[]='Stale expected checksum was accepted.'; }
    catch(RuntimeException $e){ if(!str_contains($e->getMessage(),'conflict')) $errors[]='Unexpected stale-write error.'; }
    $env=confidential_encrypt(['email'=>'test@example.invalid','order'=>'O-1'],'test:context');
    $plain=confidential_decrypt($env,'test:context');
    if(($plain['order']??null)!=='O-1') $errors[]='Encryption roundtrip failed.';
    $tampered=$env; $raw=base64_decode($tampered['ciphertext'],true); $raw[0]=chr(ord($raw[0])^1); $tampered['ciphertext']=base64_encode($raw);
    try { confidential_decrypt($tampered,'test:context'); $errors[]='Tampered ciphertext was accepted.'; } catch(Throwable) {}
    if(foundation_incomplete_transactions()) $errors[]='Incomplete transactions detected after test.';
} catch(Throwable $e){ $errors[]=$e->getMessage(); }
@unlink($path);
if($errors){fwrite(STDERR,implode("\n",$errors)."\n");exit(1);} 
echo "PASS: lock/write/checksum guard, transaction journal, stale-write rejection, authenticated encryption and tamper detection verified.\n";
