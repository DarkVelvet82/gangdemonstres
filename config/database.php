<?php
// config/database.php - Configuration base de donnees

// Detecter l'environnement
$rootPublic = dirname(__DIR__, 2); // remonte jusqu'a app/public
$is_local = file_exists($rootPublic . '/wp-config.php');

if ($is_local) {
    // ========= ENVIRONNEMENT LOCAL =========
    $wp_config_path = $rootPublic . '/wp-config.php';
    if (file_exists($wp_config_path)) {
        require_once $wp_config_path; // peut definir DB_NAME/DB_USER/DB_PASSWORD/DB_HOST
    }

    // Valeurs par defaut pour Local (si WP ne fournit pas)
    if (!defined('DB_NAME')) define('DB_NAME', 'local');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) {
        if (defined('DB_PASSWORD')) {
            define('DB_PASS', DB_PASSWORD);
        } else {
            define('DB_PASS', 'root');
        }
    }
    // Ne pas se fier a DB_HOST de WP (souvent sans port). On force 127.0.0.1:10023
    if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1:10023');

    if (!defined('APP_URL')) define('APP_URL', 'http://gang-de-monstres.local/gang-de-monstres-standalone/');
    if (!defined('DEBUG_MODE')) define('DEBUG_MODE', true);

} else {
    // ========= ENVIRONNEMENT PRODUCTION (HOSTINGER) =========
    // Lire d'abord depuis un fichier de secrets non versionné, sinon variables d'environnement
    $secrets_file = __DIR__ . '/secrets.php';
    if (file_exists($secrets_file)) {
        require_once $secrets_file; // peut définir DB_HOST/DB_NAME/DB_USER/DB_PASS
    }
    if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: '');
    if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: '');
    if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');

    if (!defined('APP_URL')) define('APP_URL', 'https://gangdemonstres.com/');
    if (!defined('DEBUG_MODE')) define('DEBUG_MODE', false);
}

// Configuration commune
if (!defined('APP_NAME')) define('APP_NAME', 'Gang de Monstres - Objectifs Multijoueur');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.0.0');
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600);
if (!defined('LOG_ERRORS')) define('LOG_ERRORS', true);

// Prefixe des tables
if (!defined('DB_PREFIX')) define('DB_PREFIX', 'wp_objectif_');

// Fuseau horaire
date_default_timezone_set('Europe/Paris');

// Connexion a la base de donnees
try {
    if ($is_local) {
        // Force IP + port Local pour eviter les sockets
        $dsn = 'mysql:host=127.0.0.1;port=10023;dbname=' . DB_NAME . ';charset=utf8mb4';
    } elseif (strpos(DB_HOST, ':') !== false) {
        list($host, $port) = explode(':', DB_HOST, 2);
        $dsn = "mysql:host={$host};port={$port};dbname=" . DB_NAME . ";charset=utf8mb4";
    } else {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    }

    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die('Erreur de connexion: ' . $e->getMessage());
    } else {
        error_log('DB Connection Error: ' . $e->getMessage());
        die('Erreur de connexion a la base de donnees');
    }
}

return $pdo;
