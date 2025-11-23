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

    $start_time = microtime(true);
    $log_steps = [];
    $log_steps['start'] = 0;

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
    $game_set_id = $game['game_set_id']; // Jeu de base (pour la config de difficulté)

    // Récupérer tous les game_set_ids (base + extensions)
    $game_set_ids = [$game_set_id];
    if ($game['game_config']) {
        $config = json_decode($game['game_config'], true);
        if ($config && isset($config['extensions']) && is_array($config['extensions'])) {
            $game_set_ids = array_merge($game_set_ids, $config['extensions']);
        }
    }

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

        // Vérifier si c'est un objectif spécial
        $is_special = isset($existing_objective['_special_id']);
        $special_image = null;

        if ($is_special) {
            $special_id = $existing_objective['_special_id'];
            $stmt = $pdo->prepare("SELECT image_url FROM " . DB_PREFIX . "special_objective_images
                WHERE special_objective_id = ? AND player_count = ?");
            $stmt->execute([$special_id, $game['player_count']]);
            $special_image = $stmt->fetchColumn();
        }

        send_json_response(true, [
            'objective' => $existing_objective,
            'player_name' => $player['player_name'],
            'pictos' => $pictos_v2,
            'already_generated' => true,
            'is_special_objective' => $is_special,
            'special_image' => $special_image
        ]);
        return;
    }

    $log_steps['before_distribution'] = round((microtime(true) - $start_time) * 1000, 2);

    // Analyser la distribution pour identifier les types rares
    // Combiner les distributions de tous les jeux sélectionnés (base + extensions)
    // OPTIMISATION: Stocker les distributions individuelles pour réutilisation
    $distributions_by_game = [];
    $distribution = [];
    foreach ($game_set_ids as $gsid) {
        $gs_distribution = analyze_type_distribution($gsid);
        $distributions_by_game[$gsid] = $gs_distribution; // Stocker pour réutilisation
        foreach ($gs_distribution as $type_id => $data) {
            if (!isset($distribution[$type_id])) {
                $distribution[$type_id] = $data;
            } else {
                // Combiner les données
                $distribution[$type_id]['total_symbols'] += $data['total_symbols'];
                $distribution[$type_id]['card_count'] += $data['card_count'];
                $distribution[$type_id]['max_on_card'] = max($distribution[$type_id]['max_on_card'], $data['max_on_card']);
                $distribution[$type_id]['cards_list'] = array_merge($distribution[$type_id]['cards_list'], $data['cards_list']);
            }
        }
    }
    $log_steps['after_distribution'] = round((microtime(true) - $start_time) * 1000, 2);

    // Séparer types abondants et rares selon la distribution RÉELLE
    // IMPORTANT: Exclure les types qui n'ont AUCUN symbole dans les cartes
    $abundant_types = [];
    $rare_types = [];
    $all_valid_types = []; // Types avec au moins 1 symbole

    foreach ($available_types as $type) {
        // Si le type n'existe pas dans la distribution = 0 cartes = on l'ignore complètement
        if (!isset($distribution[$type['id']])) {
            continue; // Type avec 0 symboles, on l'exclut
        }

        $all_valid_types[] = $type; // Ce type a des symboles, il est valide

        // Un type est considéré rare s'il a < 15 symboles total
        $is_rare = $distribution[$type['id']]['total_symbols'] < 15;

        if ($is_rare) {
            $rare_types[] = $type;
        } else {
            $abundant_types[] = $type;
        }
    }

    // Vérifier qu'il reste des types valides après filtrage
    if (empty($all_valid_types)) {
        send_json_response(false, [], "Aucun type avec des symboles sur les cartes de ce jeu");
    }

    // OPTIMISATION: Pré-charger toutes les configurations de difficulté en une seule requête
    $stmt = $pdo->prepare("SELECT types_count, min_quantity, max_quantity, generation_weight
        FROM " . DB_PREFIX . "difficulty_config
        WHERE game_set_id = ? AND difficulty = ?");
    $stmt->execute([$game_set_id, $difficulty]);
    $difficulty_configs_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indexer par types_count pour accès rapide O(1)
    $difficulty_configs = [];
    $generation_weights = [];
    foreach ($difficulty_configs_raw as $config) {
        $tc = $config['types_count'];
        $difficulty_configs[$tc] = [
            'min_quantity' => $config['min_quantity'],
            'max_quantity' => $config['max_quantity']
        ];
        if ($config['generation_weight'] > 0) {
            $generation_weights[$tc] = $config['generation_weight'];
        }
    }

    $log_steps['after_difficulty_config'] = round((microtime(true) - $start_time) * 1000, 2);

    // Déterminer le nombre de types en utilisant les poids de génération PRE-CHARGES
    $types_count = select_types_count_weighted($pdo, $game_set_id, $difficulty, count($all_valid_types), $generation_weights);
    $log_steps['after_types_count'] = round((microtime(true) - $start_time) * 1000, 2);

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
        // Pour 3+ types, on peut mélanger types abondants ET rares (mais jamais les types à 0 symboles)
        $available_for_selection = $all_valid_types;
    }

    // OPTIMISATION: Utiliser la config pré-chargée
    if (!isset($difficulty_configs[$types_count])) {
        // Valeurs par défaut
        $min_quantity = 3;
        $max_quantity = 8;
    } else {
        $min_quantity = $difficulty_configs[$types_count]['min_quantity'];
        $max_quantity = $difficulty_configs[$types_count]['max_quantity'];
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

    $log_steps['before_type_limits'] = round((microtime(true) - $start_time) * 1000, 2);

    // Obtenir les limites réalistes par type selon le nombre de joueurs
    // Combiner les limites de tous les jeux sélectionnés (base + extensions)
    // OPTIMISATION: Réutiliser les distributions déjà calculées
    $player_count = (int)$game['player_count'];
    $type_limits = [];
    foreach ($game_set_ids as $gsid) {
        // Passer la distribution déjà calculée pour éviter un nouvel appel SQL
        $gs_distribution = $distributions_by_game[$gsid] ?? null;
        $gs_limits = get_type_limits_by_player_count($pdo, $gsid, $player_count, $gs_distribution);
        foreach ($gs_limits as $type_id => $limit) {
            if (!isset($type_limits[$type_id])) {
                $type_limits[$type_id] = $limit;
            } else {
                $type_limits[$type_id] += $limit; // Cumuler les limites
            }
        }
    }
    $log_steps['after_type_limits'] = round((microtime(true) - $start_time) * 1000, 2);

    // Récupérer les objectifs déjà générés pour les autres joueurs de cette partie
    $stmt = $pdo->prepare("SELECT objective_json FROM " . DB_PREFIX . "players
        WHERE game_id = ? AND id != ? AND objective_json IS NOT NULL AND objective_json != ''");
    $stmt->execute([$player['game_id'], $player_id]);
    $existing_objectives_json = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $existing_objectives = [];
    foreach ($existing_objectives_json as $obj_json) {
        $existing_objectives[] = json_decode($obj_json, true);
    }

    // ===== TIRAGE OBJECTIF SPÉCIAL =====
    // Le tirage a été fait à la création de la partie, on vérifie si ce joueur est le gagnant
    // L'ordre du joueur = nombre d'objectifs déjà générés + 1
    $current_player_order = count($existing_objectives) + 1;
    $special_objective_result = try_assign_special_objective($pdo, $game, $current_player_order);

    if ($special_objective_result !== null) {
        // Ce joueur a gagné l'objectif spécial !
        $objectif = $special_objective_result['objective'];
        $objectif_json = json_encode($objectif);

        // Enregistrer l'objectif spécial
        $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "players
            SET objective_json = ?, generated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$objectif_json, $player_id]);

        $log_steps['special_objective'] = true;
        $log_steps['total_ms'] = round((microtime(true) - $start_time) * 1000, 2);

        send_json_response(true, [
            'objective' => $objectif,
            'player_name' => $player['player_name'],
            'pictos' => $pictos_v2,
            'already_generated' => false,
            'is_special_objective' => true,
            'special_image' => $special_objective_result['image'],
            'special_name' => $special_objective_result['name'],
            'debug_timing' => $log_steps
        ]);
        return;
    }
    // ===== FIN TIRAGE OBJECTIF SPÉCIAL =====

    // NOUVELLE APPROCHE ÉQUILIBRÉE avec contrainte d'unicité :
    // Générer un objectif en assignant à chaque type une quantité entre min et max
    // min_quantity et max_quantity sont les valeurs PER TYPE (pas le total)
    // Ex: 3 types avec min=4, max=8 → chaque type aura entre 4-8 symboles
    $min_per_type = $min_quantity;  // Utiliser min_quantity de la config comme min per type
    $max_per_type = $max_quantity;  // Utiliser max_quantity de la config comme max per type

    $log_steps['before_generation_loop'] = round((microtime(true) - $start_time) * 1000, 2);

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

    $log_steps['after_generation_loop'] = round((microtime(true) - $start_time) * 1000, 2);
    $log_steps['generation_attempts'] = $attempt;

    if (!$is_unique) {
        send_json_response(false, [], "Impossible de générer un objectif unique après {$max_attempts} tentatives");
    }

    $objectif_json = json_encode($objectif);

    // Enregistrer l'objectif
    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "players
        SET objective_json = ?, generated_at = NOW()
        WHERE id = ?");
    $stmt->execute([$objectif_json, $player_id]);

    $log_steps['after_save'] = round((microtime(true) - $start_time) * 1000, 2);
    $log_steps['total_ms'] = round((microtime(true) - $start_time) * 1000, 2);

    send_json_response(true, [
        'objective' => $objectif,
        'player_name' => $player['player_name'],
        'pictos' => $pictos_v2,
        'already_generated' => false,
        'debug_timing' => $log_steps
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

    // Récupérer le statut de la partie et le nom du créateur
    $stmt = $pdo->prepare("
        SELECT g.status as game_status,
               creator.player_name as creator_name
        FROM " . DB_PREFIX . "games g
        LEFT JOIN " . DB_PREFIX . "players creator ON g.id = creator.game_id AND creator.is_creator = 1
        WHERE g.id = ?
    ");
    $stmt->execute([$player['game_id']]);
    $game_info = $stmt->fetch();

    $game_status = $game_info ? $game_info['game_status'] : 'active';
    $creator_name = $game_info ? $game_info['creator_name'] : '';

    // Si pas d'objectif, renvoyer success avec objective vide (pour le chargement initial)
    if (empty($player['objective_json'])) {
        send_json_response(true, [
            'objective' => null,
            'player_name' => $player['player_name'],
            'pictos' => [],
            'has_objective' => false,
            'game_status' => $game_status,
            'creator_name' => $creator_name
        ]);
        return;
    }

    $objective = json_decode($player['objective_json'], true);

    // Récupérer la partie pour obtenir les pictos
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "games WHERE id = ?");
    $stmt->execute([$player['game_id']]);
    $game = $stmt->fetch();

    $pictos_v2 = [];

    if ($game) {
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

        // Créer les pictos
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
    }

    send_json_response(true, [
        'objective' => $objective,
        'player_name' => $player['player_name'],
        'pictos' => $pictos_v2,
        'generated_at' => $player['generated_at'],
        'has_objective' => true,
        'game_status' => $game_status,
        'creator_name' => $creator_name
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
