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

/**
 * Obtenir l'adresse IP du client
 */
function get_client_ip() {
    $ip = '';

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Prendre la première IP si plusieurs
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
}

/**
 * Vérifier le rate limiting basé sur fichier
 *
 * @param string $action L'action à limiter (ex: 'register', 'login')
 * @param string $identifier L'identifiant unique (IP, user_id, etc.)
 * @param int $max_attempts Nombre max de tentatives autorisées
 * @param int $time_window Fenêtre de temps en secondes
 * @return bool True si autorisé, False si limite dépassée
 */
function check_rate_limit($action, $identifier, $max_attempts, $time_window) {
    $rate_limit_dir = __DIR__ . '/../storage/rate_limits';

    // Créer le dossier s'il n'existe pas
    if (!is_dir($rate_limit_dir)) {
        mkdir($rate_limit_dir, 0755, true);
    }

    // Nom de fichier sécurisé basé sur action + identifiant hashé
    $filename = $rate_limit_dir . '/' . $action . '_' . md5($identifier) . '.json';

    $now = time();
    $attempts = [];

    // Lire les tentatives existantes
    if (file_exists($filename)) {
        $data = json_decode(file_get_contents($filename), true);
        if (is_array($data)) {
            // Nettoyer les tentatives expirées
            $attempts = array_filter($data, function($timestamp) use ($now, $time_window) {
                return ($now - $timestamp) < $time_window;
            });
        }
    }

    // Vérifier si la limite est atteinte
    if (count($attempts) >= $max_attempts) {
        return false;
    }

    // Ajouter la nouvelle tentative
    $attempts[] = $now;

    // Sauvegarder
    file_put_contents($filename, json_encode(array_values($attempts)));

    return true;
}

/**
 * Nettoyer les fichiers de rate limiting expirés (à appeler périodiquement)
 */
function cleanup_rate_limits() {
    $rate_limit_dir = __DIR__ . '/../storage/rate_limits';

    if (!is_dir($rate_limit_dir)) {
        return;
    }

    $files = glob($rate_limit_dir . '/*.json');
    $now = time();
    $max_age = 7200; // 2 heures

    foreach ($files as $file) {
        if (filemtime($file) < ($now - $max_age)) {
            @unlink($file);
        }
    }
}

/**
 * Effectuer le tirage des objectifs spéciaux à la création/redémarrage d'une partie
 *
 * @param PDO $pdo
 * @param int $player_count Nombre de joueurs dans la partie
 * @return array|null Données du tirage {special_id, winner_order} ou null si pas de gagnant
 */
function draw_special_objective_for_game($pdo, $player_count) {
    // Récupérer les objectifs spéciaux actifs
    $stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "special_objectives WHERE is_active = 1 ORDER BY display_order");
    $special_objectives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($special_objectives)) {
        return null;
    }

    // Pour chaque objectif spécial, faire le tirage
    foreach ($special_objectives as $special) {
        $probability = (float)$special['probability'];
        $random = mt_rand() / mt_getrandmax();

        if ($random <= $probability) {
            // Tirage gagnant ! Choisir aléatoirement quel joueur aura l'objectif
            $winner_order = mt_rand(1, $player_count);

            return [
                'special_id' => (int)$special['id'],
                'winner_order' => $winner_order
            ];
        }
    }

    // Aucun objectif spécial n'a été tiré
    return null;
}

/**
 * Vérifier si un joueur doit recevoir un objectif spécial
 *
 * @param PDO $pdo
 * @param array $game La partie (avec special_objective_data)
 * @param int $current_player_order Numéro d'ordre du joueur (1-based, basé sur le nombre d'objectifs déjà générés + 1)
 * @return array|null Retourne l'objectif spécial ou null
 */
function try_assign_special_objective($pdo, $game, $current_player_order) {
    // Vérifier si un tirage a été fait pour cette partie
    $special_data = null;
    if (!empty($game['special_objective_data'])) {
        $special_data = json_decode($game['special_objective_data'], true);
    }

    if (empty($special_data) || !isset($special_data['winner_order']) || $special_data['winner_order'] === 0) {
        // Pas de tirage gagnant pour cette partie
        return null;
    }

    // Vérifier si c'est le tour du joueur gagnant
    if ($current_player_order !== (int)$special_data['winner_order']) {
        return null;
    }

    // C'est le joueur gagnant ! Récupérer l'objectif spécial
    $special_id = (int)$special_data['special_id'];
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "special_objectives WHERE id = ? AND is_active = 1");
    $stmt->execute([$special_id]);
    $special = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$special) {
        return null;
    }

    // Construire l'objectif (les requirements sont vides car l'image contient les infos)
    $objective = [];
    $objective['_special_id'] = $special_id;
    $objective['_special_name'] = $special['name'];

    // Récupérer l'image correspondant au nombre de joueurs
    $player_count = (int)$game['player_count'];
    $stmt = $pdo->prepare("SELECT image_url FROM " . DB_PREFIX . "special_objective_images
        WHERE special_objective_id = ? AND player_count = ?");
    $stmt->execute([$special_id, $player_count]);
    $image_url = $stmt->fetchColumn();

    // Si pas d'image pour ce nombre de joueurs, prendre la première disponible
    if (!$image_url) {
        $stmt = $pdo->prepare("SELECT image_url FROM " . DB_PREFIX . "special_objective_images
            WHERE special_objective_id = ? ORDER BY player_count LIMIT 1");
        $stmt->execute([$special_id]);
        $image_url = $stmt->fetchColumn();
    }

    return [
        'objective' => $objective,
        'name' => $special['name'],
        'image' => $image_url ?: null
    ];
}
