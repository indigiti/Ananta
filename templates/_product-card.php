<?php
$back=$_SERVER['REQUEST_URI']??'/shop';
$isEnquiry=product_is_enquiry($p);
$care=(array)($p['care_profile']??[]);
$hasImage=product_has_image($p);
$facets=function_exists('discovery_product_facets')?discovery_product_facets($p):['live'=>!$isEnquiry,'media_ready'=>false];
?>
<article class="product-card reveal marketplace-card sn-plant-card" data-product-card data-item-id="<?=e($p['sku'])?>" data-item-name="<?=e($p['name'])?>" data-item-category="<?=e($p['collection']??$p['category_label'])?>" data-product-slug="<?=e($p['slug'])?>" data-product-guid="<?=e($p['guid'])?>">
  <a class="product-image sn-plant-image" href="<?=e(url('/product/'.$p['slug']))?>" data-track="select_item" data-item-id="<?=e($p['sku'])?>" data-item-name="<?=e($p['name'])?>">
    <?php if($hasImage): ?>
      <img src="<?=e(image_url(mediaos_primary_file($p)))?>" alt="<?=e(mediaos_primary_alt($p))?>" loading="lazy" decoding="async" width="700" height="840">
    <?php else: ?>
      <div class="sn-plant-placeholder" aria-label="Image pending for <?=e($p['name'])?>">
        <i class="leaf leaf-a"></i><i class="leaf leaf-b"></i><i class="leaf leaf-c"></i>
        <span><?=e($p['collection']??'Plant')?></span>
        <small>Nursery image pending</small>
      </div>
    <?php endif; ?>
    <span class="product-category"><?=e($p['category_label'])?></span>
    <span class="card-badges"><b><?=e($p['collection']??'Plant')?></b><?php if(!empty($facets['live'])):?><b class="is-live">In stock</b><?php else:?><b>Check availability</b><?php endif;?><?php if(!empty($facets['media_ready'])):?><b>Real photos</b><?php endif;?></span>
  </a>
  <div class="product-card-body">
    <div class="product-title-row"><a href="<?=e(url('/product/'.$p['slug']))?>" data-track="select_item" data-item-id="<?=e($p['sku'])?>" data-item-name="<?=e($p['name'])?>"><h3><?=e($p['name'])?></h3></a><form method="post" action="<?=e(url('/wishlist/toggle'))?>" data-intel-event="<?=is_wishlisted($p['slug'])?'wishlist_remove':'wishlist_add'?>" data-product-slug="<?=e($p['slug'])?>"><input type="hidden" name="slug" value="<?=e($p['slug'])?>"><input type="hidden" name="back" value="<?=e($back)?>"><?=csrf_field()?><button class="heart-button <?=is_wishlisted($p['slug'])?'active':''?>" type="submit" aria-label="<?=is_wishlisted($p['slug'])?'Remove from wishlist':'Save to wishlist'?>"><?=ui_icon('heart')?></button></form></div>
    <?php if(!empty($p['botanical_name'])):?><div class="product-meta-line sn-botanical"><?=e($p['botanical_name'])?></div><?php endif;?>
    <p class="product-description"><?=e($p['short'])?></p>
    <div class="sn-card-care"><?php if(!empty($care['light'])):?><span><?=ui_icon('sun')?> <?=e($care['light'])?></span><?php endif;?><?php if(!empty($care['difficulty'])):?><span><?=ui_icon('sparkles')?> <?=e($care['difficulty'])?></span><?php endif;?></div>
    <div class="product-card-foot"><strong class="sn-card-price"><small><?=!empty($facets['live'])?'Live price':'Nursery availability'?></small><?=e(product_price_label($p))?></strong><a href="<?=e(url('/product/'.$p['slug']))?>" data-track="select_item" data-item-id="<?=e($p['sku'])?>" data-item-name="<?=e($p['name'])?>">View plant →</a></div>
  </div>
</article>
