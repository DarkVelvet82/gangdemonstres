-- Migration: Suppression de la colonne slug de la table game_sets
-- Date: 2024-10-31
-- Description: Le slug n'est plus utilisé pour les jeux/extensions non plus

-- Supprimer la colonne slug de la table game_sets
ALTER TABLE wp_objectif_game_sets DROP COLUMN slug;
