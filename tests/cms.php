<?php
declare(strict_types=1);
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('C',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=cms-test-secret');
putenv('SANJAY_AGENT_SIGNING_KEY=cms-test-agent-signing-key-01234567890123456789');
$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_URI']='/';$_SERVER['SCRIPT_NAME']='/index.php';
require $root.'/src/bootstrap.php';
$errors=[];
function ccheck(bool $ok,string $msg):void{global $errors;if(!$ok)$errors[]=$msg;}
$_SESSION['admin_user']=['guid'=>'019-test-admin','email'=>'admin@example.invalid','name'=>'Admin','role'=>'admin'];
$cmsPath=cms_file();$backup=is_file($cmsPath)?file_get_contents($cmsPath):null;
try{
    @unlink($cmsPath);
    $b=cms_save_block('care-note','Care standard','Check light, watering needs and drainage before recommending a plant.');
    ccheck(($b['key']??'')==='care-note','Block save failed.');
    $paragraph=str_repeat('Sanjay Nursery specialists inspect foliage, root condition, growing media and seasonal response before documenting a recommendation for customers. ',28);
    $body="## Start with the plant and room\n\n{$paragraph}\n\n## Check light, water and drainage\n\n{$paragraph}\n\n[[block:care-note]]";
    $i=cms_save_item([
        'type'=>'GUIDE','title'=>'Test Plant Care Guide for Indoor Conditions','slug'=>'test-plant-care-guide',
        'excerpt'=>'A staging authority guide that validates the nursery editorial workflow, expert review, evidence fields, structured content and publish-quality threshold.',
        'body'=>$body,'hero_image'=>'test-authority-hero.webp',
        'seo_title'=>'Test Plant Care Guide | Sanjay Nursery','seo_description'=>'A complete staging test guide for the nursery CMS, evidence workflow and SEO publishing layer.',
        'target_query'=>'how to care for indoor plants','search_intent'=>'informational','audience'=>'Pune apartment plant owners',
        'expert_reviewer'=>'Sanjay Nursery Horticulture Team',
        'firsthand_evidence'=>'Observed on nursery-grown indoor foliage batches under filtered light, with watering adjusted after checking media moisture and root health.',
        'source_urls'=>"https://sanjaynursery.com/\nhttps://developers.google.com/search/docs/fundamentals/creating-helpful-content",
    ]);
    ccheck(($i['status']??'')==='DRAFT','New CMS item should be draft.');
    $quality=growth_content_quality($i);ccheck($quality['score']>=65,'Authority article did not meet publish threshold.');
    $i=cms_publish($i['guid'],true);ccheck($i['status']==='PUBLISHED','Publish failed.');
    $pub=cms_public_by_slug('GUIDE','test-plant-care-guide');ccheck((bool)$pub,'Published content lookup failed.');
    $html=cms_render_body($pub['body']);ccheck(str_contains($html,'Care standard')&&str_contains($html,'Check light'),'Reusable block rendering failed.');
    $low=cms_save_item(['type'=>'GUIDE','title'=>'Thin Test Guide','slug'=>'thin-test-guide','excerpt'=>'Short.','body'=>'Tiny body.']);
    try{cms_publish($low['guid'],true);$errors[]='Low-quality content bypassed publish gate.';}catch(Throwable $e){ccheck(str_contains($e->getMessage(),'quality score'),'Low-quality publish denial did not explain quality threshold.');}
    $r=cms_add_redirect('/old-guide','/guides/test-plant-care-guide',301);ccheck(($r['code']??0)===301,'Redirect save failed.');
    ccheck((cms_redirect_for_path('/old-guide')['to']??'')==='/guides/test-plant-care-guide','Redirect resolution failed.');
    try{cms_add_redirect('/old-guide','/shop',301);$errors[]='Duplicate redirect source accepted.';}catch(Throwable){}
    $urls=cms_sitemap_urls();ccheck((bool)array_filter($urls,fn($u)=>$u['loc']==='/guides/test-plant-care-guide'),'Published guide missing from sitemap.');
    $checks=cms_release_checks();foreach($checks as $x)ccheck(!empty($x['ok']),'Release check failed: '.$x['name']);
}catch(Throwable $e){$errors[]='Unhandled: '.$e->getMessage();}
finally{
    if($backup===null)@unlink($cmsPath);else file_put_contents($cmsPath,$backup,LOCK_EX);
}
if($errors){fwrite(STDERR,implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: CMS authority workflow, quality-gated publishing, reusable blocks, SEO/editorial fields, redirects and sitemap coverage verified.\n";
