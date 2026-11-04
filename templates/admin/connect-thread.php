<?php $conversation=$thread['conversation'];?>
<section class="admin-head"><div><p class="eyebrow">Ananta Connect · Platform thread</p><h1><?=e($conversation['phone'])?></h1><p>Authenticated provider events and delivery statuses.</p></div><a class="btn outline" href="<?=e($back)?>">Inbox</a>
<form method="post" action="<?=e(url('/admin/connect/inbox/'.$conversation['id'].'/send'))?>" class="ananta-connect-send-form">
<?=csrf_field()?>
<input type="hidden" name="unique_ref" value="<?=e(uuidv7())?>">
<label>Approved template name<input name="template" required pattern="[a-z][a-z0-9_]{1,80}" placeholder="rfq_update" autocomplete="off"></label>
<label>Language<input name="language" value="en" required pattern="[a-z]{2}(_[A-Z]{2})?"></label>
<label>Purpose<select name="purpose"><option value="service">Service</option><option value="rfq_updates">RFQ updates</option><option value="marketing">Marketing</option></select></label>
<button class="btn primary" type="submit">Queue approved template</button>
<p>Requires tenant activation, explicit opt-in for this purpose and the server allowlist. This action queues a request; it does not confirm delivery.</p>
</form></section>
<section class="admin-panel"><div class="ananta-connect-thread">
<?php foreach($thread['messages'] as $message):?><article class="ananta-connect-bubble <?=e($message['direction'])?>">
<small><?=e($message['direction'])?> · <?=e($message['type'])?></small><p><?=e($message['body'])?></p>
<footer><?=e($message['created_at'])?> · <?=e(str_replace('_',' ',$message['status']))?></footer></article><?php endforeach;?>
</div>
<form method="post" action="<?=e(url('/admin/connect/inbox/'.$conversation['id'].'/read'))?>"><?=csrf_field()?><button class="btn primary" type="submit">Mark read</button></form></section>