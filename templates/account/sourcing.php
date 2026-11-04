<section class="page-hero shell compact"><p class="kicker">Ananta · Buyer sourcing</p><h1>Your nursery requests.</h1><p>Track invitations and compare indicative quotations from verified nurseries. Nothing here is a confirmed order.</p></section>
<section class="section section-tight shell account-grid">
<?php include __DIR__.'/_nav.php';?>
<div class="account-main"><section class="account-panel"><div class="panel-head"><h2>Supplier quotation requests</h2><div class="ananta-source-actions"><a class="btn outline" href="<?=e(url('/sourcing'))?>">Single plant RFQ</a><a class="btn dark" href="<?=e(url('/sourcing/boq'))?>">Upload BOQ</a></div></div>
<?php foreach($rfqs as $rfq):?>
<a class="order-row" href="<?=e(url('/account/sourcing/'.$rfq['id']))?>">
<div><strong><?=e($rfq['plant_name'])?> · <?=e((string)$rfq['quantity'])?> plants</strong><small><?=e($rfq['specification'])?> · <?=count($rfq['recipients'])?> nurseries · <?=e(gmdate('d M Y',strtotime($rfq['created_at'])?:time()))?></small></div><span class="status-pill commerce"><?=e($rfq['status'])?></span></a>
<?php endforeach;?>
<?php if(!$rfqs):?><p>You have not sent any nursery sourcing requests yet. Start with one plant requirement and choose up to five nurseries.</p><?php endif;?>
</section></div></section>