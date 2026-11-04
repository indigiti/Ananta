<section class="admin-head"><div><p class="eyebrow">Ananta Connect · Platform tenant</p><h1>Verified WhatsApp inbox</h1><p>Platform sender only. Independent nurseries see their messages inside their own supplier workspace.</p></div><a class="btn outline" href="<?=e($back)?>">Back to Connect</a></section>
<section class="admin-metric-grid"><?php foreach($queue as $state=>$count):?><article><span><?=e(str_replace('_',' ',$state))?></span><strong><?=e((string)$count)?></strong><small>Provider-based queue state</small></article><?php endforeach;?></section>
<section class="admin-panel"><h2>Recent platform conversations</h2>
<?php foreach($conversations as $conversation):?>
<a class="ananta-connect-item" href="<?=e(url('/admin/connect/inbox/'.$conversation['id']))?>"><div><strong><?=e($conversation['phone'])?></strong><small><?=e($conversation['last_message_at'])?></small></div><span><?=e((string)$conversation['unread'])?> unread</span></a>
<?php endforeach;?>
<?php if(!$conversations):?><p>No platform messages received.</p><?php endif;?></section>