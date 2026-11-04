<?php
declare(strict_types=1);
/** B5: XLSX/PDF extraction, fail-closed AI, evidence and human-review acceptance. */
$root=dirname(__DIR__);
putenv('APP_ENV=staging');
putenv('SANJAY_DATA_KEY='.base64_encode(str_repeat('E',32)));
putenv('SANJAY_SANDBOX_WEBHOOK_SECRET=sourcing-ingest-test');
$_SERVER['HTTP_HOST']='stage.example.invalid';$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REMOTE_ADDR']='127.0.0.72';
$nonce=bin2hex(random_bytes(7));
$paths=[
 'customers'=>$root.'/storage/tmp/ingest-customers-'.$nonce.'.json',
 'market'=>$root.'/storage/tmp/ingest-market-'.$nonce.'.json',
 'tenants'=>$root.'/storage/tmp/ingest-tenants-'.$nonce.'.json',
 'sourcing'=>$root.'/storage/tmp/ingest-sourcing-'.$nonce.'.json',
];
putenv('ANANTA_TEST_CUSTOMERS_FILE='.$paths['customers']);
putenv('ANANTA_TEST_MARKETPLACE_FILE='.$paths['market']);
putenv('ANANTA_TEST_TENANT_FILE='.$paths['tenants']);
putenv('ANANTA_TEST_SOURCING_FILE='.$paths['sourcing']);
require $root.'/src/bootstrap.php';
$errors=[];$files=[];
$assert=static function(bool $yes,string $msg)use(&$errors){if(!$yes)$errors[]=$msg;};
$tmp=static function(string $suffix,string $contents='')use(&$files):string{
  $path=tempnam(sys_get_temp_dir(),'boq-');
  if($path===false)throw new RuntimeException('Temp fixture unavailable.');
  file_put_contents($path,$contents);$files[]=$path;return $path;
};
$buildXlsx=static function(array $rows,array $extra=[])use($tmp):string{
 if(!class_exists(ZipArchive::class)||!class_exists(DOMDocument::class))
   throw new RuntimeException('PHP ZIP and DOM extensions are required in B5 CI.');
 $p=$tmp('xlsx');$z=new ZipArchive();
 if($z->open($p,ZipArchive::OVERWRITE|ZipArchive::CREATE)!==true)throw new RuntimeException('Cannot make XLSX test.');
 $esc=static fn(string $v)=>htmlspecialchars($v,ENT_QUOTES|ENT_XML1,'UTF-8');
 $body='';
 foreach($rows as $i=>$cells){
   $body.='<row r="'.($i+1).'">';
   foreach(array_slice($cells,0,3) as $c=>$v){
     $col=chr(65+$c);
     $body.='<c r="'.$col.($i+1).'" t="inlineStr"><is><t>'.$esc((string)$v).'</t></is></c>';
   }
   $body.='</row>';
 }
 $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$body.'</sheetData></worksheet>';
 $z->addFromString('xl/worksheets/sheet1.xml',$sheet);
 foreach($extra as $name=>$value)$z->addFromString($name,$value);
 $z->close();return $p;
};
try{
 $buyer=uuidv7();$other=uuidv7();
 confidential_json_write(commerce_customer_file(),[
  ['guid'=>$buyer,'name'=>'Ingestion Test Buyer','email'=>'review@qa.invalid','phone'=>'919099999999','status'=>'active'],
  ['guid'=>$other,'name'=>'Other Buyer','email'=>'other@qa.invalid','status'=>'active'],
 ],'sanjaynursery:customers:v1');
 $_SESSION['customer_user']=['guid'=>$buyer,'name'=>'Ingestion Test Buyer','email'=>'review@qa.invalid'];
 $products=array_values(products_public());if(count($products)<2)throw new RuntimeException('Catalogue must contain two plants.');
 [$a,$b]=array_slice($products,0,2);
 $rows=[
  ['plant_name','quantity','specification'],
  [$a['name'],'12','120 cm Grade A'],
  ['Unknown botanical name','no quantity','Grade B']
 ];
 $xlsx=$buildXlsx($rows);
 $pending=sourcing_ingest_stage($xlsx,'nursery-boq.xlsx');
 $assert(count($pending['rows'])===2,'XLSX row extraction failed.');
 $assert(($pending['rows'][0]['product_slug']??'')===$a['slug'],'Exact plant name match not resolved.');
 $assert(($pending['rows'][1]['product_slug']??'')==='','Unknown plant guessed instead of unresolved.');
 $assert(($pending['rows'][1]['quantity']??'')==='','Invalid quantity was silently accepted.');
 $assert(str_contains($pending['rows'][0]['source_ref'],'Excel row 2'),'Row evidence location missing.');
 $assert(sourcing_boq_pending()===null,'Unreviewed extraction became a validated BOQ.');
 $assert(sourcing_store()['rfqs']===[],'Unreviewed extraction created a supplier RFQ.');
 $_SESSION['customer_user']['guid']=$other;
 $assert(sourcing_ingest_pending()===null,'Extracted preview disclosed to another buyer.');
 $_SESSION['customer_user']['guid']=$buyer;
 // Changing buyer clears the previous preview. Reload it for explicit review.
 $pending=sourcing_ingest_stage($xlsx,'nursery-boq.xlsx');
 $draftRows=[];
 foreach($pending['rows'] as $i=>$row)$draftRows[$row['id']]=[
   'product_slug'=>$i===0?$a['slug']:$b['slug'],
   'quantity'=>$i===0?'12':'5',
   'specification'=>$i===0?'120 cm Grade A':'10 inch pot',
 ];
 try{sourcing_ingest_review(['preview_token'=>'wrong','confirm_review'=>'yes','rows'=>$draftRows]);$errors[]='Incorrect review token accepted.';}catch(RuntimeException){}
 try{sourcing_ingest_review(['preview_token'=>$pending['token'],'rows'=>$draftRows]);$errors[]='Unconsented review accepted.';}catch(InvalidArgumentException){}
 $review=sourcing_ingest_review(['preview_token'=>$pending['token'],'confirm_review'=>'yes','rows'=>$draftRows]);
 $assert(count($review['lines'])===2,'Human-reviewed BOQ not staged.');
 $assert($review['lines'][0]['human_verified']===true,'Missing human verification flag.');
 $assert($review['lines'][1]['product_slug']===$b['slug'],'Buyer correction not applied.');
 $assert($review['lines'][0]['source_match']==='exact-name','Original matching confidence missing.');
 $fakeTenant=uuidv7();
 $projected=sourcing_supplier_projection([
    'id'=>uuidv7(),'plant_guid'=>$a['guid'],'plant_name'=>'Multi-plant BOQ','specification'=>'Verified BOQ',
    'quantity'=>12,'destination'=>'Pune','notes'=>'','status'=>'open',
    'contact_shared'=>false,'buyer_contact'=>null,'type'=>'boq',
    'lines'=>$review['lines'],'recipients'=>[$fakeTenant=>['status'=>'invited','quote'=>null,'updated_at'=>gmdate('c')]],
    'created_at'=>gmdate('c')
 ],$fakeTenant);
 $assert(!array_key_exists('source_ref',$projected['lines'][0]),'Supplier projection disclosed private original source evidence.');
 $assert(!array_key_exists('source_match',$projected['lines'][0]),'Supplier projection disclosed buyer-only matching metadata.');
 $assert($projected['lines'][0]['quantity']===12,'Supplier commercial BOQ line missing.');
 $assert($review['origin']==='xlsx'&&strlen($review['source_sha256'])===64,'Source provenance incomplete.');
 $assert(sourcing_ingest_pending()===null,'Source intake still active after review.');
 $assert(sourcing_store()['rfqs']===[],'Review saved an RFQ without supplier consent.');
 try{sourcing_ingest_review(['preview_token'=>$pending['token'],'confirm_review'=>'yes','rows'=>$draftRows]);$errors[]='Duplicate review replay accepted.';}catch(RuntimeException){}
 $dupes=$buildXlsx([['product_slug','quantity','specification'],[$a['slug'],'12','120 cm'],[$a['slug'],'13','120 cm']]);
 $duplicate=sourcing_ingest_stage($dupes,'duplicate.xlsx');
 $dupRows=[];
 foreach($duplicate['rows'] as $row)$dupRows[$row['id']]=['product_slug'=>$a['slug'],'quantity'=>$row['quantity'],'specification'=>'120 cm'];
 try{sourcing_ingest_review(['preview_token'=>$duplicate['token'],'confirm_review'=>'yes','rows'=>$dupRows]);$errors[]='Duplicate verified plant-grade accepted.';}catch(InvalidArgumentException){}
 $unsafe=$buildXlsx([['plant_name','quantity','specification'],[$a['name'],'1','Grade A']],
   ['xl/externalLinks/externalLink1.xml'=>'<link/>']);
 try{sourcing_ingest_xlsx($unsafe);$errors[]='External Excel relationships accepted.';}catch(InvalidArgumentException){}
 $formula=$tmp('xlsx');$z=new ZipArchive();$z->open($formula,ZipArchive::CREATE|ZipArchive::OVERWRITE);
 $z->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0"?><worksheet><sheetData><row><c r="A1"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>');$z->close();
 try{sourcing_ingest_xlsx($formula);$errors[]='Excel formulas accepted.';}catch(InvalidArgumentException){}
 $fake=$tmp('pdf',"%PDF-1.4\n%Testing only; text comes from the isolated CLI stub.\n");
 $script=$tmp('sh',"#!/bin/sh\ncat <<'BOQ'\n".$a['slug']." | 6 | 15 inch pot\nUnmatched nursery species | 3 | Grade B\nBOQ\n");
 chmod($script,0700);putenv('ANANTA_TEST_PDFTOTEXT_BINARY='.$script);
 $pdf=sourcing_ingest_stage($fake,'plant-requirement.pdf');
 $assert(count($pdf['rows'])===2,'Text PDF did not return two evidence-linked rows.');
 $assert($pdf['rows'][0]['product_slug']===$a['slug'],'Text PDF exact slug not matched.');
 $assert(str_contains($pdf['rows'][0]['source_ref'],'PDF text line'),'PDF evidence provenance missing.');
 $assert($pdf['rows'][1]['product_slug']==='','PDF unknown name incorrectly guessed.');
 try{sourcing_ingest_stage($fake,'plant-requirement.pdf',true);$errors[]='Unconfigured AI accepted PDF transmission.';}catch(RuntimeException){}
 $missingPdf=$tmp('pdf',str_repeat('not a pdf',8));
 try{sourcing_ingest_stage($missingPdf,'wrong.pdf');$errors[]='Spoofed PDF signature accepted.';}catch(InvalidArgumentException){}
 $wrongExt=$tmp('xlsx',"%PDF-1.4\n");
 try{sourcing_ingest_stage($wrongExt,'wrong.xlsx');$errors[]='XLSX signature spoof accepted.';}catch(InvalidArgumentException){}
 $noText=$tmp('sh',"#!/bin/sh\nexit 0\n");chmod($noText,0700);putenv('ANANTA_TEST_PDFTOTEXT_BINARY='.$noText);
 try{sourcing_ingest_stage($fake,'scan.pdf');$errors[]='No-text scanned PDF silently accepted.';}catch(InvalidArgumentException){}
 putenv('ANANTA_TEST_PDFTOTEXT_BINARY');
 $view=(string)file_get_contents($root.'/templates/sourcing/boq.php');
 foreach(['/sourcing/boq/ingest','/sourcing/boq/review','name="confirm_review"','source_ref'] as $marker)
    $assert(str_contains($view,$marker),'Review UI missing '.$marker);
} catch(Throwable $e){$errors[]=$e->getMessage();}
putenv('ANANTA_TEST_PDFTOTEXT_BINARY');
foreach($files as $f)@unlink($f);
foreach($paths as $file)@unlink($file);
if($errors){fwrite(STDERR,"FAIL B5 ingestion:\n".implode("\n",array_unique($errors))."\n");exit(1);}
echo "PASS: XLSX limits and matching, unresolved rows, human corrections, buyer isolation, PDF text provenance, opt-in AI gate and no auto-RFQ.\n";
