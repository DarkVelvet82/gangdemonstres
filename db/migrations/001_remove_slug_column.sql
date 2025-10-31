-- Migration: Suppression de la colonne slug de la table types
-- Date: 2024-10-31
-- Description: Le slug n'est plus utilisé, on utilise uniquement l'ID dans la logique

-- Supprimer la colonne slug de la table types
ALTER TABLE wp_objectif_types DROP COLUMN slug;
