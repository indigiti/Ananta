<?php
declare(strict_types=1);
$root=dirname(__DIR__);putenv('APP_ENV=staging');$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_URI']='/';$_SERVER['SCRIPT_NAME']='/index.php';require $root.'/src/bootstrap.php';
$catalog=products_public();$bad=[];foreach($catalog as $p){if(product_is_enquiry($p)&&configurator_schema_for_product($p))$bad[]=$p['slug'];}
if($bad){fwrite(STDERR,'Enquiry-only varieties must not have commerce configurators: '.implode(',',$bad)."\n");exit(1);}echo "PASS: enquiry-only plant varieties correctly bypass legacy commerce configurator rules.\n";
