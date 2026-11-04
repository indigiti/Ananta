<section class="shell ananta-supplier-workspace">
<div class="ananta-supplier-top"><div><p class="kicker">NurserySetu · SupplierOS</p><h1>Sourcing inbox.</h1><p>Private buyer invitations assigned to <?=e($supplier['supplier_name'])?>. Your nursery cannot see other invited suppliers or their quotations.</p></div><a class="btn outline" href="<?=e(url('/supplier'))?>">Back to workspace</a></div>
<div class="ananta-supplier-panel"><div class="ananta-supplier-section-head"><div><p class="kicker">Procurement enquiries</p><h2>Requests awaiting your team</h2></div><span class="ananta-offer-count"><?=count($requests)?> received</span></div>
<div class="ananta-sourcing-list">
<?php foreach($requests as $request):?><a class="ananta-sourcing-list-item" href="<?=e(url('/supplier/enquiries/'.$request['id']))?>">
<div><strong><?=e($request['plant_name'])?></strong><small><?=e((string)$request['quantity'])?> plants · <?=e($request['specification'])?></small><small><?=e($request['destination'])?> · <?=e(gmdate('d M Y',strtotime($request['created_at'])?:time()))?></small></div>
<span class="ananta-branch-state <?=e($request['invitation']['status']==='quoted'?'approved':'pending')?>"><?=e(ucfirst($request['invitation']['status']))?></span>
</a><?php endforeach;?>
<?php if(!$requests):?><div class="ananta-supplier-empty"><h3>No sourcing invitations yet.</h3><p>When a buyer selects your verified nursery, their request appears here. No unverified enquiries are attributed to your business.</p></div><?php endif;?>
</div></div></section>