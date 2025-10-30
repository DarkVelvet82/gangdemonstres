<?php
// api/game.php - Endpoints pour la gestion des parties

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
        case 'create':
            create_game();
            break;

        case 'join':
            join_game();
            break;

        case 'status':
            check_game_status();
            break;

        case 'players':
            get_game_players();
            break;

        case 'restart':
            restart_game();
            break;

        default:
            send_json_response(false, [], 'Action non reconnue');
    }

} catch (Exception $e) {
    log_error('API Error: ' . $e->getMessage());
    send_json_response(false, [], DEBUG_MODE ? $e->getMessage() : 'Erreur serveur');
}

/**
 * Créer une nouvelle partie
 */
function create_game() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $player_count = clean_int(get_post_value('player_count', 0));
    $creator_name = clean_string(get_post_value('creator_name', ''));
    $other_names = get_post_value('other_names', []);
    $difficulty = clean_string(get_post_value('difficulty', 'normal'));
    $base_game = clean_int(get_post_value('base_game', 0));
    $extensions = array_map('intval', get_post_value('extensions', []));

    // Validations
    if ($player_count < 2 || $player_count > 10) {
        send_json_response(false, [], 'Nombre de joueurs invalide (2-10)');
    }

    if (empty($creator_name)) {
        send_json_response(false, [], 'Le prénom du créateur est obligatoire');
    }

    if (!$base_game) {
        send_json_response(false, [], 'Veuillez sélectionner un jeu de base');
    }

    // Valider les prénoms des autres joueurs
    $sanitized_names = [];
    for ($i = 0; $i < ($player_count - 1); $i++) {
        $name = isset($other_names[$i]) ? clean_string($other_names[$i]) : '';
        if (empty($name)) {
            send_json_response(false, [], "Le prénom du joueur " . ($i + 2) . " est obligatoire");
        }
        $sanitized_names[] = $name;
    }

    // Configuration du jeu
    $game_set_ids = array_merge([$base_game], $extensions);
    $game_config = [
        'base_game' => $base_game,
        'extensions' => $extensions
    ];

    // Récupérer les noms des jeux
    $game_names = [];
    foreach ($game_set_ids as $game_set_id) {
        $stmt = $pdo->prepare("SELECT name FROM " . DB_PREFIX . "game_sets WHERE id = ?");
        $stmt->execute([$game_set_id]);
        $name = $stmt->fetchColumn();
        if ($name) {
            $game_names[] = $name;
        }
    }
    $game_config_name = implode(' + ', $game_names);

    // Créer la partie
    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "games
        (player_count, game_set_id, game_config, difficulty)
        VALUES (?, ?, ?, ?)");

    $stmt->execute([
        $player_count,
        $base_game,
        json_encode($game_config),
        $difficulty
    ]);

    $game_id = $pdo->lastInsertId();

    // Créer les codes joueurs
    $players_data = [];
    $creator_player_id = null;

    // Créateur (Joueur 1)
    $creator_code = generate_player_code($pdo);

    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "players
        (game_id, player_code, is_creator, used, player_name)
        VALUES (?, ?, 1, 1, ?)");

    $stmt->execute([$game_id, $creator_code, $creator_name]);
    $creator_player_id = $pdo->lastInsertId();

    // Autres joueurs
    foreach ($sanitized_names as $name) {
        $code = generate_player_code($pdo);

        $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "players
            (game_id, player_code, is_creator, used, player_name)
            VALUES (?, ?, 0, 0, ?)");

        $stmt->execute([$game_id, $code, $name]);

        $players_data[] = [
            'name' => $name,
            'code' => $code
        ];
    }

    $join_page_url = get_app_url('public/rejoindre.php');

    $difficulty_names = [
        'easy' => '🟢 Facile',
        'normal' => '🟡 Normal',
        'hard' => '🔴 Difficile'
    ];
    $difficulty_display = $difficulty_names[$difficulty] ?? $difficulty;

    send_json_response(true, [
        'creator_name' => $creator_name,
        'players_data' => $players_data,
        'game_id' => $game_id,
        'creator_player_id' => $creator_player_id,
        'join_page_url' => $join_page_url,
        'is_creator' => 1,
        'game_config_name' => $game_config_name,
        'difficulty_display' => $difficulty_display
    ], 'Partie créée avec succès');
}

/**
 * Rejoindre une partie via code
 */
