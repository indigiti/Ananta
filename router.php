<?php
// Router for PHP's built-in server. Example: php -S 127.0.0.1:8080 router.php
$public = __DIR__ . '/public';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($public . $uri);
if ($file && str_starts_with($file, realpath($public)) && is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = [
        'css'=>'text/css; charset=utf-8','js'=>'application/javascript; charset=utf-8','json'=>'application/json; charset=utf-8',
        'svg'=>'image/svg+xml','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','avif'=>'image/avif',
        'ico'=>'image/x-icon','woff'=>'font/woff','woff2'=>'font/woff2','txt'=>'text/plain; charset=utf-8'
    ];
    if (!headers_sent()) {
        header('Content-Type: '.($types[$ext] ?? 'application/octet-stream'));
        header('Content-Length: '.filesize($file));
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
    }
    readfile($file);
    exit;
}
require $public . '/index.php';
