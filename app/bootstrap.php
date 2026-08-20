<?php
declare(strict_types=1);

session_name('ecom_minutes');
session_start();

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0775, true);
if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0775, true);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO('sqlite:' . DATA_PATH . '/ecom-minutes.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS stores (
        id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, name TEXT NOT NULL DEFAULT '', tagline TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '',
        logo TEXT NOT NULL DEFAULT '', hero_title TEXT NOT NULL DEFAULT '', hero_image TEXT NOT NULL DEFAULT '', primary_color TEXT NOT NULL DEFAULT '#6D5EF2',
        secondary_color TEXT NOT NULL DEFAULT '#E9E6FF', accent_color TEXT NOT NULL DEFAULT '#F37B5B', theme TEXT NOT NULL DEFAULT 'minimal',
        font_style TEXT NOT NULL DEFAULT 'sans', show_brand_name INTEGER NOT NULL DEFAULT 1, is_live INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL, email TEXT NOT NULL, password_hash TEXT NOT NULL, name TEXT NOT NULL DEFAULT 'Store owner',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(store_id,email), FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL, name TEXT NOT NULL, slug TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(store_id,slug), FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL, name TEXT NOT NULL, slug TEXT NOT NULL, sku TEXT NOT NULL DEFAULT '',
        price REAL NOT NULL DEFAULT 0, compare_price REAL NOT NULL DEFAULT 0, description TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '',
        stock INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(store_id,slug), FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS product_categories (
        product_id INTEGER NOT NULL, category_id INTEGER NOT NULL, PRIMARY KEY(product_id,category_id),
        FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE, FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS banners (
        id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL, title TEXT NOT NULL, subtitle TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '',
        cta_label TEXT NOT NULL DEFAULT 'Shop now', cta_url TEXT NOT NULL DEFAULT '/?r=shop', sort_order INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL, code TEXT NOT NULL, title TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '',
        discount_type TEXT NOT NULL DEFAULT 'percent', discount_value REAL NOT NULL DEFAULT 0, minimum_order REAL NOT NULL DEFAULT 0,
        starts_at TEXT, ends_at TEXT, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(store_id,code), FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL, order_number TEXT NOT NULL, customer_name TEXT NOT NULL, customer_email TEXT NOT NULL,
        customer_phone TEXT NOT NULL DEFAULT '', shipping_address TEXT NOT NULL, subtotal REAL NOT NULL, discount REAL NOT NULL DEFAULT 0,
        total REAL NOT NULL, coupon_code TEXT NOT NULL DEFAULT '', payment_status TEXT NOT NULL DEFAULT 'pending', order_status TEXT NOT NULL DEFAULT 'new',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(store_id,order_number), FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, product_id INTEGER, product_name TEXT NOT NULL, unit_price REAL NOT NULL, quantity INTEGER NOT NULL,
        FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
    )");
    try {
        $db->exec('CREATE VIRTUAL TABLE IF NOT EXISTS product_search USING fts5(product_id UNINDEXED, store_id UNINDEXED, name, description, categories)');
    } catch (Throwable $ignored) { /* Shared hosts with SQLite without FTS still use LIKE fallback. */ }
    $exists = (int)$db->query("SELECT COUNT(*) FROM stores")->fetchColumn();
    if (!$exists) $db->prepare("INSERT INTO stores (slug) VALUES ('your-store')")->execute();
}

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $route = '', array $params = []): string { return '?' . http_build_query(array_merge($route ? ['r' => $route] : [], $params)); }
function admin_url(string $page = 'overview', array $params = []): string { return 'admin.php?' . http_build_query(array_merge(['page' => $page], $params)); }
function slugify(string $text): string { $text = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text))); return trim($text, '-') ?: 'item-' . substr(bin2hex(random_bytes(3)), 0, 6); }
function valid_color(string $color, string $fallback): string { return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : $fallback; }
function current_store(PDO $db): array {
    $requested = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['store'] ?? '')));
    if (!$requested) {
        $host = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
        $parts = explode('.', $host);
        if (count($parts) > 2 && !in_array($parts[0], ['www', 'localhost'], true)) $requested = $parts[0];
    }
    $sql = $requested ? 'SELECT * FROM stores WHERE slug = ?' : 'SELECT * FROM stores ORDER BY id LIMIT 1';
    $stmt = $db->prepare($sql); $stmt->execute($requested ? [$requested] : []);
    $store = $stmt->fetch();
    if (!$store) { $store = $db->query('SELECT * FROM stores ORDER BY id LIMIT 1')->fetch(); }
    return $store;
}
function store_url(array $store, string $route = '', array $params = []): string { return 'index.php' . url($route, array_merge(['store' => $store['slug']], $params)); }
function active_coupons(PDO $db, int $storeId): array {
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("SELECT * FROM coupons WHERE store_id=? AND is_active=1 AND (starts_at IS NULL OR starts_at='' OR starts_at<=?) AND (ends_at IS NULL OR ends_at='' OR ends_at>=?) ORDER BY id DESC");
    $stmt->execute([$storeId, $now, $now]); return $stmt->fetchAll();
}
function categories(PDO $db, int $storeId): array { $s=$db->prepare('SELECT * FROM categories WHERE store_id=? ORDER BY name'); $s->execute([$storeId]); return $s->fetchAll(); }
function product_categories(PDO $db, int $productId): array { $s=$db->prepare('SELECT c.* FROM categories c JOIN product_categories pc ON pc.category_id=c.id WHERE pc.product_id=? ORDER BY c.name'); $s->execute([$productId]); return $s->fetchAll(); }
function index_product(PDO $db, int $productId): void {
    try {
        $s=$db->prepare('SELECT p.*, GROUP_CONCAT(c.name, " ") category_names FROM products p LEFT JOIN product_categories pc ON pc.product_id=p.id LEFT JOIN categories c ON c.id=pc.category_id WHERE p.id=? GROUP BY p.id'); $s->execute([$productId]); $product=$s->fetch(); if(!$product) return;
        $db->prepare('DELETE FROM product_search WHERE product_id=?')->execute([$productId]);
        $db->prepare('INSERT INTO product_search (product_id,store_id,name,description,categories) VALUES (?,?,?,?,?)')->execute([$productId,$product['store_id'],$product['name'],$product['description'],$product['category_names'] ?? '']);
    } catch (Throwable $ignored) {}
}
function products(PDO $db, int $storeId, ?string $categorySlug = null): array {
    $sql='SELECT DISTINCT p.* FROM products p LEFT JOIN product_categories pc ON pc.product_id=p.id LEFT JOIN categories c ON c.id=pc.category_id WHERE p.store_id=? AND p.is_active=1'; $args=[$storeId];
    if($categorySlug){$sql.=' AND c.slug=?';$args[]=$categorySlug;} $sql.=' ORDER BY p.created_at DESC'; $s=$db->prepare($sql);$s->execute($args);return $s->fetchAll();
}
function search_products(PDO $db, int $storeId, string $query): array {
    $query=trim($query); if($query==='') return [];
    try { $terms=preg_split('/\s+/', preg_replace('/[^\pL\pN\s-]/u','',$query)); $match=implode(' AND ',array_map(fn($term)=>$term.'*',$terms));
        $s=$db->prepare('SELECT p.* FROM product_search f JOIN products p ON p.id=f.product_id WHERE f.store_id=? AND product_search MATCH ? AND p.is_active=1 ORDER BY rank');$s->execute([$storeId,$match]);$found=$s->fetchAll(); if($found) return $found;
    } catch(Throwable $ignored) {}
    $needle='%'.str_replace(['%','_'],['\\%','\\_'],$query).'%';
    $s=$db->prepare("SELECT DISTINCT p.* FROM products p LEFT JOIN product_categories pc ON pc.product_id=p.id LEFT JOIN categories c ON c.id=pc.category_id WHERE p.store_id=? AND p.is_active=1 AND (p.name LIKE ? ESCAPE '\\' OR p.description LIKE ? ESCAPE '\\' OR c.name LIKE ? ESCAPE '\\') ORDER BY p.name");$s->execute([$storeId,$needle,$needle,$needle]);return $s->fetchAll();
}
function upload_image(string $field): string {
    if (empty($_FILES[$field]['tmp_name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return '';
    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) throw new RuntimeException('Images must be 5 MB or smaller.');
    $info = new finfo(FILEINFO_MIME_TYPE); $type=$info->file($_FILES[$field]['tmp_name']); $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if(!isset($allowed[$type])) throw new RuntimeException('Upload a JPG, PNG, WEBP, or GIF image.');
    $name=date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$type]; if(!move_uploaded_file($_FILES[$field]['tmp_name'],UPLOAD_PATH.'/'.$name)) throw new RuntimeException('Image upload failed.'); return 'uploads/'.$name;
}
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function verify_csrf(): void { if(!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) throw new RuntimeException('Your form expired. Please refresh and try again.'); }
function flash(string $message, string $type='success'): void { $_SESSION['flash']=[$type,$message]; }
function consume_flash(): ?array { $flash=$_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }
function admin_store(PDO $db): ?array { if(empty($_SESSION['admin_id'])) return null; $s=$db->prepare('SELECT a.*, s.slug store_slug, s.name store_name FROM admins a JOIN stores s ON s.id=a.store_id WHERE a.id=?');$s->execute([$_SESSION['admin_id']]);return $s->fetch() ?: null; }
function require_admin(PDO $db): array { $admin=admin_store($db); if(!$admin){header('Location: admin.php?page=login');exit;}return $admin; }
function cart(): array { return $_SESSION['cart'] ?? []; }
function cart_count(): int { return array_sum($_SESSION['cart'] ?? []); }
function money(float $value): string { return '₹' . number_format($value, 0); }
function safe_return(string $path, string $fallback): string { return (str_starts_with($path,'?') || str_starts_with($path,'index.php?')) ? $path : $fallback; }
