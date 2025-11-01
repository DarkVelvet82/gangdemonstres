<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Ajout des colonnes has_eye et main_type_id...\n";

    // Vérifier si has_eye existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "cards LIKE 'has_eye'");
    $has_eye_exists = $stmt->fetch();

    if (!$has_eye_exists) {
        $pdo->exec("ALTER TABLE " . DB_PREFIX . "cards ADD COLUMN has_eye TINYINT(1) DEFAULT 0 AFTER is_visible");
        echo "Colonne has_eye ajoutée.\n";
    } else {
        echo "Colonne has_eye existe déjà.\n";
    }

    // Vérifier si main_type_id existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "cards LIKE 'main_type_id'");
    $main_type_exists = $stmt->fetch();

    if (!$main_type_exists) {
        $pdo->exec("ALTER TABLE " . DB_PREFIX . "cards ADD COLUMN main_type_id BIGINT UNSIGNED NULL AFTER has_eye");
        echo "Colonne main_type_id ajoutée.\n";

        // Ajouter la clé étrangère
        $pdo->exec("ALTER TABLE " . DB_PREFIX . "cards ADD FOREIGN KEY (main_type_id) REFERENCES " . DB_PREFIX . "types(id) ON DELETE SET NULL");
        echo "Clé étrangère ajoutée.\n";
    } else {
        echo "Colonne main_type_id existe déjà.\n";
    }

    // Vérifier la structure finale
    echo "\nStructure de la table cards :\n";
    $stmt = $pdo->query("DESCRIBE " . DB_PREFIX . "cards");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
