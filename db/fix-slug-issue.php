<?php
/**
 * Script pour forcer la suppression de la colonne slug
 */

require_once __DIR__ . '/../config/database.php';

echo "<h1>Correction du problème de slug</h1>";
echo "<pre>";

// Vérifier d'abord la structure actuelle
echo "\n=== Structure actuelle de wp_objectif_types ===\n";
try {
    $stmt = $pdo->query("DESCRIBE " . DB_PREFIX . "types");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_slug = false;
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
        if ($col['Field'] === 'slug') {
            $has_slug = true;
        }
    }

    if ($has_slug) {
        echo "\n❌ La colonne 'slug' existe encore!\n";
        echo "\n=== Tentative de suppression de la colonne slug ===\n";

        try {
            $pdo->exec("ALTER TABLE " . DB_PREFIX . "types DROP COLUMN slug");
            echo "✅ Colonne 'slug' supprimée avec succès!\n";
        } catch (PDOException $e) {
            echo "❌ Erreur lors de la suppression: " . $e->getMessage() . "\n";
        }

        // Vérifier à nouveau
        echo "\n=== Vérification après suppression ===\n";
        $stmt = $pdo->query("DESCRIBE " . DB_PREFIX . "types");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo $col['Field'] . "\n";
        }
    } else {
        echo "\n✅ La colonne 'slug' n'existe pas (c'est bon!)\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

// Faire de même pour game_sets
echo "\n\n=== Structure actuelle de wp_objectif_game_sets ===\n";
try {
    $stmt = $pdo->query("DESCRIBE " . DB_PREFIX . "game_sets");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_slug = false;
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
        if ($col['Field'] === 'slug') {
            $has_slug = true;
        }
    }

    if ($has_slug) {
        echo "\n❌ La colonne 'slug' existe encore dans game_sets!\n";
        echo "\n=== Tentative de suppression de la colonne slug ===\n";

        try {
            $pdo->exec("ALTER TABLE " . DB_PREFIX . "game_sets DROP COLUMN slug");
            echo "✅ Colonne 'slug' supprimée avec succès!\n";
        } catch (PDOException $e) {
            echo "❌ Erreur lors de la suppression: " . $e->getMessage() . "\n";
        }
    } else {
        echo "\n✅ La colonne 'slug' n'existe pas dans game_sets (c'est bon!)\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== Fin du script ===\n";
echo "</pre>";
?>
