<?php
$entry=$request['invitation'];$quote=$entry['quote']??null;
$isBoq=($request['type']??'')==='boq';
?>
<section class="shell ananta-supplier-workspace">
<div class="ananta-supplier-top"><div><p class="kicker">NurserySetu · Private sourcing invitation</p><h1><?=e($request['plant_name'])?></h1>
  <p><?=e((string)$request['quantity'])?> plants · <?=e($request['destination'])?> · <?=e(ucfirst(str_replace('_',' ',$request['status'])))?></p>
</div><a class="btn outline" href="<?=e(url('/supplier/enquiries'))?>">Back to enquiries</a></div>
<div class="ananta-supplier-panel">
  <h2>Buyer requirement</h2>
  <dl class="ananta-rfq-facts"><div><dt>Requirement</dt><dd><?=e($request['plant_name'])?></dd></div>
  <div><dt>Total plants</dt><dd><?=e((string)$request['quantity'])?></dd></div>
  <div><dt>Specification</dt><dd><?=e($request['specification'])?></dd></div>
  <div><dt>Destination</dt><dd><?=e($request['destination'])?></dd></div></dl>
  <?php if($isBoq):?>
    <h3>Buyer-approved BOQ line items</h3><div class="ananta-boq-table-wrap"><table class="ananta-boq-table"><thead><tr><th>Plant</th><th>Quantity</th><th>Specification</th></tr></thead><tbody>
    <?php foreach($request['lines'] as $line):?><tr><td><?=e($line['plant_name'])?></td><td><?=e((string)$line['quantity'])?></td><td><?=e($line['specification'])?></td></tr><?php endforeach;?>
    </tbody></table></div>
  <?php endif;?>
  <?php if($request['notes']!==''):?><p><strong>Additional notes:</strong> <?=e($request['notes'])?></p><?php endif;?>
  <?php if($request['contact_shared']&&!empty($request['buyer_contact'])):$contact=$request['buyer_contact'];?>
    <div class="ananta-sourcing-contact"><strong>Buyer opted to share contact details</strong><p><?=e($contact['name'])?> · <?=e($contact['email'])?> <?=e($contact['phone'])?></p></div>
  <?php else:?><p class="ananta-import-note">Buyer has not opted to share contact information. Respond securely here.</p><?php endif;?>
