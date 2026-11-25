CREATE DATABASE IF NOT EXISTS le_gentleman CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE le_gentleman;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  description TEXT NULL,
  brand VARCHAR(120) NULL,
  price INT UNSIGNED NOT NULL,
  old_price INT UNSIGNED NULL,
  ready_days SMALLINT UNSIGNED NULL,
  image VARCHAR(255) NULL,
  image_back VARCHAR(255) NULL,
  is_gift_box TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  size VARCHAR(40) NOT NULL,
  color VARCHAR(60) NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY product_variant (product_id,size,color),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gift_box_components (
  gift_box_id INT UNSIGNED NOT NULL,
  component_product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (gift_box_id,component_product_id),
  FOREIGN KEY (gift_box_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (component_product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gift_box_text_components (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gift_box_id INT UNSIGNED NOT NULL,
  component_name VARCHAR(160) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  sizes TEXT NOT NULL,
  colors TEXT NOT NULL,
  FOREIGN KEY (gift_box_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  fee INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_zone_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS qr_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  destination_type ENUM('site','product','direct') NOT NULL,
  product_id INT UNSIGNED NULL,
  target_path VARCHAR(512) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_qr_campaign_created (created_at),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  delivery_zone_id INT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NULL,
  customer_name VARCHAR(160) NOT NULL,
  first_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NULL,
  phone VARCHAR(30) NOT NULL,
  address VARCHAR(255) NOT NULL,
  note VARCHAR(160) NULL,
  payment_method ENUM('wave','orange','delivery') NOT NULL,
  subtotal INT UNSIGNED NOT NULL,
  delivery_fee INT UNSIGNED NOT NULL,
  total INT UNSIGNED NOT NULL,
  status ENUM('nouvelle','payee','en_preparation','expediee','livree','annulee') NOT NULL DEFAULT 'nouvelle',
  wave_session_id VARCHAR(40) NULL,
  wave_transaction_id VARCHAR(60) NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orders_campaign_created (campaign_id,created_at),
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (delivery_zone_id) REFERENCES delivery_zones(id) ON DELETE SET NULL,
  FOREIGN KEY (campaign_id) REFERENCES qr_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NULL,
  product_name VARCHAR(160) NOT NULL,
  size VARCHAR(40) NOT NULL,
  color VARCHAR(60) NOT NULL,
  price INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  ready_date DATE NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_item_components (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_item_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NULL,
  product_name VARCHAR(160) NOT NULL,
  size VARCHAR(40) NOT NULL,
  color VARCHAR(60) NOT NULL,
  quantity_per_bundle INT UNSIGNED NOT NULL,
  FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('gerant','vendeur','preparateur') NOT NULL DEFAULT 'vendeur',
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type ENUM('page_view','product_view','product_click','share_click','buy_click','qr_scan') NOT NULL,
  visitor_id CHAR(64) NOT NULL,
  session_id CHAR(64) NOT NULL,
  product_id INT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NULL,
  path VARCHAR(255) NOT NULL,
  referrer VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_events_created (created_at),
  INDEX idx_events_type_product (event_type,product_id),
  INDEX idx_events_campaign_type (campaign_id,event_type,created_at),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  FOREIGN KEY (campaign_id) REFERENCES qr_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name,slug,sort_order) VALUES
 ('Tous','tous',0),('Hauts','hauts',1),('Bas','bas',2),('Shorts','shorts',3),
 ('Chaussures','chaussures',4),('Coffrets','coffrets',5),('Maillots','maillots',6);

INSERT IGNORE INTO delivery_zones (name,fee) VALUES
 ('Dakar centre',2000),('Ouakam-Ngor-Almadies-Yoff',2500),
 ('Parcelles-Grand-Yoff',3000),('Pikine-Guediawaye-Rufisque',3500),('Régions',5000);

INSERT IGNORE INTO admins (email,password_hash,role) VALUES
 ('admin@legentleman.sn','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'gerant');
