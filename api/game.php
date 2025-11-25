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

        case 'cancel':
            cancel_game();
            break;

        case 'full_status':
            get_full_game_status();
            break;

        case 'cards':
            get_game_cards();
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
    $user_id = clean_int(get_post_value('user_id', 0)) ?: null;

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

    // OPTIMISATION: Récupérer les noms des jeux en une seule requête
    $placeholders = implode(',', array_fill(0, count($game_set_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM " . DB_PREFIX . "game_sets WHERE id IN ($placeholders)");
    $stmt->execute($game_set_ids);
    $games_rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Réordonner selon l'ordre original (base_game en premier)
    $game_names = [];
    foreach ($game_set_ids as $game_set_id) {
        if (isset($games_rows[$game_set_id])) {
            $game_names[] = $games_rows[$game_set_id];
        }
    }
    $game_config_name = implode(' + ', $game_names);

    // Tirage des objectifs spéciaux (1/12 de chance par partie)
    $special_objective_data = draw_special_objective_for_game($pdo, $player_count);

    // Créer la partie
    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "games
        (player_count, game_set_id, game_config, difficulty, user_id, special_objective_data)
        VALUES (?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $player_count,
        $base_game,
        json_encode($game_config),
        $difficulty,
        $user_id,
        $special_objective_data ? json_encode($special_objective_data) : null
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

    $join_page_url = get_app_url('rejoindre.php');

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

    // Nouveau tirage des objectifs spéciaux pour la nouvelle partie
    $special_objective_data = draw_special_objective_for_game($pdo, (int)$game['player_count']);

    // Marquer la partie comme active
    $game_config = json_decode($game['game_config'], true) ?: [];
    $game_config['restarted_by'] = $creator_name;
    $game_config['restarted_at_timestamp'] = time();

    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "games
        SET status = 'active', ended_at = NULL, game_config = ?, special_objective_data = ?
        WHERE id = ?");
    $stmt->execute([
        json_encode($game_config),
        $special_objective_data ? json_encode($special_objective_data) : null,
        $game_id
    ]);

    send_json_response(true, [
        'message' => 'Partie redémarrée avec succès',
        'players_reset' => $updated,
        'restarted_by' => $creator_name
    ]);
}

/**
 * Récupérer le statut complet de la partie (pour la page partie.php)
 */
function get_full_game_status() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $game_id = clean_int(get_post_value('game_id', 0));
    $player_id = clean_int(get_post_value('player_id', 0));

    if (!$game_id) {
        send_json_response(false, [], 'ID de partie manquant');
    }

    // Vérifier la partie
    $stmt = $pdo->prepare("SELECT g.*, gs.name as game_set_name
        FROM " . DB_PREFIX . "games g
        LEFT JOIN " . DB_PREFIX . "game_sets gs ON g.game_set_id = gs.id
        WHERE g.id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        send_json_response(false, [], 'Partie introuvable');
    }

    // Vérifier que le joueur fait partie de cette partie
    if ($player_id) {
        $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "players WHERE id = ? AND game_id = ?");
        $stmt->execute([$player_id, $game_id]);
        $requesting_player = $stmt->fetch();

        if (!$requesting_player) {
            send_json_response(false, [], 'Vous n\'êtes pas autorisé à voir cette partie');
        }

        $is_creator = (bool)$requesting_player['is_creator'];
    } else {
        $is_creator = false;
    }

    // Récupérer le nom de la configuration
    $game_config = json_decode($game['game_config'], true) ?: [];
    $game_set_ids = array_merge(
        [$game_config['base_game'] ?? $game['game_set_id']],
        $game_config['extensions'] ?? []
    );

    $placeholders = implode(',', array_fill(0, count($game_set_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM " . DB_PREFIX . "game_sets WHERE id IN ($placeholders)");
    $stmt->execute($game_set_ids);
    $games_rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $game_names = [];
    foreach ($game_set_ids as $id) {
        if (isset($games_rows[$id])) {
            $game_names[] = $games_rows[$id];
        }
    }
    $game_config_name = implode(' + ', $game_names);

    // Récupérer tous les joueurs (sauf le créateur)
    $stmt = $pdo->prepare("SELECT id, player_name, player_code, is_creator, used
        FROM " . DB_PREFIX . "players
        WHERE game_id = ? AND is_creator = 0
        ORDER BY id ASC");
    $stmt->execute([$game_id]);
    $other_players = $stmt->fetchAll();

    // Récupérer le créateur
    $stmt = $pdo->prepare("SELECT player_name FROM " . DB_PREFIX . "players
        WHERE game_id = ? AND is_creator = 1");
    $stmt->execute([$game_id]);
    $creator_name = $stmt->fetchColumn();

    $players_data = [];
    foreach ($other_players as $player) {
        $players_data[] = [
            'id' => intval($player['id']),
            'name' => $player['player_name'],
            'code' => $player['player_code'],
            'has_joined' => (bool)$player['used']
        ];
    }

    $join_page_url = get_app_url('rejoindre.php');

    send_json_response(true, [
        'game_id' => intval($game_id),
        'creator_name' => $creator_name,
        'game_config_name' => $game_config_name,
        'players' => $players_data,
        'join_page_url' => $join_page_url,
        'is_creator' => $is_creator,
        'game_status' => $game['status']
    ]);
}

/**
 * Annuler une partie (supprimer)
 */
function cancel_game() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $game_id = clean_int(get_post_value('game_id', 0));
    $player_id = clean_int(get_post_value('player_id', 0));

    if (!$game_id) {
        send_json_response(false, [], 'ID de partie manquant');
    }

    // Vérifier que la partie existe
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        send_json_response(false, [], 'Partie introuvable');
    }

    // Vérifier que le joueur est bien le créateur
    if ($player_id) {
        $stmt = $pdo->prepare("SELECT is_creator FROM " . DB_PREFIX . "players WHERE id = ? AND game_id = ?");
        $stmt->execute([$player_id, $game_id]);
        $player = $stmt->fetch();

        if (!$player || !$player['is_creator']) {
            send_json_response(false, [], 'Seul le créateur peut annuler la partie');
        }
    }

    // Supprimer les joueurs d'abord (contrainte de clé étrangère)
    $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "players WHERE game_id = ?");
    $stmt->execute([$game_id]);

    // Supprimer la partie
    $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$game_id]);

    send_json_response(true, [
        'message' => 'Partie annulée avec succès'
    ]);
}

/**
 * Récupérer les cartes des jeux/extensions d'une partie
 */
function get_game_cards() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $game_id = clean_int(get_post_value('game_id', 0));

    if (!$game_id) {
        send_json_response(false, [], 'ID de partie manquant');
    }

    // Récupérer la partie et sa configuration
    $stmt = $pdo->prepare("SELECT game_config, game_set_id FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        send_json_response(false, [], 'Partie introuvable');
    }

    // Récupérer les IDs des jeux/extensions
    $game_config = json_decode($game['game_config'], true) ?: [];
    $game_set_ids = array_merge(
        [$game_config['base_game'] ?? $game['game_set_id']],
        $game_config['extensions'] ?? []
    );

    // Récupérer les infos des jeux
    $placeholders = implode(',', array_fill(0, count($game_set_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM " . DB_PREFIX . "game_sets WHERE id IN ($placeholders) ORDER BY name ASC");
    $stmt->execute($game_set_ids);
    $game_sets = $stmt->fetchAll();

    // Récupérer les cartes pour chaque jeu/extension
    $result = [];
    foreach ($game_sets as $game_set) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.card_type, c.image_url, c.power_text, c.has_eye, c.quantity
            FROM " . DB_PREFIX . "cards c
            WHERE c.game_set_id = ? AND c.is_visible = 1
            ORDER BY c.display_order ASC, c.name ASC
        ");
        $stmt->execute([$game_set['id']]);
        $cards = $stmt->fetchAll();

        $result[] = [
            'id' => $game_set['id'],
            'name' => $game_set['name'],
            'cards' => $cards
        ];
    }

    send_json_response(true, [
        'game_sets' => $result
    ]);
}
