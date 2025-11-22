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

    // Support pour un seul game_set_id (ancien format) ou plusieurs game_set_ids (nouveau format)
    $game_set_ids = [];
    if (!empty($input['game_set_ids']) && is_array($input['game_set_ids'])) {
        $game_set_ids = array_map('intval', $input['game_set_ids']);
    } elseif (!empty($input['game_set_id'])) {
        $game_set_ids = [clean_int($input['game_set_id'])];
    }

    $difficulty = clean_string($input['difficulty'] ?? 'normal');
    $player_counts = $input['player_counts'] ?? [2, 3, 4];

    if (empty($game_set_ids)) {
        send_json_response(false, [], 'Game Set ID(s) manquant(s)');
    }

    // Récupérer les types disponibles pour TOUS les jeux sélectionnés
    $placeholders = implode(',', array_fill(0, count($game_set_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.*, st.is_limited, st.max_quantity
        FROM " . DB_PREFIX . "types t
        JOIN " . DB_PREFIX . "set_types st ON t.id = st.type_id
        WHERE st.game_set_id IN ($placeholders)
        ORDER BY t.display_order
    ");
    $stmt->execute($game_set_ids);
    $available_types = $stmt->fetchAll();

    // Utiliser le premier game_set_id pour la config de difficulté (jeu de base prioritaire)
    $primary_game_set_id = $game_set_ids[0];

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
    // Combiner les distributions de tous les jeux sélectionnés
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

    // OPTIMISATION: Pré-calculer les limites de type pour 4 joueurs (la base)
    // Une seule fois au lieu de les recalculer dans chaque boucle
    $type_limits_4players = [];
    foreach ($game_set_ids as $gsid) {
        $gs_distribution = $distributions_by_game[$gsid] ?? null;
        $gs_limits = get_type_limits_by_player_count($pdo, $gsid, 4, $gs_distribution);
        foreach ($gs_limits as $type_id => $limit) {
            if (!isset($type_limits_4players[$type_id])) {
                $type_limits_4players[$type_id] = $limit;
            } else {
                $type_limits_4players[$type_id] += $limit;
            }
        }
    }

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
    $stmt->execute([$primary_game_set_id, $difficulty]);
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

            // OPTIMISATION: Sélection pondérée locale (évite requête SQL)
            $max_types = count($all_valid_types);
            $valid_weights = array_filter($generation_weights, function($tc) use ($max_types) {
                return $tc <= $max_types;
            }, ARRAY_FILTER_USE_KEY);

            if (empty($valid_weights)) {
                // Fallback: distribution uniforme 1-3
                $types_count = rand(1, min(3, $max_types));
            } else {
                // Sélection pondérée
                $types_count = weighted_random($valid_weights);
            }

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

            // OPTIMISATION: Utiliser les limites pré-calculées au lieu de les recalculer
            $type_limits = $type_limits_4players;

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
