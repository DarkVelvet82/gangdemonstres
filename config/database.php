<?php
// config/database.php - Configuration base de données

// Détection de l'environnement
$is_local = file_exists(__DIR__ . '/../wp-config.php');

if ($is_local) {
    // ========= ENVIRONNEMENT LOCAL =========
    // Charger la config depuis wp-config.php parent si disponible
    $wp_config_path = dirname(dirname(__DIR__)) . '/wp-config.php';
    if (file_exists($wp_config_path)) {
        require_once $wp_config_path;
    }

    // Vérifier si les constantes DB sont définies, sinon utiliser valeurs par défaut Local
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost:10023');
    if (!defined('DB_NAME')) define('DB_NAME', 'local');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) {
        if (defined('DB_PASSWORD')) {
            define('DB_PASS', DB_PASSWORD);
        } else {
            define('DB_PASS', 'root');
        }
    }

    define('APP_URL', 'http://gang-de-monstres.local/gang-de-monstres-standalone/');
    define('DEBUG_MODE', true);

} else {
    // ========= ENVIRONNEMENT PRODUCTION (HOSTINGER) =========
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'u282641111_gangdemonstres');
    define('DB_USER', 'u282641111_theogang');
    define('DB_PASS', 'L8Wh3cBKmQ9q');

    define('APP_URL', 'https://gangdemonstres.com/');
    define('DEBUG_MODE', false);
}

// Configuration commune
define('APP_NAME', 'Gang de Monstres - Objectifs Multijoueur');
define('APP_VERSION', '2.0.0');
define('SESSION_TIMEOUT', 3600);
define('LOG_ERRORS', true);

// Préfixe des tables
define('DB_PREFIX', 'wp_objectif_');

// Fuseau horaire
date_default_timezone_set('Europe/Paris');

// Connexion à la base de données
try {
    if (strpos(DB_HOST, ':') !== false) {
        // Local avec port
        list($host, $port) = explode(':', DB_HOST);
        $dsn = "mysql:host={$host};port={$port};dbname=" . DB_NAME . ";charset=utf8mb4";
    } else {
        // Production sans port
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    }

    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("Erreur de connexion : " . $e->getMessage());
    } else {
        error_log("DB Connection Error: " . $e->getMessage());
        die("Erreur de connexion à la base de données");
    }
}

return $pdo;
