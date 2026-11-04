<?php $threadId=$thread['conversation']['id']; $lead=$lead??[];?>
<section class="shell ananta-supplier-workspace">
<div class="ananta-supplier-top"><div><p class="kicker">Ananta Connect · CRM</p><h1>Lead and follow-ups.</h1><p>Tenant-only conversation: <?=e($thread['conversation']['phone'])?>. Edits are versioned; assignments require an active nursery owner or manager.</p></div><a class="btn outline" href="<?=e(url('/supplier/connect/inbox/'.$threadId))?>">Back to conversation</a></div>
<div class="ananta-supplier-panel"><h2>Lead profile</h2>
<form class="ananta-connect-send-form" action="<?=e(url('/supplier/connect/inbox/'.$threadId.'/crm'))?>" method="post">
<?=csrf_field()?>
<input type="hidden" name="revision" value="<?=e((string)($lead['revision']??0))?>">
<label>Sales stage<select name="status"><?php foreach(connect_crm_stages() as $stage):?><option value="<?=e($stage)?>" <?=($lead['status']??'new')===$stage?'selected':''?>><?=e(ucfirst($stage))?></option><?php endforeach;?></select></label>
<label>Assigned nursery team member<select name="assignee_id"><option value="">Unassigned</option><?php foreach($team as $member):?><option value="<?=e($member['id'])?>" <?=($lead['assignee_id']??'')===$member['id']?'selected':''?>><?=e($member['name'])?> · <?=e($member['role'])?></option><?php endforeach;?></select></label>
<label>Tags (comma-separated, max 8)<input name="tags" value="<?=e(implode(', ',(array)($lead['tags']??[])))?>" maxlength="180"></label>
<label>Private note<textarea name="note" rows="3" maxlength="600" placeholder="Add a traceable activity note; do not paste secrets"></textarea></label>
<button class="btn dark" type="submit">Save CRM changes</button>
</form>
<?php if(!empty($lead['notes'])):?><h3>Activity notes</h3><ol class="ananta-readiness-timeline"><?php foreach(array_reverse($lead['notes']) as $note):?><li><small><?=e($note['created_at'])?></small><p><?=e($note['body'])?></p></li><?php endforeach;?></ol><?php endif;?>
</div>
<div class="ananta-supplier-panel"><h2>Schedule a follow-up</h2>
<form method="post" class="ananta-connect-send-form" action="<?=e(url('/supplier/connect/inbox/'.$threadId.'/task'))?>">
<?=csrf_field()?>
<label>Task title<input name="title" required maxlength="140" placeholder="Call about nursery quote"></label>
<label>Due date (UTC)<input name="due_date" type="date" required min="<?=e(gmdate('Y-m-d'))?>"></label>
<label>Assign<select name="assignee_id"><option value="">Unassigned</option><?php foreach($team as $member):?><option value="<?=e($member['id'])?>"><?=e($member['name'])?></option><?php endforeach;?></select></label>
<button class="btn dark" type="submit">Create follow-up</button></form>
<h3>Recent follow-ups</h3>
<?php foreach($tasks as $task):?><div class="ananta-connect-task"><div><strong><?=e($task['title'])?></strong><small><?=e($task['due_date'])?> · <?=e($task['status'])?></small></div>
<?php if($task['status']==='open'):?><form method="post" action="<?=e(url('/supplier/connect/task/'.$task['id'].'/complete'))?>"><?=csrf_field()?><input type="hidden" name="conversation_id" value="<?=e($threadId)?>"><button class="btn outline" type="submit">Complete</button></form><?php endif;?></div><?php endforeach;?>
<?php if(!$tasks):?><p>No follow-up tasks created.</p><?php endif;?></div>
</section>