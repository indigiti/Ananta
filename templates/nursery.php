<?php
$offers=array_values(array_filter(marketplace_offers_store()['offers'],fn($o)=>($o['supplier_id']??'')===$supplier['id']&&($o['status']??'')==='published'));
usort($offers,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));
?>
<section class="shell ananta-directory-hero"><p class="kicker">Ananta · Verified nursery profile</p><h1><?=e($supplier['name'])?></h1><p><?=e($supplier['city'])?> · Compare offers from this nursery. All external nursery offers are enquiry-only during the pilot.</p><a class="ananta-seller-cta" href="<?=e(url('/nurseries'))?>">← Explore all nurseries</a></section>
<section class="shell ananta-nursery-offers"><div class="ananta-buy-head"><h2>Published plant offers</h2><span class="ananta-offer-count"><?=count($offers)?> offers</span></div>
<?php foreach($offers as $offer):$plant=product_by_guid((string)($offer['product_guid']??''));if(!$plant||($plant['status']??'')!=='active')continue;$status=marketplace_offer_public_status($offer);$publicOffer=$offer;$publicOffer['supplier']=$supplier;?>
<article class="ananta-seller-option"><div class="ananta-seller-name"><span class="ananta-seller-avatar"><?=ui_icon('plant')?></span><div><strong><?=e($plant['name'])?></strong><small><?=e($offer['label'])?></small></div><span class="ananta-status <?=e($status['key'])?>"><?=e($status['label'])?></span></div>
<div class="ananta-offer-detail"><span><?=e($status['note'])?></span><strong><?=isset($offer['price_inr'])&&$offer['price_inr']>0?money($offer['price_inr']):'Ask for price'?></strong></div>
<div class="ananta-offer-actions"><a class="ananta-seller-cta" href="<?=e(url('/product/'.$plant['slug'].'#buy-from'))?>">View plant <?=ui_icon('chevron-right')?></a><a class="ananta-seller-cta" href="<?=e(marketplace_offer_contact_url($publicOffer,$plant))?>" target="_blank" rel="noopener noreferrer">Ask on WhatsApp <?=ui_icon('external')?></a></div></article>
<?php endforeach;?>
<?php if(!$offers):?><div class="ananta-no-offers"><p>This nursery has no published plant offers yet. No stock or pricing is assumed.</p></div><?php endif;?></section>
