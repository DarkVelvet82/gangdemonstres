<?php
// api/scores.php - Endpoints pour la gestion des scores et notifications

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'save':
            save_scores();
            break;

        case 'get':
            get_scores();
            break;

        case 'notifications':
            check_notifications();
            break;

        default:
            send_json_response(false, [], 'Action non reconnue');
    }

} catch (Exception $e) {
    log_error('API Error: ' . $e->getMessage());
    send_json_response(false, [], DEBUG_MODE ? $e->getMessage() : 'Erreur serveur');
}

/**
 * Sauvegarder les scores en fin de partie
 */
function save_scores() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $game_id = clean_int(get_post_value('game_id', 0));
    $winners = array_map('intval', get_post_value('winners', []));

    if (!$game_id) {
        send_json_response(false, [], 'ID de partie manquant');
    }

    // Récupérer la partie
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        send_json_response(false, [], 'Partie introuvable');
    }

    // Récupérer tous les joueurs
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "players
        WHERE game_id = ? AND used = 1");
    $stmt->execute([$game_id]);
    $players = $stmt->fetchAll();

    if (empty($players)) {
        send_json_response(false, [], 'Aucun joueur trouvé');
    }

    // Récupérer le nom du créateur et du gagnant
    $creator_name = '';
    $winner_name = '';

    foreach ($players as $player) {
        if ($player['is_creator']) {
            $creator_name = $player['player_name'];
        }
        if (in_array($player['id'], $winners)) {
            $winner_name = $player['player_name'];
        }
    }

    // Récupérer le nom de la config
    $game_config_name = get_game_config_name($pdo, $game['game_config']);

    // Enregistrer les scores
    $scores_saved = 0;
    foreach ($players as $player) {
        $is_winner = in_array($player['id'], $winners) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "scores
            (game_id, player_name, player_id, is_winner, game_config, difficulty)
            VALUES (?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $game_id,
            $player['player_name'],
            $player['id'],
            $is_winner,
            $game_config_name,
            $game['difficulty']
        ]);

        $scores_saved++;
    }

    // Marquer la partie comme terminée
    $game_config = json_decode($game['game_config'], true) ?: [];
    $game_config['winner_id'] = $winners[0] ?? null;
    $game_config['winner_name'] = $winner_name;
    $game_config['ended_by'] = $creator_name;
    $game_config['ended_at_timestamp'] = time();

    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "games
        SET status = 'ended', ended_at = NOW(), game_config = ?
        WHERE id = ?");
    $stmt->execute([json_encode($game_config), $game_id]);

    send_json_response(true, [
        'message' => 'Partie terminée et scores enregistrés',
        'scores_saved' => $scores_saved,
        'total_players' => count($players),
        'winners_count' => count($winners),
        'winner_name' => $winner_name,
        'ended_by' => $creator_name
    ]);
}

/**
 * Récupérer les scores
 */
function get_scores() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $limit = clean_int(get_post_value('limit', 50));
    $player_filter = clean_string(get_post_value('player_filter', ''));

    // Construire la requête
    $where_clause = "1=1";
    $params = [];

    if (!empty($player_filter)) {
        $where_clause .= " AND player_name LIKE ?";
        $params[] = '%' . $player_filter . '%';
    }

    // Scores agrégés par joueur
    $query = "
        SELECT
            player_name,
            COUNT(*) as total_games,
            SUM(is_winner) as total_wins,
            ROUND((SUM(is_winner) / COUNT(*)) * 100, 1) as win_percentage,
            MAX(created_at) as last_game
        FROM " . DB_PREFIX . "scores
        WHERE $where_clause
        GROUP BY player_name
        ORDER BY total_wins DESC, win_percentage DESC, total_games DESC
        LIMIT ?
    ";

    $params[] = $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $scores = $stmt->fetchAll();

    // Dernières parties
    $recent_query = "
        SELECT
            game_id,
            player_name,
            is_winner,
            game_config,
            difficulty,
            created_at
        FROM " . DB_PREFIX . "scores
        WHERE $where_clause
        ORDER BY created_at DESC
        LIMIT 20
    ";

    $recent_params = [];
    if (!empty($player_filter)) {
        $recent_params[] = '%' . $player_filter . '%';
    }

    $stmt = $pdo->prepare($recent_query);
    $stmt->execute($recent_params);
    $recent_games = $stmt->fetchAll();

    send_json_response(true, [
        'scores' => $scores,
        'recent_games' => $recent_games,
        'total_found' => count($scores)
    ]);
}

/**
 * Vérifier les notifications pour un joueur
 */
function check_notifications() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $player_id = clean_int(get_post_value('player_id', 0));

    if (!$player_id) {
        send_json_response(false, [], 'Player ID manquant');
    }

    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "players WHERE id = ?");
    $stmt->execute([$player_id]);
    $player = $stmt->fetch();

    if (!$player) {
        send_json_response(false, [], 'Joueur introuvable');
    }

    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$player['game_id']]);
    $game = $stmt->fetch();

    if (!$game) {
        send_json_response(false, [], 'Partie introuvable');
    }

    $game_config = json_decode($game['game_config'], true) ?: [];
    $notifications = [];

    // Vérifier si la partie est terminée
    if ($game['status'] === 'ended' && isset($game_config['winner_name'])) {
        $is_winner = ($game_config['winner_id'] == $player_id);
        $notifications[] = [
            'type' => 'game_ended',
            'is_winner' => $is_winner,
            'winner_name' => $game_config['winner_name'],
            'ended_by' => $game_config['ended_by'] ?? 'Le créateur',
            'timestamp' => $game_config['ended_at_timestamp'] ?? time()
        ];
    }

    // Vérifier si la partie a été relancée
    if ($game['status'] === 'active' && isset($game_config['restarted_by'])) {
        $notifications[] = [
            'type' => 'game_restarted',
            'restarted_by' => $game_config['restarted_by'],
            'timestamp' => $game_config['restarted_at_timestamp'] ?? time()
        ];
    }

    send_json_response(true, [
        'notifications' => $notifications,
        'game_status' => $game['status'],
        'player_name' => $player['player_name']
    ]);
}
