<?php $ingest=$ingest??null; $aiReady=$aiReady??false; $catalogue=$catalogue??products_public(); ?>
<section class="shell ananta-source-wrap">
  <div class="ananta-source-hero"><p class="kicker">Ananta Trade · Multi-plant procurement</p>
    <h1>One BOQ.<br><em>Several nursery options.</em></h1>
    <p>Prepare a catalogue-matched list, review every quantity, and request line-by-line quotations from verified nursery teams. Nothing is published or purchased automatically.</p>
  </div>
  <div class="ananta-source-grid">
    <div class="ananta-source-card">
      <p class="kicker">Step 1 · Extract and validate</p><h2>Review your plant requirements.</h2>
      <p class="ananta-source-disclaimer">Upload an Excel sheet or a text-based PDF. Ananta extracts candidate rows, but <strong>you decide</strong> which catalogue plants, quantities and grades are correct. Original files are not retained.</p>
      <div class="ananta-ingest-choice">
        <div><strong>Excel XLSX</strong><small>Sheet1 · Plant name or slug / quantity / specification · 30 rows maximum</small></div>
        <div><strong>Text-based PDF</strong><small>Server must support PDF text extraction · scanned images need separate OCR</small></div>
      </div>
      <form action="<?=e(url('/sourcing/boq/ingest'))?>" method="post" enctype="multipart/form-data" class="ananta-source-form ananta-ingest-upload">
        <?=csrf_field()?>
        <label for="boq-intake-file">Select a BOQ document (.xlsx or .pdf)
          <input id="boq-intake-file" type="file" name="intake_file" accept=".xlsx,.pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/pdf" required>
        </label>
        <label class="ananta-source-check">
          <input type="checkbox" name="ai_consent" value="yes" <?=!$aiReady?'disabled':''?>>
          <span>For a <strong>text-based PDF only</strong>, I authorize sending its extracted text to the configured AI provider to suggest rows. This is optional, may incur provider costs, and requires my review. <?=!$aiReady?'AI is not configured; local extraction remains available.':''?></span>
        </label>
        <button type="submit">Extract rows for my review <?=ui_icon('chevron-right')?></button>
      </form>
      <?php if($ingest):?>
        <div class="ananta-ingest-review">
          <p class="kicker">Step 2 · Required human verification</p>
          <h3><?=count($ingest['rows'])?> proposed lines · <?=e(strtoupper($ingest['origin']))?></h3>
          <p>These are extraction suggestions, <strong>not submitted RFQs</strong>. Select an active catalogue plant for every line. Correct quantity and grade using your original document. No supplier is notified until you confirm later.</p>
          <form action="<?=e(url('/sourcing/boq/review'))?>" method="post" class="ananta-source-form">
            <?=csrf_field()?>
            <input type="hidden" name="preview_token" value="<?=e($ingest['token'])?>">
            <?php foreach($ingest['rows'] as $i=>$candidate):?>
              <fieldset class="ananta-ingest-row">
                <legend>Source row <?=e((string)($i+1))?> · <?=e($candidate['match_confidence'])?></legend>
                <p class="ananta-ingest-evidence"><strong>From document:</strong> <?=e($candidate['source_ref'])?></p>
                <p class="ananta-ingest-original">Original plant: <strong><?=e($candidate['original_plant'])?></strong> · Quantity: <?=e($candidate['original_quantity'])?> · Grade: <?=e($candidate['original_specification'])?></p>
                <div class="ananta-ingest-fields">
                  <label>Correct catalogue plant<select name="rows[<?=e($candidate['id'])?>][product_slug]" required>
                    <option value="">Choose a matched plant</option>
                    <?php foreach($catalogue as $plant):?>
                    <option value="<?=e($plant['slug'])?>" <?=($candidate['product_slug']===$plant['slug'])?'selected':''?>><?=e($plant['name'])?></option>
                    <?php endforeach;?>
                  </select></label>
                  <label>Correct quantity<input type="number" name="rows[<?=e($candidate['id'])?>][quantity]" min="1" max="50000" value="<?=e($candidate['quantity'])?>" required></label>
                  <label>Correct size / grade<input type="text" name="rows[<?=e($candidate['id'])?>][specification]" maxlength="180" value="<?=e($candidate['specification'])?>" required></label>
                </div>
              </fieldset>
            <?php endforeach;?>
            <label class="ananta-source-check"><input type="checkbox" name="confirm_review" value="yes" required>
              <span>I have checked <strong>every line</strong> against the source document, including quantities and grades. AI suggestions are not treated as facts.</span>
            </label>
            <button type="submit">Confirm my reviewed BOQ <?=ui_icon('chevron-right')?></button>
          </form>
        </div>
      <?php endif;?>
      <details class="ananta-ingest-legacy">
        <summary>Use the original CSV template instead</summary>
        <p>Strict CSV columns: <code>product_slug,quantity,specification</code>. Maximum 30 unique catalogue lines and 128 KB. All quantities and specifications must already be correct.</p>
        <a class="ananta-seller-cta" href="<?=e(url('/sourcing/boq/template.csv'))?>">Download CSV template <?=ui_icon('external')?></a>
      </details>
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