function join_game() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $code = clean_string(get_post_value('code', ''));

    if (!preg_match('/^[0-9]{6}$/', $code)) {
        send_json_response(false, [], 'Code invalide (6 chiffres requis)');
    }

    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "players WHERE player_code = ?");
    $stmt->execute([$code]);
    $player = $stmt->fetch();

    if (!$player) {
        send_json_response(false, [], "Ce code n'existe pas");
    }

    if ($player['used']) {
        send_json_response(false, [], "Ce code a déjà été utilisé");
    }

    // Marquer comme utilisé
    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "players SET used = 1 WHERE id = ?");
    $stmt->execute([$player['id']]);

    send_json_response(true, [
        'game_id' => intval($player['game_id']),
        'player_id' => intval($player['id']),
        'is_creator' => intval($player['is_creator'])
    ], 'Connexion réussie');
}

/**
 * Vérifier le statut de la partie
 */
function check_game_status() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $game_id = clean_int(get_post_value('game_id', 0));

    if (!$game_id) {
        send_json_response(false, [], 'ID de partie manquant');
    }

    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        send_json_response(false, [], 'Partie introuvable');
    }

    // Compter les joueurs connectés
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "players
        WHERE game_id = ? AND used = 1");
    $stmt->execute([$game_id]);
    $connected_players = $stmt->fetchColumn();

    // Compter les joueurs avec objectifs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "players
        WHERE game_id = ? AND used = 1 AND objective_json IS NOT NULL");
    $stmt->execute([$game_id]);
    $players_with_objectives = $stmt->fetchColumn();

    $total_players = intval($game['player_count']);
    $connected = intval($connected_players);
    $with_objectives = intval($players_with_objectives);

    $all_connected = ($connected === $total_players);
    $all_have_objectives = ($with_objectives === $total_players);
    $can_end_game = $all_connected && $all_have_objectives;

    $status_message = $can_end_game ?
        'Tous les joueurs sont prêts !' :
        "En attente : {$connected}/{$total_players} connectés, {$with_objectives}/{$total_players} avec objectifs";

    send_json_response(true, [
        'total_players' => $total_players,
        'connected_players' => $connected,
        'players_with_objectives' => $with_objectives,
        'all_connected' => $all_connected,
        'all_have_objectives' => $all_have_objectives,
        'can_end_game' => $can_end_game,
        'status_message' => $status_message,
        'game_status' => $game['status']
    ]);
}

/**
 * Récupérer la liste des joueurs d'une partie
 */
function get_game_players() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $game_id = clean_int(get_post_value('game_id', 0));

    if (!$game_id) {
        send_json_response(false, [], 'ID de partie manquant');
    }

    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        send_json_response(false, [], 'Partie introuvable');
    }

    $stmt = $pdo->prepare("SELECT id, player_name, player_code, is_creator, used
        FROM " . DB_PREFIX . "players
        WHERE game_id = ? AND used = 1
        ORDER BY is_creator DESC, player_name ASC");
    $stmt->execute([$game_id]);
    $players = $stmt->fetchAll();

    if (empty($players)) {
        send_json_response(false, [], 'Aucun joueur connecté');
    }

    $players_data = [];
    foreach ($players as $player) {
        $players_data[] = [
            'id' => intval($player['id']),
            'player_name' => $player['player_name'],
            'is_creator' => intval($player['is_creator'])
        ];
    }

    send_json_response(true, [
        'players' => $players_data,
        'total_players' => count($players_data)
    ]);
}

/**
 * Redémarrer une partie
 */
function restart_game() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $game_id = clean_int(get_post_value('game_id', 0));

    if (!$game_id) {
        send_json_response(false, [], 'ID de partie manquant');
    }

    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        send_json_response(false, [], 'Partie introuvable');
    }

    // Récupérer le nom du créateur
    $stmt = $pdo->prepare("SELECT player_name FROM " . DB_PREFIX . "players
        WHERE game_id = ? AND is_creator = 1");
    $stmt->execute([$game_id]);
    $creator_name = $stmt->fetchColumn();

    // Reset des objectifs
    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "players
        SET objective_json = NULL, generated_at = NULL
        WHERE game_id = ?");
    $stmt->execute([$game_id]);
    $updated = $stmt->rowCount();

    // Marquer la partie comme active
    $game_config = json_decode($game['game_config'], true) ?: [];
    $game_config['restarted_by'] = $creator_name;
    $game_config['restarted_at_timestamp'] = time();

    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "games
        SET status = 'active', ended_at = NULL, game_config = ?
        WHERE id = ?");
    $stmt->execute([json_encode($game_config), $game_id]);

    send_json_response(true, [
        'message' => 'Partie redémarrée avec succès',
        'players_reset' => $updated,
        'restarted_by' => $creator_name
    ]);
}
