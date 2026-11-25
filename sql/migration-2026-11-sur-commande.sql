-- Habits sur commande (ex. habits traditionnels).
-- products.ready_days : délai de confection en jours (vide = disponible immédiatement).
-- order_items.ready_date : date à laquelle l'article sera prêt, calculée au moment de la commande.
ALTER TABLE products ADD COLUMN ready_days SMALLINT UNSIGNED NULL AFTER old_price;
ALTER TABLE order_items ADD COLUMN ready_date DATE NULL AFTER quantity;
