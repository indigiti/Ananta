<?php
declare(strict_types=1);

function theme_settings_file(): string { return data_root().'/public-domain/themes/theme-settings.json'; }

function theme_registry(): array {
    return [
        'sanjay-botanical-retail'=>[
            'label'=>'Sanjay Botanical Retail',
            'description'=>'Compact green-and-cream botanical retail system matched to the supplied nursery reference: Georgia display type, Inter/system body, dense category merchandising and rounded retail controls.',
            'mode'=>'botanical-retail',
            'tokens'=>[
                'bg'=>'#ffffff','surface'=>'#ffffff','surface_2'=>'#f6f3eb','ink'=>'#171b18','muted'=>'#69716b',
                'accent'=>'#173f35','accent_2'=>'#d3a348','border'=>'#dfe4e0','hero_from'=>'#173f35','hero_to'=>'#8eae8b',
                'story_bg'=>'#173f35','story_ink'=>'#ffffff','glow'=>'#7ea27f','shadow'=>'#183f35'
            ],
        ],
        'nursery-natural'=>[
            'label'=>'Nursery Natural',
            'description'=>'Fresh ivory, leaf green and soil neutrals for the core Sanjay Nursery storefront.',
            'mode'=>'natural',
            'tokens'=>[
                'bg'=>'#f4f4ea','surface'=>'#fffef8','surface_2'=>'#e7eadb','ink'=>'#1f2b1f','muted'=>'#687366',
                'accent'=>'#315c3a','accent_2'=>'#8b6a43','border'=>'#d3d9c8','hero_from'=>'#dce8d4','hero_to'=>'#f7f3e6',
                'story_bg'=>'#183622','story_ink'=>'#f5f3e7','glow'=>'#aecb8a','shadow'=>'#19311f'
            ],
        ],
        'tropical-canopy'=>[
            'label'=>'Tropical Canopy','description'=>'Deep botanical greens with misty foliage tones for indoor and tropical plant collections.','mode'=>'tropical',
            'tokens'=>['bg'=>'#edf3ea','surface'=>'#fbfef9','surface_2'=>'#d7e6d4','ink'=>'#17311e','muted'=>'#5f7564','accent'=>'#1f6a3c','accent_2'=>'#8aa55f','border'=>'#c7d9c6','hero_from'=>'#cce0c7','hero_to'=>'#f2f7ed','story_bg'=>'#12351e','story_ink'=>'#f3faef','glow'=>'#88c37d','shadow'=>'#15351e'],
        ],
        'succulent-sand'=>[
            'label'=>'Succulent Sand','description'=>'Mineral sand, sage and clay for succulents, cacti and drought-tolerant collections.','mode'=>'succulent',
            'tokens'=>['bg'=>'#f2eee3','surface'=>'#fcfaf5','surface_2'=>'#e3ddcb','ink'=>'#2c3325','muted'=>'#747765','accent'=>'#65784c','accent_2'=>'#b27d54','border'=>'#d6cfbd','hero_from'=>'#ddd9c4','hero_to'=>'#f7efe2','story_bg'=>'#343b2c','story_ink'=>'#fff9eb','glow'=>'#b9c889','shadow'=>'#2b3225'],
        ],
        'ceramic-studio'=>[
            'label'=>'Ceramic Studio','description'=>'Soft stone, terracotta and dark olive for pots, planters and decorative containers.','mode'=>'ceramic',
            'tokens'=>['bg'=>'#f3eee7','surface'=>'#fffaf5','surface_2'=>'#eadcd0','ink'=>'#32251f','muted'=>'#796b62','accent'=>'#8b4f36','accent_2'=>'#4f6044','border'=>'#decfc3','hero_from'=>'#ead8ca','hero_to'=>'#faf3e8','story_bg'=>'#30271f','story_ink'=>'#fff7ed','glow'=>'#d8a37f','shadow'=>'#32241c'],
        ],
        'garden-earth'=>[
            'label'=>'Garden Earth','description'=>'Forest, clay and stone tones for outdoor plants and gardening essentials.','mode'=>'earth',
            'tokens'=>['bg'=>'#eceee3','surface'=>'#fafbf5','surface_2'=>'#d8ddcb','ink'=>'#202a1d','muted'=>'#68705f','accent'=>'#3b5633','accent_2'=>'#9a6b45','border'=>'#cbd1c0','hero_from'=>'#d2dac6','hero_to'=>'#f3efe4','story_bg'=>'#263425','story_ink'=>'#f7f5eb','glow'=>'#a8bd7e','shadow'=>'#202b1e'],
        ],
        'gift-bloom'=>[
            'label'=>'Gift Bloom','description'=>'Warm blush, botanical green and kraft neutrals for corporate and seasonal gifting.','mode'=>'gift',
            'tokens'=>['bg'=>'#f6efea','surface'=>'#fffaf7','surface_2'=>'#eadfd7','ink'=>'#30241f','muted'=>'#776a63','accent'=>'#536b4d','accent_2'=>'#b77d68','border'=>'#decfc6','hero_from'=>'#ebddd5','hero_to'=>'#f9f2e9','story_bg'=>'#31402e','story_ink'=>'#fff7ef','glow'=>'#d9ad91','shadow'=>'#33251f'],
        ],
        'trade-green'=>[
            'label'=>'Trade Green','description'=>'Structured graphite-green palette for wholesale, landscape and corporate project workspaces.','mode'=>'trade',
            'tokens'=>['bg'=>'#eaede8','surface'=>'#f8faf6','surface_2'=>'#d9ded8','ink'=>'#172019','muted'=>'#626c64','accent'=>'#294934','accent_2'=>'#8c765b','border'=>'#c9d0ca','hero_from'=>'#d7ddd7','hero_to'=>'#f1f4ee','story_bg'=>'#1f2c23','story_ink'=>'#f4f6f1','glow'=>'#9eb28f','shadow'=>'#172019'],
        ],
    ];
}

