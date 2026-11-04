<?php $quality=function_exists('growth_content_quality')?growth_content_quality($item):['score'=>0,'publish_threshold'=>65,'checks'=>[],'words'=>0];$ed=(array)($item['editorial']??[]); ?>
<section class="admin-head"><div><p class="eyebrow">Content Authority Workflow · v1.4.0</p><h1><?=e($item['title']??'New content')?></h1><p>Status: <?=e($item['status']??'DRAFT')?> · Quality <?=e((string)$quality['score'])?>/100 · Publish gate <?=e((string)$quality['publish_threshold'])?>+</p></div><a class="btn outline" href="<?=e(url('/admin/cms'))?>">Back to CMS</a></section>

<div class="admin-grid cms-growth-layout">
<form method="post" class="admin-card stack"><?=csrf_field()?>
<h2>Article content</h2>
<div class="admin-grid two"><label>Type<select name="type"><?php foreach(cms_types() as $k=>$v):?><option value="<?=e($k)?>" <?=($item['type']??'')===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label><label>Slug<input name="slug" value="<?=e($item['slug']??'')?>" placeholder="auto-from-title"></label></div>
<label>Title<input name="title" value="<?=e($item['title']??'')?>" required></label>
<label>Excerpt<textarea name="excerpt" rows="4" placeholder="Write a useful 80–160 character summary."><?=e($item['excerpt']??'')?></textarea></label>
<label>Body<textarea name="body" rows="22" placeholder="Write from nursery experience. Separate paragraphs with blank lines. Use ## Heading and - list items."><?=e($item['body']??'')?></textarea></label>
<label>Hero image filename<input name="hero_image" value="<?=e($item['hero_image']??'')?>" placeholder="Use an approved nursery image"></label>

<h2>Search intent & evidence</h2>
<label>Primary search question / topic<input name="target_query" value="<?=e($ed['target_query']??'')?>" placeholder="e.g. why are aglaonema leaves turning yellow"></label>
<div class="admin-grid two"><label>Search intent<select name="search_intent"><option value="">Choose intent</option><?php foreach(['Informational','Commercial research','Local visit','Transactional','Problem / diagnosis'] as $v):?><option <?=($ed['search_intent']??'')===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label><label>Audience<input name="audience" value="<?=e($ed['audience']??'')?>" placeholder="e.g. Pune apartment plant owners"></label></div>
<label>Expert reviewer<input name="expert_reviewer" value="<?=e($ed['expert_reviewer']??'')?>" placeholder="Named nursery expert / horticulturist"></label>
<label>First-hand nursery evidence<textarea name="firsthand_evidence" rows="6" placeholder="What did Sanjay Nursery observe, test, photograph or recommend from real plants/batches?"><?=e($ed['firsthand_evidence']??'')?></textarea></label>
<label>Source/reference URLs <small>one per line; use primary/authoritative sources where relevant</small><textarea name="source_urls" rows="6"><?=e(implode("\n",(array)($ed['source_urls']??[])))?></textarea></label>

<h2>SEO</h2>
<div class="admin-grid two"><label>SEO title<input name="seo_title" value="<?=e($item['seo']['title']??'')?>"></label><label>SEO description<textarea name="seo_description" rows="3"><?=e($item['seo']['description']??'')?></textarea></label></div>
<label class="checkline"><input type="checkbox" name="noindex" value="1" <?=!empty($item['seo']['noindex'])?'checked':''?>> Noindex this content</label>
<button class="btn primary">Save content</button>
</form>

<aside class="admin-card growth-quality-card"><h2>Authority score</h2><div class="quality-score <?=$quality['score']>=$quality['publish_threshold']?'ready':'needs-work'?>"><strong><?=e((string)$quality['score'])?></strong><span>/ 100</span></div><p><?=e((string)$quality['words'])?> words · publish threshold <?=e((string)$quality['publish_threshold'])?>.</p><div class="release-list"><?php foreach($quality['checks'] as $c):?><div><span><?=e($c['label'])?><?php if($c['detail']!==''):?><small><?=e($c['detail'])?></small><?php endif;?></span><b class="<?=$c['ok']?'ok':'warn'?>"><?=$c['ok']?'READY':'TODO'?></b></div><?php endforeach;?></div><p class="admin-muted">The score is a publishing guard, not a ranking promise. It is designed to stop thin, unreviewed, generic pages from entering the index.</p></aside>
</div>

<?php if(!empty($item['guid'])):?><section class="admin-card"><h2>Publishing</h2><?php if(($item['status']??'')==='PUBLISHED'):?><p>This page is live and indexable unless Noindex is selected.</p><?php elseif($quality['score']<$quality['publish_threshold']):?><p class="notice warn">Publishing is blocked until the authority score reaches <?=e((string)$quality['publish_threshold'])?>.</p><?php else:?><p class="notice ok">Quality gate passed. Review the rendered page before publishing.</p><?php endif;?><form method="post" action="<?=e(url('/admin/cms/'.$item['guid'].'/publish'))?>"><?=csrf_field()?><input type="hidden" name="publish" value="<?=($item['status']??'')==='PUBLISHED'?'0':'1'?>"><button class="btn <?=($item['status']??'')==='PUBLISHED'?'outline':'primary'?>" <?=($item['status']??'')!=='PUBLISHED'&&$quality['score']<$quality['publish_threshold']?'disabled':''?>><?=($item['status']??'')==='PUBLISHED'?'Return to draft':'Publish now'?></button></form><?php if(($item['status']??'')==='PUBLISHED'):?><p><a href="<?=e(url(cms_content_route($item)))?>" target="_blank">View published page ↗</a></p><?php endif;?></section><?php endif;?>
