-- À exécuter une fois pour ajouter le suivi de fréquentation à une base existante.
-- Pour le suivi par campagne QR, exécuter ensuite qr-campaigns.sql.
USE le_gentleman;

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
