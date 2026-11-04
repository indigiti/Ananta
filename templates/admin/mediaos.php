<section class="admin-head"><div><p class="kicker">Plant Photography OS · v<?=e($GLOBALS['app']['version'])?></p><h1>Product photography & media approval</h1><p>Assign real nursery photography by semantic plant role, record the nursery batch/date, write accessible alt text and approve rights before an image can support Merchant eligibility.</p></div><a class="btn outline" href="<?=e(url('/admin/media'))?>">Upload images</a></section>
<div class="admin-note"><strong>Commerce photo gate:</strong> production-ready photography requires four approved, rights-cleared roles: catalogue hero, full plant, foliage close-up and pot/scale reference. Nursery-batch and lifestyle shots are strongly recommended but not fabricated.</div>
<div class="mediaos-list">
<?php foreach($products as $p): $entry=mediaos_product((string)$p['guid']); $complete=mediaos_completeness($p); ?>
<details class="admin-card mediaos-product">
  <summary><span><strong><?=e($p['name'])?></strong><small><?=e($p['sku'])?> · <?=e(category_label($p['category']))?></small></span><span><?=$complete['approved']?> approved · <?=$complete['production_ready']?'photo ready':'needs '.count($complete['missing']).' required shot(s)'?></span></summary>
  <form method="post" action="<?=e(url('/admin/mediaos/save'))?>" class="mediaos-form">
    <?=csrf_field()?> <input type="hidden" name="product_guid" value="<?=e($p['guid'])?>">
    <div class="mediaos-role-grid">
    <?php foreach(mediaos_roles() as $role=>$label): $r=$entry['roles'][$role]??[]; $required=in_array($role,mediaos_required_roles(),true); ?>
      <fieldset>
        <legend><?=e($label)?> <?=$required?'<small>Required</small>':''?></legend>
        <label>File<select name="file[<?=e($role)?>]"><option value="">— not assigned —</option><?php foreach($files as $m):?><option value="<?=e($m['name'])?>" <?=($r['file']??'')===$m['name']?'selected':''?>><?=e($m['name'])?></option><?php endforeach;?></select></label>
        <label>Alt text<input name="alt[<?=e($role)?>]" value="<?=e($r['alt']??'')?>" maxlength="180" placeholder="<?=e(mediaos_safe_alt($p,$role))?>"></label>
        <div class="form-two"><label>Status<select name="status[<?=e($role)?>]"><?php foreach(['staging','approved','retired'] as $v):?><option <?=$v===($r['status']??'staging')?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label><label>Rights<select name="rights[<?=e($role)?>]"><?php foreach(['unverified','owned','licensed','generated'] as $v):?><option <?=$v===($r['rights']??'unverified')?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label></div>
        <div class="form-two"><label>Nursery batch<input name="batch_code[<?=e($role)?>]" value="<?=e($r['batch_code']??'')?>" placeholder="SEP26-A01"></label><label>Shot date<input type="date" name="shot_date[<?=e($role)?>]" value="<?=e($r['shot_date']??'')?>"></label></div>
        <label>Caption / proof note<input name="caption[<?=e($role)?>]" value="<?=e($r['caption']??'')?>" maxlength="220" placeholder="Actual live batch photographed at the nursery"></label>
        <div class="form-three"><label>Focal X<input type="number" min="0" max="100" name="focal_x[<?=e($role)?>]" value="<?=e((string)($r['focal_x']??50))?>"></label><label>Focal Y<input type="number" min="0" max="100" name="focal_y[<?=e($role)?>]" value="<?=e((string)($r['focal_y']??50))?>"></label><label>Sort<input type="number" min="1" max="99" name="sort[<?=e($role)?>]" value="<?=e((string)($r['sort']??50))?>"></label></div>
      </fieldset>
    <?php endforeach; ?>
    </div>
    <button class="btn dark">Save photography roles</button>
  </form>
</details>
<?php endforeach; ?>
</div>
