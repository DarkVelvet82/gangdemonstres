<?php
require_once __DIR__ . '/../config/database.php';

try {

    echo "Ajout de la colonne is_visible à la table cards...\n";

    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "cards LIKE 'is_visible'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "La colonne is_visible existe déjà.\n";
    } else {
        $sql = "ALTER TABLE " . DB_PREFIX . "cards ADD COLUMN is_visible TINYINT(1) DEFAULT 1 AFTER power_text";
        $pdo->exec($sql);
        echo "Colonne is_visible ajoutée avec succès !\n";
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
