<?php
// includes/install.php - Installation des tables de base de données

require_once __DIR__ . '/../config/database.php';

function install_database() {
    global $pdo;

    try {
        // Table des types d'objectifs
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "types (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            image_url TEXT NULL,
            emoji VARCHAR(10) NULL,
            display_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Table des jeux/extensions
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "game_sets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            slug VARCHAR(200) NOT NULL UNIQUE,
            description TEXT NULL,
            is_base_game TINYINT(1) DEFAULT 0,
            display_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Table des parties
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "games (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME NULL,
            player_count INT NOT NULL,
            game_set_id BIGINT UNSIGNED NULL,
            game_config TEXT NULL,
            difficulty VARCHAR(20) DEFAULT 'normal',
            status VARCHAR(20) DEFAULT 'active',
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Table des joueurs
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "players (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            game_id BIGINT UNSIGNED NOT NULL,
            player_code VARCHAR(6) NOT NULL UNIQUE,
            player_name VARCHAR(100) NULL,
            used TINYINT(1) DEFAULT 0,
            is_creator TINYINT(1) DEFAULT 0,
            objective_json TEXT NULL,
            generated_at DATETIME NULL,
            INDEX idx_game_id (game_id),
            INDEX idx_player_code (player_code),
            INDEX idx_used (used)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Table de liaison jeux <-> types
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "set_types (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            game_set_id BIGINT UNSIGNED NOT NULL,
            type_id BIGINT UNSIGNED NOT NULL,
            is_limited TINYINT(1) DEFAULT 0,
            max_quantity INT DEFAULT NULL,
            INDEX idx_game_set (game_set_id),
            INDEX idx_type (type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Table de configuration des difficultés
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "difficulty_config (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            game_set_id BIGINT UNSIGNED NOT NULL,
            difficulty VARCHAR(20) NOT NULL,
            types_count INT NOT NULL,
            min_quantity INT NOT NULL,
            max_quantity INT NOT NULL,
            INDEX idx_game_difficulty (game_set_id, difficulty)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Table des scores
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "scores (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            game_id BIGINT UNSIGNED NOT NULL,
            player_name VARCHAR(100) NOT NULL,
            player_id BIGINT UNSIGNED NULL,
            is_winner TINYINT(1) DEFAULT 0,
            game_config TEXT NULL,
            difficulty VARCHAR(20) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_game_id (game_id),
            INDEX idx_player_name (player_name),
            INDEX idx_is_winner (is_winner),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Table des utilisateurs admin
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255) NULL,
            is_admin TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Insérer des données par défaut
        insert_default_data();

        echo "✅ Tables créées avec succès!\n";

    } catch (PDOException $e) {
        echo "❌ Erreur lors de la création des tables: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function insert_default_data() {
    global $pdo;

    // Vérifier si déjà initialisé
    $stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "types");
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo "ℹ️ Données par défaut déjà présentes.\n";
        return;
    }

    // Types par défaut
    $pdo->exec("INSERT INTO " . DB_PREFIX . "types (name, slug, emoji, display_order) VALUES
        ('Faucheuse', 'faucheuse', '💀', 1),
        ('Garou', 'garou', '🐺', 2),
        ('Citrouille', 'citrouille', '🎃', 3)");

    $faucheuse_id = $pdo->lastInsertId();

    // Récupérer les IDs
    $stmt = $pdo->query("SELECT id FROM " . DB_PREFIX . "types ORDER BY id ASC");
    $type_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    list($faucheuse_id, $garou_id, $citrouille_id) = $type_ids;

    // Jeu de base
    $pdo->exec("INSERT INTO " . DB_PREFIX . "game_sets (name, slug, description, is_base_game, display_order) VALUES
        ('Jeu de Base', 'jeu-de-base', 'Version de base du jeu', 1, 1)");

    $base_game_id = $pdo->lastInsertId();

    // Associer les types au jeu de base
    $pdo->exec("INSERT INTO " . DB_PREFIX . "set_types (game_set_id, type_id, is_limited, max_quantity) VALUES
        ($base_game_id, $faucheuse_id, 0, NULL),
        ($base_game_id, $garou_id, 0, NULL),
        ($base_game_id, $citrouille_id, 1, 2)");

    // Configuration de difficulté par défaut
    $difficulties = [
        ['easy', 1, 8, 12],
        ['easy', 2, 4, 6],
        ['easy', 3, 3, 4],
        ['normal', 1, 10, 15],
        ['normal', 2, 5, 8],
        ['normal', 3, 4, 6],
        ['hard', 1, 15, 20],
        ['hard', 2, 8, 12],
        ['hard', 3, 6, 9]
    ];

    foreach ($difficulties as $diff) {
        $pdo->exec("INSERT INTO " . DB_PREFIX . "difficulty_config
            (game_set_id, difficulty, types_count, min_quantity, max_quantity) VALUES
            ($base_game_id, '{$diff[0]}', {$diff[1]}, {$diff[2]}, {$diff[3]})");
    }

    // Créer un utilisateur admin par défaut
    $admin_password = password_hash('admin123', PASSWORD_BCRYPT);
    $pdo->exec("INSERT INTO " . DB_PREFIX . "users (username, password, is_admin) VALUES
        ('admin', '$admin_password', 1)");

    echo "✅ Données par défaut insérées!\n";
}

// Exécuter l'installation si appelé directement
if (php_sapi_name() === 'cli' || (isset($_GET['install']) && $_GET['install'] === 'run')) {
    install_database();
}
