<section class="shell ananta-source-wrap">
  <div class="ananta-source-hero"><p class="kicker">Ananta Trade · Multi-plant procurement</p>
    <h1>One BOQ.<br><em>Several nursery options.</em></h1>
    <p>Prepare a catalogue-matched list, review every quantity, and request line-by-line quotations from verified nursery teams. Nothing is published or purchased automatically.</p>
  </div>
  <div class="ananta-source-grid">
    <div class="ananta-source-card">
      <p class="kicker">Step 1 · Upload and validate</p><h2>Review your plant requirements.</h2>
      <p class="ananta-source-disclaimer">CSV upload is the supported structured extraction method in this release. AI-assisted OCR/PDF extraction remains disabled until a human-review pipeline and evidence checks are ready.</p>
      <p>Required CSV columns: <code>product_slug,quantity,specification</code>. Maximum 30 unique plant/grade rows, 128 KB. Choose plant slugs from the Ananta catalogue.</p>
      <a class="ananta-seller-cta" href="<?=e(url('/sourcing/boq/template.csv'))?>">Download CSV template <?=ui_icon('external')?></a>
      <form action="<?=e(url('/sourcing/boq/preview'))?>" method="post" enctype="multipart/form-data" class="ananta-source-form" style="margin-top:20px">
        <?=csrf_field()?>
        <label>Choose BOQ CSV file<input type="file" name="boq_file" accept=".csv,text/csv" required></label>
        <button type="submit">Validate and preview BOQ <?=ui_icon('chevron-right')?></button>
      </form>
      <?php if($preview):?>
      <hr>
      <p class="kicker">Step 2 · Buyer verification</p>
      <h2><?=count($preview['lines'])?> matched plant lines</h2>
      <p>Confirm each plant, specification and quantity below. The preview expires in 15 minutes. To correct anything, edit and upload the CSV again.</p>
      <div class="ananta-boq-table-wrap"><table class="ananta-boq-table"><thead><tr><th>Plant matched</th><th>Size and grade</th><th>Quantity</th></tr></thead><tbody>
      <?php foreach($preview['lines'] as $item):?><tr><td><?=e($item['plant_name'])?></td><td><?=e($item['specification'])?></td><td><?=e((string)$item['quantity'])?></td></tr><?php endforeach;?>
      </tbody></table></div>
      <form action="<?=e(url('/sourcing/boq/submit'))?>" method="post" class="ananta-source-form" style="margin-top:24px">
        <?=csrf_field()?>
        <input type="hidden" name="preview_token" value="<?=e($preview['token'])?>">
        <label>Delivery destination<input name="destination" maxlength="140" placeholder="Pune, Maharashtra" required></label>
        <label>Additional terms and packaging notes<textarea name="notes" maxlength="600" rows="3"></textarea></label>
        <fieldset class="ananta-source-suppliers">
          <legend>Choose 1–5 verified, onboarded nurseries</legend>
          <?php foreach($suppliers as $nursery):?><label><input type="checkbox" name="supplier_ids[]" value="<?=e($nursery['id'])?>">
            <span><strong><?=e($nursery['name'])?></strong><small><?=e($nursery['city'])?></small></span></label><?php endforeach;?>
          <?php if(!$suppliers):?><p>No verified nursery currently has an active supplier inbox.</p><?php endif;?>
        </fieldset>
        <label class="ananta-source-check"><input type="checkbox" name="share_contact" value="yes"><span>Allow invited nurseries to view my buyer-account contact information (optional).</span></label>
        <label class="ananta-source-check"><input type="checkbox" name="consent" value="yes" required><span>I have reviewed all BOQ lines and consent to share this requirement with the selected nurseries.</span></label>
        <button type="submit" <?=!$suppliers?'disabled':''?>>Confirm and request line-by-line quotes <?=ui_icon('chevron-right')?></button>
        <p class="ananta-source-disclaimer">Supplier replies are estimates, not reserved inventory. Any purchase-order draft you approve later remains non-binding until separately contracted.</p>
      </form>
      <?php endif;?>
    </div>
    <aside class="ananta-source-aside"><p class="kicker">A controlled sourcing workflow</p><h3>Never confuse a quote with available inventory.</h3>
      <ol><li>Buyer confirms catalogue-matched BOQ lines</li><li>Verified nurseries quote every line</li><li>Supplier revisions are preserved privately</li><li>Buyer chooses a revision for a non-binding PO draft</li></ol>
      <a class="ananta-seller-cta" href="<?=e(url('/account/sourcing'))?>">View your requests <?=ui_icon('chevron-right')?></a>
    </aside>
  </div>
</section>