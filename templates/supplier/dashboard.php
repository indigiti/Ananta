<?php
$mine=array_values(array_filter(marketplace_offers_store()['offers'],fn($o)=>($o['supplier_id']??'')===$supplier['tenant_id']));
$editId=(string)($_GET['edit']??'');
$editing=null;
foreach($mine as $o)if($o['id']===$editId&&($o['status']??'draft')==='draft'){$editing=$o;break;}
$editingPlant=$editing?product_by_guid((string)$editing['product_guid']):null;
?>
<section class="shell ananta-supplier-workspace">
<div class="ananta-supplier-top"><div><p class="kicker">NurserySetu · Private supplier workspace</p><h1><?=e($supplier['supplier_name'])?></h1><p>Welcome, <?=e($supplier['name'])?> · <?=e(ucfirst($supplier['role']))?>. Manage your own nursery's offers; other suppliers' private records are not accessible.</p></div><form method="post" action="<?=e(url('/supplier/logout'))?>"><?=csrf_field()?><button class="btn outline" type="submit">Sign out</button></form></div>
<div class="ananta-supplier-stats"><article><small>Total offers</small><strong><?=count($mine)?></strong></article><article><small>Published</small><strong><?=count(array_filter($mine,fn($o)=>($o['status']??'')==='published'))?></strong></article><article><small>Awaiting review</small><strong><?=count(array_filter($mine,fn($o)=>($o['status']??'')==='draft'))?></strong></article></div>
<?php if(tenant_can('offers.draft',$supplier['tenant_id'])):?>
<section class="ananta-supplier-panel"><h2><?=($editing?'Edit your draft':'Submit a plant offer')?></h2><p>Drafts require platform review before they appear publicly. Prices are indicative and never enter the founding nursery's checkout.</p>
<form class="ananta-supplier-form" action="<?=e(url('/supplier/offer/draft'))?>" method="post"><?=csrf_field()?>
<input type="hidden" name="id" value="<?=e($editing['id']??'')?>">
<label>Plant variety<select name="product_slug" required><option value="">Choose a plant</option><?php foreach(products_public() as $p):?><option value="<?=e($p['slug'])?>" <?=($editingPlant['slug']??'')===$p['slug']?'selected':''?>><?=e($p['name'])?></option><?php endforeach;?></select></label>
<label>Size / grade / specification<input name="label" maxlength="140" required value="<?=e($editing['label']??'')?>" placeholder="120–150 cm · 12 inch pot · Grade A"></label>
<label>Indicative price (INR, optional)<input name="price_inr" type="number" min="1" max="10000000" value="<?=isset($editing['price_inr'])?e((string)$editing['price_inr']):''?>"></label>
<label>Estimated quantity (optional)<input name="quantity" type="number" min="0" max="1000000" value="<?=isset($editing['quantity'])?e((string)$editing['quantity']):''?>"></label>
<input type="hidden" name="availability" value="unknown"><button class="btn dark" type="submit">Save draft for review</button>
</form></section><?php endif;?>
<section class="ananta-supplier-panel"><h2>Your nursery offers</h2>
<?php if(!$mine):?><p>No offers yet. Add your first draft above or ask the platform team to publish a reviewed listing.</p><?php endif;?>
<div class="ananta-supplier-list"><?php foreach($mine as $o):$plant=product_by_guid((string)($o['product_guid']??''));$av=marketplace_offer_public_status($o);?>
<article class="ananta-supplier-offer"><div><strong><?=e($plant['name']??'Unlinked product')?></strong><small><?=e($o['label']??'')?> · <?=e($o['status']??'draft')?> · <?=e($av['label'])?></small><?php if($o['status']==='draft'&&tenant_can('offers.draft',$supplier['tenant_id'])):?><a href="<?=e(url('/supplier?edit='.$o['id']))?>">Edit draft</a><?php endif;?></div>
<?php if(tenant_can('availability.report',$supplier['tenant_id'])):?>
<form action="<?=e(url('/supplier/offer/report'))?>" method="post" class="ananta-supplier-report"><?=csrf_field()?>
<input type="hidden" name="offer_id" value="<?=e($o['id'])?>"><label>Supplier availability<select name="availability"><option value="unknown">Check availability</option><option value="reported">Supplier-reported stock</option><option value="unavailable">Unavailable</option></select></label>
<label>Quantity<input type="number" min="0" max="1000000" name="quantity" value="<?=isset($o['quantity'])?e((string)$o['quantity']):''?>"></label><button class="btn outline" type="submit">Update availability</button>
</form><?php endif;?></article><?php endforeach;?></div></section>
<p class="ananta-supplier-caption">Supplier stock reports are indicative, not verified reservations. Confirmed availability must be reviewed by the Ananta platform administrator.</p>
</section>