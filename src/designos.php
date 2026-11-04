<?php
declare(strict_types=1);

/**
 * Sanjay Nursery DesignOS — v0.8.0
 * Style Finder + Room Planner. File-authoritative staging implementation.
 * Saved client design state is confidential and encrypted at rest.
 */

function designos_root(): string { return data_root().'/private-domain/designos'; }
function designos_style_file(): string { return designos_root().'/style-profiles.json'; }
function designos_room_file(): string { return designos_root().'/room-plans.json'; }

function designos_ensure_dirs(): void {
    $dir=designos_root();
    if(!is_dir($dir) && !@mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Unable to create DesignOS storage directory.');
    @chmod($dir,0750);
}

function designos_styles(): array {
    return [
        'tropical-lush'=>['name'=>'Tropical Lush','moods'=>['lush-green','bold-botanical'],'materials'=>['foliage-first','natural-planters'],'palette'=>['Deep green','Fern','Warm clay','Cream'],'copy'=>'Layered foliage, generous leaf shapes and warm natural planters for a lush indoor-garden feel.'],
        'calm-indoor'=>['name'=>'Calm Indoor','moods'=>['calm-minimal','soft-natural'],'materials'=>['easy-care','neutral-planters'],'palette'=>['Soft white','Sage','Stone','Leaf green'],'copy'=>'Easy-care plants, restrained pots and clean spacing for calm bedrooms, workspaces and living areas.'],
        'sunny-balcony'=>['name'=>'Sunny Balcony','moods'=>['bright-airy','bold-botanical'],'materials'=>['sun-loving','weather-ready'],'palette'=>['Leaf green','Terracotta','Sky','Sand'],'copy'=>'Sun-loving plants and durable planters composed for balconies, terraces and brighter outdoor edges.'],
        'succulent-studio'=>['name'=>'Succulent Studio','moods'=>['calm-minimal','graphic-modern'],'materials'=>['succulents-cacti','ceramic-planters'],'palette'=>['Sage','Chalk','Clay','Graphite'],'copy'=>'Compact sculptural plants with ceramic forms for desks, shelves and small-space styling.'],
        'garden-earth'=>['name'=>'Garden Earth','moods'=>['soft-natural','lush-green'],'materials'=>['garden-ready','natural-planters'],'palette'=>['Moss','Soil','Terracotta','Limestone'],'copy'=>'Outdoor plants, practical garden essentials and earthy containers for grounded, long-lived garden compositions.'],
        'gift-bloom'=>['name'=>'Gift Bloom','moods'=>['bright-airy','graphic-modern'],'materials'=>['gift-ready','premium-planters'],'palette'=>['Forest','Ivory','Rose clay','Brass'],'copy'=>'Presentation-ready green gifts with refined planters and polished packaging for homes, teams and occasions.'],
    ];
}

function designos_rooms(): array {
    return [
        'living-room'=>'Living room','bedroom'=>'Bedroom','study'=>'Study / home office','kitchen'=>'Kitchen',
        'balcony'=>'Balcony / terrace','entrance'=>'Entrance','garden'=>'Garden','office'=>'Office / workplace'
    ];
}
function designos_moods(): array {
    return ['lush-green'=>'Lush green','calm-minimal'=>'Calm minimal','bright-airy'=>'Bright & airy','soft-natural'=>'Soft natural','bold-botanical'=>'Bold botanical','graphic-modern'=>'Graphic modern'];
}
function designos_material_directions(): array {
    return ['easy-care'=>'Easy-care plants','foliage-first'=>'Foliage first','sun-loving'=>'Sun-loving','succulents-cacti'=>'Succulents & cacti','natural-planters'=>'Natural planters','ceramic-planters'=>'Ceramic planters','weather-ready'=>'Weather-ready','gift-ready'=>'Gift-ready'];
}
function designos_priorities(): array {
    return ['easy-care'=>'Easy care','air-purifying'=>'Air-purifying','low-light'=>'Low-light friendly','pet-aware'=>'Pet-aware selection','small-space'=>'Small-space friendly','statement'=>'Statement greenery'];
}
function designos_budgets(): array {
    return ['under-5k'=>['label'=>'Under ₹5,000','max'=>5000],'5k-15k'=>['label'=>'₹5,000–₹15,000','max'=>15000],'15k-30k'=>['label'=>'₹15,000–₹30,000','max'=>30000],'30k-plus'=>['label'=>'₹30,000+','max'=>1000000]];
}

function designos_normalize_style_input(array $input): array {
    $room=(string)($input['room']??'living-room'); if(!isset(designos_rooms()[$room]))$room='living-room';
    $mood=(string)($input['mood']??'calm-minimal'); if(!isset(designos_moods()[$mood]))$mood='calm-minimal';
    $material=(string)($input['material']??'easy-care'); if(!isset(designos_material_directions()[$material]))$material='easy-care';
    $budget=(string)($input['budget']??'5k-15k'); if(!isset(designos_budgets()[$budget]))$budget='5k-15k';
    $priorities=$input['priorities']??[]; if(!is_array($priorities))$priorities=[$priorities];
    $priorities=array_values(array_intersect(array_keys(designos_priorities()),array_map('strval',$priorities)));
    if(!$priorities)$priorities=['easy-care'];
    return ['room'=>$room,'mood'=>$mood,'material'=>$material,'budget'=>$budget,'priorities'=>$priorities];
}

function designos_style_score(array $input): array {
    $n=designos_normalize_style_input($input);$scores=[];
    foreach(designos_styles() as $key=>$style){
        $score=35;
        if(in_array($n['mood'],$style['moods'],true))$score+=35;
        if(in_array($n['material'],$style['materials'],true))$score+=22;
        if($n['room']==='balcony'&&$key==='sunny-balcony')$score+=7;
        if($n['room']==='garden'&&$key==='garden-earth')$score+=7;
        if(in_array('small-space',$n['priorities'],true)&&in_array($key,['calm-indoor','succulent-studio'],true))$score+=6;
        if(in_array('statement',$n['priorities'],true)&&$key==='tropical-lush')$score+=6;
        $scores[$key]=min(99,$score);
    }
    arsort($scores);$key=(string)array_key_first($scores);$style=designos_styles()[$key];
    $result=['profile_key'=>$key,'profile_name'=>$style['name'],'score'=>(int)$scores[$key],'copy'=>$style['copy'],'palette'=>$style['palette'],'input'=>$n,'generated_at'=>gmdate('c')];
    $result['recommendations']=designos_recommend_products($result,6);
    return $result;
}

function designos_product_material_score(array $p,string $direction): int {
    $hay=text_lower(implode(' ',array_merge((array)($p['materials']??[]),(array)($p['finishes']??[]),(array)($p['tags']??[]))));
    $tokens=match($direction){
        'easy-care'=>['easy care','low maintenance','hardy'],
        'foliage-first'=>['foliage','leaf','tropical'],
        'sun-loving'=>['sun','outdoor','flowering'],
        'succulents-cacti'=>['succulent','cactus','cacti'],
        'natural-planters'=>['terracotta','natural','coir'],
        'ceramic-planters'=>['ceramic','pot','planter'],
        'weather-ready'=>['outdoor','weather','garden'],
        'gift-ready'=>['gift','corporate','premium'],
        default=>[]
    };
    $score=0;foreach($tokens as $t)if(str_contains($hay,$t))$score+=2;return min(8,$score);
}

function designos_recommend_products(array $styleResult,int $limit=6): array {
    $n=$styleResult['input']??designos_normalize_style_input([]);$room=(string)$n['room'];$prior=(array)$n['priorities'];$budgetMax=(int)(designos_budgets()[$n['budget']]['max']??15000);
    $roomCat=[
        'living-room'=>['indoor-plants'=>5,'succulents-cacti'=>3,'pots-planters'=>3,'outdoor-plants'=>0,'gardening-essentials'=>1,'corporate-gifting'=>1],
        'bedroom'=>['indoor-plants'=>5,'succulents-cacti'=>3,'pots-planters'=>2,'outdoor-plants'=>0,'gardening-essentials'=>1,'corporate-gifting'=>1],
        'study'=>['succulents-cacti'=>5,'indoor-plants'=>4,'pots-planters'=>3,'corporate-gifting'=>2,'gardening-essentials'=>1,'outdoor-plants'=>0],
        'kitchen'=>['indoor-plants'=>4,'gardening-essentials'=>3,'pots-planters'=>3,'succulents-cacti'=>2,'outdoor-plants'=>1,'corporate-gifting'=>0],
        'balcony'=>['outdoor-plants'=>6,'pots-planters'=>5,'succulents-cacti'=>3,'gardening-essentials'=>3,'indoor-plants'=>2,'corporate-gifting'=>0],
        'entrance'=>['outdoor-plants'=>5,'pots-planters'=>5,'indoor-plants'=>3,'succulents-cacti'=>2,'gardening-essentials'=>2,'corporate-gifting'=>1],
        'garden'=>['outdoor-plants'=>7,'gardening-essentials'=>5,'pots-planters'=>4,'succulents-cacti'=>2,'indoor-plants'=>1,'corporate-gifting'=>0],
        'office'=>['indoor-plants'=>5,'corporate-gifting'=>4,'pots-planters'=>3,'succulents-cacti'=>3,'gardening-essentials'=>1,'outdoor-plants'=>1],
    ];
    $priorityTags=['easy-care'=>['easy care','low maintenance','hardy'],'air-purifying'=>['air purifying','air-purifying'],'low-light'=>['low light','shade'],'pet-aware'=>['pet friendly','pet-safe'],'statement'=>['large','xl','statement','tropical'],'small-space'=>['compact','mini','desk','small']];
    $rank=[];
    foreach(products_public() as $p){
        $score=(int)($roomCat[$room][$p['category']??'']??0)*4;
        $score+=designos_product_material_score($p,(string)$n['material']);
        $hay=text_lower(implode(' ',array_merge((array)($p['tags']??[]),[$p['name']??'',$p['short']??''])));
        foreach($prior as $pr)foreach($priorityTags[$pr]??[] as $t)if(str_contains($hay,$t)){$score+=3;break;}
        $price=(int)($p['price']??0); if($price<=$budgetMax)$score+=5;else $score-=min(8,(int)ceil(($price-$budgetMax)/3000));
        if(!empty($p['featured']))$score+=1;
        $rank[]=['product'=>$p,'score'=>$score];
    }
    usort($rank,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp((string)$a['product']['name'],(string)$b['product']['name']));
    return array_slice($rank,0,max(1,$limit));
}

function designos_style_profiles(): array { designos_ensure_dirs();try{return confidential_json_read(designos_style_file(),'sanjaynursery:designos:style-profiles:v1',[]);}catch(Throwable){return [];} }
function customer_style_profiles(?string $customerGuid=null): array {
    $customerGuid=$customerGuid??(string)(customer_identity()['guid']??''); if($customerGuid==='')return[];
    $rows=array_values(array_filter(designos_style_profiles(),fn($x)=>($x['customer_guid']??'')===$customerGuid));usort($rows,fn($a,$b)=>strcmp((string)$b['created_at'],(string)$a['created_at']));return$rows;
}
function customer_style_profile(string $guid,?string $customerGuid=null): ?array {
    $customerGuid=$customerGuid??(string)(customer_identity()['guid']??'');foreach(customer_style_profiles($customerGuid) as $x)if(($x['guid']??'')===$guid)return$x;return null;
}
function designos_save_style_profile(array $result,string $label=''): array {
    require_customer('/style-finder');$cg=(string)customer_identity()['guid'];$label=trim($label);if($label==='')$label=$result['profile_name'].' · '.(designos_rooms()[$result['input']['room']]??'Room');
    $row=['guid'=>uuidv7(),'customer_guid'=>$cg,'label'=>(function_exists('mb_substr')?mb_substr($label,0,80):substr($label,0,80)),'result'=>$result,'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
    confidential_json_mutate(designos_style_file(),'sanjaynursery:designos:style-profiles:v1',function(array $rows)use($row){$rows[]=$row;return$rows;},'designos.style.save');
    if(function_exists('commerce_audit'))commerce_audit('designos.style.save',['customer_guid'=>$cg,'style_guid'=>$row['guid'],'profile_key'=>$result['profile_key']]);return$row;
}
function designos_delete_style_profile(string $guid): void {
    require_customer('/account/rooms');$cg=(string)customer_identity()['guid'];$found=false;
    confidential_json_mutate(designos_style_file(),'sanjaynursery:designos:style-profiles:v1',function(array $rows)use($guid,$cg,&$found){$out=[];foreach($rows as $r){if(($r['guid']??'')===$guid){if(($r['customer_guid']??'')!==$cg)throw new RuntimeException('Style profile access denied.');$found=true;continue;}$out[]=$r;}return$out;},'designos.style.delete');
    if(!$found)throw new RuntimeException('Style profile not found.');
}

function designos_room_plans(): array { designos_ensure_dirs();try{return confidential_json_read(designos_room_file(),'sanjaynursery:designos:rooms:v1',[]);}catch(Throwable){return[];} }
function customer_room_plans(?string $customerGuid=null): array {
    $customerGuid=$customerGuid??(string)(customer_identity()['guid']??'');if($customerGuid==='')return[];$rows=array_values(array_filter(designos_room_plans(),fn($r)=>($r['customer_guid']??'')===$customerGuid));usort($rows,fn($a,$b)=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));return$rows;
}
function customer_room_plan(string $guid,?string $customerGuid=null): ?array {
    $customerGuid=$customerGuid??(string)(customer_identity()['guid']??'');foreach(customer_room_plans($customerGuid) as $r)if(($r['guid']??'')===$guid)return$r;return null;
}
function designos_room_payload(array $input,?array $existing=null): array {
    $name=trim((string)($input['name']??$existing['name']??''));if(text_len($name)<2||text_len($name)>80)throw new RuntimeException('Room name must be between 2 and 80 characters.');
    $type=(string)($input['room_type']??$existing['room_type']??'living-room');if(!isset(designos_rooms()[$type]))throw new RuntimeException('Choose a valid room type.');
    $width=(int)($input['width_cm']??$existing['dimensions']['width_cm']??0);$length=(int)($input['length_cm']??$existing['dimensions']['length_cm']??0);$ceiling=(int)($input['ceiling_cm']??$existing['dimensions']['ceiling_cm']??270);
    if($width<180||$width>1500)throw new RuntimeException('Room width must be between 180 and 1500 cm.');if($length<180||$length>2000)throw new RuntimeException('Room length must be between 180 and 2000 cm.');if($ceiling<210||$ceiling>600)throw new RuntimeException('Ceiling height must be between 210 and 600 cm.');
    $budget=max(0,min(10000000,(int)($input['budget']??$existing['budget']??0)));
    $styleGuid=trim((string)($input['style_profile_guid']??$existing['style_profile_guid']??''));if($styleGuid!==''&&!customer_style_profile($styleGuid))throw new RuntimeException('Selected style profile is not available to this account.');
    return ['name'=>$name,'room_type'=>$type,'dimensions'=>['width_cm'=>$width,'length_cm'=>$length,'ceiling_cm'=>$ceiling],'wall_color'=>(function_exists('mb_substr')?mb_substr(trim((string)($input['wall_color']??$existing['wall_color']??'Warm ivory')),0,60):substr(trim((string)($input['wall_color']??$existing['wall_color']??'Warm ivory')),0,60)),'floor_material'=>(function_exists('mb_substr')?mb_substr(trim((string)($input['floor_material']??$existing['floor_material']??'Natural stone / tile')),0,60):substr(trim((string)($input['floor_material']??$existing['floor_material']??'Natural stone / tile')),0,60)),'budget'=>$budget,'style_profile_guid'=>$styleGuid?:null];
}
function designos_create_room(array $input): array {
    require_customer('/account/rooms/new');$cg=(string)customer_identity()['guid'];$payload=designos_room_payload($input);
    $row=['guid'=>uuidv7(),'customer_guid'=>$cg]+$payload+['placements'=>[],'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
    confidential_json_mutate(designos_room_file(),'sanjaynursery:designos:rooms:v1',function(array $rows)use($row){$rows[]=$row;return$rows;},'designos.room.create');if(function_exists('commerce_audit'))commerce_audit('designos.room.create',['customer_guid'=>$cg,'room_guid'=>$row['guid']]);return$row;
}
function designos_update_room(string $guid,array $input): array {
    require_customer('/account/rooms/'.$guid);$cg=(string)customer_identity()['guid'];$existing=customer_room_plan($guid,$cg);if(!$existing)throw new RuntimeException('Room plan not found.');$payload=designos_room_payload($input,$existing);$updated=null;
    confidential_json_mutate(designos_room_file(),'sanjaynursery:designos:rooms:v1',function(array $rows)use($guid,$cg,$payload,&$updated){foreach($rows as &$r)if(($r['guid']??'')===$guid){if(($r['customer_guid']??'')!==$cg)throw new RuntimeException('Room plan access denied.');$r=array_merge($r,$payload);$r['updated_at']=gmdate('c');$updated=$r;break;}return$rows;},'designos.room.update');if(!$updated)throw new RuntimeException('Room plan not found.');return$updated;
}
function designos_delete_room(string $guid): void {
    require_customer('/account/rooms');$cg=(string)customer_identity()['guid'];$found=false;confidential_json_mutate(designos_room_file(),'sanjaynursery:designos:rooms:v1',function(array $rows)use($guid,$cg,&$found){$out=[];foreach($rows as $r){if(($r['guid']??'')===$guid){if(($r['customer_guid']??'')!==$cg)throw new RuntimeException('Room plan access denied.');$found=true;continue;}$out[]=$r;}return$out;},'designos.room.delete');if(!$found)throw new RuntimeException('Room plan not found.');
}

function designos_product_footprint(array $p): array {
    $raw=(string)($p['dimensions']??'');$w=60;$d=30;
    if(preg_match('/([0-9]+(?:\.[0-9]+)?)\s*[×x]\s*([0-9]+(?:\.[0-9]+)?)\s*[×x]\s*([0-9]+(?:\.[0-9]+)?)/u',$raw,$m)){$w=(float)$m[1];$d=(float)$m[3];}
    elseif(preg_match('/([0-9]+(?:\.[0-9]+)?)\s*[×x]\s*([0-9]+(?:\.[0-9]+)?)/u',$raw,$m)){$w=(float)$m[1];$d=(float)$m[2];}
    $cat=(string)($p['category']??'');if(in_array($cat,['succulents-cacti','corporate-gifting'],true)){$w=min($w,45);$d=min($d,45);}if($cat==='outdoor-plants'){$w=max($w,35);$d=max($d,35);}
    return ['width_cm'=>max(8,min(220,(int)round($w))),'depth_cm'=>max(8,min(120,(int)round($d)))];
}
function designos_validate_placement(array $room,array $placement): void {
    $rw=(int)$room['dimensions']['width_cm'];$rl=(int)$room['dimensions']['length_cm'];$x=(int)$placement['x_cm'];$y=(int)$placement['y_cm'];$w=(int)$placement['width_cm'];$d=(int)$placement['depth_cm'];$rot=(int)$placement['rotation'];if(in_array($rot,[90,270],true))[$w,$d]=[$d,$w];
    if($x<0||$y<0||$x+$w>$rw||$y+$d>$rl)throw new RuntimeException('This placement falls outside the room boundary.');
}
function designos_add_product(string $roomGuid,string $slug,array $input=[]): array {
    require_customer('/account/rooms/'.$roomGuid);$cg=(string)customer_identity()['guid'];$room=customer_room_plan($roomGuid,$cg);if(!$room)throw new RuntimeException('Room plan not found.');$p=product_by_slug($slug);if(!$p)throw new RuntimeException('Product not found.');$fp=designos_product_footprint($p);$x=max(0,(int)($input['x_cm']??20));$y=max(0,(int)($input['y_cm']??20));$rot=(int)($input['rotation']??0);if(!in_array($rot,[0,90,180,270],true))$rot=0;
    $placement=['guid'=>uuidv7(),'product_guid'=>$p['guid'],'slug'=>$p['slug'],'name_snapshot'=>$p['name'],'image_snapshot'=>$p['image'],'unit_price_snapshot'=>(int)$p['price'],'x_cm'=>$x,'y_cm'=>$y,'width_cm'=>$fp['width_cm'],'depth_cm'=>$fp['depth_cm'],'rotation'=>$rot,'note'=>(function_exists('mb_substr')?mb_substr(trim((string)($input['note']??'')),0,180):substr(trim((string)($input['note']??'')),0,180)),'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];designos_validate_placement($room,$placement);$updated=null;
    confidential_json_mutate(designos_room_file(),'sanjaynursery:designos:rooms:v1',function(array $rows)use($roomGuid,$cg,$placement,&$updated){foreach($rows as &$r)if(($r['guid']??'')===$roomGuid){if(($r['customer_guid']??'')!==$cg)throw new RuntimeException('Room plan access denied.');$r['placements'][]=$placement;$r['updated_at']=gmdate('c');$updated=$r;break;}return$rows;},'designos.room.add_product');if(!$updated)throw new RuntimeException('Room plan not found.');return$updated;
}
function designos_move_product(string $roomGuid,string $placementGuid,array $input): array {
    require_customer('/account/rooms/'.$roomGuid);$cg=(string)customer_identity()['guid'];$room=customer_room_plan($roomGuid,$cg);if(!$room)throw new RuntimeException('Room plan not found.');$target=null;foreach($room['placements']??[] as $pl)if(($pl['guid']??'')===$placementGuid){$target=$pl;break;}if(!$target)throw new RuntimeException('Placed item not found.');$target['x_cm']=max(0,(int)($input['x_cm']??$target['x_cm']));$target['y_cm']=max(0,(int)($input['y_cm']??$target['y_cm']));$rot=(int)($input['rotation']??$target['rotation']);if(!in_array($rot,[0,90,180,270],true))$rot=0;$target['rotation']=$rot;$target['note']=(function_exists('mb_substr')?mb_substr(trim((string)($input['note']??$target['note']??'')),0,180):substr(trim((string)($input['note']??$target['note']??'')),0,180));$target['updated_at']=gmdate('c');designos_validate_placement($room,$target);$updated=null;
    confidential_json_mutate(designos_room_file(),'sanjaynursery:designos:rooms:v1',function(array $rows)use($roomGuid,$placementGuid,$cg,$target,&$updated){foreach($rows as &$r)if(($r['guid']??'')===$roomGuid){if(($r['customer_guid']??'')!==$cg)throw new RuntimeException('Room plan access denied.');foreach($r['placements'] as &$pl)if(($pl['guid']??'')===$placementGuid){$pl=$target;break;}$r['updated_at']=gmdate('c');$updated=$r;break;}return$rows;},'designos.room.move_product');if(!$updated)throw new RuntimeException('Room plan not found.');return$updated;
}
function designos_remove_product(string $roomGuid,string $placementGuid): array {
    require_customer('/account/rooms/'.$roomGuid);$cg=(string)customer_identity()['guid'];$updated=null;
    confidential_json_mutate(designos_room_file(),'sanjaynursery:designos:rooms:v1',function(array $rows)use($roomGuid,$placementGuid,$cg,&$updated){foreach($rows as &$r)if(($r['guid']??'')===$roomGuid){if(($r['customer_guid']??'')!==$cg)throw new RuntimeException('Room plan access denied.');$before=count($r['placements']??[]);$r['placements']=array_values(array_filter($r['placements']??[],fn($p)=>($p['guid']??'')!==$placementGuid));if(count($r['placements'])===$before)throw new RuntimeException('Placed item not found.');$r['updated_at']=gmdate('c');$updated=$r;break;}return$rows;},'designos.room.remove_product');if(!$updated)throw new RuntimeException('Room plan not found.');return$updated;
}

function designos_overlap(array $a,array $b): bool {
    $aw=(int)$a['width_cm'];$ad=(int)$a['depth_cm'];$bw=(int)$b['width_cm'];$bd=(int)$b['depth_cm'];if(in_array((int)$a['rotation'],[90,270],true))[$aw,$ad]=[$ad,$aw];if(in_array((int)$b['rotation'],[90,270],true))[$bw,$bd]=[$bd,$bw];
    return !(((int)$a['x_cm']+$aw)<=(int)$b['x_cm']||((int)$b['x_cm']+$bw)<=(int)$a['x_cm']||((int)$a['y_cm']+$ad)<=(int)$b['y_cm']||((int)$b['y_cm']+$bd)<=(int)$a['y_cm']);
}
function designos_room_diagnostics(array $room): array {
    $placements=(array)($room['placements']??[]);$collisions=[];for($i=0;$i<count($placements);$i++)for($j=$i+1;$j<count($placements);$j++)if(designos_overlap($placements[$i],$placements[$j]))$collisions[]=[(string)$placements[$i]['guid'],(string)$placements[$j]['guid']];
    $roomArea=max(1,(int)$room['dimensions']['width_cm']*(int)$room['dimensions']['length_cm']);$used=0;foreach($placements as $p)$used+=(int)$p['width_cm']*(int)$p['depth_cm'];$free=max(0,100-(int)round(($used/$roomArea)*100));$spend=array_sum(array_map(fn($p)=>(int)($p['unit_price_snapshot']??0),$placements));$budget=(int)($room['budget']??0);
    return ['collisions'=>$collisions,'free_area_percent'=>$free,'planned_spend'=>$spend,'budget'=>$budget,'budget_remaining'=>$budget>0?$budget-$spend:null,'budget_over'=>$budget>0&&$spend>$budget];
}
function designos_room_recommendations(array $room,int $limit=8): array {
    $style=$room['style_profile_guid']?customer_style_profile((string)$room['style_profile_guid']):null;$result=$style['result']??designos_style_score(['room'=>$room['room_type'],'mood'=>'calm-minimal','material'=>'easy-care','budget'=>$room['budget']>30000?'30k-plus':($room['budget']>15000?'15k-30k':($room['budget']>5000?'5k-15k':'under-5k')),'priorities'=>['easy-care','small-space']]);$result['input']['room']=$room['room_type'];$rank=designos_recommend_products($result,$limit+count($room['placements']??[]));$used=array_column($room['placements']??[],'slug');return array_slice(array_values(array_filter($rank,fn($r)=>!in_array($r['product']['slug'],$used,true))),0,$limit);
}

function designos_release_checks(): array {
    $profiles=designos_style_profiles();$rooms=designos_room_plans();$styleGuids=[];$roomGuids=[];$schema=true;$owners=true;$productRefs=true;$bounds=true;
    $customerGuids=array_column(customer_users(),'guid');$productGuids=array_column(products_all(),'guid');
    foreach($profiles as $p){foreach(['guid','customer_guid','label','result','created_at'] as $k)if(empty($p[$k]))$schema=false;$styleGuids[]=$p['guid']??'';if(!in_array($p['customer_guid']??'',$customerGuids,true))$owners=false;}
    foreach($rooms as $r){foreach(['guid','customer_guid','name','room_type','dimensions','placements','created_at'] as $k)if(!array_key_exists($k,$r))$schema=false;$roomGuids[]=$r['guid']??'';if(!in_array($r['customer_guid']??'',$customerGuids,true))$owners=false;foreach($r['placements']??[] as $pl){if(!in_array($pl['product_guid']??'',$productGuids,true))$productRefs=false;try{designos_validate_placement($r,$pl);}catch(Throwable){$bounds=false;}}}
    $dupe=fn($x)=>count($x)!==count(array_unique($x));$a=designos_style_score(['room'=>'living-room','mood'=>'calm-minimal','material'=>'easy-care','budget'=>'5k-15k','priorities'=>['easy-care']]);$b=designos_style_score(['room'=>'living-room','mood'=>'calm-minimal','material'=>'easy-care','budget'=>'5k-15k','priorities'=>['easy-care']]);
    return [
      ['name'=>'DesignOS encryption','ok'=>confidential_key()!==null&&confidential_crypto_available(),'detail'=>'Style profiles and room plans use authenticated confidential persistence.'],
      ['name'=>'DesignOS schema','ok'=>$schema,'detail'=>$schema?'Saved style/room entities satisfy required schema.':'One or more saved DesignOS entities are malformed.'],
      ['name'=>'DesignOS identity uniqueness','ok'=>!$dupe(array_filter(array_merge($styleGuids,$roomGuids))),'detail'=>'Saved DesignOS GUIDs are unique.'],
      ['name'=>'DesignOS owner integrity','ok'=>$owners,'detail'=>$owners?'Every saved design object belongs to a known customer.':'Orphan DesignOS owner detected.'],
      ['name'=>'Room planner product references','ok'=>$productRefs,'detail'=>$productRefs?'Placed products reference canonical catalogue entities.':'A room plan references a missing product.'],
      ['name'=>'Room planner boundary integrity','ok'=>$bounds,'detail'=>$bounds?'All saved product placements remain inside their room bounds.':'An item is outside its room boundary.'],
      ['name'=>'Style Finder deterministic scoring','ok'=>($a['profile_key']??'')===($b['profile_key']??'')&&($a['score']??0)===($b['score']??-1),'detail'=>'Identical Style Finder inputs return stable profile scoring.'],
      ['name'=>'Style Finder recommendation coverage','ok'=>count(products_public())<3 || count($a['recommendations']??[])>=3,'detail'=>count(products_public())<3?'Recommendation coverage requires at least three catalogue varieties.':'Style result returns ranked plant-variety recommendations.'],
    ];
}
