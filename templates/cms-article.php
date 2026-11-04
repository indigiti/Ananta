<?php $ed=(array)($item['editorial']??[]); ?>
<article class="editorial-article">
<header class="editorial-hero"><div class="shell narrow"><p class="eyebrow"><?=e(cms_types()[$item['type']]??$item['type'])?></p><h1><?=e($item['title'])?></h1><p class="lede"><?=e($item['excerpt'])?></p><p class="meta">By <?=e($item['author']??'Sanjay Nursery Studio')?><?php if(!empty($ed['expert_reviewer'])):?> · Reviewed by <?=e($ed['expert_reviewer'])?><?php endif;?> · Updated <?=e(substr($item['updated_at'],0,10))?></p></div></header>
<div class="shell narrow prose"><?=cms_render_body((string)$item['body'])?>
<?php if(!empty($ed['firsthand_evidence'])):?><aside class="editorial-evidence"><h2>Nursery note</h2><p><?=nl2br(e($ed['firsthand_evidence']))?></p></aside><?php endif;?>
<?php if(!empty($ed['source_urls'])):?><aside class="editorial-sources"><h2>References</h2><ul><?php foreach((array)$ed['source_urls'] as $u):?><li><a href="<?=e($u)?>" rel="noopener noreferrer" target="_blank"><?=e(parse_url($u,PHP_URL_HOST)?:$u)?> ↗</a></li><?php endforeach;?></ul></aside><?php endif;?>
</div></article>
