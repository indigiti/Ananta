<?php
$app = $GLOBALS['app'];
$pageTitle = ($title ?? 'Plants and Nurseries') . ' · Ananta Nursery Mall';
$pageDescription = $description ?? $app['tagline'];
$pageRobots = $robots ?? 'index,follow';
$pageCanonical = $canonical ?? absolute_url(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$pageOgType = $og_type ?? 'website';
$themeSurface = $themeSurface ?? 'default';
$pageTheme = function_exists('theme_resolve') ? theme_resolve($themeSurface) : ['key'=>'sanjay-botanical-retail','surface'=>$themeSurface,'tokens'=>['bg'=>'#ffffff']];
$themeStyle = function_exists('theme_css_variables') ? theme_css_variables($pageTheme) : '';
$success = flash('success');
$error = flash('error');
$catalogCount = count(products_public());
$catalogReady = $catalogCount > 0;
$growthSettings = function_exists('growth_settings') ? growth_settings() : [];
$growthTracking = function_exists('growth_tracking_public') ? growth_tracking_public() : ['enabled'=>false,'consent'=>false,'ga4'=>'','gtm'=>''];
$pageImage = $og_image ?? ($growthSettings['default_social_image'] ?? '');
if ($pageImage==='' && isset($product) && is_array($product) && product_has_image($product)) $pageImage=absolute_url(image_url(mediaos_primary_file($product)));
if (!empty($schema)) $pageSchema=$schema;
elseif (function_exists('growth_schema_graph')) $pageSchema=growth_schema_graph([['@type'=>'WebPage','@id'=>$pageCanonical.'#webpage','url'=>$pageCanonical,'name'=>$pageTitle,'description'=>$pageDescription,'isPartOf'=>['@id'=>absolute_url('/').'#website']]]);
else $pageSchema=[];
$siConfig=function_exists('si_public_config')?si_public_config():['enabled'=>false];
$currentPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="<?= e($pageTheme['tokens']['accent'] ?? '#173f35') ?>" data-theme-color>
<script nonce="<?= e($GLOBALS['csp_nonce'] ?? '') ?>">(function(){try{var s=localStorage.getItem('sanjay-theme');var t=(s==='dark'||s==='light')?s:(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.dataset.theme=t;document.documentElement.style.colorScheme=t;}catch(e){}})();</script>
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<meta name="robots" content="<?= e($pageRobots) ?>">
<link rel="canonical" href="<?= e($pageCanonical) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:type" content="<?= e($pageOgType) ?>">
<meta property="og:url" content="<?= e($pageCanonical) ?>">
<?php if($pageImage!==''): ?><meta property="og:image" content="<?=e($pageImage)?>"><meta name="twitter:card" content="summary_large_image"><?php else:?><meta name="twitter:card" content="summary"><?php endif;?>
<meta name="twitter:title" content="<?=e($pageTitle)?>"><meta name="twitter:description" content="<?=e($pageDescription)?>">
<?php if(!empty($growthSettings['google_site_verification'])):?><meta name="google-site-verification" content="<?=e($growthSettings['google_site_verification'])?>"><?php endif;?>
<?php if(!empty($growthSettings['bing_site_verification'])):?><meta name="msvalidate.01" content="<?=e($growthSettings['bing_site_verification'])?>"><?php endif;?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/ananta-premium.css')) ?>">
<?php if (!empty($pageSchema)): ?><script type="application/ld+json" nonce="<?= e($GLOBALS['csp_nonce'] ?? '') ?>"><?= json_encode($pageSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?></script><?php endif; ?>
</head>
<body class="ananta-storefront page-theme page-theme-<?= e($pageTheme['key'] ?? 'sanjay-botanical-retail') ?> surface-<?= e(str_replace('_','-',$pageTheme['surface'] ?? 'default')) ?>" style="<?= e($themeStyle) ?>" data-page-theme="<?= e($pageTheme['key'] ?? 'sanjay-botanical-retail') ?>" data-analytics-enabled="<?=!empty($growthTracking['enabled'])?'1':'0'?>" data-analytics-consent="<?=!empty($growthTracking['consent'])?'1':'0'?>" data-ga4="<?=e($growthTracking['ga4']??'')?>" data-gtm="<?=e($growthTracking['gtm']??'')?>" data-si-enabled="<?=!empty($siConfig['enabled'])?'1':'0'?>" data-si-personalisation="<?=!empty($siConfig['personalisation'])?'1':'0'?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="sn-announcement"><div class="sn-marquee"><span>Ananta Nursery Mall</span><span>Curated plants and nurseries</span><span>Wholesale &amp; project enquiries</span><span><?=e((string)count(products_public()))?> curated plant varieties</span></div></div>
<header class="site-header marketplace-header sn-header" data-header>
  <div class="sn-utility">
    <div class="shell nav-row commerce-nav-row sn-main-header">
      <button class="icon-button compact-only sn-menu-button" type="button" data-menu-toggle aria-label="Open menu" aria-expanded="false"><?=ui_icon('menu')?></button>
      <a class="brand nursery-brand sn-brand" href="<?= e(url('/')) ?>" aria-label="Ananta Nursery Mall home"><i class="sn-brand-mark" aria-hidden="true"></i><span class="brand-main">Ananta</span><span class="brand-sub">Nursery Mall</span></a>
      <form class="header-search header-search-primary sn-search" action="<?= e(url('/shop')) ?>" method="get" role="search"><span class="sn-search-icon" aria-hidden="true"><?=ui_icon('search')?></span><input type="search" name="q" placeholder="Search plants or nurseries" aria-label="Search catalogue" <?= $catalogReady?'':'disabled' ?>><button type="submit" aria-label="Search" title="Search" <?= $catalogReady?'':'disabled' ?>><?=ui_icon('search')?> <span class="sr-only">Search</span></button></form>
      <div class="nav-actions sn-header-actions" aria-label="Store utilities">
        <a class="sn-action sn-icon-action <?=str_starts_with($currentPath,'/contact')?'active':''?>" href="<?= e(url('/contact')) ?>" aria-label="Visit and contact Sanjay Nursery" data-tooltip="Visit"><?=ui_icon('pin','sn-action-icon ui-icon')?></a>
        <button class="sn-action sn-icon-action sn-theme-action" type="button" data-theme-toggle aria-label="Switch to dark mode" aria-pressed="false" data-tooltip="Dark mode"><span class="sn-theme-glyph" aria-hidden="true"><?=ui_icon('moon','sn-action-icon ui-icon theme-icon-moon')?><?=ui_icon('sun','sn-action-icon ui-icon theme-icon-sun')?></span></button>
        <a class="sn-action sn-icon-action account-link <?=str_starts_with($currentPath,'/account')?'active':''?>" href="<?= e(url(customer_logged_in()?'/account':'/account/login')) ?>" aria-label="<?= customer_logged_in() ? 'My account' : 'Sign in' ?>" data-tooltip="<?= customer_logged_in() ? 'Account' : 'Sign in' ?>"><?=ui_icon('user','sn-action-icon ui-icon')?></a>
        <?php if($catalogReady): ?><a class="sn-action sn-icon-action bag <?=$currentPath==='/cart'?'active':''?>" href="<?= e(url('/cart')) ?>" aria-label="Shopping bag<?=cart_count()>0?' with '.cart_count().' items':''?>" data-tooltip="Bag"><?=ui_icon('bag','sn-action-icon ui-icon')?><?php if(cart_count()>0):?><span class="sn-bag-count" aria-hidden="true"><?=cart_count()?></span><?php endif;?></a><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="tablet-search-row sn-tablet-search"><div class="shell"><form class="header-search tablet-search" action="<?= e(url('/shop')) ?>" method="get" role="search"><input type="search" name="q" placeholder="Search plants or nurseries" aria-label="Search catalogue" <?= $catalogReady?'':'disabled' ?>><button type="submit" <?= $catalogReady?'':'disabled' ?>>Search</button></form></div></div>
  <div class="category-nav-wrap sn-nav-wrap">
    <div class="shell category-nav-shell">
      <nav class="category-nav nursery-category-nav sn-nav" aria-label="Nursery navigation">
        <a href="<?= e(url('/collections/indoor-plants')) ?>">Indoor Plants</a>
        <a href="<?= e(url('/collections/outdoor-plants')) ?>">Outdoor Plants</a>
        <a href="<?= e(url('/collections/succulents-cacti')) ?>">Succulents & Cacti</a>
        <a href="<?= e(url('/collections/pots-planters')) ?>">Pots & Planters</a>
        <a href="<?= e(url('/collections/gardening-essentials')) ?>">Gardening Essentials</a>
        <a href="<?= e(url('/plant-finder')) ?>">Plant Finder</a>
        <a href="<?= e(url('/nurseries')) ?>">Nurseries</a>
        <a href="<?= e(url('/supplier/login')) ?>">For Nurseries</a>
        <a href="<?= e(url('/contact#bulk')) ?>">Corporate Gifting</a>
        <a href="<?= e(url('/materials')) ?>">Plant Care</a>
        <a href="<?= e(url('/about')) ?>">Our Story</a>
      </nav>
    </div>
  </div>
  <div class="mobile-menu sn-mobile-menu" data-menu hidden>
    <div class="mobile-menu-head"><strong>Explore Ananta</strong><button type="button" class="menu-theme" data-theme-toggle aria-label="Switch to dark mode" aria-pressed="false"><?=ui_icon('theme')?> <span data-theme-label>Dark mode</span></button></div>
    <a href="<?= e(url('/shop')) ?>">Catalogue</a><a href="<?= e(url('/nurseries')) ?>">Nurseries</a><a href="<?= e(url('/plant-finder')) ?>">Plant Finder</a><a href="<?= e(url('/collections/indoor-plants')) ?>">Indoor Plants</a><a href="<?= e(url('/collections/outdoor-plants')) ?>">Outdoor Plants</a><a href="<?= e(url('/collections/succulents-cacti')) ?>">Succulents & Cacti</a><a href="<?= e(url('/collections/pots-planters')) ?>">Pots & Planters</a><a href="<?= e(url('/collections/gardening-essentials')) ?>">Gardening Essentials</a><a href="<?= e(url('/materials')) ?>">Plant Care</a><a href="<?= e(url('/contact#bulk')) ?>">Corporate / Bulk</a><a href="<?= e(url('/contact')) ?>">Visit & Contact</a><a href="<?= e(url(customer_logged_in()?'/account':'/account/login')) ?>"><?= customer_logged_in() ? 'My account' : 'Sign in / Register' ?></a>
  </div>
</header>
<?php if ($success || $error): ?><div class="shell flash-wrap"><div class="flash <?= $error ? 'error':'success' ?>"><?= e($error ?: $success) ?></div></div><?php endif; ?>
<main id="main"><?= $content ?></main>
<section class="sn-newsletter"><div class="shell sn-newsletter-inner"><div><h3>Grow with Sanjay Nursery.</h3><p>Plant-care notes, nursery updates and new-arrival alerts.</p></div><form class="sn-email" action="<?=e(url('/contact'))?>" data-track-submit="newsletter_intent"><input type="email" placeholder="Enter email address" aria-label="Email address"><button type="submit">Stay connected</button></form></div></section>
<footer class="site-footer nursery-footer sn-footer">
  <div class="shell footer-grid sn-footer-grid">
    <div class="sn-footer-brand"><a class="brand sn-brand footer-brand-lockup" href="<?=e(url('/'))?>"><i class="sn-brand-mark" aria-hidden="true"></i><span class="brand-main">Ananta</span></a><p>Discover plants, explore verified nursery offers and source greener spaces with confidence. Sanjay Nursery is our founding nursery.</p></div>
    <div><h3>Shop</h3><a href="<?= e(url('/collections/indoor-plants')) ?>">Indoor plants</a><a href="<?= e(url('/collections/outdoor-plants')) ?>">Outdoor plants</a><a href="<?= e(url('/collections/succulents-cacti')) ?>">Succulents & cacti</a><a href="<?= e(url('/collections/pots-planters')) ?>">Pots & planters</a></div>
    <div><h3>Services</h3><a href="<?= e(url('/nurseries')) ?>">Find nurseries</a><a href="<?= e(url('/contact#bulk')) ?>">Corporate gifting</a><a href="<?= e(url('/contact#bulk')) ?>">Bulk plants</a><a href="<?= e(url('/contact#bulk')) ?>">Garden projects</a><a href="<?= e(url('/contact')) ?>">Visit nursery</a></div>
    <div><h3>Learn</h3><a href="<?= e(url('/materials')) ?>">Plant care</a><a href="<?= e(url('/journal')) ?>">Nursery journal</a><a href="<?= e(url('/about')) ?>">Our story</a><a href="<?= e(url('/contact')) ?>">Contact us</a><a href="<?= e(url('/privacy/preferences')) ?>">Privacy preferences</a></div>
    <div><h3>Founding nursery · Sanjay Nursery</h3><p>Kadam Wak Wasti, Pune–Solapur Road, Loni Kalbhor, Pune.</p><a href="mailto:sanjaynursery@gmail.com">sanjaynursery@gmail.com</a><a href="tel:+919225337601">+91 92253 37601</a></div>
  </div>
  <div class="shell footer-bottom"><span>© 2026 Ananta · Founding nursery: Sanjay Nursery</span><span>Staging build <?= e($app['version']) ?> · <?= $catalogCount ?> products</span></div>
</footer>
<?php if(!empty($siConfig['enabled'])&&($siConfig['consent_mode']??'opt_in')!=='essential_only'):?><aside class="sn-consent si-consent" data-analytics-consent-banner data-si-consent-banner hidden aria-label="Privacy preferences"><div class="si-consent-copy"><strong>Your nursery browsing, your choice.</strong><p>Essential storefront functions always work. Optional analytics helps us measure discovery; personalisation remembers plant interests and improves recommendations. Marketing remains separate.</p><details><summary>Choose preferences</summary><div class="si-consent-options"><label><input type="checkbox" checked disabled> Essential</label><label><input type="checkbox" data-consent-analytics> Analytics</label><label><input type="checkbox" data-consent-personalisation> Personalisation</label><label><input type="checkbox" data-consent-marketing> Marketing</label></div></details></div><div class="sn-consent-actions"><button class="btn outline" type="button" data-consent-decline data-consent-essential>Essential only</button><button class="btn outline" type="button" data-consent-save>Save choices</button><button class="btn primary" type="button" data-consent-accept>Allow recommended</button></div></aside><?php endif;?>
<script type="application/json" id="sanjay-intelligence-config"><?=json_encode($siConfig,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP)?></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
