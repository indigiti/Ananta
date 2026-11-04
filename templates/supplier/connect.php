<section class="shell ananta-supplier-workspace">
 <div class="ananta-supplier-top"><div><p class="kicker">NurserySetu · Ananta Connect</p><h1>WhatsApp readiness.</h1><p>Your nursery's business sender and contact-consent evidence. B8.2 offers a signed and tenant-scoped CRM inbox; message sending requires explicit administrator activation and consent.</p></div><a class="btn outline" href="<?=e(url('/supplier'))?>">Back to workspace</a></div>
 <div class="ananta-supplier-panel">
  <h2>WhatsApp Business account</h2>
  <?php if($sender):?>
    <p><strong><?=e($sender['name'])?></strong> · Sender registration <?=($sender['configured']?'configured':'missing')?></p>
    <p>Credentials: <?=$sender['token_ready']&&$sender['app_secret_ready']&&$sender['verify_token_ready']?'Environment references ready':'Some environment secrets missing'?></p>
  <?php else:?><p>Your nursery does not have an assigned official Meta sender. Ask the platform administrator to verify and register your WhatsApp Business Account.</p><?php endif;?>
  <p><strong>Outbound messaging is controlled.</strong> Sender activation, purpose-specific opt-in, approved templates and the Cloudways kill switch must all permit a send.</p>
 <a class="btn dark" href="<?=e(url('/supplier/connect/inbox'))?>">View your nursery inbox</a>
 </div>
 <?php if(in_array($supplier['role'],['owner','manager'],true)):?>
 <div class="ananta-supplier-panel">
  <h2>Record a customer's messaging preference</h2>
  <p>Enter consent only when the contact explicitly agreed and you have documentary evidence. An opt-out must be respected.</p>
  <form method="post" action="<?=e(url('/supplier/connect/consent'))?>" class="ananta-supplier-form">
   <?=csrf_field()?>
   <label>Contact international phone<input name="phone" inputmode="tel" maxlength="20" required placeholder="91XXXXXXXXXX"></label>
   <label>Purpose<select name="purpose"><option value="rfq_updates">RFQ updates</option><option value="service">Service</option><option value="marketing">Marketing</option></select></label>
   <label>Decision<select name="decision"><option value="opt_out">Opt out</option><option value="opt_in">Verified opt-in</option></select></label>
   <label>Consent evidence source<select name="source"><option value="web_form">Web form</option><option value="signed_form">Signed form</option><option value="customer_message">Customer message</option><option value="manual_evidence">Manual evidence</option></select></label>
   <label>Evidence reference<input name="evidence" minlength="8" maxlength="180" placeholder="Traceable submission or consent ID" required></label>
   <button class="btn dark" type="submit">Record consent evidence</button>
  </form>
 </div>
 <?php endif;?>
</section>