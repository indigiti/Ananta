<section class="page-hero shell compact"><p class="kicker">Project Enquiries</p><h1>Turn a configured piece into a made-for-you proposal.</h1><p>Share the space, dimensions and changes you need. Our staging workflow keeps every design revision, approval and deposit decision traceable.</p></section>
<section class="section section-tight shell bespoke-request-grid">
  <div class="bespoke-source-card"><img src="<?=e(image_url($product['image']))?>" alt="<?=e($product['name'])?>"><div><p class="kicker">Starting piece</p><h2><?=e($product['name'])?></h2><p><?=e($product['short'])?></p><div class="price-line"><span>Current configured basis</span><strong><?=money($sourcePrice)?></strong></div><?php if($saved):?><small>Saved configuration: <?=e($saved['label'])?></small><?php endif;?></div></div>
  <form class="account-panel commerce-form bespoke-request-form" method="post" action="<?=e(url('/bespoke/request'))?>">
    <?=csrf_field()?><input type="hidden" name="slug" value="<?=e($product['slug'])?>"><input type="hidden" name="configuration_guid" value="<?=e($saved['guid']??'')?>">
    <div><p class="kicker">Project brief</p><h2>What should change?</h2></div>
    <label class="span-2">Requirement<textarea name="brief" rows="6" required placeholder="Example: make this shelf 130 cm tall, retain the walnut finish, add a closed drawer at the bottom and allow cable management."></textarea></label>
    <label>Space / room<input name="space" maxlength="100" placeholder="Living room · entryway · bedroom"></label>
    <label>Target dimensions<input name="target_dimensions" maxlength="120" placeholder="W 90 × H 130 × D 24 cm"></label>
    <label>Indicative budget<input name="budget" maxlength="80" placeholder="₹15,000–₹20,000"></label>
    <div class="bespoke-process span-2"><span>1 · Request</span><span>2 · Design revisions</span><span>3 · Approve</span><span>4 · Deposit</span><span>5 · Design freeze</span></div>
    <button class="btn dark" type="submit">Submit bespoke request</button>
  </form>
</section>
