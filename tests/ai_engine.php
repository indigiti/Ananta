<?php
declare(strict_types=1);
$root=dirname(__DIR__);putenv('APP_ENV=staging');if(!getenv('SANJAY_DATA_KEY'))putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('A',32)));if(!getenv('SANJAY_AGENT_SIGNING_KEY'))putenv('SANJAY_AGENT_SIGNING_KEY=ai-engine-regression-agent-signing-key-012345');$_SERVER['HTTP_HOST']='staging.example.test';$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REMOTE_ADDR']='127.0.0.1';require $root.'/src/bootstrap.php';
$errors=[];
$s=ai_settings();if(($s['mode']??'')!=='scan_only')$errors[]='Default AI mode must be scan_only.';if(ai_provider_required())$errors[]='Scan Only must not require an external AI provider.';if(!in_array('price',(array)$s['protected_fields'],true)||!in_array('inventory',(array)$s['protected_fields'],true))$errors[]='Commercial protected-field policy is incomplete.';
$scan=ai_scan_catalogue();if(($scan['count']??0)!==count(products_all()))$errors[]='Catalogue AI scan does not cover every product.';if(($scan['average']??-1)<0||($scan['average']??101)>100)$errors[]='Catalogue health score invalid.';
$seo=ai_seo_scan();if(!isset($seo['score'],$seo['issues']))$errors[]='SEO intelligence report schema invalid.';
$opp=ai_build_opportunities();if(count($opp['listicles']??[])<5||count($opp['comparisons']??[])<1)$errors[]='Content opportunity discovery is incomplete.';
$cap=ai_agent_capabilities();if(!in_array('purchase',(array)($cap['human_approval_required']??[]),true))$errors[]='Agent capability manifest does not require human purchase approval.';
$spec=ai_openapi_spec();if(($spec['openapi']??'')!=='3.1.0'||empty($spec['paths']['/api/agent/catalog/search']))$errors[]='OpenAPI agent discovery schema invalid.';
$checks=ai_release_checks();foreach($checks as $c)if(empty($c['ok']))$errors[]='AI release check failed: '.$c['name'];
// Content creation helpers require admin by policy; validate candidate selection and comparison matrix without mutating content.
$candidates=ai_listicle_candidates('Low-Maintenance Indoor Plants for Pune Homes',10);if(count($candidates)<2)$errors[]='Listicle candidate engine returned too few products.';$matrix=ai_comparison_matrix(array_slice($candidates,0,2));if(count($matrix)<6)$errors[]='Comparison matrix lacks expected catalogue attributes.';
if($errors){fwrite(STDERR,"FAIL: ".implode("\nFAIL: ",$errors)."\n");exit(1);}echo "PASS: v1.7.0 AI catalogue/SEO scanning, opportunity discovery, protected-field policy, comparison/listicle data engine and agent discovery contracts verified.\n";
