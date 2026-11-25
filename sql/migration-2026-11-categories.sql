-- Image de couverture par catégorie + catégorie « Tenues traditionnelles ».
-- Les chemins sont relatifs au site (ex. assets/img/categories/hauts.webp).
ALTER TABLE categories ADD COLUMN image VARCHAR(255) NULL AFTER slug;

INSERT IGNORE INTO categories (name, slug, sort_order) VALUES ('Tenues traditionnelles', 'tenues-traditionnelles', 2);

UPDATE categories SET sort_order = CASE slug
  WHEN 'tous' THEN 0 WHEN 'hauts' THEN 1 WHEN 'tenues-traditionnelles' THEN 2 WHEN 'bas' THEN 3
  WHEN 'shorts' THEN 4 WHEN 'chaussures' THEN 5 WHEN 'accessoires' THEN 6 WHEN 'coffrets' THEN 7
  WHEN 'maillots' THEN 8 ELSE sort_order END;

UPDATE categories SET image = CONCAT('assets/img/categories/', slug, '.webp')
WHERE slug IN ('hauts','tenues-traditionnelles','bas','shorts','chaussures','accessoires','coffrets','maillots') AND image IS NULL;
