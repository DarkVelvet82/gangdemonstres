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
            image_url TEXT NOT NULL,
            display_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Table des jeux/extensions
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "game_sets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
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
            user_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) DEFAULT 'active',
            special_objective_data JSON NULL COMMENT 'Tirage objectif spécial: {special_id: X, winner_order: Y}',
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

        // Table des objectifs spéciaux (ex: Le Parrain)
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "special_objectives (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            requirements JSON NOT NULL COMMENT 'Format: {\"type_id\": quantity, ...}',
            probability DECIMAL(5,4) DEFAULT 0.0833 COMMENT 'Probabilité de tirage (ex: 0.0833 = 1/12)',
            max_per_game INT DEFAULT 1 COMMENT 'Nombre max de joueurs pouvant avoir cet objectif par partie',
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Table des images d'objectifs spéciaux par nombre de joueurs
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "special_objective_images (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            special_objective_id BIGINT UNSIGNED NOT NULL,
            player_count INT NOT NULL COMMENT 'Nombre de joueurs (2, 3, 4, 5, 6)',
            image_url TEXT NOT NULL,
            INDEX idx_special_objective (special_objective_id),
            INDEX idx_player_count (player_count),
            UNIQUE KEY unique_objective_players (special_objective_id, player_count)
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

    // Note: Les types et jeux doivent maintenant être créés via l'interface admin
    // car les types nécessitent des images uploadées

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
