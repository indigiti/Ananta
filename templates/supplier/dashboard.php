<?php
$tenantId=$supplier['tenant_id'];
$branches=supplier_branches_for_tenant($tenantId);
$branchesById=[];foreach($branches as $branch)$branchesById[$branch['id']]=$branch;
$mine=array_values(array_filter(marketplace_offers_store()['offers'],static fn($o)=>($o['supplier_id']??'')===$tenantId));
$editId=(string)($_GET['edit']??'');$editing=null;
foreach($mine as $o)if($o['id']===$editId&&($o['status']??'draft')==='draft'){$editing=$o;break;}
$editingPlant=$editing?product_by_guid((string)$editing['product_guid']):null;
$branchEditId=(string)($_GET['branch']??'');
$branchEditing=$branchesById[$branchEditId]??null;
$approvedBranches=array_values(array_filter($branches,static fn($b)=>($b['state']??'pending')==='approved'));
?>
<section class="shell ananta-supplier-workspace">
  <div class="ananta-supplier-top">
    <div><p class="kicker">NurserySetu · SupplierOS</p><h1><?=e($supplier['supplier_name'])?></h1>
      <p><?=e($supplier['name'])?> · <?=e(ucfirst($supplier['role']))?> · Private nursery workspace</p>
      <p>Manage your branches, offer drafts and supplier-reported stock. Platform review controls public publication and confirmed availability.</p>
    </div>
    <form action="<?=e(url('/supplier/logout'))?>" method="post"><?=csrf_field()?><button type="submit" class="btn outline">Sign out</button></form>
  </div>
  <div class="ananta-supplier-stats" aria-label="Supplier summary">
    <article><small>Branches</small><strong><?=count($branches)?></strong><span><?=count($approvedBranches)?> approved</span></article>
    <article><small>Your offers</small><strong><?=count($mine)?></strong><span><?=count(array_filter($mine,static fn($o)=>($o['status']??'')==='published'))?> published</span></article>
    <article><small>For review</small><strong><?=count(array_filter($mine,static fn($o)=>($o['status']??'')==='draft'))?></strong><span>Draft offers</span></article>
  </div>
  <section class="ananta-supplier-panel" id="branches">
    <div class="ananta-supplier-section-head"><div><p class="kicker">Locations and fulfillment</p><h2>Your nursery branches</h2></div><span class="ananta-offer-count"><?=count($branches)?> branches</span></div>
    <p>Each branch is reviewed independently. Changing its name, city or address returns it to pending review and temporarily hides its public offers.</p>
    <?php if(tenant_can('branches.manage',$tenantId)):?>
      <form class="ananta-supplier-form" method="post" action="<?=e(url('/supplier/branch/save'))?>"><?=csrf_field()?>
        <input type="hidden" name="id" value="<?=e($branchEditing['id']??'')?>">
        <label>Branch name<input name="name" maxlength="110" required value="<?=e($branchEditing['name']??'')?>" placeholder="Main growing nursery"></label>
        <label>City<input name="city" maxlength="100" required value="<?=e($branchEditing['city']??'')?>" placeholder="Pune"></label>
        <label>Site address (private)<textarea name="address" maxlength="250" rows="2" placeholder="Full address for internal review"><?=e($branchEditing['address']??'')?></textarea></label>
        <label>PIN code (private, optional)<input name="pincode" inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" value="<?=e($branchEditing['pincode']??'')?>" placeholder="412201"></label>
        <div class="ananta-supplier-form-actions"><button type="submit" class="btn dark"><?=($branchEditing?'Resubmit branch for review':'Add branch for review')?></button><?php if($branchEditing):?><a class="btn outline" href="<?=e(url('/supplier#branches'))?>">Clear form</a><?php endif;?></div>
      </form>
    <?php endif;?>
    <div class="ananta-supplier-list" aria-label="Branch records">
    <?php foreach($branches as $branch):?>
      <article class="ananta-supplier-branch">
        <div><strong><?=e($branch['name'])?></strong><small><?=e($branch['city'])?><?=!empty($branch['pincode'])?' · '.e($branch['pincode']):''?></small></div>
        <div class="ananta-supplier-branch-actions"><span class="ananta-branch-state <?=e($branch['state']??'pending')?>"><?=e(ucfirst($branch['state']??'pending'))?></span>
          <?php if(tenant_can('branches.manage',$tenantId)):?><a href="<?=e(url('/supplier?branch='.$branch['id'].'#branches'))?>">Edit branch</a><?php endif;?></div>
      </article>
    <?php endforeach;?>
    <?php if(!$branches):?><p>No branches added yet. Register your first growing or dispatch location to begin.</p><?php endif;?>
    </div>
  </section>
  <?php if(tenant_can('offers.draft',$tenantId)):?>
  <section class="ananta-supplier-panel" id="offers">
    <div class="ananta-supplier-section-head"><div><p class="kicker">Catalog</p><h2><?=($editing?'Revise draft plant offer':'Submit a plant offer')?></h2></div><span class="ananta-offer-count">Review required</span></div>
    <p>Choose the nursery branch holding the stock. Draft offers do not appear to buyers until the platform approves both branch and offer.</p>
    <form class="ananta-supplier-form" method="post" action="<?=e(url('/supplier/offer/draft'))?>"><?=csrf_field()?>
      <input type="hidden" name="id" value="<?=e($editing['id']??'')?>">
      <label>Supplying branch<select name="branch_id" required><option value="">Choose branch</option>
      <?php foreach($branches as $branch):if(in_array(($branch['state']??''),['rejected','suspended'],true))continue;?>
        <option value="<?=e($branch['id'])?>" <?=($editing['branch_id']??'')===$branch['id']?'selected':''?>><?=e($branch['name'])?> · <?=e($branch['city'])?> (<?=e($branch['state']??'pending')?>)</option>
      <?php endforeach;?></select></label>
      <label>Canonical plant<select name="product_slug" required><option value="">Select plant variety</option><?php foreach(products_public() as $p):?>
        <option value="<?=e($p['slug'])?>" <?=($editingPlant['slug']??'')===$p['slug']?'selected':''?>><?=e($p['name'])?></option>
      <?php endforeach;?></select></label>
      <label>Pot size, height and grade<input name="label" maxlength="140" required value="<?=e($editing['label']??'')?>" placeholder="12 inch pot · 120–150 cm · Grade A"></label>
      <label>Indicative price (INR)<input type="number" min="1" max="10000000" name="price_inr" value="<?=isset($editing['price_inr'])?e((string)$editing['price_inr']):''?>" placeholder="Request quote when blank"></label>
      <label>Estimated available quantity<input type="number" min="0" max="1000000" name="quantity" value="<?=isset($editing['quantity'])?e((string)$editing['quantity']):''?>"></label>
      <div class="ananta-supplier-form-actions"><button type="submit" class="btn dark">Save draft for review</button><?php if($editing):?><a class="btn outline" href="<?=e(url('/supplier#offers'))?>">Clear form</a><?php endif;?></div>
    </form>
    <?php if(!$branches):?><p class="ananta-supplier-caption">You need a branch record before you can create a seller offer.</p><?php endif;?>
  </section>
  <?php endif;?>
  <section class="ananta-supplier-panel" id="inventory">
    <div class="ananta-supplier-section-head"><div><p class="kicker">Plant batches</p><h2>Inventory and availability</h2></div><span class="ananta-offer-count"><?=count($mine)?> offers</span></div>
    <p>Stock numbers are claims, not reservations. Any supplier stock update clears a previous platform-confirmed status until evidence is reviewed again.</p>
    <div class="ananta-supplier-list"><?php foreach($mine as $o):
      $plant=product_by_guid((string)($o['product_guid']??''));$av=marketplace_offer_public_status($o);
      $branch=$branchesById[(string)($o['branch_id']??'')]??null;?>
      <article class="ananta-supplier-offer">
        <div><strong><?=e($plant['name']??'Unavailable plant')?></strong>
          <small><?=e($o['label']??'')?></small>
          <small><?=e($branch['name']??'Legacy / unassigned branch')?> · <?=e($o['status']??'draft')?></small>
          <small><?=e($av['label'])?> · Last checked <?=e($o['checked_at']??'—')?></small>
          <?php if(($o['status']??'')==='draft'&&tenant_can('offers.draft',$tenantId)):?><a href="<?=e(url('/supplier?edit='.$o['id'].'#offers'))?>">Edit offer draft</a><?php endif;?>
        </div>
        <?php if(tenant_can('availability.report',$tenantId)):?>
        <form action="<?=e(url('/supplier/offer/report'))?>" method="post" class="ananta-supplier-report"><?=csrf_field()?>
          <input type="hidden" name="offer_id" value="<?=e($o['id'])?>">
          <label>Availability<select name="availability">
            <option value="unknown" <?=($o['availability']??'')==='unknown'?'selected':''?>>Check availability</option>
            <option value="reported" <?=($o['availability']??'')==='reported'?'selected':''?>>Supplier reported</option>
            <option value="unavailable" <?=($o['availability']??'')==='unavailable'?'selected':''?>>Unavailable</option>
          </select></label>
          <label>Quantity<input type="number" name="quantity" min="0" max="1000000" value="<?=isset($o['quantity'])?e((string)$o['quantity']):''?>"></label>
          <button type="submit" class="btn outline">Update stock</button>
        </form>
        <?php endif;?>
      </article>
    <?php endforeach; if(!$mine):?><p>No offers yet. Create a draft once a nursery branch has been added.</p><?php endif;?></div>
  </section>
  <p class="ananta-supplier-caption">Security boundary: you only see data for your active nursery membership. Published stock is still enquiry-only; no supplier payments are enabled.</p>
</section>
