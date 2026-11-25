-- À exécuter sur une base déjà installée ; les créations et conversions sont relançables.
USE le_gentleman;

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

INSERT INTO gift_box_text_components (gift_box_id,component_name,quantity,sizes,colors)
SELECT gbc.gift_box_id,p.name,gbc.quantity,
  COALESCE((SELECT GROUP_CONCAT(DISTINCT v.size ORDER BY v.size SEPARATOR ', ') FROM variants v WHERE v.product_id=p.id),''),
  COALESCE((SELECT GROUP_CONCAT(DISTINCT v.color ORDER BY v.color SEPARATOR ', ') FROM variants v WHERE v.product_id=p.id),'')
FROM gift_box_components gbc
JOIN products p ON p.id=gbc.component_product_id
WHERE NOT EXISTS (
  SELECT 1 FROM gift_box_text_components tc
  WHERE tc.gift_box_id=gbc.gift_box_id
);

INSERT INTO variants (product_id,size,color,stock)
SELECT p.id,'Unique','Coffret',0
FROM products p
LEFT JOIN categories c ON c.id=p.category_id
WHERE (p.is_gift_box=1 OR c.slug='coffrets')
  AND NOT EXISTS (SELECT 1 FROM variants v WHERE v.product_id=p.id AND v.size='Unique' AND v.color='Coffret');
