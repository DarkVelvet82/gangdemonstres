-- Migration 002: Tables utilisateurs et joueurs fréquents
-- À exécuter après la migration initiale

-- Table des utilisateurs (créateurs de parties)
CREATE TABLE IF NOT EXISTS `wp_objectif_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `prenom` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `code_unique` VARCHAR(5) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login_at` TIMESTAMP NULL,
    INDEX `idx_code_unique` (`code_unique`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des joueurs fréquents (liés à un utilisateur)
CREATE TABLE IF NOT EXISTS `wp_objectif_user_players` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `player_name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `wp_objectif_users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    UNIQUE KEY `unique_user_player` (`user_id`, `player_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter user_id à la table games pour lier les parties au créateur
-- Note: Sans foreign key pour éviter les problèmes de compatibilité de types
ALTER TABLE `wp_objectif_games`
ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`,
ADD INDEX `idx_games_user_id` (`user_id`);
