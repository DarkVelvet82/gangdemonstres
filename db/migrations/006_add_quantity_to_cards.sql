-- Ajout du champ quantity pour indiquer le nombre d'exemplaires de chaque carte dans le deck
ALTER TABLE wp_objectif_cards ADD COLUMN quantity INT DEFAULT 1 AFTER game_set_id;
