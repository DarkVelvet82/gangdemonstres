-- Ajout de la colonne bonus_players à la table game_sets
-- Pour le jeu de base : nombre de joueurs max de base (ex: 4)
-- Pour les extensions : bonus de joueurs ajoutés (ex: 2)
ALTER TABLE wp_objectif_game_sets ADD COLUMN bonus_players INT DEFAULT 0 AFTER is_base_game;

-- Mettre à jour les jeux existants avec des valeurs par défaut
-- Jeu de base = 4 joueurs, Extensions = +2 joueurs
UPDATE wp_objectif_game_sets SET bonus_players = 4 WHERE is_base_game = 1;
UPDATE wp_objectif_game_sets SET bonus_players = 2 WHERE is_base_game = 0;