</div>
<div class="ananta-supplier-panel">
  <h2>Your nursery's <?=($isBoq?'line-by-line quotation':'response')?></h2>
  <?php if($quote&&$entry['status']==='quoted'):?>
    <p>Current indicative total: <?=money((int)$quote['total_inr'])?> · <?=e((string)$quote['lead_days'])?> day lead time. Revision <?=e((string)$entry['quote_version'])?>.</p>
  <?php elseif($entry['status']==='declined'):?><p>Your nursery previously declined. You can revise while the request remains open.</p><?php endif;?>
  <?php if(!empty($request['awarded_draft'])):?><p class="ananta-import-note">Buyer selected your quotation as a non-binding commercial draft. Stock, payment and supplier acceptance are not confirmed.</p><?php endif;?>
  <?php if($request['status']==='open'&&tenant_can('rfq.quote',$supplier['tenant_id'])):?>
  <?php if($isBoq):?>
  <form class="ananta-supplier-form" method="post" action="<?=e(url('/supplier/enquiries/'.$request['id'].'/boq-quote'))?>">
    <?=csrf_field()?><input type="hidden" name="quote_version" value="<?=e((string)$entry['quote_version'])?>">
    <p>Enter your own indicative price for every line. No price is automatically inferred from the catalogue.</p>
    <div class="ananta-boq-table-wrap"><table class="ananta-boq-table"><thead><tr><th>Plant, grade and quantity</th><th>Unit price (INR)</th></tr></thead><tbody>
    <?php foreach($request['lines'] as $line):$latest=$quote['line_prices'][$line['id']]['unit_price_inr']??null;?>
      <tr><td><strong><?=e($line['plant_name'])?></strong> · <?=e($line['specification'])?><small><?=e((string)$line['quantity'])?> plants</small></td>
      <td><label>Price per plant<input name="line_prices[<?=e($line['id'])?>]" type="number" min="1" max="100000000" value="<?=e((string)($latest??''))?>"></label></td></tr>
    <?php endforeach;?></tbody></table></div>
    <label>Decision<select name="decision" required><option value="quoted">Submit revised quotation</option><option value="declined">Unable to quote</option></select></label>
    <label>Estimated lead time (days)<input name="lead_days" type="number" min="0" max="365" value="<?=e((string)($quote['lead_days']??''))?>"></label>
    <label>Assumptions and packing notes<textarea name="message" rows="3" maxlength="600"><?=e((string)($quote['message']??''))?></textarea></label>
    <button class="btn dark" type="submit">Submit BOQ quote revision</button>
  </form>
  <?php else:?>
  <form class="ananta-supplier-form" method="post" action="<?=e(url('/supplier/enquiries/'.$request['id'].'/respond'))?>">
    <?=csrf_field()?>
    <label>Response<select name="decision" required><option value="quoted">Submit indicative quotation</option><option value="declined">Unable to quote</option></select></label>
    <label>Indicative unit price (INR, required for quotation)<input name="unit_price_inr" type="number" min="1" max="100000000" value="<?=e((string)($quote['unit_price_inr']??''))?>"></label>
    <label>Estimated lead time in days (0–365)<input name="lead_days" type="number" min="0" max="365" value="<?=e((string)($quote['lead_days']??''))?>"></label>
    <label>Supplier notes (optional)<textarea name="message" rows="3" maxlength="600"><?=e((string)($quote['message']??''))?></textarea></label>
    <button class="btn dark" type="submit">Send response to buyer</button>
  </form>
  <?php endif;?>
  <?php elseif($request['status']!=='open'):?><p>The buyer closed or selected a draft. New quote revisions are blocked.</p>
  <?php else:?><p>You have read-only access. Contact your nursery owner or manager to quote.</p><?php endif;?>
  <p class="ananta-supplier-caption">These are supplier-authored, indicative offers. No live inventory, payment, or binding purchase order is created by this form.</p>
</div>

<?php if($isBoq&&!empty($request['awarded_draft'])):
  $planning=$request['awarded_draft']['readiness']??['status'=>'awaiting_supplier','version'=>0,'events'=>[],'proposal'=>null];
  $proposal=$planning['proposal']??null;
