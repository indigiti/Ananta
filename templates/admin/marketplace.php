<section class="admin-head"><div><p class="eyebrow">Ananta · File-first supplier marketplace</p><h1>Nursery suppliers &amp; offers</h1><p>Admin-curated supplier offers for the controlled pilot. Verify the supplier before publishing; published offers are enquiry-only and never enter the legacy Sanjay Nursery cart.</p></div><a class="btn outline" href="<?=e(url('/shop'))?>" target="_blank" rel="noopener">View catalogue</a></section>
<?php $suppliers=(array)($store['suppliers']??[]);$offers=(array)($store['offers']??[]);$products=products_public();$branches=supplier_branches_all();$editSupplier=marketplace_supplier_by_id((string)($_GET['supplier']??''))??[];$editOffer=marketplace_offer_by_id((string)($_GET['offer']??''))??[];$editPlant=isset($editOffer['product_guid'])?product_by_guid((string)$editOffer['product_guid']):null; ?>
<section class="admin-metric-grid">
  <article><span>Suppliers</span><strong><?=count($suppliers)?></strong><small><?=count(array_filter($suppliers,fn($s)=>($s['status']??'')==='verified'))?> verified</small></article>
  <article><span>Offers</span><strong><?=count($offers)?></strong><small><?=count(array_filter($offers,fn($o)=>($o['status']??'')==='published'))?> published</small></article>
  <article><span>Checkout</span><strong>0</strong><small>External sellers are enquiry-only</small></article>
  <article><span>Storage</span><strong>JSON</strong><small>One-server pilot</small></article>
</section>
<section class="admin-panel"><div class="panel-head"><h2>Register or update a supplier</h2><span class="status-pill">Platform admin only</span></div>
  <p>Only mark a nursery verified after checking its identity and obtaining permission to publish its name, location and contact number. For updates, select an existing supplier.</p>
  <form class="stack-form" action="<?=e(url('/admin/marketplace/suppliers/save'))?>" method="post"><?=csrf_field()?>
    <label>Existing nursery (optional)<select name="id"><option value="">Create new nursery</option><?php foreach($suppliers as $s):?><option value="<?=e($s['id'])?>" <?=($editSupplier['id']??'')===$s['id']?'selected':''?>><?=e($s['name'])?> — <?=e($s['city'])?></option><?php endforeach;?></select></label>
    <label>Nursery business name <input name="name" maxlength="120" required autocomplete="organization" value="<?=e($editSupplier['name']??'')?>" placeholder="Registered nursery name"></label>
    <label>City / location <input name="city" maxlength="100" required value="<?=e($editSupplier['city']??'')?>" placeholder="Pune, Maharashtra"></label>
    <label>Public WhatsApp number (country code) <input name="phone" inputmode="tel" maxlength="18" value="<?=e($editSupplier['phone']??'')?>" placeholder="91XXXXXXXXXX"></label>
    <label>Verification status<select name="status"><option value="draft" <?=($editSupplier['status']??'draft')==='draft'?'selected':''?>>Draft — not publicly listed</option><option value="verified" <?=($editSupplier['status']??'draft')==='verified'?'selected':''?>>Verified — contact details permitted for publication</option><option value="suspended" <?=($editSupplier['status']??'draft')==='suspended'?'selected':''?>>Suspended — hide all offers</option></select></label>
    <button class="btn primary" type="submit">Save nursery</button> <?php if($editSupplier):?><a class="btn outline" href="<?=e(url('/admin/marketplace'))?>">Create new</a><?php endif;?>
  </form>
