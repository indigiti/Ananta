<section class="shell ananta-source-wrap">
  <div class="ananta-source-hero"><p class="kicker">Ananta Trade · Supplier network</p><h1>One requirement.<br><em>More nursery options.</em></h1><p>Send one plant requirement to up to five verified nurseries. Compare their responses privately, without making a purchase commitment.</p></div>
  <div class="ananta-source-grid">
  <section class="ananta-source-card" aria-labelledby="source-form-title">
    <p class="kicker">New sourcing request</p><h2 id="source-form-title">Tell nurseries what you need.</h2>
    <form method="post" action="<?=e(url('/sourcing'))?>" class="ananta-source-form">
      <?=csrf_field()?>
      <label>Plant or variety
        <select name="product_slug" required><option value="">Select a plant</option>
        <?php foreach($products as $plant):?><option value="<?=e($plant['slug'])?>" <?=($selectedSlug??'')===$plant['slug']?'selected':''?>><?=e($plant['name'])?></option><?php endforeach;?></select>
      </label>
      <div class="ananta-source-cols">
        <label>Quantity<input type="number" name="quantity" value="10" min="1" max="50000" required></label>
        <label>Delivery destination<input name="destination" maxlength="140" placeholder="Pune, Maharashtra" required></label>
      </div>
      <label>Size, pot, grade and other specifications<input name="specification" maxlength="180" placeholder="12 inch pot · 100–120 cm · Grade A" required></label>
      <label>Additional notes (optional)<textarea name="notes" maxlength="600" rows="3" placeholder="Delivery requirements, preferred timeline or special instructions"></textarea></label>
      <fieldset class="ananta-source-suppliers"><legend>Select up to five verified nurseries</legend>
        <p>Invited nurseries can view this plant requirement. A verified profile does not guarantee live stock.</p>
        <?php foreach($suppliers as $nursery):?>
          <label><input type="checkbox" name="supplier_ids[]" value="<?=e($nursery['id'])?>" <?=($selectedSupplier??'')===$nursery['id']?'checked':''?>>
            <span><strong><?=e($nursery['name'])?></strong><small><?=e($nursery['city'])?></small></span></label>
        <?php endforeach;?>
        <?php if(!$suppliers):?><p>No verified nurseries are available to invite yet.</p><?php endif;?>
      </fieldset>
      <label class="ananta-source-check"><input type="checkbox" name="share_contact" value="yes"><span>Allow the selected nurseries to see my account name, email and phone. Without this, they can respond only inside Ananta.</span></label>
      <label class="ananta-source-check"><input type="checkbox" name="consent" value="yes" required><span>I agree to share this plant requirement with the nurseries selected above for the purpose of receiving quotations.</span></label>
      <button class="btn dark" type="submit" <?=!$suppliers?'disabled':''?>>Send request to selected nurseries <?=ui_icon('chevron-right')?></button>
      <p class="ananta-source-disclaimer">Requests and responses are indicative. No payment, order or stock reservation is created.</p>
    </form>
  </section>
  <aside class="ananta-source-aside">
    <div><p class="kicker">How it works</p><h3>A clearer path to wholesale plants.</h3><ol><li>Select a plant, size and quantity</li><li>Invite the nurseries you choose</li><li>See independent quotes in your account</li><li>Confirm details directly before purchasing</li></ol></div>
    <a class="ananta-seller-cta" href="<?=e(url('/account/sourcing'))?>">Your sourcing requests <?=ui_icon('chevron-right')?></a>
  </aside>
  </div>
</section>