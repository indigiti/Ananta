<?php
declare(strict_types=1);


function text_lower(string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function text_len(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string {
    return $GLOBALS['base_path'] ?? '';
}

function url(string $path = '/'): string {
    $base = base_path();
    $path = '/' . ltrim($path, '/');
    if ($path === '//') $path = '/';
    return ($base ?: '') . $path;
}

function asset(string $path): string {
    $clean=ltrim($path,'/');
    $out=url('/assets/'.$clean);
    $ext=strtolower(pathinfo($clean,PATHINFO_EXTENSION));
    if(in_array($ext,['css','js'],true)){
        // APP_VERSION stays stable during staged releases. Use a content fingerprint
        // so browsers/CDNs cannot reuse a pre-SupplierOS CSS or JS asset.
        static $fingerprints=[];
        if(!array_key_exists($clean,$fingerprints)){
            $root=dirname(__DIR__).'/public/assets/';
            $source=realpath($root.$clean);
            $base=realpath($root);
            $valid=$source!==false&&$base!==false
                &&str_starts_with($source,$base.DIRECTORY_SEPARATOR)&&is_file($source);
            $fingerprints[$clean]=$valid?substr((string)hash_file('sha256',$source),0,12):'';
        }
        $version=(string)($GLOBALS['app']['version']??'');
        $stamp=($version!==''?$version:'assets').($fingerprints[$clean]!==''?'-'.$fingerprints[$clean]:'');
        $out.='?v='.rawurlencode($stamp);
    }
    return $out;
}

function absolute_url(string $path='/'): string {
    $scheme=request_is_https()?'https':'http';
    $host=(string)($_SERVER['HTTP_HOST']??'localhost');
    return $scheme.'://'.$host.url($path);
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    $sent = $_POST['_csrf'] ?? '';
    if (!$sent || !hash_equals(csrf_token(), (string)$sent)) {
        http_response_code(419);
        exit('Session expired. Please refresh and try again.');
    }
}

function money(int|float $amount): string {
    return '₹' . number_format((float)$amount, 0, '.', ',');
}

/** Lightweight inline icon set used across storefront/admin UI. */
function ui_icon(string $name, string $class='ui-icon'): string {
    $icons = [
        'search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'pin'=>'<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'theme'=>'<path d="M12 3a9 9 0 1 0 9 9c0-.4 0-.8-.1-1.2A7 7 0 0 1 12 3Z"/>',
        'moon'=>'<path d="M21 12.8A8.5 8.5 0 1 1 11.2 3 6.8 6.8 0 0 0 21 12.8Z"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'bag'=>'<path d="M6 8h12l1 13H5L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close'=>'<path d="m6 6 12 12M18 6 6 18"/>',
        'heart'=>'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'water'=>'<path d="M12 2s6 6.5 6 12a6 6 0 0 1-12 0c0-5.5 6-12 6-12Z"/>',
        'humidity'=>'<path d="M4 7c2-2 4-2 6 0s4 2 6 0 4-2 4-2M4 13c2-2 4-2 6 0s4 2 6 0 4-2 4-2M4 19c2-2 4-2 6 0s4 2 6 0 4-2 4-2"/>',
        'sparkles'=>'<path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3ZM5 14l.8 2.2L8 17l-2.2.8L5 20l-.8-2.2L2 17l2.2-.8L5 14ZM19 13l.7 1.8 1.8.7-1.8.7L19 18l-.7-1.8-1.8-.7 1.8-.7L19 13Z"/>',
        'home'=>'<path d="m3 11 9-8 9 8"/><path d="M5 10v11h14V10M9 21v-6h6v6"/>',
        'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'orders'=>'<path d="M6 3h12l2 4v14H4V7l2-4Z"/><path d="M4 8h16M9 12h6"/>',
        'plant'=>'<path d="M12 22V10"/><path d="M12 13C6 13 4 9 4 5c5 0 8 2 8 8ZM12 17c6 0 8-4 8-8-5 0-8 2-8 8Z"/>',
        'variants'=>'<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/><path d="M14 7h6M17 4v6M4 17h6M7 14v6"/>',
        'image'=>'<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/>',
        'discovery'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M11 7v8M7 11h8"/>',
        'brain'=>'<path d="M9 4a3 3 0 0 0-5 2.2A3.5 3.5 0 0 0 4 13a3 3 0 0 0 5 3v4M15 4a3 3 0 0 1 5 2.2A3.5 3.5 0 0 1 20 13a3 3 0 0 1-5 3v4M9 8h6M9 12h6M12 4v16"/>',
        'growth'=>'<path d="M4 19V9M10 19V5M16 19v-7M22 19V3"/>',
        'content'=>'<path d="M6 3h9l3 3v15H6V3Z"/><path d="M14 3v4h4M9 11h6M9 15h6"/>',
        'tag'=>'<path d="M20 13 13 20 4 11V4h7l9 9Z"/><circle cx="8.5" cy="8.5" r="1"/>',
        'shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9A1.7 1.7 0 0 0 21 10h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'external'=>'<path d="M14 3h7v7M10 14 21 3M21 14v7H3V3h7"/>',
        'logout'=>'<path d="M10 17l5-5-5-5M15 12H3M21 3v18h-6"/>',
        'chevron-right'=>'<path d="m9 18 6-6-6-6"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'filter'=>'<path d="M4 5h16M7 12h10M10 19h4"/>',
        'pulse'=>'<path d="M3 12h4l2-5 4 10 2-5h6"/>',
        'alert'=>'<path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17.5h.01"/>',
    ];
    $body=$icons[$name]??$icons['sparkles'];
    return '<svg class="'.e($class).'" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$body.'</svg>';
}

function products_all(): array {
    return $GLOBALS['products'] ?? [];
}

function products_public(): array {
    return array_values(array_filter(products_all(), fn($p)=>(($p['status'] ?? 'active') === 'active')));
}

function product_by_slug(string $slug): ?array {
    $source = (function_exists('admin_logged_in') && admin_logged_in()) ? products_all() : products_public();
    foreach ($source as $product) {
        if (($product['slug'] ?? '') === $slug) return $product;
    }
    return null;
}

function category_label(string $category): string {
    $map = [
        'indoor-plants' => 'Indoor Plants',
        'succulents-cacti' => 'Succulents & Cacti',
        'outdoor-plants' => 'Outdoor Plants',
        'pots-planters' => 'Pots & Planters',
        'gardening-essentials' => 'Gardening Essentials',
        'corporate-gifting' => 'Corporate Gifting',
    ];
    return $map[$category] ?? ucwords(str_replace('-', ' ', $category));
}

function image_url(string $file): string {
    return asset('images/products/' . $file);
}

function product_is_enquiry(array $product): bool {
    if (function_exists('product_has_sellable_variants') && product_has_sellable_variants($product)) return false;
    return (($product['sales_mode'] ?? 'commerce') === 'enquiry') || (int)($product['price'] ?? 0) <= 0;
}

function product_is_sellable(array $product): bool {
    if (($product['status'] ?? 'active') !== 'active') return false;
    if (function_exists('product_has_sellable_variants') && product_has_sellable_variants($product)) return true;
    return (($product['sales_mode'] ?? 'commerce') === 'commerce') && (int)($product['price'] ?? 0) > 0;
}

function product_price_label(array $product): string {
    if (function_exists('product_variant_price_range')) {
        $range=product_variant_price_range($product);
        if ($range) return $range['min']===$range['max'] ? money($range['min']) : money($range['min']).'–'.money($range['max']);
    }
    if (product_is_enquiry($product)) return (string)($product['price_label'] ?? 'Check price & availability');
    return money((int)($product['price'] ?? 0));
}

function product_has_image(array $product): bool {
    // v1.6 public storefront only treats the primary image as publishable after MediaOS approval/rights clearance.
    if (function_exists('mediaos_primary_approved')) return mediaos_primary_approved($product);
    $file = (string)($product['image'] ?? '');
    if ($file === '') return false;
    return is_file(dirname(__DIR__) . '/public/assets/images/products/' . basename($file));
}

function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $out = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $out;
}

function redirect(string $path): never {
    header('Location: ' . url($path));
    exit;
}

function cart_lines(): array {
    return function_exists('commerce_cart_lines') ? commerce_cart_lines() : [];
}

function cart_count(): int {
    return function_exists('commerce_cart_count') ? commerce_cart_count() : 0;
}

function cart_total(): int {
    return function_exists('commerce_cart_subtotal') ? commerce_cart_subtotal() : 0;
}

function is_wishlisted(string $slug): bool {
    return in_array($slug, $_SESSION['wishlist'] ?? [], true);
}

function render(string $template, array $data = []): void {
    extract($data, EXTR_SKIP);
    $templateFile = dirname(__DIR__) . '/templates/' . $template . '.php';
    if (!is_file($templateFile)) throw new RuntimeException('Template not found: ' . $template);
    ob_start();
    include $templateFile;
    $content = ob_get_clean();
    include dirname(__DIR__) . '/templates/layout.php';
}
