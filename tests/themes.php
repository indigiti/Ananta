<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$registry=theme_registry();
foreach(['sanjay-botanical-retail','nursery-natural','tropical-canopy','succulent-sand','ceramic-studio','garden-earth','gift-bloom','trade-green'] as $key){
    if(!isset($registry[$key])) throw new RuntimeException('Theme preset missing: '.$key);
    foreach(['bg','surface','ink','accent','accent_2','border','hero_from','hero_to','story_bg','story_ink'] as $token){
        if(!theme_valid_hex((string)($registry[$key]['tokens'][$token]??''))) throw new RuntimeException('Invalid '.$token.' token on '.$key);
    }
}
$resolved=theme_resolve('default');
if(($resolved['key']??'')!=='sanjay-botanical-retail') throw new RuntimeException('Default storefront should use Sanjay Botanical Retail.');
$indoor=theme_resolve('indoor-plants');if(($indoor['key']??'')!=='sanjay-botanical-retail')throw new RuntimeException('Indoor Plants should inherit Sanjay Botanical Retail.');
$bad=theme_sanitize_overrides(['accent'=>'red','bg'=>'#abcdef','ink'=>'#123456','evil'=>'#ffffff']);
if(isset($bad['accent'])||isset($bad['evil'])||($bad['bg']??'')!=='#abcdef'||($bad['ink']??'')!=='#123456') throw new RuntimeException('Theme override sanitization failed.');
$style=theme_css_variables($resolved);if(!str_contains($style,'--collection-accent:')||!str_contains($style,'--collection-story-bg:'))throw new RuntimeException('Theme CSS variable export failed.');
$checks=theme_release_checks();foreach($checks as $c)if(empty($c['ok']))throw new RuntimeException('Theme release check failed: '.$c['name']);
echo "PASS: Sanjay Botanical Retail theme, nursery surface assignments, sanitized overrides and CSS token export verified.\n";
