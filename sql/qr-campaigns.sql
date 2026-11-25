-- À exécuter une fois après analytics.sql sur une base déjà installée.
USE le_gentleman;

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

ALTER TABLE orders
  ADD COLUMN campaign_id BIGINT UNSIGNED NULL AFTER delivery_zone_id,
  ADD INDEX idx_orders_campaign_created (campaign_id,created_at),
  ADD CONSTRAINT fk_orders_campaign FOREIGN KEY (campaign_id) REFERENCES qr_campaigns(id) ON DELETE SET NULL;

ALTER TABLE analytics_events
  MODIFY event_type ENUM('page_view','product_view','product_click','share_click','buy_click','qr_scan') NOT NULL,
  ADD COLUMN campaign_id BIGINT UNSIGNED NULL AFTER product_id,
  ADD INDEX idx_events_campaign_type (campaign_id,event_type,created_at),
  ADD CONSTRAINT fk_events_campaign FOREIGN KEY (campaign_id) REFERENCES qr_campaigns(id) ON DELETE SET NULL;
