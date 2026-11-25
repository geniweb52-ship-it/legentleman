-- Thèmes de fête (créées aussi automatiquement à la première ouverture de admin/themes.php).
CREATE TABLE IF NOT EXISTS settings (
  name VARCHAR(60) PRIMARY KEY,
  value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS holiday_themes (
  theme_key VARCHAR(40) PRIMARY KEY,
  banner VARCHAR(255) NULL,
  title VARCHAR(160) NULL,
  text VARCHAR(500) NULL,
  cta_label VARCHAR(60) NULL,
  cta_url VARCHAR(255) NULL,
  image VARCHAR(255) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  auto_enabled TINYINT(1) NOT NULL DEFAULT 1,
  effect_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