function theme_default_settings(): array {
    return [
        'version'=>1,
        'surfaces'=>[
            'default'=>['preset'=>'sanjay-botanical-retail','overrides'=>[]],
            'indoor-plants'=>['preset'=>'sanjay-botanical-retail','overrides'=>[]],
            'succulents-cacti'=>['preset'=>'sanjay-botanical-retail','overrides'=>[]],
            'pots-planters'=>['preset'=>'sanjay-botanical-retail','overrides'=>[]],
            'outdoor-plants'=>['preset'=>'sanjay-botanical-retail','overrides'=>[]],
            'corporate-gifting'=>['preset'=>'sanjay-botanical-retail','overrides'=>[]],
            'trade'=>['preset'=>'trade-green','overrides'=>[]],
        ],
        'updated_at'=>null,
    ];
}

function theme_settings(): array {
    $data=foundation_json_read(theme_settings_file(), theme_default_settings());
    if(!isset($data['surfaces']) || !is_array($data['surfaces'])) return theme_default_settings();
    return array_replace_recursive(theme_default_settings(), $data);
}

function theme_allowed_surfaces(): array {
    return [
        'default'=>'Default storefront',
        'indoor-plants'=>'Indoor plants',
        'succulents-cacti'=>'Succulents & cacti',
        'pots-planters'=>'Pots & planters',
        'outdoor-plants'=>'Outdoor plants',
        'corporate-gifting'=>'Corporate gifting',
        'trade'=>'Wholesale / landscape workspace',
    ];
}

