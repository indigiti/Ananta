<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$root=dirname(__DIR__);$errors=[];
$layout=(string)file_get_contents($root.'/templates/layout.php');
$css=(string)file_get_contents($root.'/public/assets/css/app.css');
$js=(string)file_get_contents($root.'/public/assets/js/app.js');
$helpers=(string)file_get_contents($root.'/src/helpers.php');
if(!str_contains($layout,'class="sn-action sn-icon-action'))$errors[]='Icon-only utility actions are missing.';
foreach(['<b>Visit</b>','<b>Theme</b>','<b>Bag'] as $legacy)if(str_contains($layout,$legacy))$errors[]='Legacy utility text remains: '.$legacy;
foreach(['data-tooltip="Visit"','data-tooltip="Dark mode"','data-theme-color','prefers-color-scheme: dark','class="sr-only">Search</span>'] as $needle)if(!str_contains($layout,$needle))$errors[]='Header/theme bootstrap marker missing: '.$needle;
foreach(['moon','sun'] as $icon)if(!str_contains($helpers,"'{$icon}'=>"))$errors[]='Theme icon missing: '.$icon;
foreach(['syncThemeControls','themeMeta','matchMedia','aria-pressed','localStorage.setItem(\'sanjay-theme\''] as $needle)if(!str_contains($js,$needle))$errors[]='Theme runtime marker missing: '.$needle;
foreach(['Storefront utility icons + complete light/dark visual parity','.sn-action.sn-icon-action','html[data-theme="dark"] .sn-newsletter','html[data-theme="dark"] .sn-discovery-search input','html[data-theme="dark"] .sn-consent'] as $needle)if(!str_contains($css,$needle))$errors[]='Dark/UI CSS marker missing: '.$needle;
if($errors){fwrite(STDERR,"FAIL: ".implode("\nFAIL: ",$errors)."\n");exit(1);}echo "PASS: v1.6.4 icon-only header utilities, persistent/system-aware theme state and dark-mode storefront parity markers verified.\n";
