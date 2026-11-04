<?php
$isBoq=($rfq['type']??'')==='boq';
$supplierNames=array_column(sourcing_verified_suppliers(),null,'id');
$po=$rfq['purchase_order']??null;
?>
<section class="page-hero shell compact"><p class="kicker">Ananta · Private sourcing request</p>
  <h1><?=e($rfq['plant_name'])?></h1>
  <p><?=e((string)$rfq['quantity'])?> plants · <?=e($rfq['specification'])?> · <?=e($rfq['destination'])?></p>
</section>
<section class="section section-tight shell account-grid">
<?php include __DIR__.'/_nav.php';?>
<div class="account-main">
<section class="account-panel">
  <div class="panel-head"><div><h2>RFQ <?=e(substr($rfq['id'],0,8))?></h2><p><?=e(ucfirst(str_replace('_',' ',$rfq['status'])))?> · <?=e(gmdate('d M Y',strtotime($rfq['created_at'])?:time()))?></p></div><a href="<?=e(url('/account/sourcing'))?>">All requests</a></div>
  <p><strong>Specification:</strong> <?=e($rfq['specification'])?></p>
  <p><strong>Destination:</strong> <?=e($rfq['destination'])?></p>
  <?php if($rfq['notes']!==''):?><p><strong>Notes:</strong> <?=e($rfq['notes'])?></p><?php endif;?>
  <p><small>Buyer contact sharing: <?=$rfq['contact_shared']?'Enabled for selected nurseries':'Disabled — replies only through Ananta'?>.</small></p>
  <?php if($isBoq):?>
  <h3>Buyer-confirmed plant schedule</h3>
  <div class="ananta-boq-table-wrap"><table class="ananta-boq-table">
  <thead><tr><th>Plant</th><th>Size / grade</th><th>Quantity</th></tr></thead><tbody>
  <?php foreach($rfq['lines'] as $line):?><tr><td><?=e($line['plant_name'])?></td><td><?=e($line['specification'])?></td><td><?=e((string)$line['quantity'])?></td></tr><?php endforeach;?>
  </tbody></table></div>
  <?php endif;?>
  <?php if($rfq['status']==='open'):?><form method="post" action="<?=e(url('/account/sourcing/'.$rfq['id'].'/close'))?>"><?=csrf_field()?><button class="btn outline" type="submit">Close request to further quotes</button></form><?php endif;?>
</section>

<?php if($po):?>
<section class="account-panel ananta-buyer-po">
  <p class="kicker">Buyer-approved commercial draft</p><h2>Non-binding PO draft <?=e(substr($po['id'],0,8))?></h2>
  <p><strong><?=e($po['supplier_name'])?></strong> · Quote revision <?=e((string)$po['quote_version'])?> · <?=money((int)$po['total_inr'])?> indicative amount.</p>
  <p>This is your approved quotation selection, <strong>not a legally issued purchase order</strong>. Taxes, freight, payment terms, stock and supplier acceptance remain unconfirmed. No payment or dispatch has been triggered.</p>
</section>
<?php endif;?>

<section class="account-panel"><h2>Responses from selected nurseries</h2>
<?php foreach($rfq['recipients'] as $supplierId=>$response):
  $name=$supplierNames[$supplierId]['name']??(($po['supplier_id']??'')===$supplierId?$po['supplier_name']:'Nursery supplier');
  $quote=$response['quote']??null;?>
<article class="ananta-source-response">
  <div><strong><?=e($name)?></strong><small><?=e(ucfirst($response['status']))?> · Revision <?=e((string)($response['quote_version']??0))?></small></div>
  <?php if(($response['status']??'')==='quoted'&&is_array($quote)):?>
  <div>
    <?php if($isBoq):?>
      <strong>Indicative total <?=money((int)$quote['total_inr'])?></strong><small><?=e((string)$quote['lead_days'])?> day estimated lead time</small>
      <div class="ananta-boq-table-wrap"><table class="ananta-boq-table"><thead><tr><th>Plant</th><th>Unit</th><th>Line total</th></tr></thead><tbody>
      <?php foreach($rfq['lines'] as $line):$price=$quote['line_prices'][$line['id']]??null;if(!$price)continue;?>
      <tr><td><?=e($line['plant_name'])?> · <?=e((string)$line['quantity'])?></td><td><?=money((int)$price['unit_price_inr'])?></td><td><?=money((int)$price['line_total_inr'])?></td></tr>
      <?php endforeach;?></tbody></table></div>
      <?php if($rfq['status']==='open'):?>
      <form method="post" action="<?=e(url('/account/sourcing/'.$rfq['id'].'/approve-draft'))?>" class="ananta-boq-award-form">
        <?=csrf_field()?>
        <input type="hidden" name="supplier_id" value="<?=e($supplierId)?>">
        <input type="hidden" name="quote_version" value="<?=e((string)$response['quote_version'])?>">
        <label class="ananta-source-check"><input type="checkbox" name="confirmation" value="approve_nonbinding_draft" required>
        <span>I approve <strong>this exact quote revision</strong> as a non-binding PO draft. I understand stock, taxes, shipping, payment and supplier acceptance are NOT confirmed.</span></label>
        <button type="submit" class="btn dark">Approve this draft quotation</button>
      </form>
      <?php endif;?>
      <small><?=count($response['revisions']??[])?> revision<?=count($response['revisions']??[])===1?'':'s'?> recorded</small>
    <?php else:?>
      <strong><?=money((int)$quote['unit_price_inr'])?> / plant</strong><small>Indicative total <?=money((int)$quote['total_inr'])?> · <?=e((string)$quote['lead_days'])?> day lead time</small>
    <?php endif;?>
    <?php if(($quote['message']??'')!==''):?><p><?=e($quote['message'])?></p><?php endif;?>
  </div>
  <?php elseif(($response['status']??'')==='declined'):?><p>This nursery cannot quote at present.</p>
  <?php else:?><p>Awaiting a response.</p><?php endif;?>
</article>
<?php endforeach;?>
<p class="ananta-source-disclaimer">Prices are independent supplier estimates. No inventory is reserved, no payment is collected and no binding purchase order is issued by Ananta at this stage.</p>
</section>
</div></section>