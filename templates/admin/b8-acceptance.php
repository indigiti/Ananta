<section class="admin-head"><div><p class="eyebrow">Ananta · B8.5</p><h1>Pilot acceptance & recovery</h1><p>Machine-verified local safety checks and operator-reported field evidence are distinct. Meta delivery, Merchant approval and mobile QA are never inferred from CI.</p></div><span class="status-pill">Staging only · Production blocked</span></section>
<section class="admin-metric-grid">
<article><span>Machine checks</span><strong><?=e((string)$snapshot['checks_pass'])?> / <?=e((string)$snapshot['checks_total'])?></strong><small>Local integration and runtime checks</small></article>
<article><span>Uncertain sends</span><strong><?=e((string)$snapshot['queue']['needs_review'])?></strong><small>Manual Meta reconciliation required</small></article>
<article><span>Merchant-eligible SKUs</span><strong><?=e((string)$snapshot['feed']['eligible'])?></strong><small>Local feed evidence only</small></article>
<article><span>Field evidence</span><strong><?=count($snapshot['operator_evidence'])?> / <?=count($snapshot['evidence_required'])?></strong><small>Operator attested, not provider verified</small></article></section>
<div class="ananta-b8-grid">
<section class="admin-panel"><h2>Machine checks</h2><div class="ananta-b8-reasons">
<?php foreach($snapshot['machine_checks'] as $name=>$passed):?><div><span><?=e(str_replace('_',' ',$name))?></span><strong><?=$passed?'PASS':'REVIEW'?></strong></div><?php endforeach;?></div>
<p>Sample taken <?=e($snapshot['at'])?>. Passing does not establish external or browser acceptance.</p></section>
<section class="admin-panel"><h2>Messages and CRM</h2><div class="ananta-b8-reasons">
<?php foreach($snapshot['crm'] as $name=>$count):?><div><span><?=e($name)?></span><strong><?=e((string)$count)?></strong></div><?php endforeach;?>
<?php foreach($snapshot['queue'] as $name=>$count):?><div><span><?=e(str_replace('_',' ',$name))?></span><strong><?=e((string)$count)?></strong></div><?php endforeach;?></div></section>
</div>
<section class="admin-panel"><h2>Performance sample · not an SLA</h2><div class="ananta-b8-reasons">
<div><span>Local feed audit, single run</span><strong><?=e((string)$snapshot['performance_sample']['feed_audit_ms_single_sample'])?> ms</strong></div>
<div><span>Encrypted messaging ledger</span><strong><?=e((string)$snapshot['performance_sample']['message_ledger_bytes'])?> bytes</strong></div>
<div><span>Encrypted CRM ledger</span><strong><?=e((string)$snapshot['performance_sample']['crm_ledger_bytes'])?> bytes</strong></div>
</div><p>A single local sample is not a p95 latency, user Core Web Vitals or throughput test.</p></section>
<section class="admin-panel"><h2>Field acceptance evidence</h2>
<p>Record the test run, screenshot report or issue reference. This is a human attestation, not independent proof from Meta, Google, a payment gateway or a real device. Do not paste access tokens or personal conversation content.</p>
<div class="ananta-b8-reasons"><?php foreach($snapshot['evidence_required'] as $step=>$label):?>
<div><span><?=e($label)?></span><strong><?=isset($snapshot['operator_evidence'][$step])?'Attested':'Pending'?></strong></div>
<?php endforeach;?></div>
<form class="ananta-b8-form-grid" method="post" action="<?=e(url('/admin/b8/acceptance/evidence'))?>">
<?=csrf_field()?>
<label>Acceptance item<select name="step"><?php foreach($snapshot['evidence_required'] as $step=>$label):?><option value="<?=e($step)?>"><?=e($label)?></option><?php endforeach;?></select></label>
<label>Traceable evidence reference<input name="reference" required minlength="12" maxlength="300" placeholder="Verified test run URL or secure issue ID"></label>
<button class="btn primary" type="submit">Record operator attestation</button>
</form></section>
<section class="admin-panel"><h2>Encrypted backup and isolated restore drill</h2>
<p>Run on Cloudways in the private PHP directory: <code>php src/b8_pilot_cli.php backup</code>, followed by <code>php src/b8_pilot_cli.php verify /private/path/to/backup</code>.</p>
<p>Verification checks copied encrypted objects and decrypts them without modifying live data. An off-server copy and disaster-recovery procedure must be checked independently.</p>
<p><a href="<?=e(url('/admin/connect'))?>">WhatsApp settings</a> · <a href="<?=e(url('/admin/discover/merchant'))?>">Merchant diagnostics</a> · <a href="<?=e(url('/admin/system'))?>">System Health</a></p>
</section>