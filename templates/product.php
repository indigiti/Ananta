<?php
$care=(array)($product['care_profile']??[]);
$variants=variants_for_product($product,true);
$sellableVariants=sellable_variants_for_product($product);
$isEnquiry=product_is_enquiry($product);
$hasImage=product_has_image($product);
$gallery=function_exists('mediaos_gallery')?mediaos_gallery($product,true):[];
$facets=function_exists('discovery_product_facets')?discovery_product_facets($product):[];
?>
<section class="product-mobile-summary shell reveal sn-pdp-mobile-summary">
  <p class="kicker"><?=e($product['category_label'])?> · <?=e($product['collection']??'Plant')?></p>
  <h1><?=e($product['name'])?></h1>
  <?php if(!empty($product['botanical_name'])):?><p class="sn-pdp-botanical"><em><?=e($product['botanical_name'])?></em></p><?php endif;?>
  <div class="mobile-price-row"><strong><?=e(product_price_label($product))?></strong><span><?=e($product['lead_time'])?></span></div>
</section>

<section class="product-page shell marketplace-pdp sn-plant-pdp" data-view-item data-item-id="<?=e($product['sku'])?>" data-item-name="<?=e($product['name'])?>" data-item-category="<?=e($product['collection']??$product['category_label'])?>">
  <div class="product-gallery reveal" id="gallery">
    <?php if($gallery): foreach($gallery as $i=>$img): ?>
      <figure class="gallery-item <?=$i===0?'main':''?>"><img src="<?=e(image_url($img['file']))?>" alt="<?=e($img['alt']??$product['name'])?>" <?=$i===0?'fetchpriority="high"':'loading="lazy"'?>></figure>
    <?php endforeach; elseif($hasImage): ?>
      <figure class="gallery-item main"><img src="<?=e(image_url(mediaos_primary_file($product)))?>" alt="<?=e(mediaos_primary_alt($product))?>" fetchpriority="high"></figure>
    <?php else: ?>
      <div class="sn-pdp-placeholder" aria-label="Nursery image pending for <?=e($product['name'])?>"><div class="sn-pdp-leaves"><i></i><i></i><i></i><i></i></div><p><?=e($product['collection']??'Sanjay Nursery')?></p><strong><?=e($product['name'])?></strong><small>Live plant photography will be attached to the verified nursery batch.</small></div>
    <?php endif; ?>
  </div>

  <div class="product-info reveal sn-plant-info">
    <div class="desktop-product-summary">
      <p class="kicker"><?=e($product['category_label'])?> · <?=e($product['collection']??'Plant')?> · <?=e($product['sku'])?></p>
      <h2 class="product-desktop-title"><?=e($product['name'])?></h2>
      <?php if(!empty($product['botanical_name'])):?><p class="sn-pdp-botanical"><em><?=e($product['botanical_name'])?></em></p><?php endif;?>
      <p class="product-short"><?=e($product['short'])?></p>
      <div class="product-price sn-live-price"><small><?=!empty($sellableVariants)?'LIVE NURSERY PRICE':'VARIETY LISTING'?></small><?=e(product_price_label($product))?></div>
    </div>

    <div class="purchase-signals sn-plant-signals"><span>Live plant</span><span><?=e($product['collection']??'Nursery collection')?></span><span><?=e($care['difficulty']??'Care guide included')?></span></div>

    <div class="sn-care-grid sn-pdp-care">
      <?php foreach([['Light','light','sun'],['Water','water','water'],['Humidity','humidity','humidity'],['Difficulty','difficulty','sparkles'],['Best placement','placement','home']] as [$label,$key,$icon]): if(empty($care[$key]))continue; ?>
      <div><b><?=ui_icon($icon)?> <?=e($label)?></b><span><?=e($care[$key])?></span></div>
      <?php endforeach; ?>
    </div>

    <?php if($variants): ?>
      <div class="sn-variant-engine" id="configure"><div class="sn-variant-head"><div><p class="kicker">Live pot sizes & batches</p><h2>Select the exact nursery SKU.</h2></div><span><?=count($variants)?> variant<?=count($variants)===1?'':'s'?></span></div>
      <div class="sn-variant-list"><?php foreach($variants as $v): $canBuy=variant_is_sellable($v); ?><label class="sn-variant-card <?=$canBuy?'is-sellable':'is-unavailable'?>" data-variant-card="<?=e($v['guid'])?>"><input type="radio" name="variant_preview" value="<?=e($v['guid'])?>" <?=$canBuy?'':'disabled'?> data-variant-price="<?=e((string)($v['price']??0))?>"><span><strong><?=e(variant_display_label($v))?></strong><small><?=e($v['sku'])?><?php if(!empty($v['batch_code'])):?> · Batch <?=e($v['batch_code'])?><?php endif;?></small><?php if(($v['plant_height_cm_min']??0)>0||($v['plant_height_cm_max']??0)>0):?><small>Approx. <?=e((string)($v['plant_height_cm_min']??0))?>–<?=e((string)($v['plant_height_cm_max']??0))?> cm plant height</small><?php endif;?><small><?=e(($v['shipping_class']??'plant-standard')==='plant-large'?'Large-plant delivery':'Standard plant delivery')?><?=!empty($v['local_pickup'])?' · Nursery pickup available':''?></small></span><b><?=$canBuy?money($v['price']):e(variant_availability_label($v))?></b></label><?php endforeach;?></div>
      <?php if($sellableVariants): ?><form class="config-form sn-variant-cart-form" method="post" action="<?=e(url('/cart/add'))?>" data-intel-event="cart_add" data-product-slug="<?=e($product['slug'])?>"><?=csrf_field()?><input type="hidden" name="slug" value="<?=e($product['slug'])?>"><label>Pot size / live batch<select name="variant_guid" required data-variant-select><option value="">Choose a variant</option><?php foreach($sellableVariants as $v):?><option value="<?=e($v['guid'])?>"><?=e(variant_display_label($v))?> · <?=money($v['price'])?> · <?=e(variant_availability_label($v))?></option><?php endforeach;?></select></label><label>Quantity<select name="qty"><?php for($i=1;$i<=5;$i++):?><option value="<?=$i?>"><?=$i?></option><?php endfor;?></select></label><button class="btn primary wide" type="submit">Add selected live plant to bag</button><small>Checkout uses the selected variant SKU, current price and current inventory—not the parent variety record.</small></form><div class="sn-delivery-check" data-pincode-widget data-shipping-url="<?=e(url('/api/shipping'))?>"><label>Check delivery pincode<input inputmode="numeric" maxlength="6" placeholder="6-digit pincode"></label><button class="btn outline" type="button" data-pincode-check>Check</button><p data-pincode-result></p></div><?php else:?><div class="sn-availability-box"><p>No variant is checkout-ready right now. You can still ask the nursery for the latest batch.</p><a class="btn primary" href="<?=e(url('/contact?product='.rawurlencode($product['slug'])))?>" data-track="generate_lead" data-lead-type="variant_availability">Check latest availability</a></div><?php endif;?></div>
    <?php elseif($isEnquiry): ?>
      <div class="sn-availability-box" id="availability"><p class="kicker">Live nursery availability</p><h2>Choose the actual plant, size and pot.</h2><p><?=e($product['nursery_note']??'Live plants and pot sizes vary by batch.')?></p><div class="inline-actions"><a class="btn primary" href="<?=e(url('/contact?product='.rawurlencode($product['slug'])))?>" data-track="generate_lead" data-lead-type="product_availability" data-item-id="<?=e($product['sku'])?>" data-item-name="<?=e($product['name'])?>">Check price & availability</a><a class="btn outline" href="tel:+919225337601" data-track="generate_lead" data-lead-type="call_nursery" data-item-id="<?=e($product['sku'])?>" data-item-name="<?=e($product['name'])?>">Call nursery</a></div><small>No live pot-size SKU has been published for this variety yet.</small></div>
    <?php else: ?>
      <form class="config-form" id="configure" method="post" action="<?=e(url('/cart/add'))?>"><?=csrf_field()?><input type="hidden" name="slug" value="<?=e($product['slug'])?>"><label>Quantity<select name="qty"><?php for($i=1;$i<=5;$i++):?><option value="<?=$i?>"><?=$i?></option><?php endfor;?></select></label><button class="btn primary wide" type="submit">Add to bag · <?=money($product['price'])?></button></form>
    <?php endif; ?>

    <form class="wishlist-inline" method="post" action="<?=e(url('/wishlist/toggle'))?>" data-intel-event="<?=is_wishlisted($product['slug'])?'wishlist_remove':'wishlist_add'?>" data-product-slug="<?=e($product['slug'])?>"><?=csrf_field()?><input type="hidden" name="slug" value="<?=e($product['slug'])?>"><input type="hidden" name="back" value="<?=e('/product/'.$product['slug'])?>"><button type="submit"><?=ui_icon('heart')?> <span><?=is_wishlisted($product['slug'])?'Saved':'Save plant'?></span></button></form>
  </div>
