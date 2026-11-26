-- Paiement en ligne GeniusPay (à exécuter une fois sur une base existante).
-- Fait aussi automatiquement par le site à la première commande GeniusPay (orders_geniuspay_ensure).
ALTER TABLE orders
  MODIFY payment_method ENUM('wave','orange','geniuspay','delivery') NOT NULL,
  ADD COLUMN geniuspay_reference VARCHAR(40) NULL AFTER wave_transaction_id;
