<section class="admin-head">
  <div><p class="kicker">Theme Studio</p><h1>Collection themes</h1><p>Assign an allowlisted visual preset to each storefront surface, then optionally override approved color tokens. No raw CSS is accepted.</p></div>
  <a class="btn outline" href="<?=e(url('/'))?>" target="_blank">Preview storefront ↗</a>
</section>

<div class="theme-admin-grid">
<?php foreach($rows as $row): $resolved=$row['resolved']; $cfg=$row['config']; ?>
  <section class="admin-card theme-admin-card">
    <div class="theme-card-head">
      <div><p class="kicker"><?=e($row['label'])?></p><h2><?=e($resolved['label'])?></h2><p><?=e($resolved['description'])?></p></div>
      <div class="theme-swatch-row" aria-label="Theme colors">
        <?php foreach(['bg','surface','accent','accent_2','ink'] as $key): ?><span style="background:<?=e($resolved['tokens'][$key])?>" title="<?=e($key)?>"></span><?php endforeach; ?>
      </div>
    </div>
    <form method="post" action="<?=e(url('/admin/themes/save'))?>" class="theme-form">
      <?=csrf_field()?><input type="hidden" name="surface" value="<?=e($row['surface'])?>">
      <label>Preset<select name="preset">
        <?php foreach($registry as $key=>$theme): ?><option value="<?=e($key)?>" <?=$resolved['key']===$key?'selected':''?>><?=e($theme['label'])?> — <?=e($theme['description'])?></option><?php endforeach; ?>
      </select></label>
      <details>
        <summary>Optional color overrides</summary>
        <p class="admin-help">Leave blank to inherit the selected preset. Only #RRGGBB values are accepted.</p>
        <div class="theme-token-grid">
        <?php foreach(['bg'=>'Page background','surface'=>'Cards / surface','surface_2'=>'Secondary surface','ink'=>'Primary text','muted'=>'Muted text','accent'=>'Primary accent','accent_2'=>'Luxury accent','border'=>'Borders','hero_from'=>'Hero start','hero_to'=>'Hero end','story_bg'=>'Story background','story_ink'=>'Story text','glow'=>'Glow','shadow'=>'Shadow'] as $key=>$label): ?>
          <label><?=e($label)?><input name="overrides[<?=e($key)?>]" value="<?=e($cfg['overrides'][$key]??'')?>" placeholder="<?=e($resolved['tokens'][$key])?>" pattern="#[0-9A-Fa-f]{6}"></label>
        <?php endforeach; ?>
        </div>
      </details>
      <div class="admin-actions"><button class="btn dark" type="submit">Save theme</button></div>
    </form>
  </section>
<?php endforeach; ?>
</div>

<section class="admin-card">
  <p class="kicker">Preset library</p><h2>Available visual directions</h2>
  <div class="theme-preset-grid">
  <?php foreach($registry as $key=>$theme): ?><article>
    <div class="theme-preview-mini theme-preview-<?=e($theme['mode'])?>" style="<?=e(theme_css_variables(array_merge($theme,['key'=>$key])))?>">
      <span class="theme-mini-arch"></span><span class="theme-mini-line"></span><b><?=e($theme['label'])?></b><small><?=e($theme['mode'])?></small>
    </div>
    <p><?=e($theme['description'])?></p>
  </article><?php endforeach; ?>
  </div>
</section>
