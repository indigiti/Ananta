<?php
declare(strict_types=1);

function commerceux_room_map(): array {
    return [
        'indoors'=>['label'=>'Indoor Spaces','categories'=>['indoor-plants'],'tags'=>['indoor','low-light','foliage']],
        'balcony'=>['label'=>'Balcony & Terrace','categories'=>['outdoor-plants','succulents-cacti','pots-planters'],'tags'=>['balcony','terrace','sun']],
        'garden'=>['label'=>'Garden & Landscape','categories'=>['outdoor-plants','gardening-essentials'],'tags'=>['garden','landscape','outdoor']],
        'gifting'=>['label'=>'Gifting','categories'=>['corporate-gifting','indoor-plants','succulents-cacti'],'tags'=>['gift','corporate']],
    ];
}
function commerceux_filter_options(array $items): array {
    $materials=[];$finishes=[];
    foreach($items as $p){foreach((array)($p['materials']??[]) as $v)$materials[$v]=true;foreach((array)($p['finishes']??[]) as $v)$finishes[$v]=true;}
    $materials=array_keys($materials);$finishes=array_keys($finishes);sort($materials,SORT_NATURAL|SORT_FLAG_CASE);sort($finishes,SORT_NATURAL|SORT_FLAG_CASE);
    return ['materials'=>$materials,'finishes'=>$finishes,'rooms'=>commerceux_room_map()];
}
function commerceux_matches_search(array $p,string $q): bool {
    $q=text_lower(trim($q)); if($q==='')return true;
    $hay=text_lower(implode(' ',[(string)($p['name']??''),(string)($p['short']??''),(string)($p['description']??''),(string)($p['category_label']??''),(string)($p['collection']??''),implode(' ',(array)($p['tags']??[])),implode(' ',(array)($p['materials']??[])),implode(' ',(array)($p['finishes']??[]))]));
    foreach(preg_split('/\s+/',$q)?:[] as $term)if($term!==''&&!str_contains($hay,$term))return false; return true;
}
function commerceux_filter_catalog(array $items,array $input): array {
    $q=trim((string)($input['q']??''));$category=trim((string)($input['category']??''));$collection=trim((string)($input['collection']??''));$room=trim((string)($input['room']??''));$material=trim((string)($input['material']??''));$finish=trim((string)($input['finish']??''));$priceMax=max(0,(int)($input['price_max']??0));$custom=!empty($input['customisable']);
    $roomMap=commerceux_room_map();
    $items=array_values(array_filter($items,function($p)use($q,$category,$collection,$room,$material,$finish,$priceMax,$custom,$roomMap){
        if($category!==''&&($p['category']??'')!==$category)return false;
        if($collection!==''&&($p['collection']??'')!==$collection)return false;
        if($q!==''&&!commerceux_matches_search($p,$q))return false;
        if($material!==''&&!in_array($material,(array)($p['materials']??[]),true))return false;
        if($finish!==''&&!in_array($finish,(array)($p['finishes']??[]),true))return false;
        if($priceMax>0&&(int)($p['price']??0)>$priceMax)return false;
        if($custom&&empty($p['customisation']['enabled']))return false;
        if($room!==''&&isset($roomMap[$room])){$r=$roomMap[$room];$cat=in_array((string)($p['category']??''),$r['categories'],true);$tag=count(array_intersect((array)($p['tags']??[]),$r['tags']))>0;if(!empty($r['strict_categories'])){if(!$cat)return false;}elseif(!$cat&&!$tag)return false;}
        return true;
    }));
    $sort=(string)($input['sort']??'recommended');
    if($sort==='price_asc')usort($items,fn($a,$b)=>(int)$a['price']<=>(int)$b['price']);
    elseif($sort==='price_desc')usort($items,fn($a,$b)=>(int)$b['price']<=>(int)$a['price']);
    elseif($sort==='name')usort($items,fn($a,$b)=>strcasecmp((string)$a['name'],(string)$b['name']));
    else usort($items,fn($a,$b)=>(int)!empty($b['featured'])<=>(int)!empty($a['featured']) ?: strcasecmp((string)$a['name'],(string)$b['name']));
    return $items;
}
function commerceux_release_checks(): array {
    $sample=products_public();$filtered=commerceux_filter_catalog($sample,['customisable'=>'1']);$customOk=true;foreach($filtered as $p)if(empty($p['customisation']['enabled']))$customOk=false;
    $asc=commerceux_filter_catalog($sample,['sort'=>'price_asc']);$sortOk=true;for($i=1;$i<count($asc);$i++)if((int)$asc[$i-1]['price']>(int)$asc[$i]['price'])$sortOk=false;
    return [
        ['name'=>'Commerce UX filter integrity','ok'=>$customOk,'detail'=>$customOk?'Customisable filter returns valid products.':'Filter mismatch detected.'],
        ['name'=>'Commerce UX sort integrity','ok'=>$sortOk,'detail'=>$sortOk?'Price sorting is deterministic.':'Price sorting failed.'],
    ];
}
