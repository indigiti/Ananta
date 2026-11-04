<?php $entry=$request['invitation'];$quote=$entry['quote']??null;?>
<section class="shell ananta-supplier-workspace">
<div class="ananta-supplier-top"><div><p class="kicker">NurserySetu · Private sourcing invitation</p><h1><?=e($request['plant_name'])?></h1><p><?=e((string)$request['quantity'])?> plants · <?=e($request['destination'])?> · <?=e(ucfirst($request['status']))?></p></div><a class="btn outline" href="<?=e(url('/supplier/enquiries'))?>">Back to enquiries</a></div>
<div class="ananta-supplier-panel"><h2>Buyer requirement</h2><dl class="ananta-rfq-facts"><div><dt>Plant</dt><dd><?=e($request['plant_name'])?></dd></div><div><dt>Required quantity</dt><dd><?=e((string)$request['quantity'])?></dd></div><div><dt>Size / grade</dt><dd><?=e($request['specification'])?></dd></div><div><dt>Destination</dt><dd><?=e($request['destination'])?></dd></div></dl>
<?php if($request['notes']!==''):?><p><strong>Additional notes:</strong> <?=e($request['notes'])?></p><?php endif;?>
<?php if($request['contact_shared']&&!empty($request['buyer_contact'])):$contact=$request['buyer_contact'];?>
<div class="ananta-sourcing-contact"><strong>Buyer opted to share contact details</strong><p><?=e($contact['name'])?> · <?=e($contact['email'])?> <?=e($contact['phone'])?></p></div>
<?php else:?><p class="ananta-import-note">The buyer has not opted to share contact details. Respond securely through this RFQ.</p><?php endif;?>
</div>
<div class="ananta-supplier-panel"><h2>Your nursery's response</h2>
<?php if($quote&&$entry['status']==='quoted'):?><p>Current indication: <?=money((int)$quote['unit_price_inr'])?> per plant · <?=e((string)$quote['lead_days'])?> days. Revision <?=e((string)$entry['quote_version'])?>.</p><?php elseif($entry['status']==='declined'):?><p>Your nursery previously declined. You can submit an updated indication while the request remains open.</p><?php endif;?>
<?php if($request['status']==='open'&&tenant_can('rfq.quote',$supplier['tenant_id'])):?>
<form class="ananta-supplier-form" method="post" action="<?=e(url('/supplier/enquiries/'.$request['id'].'/respond'))?>">
<?=csrf_field()?>
<label>Response<select name="decision" required><option value="quoted">Submit indicative quotation</option><option value="declined">Unable to quote</option></select></label>
<label>Indicative unit price (INR, required for quotation)<input name="unit_price_inr" type="number" min="1" max="100000000" value="<?=e((string)($quote['unit_price_inr']??''))?>"></label>
<label>Estimated lead time in days (0–365)<input name="lead_days" type="number" min="0" max="365" value="<?=e((string)($quote['lead_days']??''))?>"></label>
<label>Supplier notes (optional)<textarea name="message" rows="3" maxlength="600" placeholder="Plant grade, packaging or dispatch assumptions"><?=e((string)($quote['message']??''))?></textarea></label>
<button class="btn dark" type="submit">Send response to buyer</button>
</form>
<?php elseif($request['status']!=='open'):?><p>This sourcing request is closed. New quotes or revisions are not accepted.</p>
<?php else:?><p>You have read-only access. Contact your nursery owner or manager to submit a quote.</p><?php endif;?>
<p class="ananta-supplier-caption">Only your nursery's quote is visible here. Quoted amounts are estimates, not confirmed inventory or purchase orders.</p>
</div></section>