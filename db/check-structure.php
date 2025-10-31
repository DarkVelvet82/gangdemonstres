<?php
require_once __DIR__ . '/../config/database.php';

echo "<h1>Structure de la table wp_objectif_types</h1>";
echo "<pre>";

try {
    $stmt = $pdo->query("DESCRIBE " . DB_PREFIX . "types");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Colonnes de la table:\n\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}

echo "</pre>";
?>