</section>

<section class="section shell product-story-grid sn-plant-story">
  <div><p class="kicker">About this variety</p><h2>Know the plant<br><em>before you choose.</em></h2></div>
  <div><p><?=e($product['description'])?></p><dl><dt>Care summary</dt><dd><?=e($product['care'])?></dd><dt>Nursery assurance</dt><dd><?=e($product['warranty'])?></dd><dt>Size</dt><dd><?=e($product['dimensions'])?></dd></dl></div>
</section>

<?php if(!empty($aiRelated)): ?>
<section class="section sn-related-content"><div class="shell"><div class="section-head"><div><p class="kicker">Compare & learn</p><h2>Guides featuring this plant.</h2></div></div><div class="sn-related-links"><?php foreach($aiRelated as $r):?><a href="<?=e(url($r['url']))?>"><span><?=e(ucfirst($r['type']))?></span><strong><?=e($r['title'])?></strong><b>→</b></a><?php endforeach;?></div></div></section>
<?php endif; ?>

<?php if($related): ?>
<section class="section section-soft"><div class="shell"><div class="section-head"><div><p class="kicker">More in <?=e($product['collection']??'this collection')?></p><h2>Continue exploring.</h2></div><a class="text-link" href="<?=e(url('/collections/'.growth_collection_slug($product['collection']??'')))?>">See collection →</a></div><div class="product-grid product-grid-3"><?php foreach($related as $p)include __DIR__.'/_product-card.php';?></div></div></section>
<?php endif; ?>

<section class="section section-soft si-pdp-recommendations"><div class="shell"><div class="section-head"><div><p class="kicker">Smart discovery</p><h2>Similar plants & next ideas.</h2></div></div><div class="si-recommendation-rail" data-intelligence-rail data-placement="product" data-algorithm="similar_plants" data-product="<?=e($product['slug'])?>" data-limit="4"></div></div></section>
<div class="mobile-purchase-bar sn-mobile-enquiry"><div><small><?=e($product['collection']??'Live plant')?></small><strong><?=e(product_price_label($product))?></strong></div><?php if($sellableVariants):?><a class="btn primary" href="#configure">Choose size</a><?php elseif($isEnquiry):?><a class="btn primary" href="<?=e(url('/contact?product='.rawurlencode($product['slug'])))?>" data-track="generate_lead" data-lead-type="mobile_product_availability" data-item-id="<?=e($product['sku'])?>" data-item-name="<?=e($product['name'])?>">Enquire</a><?php else:?><a class="btn primary" href="#configure">Add</a><?php endif;?></div>