</section>
<section class="admin-panel"><div class="panel-head"><h2>RFQ routing overview</h2><span class="status-pill">Buyer sourcing</span></div>
<?php $sourcingStats=sourcing_admin_summary();?>
<p>Private, consent-based multi-nursery requests. Only verified selected suppliers can read and quote each invitation. No external order or payment is created.</p>
<div class="admin-metrics"><div><span>Total RFQs</span><strong><?=e((string)$sourcingStats['total'])?></strong></div><div><span>Open requests</span><strong><?=e((string)$sourcingStats['open'])?></strong></div><div><span>Supplier invitations</span><strong><?=e((string)$sourcingStats['invites'])?></strong></div><div><span>Quotes returned</span><strong><?=e((string)$sourcingStats['quoted'])?></strong></div></div>
</section>
<section class="admin-panel"><div class="panel-head"><h2>Invite verified nursery staff</h2><span class="status-pill">Private onboarding</span></div>
<p>Invitations expire after 24 hours. Members choose their own passwords and receive only tenant-scoped permissions. Share the one-time link privately.</p>
<?php if($inviteUrl=flash('tenant_invite_link')):?><div class="ananta-admin-invite" role="status"><strong>Copy invitation link now</strong><input type="text" value="<?=e($inviteUrl)?>" readonly aria-label="One-time supplier invitation" onclick="this.select()"><small>This link is shown only once. Do not put it in a public post.</small></div><?php endif;?>
<form class="stack-form" method="post" action="<?=e(url('/admin/marketplace/invite'))?>"><?=csrf_field()?>
<label>Verified nursery<select name="supplier_id" required><option value="">Select nursery</option><?php foreach($suppliers as $s):if(($s['status']??'')!=='verified')continue;?><option value="<?=e($s['id'])?>"><?=e($s['name'])?> — <?=e($s['city'])?></option><?php endforeach;?></select></label>
<label>Member email<input type="email" name="email" maxlength="254" autocomplete="off" required></label>
<label>Access<select name="role"><option value="owner">Owner</option><option value="manager">Manager</option><option value="inventory">Inventory</option><option value="viewer">Viewer</option></select></label>
<button class="btn primary" type="submit">Create invitation</button></form></section>
<section class="admin-panel"><div class="panel-head"><h2>Supplier register</h2><span class="status-pill"><?=count($suppliers)?> nurseries</span></div>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Nursery</th><th>City</th><th>State</th><th>Offers</th><th>Updated</th></tr></thead><tbody>
<?php foreach($suppliers as $s):?><tr><td><strong><?=e($s['name'])?></strong><small><?=e($s['id'])?></small></td><td><?=e($s['city'])?></td><td><span class="status-pill <?=($s['status']??'')==='verified'?'ok':'warn'?>"><?=e($s['status']??'draft')?></span></td><td><?=count(array_filter($offers,fn($o)=>($o['supplier_id']??'')===$s['id']))?></td><td><?=e($s['updated_at']??'—')?> <a class="text-link" href="<?=e(url('/admin/marketplace?supplier='.$s['id']))?>">Edit</a></td></tr><?php endforeach;?>
<?php if(!$suppliers):?><tr><td colspan="5"><p>No suppliers registered. Published offers will stay empty until a nursery is verified.</p></td></tr><?php endif;?>
</tbody></table></div></section>

<section class="admin-panel" id="branch-verification">
  <div class="panel-head"><h2>Branch verification queue</h2><span class="status-pill"><?=count(array_filter($branches,static fn($b)=>($b['state']??'pending')==='pending'))?> pending</span></div>
  <p>Supplier verification is not branch verification. Verify each nursery location separately and record an internal evidence reference. Suspended or edited branches automatically disappear from public offers until reapproved.</p>
  <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Supplier / branch</th><th>Location</th><th>Review state</th><th>Platform review</th></tr></thead><tbody>
  <?php foreach($branches as $branch):$owner=marketplace_supplier_by_id((string)$branch['tenant_id']);?>
    <tr><td><strong><?=e($owner['name']??'Unknown supplier')?></strong><small><?=e($branch['name'])?></small></td>
      <td><?=e($branch['city'])?><small><?=e($branch['pincode']??'')?> · <?=e($branch['address']??'')?></small></td>
      <td><span class="status-pill <?=($branch['state']??'')==='approved'?'ok':'warn'?>"><?=e($branch['state']??'pending')?></span><small>Last reviewed <?=e($branch['reviewed_at']??'—')?></small></td>
      <td><form method="post" action="<?=e(url('/admin/marketplace/branches/review'))?>" class="ananta-branch-review-form"><?=csrf_field()?>
        <input type="hidden" name="tenant_id" value="<?=e($branch['tenant_id'])?>">
        <input type="hidden" name="branch_id" value="<?=e($branch['id'])?>">
        <label>Decision<select name="decision"><option value="approved">Approve</option><option value="rejected">Reject</option><option value="suspended">Suspend</option></select></label>
        <label>Private evidence reference<input type="text" name="evidence" required minlength="8" maxlength="200" placeholder="Inspection / call reference"></label>
        <button class="btn outline" type="submit">Save review</button>
      </form></td>
    </tr>
  <?php endforeach;?>
  <?php if(!$branches):?><tr><td colspan="4">No nursery branch submissions yet.</td></tr><?php endif;?>
  </tbody></table></div>
