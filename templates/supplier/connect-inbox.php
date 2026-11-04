<section class="shell ananta-supplier-workspace">
<div class="ananta-supplier-top"><div><p class="kicker">NurserySetu · Ananta Connect</p><h1>WhatsApp inbox.</h1><p>Official Meta Cloud API conversations for your nursery only. Inbound messages and provider receipts are recorded after signature verification.</p></div><a class="btn outline" href="<?=e(url('/supplier/connect'))?>">Sender settings</a></div>
<p><a class="btn outline" href="<?=e(url('/supplier/connect/report'))?>">CRM reports and follow-ups</a></p>
<div class="ananta-supplier-panel"><h2>Conversations</h2><p>Showing the most recent <?=count($conversations)?> conversations. Message status is based on Meta delivery events, not assumptions.</p>
<div class="ananta-connect-list"><?php foreach($conversations as $conversation):?>
<a class="ananta-connect-item" href="<?=e(url('/supplier/connect/inbox/'.$conversation['id']))?>">
<div><strong><?=e($conversation['phone'])?></strong><small>Last activity <?=e($conversation['last_message_at'])?></small></div>
<span class="ananta-connect-count"><?=e((string)$conversation['unread'])?> unread</span></a>
<?php endforeach;?><?php if(!$conversations):?><p>No verified inbound conversations are recorded for this nursery yet.</p><?php endif;?></div>
</div>
<div class="ananta-supplier-panel"><h2>Outbound queue</h2><div class="ananta-connect-metrics">
<?php foreach($queue as $status=>$total):?><div><span><?=e(str_replace('_',' ',$status))?></span><strong><?=e((string)$total)?></strong></div><?php endforeach;?></div>
<p class="ananta-import-note">Sends are limited to explicitly approved WhatsApp templates and purpose-specific consent. Jobs with uncertain provider acceptance are held for manual reconciliation.</p></div>
</section>