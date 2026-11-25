-- Canal de la commande : site (formulaire de commande) ou WhatsApp (bouton « Commander sur WhatsApp »).
-- Ajoutée aussi automatiquement par le site si elle manque (orders_channel_ensure).
ALTER TABLE orders ADD COLUMN channel ENUM('site','whatsapp') NOT NULL DEFAULT 'site' AFTER payment_method;
