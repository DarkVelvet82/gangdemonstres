<?php
/**
 * Script pour exécuter les migrations SQL
 * Usage: Accédez à ce fichier via le navigateur ou en ligne de commande
 */

require_once __DIR__ . '/../config/database.php';

echo "<h1>Exécution des migrations</h1>";
echo "<pre>";

// Récupérer tous les fichiers de migration
$migrations_dir = __DIR__ . '/migrations/';
$migration_files = glob($migrations_dir . '*.sql');

if (empty($migration_files)) {
    echo "Aucune migration trouvée.\n";
    exit;
}

sort($migration_files);

foreach ($migration_files as $file) {
    $filename = basename($file);
    echo "\n=== Migration: $filename ===\n";

    try {
        $sql = file_get_contents($file);

        // Exécuter chaque requête séparément (au cas où il y en a plusieurs)
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                $pdo->exec($statement);
            }
        }

        echo "✅ Migration réussie!\n";

    } catch (PDOException $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";

        // Si l'erreur est "column doesn't exist", c'est probablement déjà fait
        if (strpos($e->getMessage(), "Can't DROP") !== false) {
            echo "ℹ️  (La colonne a peut-être déjà été supprimée)\n";
        }
    }
}

echo "\n=== Fin des migrations ===\n";
echo "</pre>";
?>
