-- Ajout du champ is_visible pour permettre de cacher des cartes en frontend
ALTER TABLE wp_objectif_cards ADD COLUMN is_visible TINYINT(1) DEFAULT 1 AFTER power_text;
