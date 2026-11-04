<section class="page-hero shell designos-hero">
  <p class="kicker">DesignOS · Style Finder</p>
  <h1>Start with how you want<br><em>the room to feel.</em></h1>
  <p>Five considered choices turn into a style direction, material palette and ranked product shortlist. The scoring is deterministic and uses the live Sanjay Nursery catalogue.</p>
</section>
<section class="section section-tight shell style-finder-v2">
  <form method="post" action="<?=e(url('/style-finder'))?>" class="style-form" data-style-wizard>
    <?=csrf_field()?>
    <div class="style-progress"><span>01 Space</span><span>02 Mood</span><span>03 Material</span><span>04 Priorities</span><span>05 Budget</span></div>
    <fieldset><legend>What are you designing?</legend><div class="design-choice-grid"><?php foreach(designos_rooms() as $key=>$label):?><label><input type="radio" name="room" value="<?=e($key)?>" <?=($result['input']['room']??'')===$key?'checked':''?> required><span><?=e($label)?></span></label><?php endforeach;?></div></fieldset>
    <fieldset><legend>How should it feel?</legend><div class="design-choice-grid"><?php foreach(designos_moods() as $key=>$label):?><label><input type="radio" name="mood" value="<?=e($key)?>" <?=($result['input']['mood']??'')===$key?'checked':''?> required><span><?=e($label)?></span></label><?php endforeach;?></div></fieldset>
    <fieldset><legend>Choose a material direction.</legend><div class="design-choice-grid material-choices"><?php foreach(designos_material_directions() as $key=>$label):?><label><input type="radio" name="material" value="<?=e($key)?>" <?=($result['input']['material']??'')===$key?'checked':''?> required><span><?=e($label)?></span></label><?php endforeach;?></div></fieldset>
    <fieldset><legend>What matters most?</legend><p class="form-hint">Choose one or more.</p><div class="design-choice-grid compact"><?php foreach(designos_priorities() as $key=>$label):?><label><input type="checkbox" name="priorities[]" value="<?=e($key)?>" <?=in_array($key,(array)($result['input']['priorities']??[]),true)?'checked':''?>><span><?=e($label)?></span></label><?php endforeach;?></div></fieldset>
    <fieldset><legend>Indicative product budget.</legend><div class="design-choice-grid compact"><?php foreach(designos_budgets() as $key=>$row):?><label><input type="radio" name="budget" value="<?=e($key)?>" <?=($result['input']['budget']??'')===$key?'checked':''?> required><span><?=e($row['label'])?></span></label><?php endforeach;?></div></fieldset>
    <button class="btn primary" type="submit">Reveal my design direction</button>
  </form>
</section>
<?php if($result): $style=designos_styles()[$result['profile_key']]??null; ?>
<section class="section section-soft style-result-section" id="result"><div class="shell">
  <div class="style-result-card">
    <div><p class="kicker">Your DesignOS profile · <?=e((string)$result['score'])?>% match</p><h2><?=e($result['profile_name'])?></h2><p><?=e($result['copy'])?></p><div class="palette-row"><?php foreach($result['palette'] as $tone):?><span><?=e($tone)?></span><?php endforeach;?></div>
      <div class="inline-actions"><a class="btn dark" href="<?=e(url('/shop'))?>">Explore collection</a><?php if(customer_logged_in()):?><form method="post" action="<?=e(url('/account/style-profile/save'))?>" class="inline-save-style"><?=csrf_field()?><input name="label" placeholder="Optional profile name"><button class="btn outline" type="submit">Save style profile</button></form><?php else:?><a class="btn outline" href="<?=e(url('/account/login'))?>">Sign in to save</a><?php endif;?></div>
    </div>
    <aside><p class="kicker">Room brief</p><dl><div><dt>Room</dt><dd><?=e(designos_rooms()[$result['input']['room']]??'')?></dd></div><div><dt>Mood</dt><dd><?=e(designos_moods()[$result['input']['mood']]??'')?></dd></div><div><dt>Material</dt><dd><?=e(designos_material_directions()[$result['input']['material']]??'')?></dd></div><div><dt>Budget</dt><dd><?=e(designos_budgets()[$result['input']['budget']]['label']??'')?></dd></div></dl></aside>
  </div>
  <div class="section-head design-rec-head"><div><p class="kicker">Ranked for your brief</p><h2>Pieces to start with.</h2></div><p>Recommendations combine room fit, material affinity, selected priorities and budget—not paid placement.</p></div>
  <div class="product-grid product-grid-3"><?php foreach($result['recommendations'] as $r):$p=$r['product'];include __DIR__.'/_product-card.php';endforeach;?></div>
  <?php if(customer_logged_in()):?><div class="room-planner-cta"><div><p class="kicker">Turn the profile into a plan</p><h3>Lay the pieces into your actual room.</h3><p>Create room dimensions, attach this style profile and test product positions against the room boundary and budget.</p></div><a class="btn primary" href="<?=e(url('/account/rooms/new'))?>">Create room plan</a></div><?php endif;?>
</div></section>
<?php endif;?>
<section class="section shell consult-card"><div><p class="kicker">Project Enquiries</p><h2>Need something the catalogue<br><em>cannot solve?</em></h2></div><div><p>Move from the Style Finder into our controlled bespoke workflow for custom dimensions, revisions, approval, deposit and Design Freeze.</p><a class="btn outline" href="<?=e(url('/shop'))?>">Choose a starting product</a></div></section>
