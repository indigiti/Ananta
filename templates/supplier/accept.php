<section class="shell ananta-auth-shell" aria-labelledby="ananta-auth-heading">
  <div class="ananta-auth-story">
    <p class="kicker">Ananta · Supplier invitation</p>
    <h1 id="ananta-auth-heading">Grow with<br><em>Ananta.</em></h1>
    <p>Activate your secure nursery workspace. Supplier membership is invite-only and every nursery keeps its records private.</p>
    <ul class="ananta-auth-benefits"><li><?=ui_icon('shield')?> One-time invitation, valid for 24 hours</li><li><?=ui_icon('check')?> Individual member identity and permissions</li><li><?=ui_icon('check')?> Platform-reviewed branch and stock information</li></ul>
  </div>
  <div class="ananta-auth-panel">
    <p class="kicker">Supplier onboarding</p>
    <?php if(!$invitation):?>
      <h2>Invitation unavailable.</h2>
      <p>This link is invalid, expired or already used. Request another private invitation from the Ananta platform team.</p>
      <a class="ananta-seller-cta" href="<?=e(url('/supplier/login'))?>">Back to supplier sign in <?=ui_icon('chevron-right')?></a>
    <?php else:?>
      <h2>Activate your account.</h2>
      <p>Complete your invitation for the nursery below.</p>
      <div class="ananta-invite-details"><strong><?=e($invitation['supplier_name'])?></strong><span><?=e(ucfirst($invitation['role']))?> access · <?=e($invitation['email'])?></span></div>
      <form method="post" action="<?=e(url('/supplier/accept'))?>" class="ananta-supplier-form">
        <?=csrf_field()?>
        <input type="hidden" name="token" value="<?=e($token)?>">
        <label for="supplier-activate-name">Full name<input id="supplier-activate-name" type="text" name="name" placeholder="Your name" autocomplete="name" maxlength="100" required></label>
        <label for="supplier-activate-password">Password (12 characters minimum)<input id="supplier-activate-password" type="password" name="password" placeholder="Create a secure password" autocomplete="new-password" minlength="12" required></label>
        <button type="submit">Activate supplier access <?=ui_icon('chevron-right')?></button>
      </form>
      <p class="ananta-supplier-caption">Already have an Ananta supplier account? Enter its current password to join this additional nursery.</p>
    <?php endif;?>
    <div class="ananta-auth-security"><?=ui_icon('shield')?> Confidential supplier onboarding</div>
  </div>
</section>