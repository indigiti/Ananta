<section class="admin-head"><div><p class="eyebrow">Ananta Discover · B8.1</p><h1>Google Shopping readiness</h1><p>SKU-level eligibility checks grounded in real retail inventory and approved photography. This screen is not a Merchant Center approval report.</p></div><span class="status-pill">External submission disabled</span></section>
<section class="admin-metric-grid">
 <article><span>Retail SKU candidates</span><strong><?=e((string)$report['candidates'])?></strong><small>Catalogue parents and variant records</small></article>
 <article><span>Feed-eligible SKUs</span><strong><?=e((string)$report['eligible'])?></strong><small>Locally eligible, pending Google review</small></article>
 <article><span>Blocked SKUs</span><strong><?=e((string)$report['blocked'])?></strong><small>Requires attention or enquiry-only</small></article>
 <article><span>Feed items</span><strong><?=e((string)$report['feed_rows'])?></strong><small>No external submission yet</small></article>
</section>
<div class="ananta-b8-grid">
 <section class="admin-panel">
  <div class="panel-head"><h2>Eligibility blockers</h2></div>
  <?php if($report['reason_counts']):?><div class="ananta-b8-reasons">
   <?php foreach($report['reason_counts'] as $reason=>$total):?><div><span><?=e(ucwords(str_replace('_',' ',$reason)))?></span><strong><?=e((string)$total)?></strong></div><?php endforeach;?>
  </div><?php else:?><p>No local eligibility problems detected in active catalogue records.</p><?php endif;?>
 </section>
 <section class="admin-panel">
  <div class="panel-head"><h2>AI and organic search readiness</h2></div>
  <div class="ananta-b8-reasons">
   <div><span>Public catalogue products</span><strong><?=e((string)$ai['public_products'])?></strong></div>
   <div><span>Approved primary images</span><strong><?=e((string)$ai['approved_hero_images'])?></strong></div>
   <div><span>Botanical names present</span><strong><?=e((string)$ai['botanical_names'])?></strong></div>
   <div><span>SEO descriptions</span><strong><?=e((string)$ai['seo_descriptions'])?></strong></div>
  </div>
  <p class="ananta-b8-help">Google indexing, Google Shopping approval, ChatGPT references and AI citations depend on third-party crawlers, eligibility and ranking. Nothing here guarantees placement.</p>
 </section>
</div>
<section class="admin-panel">
 <div class="panel-head"><h2>Product-level audit</h2><a href="<?=e(url('/feeds/google-merchant.xml'))?>" class="btn outline" target="_blank" rel="noopener">View Merchant XML</a></div>
 <p>Only checkout-ready first-party products can enter the Merchant XML. Marketplace nursery RFQs remain independently discoverable but never impersonate a retail checkout.</p>
 <div class="ananta-b8-table-wrap"><table class="admin-table"><thead><tr><th>SKU</th><th>Product</th><th>Type</th><th>Eligibility</th><th>Reason</th></tr></thead><tbody>
 <?php foreach($report['items'] as $item):?><tr>
  <td><code><?=e((string)$item['sku'])?></code></td><td><a href="<?=e($item['url'])?>" target="_blank" rel="noopener"><?=e($item['product'])?></a></td>
  <td><?=e($item['type'])?></td><td><span class="ananta-b8-status <?=$item['eligible']?'is-ready':'is-blocked'?>"><?=$item['eligible']?'Eligible':'Blocked'?></span></td>
  <td><?=e($item['reasons']?implode(', ',array_map(static fn($x)=>str_replace('_',' ',$x),$item['reasons'])):'—')?></td>
 </tr><?php endforeach;?></tbody></table></div>
</section>
<section class="admin-panel"><h2>Launch gates</h2>
 <p>Before external submission: verify the Merchant Center owner, seller of record, shipping and returns, checkout-path availability, product photography rights, Search Console ownership, and domain approval.</p>
 <p><strong>Merchant feed:</strong> <code><?=e(url('/feeds/google-merchant.xml'))?></code></p>
 <p><strong>AI products:</strong> <code><?=e(url('/feeds/ai-products.json'))?></code> · <strong>Sitemap:</strong> <code><?=e(url('/sitemap.xml'))?></code></p>
