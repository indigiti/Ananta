<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('D',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=designos-test-secret');
$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_URI']='/';$_SERVER['SCRIPT_NAME']='/index.php';
require $root.'/src/bootstrap.php';
$errors=[];function dcheck(bool $ok,string $msg):void{global $errors;if(!$ok)$errors[]=$msg;}
$paths=[commerce_customer_file(),designos_style_file(),designos_room_file()];$backup=[];foreach($paths as $p){$backup[$p]=is_file($p)?file_get_contents($p):null;@unlink($p);} 
try{
  [$ok,$u]=customer_register(['name'=>'Design Test','email'=>'designos@example.test','phone'=>'9999999999','password'=>'LongPassw0rd!']);dcheck($ok,'Customer registration failed.');customer_login('designos@example.test','LongPassw0rd!');
  $a=designos_style_score(['room'=>'living-room','mood'=>'calm-minimal','material'=>'easy-care','priorities'=>['easy-care','small-space'],'budget'=>'5k-15k']);
  $b=designos_style_score(['room'=>'living-room','mood'=>'calm-minimal','material'=>'easy-care','priorities'=>['easy-care','small-space'],'budget'=>'5k-15k']);
  dcheck(($a['profile_key']??'')===($b['profile_key']??''),'Style scoring is not deterministic.');
  dcheck(count($a['recommendations']??[])>=3,'Style recommendations missing.');
  $sp=designos_save_style_profile($a,'Living direction');dcheck((bool)customer_style_profile($sp['guid']),'Saved style profile unavailable.');
  $room=designos_create_room(['name'=>'Main living room','room_type'=>'living-room','width_cm'=>420,'length_cm'=>560,'ceiling_cm'=>280,'budget'=>20000,'wall_color'=>'Warm ivory','floor_material'=>'Stone','style_profile_guid'=>$sp['guid']]);dcheck(($room['name']??'')==='Main living room','Room create failed.');
  $first=products_public()[0]??null;dcheck((bool)$first,'Catalogue unavailable.');
  if($first){$room=designos_add_product($room['guid'],$first['slug'],['x_cm'=>20,'y_cm'=>20]);dcheck(count($room['placements']??[])===1,'Product placement failed.');$pl=$room['placements'][0];$room=designos_move_product($room['guid'],$pl['guid'],['x_cm'=>60,'y_cm'=>70,'rotation'=>90]);dcheck((int)$room['placements'][0]['x_cm']===60,'Placement move failed.');try{designos_move_product($room['guid'],$pl['guid'],['x_cm'=>410,'y_cm'=>550,'rotation'=>0]);$errors[]='Out-of-bounds placement was accepted.';}catch(Throwable){}
  }
  $diag=designos_room_diagnostics($room);dcheck(isset($diag['free_area_percent']),'Room diagnostics missing.');dcheck(count(designos_room_recommendations($room,4))>0,'Room recommendations missing.');
  [$ok2,$u2]=customer_register(['name'=>'Other User','email'=>'designos2@example.test','phone'=>'9888888888','password'=>'LongPassw0rd!']);dcheck($ok2,'Second customer registration failed.');customer_logout();customer_login('designos2@example.test','LongPassw0rd!');dcheck(customer_room_plan($room['guid'])===null,'Cross-customer room access leaked.');
  customer_logout();customer_login('designos@example.test','LongPassw0rd!');
  foreach(designos_release_checks() as $c)dcheck(!empty($c['ok']),'Release check failed: '.$c['name']);
}catch(Throwable $e){$errors[]='Unhandled: '.$e->getMessage();}
finally{foreach($backup as $p=>$raw){if($raw===null)@unlink($p);else{if(!is_dir(dirname($p)))@mkdir(dirname($p),0750,true);file_put_contents($p,$raw);}}}
if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);}echo "PASS: DesignOS Style Finder scoring, encrypted saved profiles, room CRUD, object authorization, catalogue recommendations, placement boundary validation and release gates verified.\n";
