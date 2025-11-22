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
require_once __DIR__ . '/../includes/algorithm-v2.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'generate':
            generate_objective();
            break;

        case 'check':
            check_objective();
            break;

        case 'info':
            get_player_info();
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

    // Exclure la Pierre vide (ID 1) qui ne doit jamais être dans les objectifs
    $available_types = array_filter($available_types, function($type) {
        return $type['id'] != 1;
    });

    if (empty($available_types)) {
        send_json_response(false, [], "Aucun type d'objectif disponible (hors Pierre vide)");
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

    // Analyser la distribution pour identifier les types rares
    $distribution = analyze_type_distribution($game_set_id);

    // Déterminer le nombre de types en utilisant les poids de génération
    $types_count = select_types_count_weighted($pdo, $game_set_id, $difficulty, count($available_types));

    // Séparer types abondants et rares selon la distribution RÉELLE
    $abundant_types = [];
    $rare_types = [];

    foreach ($available_types as $type) {
        // Un type est considéré rare s'il a < 15 symboles total
        $is_rare = isset($distribution[$type['id']]) && $distribution[$type['id']]['total_symbols'] < 15;

        if ($is_rare) {
            $rare_types[] = $type;
        } else {
            $abundant_types[] = $type;
        }
    }

    // Pour 1 type SEULEMENT : utiliser uniquement les types abondants (pas de Zombies/Sorcière x1)
    // Pour 2 types : utiliser aussi seulement les types abondants
    if ($types_count <= 2) {
        if (count($abundant_types) < $types_count) {
            $types_count = count($abundant_types);
            if ($types_count == 0) {
                send_json_response(false, [], "Aucun type abondant disponible");
            }
        }
        $available_for_selection = $abundant_types;
    } else {
        // Pour 3+ types, on peut mélanger types abondants ET rares
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

    // CONTRAINTE : Maximum 1 type rare par objectif
    // Compter combien de types rares sont dans la sélection
    $rare_count_in_selection = 0;
    foreach ($selected_types as $type) {
        $is_rare = isset($distribution[$type['id']]) && $distribution[$type['id']]['total_symbols'] < 15;
        if ($is_rare) {
            $rare_count_in_selection++;
        }
    }

    // Si plus d'1 type rare, garder seulement le premier et remplacer les autres par des types abondants
    if ($rare_count_in_selection > 1) {
        $first_rare_found = false;
        $types_to_replace = [];

        foreach ($selected_types as $index => $type) {
            $is_rare = isset($distribution[$type['id']]) && $distribution[$type['id']]['total_symbols'] < 15;

            if ($is_rare) {
                if (!$first_rare_found) {
                    // Garder le premier type rare
                    $first_rare_found = true;
                } else {
                    // Marquer les types rares supplémentaires pour remplacement
                    $types_to_replace[] = $index;
                }
            }
        }

        // Remplacer les types rares en trop par des types abondants non déjà sélectionnés
        $selected_type_ids = array_map(function($t) { return $t['id']; }, $selected_types);
        $available_abundant = array_filter($abundant_types, function($t) use ($selected_type_ids) {
            return !in_array($t['id'], $selected_type_ids);
        });

        shuffle($available_abundant);
        $replacement_index = 0;

        foreach ($types_to_replace as $index) {
            if (isset($available_abundant[$replacement_index])) {
                $selected_types[$index] = $available_abundant[$replacement_index];
                $replacement_index++;
            }
        }
    }

    // Obtenir les limites réalistes par type selon le nombre de joueurs
    $player_count = (int)$game['player_count'];
    $type_limits = get_type_limits_by_player_count($pdo, $game_set_id, $player_count);

    // Récupérer les objectifs déjà générés pour les autres joueurs de cette partie
    $stmt = $pdo->prepare("SELECT objective_json FROM " . DB_PREFIX . "players
        WHERE game_id = ? AND id != ? AND objective_json IS NOT NULL AND objective_json != ''");
    $stmt->execute([$player['game_id'], $player_id]);
    $existing_objectives_json = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $existing_objectives = [];
    foreach ($existing_objectives_json as $obj_json) {
        $existing_objectives[] = json_decode($obj_json, true);
    }

    // NOUVELLE APPROCHE ÉQUILIBRÉE avec contrainte d'unicité :
    // Générer un objectif en assignant à chaque type une quantité entre min et max
    // min_quantity et max_quantity sont les valeurs PER TYPE (pas le total)
    // Ex: 3 types avec min=4, max=8 → chaque type aura entre 4-8 symboles
    $min_per_type = $min_quantity;  // Utiliser min_quantity de la config comme min per type
    $max_per_type = $max_quantity;  // Utiliser max_quantity de la config comme max per type

    $max_attempts = 50; // Limite de tentatives pour éviter une boucle infinie
    $attempt = 0;
    $is_unique = false;
    $objectif = null;

    while (!$is_unique && $attempt < $max_attempts) {
        $attempt++;

        // Générer l'objectif équilibré pour 4 joueurs (la base)
        $base_objective = generate_balanced_objective(
            $selected_types,
            $distribution,
            $type_limits,
            $min_per_type,
            $max_per_type
        );

        // Appliquer l'ajustement selon le nombre de joueurs
        // 2J: +2, 3J: +1, 4J: 0, 5J: -1, 6J: -2 (minimum 1 toujours)
        $objectif = apply_player_count_adjustment($base_objective, $player_count);

        // CONTRAINTE : Vérifier que l'objectif est unique dans cette partie
        $is_unique = true;
        foreach ($existing_objectives as $existing_objective) {
            if (objectives_are_identical($objectif, $existing_objective)) {
                $is_unique = false;
                break;
            }
        }
    }

    if (!$is_unique) {
        send_json_response(false, [], "Impossible de générer un objectif unique après {$max_attempts} tentatives");
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

/**
 * Récupérer les informations basiques d'un joueur (nom, statut)
 */
function get_player_info() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $player_id = clean_int(get_post_value('player_id', 0));

    if (!$player_id) {
        send_json_response(false, [], 'Player ID manquant');
    }

    $stmt = $pdo->prepare("SELECT p.*, g.status as game_status FROM " . DB_PREFIX . "players p
        LEFT JOIN " . DB_PREFIX . "games g ON p.game_id = g.id
        WHERE p.id = ?");
    $stmt->execute([$player_id]);
    $player = $stmt->fetch();

    if (!$player) {
        send_json_response(false, [], 'Joueur introuvable');
    }

    send_json_response(true, [
        'player_id' => $player['id'],
        'player_name' => $player['player_name'],
        'is_creator' => (bool)$player['is_creator'],
        'has_objective' => !empty($player['objective_json']),
        'game_status' => $player['game_status']
    ]);
}