</section>
<section class="admin-panel">
 <div class="panel-head"><h2>B8.4 · Google and AI distribution diagnostics</h2><span class="status-pill">Scheduled XML · no automated Google submission</span></div>
 <p>The hosted XML is the supported feed method. Register its HTTPS URL in the verified Merchant Center primary data source; Google fetches it on its own schedule. A local feed refresh only records a fingerprint and never implies listing approval.</p>
 <div class="ananta-b8-reasons">
 <div><span>Current feed items</span><strong><?=e((string)$distribution['current']['sku_count'])?></strong></div>
 <div><span>Feed and schema consistency</span><strong><?=$distribution['current']['valid']?'Pass':'Needs review'?></strong></div>
 <div><span>Last recorded audit</span><strong><?=e($distribution['last']['at']??'Never')?></strong></div>
 <div><span>External Merchant approval</span><strong>Not verified</strong></div>
 </div>
 <?php if($distribution['current']['issues']):?>
 <h3>Local distribution issues</h3><div class="ananta-b8-reasons"><?php foreach($distribution['current']['issues'] as $issue=>$count):?><div><span><?=e(str_replace('_',' ',$issue))?></span><strong><?=e((string)$count)?></strong></div><?php endforeach;?></div>
 <?php endif;?>
 <form method="post" action="<?=e(url('/admin/discover/distribution/refresh'))?>">
 <?=csrf_field()?><button class="btn primary" type="submit">Record fresh feed and schema audit</button></form>
 <p class="ananta-b8-help">Merchant XML SHA-256: <code><?=e($distribution['current']['feed_sha256'])?></code>. Snapshot digests allow operators to detect feed changes and reconcile submitted SKU counts.</p>
</section>
<section class="admin-panel">
 <div class="panel-head"><h2>Search Console · read-only integration</h2><span class="status-pill"><?=$distribution['search_console_configured']?'Credentials configured':'Not connected'?></span></div>
 <?php if($distribution['gsc']):?>
 <div class="ananta-b8-reasons">
  <div><span>Property</span><strong><?=e($distribution['gsc']['property'])?></strong></div>
  <div><span>Clicks</span><strong><?=e((string)$distribution['gsc']['clicks'])?></strong></div>
  <div><span>Impressions</span><strong><?=e((string)$distribution['gsc']['impressions'])?></strong></div>
  <div><span>Last API read</span><strong><?=e($distribution['gsc']['synced_at'])?></strong></div>
 </div>
 <?php else:?><p>No Google Search Console Search Analytics API response has been received.</p><?php endif;?>
 <form method="post" action="<?=e(url('/admin/discover/distribution/gsc-sync'))?>">
 <?=csrf_field()?><button class="btn primary" type="submit">Refresh verified Google Search Console metrics</button></form>
 <p class="ananta-b8-help">Requires a read-only OAuth access token in Cloudways environment and ANANTA_GSC_PROPERTY matching this storefront's HTTPS URL prefix. This is not a Search Console ownership verification or token-refresh implementation.</p>
</section>
<section class="admin-panel">
 <div class="panel-head"><h2>AI search and rich result readiness</h2></div>
 <div class="ananta-b8-reasons">
  <div><span>Public AI-feed products</span><strong><?=e((string)$distribution['ai']['public_products'])?></strong></div>
  <div><span>Canonical record mismatches</span><strong><?=e((string)$distribution['ai']['canonical_mismatches'])?></strong></div>
  <div><span>Missing botanical names</span><strong><?=e((string)$distribution['ai']['missing_botanical_names'])?></strong></div>
  <div><span>Missing approved image URLs</span><strong><?=e((string)$distribution['ai']['missing_images'])?></strong></div>
 </div>
 <p><a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google Rich Results Test</a> · <a href="https://validator.schema.org/" target="_blank" rel="noopener">Schema Markup Validator</a>. Automated Google rich-result validation is not claimed; test live public product URLs in those official tools.</p>
 <p>AI discovery and search indexing are independently controlled by search systems. Public feeds and structured data improve eligibility, not guaranteed rankings or citations.</p>
</section>

<section class="admin-panel"><h2>Release acceptance and recovery</h2>
<p>Local catalogue and schema checks are not evidence of Merchant Center approval or live product indexing.</p>
<a class="btn outline" href="<?=e(url('/admin/b8/acceptance'))?>">View B8.5 field acceptance checklist</a></section>