-- Migration: Suppression de la colonne emoji de la table types
-- Date: 2024-10-31
-- Description: On utilise uniquement des images uploadées maintenant

-- Supprimer la colonne emoji de la table types
ALTER TABLE wp_objectif_types DROP COLUMN emoji;
