<section class="admin-head"><div><p class="eyebrow">Ananta Connect · B8.1</p><h1>WhatsApp infrastructure</h1><p>Connect verified business senders and record contact consent without activating outbound traffic. Official Meta Cloud API only.</p></div><span class="status-pill">Provisioning only · Sending disabled</span></section>
<section class="admin-metric-grid">
 <article><span>Registered senders</span><strong><?=count($summary['accounts'])?></strong><small>Meta phone IDs mapped to one tenant each</small></article>
 <article><span>Consent records</span><strong><?=e((string)$summary['consent_total'])?></strong><small>Purpose-specific, encrypted</small></article>
 <article><span>Opted-out contacts</span><strong><?=e((string)$summary['opted_out'])?></strong><small>Not eligible for promotional messages</small></article>
 <article><span>Outbound messaging</span><strong>OFF</strong><small>B8.2 delivery queue and signed webhooks required</small></article>
</section>
<div class="ananta-b8-grid">
 <section class="admin-panel">
  <div class="panel-head"><h2>Register official Meta sender</h2><span class="status-pill">Admin only</span></div>
  <p>Credentials belong in protected Cloudways environment variables. Enter variable <em>names</em> below—never paste Meta tokens, verify secrets or app secrets into the dashboard.</p>
  <form method="post" action="<?=e(url('/admin/connect/account'))?>" class="stack-form">
   <?=csrf_field()?>
   <label>Sender owner / tenant<select name="tenant_id" required><option value="platform">Ananta platform sender</option>
   <?php foreach($suppliers as $supplier):if(($supplier['status']??'')!=='verified')continue;?>
     <option value="<?=e($supplier['id'])?>"><?=e($supplier['name'])?> — <?=e($supplier['city'])?></option>
   <?php endforeach;?></select></label>
   <label>Sender label<input name="name" maxlength="90" placeholder="Ananta official communications" required></label>
   <label>Meta Phone Number ID<input name="phone_number_id" inputmode="numeric" minlength="6" maxlength="30" required autocomplete="off"></label>
   <label>WhatsApp Business Account ID<input name="waba_id" inputmode="numeric" minlength="6" maxlength="30" required autocomplete="off"></label>
   <label>Access-token environment variable<input name="token_env" value="ANANTA_WA_TOKEN_MAIN" required pattern="ANANTA_WA_TOKEN_[A-Z][A-Z0-9_]{3,64}"></label>
   <label>App-secret environment variable<input name="secret_env" value="ANANTA_WA_SECRET_MAIN" required pattern="ANANTA_WA_SECRET_[A-Z][A-Z0-9_]{3,64}"></label>
   <label>Webhook verification variable<input name="verify_env" value="ANANTA_WA_VERIFY_MAIN" required pattern="ANANTA_WA_VERIFY_[A-Z][A-Z0-9_]{3,64}"></label>
   <button type="submit" class="btn primary">Save disabled sender configuration</button>
  </form>
 </section>
 <section class="admin-panel">
  <div class="panel-head"><h2>Account readiness</h2><span class="status-pill">No API calls</span></div>
  <p>These checks confirm only local configuration. They do not mean Meta approved the business or that live webhooks and message delivery work.</p>
  <?php foreach($summary['accounts'] as $account):?>
  <div class="ananta-b8-account">
   <strong><?=e($account['name'])?></strong><small><?=e($account['tenant_id']==='platform'?'Platform':$account['tenant_id'])?></small>
   <div class="ananta-b8-badges"><span>Access token: <?=$account['token_ready']?'Configured':'Missing'?></span><span>App secret: <?=$account['app_secret_ready']?'Configured':'Missing'?></span><span>Verify token: <?=$account['verify_token_ready']?'Configured':'Missing'?></span></div>
   <small>Provider connected: No · Send mode: Disabled</small>
  </div>
  <?php endforeach;?>
  <?php if(!$summary['accounts']):?><p>No sender registered. Configure a Meta Business App and WhatsApp Business Account before registering the sender here.</p><?php endif;?>
 </section>
</div>
<section class="admin-panel">
  <div class="panel-head"><h2>Purpose-specific consent evidence</h2><span class="status-pill">Protected register</span></div>
  <p>Only record permission you can substantiate. A generic enquiry does not automatically grant marketing consent. Future opt-outs must be enforced before sending.</p>
  <form method="post" action="<?=e(url('/admin/connect/consent'))?>" class="ananta-b8-form-grid">
   <?=csrf_field()?>
   <label>Tenant<select name="tenant_id" required><option value="platform">Ananta platform</option><?php foreach($suppliers as $supplier):if(($supplier['status']??'')==='verified'):?><option value="<?=e($supplier['id'])?>"><?=e($supplier['name'])?></option><?php endif;endforeach;?></select></label>
   <label>Contact phone (country code)<input name="phone" autocomplete="off" inputmode="tel" placeholder="919XXXXXXXXX" required></label>
   <label>Messaging purpose<select name="purpose"><option value="rfq_updates">RFQ updates</option><option value="service">Service messages</option><option value="marketing">Marketing</option></select></label>
   <label>Decision<select name="decision"><option value="opt_out">Opt out</option><option value="opt_in">Opt in with evidence</option></select></label>
   <label>Evidence source<select name="source"><option value="web_form">Web form</option><option value="signed_form">Signed form</option><option value="customer_message">Customer message</option><option value="manual_evidence">Manual evidence</option></select></label>
   <label>Evidence reference<input name="evidence" minlength="8" maxlength="180" placeholder="Form submission or signed reference ID" required></label>
   <button type="submit" class="btn primary">Record consent event</button>
  </form>
  <p class="ananta-b8-help">Contact numbers and evidence are encrypted at rest. Only hashed identifiers appear in the audit journal; exports and bulk sends are not enabled in B8.1.</p>
</section>