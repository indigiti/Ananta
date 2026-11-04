<section class="admin-head"><div><p class="eyebrow">Growth Foundation · v1.4.0</p><h1>Growth Center</h1><p>Measurement, search verification, local/merchant readiness, migration protection and content-quality controls in one browser-managed surface.</p></div><a class="btn outline" href="<?=e(url('/sitemap.xml'))?>" target="_blank">Open sitemap ↗</a></section>

<div class="admin-metrics growth-metrics"><?php foreach($metrics as $label=>$value):?><div><strong><?=e((string)$value)?></strong><span><?=e(ucwords(str_replace('_',' ',$label)))?></span></div><?php endforeach;?></div>

<div class="admin-grid two growth-admin-grid">
<section class="admin-card"><h2>Measurement & discovery configuration</h2><p class="admin-muted">These identifiers are public website configuration, not API secrets. Leave a field blank until the corresponding property/account is ready.</p>
<form method="post" action="<?=e(url('/admin/growth/settings'))?>" class="stack"><?=csrf_field()?>
<label class="checkline"><input type="checkbox" name="analytics_enabled" value="1" <?=!empty($settings['analytics_enabled'])?'checked':''?>> Enable analytics loader after visitor consent</label>
<label class="checkline"><input type="checkbox" name="cookie_consent_enabled" value="1" <?=!empty($settings['cookie_consent_enabled'])?'checked':''?>> Show analytics consent controls</label>
<div class="admin-grid two"><label>GA4 Measurement ID<input name="ga4_measurement_id" value="<?=e($settings['ga4_measurement_id'])?>" placeholder="G-XXXXXXXXXX"></label><label>GTM Container ID<input name="gtm_container_id" value="<?=e($settings['gtm_container_id'])?>" placeholder="GTM-XXXXXXX"></label></div>
<label>Google Search Console verification token<input name="google_site_verification" value="<?=e($settings['google_site_verification'])?>" placeholder="Paste only the meta-tag content value"></label>
<label>Bing Webmaster verification token<input name="bing_site_verification" value="<?=e($settings['bing_site_verification'])?>"></label>
<div class="admin-grid two"><label>Merchant Center ID<input name="merchant_center_id" value="<?=e($settings['merchant_center_id'])?>" inputmode="numeric"></label><label>Google Business Place ID<input name="google_business_place_id" value="<?=e($settings['google_business_place_id'])?>"></label></div>
<label>Google Business Profile URL<input name="google_business_profile_url" value="<?=e($settings['google_business_profile_url'])?>" placeholder="https://..."></label>
<label>Organization logo URL<input name="logo_url" value="<?=e($settings['logo_url'])?>" placeholder="https://..."></label>
<label>Default social sharing image URL<input name="default_social_image" value="<?=e($settings['default_social_image'])?>" placeholder="https://..."></label>
<label>Official social/profile URLs <small>one per line</small><textarea name="same_as" rows="5"><?=e(implode("\n",(array)$settings['same_as']))?></textarea></label>
<label>Editorial publish-quality threshold<input type="number" min="50" max="90" name="content_quality_publish_threshold" value="<?=e((string)$settings['content_quality_publish_threshold'])?>"></label>
<button class="btn primary" type="submit">Save growth settings</button></form></section>

<section class="admin-card"><h2>Readiness board</h2><div class="release-list growth-readiness">
<?php foreach($readiness as $row):?><div><span><?=e($row['label'])?><small><?=e($row['detail'])?></small></span><b class="<?=$row['state']==='ready'?'ok':($row['state']==='action'?'warn':'')?>"><?=e(strtoupper($row['state']))?></b></div><?php endforeach;?>
</div><p class="admin-muted">Merchant eligibility intentionally stays at zero until a product has real price, stock/availability and a functional purchase path.</p></section>
</div>

<div class="admin-grid two growth-admin-grid">
<section class="admin-card"><h2>Migration redirect importer</h2><p>Paste one redirect per line as <code>/old-path,/new-path,301</code>. Existing redirect sources are skipped rather than overwritten.</p><form method="post" action="<?=e(url('/admin/growth/redirects/import'))?>" class="stack"><?=csrf_field()?><textarea name="redirects" rows="10" placeholder="/old-aglaonema,/collections/aglaonema,301&#10;/old-contact,/contact,301"></textarea><button class="btn outline" type="submit">Import redirects</button></form><p><strong><?=count($redirects)?></strong> redirects currently stored.</p></section>
<section class="admin-card"><div class="admin-card-head"><div><h2>404 migration monitor</h2><p>Prioritise frequently hit missing URLs after launch.</p></div><?php if($missing):?><form method="post" action="<?=e(url('/admin/growth/404/clear'))?>" data-confirm="Clear the 404 migration journal?"><?=csrf_field()?><button class="btn outline" type="submit">Clear log</button></form><?php endif;?></div>
<?php if($missing):?><div class="table-wrap"><table><thead><tr><th>Missing path</th><th>Hits</th><th>Last seen</th></tr></thead><tbody><?php foreach($missing as $r):?><tr><td><code><?=e($r['path'])?></code></td><td><?=e((string)$r['hits'])?></td><td><?=e(substr((string)$r['last_seen'],0,16))?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><p class="empty-state">No missing public routes have been recorded yet.</p><?php endif;?></section>
</div>

<section class="admin-card"><h2>Indexable discovery hubs</h2><p>Clean collection URLs are generated from canonical categories and plant families. Query-string search/filter pages remain discovery tools; these hubs are the indexable landing pages.</p><div class="growth-hub-grid"><?php foreach($hubs as $hub):?><a href="<?=e(url('/collections/'.$hub['slug']))?>" target="_blank"><strong><?=e($hub['label'])?></strong><small><?=e($hub['type'])?> · /collections/<?=e($hub['slug'])?></small></a><?php endforeach;?></div></section>
