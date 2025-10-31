-- Migration: Création de la table cards
-- Date: 2024-10-31
-- Description: Table pour stocker toutes les cartes du jeu (monstres et coups bas)

CREATE TABLE IF NOT EXISTS wp_objectif_cards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    card_type ENUM('monster', 'dirty_trick') NOT NULL DEFAULT 'monster',
    game_set_id BIGINT UNSIGNED NOT NULL,
    image_url TEXT NULL,
    power_text TEXT NULL,
    qr_code_url TEXT NULL,
    display_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_game_set (game_set_id),
    INDEX idx_card_type (card_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison entre cartes et types (pour les monstres uniquement)
CREATE TABLE IF NOT EXISTS wp_objectif_card_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    card_id BIGINT UNSIGNED NOT NULL,
    type_id BIGINT UNSIGNED NULL,
    quantity INT NOT NULL DEFAULT 0,
    INDEX idx_card (card_id),
    INDEX idx_type (type_id),
    FOREIGN KEY (card_id) REFERENCES wp_objectif_cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
