<section class="shell ananta-auth-shell" aria-labelledby="ananta-auth-heading">
  <div class="ananta-auth-story">
    <p class="kicker">Ananta · NurserySetu SupplierOS</p>
    <h1 id="ananta-auth-heading">Your nursery.<br><em>A bigger opportunity.</em></h1>
    <p>Manage plant offers, report availability and grow with a network built for professional nurseries. Every business keeps its own private workspace.</p>
    <ul class="ananta-auth-benefits">
      <li><?=ui_icon('check')?> Your inventory, your nursery and your team</li>
      <li><?=ui_icon('check')?> Branch-specific offers and sourcing enquiries</li>
      <li><?=ui_icon('shield')?> Access by verified invitation only</li>
    </ul>
  </div>
  <div class="ananta-auth-panel">
    <p class="kicker">Supplier sign in</p>
    <h2>Welcome back.</h2>
    <p>Use your verified supplier account to continue.</p>
    <form method="post" action="<?=e(url('/supplier/login'))?>" class="ananta-supplier-form">
      <?=csrf_field()?>
      <label for="supplier-login-email">Work email
        <input id="supplier-login-email" type="email" name="email" placeholder="name@nursery.com" autocomplete="username" inputmode="email" maxlength="254" required>
      </label>
      <label for="supplier-login-password">Password
        <input id="supplier-login-password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
      </label>
      <button type="submit">Sign in to SupplierOS <?=ui_icon('chevron-right')?></button>
    </form>
    <p class="ananta-supplier-caption">Not registered yet? Contact the Ananta platform administrator to receive a private supplier invitation.</p>
    <div class="ananta-auth-security"><?=ui_icon('shield')?> Private, tenant-scoped workspace</div>
  </div>
</section>