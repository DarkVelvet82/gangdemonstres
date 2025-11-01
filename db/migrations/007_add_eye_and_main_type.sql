-- Ajout du pouvoir "oeil" et du type principal
ALTER TABLE wp_objectif_cards
ADD COLUMN has_eye TINYINT(1) DEFAULT 0 AFTER is_visible,
ADD COLUMN main_type_id BIGINT UNSIGNED NULL AFTER has_eye,
ADD FOREIGN KEY (main_type_id) REFERENCES wp_objectif_types(id) ON DELETE SET NULL;
