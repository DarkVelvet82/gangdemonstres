-- Ajout de la colonne image_url à la table game_sets
ALTER TABLE wp_objectif_game_sets ADD COLUMN image_url VARCHAR(500) NULL AFTER description;
