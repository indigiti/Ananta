<?php
declare(strict_types=1);

/**
 * DigiOps public entry point for https://<site>/ananta/.
 * The private application code stays outside public_html.
 * Cloudways public_html and private_html are siblings in application home.
 */
$applicationHome=dirname(__DIR__,2);
$entry=$applicationHome.'/private_html/ananta/public/index.php';
if(!is_file($entry)){
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Ananta private application is not installed. Check the DigiOps release.');
}
require $entry;
