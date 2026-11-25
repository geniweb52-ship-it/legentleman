-- Migration : paiement Wave, facture, prénom/nom (à exécuter une fois sur une base existante)
USE le_gentleman;
ALTER TABLE orders
  ADD COLUMN first_name VARCHAR(80) NULL AFTER customer_name,
  ADD COLUMN last_name VARCHAR(80) NULL AFTER first_name,
  ADD COLUMN note VARCHAR(160) NULL AFTER address,
  ADD COLUMN wave_session_id VARCHAR(40) NULL,
  ADD COLUMN wave_transaction_id VARCHAR(60) NULL,
  ADD COLUMN paid_at DATETIME NULL;
INSERT INTO categories (name, slug, sort_order) SELECT 'Accessoires','accessoires',5 WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug='accessoires');

CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type ENUM('page_view','product_view','product_click','share_click','buy_click') NOT NULL,
  visitor_id CHAR(64) NOT NULL,
  session_id CHAR(64) NOT NULL,
  product_id INT UNSIGNED NULL,
  path VARCHAR(255) NOT NULL,
  referrer VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_events_created (created_at),
  INDEX idx_events_type_product (event_type,product_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;
