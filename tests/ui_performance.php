<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$root=dirname(__DIR__);$errors=[];
if(($GLOBALS['app']['version']??'')!=='1.7.0')$errors[]='Release version is not 1.7.0.';
$icon=ui_icon('search');if(!str_contains($icon,'<svg')||!str_contains($icon,'viewBox'))$errors[]='Inline icon helper is unavailable.';
$layout=(string)file_get_contents($root.'/templates/layout.php');
foreach(['⌖','◐','◎','◫','⌕'] as $legacy)if(str_contains($layout,$legacy))$errors[]='Legacy Unicode header icon remains: '.$legacy;
$product=(string)file_get_contents($root.'/templates/product.php');if(substr_count($product,'<h1')!==1)$errors[]='Product template must expose exactly one H1.';
$css=(string)file_get_contents($root.'/public/assets/css/app.css');foreach(['focus-visible','admin-nav-group','.ui-icon'] as $needle)if(!str_contains($css,$needle))$errors[]='UI polish marker missing: '.$needle;
$router=(string)file_get_contents($root.'/router.php');if(!str_contains($router,'readfile($file)')||!str_contains($router,"'css'=>'text/css"))$errors[]='Built-in router does not serve public assets directly.';
$nginx=(string)file_get_contents($root.'/nginx.sample.conf');if(!str_contains($nginx,'max-age=2592000')||!str_contains($nginx,'immutable'))$errors[]='Static asset cache policy missing from Nginx sample.';
$asset=asset('css/app.css');if(!str_contains($asset,'?v=1.7.0'))$errors[]='CSS asset URL is not versioned for cache busting.';
$settings1=discovery_settings();$settings2=discovery_settings();if($settings1!==$settings2)$errors[]='Discovery settings request cache is inconsistent.';
$sample=products_public()[0]??null;if(!$sample)$errors[]='Catalogue sample unavailable.';else{$a=discovery_product_facets($sample);$b=discovery_product_facets($sample);if($a!==$b)$errors[]='Discovery facet cache is inconsistent.';}
if($errors){fwrite(STDERR,"FAIL: ".implode("\nFAIL: ",$errors)."\n");exit(1);}echo "PASS: v1.7.0 icon consistency, focus visibility, single-H1 PDP, static-asset delivery/cache busting and discovery request caching verified.\n";
