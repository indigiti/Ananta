<section class="admin-head">
  <div><p class="eyebrow">Ananta Discover · Agentic Commerce</p>
  <h1>ACP integration & readiness</h1>
  <p>Native product-feed compliance, session protocol, delegated-payment boundaries and durable order-event diagnostics. OpenAI partner approval and live checkout must be independently verified.</p></div>
  <span class="status-pill">Staging · Checkout blocked</span>
</section>
<section class="admin-metric-grid">
<article><span>ACP-ready product rows</span><strong><?=e((string)$report['feed']['valid_partner_feed_rows'])?></strong><small>Eligible reviewed commercial SKUs</small></article>
<article><span>Feed candidates rejected</span><strong><?=e((string)$report['feed']['rejected_from_partner_feed'])?></strong><small>Local native-schema validation</small></article>
<article><span>Staging sessions</span><strong><?=e((string)$report['checkout']['sessions'])?></strong><small>Encrypted and limited to 30 minutes</small></article>
<article><span>Live agentic checkout</span><strong>OFF</strong><small>PSP, legal and stock reservation gates</small></article>
</section>
<div class="ananta-b8-grid">
<section class="admin-panel">
<h2>ACP.1 · Native product feed</h2>
<p>Records come exclusively from the existing merchant-approved catalogue. Enquiry-only nursery products are never labeled purchasable. Checkout eligibility remains <code>false</code> until partner certification.</p>
<div class="ananta-b8-reasons">
<div><span>Native schema</span><strong>OpenAI product file</strong></div>
<div><span>Target country</span><strong>IN</strong></div>
<div><span>Currency</span><strong>INR</strong></div>
<div><span>External OpenAI approval</span><strong>Unverified</strong></div>
</div>
<?php if($report['feed']['errors']):?>
<h3>Validation reasons</h3><div class="ananta-b8-reasons">
<?php foreach($report['feed']['errors'] as $reason=>$count):?><div><span><?=e(str_replace('_',' ',$reason))?></span><strong><?=e((string)$count)?></strong></div><?php endforeach;?></div>
<?php endif;?>
<p><a class="btn primary" href="<?=e(url('/admin/acp/products.csv'))?>">Export compliant ACP native CSV</a></p>
<p>This export fails if no eligible products exist. No feed registration or OpenAI upload is triggered.</p>
</section>
<section class="admin-panel">
<h2>ACP.2 · Checkout-session API</h2>
<p>Implements signed/authenticated create, retrieve, update, cancel and gated complete endpoints. Payloads use the 2026-04-17 schema with minor currency amounts. Staging quote replies do not reserve nursery stock.</p>
<div class="ananta-b8-reasons">
<div><span>API version</span><strong><?=e($report['checkout']['spec_version'])?></strong></div>
<div><span>Key and HMAC configured</span><strong><?=$report['checkout']['staging_api_configured']?'Yes':'No'?></strong></div>
<div><span>Staging API enabled</span><strong><?=$report['checkout']['staging_api_enabled']?'Yes':'No'?></strong></div>
<div><span>Remote completion</span><strong>Disabled</strong></div>
</div>
<p>Partner endpoint (after Cloudways authentication setup): <code>/ananta/acp/checkout_sessions</code>. No customer cookie or website CSRF tokens are accepted in place of API authentication.</p>
</section></div>
<section class="admin-panel">
<h2>ACP.3 · Payment and order event certification</h2>
<div class="ananta-b8-reasons">
<?php foreach($report['payments']['gates'] as $gate=>$passed):?><div><span><?=e(str_replace('_',' ',$gate))?></span><strong><?=$passed?'Verified':'Pending'?></strong></div><?php endforeach;?>
<div><span>Stripe Shared Payment Token test adapter</span><strong>Available (test-only)</strong></div>
<div><span>Payment capture and binding orders</span><strong>Blocked</strong></div>
<div><span>Order event backlog</span><strong><?=e((string)$report['order_webhooks']['queued'])?></strong></div>
</div>
<p>Order webhooks use a signed encrypted queue. No payment is captured and no order is issued from an agentic checkout until a transactional reservation and approved PSP flow are certified.</p>
<p><a href="<?=e(url('/admin/discover/completion'))?>">Review actual eligible inventory</a> · <a href="<?=e(url('/admin/b8/acceptance'))?>">Review external acceptance evidence</a></p>
</section>