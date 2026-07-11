CREATE TABLE IF NOT EXISTS printers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(16) NOT NULL UNIQUE,
  hardware_hash CHAR(64) NOT NULL UNIQUE,
  publish_token CHAR(64) NOT NULL UNIQUE,
  firmware_version VARCHAR(32) DEFAULT NULL,
  printer_name VARCHAR(80) DEFAULT NULL,
  leaderboard_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  blocked TINYINT(1) NOT NULL DEFAULT 0,
  block_reason VARCHAR(255) DEFAULT NULL,
  first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS models (
  id INT AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(16) NOT NULL UNIQUE,
  printer_id INT NULL,
  model_name VARCHAR(120) NOT NULL,
  original_credits VARCHAR(255) DEFAULT '',
  layers INT NOT NULL,
  height_mm DECIMAL(8,2) NOT NULL,
  resin_ml DECIMAL(8,2) DEFAULT NULL,
  file_size BIGINT NOT NULL,
  download_count INT NOT NULL DEFAULT 0,
  rating_count INT NOT NULL DEFAULT 0,
  rating_sum INT NOT NULL DEFAULT 0,
  bookmark_count INT NOT NULL DEFAULT 0,
  checksum_sha256 CHAR(64) NOT NULL,
  preview_path VARCHAR(255) DEFAULT NULL,
  download_path VARCHAR(255) NOT NULL,
  status ENUM('pending','published','hidden','removed') NOT NULL DEFAULT 'published',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_models_printer FOREIGN KEY (printer_id) REFERENCES printers(id)
);

CREATE INDEX idx_models_status_created ON models(status, created_at);
CREATE INDEX idx_models_printer_status ON models(printer_id, status);

CREATE TABLE IF NOT EXISTS model_downloads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  model_id INT NOT NULL,
  printer_id INT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_downloads_model FOREIGN KEY (model_id) REFERENCES models(id)
);
CREATE INDEX idx_downloads_printer ON model_downloads(printer_id);
CREATE UNIQUE INDEX idx_downloads_model_printer_unique ON model_downloads(model_id, printer_id);

CREATE TABLE IF NOT EXISTS model_ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  model_id INT NOT NULL,
  printer_id INT NOT NULL,
  rating TINYINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_ratings_model_printer_unique (model_id, printer_id),
  CONSTRAINT fk_ratings_model FOREIGN KEY (model_id) REFERENCES models(id),
  CONSTRAINT fk_ratings_printer FOREIGN KEY (printer_id) REFERENCES printers(id)
);

CREATE TABLE IF NOT EXISTS model_bookmarks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  model_id INT NOT NULL,
  printer_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_bookmarks_model_printer_unique (model_id, printer_id),
  CONSTRAINT fk_bookmarks_model FOREIGN KEY (model_id) REFERENCES models(id),
  CONSTRAINT fk_bookmarks_printer FOREIGN KEY (printer_id) REFERENCES printers(id)
);

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
  is_super TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration VARCHAR(80) PRIMARY KEY,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
