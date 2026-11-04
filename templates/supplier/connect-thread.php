<?php $conversation=$thread['conversation'];?>
<section class="shell ananta-supplier-workspace">
<div class="ananta-supplier-top"><div><p class="kicker">Ananta Connect · Tenant-scoped thread</p><h1><?=e($conversation['phone'])?></h1><p>Verified incoming messages and actual outbound status receipts, when available.</p></div><a class="btn outline" href="<?=e(url('/supplier/connect/inbox'))?>">All conversations</a></div>
<a class="btn outline" href="<?=e(url('/supplier/connect/inbox/'.$conversation['id'].'/crm'))?>">Manage lead and follow-ups</a>
<div class="ananta-supplier-panel">
<div class="ananta-connect-thread"><?php foreach($thread['messages'] as $message):?>
<article class="ananta-connect-bubble <?=($message['direction']==='outbound'?'outbound':'inbound')?>">
<div><small><?=e($message['direction'])?> · <?=e($message['type'])?></small><p><?=e($message['body'])?></p></div>
<footer><span><?=e($message['created_at'])?></span><span><?=e(str_replace('_',' ',$message['status']))?></span></footer>
</article><?php endforeach;?></div>
<form method="post" action="<?=e(url('/supplier/connect/inbox/'.$conversation['id'].'/read'))?>"><?=csrf_field()?>
<button class="btn dark" type="submit">Mark conversation read</button></form>
<p class="ananta-import-note">Interactive replies and attachments are not enabled in B8.2; no automatic message is sent from this screen.</p>
</div>
<form method="post" action="<?=e(url('/supplier/connect/inbox/'.$conversation['id'].'/send'))?>" class="ananta-connect-send-form">
<?=csrf_field()?>
<input type="hidden" name="unique_ref" value="<?=e(uuidv7())?>">
<label>Approved template name<input name="template" required pattern="[a-z][a-z0-9_]{1,80}" placeholder="rfq_update" autocomplete="off"></label>
<label>Language<input name="language" value="en" required pattern="[a-z]{2}(_[A-Z]{2})?"></label>
<label>Purpose<select name="purpose"><option value="service">Service</option><option value="rfq_updates">RFQ updates</option><option value="marketing">Marketing</option></select></label>
<button class="btn dark" type="submit">Queue approved template</button>
<p>Requires tenant activation, explicit opt-in for this purpose and the server allowlist. This action queues a request; it does not confirm delivery.</p>
</form></section>