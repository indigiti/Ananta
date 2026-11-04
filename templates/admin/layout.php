<?php
$app=$GLOBALS['app']; $success=flash('success'); $error=flash('error'); $identity=admin_identity();
$pageTitle=($title??'Admin').' — Sanjay Nursery Admin';
$adminPath=parse_url($_SERVER['REQUEST_URI']??'/admin',PHP_URL_PATH)?:'/admin';
$navLink=function(string $path,string $label,string $icon) use($adminPath): string {
    $active=$path==='/admin' ? $adminPath==='/admin' : str_starts_with($adminPath,$path);
    return '<a class="admin-nav-link'.($active?' active':'').'" href="'.e(url($path)).'"'.($active?' aria-current="page"':'').'>'.ui_icon($icon).'<span>'.e($label).'</span></a>';
};
?>
<!doctype html><html lang="en" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=e($pageTitle)?></title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head>
<body class="admin-body">
<header class="admin-top"><a class="admin-brand" href="<?=e(url('/admin'))?>"><span>Sanjay Nursery</span><small>Admin OS · <?=e($app['version'])?></small></a><?php if($identity): ?><div class="admin-user"><span><?=e($identity['name'])?></span><a class="admin-store-link" href="<?=e(url('/'))?>" target="_blank" rel="noopener"><?=ui_icon('external')?> <span>View store</span></a><form action="<?=e(url('/admin/logout'))?>" method="post"><?=csrf_field()?><button class="btn outline admin-signout" type="submit"><?=ui_icon('logout')?> <span>Sign out</span></button></form></div><?php endif; ?></header>
<?php if($identity): ?><div class="admin-shell"><aside class="admin-side" aria-label="Admin navigation">
  <div class="admin-nav-group"><p>Storefront & commerce</p><?=$navLink('/admin','Dashboard','dashboard')?><?=$navLink('/admin/orders','Orders','orders')?><?=$navLink('/admin/products','Products','plant')?><?=$navLink('/admin/variants','Live Plant Variants','variants')?><?=$navLink('/admin/media','Media Library','image')?><?=$navLink('/admin/mediaos','Plant Photography','image')?></div>
  <div class="admin-nav-group"><p>Growth & discovery</p><?=$navLink('/admin/discovery','Discovery Center','discovery')?><?=$navLink('/admin/ai/overview','AI & Search','sparkles')?><?=$navLink('/admin/intelligence/overview','Shopping Intelligence','brain')?><?=$navLink('/admin/growth','Growth Center','growth')?><?=$navLink('/admin/cms','CMS & SEO','content')?><?=$navLink('/admin/taxonomies','Taxonomies','tag')?></div>
  <div class="admin-nav-group"><p>Operations</p><?=$navLink('/admin/system','System Health','pulse')?><?=$navLink('/admin/errors','Error Monitor','alert')?><?=$navLink('/admin/procurement','Procurement & Inventory','orders')?><?=$navLink('/admin/quality','Quality & Evidence','shield')?><?=$navLink('/admin/fulfilment','Fulfilment','orders')?><?=$navLink('/admin/requests','Refunds & Cancellations','orders')?><?=$navLink('/admin/themes','Theme Studio','theme')?><?=$navLink('/admin/release','Release Checks','shield')?></div>
  <div class="admin-nav-group"><p>Projects & B2B</p><?=$navLink('/admin/quotes','Project Enquiries','content')?><?=$navLink('/admin/trade','Trade & B2B','growth')?></div>
  <details class="admin-nav-advanced"><summary><?=ui_icon('settings')?> <span>Advanced workflows</span></summary><div><?=$navLink('/admin/makeos','Production / MakeOS','settings')?><?=$navLink('/admin/passports','Product Passports','shield')?><?=$navLink('/admin/configurator','Configurator','settings')?><?=$navLink('/admin/product/new','Add Product','plant')?></div></details>
</aside><main class="admin-main"><?php else:?><main class="admin-auth-main"><?php endif; ?>
<?php if($success||$error):?><div class="flash <?=$error?'error':'success'?>" role="status"><?=e($error?:$success)?></div><?php endif;?>
<?=$content?></main><?php if($identity):?></div><?php endif;?><script src="<?=e(asset('js/app.js'))?>" defer></script></body></html>
