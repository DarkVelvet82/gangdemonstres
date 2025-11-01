<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Ajout de la colonne quantity à la table cards...\n";

    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "cards LIKE 'quantity'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "La colonne quantity existe déjà.\n";
    } else {
        $sql = "ALTER TABLE " . DB_PREFIX . "cards ADD COLUMN quantity INT DEFAULT 1 AFTER game_set_id";
        $pdo->exec($sql);
        echo "Colonne quantity ajoutée avec succès !\n";
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
