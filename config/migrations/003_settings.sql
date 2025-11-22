-- Migration 003: Table des paramètres globaux
-- Date: 2024-11-22

CREATE TABLE IF NOT EXISTS `wp_objectif_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valeurs par défaut
INSERT INTO `wp_objectif_settings` (`setting_key`, `setting_value`) VALUES
('site_logo', ''),
('site_name', 'Gang de Monstres')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
