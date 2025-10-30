<?php
// includes/functions.php - Fonctions utilitaires

/**
 * Envoyer une réponse JSON
 */
function send_json_response($success, $data = [], $message = '') {
    header('Content-Type: application/json');
    http_response_code($success ? 200 : 400);

    $response = [
        'success' => $success,
        'data' => $data
    ];

    if ($message) {
        $response['message'] = $message;
    }

    echo json_encode($response);
    exit;
}

/**
 * Récupérer une valeur POST de manière sécurisée
 */
function get_post_value($key, $default = null) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/**
 * Nettoyer une chaîne de caractères
 */
function clean_string($string) {
    return trim(strip_tags($string));
}

/**
 * Nettoyer un entier
 */
function clean_int($value) {
    return intval($value);
}

/**
 * Générer un code joueur unique
 */
function generate_player_code($pdo) {
    $max_attempts = 100;
    $attempt = 0;

    do {
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "players WHERE player_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetchColumn();

        $attempt++;

        if ($attempt >= $max_attempts) {
            throw new Exception("Impossible de générer un code unique");
        }

    } while ($exists > 0);

    return $code;
}

/**
 * Vérifier le nonce (CSRF protection)
 * Pour l'instant simplifié, à améliorer avec vraie génération de token
 */
function verify_nonce($nonce) {
    // TODO: Implémenter une vraie vérification de nonce
    return !empty($nonce);
}

/**
 * Créer un nonce
 */
function create_nonce() {
    return bin2hex(random_bytes(16));
}

/**
 * Logger une erreur
 */
function log_error($message, $context = []) {
    if (LOG_ERRORS) {
        $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $log_message .= ' - Context: ' . json_encode($context);
        }
        error_log($log_message);
    }
}

/**
 * Résoudre les types disponibles selon la configuration du jeu
 */
function resolve_available_types($pdo, $game_config_json) {
    $config = json_decode($game_config_json, true);
    if (!$config || !isset($config['base_game'])) {
        return [];
    }

    $game_set_ids = array_merge(
        [$config['base_game']],
        isset($config['extensions']) ? $config['extensions'] : []
    );

    $placeholders = implode(',', array_fill(0, count($game_set_ids), '?'));

    $query = "
        SELECT t.*, st.is_limited, st.max_quantity
        FROM " . DB_PREFIX . "types t
        JOIN " . DB_PREFIX . "set_types st ON t.id = st.type_id
        WHERE st.game_set_id IN ($placeholders)
        ORDER BY t.display_order
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($game_set_ids);
    return $stmt->fetchAll();
}

/**
 * Vérifier si l'utilisateur est admin (basé sur session)
 */
function is_admin() {
    session_start();
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Rediriger vers une page
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Obtenir l'URL de base de l'application
 */
function get_app_url($path = '') {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Obtenir le nom de la configuration du jeu
 */
function get_game_config_name($pdo, $game_config_json) {
    $config = json_decode($game_config_json, true);
    if (!$config || !isset($config['base_game'])) {
        return 'Configuration inconnue';
    }

    $game_set_ids = array_merge(
        [$config['base_game']],
        isset($config['extensions']) ? $config['extensions'] : []
    );

    $game_names = [];
    foreach ($game_set_ids as $set_id) {
        $stmt = $pdo->prepare("SELECT name FROM " . DB_PREFIX . "game_sets WHERE id = ?");
        $stmt->execute([$set_id]);
        $name = $stmt->fetchColumn();
        if ($name) {
            $game_names[] = $name;
        }
    }

    return implode(' + ', $game_names);
}
