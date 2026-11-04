<?php $supplierNames=array_column(sourcing_verified_suppliers(),null,'id');?>
<section class="page-hero shell compact"><p class="kicker">Ananta · Private sourcing request</p><h1><?=e($rfq['plant_name'])?></h1><p><?=e((string)$rfq['quantity'])?> plants · <?=e($rfq['specification'])?> · <?=e($rfq['destination'])?></p></section>
<section class="section section-tight shell account-grid">
<?php include __DIR__.'/_nav.php';?>
<div class="account-main">
<section class="account-panel">
<div class="panel-head"><div><h2>RFQ <?=e(substr($rfq['id'],0,8))?></h2><p><?=e(ucfirst($rfq['status']))?> · <?=e(gmdate('d M Y',strtotime($rfq['created_at'])?:time()))?></p></div><a href="<?=e(url('/account/sourcing'))?>">All requests</a></div>
<p><strong>Specification:</strong> <?=e($rfq['specification'])?></p><p><strong>Delivery:</strong> <?=e($rfq['destination'])?></p>
<?php if($rfq['notes']!==''):?><p><strong>Notes:</strong> <?=e($rfq['notes'])?></p><?php endif;?>
<p><small>Buyer contact sharing: <?=$rfq['contact_shared']?'Enabled for invited nurseries':'Disabled — replies only through Ananta'?>.</small></p>
<?php if($rfq['status']==='open'):?><form method="post" action="<?=e(url('/account/sourcing/'.$rfq['id'].'/close'))?>"><?=csrf_field()?><button class="btn outline" type="submit">Close request to further quotes</button></form><?php endif;?>
</section>
<section class="account-panel"><h2>Responses from your selected nurseries</h2>
<?php foreach($rfq['recipients'] as $supplierId=>$response):$name=$supplierNames[$supplierId]['name']??'Nursery supplier';?>
<article class="ananta-source-response"><div><strong><?=e($name)?></strong><small><?=e(ucfirst($response['status']))?> · Updated <?=e(gmdate('d M Y',strtotime($response['updated_at'])?:time()))?></small></div>
<?php if(($response['status']??'')==='quoted'&&isset($response['quote'])):$quote=$response['quote'];?>
  <div><strong><?=money((int)$quote['unit_price_inr'])?> / plant</strong><small>Indicative total <?=money((int)$quote['total_inr'])?> · <?=$quote['lead_days']?> day lead time</small><?php if($quote['message']!==''):?><p><?=e($quote['message'])?></p><?php endif;?></div>
<?php elseif(($response['status']??'')==='declined'):?><p>This nursery cannot quote at present.</p>
<?php else:?><p>Awaiting a response.</p><?php endif;?>
</article>
<?php endforeach;?>
<p class="ananta-source-disclaimer">Quotes are supplier-authored estimates. Ananta does not reserve stock, accept payment or issue purchase orders for these requests. Reconfirm price and availability before any transaction.</p>
</section>
</div></section>