?>
<div class="ananta-supplier-panel ananta-readiness-panel" id="fulfilment-readiness">
  <p class="kicker">B6 · Supplier-reported readiness</p>
  <h2>Reconfirm stock and delivery terms</h2>
  <p><strong>Current stage:</strong> <?=e(ucwords(str_replace('_',' ',$planning['status'])))?> · Version <?=e((string)$planning['version'])?></p>
  <p class="ananta-import-note">This is a non-binding purchasing plan. Your stock statement does not reserve inventory. Freight and tax treatment must be independently confirmed before any legally issued purchase order.</p>
  <?php if($proposal):?>
    <dl class="ananta-rfq-facts">
      <div><dt>Dispatch estimate</dt><dd><?=e((string)$proposal['dispatch_days'])?> days</dd></div>
      <div><dt>Freight estimate</dt><dd><?=money((int)$proposal['freight_inr'])?></dd></div>
      <div><dt>Delivery responsibility</dt><dd><?=e(str_replace('_',' ',ucwords($proposal['delivery_mode'],'_')))?></dd></div>
      <div><dt>GST / tax declaration</dt><dd><?=e(str_replace('_',' ',ucwords($proposal['tax_treatment'],'_')))?></dd></div>
    </dl>
    <p><strong>Supplier note:</strong> <?=e($proposal['comment'])?></p>
    <p><strong>Stock statement:</strong> <?=$proposal['all_lines_reported_sufficient']?'All lines reportedly available':'Shortfall reported on one or more lines'?>. Not a reservation.</p>
  <?php endif;?>
  <?php if(in_array($planning['status'],['awaiting_supplier','buyer_changes_requested','supplier_proposed'],true)
      &&tenant_can('rfq.quote',$supplier['tenant_id'])):?>
  <form method="post" action="<?=e(url('/supplier/enquiries/'.$request['id'].'/readiness'))?>" class="ananta-supplier-form">
    <?=csrf_field()?>
    <input type="hidden" name="readiness_version" value="<?=e((string)$planning['version'])?>">
    <label>Readiness decision
      <select name="decision" required>
        <option value="propose">Submit stock and delivery estimate</option>
        <option value="decline">Unable to fulfil this selected BOQ</option>
      </select>
    </label>
    <p class="ananta-import-note">For a proposal, enter supplier-reported available quantity for every BOQ line, including zero for unavailable stock.</p>
    <div class="ananta-boq-table-wrap"><table class="ananta-boq-table">
      <thead><tr><th>Plant / grade</th><th>Required</th><th>Reported availability</th></tr></thead><tbody>
      <?php foreach($request['lines'] as $line):
        $reported=$proposal['line_availability'][$line['id']]['reported_available']??null;?>
      <tr>
        <td><?=e($line['plant_name'])?> · <?=e($line['specification'])?></td>
        <td><?=e((string)$line['quantity'])?></td>
        <td><label>Supplier-reported quantity
          <input type="number" min="0" max="50000" name="available_quantities[<?=e($line['id'])?>]" value="<?=e($reported===null?'':(string)$reported)?>">
        </label></td>
      </tr>
      <?php endforeach;?>
      </tbody></table></div>
    <label>Dispatch estimate (days)<input type="number" min="0" max="365" name="dispatch_days" value="<?=e((string)($proposal['dispatch_days']??''))?>"></label>
    <label>Freight estimate in INR (zero if pickup or included in quotation)<input type="number" min="0" max="2000000" name="freight_inr" value="<?=e((string)($proposal['freight_inr']??''))?>"></label>
    <label>Delivery responsibility<select name="delivery_mode">
      <option value="">Select</option>
      <?php foreach(['supplier_delivery'=>'Supplier delivery','buyer_pickup'=>'Buyer pickup','third_party_transport'=>'Third-party transport'] as $value=>$label):?>
        <option value="<?=e($value)?>" <?=($proposal['delivery_mode']??'')===$value?'selected':''?>><?=e($label)?></option>
      <?php endforeach;?>
    </select></label>
    <label>Tax treatment (declaration only)
      <select name="tax_treatment">
      <option value="">Select</option>
      <option value="unconfirmed">Tax treatment unconfirmed</option>
      <option value="supplier_states_included" <?=($proposal['tax_treatment']??'')==='supplier_states_included'?'selected':''?>>Supplier states taxes included</option>
      <option value="supplier_states_extra" <?=($proposal['tax_treatment']??'')==='supplier_states_extra'?'selected':''?>>Supplier states taxes extra</option>
      </select>
    </label>
    <label>Notes / reason for declining<textarea name="comment" maxlength="500" rows="3"><?=e((string)($proposal['comment']??''))?></textarea></label>
    <button class="btn dark" type="submit">Record non-binding readiness response</button>
  </form>
  <?php elseif($planning['status']==='buyer_acknowledged'):?>
    <p class="ananta-import-note">Buyer acknowledged these terms for planning only. This is not supplier acceptance of a binding purchase order.</p>
  <?php else:?><p class="ananta-import-note">This readiness response is closed to supplier edits.</p><?php endif;?>
  <?php if(!empty($planning['events'])):?>
    <h3>Readiness activity</h3><ol class="ananta-readiness-timeline">
    <?php foreach(array_reverse($planning['events']) as $event):?><li>
      <strong><?=e(ucwords(str_replace('_',' ',$event['action'])))?></strong>
      <small><?=e($event['at'])?> · Version <?=e((string)$event['version'])?></small>
      <?php if(($event['note']??'')!==''):?><small>Note: <?=e($event['note'])?></small><?php endif;?>
    </li><?php endforeach;?></ol>
  <?php endif;?>
</div>
<?php endif;?>
</section>