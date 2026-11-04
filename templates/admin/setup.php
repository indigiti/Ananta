<section class="admin-auth-card setup-card">
  <p class="kicker">Web-only first run</p>
  <h1>Initialize Admin OS</h1>
  <p>No SSH, terminal, environment-variable editing or setup-key file access is required. This browser session temporarily owns the installer; protected encryption, webhook and signing keys are generated automatically and stored outside the public web root.</p>
  <?php if(!empty($setupClaim['locked'])): ?>
    <div class="admin-errors"><strong>Setup is already active in another browser.</strong><br>For security, the first setup browser keeps a 30-minute installation lock. Retry after that session expires, or continue in the browser that started setup.</div>
  <?php else: ?>
    <div class="setup-security-strip">
      <span><?=ui_icon('shield')?> Protected key generation</span>
      <span><?=ui_icon('settings')?> Web-managed configuration</span>
      <span><?=ui_icon('check')?> No console commands</span>
    </div>
    <form class="admin-form" action="<?=e(url('/admin/setup'))?>" method="post">
      <?=csrf_field()?>
      <input type="hidden" name="_setup_claim" value="<?=e((string)($setupClaim['token']??''))?>">
      <label>Administrator name<input name="name" required autocomplete="name"></label>
      <label>Email<input name="email" type="email" required autocomplete="email"></label>
      <label>Password<input name="password" type="password" minlength="12" required autocomplete="new-password"><small>Minimum 12 characters. Use a unique administrator password.</small></label>
      <button class="btn dark" type="submit"><?=ui_icon('shield')?> Create administrator & secure installation</button>
    </form>
    <p class="admin-note">Deployment note: complete this page immediately after upload and before exposing an uninitialized staging URL publicly. Once the administrator exists, the installer closes automatically.</p>
  <?php endif; ?>
</section>
