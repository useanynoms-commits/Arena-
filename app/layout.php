<?php
require_once __DIR__ . '/bootstrap.php';

function public_head(array $store, string $title = ''): void {
    $title = $title ?: ($store['name'] ?: 'Your Store');
    $theme = in_array($store['theme'], ['minimal','brutalist','editorial','playful','luxe'], true) ? $store['theme'] : 'minimal';
    $style = '--brand:' . e($store['primary_color']) . ';--soft:' . e($store['secondary_color']) . ';--accent:' . e($store['accent_color']) . ';';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="' . e($store['tagline']) . '"><title>' . e($title) . '</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Playfair+Display:ital,wght@0,600;0,600;1,600&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/app.css"></head><body class="theme-' . e($theme) . '" style="' . $style . '">';
}
function brand_mark(array $store): string {
    $name = e($store['name'] ?: 'YOUR BRAND');
    $logo = $store['logo'] ? '<img src="' . e($store['logo']) . '" alt="">' : '';
    $text = (!$store['logo'] || (int)$store['show_brand_name']) ? '<span>' . $name . '</span>' : '';
    return '<a class="store-brand" href="' . store_url($store) . '">' . $logo . $text . '</a>';
}
function public_nav(PDO $db, array $store): void {
    $coupons = active_coupons($db, (int)$store['id']);
    echo '<div class="offer-bar">';
    if ($coupons) { foreach ($coupons as $i => $coupon) { echo '<div class="offer-item' . ($i ? ' is-hidden' : '') . '"><b>' . e($coupon['title'] ?: 'Store offer') . '</b><span>' . e($coupon['description'] ?: ('Use code ' . $coupon['code'])) . '</span><button class="copy-coupon" data-code="' . e($coupon['code']) . '">Copy ' . e($coupon['code']) . '</button></div>'; } }
    else echo '<div class="offer-item"><b>Your offers will appear here</b><span>Create coupons and announcements in the admin workspace.</span></div>';
    echo '</div><header class="site-header"><button class="menu-button" data-menu-toggle aria-label="Open navigation">☰</button>' . brand_mark($store) . '<nav data-menu><a href="' . store_url($store, 'shop') . '">Shop</a><a href="' . store_url($store, 'about') . '">About</a><a href="' . store_url($store, 'search') . '">Search</a></nav><div class="header-tools"><form action="" method="get" class="header-search"><input type="hidden" name="r" value="search"><input type="hidden" name="store" value="' . e($store['slug']) . '"><input name="q" aria-label="Search products" placeholder="Search"></form><a class="bag-link" href="' . store_url($store, 'cart') . '">Bag <i>' . cart_count() . '</i></a></div></header>';
}
function public_footer(array $store): void {
    echo '<footer class="site-footer"><div>' . brand_mark($store) . '<p>' . e($store['tagline'] ?: 'Your store tagline will live here.') . '</p></div><div><b>Shop</b><a href="' . store_url($store,'shop') . '">All products</a><a href="' . store_url($store,'search') . '">Search</a></div><div><b>Information</b><a href="' . store_url($store,'about') . '">About</a><a href="' . store_url($store,'cart') . '">Your bag</a></div><div><b>Store owner?</b><a href="admin.php">Admin workspace</a><a href="setup.php">Setup wizard</a></div></footer><script src="assets/app.js"></script></body></html>';
}
function admin_head(string $title): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/app.css"></head><body class="admin-body">';
}
function admin_shell_start(array $store, string $active, string $title): void {
    $links = ['overview'=>'Overview','products'=>'Products','categories'=>'Categories','banners'=>'Hero banners','coupons'=>'Coupons & offers','storefront'=>'Storefront','orders'=>'Orders'];
    echo '<div class="admin-shell"><aside class="admin-sidebar"><a class="admin-brand" href="admin.php"><span>✦</span><b>' . e($store['name'] ?: 'Your store') . '</b></a><nav>';
    foreach($links as $key=>$label) echo '<a class="' . ($active===$key?'active':'') . '" href="' . admin_url($key) . '">' . e($label) . '</a>';
    echo '</nav><div class="admin-sidebar-bottom"><a href="' . store_url($store) . '">View storefront ↗</a><a href="admin.php?page=logout">Log out</a></div></aside><main class="admin-main"><header class="admin-header"><div><span>Store manager</span><h1>' . e($title) . '</h1></div><a class="button dark small" href="' . store_url($store) . '">Open store ↗</a></header><section class="admin-content">';
}
function admin_shell_end(): void { echo '</section></main></div><script src="assets/app.js"></script></body></html>'; }
function render_flash(): void { if($flash=consume_flash()) echo '<div class="flash ' . e($flash[0]) . '">' . e($flash[1]) . '</div>'; }
