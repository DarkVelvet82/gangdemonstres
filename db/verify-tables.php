<?php
require_once __DIR__ . '/../config/database.php';

echo "<h1>Vérification des tables</h1>";
echo "<pre>";

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Tables existantes dans la base de données:\n\n";
    foreach ($tables as $table) {
        echo "✅ " . $table . "\n";
    }

    echo "\n\n=== Recherche de wp_objectif_cards ===\n";
    if (in_array('wp_objectif_cards', $tables)) {
        echo "✅ La table wp_objectif_cards EXISTE\n";
    } else {
        echo "❌ La table wp_objectif_cards N'EXISTE PAS\n";
        echo "\nTentative de création directe...\n\n";

        // Créer la table directement
        $pdo->exec("CREATE TABLE IF NOT EXISTS wp_objectif_cards (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            card_type ENUM('monster', 'dirty_trick') NOT NULL DEFAULT 'monster',
            game_set_id BIGINT UNSIGNED NOT NULL,
            image_url TEXT NULL,
            power_text TEXT NULL,
            qr_code_url TEXT NULL,
            display_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_game_set (game_set_id),
            INDEX idx_card_type (card_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        echo "✅ Table wp_objectif_cards créée!\n\n";

        $pdo->exec("CREATE TABLE IF NOT EXISTS wp_objectif_card_types (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            card_id BIGINT UNSIGNED NOT NULL,
            type_id BIGINT UNSIGNED NULL,
            quantity INT NOT NULL DEFAULT 0,
            INDEX idx_card (card_id),
            INDEX idx_type (type_id),
            FOREIGN KEY (card_id) REFERENCES wp_objectif_cards(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        echo "✅ Table wp_objectif_card_types créée!\n";
    }

    echo "\n\n=== Recherche de wp_objectif_card_types ===\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('wp_objectif_card_types', $tables)) {
        echo "✅ La table wp_objectif_card_types EXISTE\n";
    } else {
        echo "❌ La table wp_objectif_card_types N'EXISTE PAS\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
