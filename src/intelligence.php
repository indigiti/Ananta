<?php
declare(strict_types=1);

function intelligence_root(): string { return data_root().'/private-domain/designos/intelligence'; }
function intelligence_photo_dir(): string { return intelligence_root().'/photos'; }
function intelligence_file(): string { return intelligence_root().'/analyses.json'; }
function intelligence_ensure_dirs(): void { foreach([intelligence_root(),intelligence_photo_dir()] as $d)if(!is_dir($d))@mkdir($d,0750,true); }
function intelligence_analyses(): array { intelligence_ensure_dirs();try{return confidential_json_read(intelligence_file(),'sanjaynursery:intelligence:analyses:v1',[]);}catch(Throwable){return[];} }
function intelligence_customer_room(string $roomGuid): array {
    $room=customer_room_plan($roomGuid);if(!$room)throw new RuntimeException('Room not found.');return $room;
}
function intelligence_photo_store(string $roomGuid,array $file): array {
    $room=intelligence_customer_room($roomGuid);if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a room photo.');if(($file['size']??0)>10*1024*1024)throw new RuntimeException('Room photo exceeds 10 MB.');
    $tmp=(string)$file['tmp_name'];$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($tmp);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('Upload JPEG, PNG or WebP only.');
    intelligence_ensure_dirs();$guid=uuidv7();$name=$guid.'.webp';$dest=intelligence_photo_dir().'/'.$name;$worker=dirname(__DIR__).'/python/media_worker.py';$cmd='python3 '.escapeshellarg($worker).' '.escapeshellarg($tmp).' '.escapeshellarg($dest).' 2>&1';$out=[];$code=1;exec($cmd,$out,$code);$payload=json_decode(implode("\n",$out),true);if($code!==0||empty($payload['ok'])){ @unlink($dest);throw new RuntimeException('Unable to safely process room photo.');}@chmod($dest,0640);
    $row=['guid'=>$guid,'room_guid'=>$roomGuid,'customer_guid'=>$room['customer_guid'],'file'=>$name,'sha256'=>hash_file('sha256',$dest),'width'=>(int)($payload['width']??0),'height'=>(int)($payload['height']??0),'created_at'=>gmdate('c'),'analysis'=>null];
    $all=intelligence_analyses();$all[]=$row;confidential_json_write(intelligence_file(),$all,'sanjaynursery:intelligence:analyses:v1',foundation_checksum(intelligence_file()));audit_event('intelligence.photo_upload',['room_guid'=>$roomGuid,'photo_guid'=>$guid]);return $row;
}
function intelligence_photo(string $photoGuid): ?array { foreach(intelligence_analyses() as $row)if(($row['guid']??'')===$photoGuid){if(($row['customer_guid']??'')!==(customer_identity()['guid']??''))return null;return $row;}return null; }
function intelligence_room_photos(string $roomGuid): array { $room=intelligence_customer_room($roomGuid);return array_values(array_filter(intelligence_analyses(),fn($r)=>($r['room_guid']??'')===$roomGuid&&($r['customer_guid']??'')===$room['customer_guid'])); }
function intelligence_analyze(string $photoGuid): array {
    $photo=intelligence_photo($photoGuid);if(!$photo)throw new RuntimeException('Photo not found.');$path=intelligence_photo_dir().'/'.$photo['file'];if(!is_file($path))throw new RuntimeException('Photo file missing.');$worker=dirname(__DIR__).'/python/visual_worker.py';$out=[];$code=1;exec('python3 '.escapeshellarg($worker).' '.escapeshellarg($path).' 2>&1',$out,$code);$result=json_decode(implode("\n",$out),true);if($code!==0||empty($result['ok']))throw new RuntimeException('Visual analysis failed.');
    $room=customer_room_plan((string)$photo['room_guid']);$style=$room&&!empty($room['style_profile_guid'])?customer_style_profile((string)$room['style_profile_guid']):null;$recs=$room?designos_room_recommendations($room,6):[];$result['room_type']=$room['room_type']??'room';$result['recommendations']=array_map(fn($r)=>['slug'=>$r['product']['slug'],'name'=>$r['product']['name'],'price'=>$r['product']['price'],'score'=>$r['score']??null],$recs);$result['style_profile']=$style['profile_key']??null;$result['disclaimer']='Conceptual visual intelligence for staging. Verify dimensions, wall type, clearances and installation requirements before purchase.';$result['analyzed_at']=gmdate('c');
    $all=intelligence_analyses();foreach($all as &$row)if(($row['guid']??'')===$photoGuid)$row['analysis']=$result;unset($row);confidential_json_write(intelligence_file(),$all,'sanjaynursery:intelligence:analyses:v1',foundation_checksum(intelligence_file()));audit_event('intelligence.analyze',['photo_guid'=>$photoGuid,'provider'=>$result['provider']??'']);return $result;
}
function intelligence_delete_photo(string $photoGuid): void { $photo=intelligence_photo($photoGuid);if(!$photo)throw new RuntimeException('Photo not found.');$all=array_values(array_filter(intelligence_analyses(),fn($r)=>($r['guid']??'')!==$photoGuid));confidential_json_write(intelligence_file(),$all,'sanjaynursery:intelligence:analyses:v1',foundation_checksum(intelligence_file()));@unlink(intelligence_photo_dir().'/'.$photo['file']);audit_event('intelligence.photo_delete',['photo_guid'=>$photoGuid]); }
function intelligence_release_checks(): array {
    $schema=true;$files=true;$owners=true;$customers=array_column(customer_users(),'guid');$rooms=array_column(designos_room_plans(),'guid');foreach(intelligence_analyses() as $r){foreach(['guid','room_guid','customer_guid','file','sha256'] as $k)if(empty($r[$k]))$schema=false;if(!is_file(intelligence_photo_dir().'/'.($r['file']??'')))$files=false;if(!in_array($r['customer_guid']??'',$customers,true)||!in_array($r['room_guid']??'',$rooms,true))$owners=false;}
    return [
      ['name'=>'Visual intelligence encryption','ok'=>confidential_key()!==null&&confidential_crypto_available(),'detail'=>'Room-photo metadata and analyses use encrypted private persistence.'],
      ['name'=>'Visual intelligence schema','ok'=>$schema,'detail'=>$schema?'Room-photo intelligence records satisfy required schema.':'Malformed intelligence record detected.'],
      ['name'=>'Visual intelligence file integrity','ok'=>$files,'detail'=>$files?'Every intelligence record resolves to a private processed image.':'A room photo file is missing.'],
      ['name'=>'Visual intelligence owner integrity','ok'=>$owners,'detail'=>$owners?'Every room-photo analysis is owned by a known customer/room.':'Orphan room-photo intelligence record detected.'],
      ['name'=>'Visual intelligence provider','ok'=>is_file(dirname(__DIR__).'/python/visual_worker.py'),'detail'=>'Sandbox visual provider is packaged; production model/provider integration remains a production gate.'],
    ];
}
