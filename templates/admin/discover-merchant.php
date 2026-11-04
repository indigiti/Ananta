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