-- Optional scan history for AI + AR Smart Object Recognition System
-- Import with phpMyAdmin or:  mysql -u root -p < database/database.sql

CREATE DATABASE IF NOT EXISTS ai_ar_scanner
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai_ar_scanner;

CREATE TABLE IF NOT EXISTS scan_history (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    object_label  VARCHAR(80)   NOT NULL,
    product_name  VARCHAR(120)  NOT NULL,
    manufacturer  VARCHAR(80)   NOT NULL,
    specification VARCHAR(300)  NOT NULL,
    description   VARCHAR(500)  NOT NULL,
    confidence    DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
    provider      VARCHAR(20)   NOT NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_created (created_at),
    KEY idx_product (product_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
