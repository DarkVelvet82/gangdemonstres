-- Migration: Ajout d'index de performance
-- Date: 2024-11-22
-- Description: Index composites pour optimiser les requetes frequentes

-- Index composite pour difficulty_config (requete frequente: game_set_id + difficulty + types_count)
ALTER TABLE wp_objectif_difficulty_config
ADD INDEX idx_full_config (game_set_id, difficulty, types_count);

-- Index composite pour card_types (requete JOIN frequente)
ALTER TABLE wp_objectif_card_types
ADD INDEX idx_card_type_combo (card_id, type_id);

-- Index pour games.game_set_id (utilise dans plusieurs requetes)
ALTER TABLE wp_objectif_games
ADD INDEX idx_game_set_id (game_set_id);

-- Index composite pour players (requetes frequentes par game_id + used)
ALTER TABLE wp_objectif_players
ADD INDEX idx_game_used (game_id, used);

-- Index composite pour set_types (requetes JOIN frequentes)
ALTER TABLE wp_objectif_set_types
ADD INDEX idx_set_type_combo (game_set_id, type_id);