</section>
<section class="admin-panel"><div class="panel-head"><h2>Publish nursery-specific plant offers</h2><span class="status-pill">Enquiry only</span></div>
<p>Every offer belongs to a real supplier and a canonical plant variety. A reported quantity is not guaranteed stock. “Recently confirmed” requires supporting evidence and expires into “Check availability” after seven days.</p>
<form class="stack-form" action="<?=e(url('/admin/marketplace/offers/save'))?>" method="post"><?=csrf_field()?>
  <label>Existing offer (optional)<select name="id"><option value="">Create new offer</option><?php foreach($offers as $o): $p=product_by_guid((string)($o['product_guid']??''));?><option value="<?=e($o['id'])?>" <?=($editOffer['id']??'')===$o['id']?'selected':''?>><?=e($p['name']??'Missing plant')?> · <?=e($o['label']??'')?> · <?=e($o['id'])?></option><?php endforeach;?></select></label>
  <label>Supplier<select name="supplier_id" required><option value="">Choose nursery</option><?php foreach($suppliers as $s):?><option value="<?=e($s['id'])?>" <?=($editOffer['supplier_id']??'')===$s['id']?'selected':''?>><?=e($s['name'])?> (<?=e($s['status']??'draft')?>)</option><?php endforeach;?></select></label>
  <label>Supplying branch<select name="branch_id"><option value="">Legacy offer / unassigned branch</option><?php foreach($branches as $branch):$owner=marketplace_supplier_by_id((string)$branch['tenant_id']);?><option value="<?=e($branch['id'])?>" <?=($editOffer['branch_id']??'')===$branch['id']?'selected':''?>><?=e($owner['name']??'Unknown')?> · <?=e($branch['name'])?> (<?=e($branch['state']??'pending')?>)</option><?php endforeach;?></select></label>
  <label>Canonical plant<select name="product_slug" required><option value="">Choose plant</option><?php foreach($products as $p):?><option value="<?=e($p['slug'])?>" <?=($editPlant['slug']??'')===$p['slug']?'selected':''?>><?=e($p['name'])?></option><?php endforeach;?></select></label>
  <label>Variant specification<input name="label" maxlength="140" required value="<?=e($editOffer['label']??'')?>" placeholder="12 inch pot · 120–150 cm · Grade A"></label>
  <label>Indicative seller price in ₹ (optional)<input type="number" name="price_inr" min="1" max="10000000" step="1" value="<?=isset($editOffer['price_inr'])?e((string)$editOffer['price_inr']):''?>" placeholder="Subject to reconfirmation"></label>
  <label>Reported stock quantity (optional)<input type="number" name="quantity" min="0" max="1000000" step="1" value="<?=isset($editOffer['quantity'])?e((string)$editOffer['quantity']):''?>" placeholder="Reported, not reserved"></label>
  <label>Availability<select name="availability"><option value="unknown" <?=($editOffer['availability']??'unknown')==='unknown'?'selected':''?>>Unknown / check supplier</option><option value="reported" <?=($editOffer['availability']??'unknown')==='reported'?'selected':''?>>Supplier-reported</option><option value="confirmed" <?=($editOffer['availability']??'unknown')==='confirmed'?'selected':''?>>Confirmed with evidence</option><option value="unavailable" <?=($editOffer['availability']??'unknown')==='unavailable'?'selected':''?>>Currently unavailable</option></select></label>
  <label>Private stock evidence reference (required for confirmed stock)<input name="evidence_ref" maxlength="200" value="<?=e($editOffer['evidence_ref']??'')?>" placeholder="Internal call/inspection reference"></label>
  <label>Publication<select name="status"><option value="draft" <?=($editOffer['status']??'draft')==='draft'?'selected':''?>>Draft (hidden)</option><option value="published" <?=($editOffer['status']??'draft')==='published'?'selected':''?>>Published (requires verified supplier)</option><option value="withdrawn" <?=($editOffer['status']??'draft')==='withdrawn'?'selected':''?>>Withdrawn</option></select></label>
  <button class="btn primary" type="submit">Save offer</button> <?php if($editOffer):?><a class="btn outline" href="<?=e(url('/admin/marketplace'))?>">Create new</a><?php endif;?>
</form></section>
<section class="admin-panel"><div class="panel-head"><h2>Offer register</h2><span class="status-pill"><?=count($offers)?> offers</span></div>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Plant / specification</th><th>Supplier</th><th>Price</th><th>Availability</th><th>Publish</th></tr></thead><tbody>
<?php foreach($offers as $o):$p=product_by_guid((string)($o['product_guid']??''));$s=marketplace_supplier_by_id((string)($o['supplier_id']??''));$av=marketplace_offer_public_status($o);?><tr><td><strong><?=e($p['name']??'Missing plant')?></strong><small><?=e($o['label']??'')?></small></td><td><?=e($s['name']??'Missing supplier')?><small><?=e(supplier_branch_by_id((string)($o['branch_id']??''))['name']??'Legacy / unassigned')?></small></td><td><?=isset($o['price_inr'])?money((int)$o['price_inr']):'Request quote'?></td><td><?=e($av['label'])?><small><?=e($o['checked_at']??'—')?></small></td><td><?=e($o['status']??'draft')?> <a class="text-link" href="<?=e(url('/admin/marketplace?offer='.$o['id']))?>">Edit</a></td></tr><?php endforeach;?>
<?php if(!$offers):?><tr><td colspan="5"><p>No nursery offers yet. Create a supplier before creating an offer.</p></td></tr><?php endif;?>
</tbody></table></div></section>
