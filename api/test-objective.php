<?php
// api/test-objective.php - Endpoint pour tester la génération d'objectifs

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
        case 'generate_test':
            generate_test_objectives();
            break;

        default:
            send_json_response(false, [], 'Action non reconnue');
    }

} catch (Exception $e) {
    log_error('API Test Error: ' . $e->getMessage());
    send_json_response(false, [], DEBUG_MODE ? $e->getMessage() : 'Erreur serveur');
}

/**
 * Générer des objectifs de test pour plusieurs nombres de joueurs
 */
function generate_test_objectives() {
    global $pdo;

    // Récupérer les données POST
    $input = json_decode(file_get_contents('php://input'), true);

    $game_set_id = clean_int($input['game_set_id'] ?? 0);
    $difficulty = clean_string($input['difficulty'] ?? 'normal');
    $player_counts = $input['player_counts'] ?? [2, 3, 4];

    if (!$game_set_id) {
        send_json_response(false, [], 'Game Set ID manquant');
    }

    // Récupérer les types disponibles
    $stmt = $pdo->prepare("
        SELECT t.*, st.is_limited, st.max_quantity
        FROM " . DB_PREFIX . "types t
        JOIN " . DB_PREFIX . "set_types st ON t.id = st.type_id
        WHERE st.game_set_id = ?
        ORDER BY t.display_order
    ");
    $stmt->execute([$game_set_id]);
    $available_types = $stmt->fetchAll();

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

    // Créer les pictos (utilise l'ID comme clé)
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

    // Analyser la distribution pour identifier les types rares
    $distribution = analyze_type_distribution($game_set_id);

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

    // ÉTAPE 1 : Générer 4 objectifs de BASE (pour 4 joueurs)
    // Ces objectifs seront réutilisés pour 2, 3 et 4 joueurs
    $base_objectives_4players = [];

    for ($i = 0; $i < 4; $i++) {
        $max_attempts = 50; // Limite de tentatives pour éviter une boucle infinie
        $attempt = 0;
        $is_unique = false;
        $base_objective = null;

        while (!$is_unique && $attempt < $max_attempts) {
            $attempt++;

            // Chaque joueur a son propre nombre de types (généré aléatoirement)
            $types_count = select_types_count_weighted($pdo, $game_set_id, $difficulty, count($available_types));

            // Pour 1-2 types : utiliser uniquement les types abondants (pas de Zombies/Sorcière x1)
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

            // Récupérer la configuration de difficulté pour ce nombre de types
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

            // Sélectionner aléatoirement les types pour ce joueur
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

            // Obtenir les limites réalistes par type pour 4 joueurs (la base)
            $type_limits = get_type_limits_by_player_count($pdo, $game_set_id, 4);

            // Générer l'objectif de BASE pour 4 joueurs
            // min_quantity et max_quantity sont les valeurs PER TYPE (pas le total)
            $min_per_type = $min_quantity;
            $max_per_type = $max_quantity;

            $base_objective = generate_balanced_objective(
                $selected_types,
                $distribution,
                $type_limits,
                $min_per_type,
                $max_per_type
            );

            // CONTRAINTE : Vérifier que l'objectif est unique (pas identique aux précédents)
            $is_unique = true;
            foreach ($base_objectives_4players as $existing_objective) {
                if (objectives_are_identical($base_objective, $existing_objective)) {
                    $is_unique = false;
                    break;
                }
            }
        }

        if (!$is_unique) {
            send_json_response(false, [], "Impossible de générer 4 objectifs uniques après {$max_attempts} tentatives");
        }

        $base_objectives_4players[] = $base_objective;
    }

    // ÉTAPE 2 : Appliquer les ajustements pour chaque nombre de joueurs
    $player_results = [];
    foreach ($player_counts as $player_count) {
        $objectives_for_count = [];

        // Utiliser uniquement les N premiers objectifs (N = player_count)
        for ($i = 0; $i < $player_count; $i++) {
            // Prendre l'objectif de base du joueur $i
            $base_objective = $base_objectives_4players[$i];

            // Appliquer l'ajustement selon le nombre de joueurs
            // 2J: +2, 3J: +1, 4J: 0, 5J: -1, 6J: -2 (minimum 1 toujours)
            $adjusted_objective = apply_player_count_adjustment($base_objective, $player_count);

            $objectives_for_count[] = $adjusted_objective;
        }

        $player_results[] = [
            'player_count' => $player_count,
            'objectives' => $objectives_for_count
        ];
    }

    send_json_response(true, [
        'player_results' => $player_results,
        'pictos' => $pictos_v2
    ]);
}