function theme_valid_hex(string $value): bool { return (bool)preg_match('/^#[0-9a-fA-F]{6}$/',$value); }
function theme_sanitize_overrides(array $input): array {
    $allowed=['bg','surface','surface_2','ink','muted','accent','accent_2','border','hero_from','hero_to','story_bg','story_ink','glow','shadow'];$out=[];
    foreach($allowed as $key){$value=trim((string)($input[$key]??''));if($value!==''&&theme_valid_hex($value))$out[$key]=strtolower($value);}return$out;
}
function theme_save_surface(string $surface,string $preset,array $overrides=[]): void {
    if(!array_key_exists($surface,theme_allowed_surfaces()))throw new RuntimeException('Unknown theme surface.');
    if(!isset(theme_registry()[$preset]))throw new RuntimeException('Unknown theme preset.');
    $settings=theme_settings();$settings['surfaces'][$surface]=['preset'=>$preset,'overrides'=>theme_sanitize_overrides($overrides)];$settings['updated_at']=gmdate('c');
    atomic_json_write(theme_settings_file(),$settings,foundation_checksum(theme_settings_file()),'theme.update');audit_event('theme.update',['surface'=>$surface,'preset'=>$preset]);
}
function theme_resolve(string $surface='default'): array {
    $settings=theme_settings();$registry=theme_registry();$surfaceConfig=$settings['surfaces'][$surface]??$settings['surfaces']['default']??['preset'=>'sanjay-botanical-retail','overrides'=>[]];
    $preset=(string)($surfaceConfig['preset']??'sanjay-botanical-retail');if(!isset($registry[$preset]))$preset='sanjay-botanical-retail';$theme=$registry[$preset];$theme['key']=$preset;$theme['surface']=$surface;$theme['tokens']=array_merge($theme['tokens'],theme_sanitize_overrides((array)($surfaceConfig['overrides']??[])));return$theme;
}
function theme_css_variables(array $theme): string {
    $map=['bg'=>'--collection-bg','surface'=>'--collection-surface','surface_2'=>'--collection-surface-2','ink'=>'--collection-ink','muted'=>'--collection-muted','accent'=>'--collection-accent','accent_2'=>'--collection-accent-2','border'=>'--collection-border','hero_from'=>'--collection-hero-from','hero_to'=>'--collection-hero-to','story_bg'=>'--collection-story-bg','story_ink'=>'--collection-story-ink','glow'=>'--collection-glow','shadow'=>'--collection-shadow'];$pairs=[];
    foreach($map as $key=>$css){$v=(string)($theme['tokens'][$key]??'');if(theme_valid_hex($v))$pairs[]=$css.':'.$v;}return implode(';',$pairs);
}
function theme_admin_rows(): array {$settings=theme_settings();$rows=[];foreach(theme_allowed_surfaces() as $surface=>$label)$rows[]=['surface'=>$surface,'label'=>$label,'config'=>$settings['surfaces'][$surface]??[],'resolved'=>theme_resolve($surface)];return$rows;}
function theme_surface_for_product(array $product): string {$category=(string)($product['category']??'');return array_key_exists($category,theme_allowed_surfaces())?$category:'default';}
function theme_release_checks(): array {
    $settings=theme_settings();$registry=theme_registry();$ok=true;$bad=[];foreach(theme_allowed_surfaces() as $surface=>$label){$cfg=$settings['surfaces'][$surface]??null;if(!$cfg||!isset($registry[(string)($cfg['preset']??'')])){$ok=false;$bad[]=$surface;continue;}foreach((array)($cfg['overrides']??[]) as $key=>$value)if(!theme_valid_hex((string)$value)){$ok=false;$bad[]=$surface.':'.$key;}}
    return [['name'=>'Theme registry integrity','ok'=>$ok,'detail'=>$ok?'All nursery surfaces use allowlisted presets and sanitized color tokens.':'Invalid theme configuration: '.implode(', ',$bad)],['name'=>'Nursery default theme assignment','ok'=>(theme_resolve('default')['key']??'')==='sanjay-botanical-retail','detail'=>'Default storefront resolves to '.(theme_resolve('default')['label']??'a valid theme').'.']];
}
