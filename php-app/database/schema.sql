CREATE DATABASE IF NOT EXISTS ecom_minutes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecom_minutes;

CREATE TABLE tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  tagline VARCHAR(255) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  logo_url VARCHAR(1024) NOT NULL DEFAULT '',
  hero_title VARCHAR(255) NOT NULL DEFAULT '',
  hero_image_url VARCHAR(1024) NOT NULL DEFAULT '',
  primary_color CHAR(7) NOT NULL DEFAULT '#6D5EF2',
  secondary_color CHAR(7) NOT NULL DEFAULT '#E9E6FF',
  accent_color CHAR(7) NOT NULL DEFAULT '#F37B5B',
  theme ENUM('minimal','neomorphism','skeuomorphism','glassmorphism','claymorphism','maximalism','brutalism') NOT NULL DEFAULT 'minimal',
  show_brand_name TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
  name VARCHAR(160) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_tenant_email (tenant_id,email),
  CONSTRAINT users_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  UNIQUE KEY unique_tenant_category (tenant_id,slug),
  CONSTRAINT categories_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(280) NOT NULL,
  sku VARCHAR(120) NOT NULL DEFAULT '',
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  compare_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  description TEXT NOT NULL,
  image_url VARCHAR(1024) NOT NULL DEFAULT '',
  stock INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_tenant_product (tenant_id,slug),
  FULLTEXT KEY products_search (name,description),
  CONSTRAINT products_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_categories (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(product_id,category_id),
  CONSTRAINT pc_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT pc_category_fk FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE banners (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(500) NOT NULL DEFAULT '',
  image_url VARCHAR(1024) NOT NULL DEFAULT '',
  cta_label VARCHAR(80) NOT NULL DEFAULT 'Shop now',
  cta_url VARCHAR(1024) NOT NULL DEFAULT '/shop',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT banners_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE coupons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  title VARCHAR(180) NOT NULL DEFAULT '',
  description VARCHAR(500) NOT NULL DEFAULT '',
  discount_type ENUM('percent','amount') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  minimum_order DECIMAL(12,2) NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY unique_tenant_coupon (tenant_id,code),
  CONSTRAINT coupons_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  order_number VARCHAR(80) NOT NULL,
  customer_name VARCHAR(160) NOT NULL,
  customer_email VARCHAR(255) NOT NULL,
  customer_phone VARCHAR(30) NOT NULL,
  shipping_address TEXT NOT NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) NOT NULL,
  pincode VARCHAR(12) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL,
  coupon_code VARCHAR(50) NOT NULL DEFAULT '',
  payment_method ENUM('prepaid','cod') NOT NULL DEFAULT 'prepaid',
  payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  order_status ENUM('new','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'new',
  shiprocket_order_id VARCHAR(100) NOT NULL DEFAULT '',
  awb_code VARCHAR(100) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_tenant_order (tenant_id,order_number),
  CONSTRAINT orders_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT orders_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(255) NOT NULL,
  sku VARCHAR(120) NOT NULL DEFAULT '',
  unit_price DECIMAL(12,2) NOT NULL,
  quantity INT NOT NULL,
  CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;
