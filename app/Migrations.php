<?php
declare(strict_types=1);

function migrate_database(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
          migration VARCHAR(80) PRIMARY KEY,
          applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );

    apply_migration($pdo, '001_base', function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS printers (
              id INT AUTO_INCREMENT PRIMARY KEY,
              public_id CHAR(16) NOT NULL UNIQUE,
              hardware_hash CHAR(64) NOT NULL UNIQUE,
              publish_token CHAR(64) NOT NULL UNIQUE,
              recovery_token CHAR(64) NOT NULL UNIQUE,
              firmware_version VARCHAR(32) DEFAULT NULL,
              printer_name VARCHAR(80) DEFAULT NULL,
              leaderboard_opt_in TINYINT(1) NOT NULL DEFAULT 0,
              blocked TINYINT(1) NOT NULL DEFAULT 0,
              block_reason VARCHAR(255) DEFAULT NULL,
              first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS models (
              id INT AUTO_INCREMENT PRIMARY KEY,
              public_id CHAR(16) NOT NULL UNIQUE,
              printer_id INT NULL,
              model_name VARCHAR(120) NOT NULL,
              original_credits VARCHAR(255) DEFAULT \'\',
              license VARCHAR(32) NOT NULL DEFAULT \'CC-BY-NC\',
              layers INT NOT NULL,
              height_mm DECIMAL(8,2) NOT NULL,
              resin_ml DECIMAL(8,2) DEFAULT NULL,
              file_size BIGINT NOT NULL,
              checksum_sha256 CHAR(64) NOT NULL,
              preview_05_path VARCHAR(255) DEFAULT NULL,
              preview_1_path VARCHAR(255) DEFAULT NULL,
              download_path VARCHAR(255) NOT NULL,
              status ENUM(\'pending\',\'published\',\'hidden\',\'removed\') NOT NULL DEFAULT \'published\',
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT fk_models_printer FOREIGN KEY (printer_id) REFERENCES printers(id)
            )'
        );
        add_index_if_missing($pdo, 'models', 'idx_models_status_created', 'CREATE INDEX idx_models_status_created ON models(status, created_at)');
        add_index_if_missing($pdo, 'models', 'idx_models_printer_status', 'CREATE INDEX idx_models_printer_status ON models(printer_id, status)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS model_downloads (
              id INT AUTO_INCREMENT PRIMARY KEY,
              model_id INT NOT NULL,
              ip_hash CHAR(64) DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT fk_downloads_model FOREIGN KEY (model_id) REFERENCES models(id)
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admins (
              id INT AUTO_INCREMENT PRIMARY KEY,
              username VARCHAR(80) NOT NULL UNIQUE,
              password_hash VARCHAR(255) NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              last_login TIMESTAMP NULL DEFAULT NULL
            )'
        );
    });

    apply_migration($pdo, '002_social_and_admin_roles', function (PDO $pdo): void {
        add_column_if_missing($pdo, 'admins', 'role', 'ALTER TABLE admins ADD COLUMN role ENUM(\'super_admin\',\'admin\') NOT NULL DEFAULT \'admin\' AFTER password_hash');
        add_column_if_missing($pdo, 'admins', 'is_super', 'ALTER TABLE admins ADD COLUMN is_super TINYINT(1) NOT NULL DEFAULT 0 AFTER role');
        add_column_if_missing($pdo, 'admins', 'updated_at', 'ALTER TABLE admins ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER last_login');
        $pdo->exec('UPDATE admins SET role = \'super_admin\', is_super = 1 WHERE id = (SELECT first_admin.id FROM (SELECT MIN(id) AS id FROM admins) AS first_admin)');

        add_column_if_missing($pdo, 'models', 'download_count', 'ALTER TABLE models ADD COLUMN download_count INT NOT NULL DEFAULT 0 AFTER file_size');
        add_column_if_missing($pdo, 'models', 'rating_count', 'ALTER TABLE models ADD COLUMN rating_count INT NOT NULL DEFAULT 0 AFTER download_count');
        add_column_if_missing($pdo, 'models', 'rating_sum', 'ALTER TABLE models ADD COLUMN rating_sum INT NOT NULL DEFAULT 0 AFTER rating_count');
        add_column_if_missing($pdo, 'models', 'bookmark_count', 'ALTER TABLE models ADD COLUMN bookmark_count INT NOT NULL DEFAULT 0 AFTER rating_sum');

        add_column_if_missing($pdo, 'model_downloads', 'printer_id', 'ALTER TABLE model_downloads ADD COLUMN printer_id INT NULL AFTER model_id');
        add_index_if_missing($pdo, 'model_downloads', 'idx_downloads_printer', 'CREATE INDEX idx_downloads_printer ON model_downloads(printer_id)');
        add_index_if_missing($pdo, 'model_downloads', 'idx_downloads_model_printer_unique', 'CREATE UNIQUE INDEX idx_downloads_model_printer_unique ON model_downloads(model_id, printer_id)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS model_ratings (
              id INT AUTO_INCREMENT PRIMARY KEY,
              model_id INT NOT NULL,
              printer_id INT NOT NULL,
              rating TINYINT NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY idx_ratings_model_printer_unique (model_id, printer_id),
              CONSTRAINT fk_ratings_model FOREIGN KEY (model_id) REFERENCES models(id),
              CONSTRAINT fk_ratings_printer FOREIGN KEY (printer_id) REFERENCES printers(id)
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS model_bookmarks (
              id INT AUTO_INCREMENT PRIMARY KEY,
              model_id INT NOT NULL,
              printer_id INT NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY idx_bookmarks_model_printer_unique (model_id, printer_id),
              CONSTRAINT fk_bookmarks_model FOREIGN KEY (model_id) REFERENCES models(id),
              CONSTRAINT fk_bookmarks_printer FOREIGN KEY (printer_id) REFERENCES printers(id)
            )'
        );
    });

    apply_migration($pdo, '003_leaderboard_opt_in', function (PDO $pdo): void {
        add_column_if_missing($pdo, 'printers', 'leaderboard_opt_in', 'ALTER TABLE printers ADD COLUMN leaderboard_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER printer_name');
    });

    apply_migration($pdo, '004_boot_animations', function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS boot_animations (
              id INT AUTO_INCREMENT PRIMARY KEY,
              public_id CHAR(16) NOT NULL UNIQUE,
              printer_id INT NULL,
              animation_name VARCHAR(120) NOT NULL,
              install_name VARCHAR(40) NOT NULL,
              version VARCHAR(32) NOT NULL DEFAULT \'1.0.0\',
              original_credits VARCHAR(255) DEFAULT \'\',
              description VARCHAR(255) DEFAULT \'\',
              license VARCHAR(32) NOT NULL DEFAULT \'CC-BY-NC\',
              file_size BIGINT NOT NULL,
              download_count INT NOT NULL DEFAULT 0,
              checksum_sha256 CHAR(64) NOT NULL,
              download_path VARCHAR(255) NOT NULL,
              status ENUM(\'pending\',\'published\',\'hidden\',\'removed\') NOT NULL DEFAULT \'published\',
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT fk_boot_animations_printer FOREIGN KEY (printer_id) REFERENCES printers(id)
            )'
        );
        add_index_if_missing($pdo, 'boot_animations', 'idx_boot_animations_status_created', 'CREATE INDEX idx_boot_animations_status_created ON boot_animations(status, created_at)');
        add_index_if_missing($pdo, 'boot_animations', 'idx_boot_animations_printer_status', 'CREATE INDEX idx_boot_animations_printer_status ON boot_animations(printer_id, status)');
    });

    apply_migration($pdo, '005_boot_animation_downloads', function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS boot_animation_downloads (
              id INT AUTO_INCREMENT PRIMARY KEY,
              animation_id INT NOT NULL,
              printer_id INT NULL,
              ip_hash CHAR(64) DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT fk_boot_animation_downloads_animation FOREIGN KEY (animation_id) REFERENCES boot_animations(id),
              CONSTRAINT fk_boot_animation_downloads_printer FOREIGN KEY (printer_id) REFERENCES printers(id)
            )'
        );
        add_index_if_missing($pdo, 'boot_animation_downloads', 'idx_boot_animation_downloads_printer', 'CREATE INDEX idx_boot_animation_downloads_printer ON boot_animation_downloads(printer_id)');
        add_index_if_missing($pdo, 'boot_animation_downloads', 'idx_boot_animation_downloads_animation_printer_unique', 'CREATE UNIQUE INDEX idx_boot_animation_downloads_animation_printer_unique ON boot_animation_downloads(animation_id, printer_id)');
    });

    apply_migration($pdo, '006_boot_animation_version', function (PDO $pdo): void {
        add_column_if_missing($pdo, 'boot_animations', 'version', 'ALTER TABLE boot_animations ADD COLUMN version VARCHAR(32) NOT NULL DEFAULT \'1.0.0\' AFTER install_name');
    });

    apply_migration($pdo, '007_printer_backups', function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS printer_backups (
              printer_id INT NOT NULL PRIMARY KEY,
              backup_json MEDIUMTEXT NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT fk_printer_backups_printer FOREIGN KEY (printer_id) REFERENCES printers(id)
            )'
        );
    });

    apply_migration($pdo, '008_model_license', function (PDO $pdo): void {
        add_column_if_missing($pdo, 'models', 'license', 'ALTER TABLE models ADD COLUMN license VARCHAR(32) NOT NULL DEFAULT \'CC-BY-NC\' AFTER original_credits');
    });

    apply_migration($pdo, '009_model_dual_previews', function (PDO $pdo): void {
        add_column_if_missing($pdo, 'models', 'preview_05_path', 'ALTER TABLE models ADD COLUMN preview_05_path VARCHAR(255) DEFAULT NULL AFTER checksum_sha256');
        add_column_if_missing($pdo, 'models', 'preview_1_path', 'ALTER TABLE models ADD COLUMN preview_1_path VARCHAR(255) DEFAULT NULL AFTER preview_05_path');
    });

    apply_migration($pdo, '010_printer_recovery_token', function (PDO $pdo): void {
        add_column_if_missing($pdo, 'printers', 'recovery_token', 'ALTER TABLE printers ADD COLUMN recovery_token CHAR(64) NULL AFTER publish_token');
        $stmt = $pdo->query('SELECT id FROM printers WHERE recovery_token IS NULL OR recovery_token = ""');
        $update = $pdo->prepare('UPDATE printers SET recovery_token = ? WHERE id = ?');
        while ($row = $stmt->fetch()) {
            $update->execute([token(), $row['id']]);
        }
        add_index_if_missing($pdo, 'printers', 'idx_printers_recovery_token_unique', 'CREATE UNIQUE INDEX idx_printers_recovery_token_unique ON printers(recovery_token)');
        try {
            $pdo->exec('ALTER TABLE printers MODIFY recovery_token CHAR(64) NOT NULL');
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1060) {
                throw $e;
            }
        }
    });

    apply_migration($pdo, '011_printer_leaderboard_stats', function (PDO $pdo): void {
        add_column_if_missing($pdo, 'printers', 'lifetime_print_secs', 'ALTER TABLE printers ADD COLUMN lifetime_print_secs BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER leaderboard_opt_in');
    });
}

function apply_migration(PDO $pdo, string $migration, callable $callback): void
{
    $stmt = $pdo->prepare('SELECT migration FROM schema_migrations WHERE migration = ? LIMIT 1');
    $stmt->execute([$migration]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $callback($pdo);
    $insert = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
    $insert->execute([$migration]);
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $sql): void
{
    if (column_exists($pdo, $table, $column)) {
        return;
    }
    $pdo->exec($sql);
}

function add_index_if_missing(PDO $pdo, string $table, string $index, string $sql): void
{
    if (index_exists($pdo, $table, $index)) {
        return;
    }
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1061) {
            throw $e;
        }
    }
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}
