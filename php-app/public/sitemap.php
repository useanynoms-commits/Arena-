<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$db=db();$tenant=current_tenant($db);$base=rtrim(env_value('APP_URL'),'/');
$urls=['/','/shop','/about'];$s=$db->prepare('SELECT slug,updated_at FROM products WHERE tenant_id=? AND is_active=1');$s->execute([$tenant['id']]);
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($urls as $url)echo '<url><loc>'.e($base.$url.'?store='.$tenant['slug']).'</loc></url>';
foreach($s->fetchAll() as $product)echo '<url><loc>'.e($base.'/product/'.$product['slug'].'?store='.$tenant['slug']).'</loc><lastmod>'.substr($product['updated_at'],0,10).'</lastmod></url>';
echo '</urlset>';
