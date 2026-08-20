<?php
declare(strict_types=1);

session_name('ecom_minutes_session');
session_start();

define('APP_ROOT', dirname(__DIR__));

function env_value(string $key, string $default = ''): string {
    static $values = null;
    if ($values === null) {
        $values = [];
        $path = APP_ROOT . '/.env';
        if (is_file($path)) foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$name, $value] = explode('=', $line, 2);
            $values[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
        }
    }
    return getenv($key) ?: ($values[$key] ?? $default);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . env_value('DB_HOST','127.0.0.1') . ';port=' . env_value('DB_PORT','3306') . ';dbname=' . env_value('DB_NAME') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, env_value('DB_USER'), env_value('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    return $pdo;
}
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function asset(string $path): string { return '/assets/' . ltrim($path, '/'); }
function url(string $route='', array $params=[]): string { return '/' . ($route ? trim($route,'/') : '') . ($params ? '?' . http_build_query($params) : ''); }
function slug(string $value): string { $value = strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$value))); return trim($value,'-') ?: 'item-' . bin2hex(random_bytes(3)); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function csrf_input(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) throw new RuntimeException('Your session expired. Refresh the page and try again.'); }
function flash(string $message, string $type='success'): void { $_SESSION['flash']=[$type,$message]; }
function show_flash(): string { $flash=$_SESSION['flash']??null; unset($_SESSION['flash']); return $flash ? '<div class="fixed z-50 left-1/2 -translate-x-1/2 top-5 rounded-lg px-4 py-3 text-sm text-white shadow-lg '.($flash[0]==='error'?'bg-red-600':'bg-emerald-700').'">'.e($flash[1]).'</div>' : ''; }
function redirect(string $path): never { header('Location: '.$path); exit; }

function current_tenant(PDO $db): array {
    $slug = preg_replace('/[^a-z0-9-]/','',strtolower((string)($_GET['store'] ?? '')));
    if (!$slug) { $host=explode(':',$_SERVER['HTTP_HOST']??'')[0]; $parts=explode('.',$host); if(count($parts)>2 && !in_array($parts[0],['www','localhost'],true)) $slug=$parts[0]; }
    $query=$slug?'SELECT * FROM tenants WHERE slug=? LIMIT 1':'SELECT * FROM tenants ORDER BY id LIMIT 1'; $stmt=$db->prepare($query); $stmt->execute($slug?[$slug]:[]); $tenant=$stmt->fetch();
    if (!$tenant) throw new RuntimeException('Tenant is not configured. Open /setup to create the first store.'); return $tenant;
}
function tenant_url(array $tenant, string $path='', array $params=[]): string { return url($path, array_merge(['store'=>$tenant['slug']],$params)); }
function current_user(PDO $db): ?array { if(empty($_SESSION['user_id']))return null; $s=$db->prepare('SELECT * FROM users WHERE id=? LIMIT 1');$s->execute([$_SESSION['user_id']]);return $s->fetch()?:null; }
function require_admin(PDO $db, array $tenant): array { $user=current_user($db);if(!$user||$user['tenant_id']!=$tenant['id']||$user['role']!=='admin')redirect('/admin/login');return $user; }
function cart(): array { return $_SESSION['cart']??[]; }
function cart_count(): int { return array_sum($_SESSION['cart']??[]); }
function money(float $amount): string { return '₹'.number_format($amount,0); }
function categories_of(PDO $db, int $productId): array { $q=$db->prepare('SELECT c.* FROM categories c JOIN product_categories pc ON pc.category_id=c.id WHERE pc.product_id=? ORDER BY c.name');$q->execute([$productId]);return $q->fetchAll(); }
function product_image(array $product): string { return $product['image_url'] ?: ''; }
function seo_head(array $tenant, string $title='', string $description='', array $product=[]): string {
    $title=$title?:($tenant['name'].' — '.$tenant['tagline']);$description=$description?:($tenant['description']?:$tenant['tagline']);$canonical=rtrim(env_value('APP_URL'),'/').($_SERVER['REQUEST_URI']??'/');$image=$product['image_url']??$tenant['logo_url']??'';
    $json=['@context'=>'https://schema.org','@type'=> $product ? 'Product':'WebSite','name'=>$product['name']??$tenant['name'],'description'=>$product['description']??$description,'url'=>$canonical];if($product){$json['image']=$image;$json['offers']=['@type'=>'Offer','priceCurrency'=>'INR','price'=>(float)$product['price'],'availability'=>(int)$product['stock']>0?'https://schema.org/InStock':'https://schema.org/OutOfStock'];}
    return '<title>'.e($title).'</title><meta name="description" content="'.e($description).'"><link rel="canonical" href="'.e($canonical).'"><meta property="og:title" content="'.e($title).'"><meta property="og:description" content="'.e($description).'"><meta property="og:type" content="website">'.($image?'<meta property="og:image" content="'.e($image).'">':'').'<script type="application/ld+json">'.json_encode($json,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>';
}
