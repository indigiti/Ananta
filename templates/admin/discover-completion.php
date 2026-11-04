<section class="admin-head">
  <div><p class="eyebrow">Ananta Discover · B8-G Completion</p><h1>Merchant catalogue reconciliation</h1>
  <p>Live Cloudways product and variant records compared with the immutable 376-SKU GitHub baseline. This dashboard never promotes an enquiry listing to a sale or claims Google approval.</p></div>
  <a class="btn outline" href="<?=e(url('/admin/discover/merchant'))?>">Merchant dashboard</a>
</section>
<section class="admin-metric-grid">
  <article><span>Baseline SKUs</span><strong><?=e((string)$recon['stats']['baseline'])?></strong><small>Immutable GitHub reference</small></article>
  <article><span>Live catalogue parents</span><strong><?=e((string)$recon['stats']['live'])?></strong><small>Private runtime products_all()</small></article>
  <article><span>Live pot-size SKUs</span><strong><?=e((string)$recon['stats']['live_variants'])?></strong><small>Not inferred from seed inventory</small></article>
  <article><span>Verified feed-ready SKUs</span><strong><?=e((string)$recon['stats']['feed_eligible'])?></strong><small>Local policy gate only</small></article>
</section>
<div class="ananta-b8-grid">
<section class="admin-panel">
<h2>Catalogue differences and gaps</h2><div class="ananta-b8-reasons">
<div><span>Baseline missing from runtime</span><strong><?=e((string)$recon['stats']['baseline_missing'])?></strong></div>
<div><span>Slug identity changed</span><strong><?=e((string)$recon['stats']['baseline_modified'])?></strong></div>
<div><span>New parents since baseline</span><strong><?=e((string)$recon['stats']['added'])?></strong></div>
<div><span>Enquiry-only parents</span><strong><?=e((string)$recon['stats']['enquiry_only'])?></strong></div>
<div><span>Parents with positive price</span><strong><?=e((string)$recon['stats']['priced'])?></strong></div>
<div><span>Parents with tracked stock</span><strong><?=e((string)$recon['stats']['tracked_stock'])?></strong></div>
<div><span>Approved hero images</span><strong><?=e((string)$recon['stats']['approved_hero'])?></strong></div>
</div>
<p>Baseline fingerprint: <code><?=e($recon['baseline_sha256'])?></code>. The comparison is read-only. Never replace Cloudways live stock with GitHub seed data.</p>
<a class="btn outline" href="<?=e(url('/admin/discover/completion/catalogue.csv'))?>">Export complete discrepancy CSV</a>
</section>
<section class="admin-panel">
<h2>Most common Merchant blockers</h2>
<div class="ananta-b8-reasons"><?php foreach(array_slice($recon['reason_counts'],0,16,true) as $name=>$count):?>
<div><span><?=e(str_replace('_',' ',$name))?></span><strong><?=e((string)$count)?></strong></div><?php endforeach;?></div>
<p>These are local conditions. Google Merchant Center may reject products for additional account, policy, image, landing-page or checkout reasons.</p>
</section>
</div>
<section class="admin-panel">
<h2>Merchant seller, delivery and returns sign-off</h2>
<p>Operator-controlled gate. Confirm actual legal seller, verified public domain, live shipping configuration and published live-plant returns before allowing any purchasable SKU into the external feed. Review does not connect Google or verify account status.</p>
<form method="post" action="<?=e(url('/admin/discover/completion/policy'))?>" class="ananta-b8-form-grid">
<?=csrf_field()?>
<label>Seller of record<input name="seller_name" maxlength="100" value="<?=e($policy['seller_name']??'Sanjay Nursery')?>" required></label>
<label>Public HTTPS shipping-policy URL<input name="shipping_url" type="url" required value="<?=e($policy['shipping_url']??'')?>" placeholder="https://example.com/shipping"></label>
<label>Public HTTPS returns-policy URL<input name="returns_url" type="url" required value="<?=e($policy['returns_url']??'')?>" placeholder="https://example.com/returns"></label>
<label>Evidence/ticket reference<input name="evidence" minlength="12" maxlength="200" required placeholder="Documented legal and operations sign-off"></label>
<div class="ananta-merchant-checks">
<?php foreach(['seller'=>'Seller identity','shipping'=>'Shipping and serviceability','returns'=>'Return and refund policy','domain'=>'Verified public merchant domain','checkout'=>'Purchasing/checkout path verified'] as $key=>$label):?>
<label><input type="checkbox" name="<?=$key?>_reviewed" value="yes" <?=!empty($policy[$key.'_reviewed'])?'checked':''?>> <?=e($label)?> confirmed by accountable operator</label>
<?php endforeach;?>
</div><button class="btn primary" type="submit">Save evidence and Merchant gate</button>
</form><p>Staging or test domains cannot be attested as the public merchant domain. All review changes are audit-logged in encrypted storage.</p>
</section>
<section class="admin-panel">
<h2>SKU identifier and stock review</h2>
<p>First update the actual price, tracked inventory, variant availability and approved photography through the normal catalogue controls. This form only records evidence and cannot invent those values. Expired stock reviews must be renewed within 48 hours.</p>
<form method="post" action="<?=e(url('/admin/discover/completion/sku-review'))?>" class="ananta-b8-form-grid">
<?=csrf_field()?>
<label>Exact live SKU<input name="sku" required maxlength="100" placeholder="Copy SKU from table below"></label>
<label>Brand displayed on product<input name="brand" value="Sanjay Nursery" required maxlength="90"></label>
<label>Identifier evidence<select name="identifiers"><option value="">Select actual identifier state</option><option value="none_verified">Verified: no GTIN or MPN exists</option><option value="gtin">GTIN issued for this item</option><option value="mpn">Manufacturer part number</option></select></label>
<label>GTIN / MPN value (if applicable)<input name="identifier_value" maxlength="70"></label>
<label>Source of proof<input name="evidence" required minlength="12" maxlength="240" placeholder="Batch/photo/stock count record"></label>
<label><input type="checkbox" name="stock_reviewed" value="yes"> I inspected the physical inventory record for this exact SKU</label>
<button class="btn primary" type="submit">Save SKU review</button></form>
</section>
<section class="admin-panel">
<div class="panel-head"><h2>Live product reconciliation</h2><span class="status-pill"><?=e((string)$recon['total_rows'])?> rows · page <?=e((string)$recon['page'])?> of <?=e((string)$recon['pages'])?></span></div>
<div style="overflow-x:auto">
<table class="admin-table" style="width:100%;min-width:800px">
<thead><tr><th>SKU</th><th>Plant</th><th>Base</th><th>Price</th><th>Stock</th><th>Merchant</th><th>Reasons</th></tr></thead>
<tbody><?php foreach($recon['rows'] as $item):?>
<tr><td><strong><?=e($item['sku'])?></strong><small><?=e($item['type'])?></small></td>
<td><?=e($item['name'])?></td><td><?=e(str_replace('_',' ',$item['baseline']))?></td>
<td><?=e((string)$item['price'])?> INR</td><td><?=e((string)$item['stock'])?> <?=$item['tracked']?'tracked':'untracked'?></td>
<td><?=$item['eligible']?'Local ready':'Blocked'?></td><td><?=e(implode(', ',array_slice($item['reasons'],0,7)))?></td></tr>
<?php endforeach;?></tbody></table></div>
<div class="panel-actions">
<?php if($recon['page']>1):?><a class="btn outline" href="<?=e(url('/admin/discover/completion?page='.($recon['page']-1)))?>">Previous</a><?php endif;?>
<?php if($recon['page']<$recon['pages']):?><a class="btn outline" href="<?=e(url('/admin/discover/completion?page='.($recon['page']+1)))?>">Next</a><?php endif;?>
</div>
</section>
<section class="admin-panel">
<h2>Google Merchant Center · processed-products API</h2>
<p>Optional read-only sync requires <code>ANANTA_MERCHANT_ACCOUNT_ID</code> and a content-scoped OAuth bearer token in Cloudways. It reads Google product processing results for country IN, never uploads or modifies a product.</p>
<div class="ananta-b8-reasons">
<div><span>Provider connection configuration</span><strong><?=$google['configured']?'Configured':'Missing credentials'?></strong></div>
<div><span>Last provider-verified read</span><strong><?=!empty($google['provider_verified'])?'Verified':'Never'?></strong></div>
<div><span>Last synced</span><strong><?=e($google['last']['synced_at']??'Not yet')?></strong></div>
<div><span>Processed products at Google</span><strong><?=e((string)($google['last']['totals']['google_processed']??0))?></strong></div>
<div><span>Approved for India free listings</span><strong><?=e((string)($google['last']['totals']['approved_in']??0))?></strong></div>
<div><span>Disapproved for India</span><strong><?=e((string)($google['last']['totals']['disapproved_in']??0))?></strong></div>
<div><span>Feed SKUs absent at Google</span><strong><?=e((string)($google['last']['totals']['feed_missing_in_google']??0))?></strong></div>
</div>
<form method="post" action="<?=e(url('/admin/discover/completion/google-sync'))?>"><?=csrf_field()?><button type="submit" class="btn primary">Fetch actual Google status (read-only)</button></form>
<p>Empty values are not approvals. A verified provider response is still not proof of product visibility in search results or that a Google feed source was configured.</p>
<?php if(!empty($google['last']['status_by_sku'])):?>
<h3>Recent Google item issues</h3><div class="ananta-b8-reasons">
<?php $i=0;foreach($google['last']['status_by_sku'] as $item):if(++$i>16)break;?>
<div><span><?=e($item['sku'])?> · <?=e($item['free_listings_in'])?></span>
<strong><?=e(implode(', ',array_map(static fn($v)=>$v['code'],array_slice($item['issues'],0,3))))?></strong></div>
<?php endforeach;?></div><?php endif;?>
</section>
<section class="admin-panel">
<h2>OpenAI product discovery · partner preparation</h2>
<p>The public AI and agent feeds remain discovery-oriented, including enquiry-only products clearly labelled as such. Approved direct shopping partners can prepare a separate <strong>Google-compatible CSV</strong> containing only validated, purchasable SKUs. No OpenAI account, SFTP upload, India approval or citation claim is made here.</p>
<a class="btn outline" href="<?=e(url('/admin/discover/completion/openai.csv'))?>">Export eligible OpenAI-compatible CSV (admin-only)</a>
<p>OpenAI partner onboarding, feed market support and external processing must be approved independently.</p>
</section>