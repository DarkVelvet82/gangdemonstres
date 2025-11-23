-- Migration 004: Ajouter les champs admin à la table users
-- À exécuter pour permettre la connexion admin

-- Ajouter les colonnes nécessaires pour l'authentification admin
ALTER TABLE `wp_objectif_users`
ADD COLUMN `username` VARCHAR(100) NULL AFTER `prenom`,
ADD COLUMN `password` VARCHAR(255) NULL AFTER `username`,
ADD COLUMN `is_admin` TINYINT(1) DEFAULT 0 AFTER `password`,
ADD INDEX `idx_username` (`username`);

-- Créer un utilisateur admin par défaut (mot de passe: admin123)
-- Le hash correspond à password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO `wp_objectif_users` (`prenom`, `username`, `password`, `email`, `code_unique`, `is_admin`)
VALUES ('Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@gangdemonstres.local', 'ADMIN', 1)
ON DUPLICATE KEY UPDATE `is_admin` = 1;
