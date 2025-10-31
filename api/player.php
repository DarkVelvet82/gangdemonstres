<?php
// api/player.php - Endpoints pour la gestion des objectifs joueurs

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
        case 'generate':
            generate_objective();
            break;

        case 'check':
            check_objective();
            break;

        default:
            send_json_response(false, [], 'Action non reconnue');
    }

} catch (Exception $e) {
    log_error('API Error: ' . $e->getMessage());
    send_json_response(false, [], DEBUG_MODE ? $e->getMessage() : 'Erreur serveur');
}

/**
 * Générer un objectif pour un joueur
 */
function generate_objective() {
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

    // Récupérer la partie
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$player['game_id']]);
    $game = $stmt->fetch();

    if (!$game) {
        send_json_response(false, [], 'Partie introuvable');
    }

    $difficulty = $game['difficulty'];
    $game_set_id = $game['game_set_id'];

    // Récupérer les types disponibles
    if ($game['game_config']) {
        $available_types = resolve_available_types($pdo, $game['game_config']);
    } else {
        $stmt = $pdo->prepare("
            SELECT t.*, st.is_limited, st.max_quantity
            FROM " . DB_PREFIX . "types t
            JOIN " . DB_PREFIX . "set_types st ON t.id = st.type_id
            WHERE st.game_set_id = ?
            ORDER BY t.display_order
        ");
        $stmt->execute([$game_set_id]);
        $available_types = $stmt->fetchAll();
    }

    if (empty($available_types)) {
        send_json_response(false, [], "Aucun type d'objectif configuré pour ce jeu");
    }

    // Créer les pictos (utilise l'ID comme clé maintenant)
    $pictos_v2 = [];
    foreach ($available_types as $type) {
        if (!empty($type['image_url'])) {
            $pictos_v2[$type['id']] = [
                'type' => 'image',
                'value' => $type['image_url'],
                'name' => $type['name']
            ];
        } else {
            $pictos_v2[$type['id']] = [
                'type' => 'emoji',
                'value' => $type['emoji'] ?? '',
                'name' => $type['name']
            ];
        }
    }

    // Si objectif déjà généré, le retourner
    if (!empty($player['objective_json'])) {
        $existing_objective = json_decode($player['objective_json'], true);
        send_json_response(true, [
            'objective' => $existing_objective,
            'player_name' => $player['player_name'],
            'pictos' => $pictos_v2,
            'already_generated' => true
        ]);
        return;
    }

    // Déterminer le nombre de types (1 à 3)
    $types_count = rand(1, min(3, count($available_types)));

    // Séparer types normaux et limités
    $normal_types = [];
    $limited_types = [];

    foreach ($available_types as $type) {
        if ($type['is_limited']) {
            $limited_types[] = $type;
        } else {
            $normal_types[] = $type;
        }
    }

    // Pour 1-2 types, utiliser seulement les types normaux
    if ($types_count <= 2) {
        if (count($normal_types) < $types_count) {
            $types_count = count($normal_types);
            if ($types_count == 0) {
                send_json_response(false, [], "Aucun type normal disponible");
            }
        }
        $available_for_selection = $normal_types;
    } else {
        $available_for_selection = $available_types;
    }

    // Récupérer la configuration de difficulté
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "difficulty_config
        WHERE game_set_id = ? AND difficulty = ? AND types_count = ?");
    $stmt->execute([$game_set_id, $difficulty, $types_count]);
    $difficulty_config = $stmt->fetch();

    if (!$difficulty_config) {
        // Valeurs par défaut
        $min_quantity = 3;
        $max_quantity = 8;
    } else {
        $min_quantity = $difficulty_config['min_quantity'];
        $max_quantity = $difficulty_config['max_quantity'];
    }

    // Sélectionner aléatoirement les types
    shuffle($available_for_selection);
    $selected_types = array_slice($available_for_selection, 0, $types_count);

    // Générer l'objectif (utilise l'ID comme clé maintenant)
    $objectif = [];
    foreach ($selected_types as $type) {
        if ($type['is_limited'] && $type['max_quantity']) {
            $quantity = rand(1, min($type['max_quantity'], $max_quantity));
        } else {
            $quantity = rand($min_quantity, $max_quantity);
        }

        $objectif[$type['id']] = $quantity;
    }

    $objectif_json = json_encode($objectif);

    // Enregistrer l'objectif
    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "players
        SET objective_json = ?, generated_at = NOW()
        WHERE id = ?");
    $stmt->execute([$objectif_json, $player_id]);

    send_json_response(true, [
        'objective' => $objectif,
        'player_name' => $player['player_name'],
        'pictos' => $pictos_v2,
        'already_generated' => false
    ]);
}

/**
 * Vérifier l'objectif d'un joueur
 */
function check_objective() {
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

    if (empty($player['objective_json'])) {
        send_json_response(false, [], "Aucun objectif généré pour ce joueur");
    }

    $objective = json_decode($player['objective_json'], true);

    send_json_response(true, [
        'objective' => $objective,
        'player_name' => $player['player_name'],
        'generated_at' => $player['generated_at']
    ]);
}
