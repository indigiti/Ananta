<?php
$store=marketplace_offers_store();
$suppliers=array_values(array_filter($store['suppliers'],fn($s)=>($s['status']??'')==='verified'));
usort($suppliers,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
$published=array_values(array_filter($store['offers'],fn($o)=>($o['status']??'')==='published'));
$countBySeller=[];
foreach($published as $offer)$countBySeller[(string)$offer['supplier_id']]=($countBySeller[(string)$offer['supplier_id']]??0)+1;
?>
<section class="shell ananta-directory-hero"><p class="kicker">Ananta · Nursery Network</p><h1>Discover a nursery<br><em>you can trust.</em></h1><p>Explore verified supplier profiles and compare published, nursery-specific plant offers. Stock and prices require reconfirmation before purchase.</p><div class="ananta-directory-count"><?=count($suppliers)?> verified independent <?=count($suppliers)===1?'nursery':'nurseries'?> currently listed</div></section>
<section class="shell ananta-directory-grid" aria-label="Verified nursery directory">
  <article class="ananta-directory-card ananta-founding-card"><div class="ananta-directory-icon"><?=ui_icon('plant')?></div><span class="ananta-directory-label">Founding nursery</span><h2>Sanjay Nursery</h2><p>Loni Kalbhor, Pune · Plant discovery, nursery enquiries and verified live batch checkout where available.</p><a class="ananta-seller-cta" href="<?=e(url('/shop'))?>">Browse founding nursery catalogue <?=ui_icon('chevron-right')?></a></article>
  <?php foreach($suppliers as $s):?><article class="ananta-directory-card"><div class="ananta-directory-icon"><?=ui_icon('plant')?></div><span class="ananta-directory-label">Verified profile</span><h2><?=e($s['name'])?></h2><p><?=e($s['city'])?> · <?=($countBySeller[$s['id']]??0)?> published plant <?=($countBySeller[$s['id']]??0)===1?'offer':'offers'?>. Availability must be confirmed with the nursery.</p><a class="ananta-seller-cta" href="<?=e(url('/nurseries/'.$s['id']))?>">Explore nursery <?=ui_icon('chevron-right')?></a></article><?php endforeach;?>
</section>
<?php if(!$suppliers):?><section class="shell ananta-directory-empty"><p>No independent nurseries have been verified for publication yet. The founding nursery remains available to contact directly.</p></section><?php endif;?>
