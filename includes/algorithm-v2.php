<?php
/**
 * Algorithme v2 de génération d'objectifs
 * Basé sur l'analyse réelle de la distribution des cartes
 */

/**
 * Analyse la distribution réelle des types dans les cartes du jeu
 *
 * @param int|null $game_set_id ID du jeu/extension, ou NULL pour tous les jeux
 * @return array Distribution des types avec stats détaillées
 */
function analyze_type_distribution($game_set_id = null) {
    global $pdo;

    // Récupérer toutes les cartes monstres visibles du jeu
    if ($game_set_id === null || $game_set_id === 0) {
        // Tous les jeux
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.quantity
            FROM " . DB_PREFIX . "cards c
            WHERE c.card_type = 'monster'
            AND c.is_visible = 1
        ");
        $stmt->execute();
    } else {
        // Jeu spécifique
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.quantity
            FROM " . DB_PREFIX . "cards c
            WHERE c.game_set_id = ?
            AND c.card_type = 'monster'
            AND c.is_visible = 1
        ");
        $stmt->execute([$game_set_id]);
    }
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cards)) {
        return [];
    }

    $distribution = [];
    $total_cards = 0;

    foreach ($cards as $card) {
        $card_quantity = (int)$card['quantity']; // Nombre d'exemplaires de cette carte
        $total_cards += $card_quantity;

        // Récupérer les types de cette carte
        $stmt = $pdo->prepare("
            SELECT type_id, quantity
            FROM " . DB_PREFIX . "card_types
            WHERE card_id = ?
        ");
        $stmt->execute([$card['id']]);
        $card_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($card_types as $ct) {
            $type_id = $ct['type_id'];
            $symbols_per_card = (int)$ct['quantity']; // Symboles sur une seule carte

            // Ignorer les entrées sans type_id (ne devrait pas arriver si Pierre vide existe)
            if ($type_id === null) {
                continue;
            }

            if (!isset($distribution[$type_id])) {
                $distribution[$type_id] = [
                    'total_symbols' => 0,    // Nombre total de symboles dans tout le deck
                    'card_count' => 0,        // Nombre total de cartes ayant ce type (incluant exemplaires)
                    'max_on_card' => 0,       // Quantité max sur une seule carte
                    'cards_list' => []        // Liste des cartes pour debug
                ];
            }

            // Multiplier par le nombre d'exemplaires
            $distribution[$type_id]['total_symbols'] += $symbols_per_card * $card_quantity;
            $distribution[$type_id]['card_count'] += $card_quantity;
            $distribution[$type_id]['max_on_card'] = max(
                $distribution[$type_id]['max_on_card'],
                $symbols_per_card
            );
            $distribution[$type_id]['cards_list'][] = [
                'card_id' => $card['id'],
                'card_name' => $card['name'],
                'symbols_per_card' => $symbols_per_card,
                'card_quantity' => $card_quantity,
                'total_symbols' => $symbols_per_card * $card_quantity
            ];
        }
    }

    // Ajouter des métadonnées globales
    foreach ($distribution as $type_id => &$data) {
        // Fréquence relative (% de cartes ayant ce type)
        $data['frequency'] = $data['card_count'] / $total_cards;

        // Densité moyenne (moyenne de symboles par carte ayant ce type)
        $data['avg_density'] = $data['total_symbols'] / $data['card_count'];
    }

    return $distribution;
}

/**
 * Calcule un score de rareté pour chaque type
 * Plus le score est élevé, plus le type est rare
 *
 * @param array $distribution Distribution des types
 * @return array Scores de rareté (0-1)
 */
function calculate_rarity($distribution) {
    if (empty($distribution)) {
        return [];
    }

    $rarity_scores = [];
    $total_cards = 0;

    // Calculer le total de cartes
    foreach ($distribution as $data) {
        $total_cards += $data['card_count'];
    }

    foreach ($distribution as $type_id => $data) {
        // Score de rareté basé sur la fréquence
        // 1 = très rare (peu de cartes), 0 = très commun (beaucoup de cartes)
        $rarity_scores[$type_id] = 1 - ($data['card_count'] / $total_cards);
    }

    return $rarity_scores;
}

/**
 * Recommande des valeurs min/max pour la configuration de difficulté
 * basées sur l'analyse de la distribution réelle des cartes
 *
 * @param int|null $game_set_id ID du jeu/extension
 * @return array Recommandations structurées par difficulté et types_count
 */
function recommend_difficulty_config($game_set_id = null) {
    global $pdo;

    // Analyser la distribution réelle des types
    $distribution = analyze_type_distribution($game_set_id);

    if (empty($distribution)) {
        return [];
    }

    // Identifier les types rares vs abondants
    // Seuil : < 15 symboles = rare, >= 15 symboles = abondant
    $rare_types = [];
    $abundant_types = [];

    foreach ($distribution as $type_id => $data) {
        if ($data['total_symbols'] < 15) {
            $rare_types[$type_id] = $data;
        } else {
            $abundant_types[$type_id] = $data;
        }
    }

    // NOUVELLE APPROCHE ÉQUILIBRÉE (base = 4 joueurs)
    // Objectif : MÊME nombre total de symboles pour TOUS les nombres de types
    // Cela garantit une difficulté égale entre les objectifs
    //
    // Total cible : 10-12 symboles (4 joueurs)
    // - 1 type : 10-12 symboles (types abondants seulement)
    // - 2 types : 10-12 symboles total (réparti: ~5-6 par type)
    // - 3 types : 10-12 symboles total (réparti: ~3-4 par type)
    // - 4 types : 10-12 symboles total (réparti: ~2-3 par type)
    // - 5 types : 10-12 symboles total (réparti: ~2 par type)
    //
    // Les valeurs min/max représentent maintenant le TOTAL de symboles,
    // qui sera réparti automatiquement entre les types sélectionnés

    $recommendations = [];
    $difficulty = 'normal'; // Une seule difficulté

    // Base commune pour TOUS les nombres de types : 10-12 symboles total
    $base_min_total = 10;
    $base_max_total = 12;

    // ========== 1 TYPE ==========
    // Chaque type: 10-12 symboles (types abondants seulement)
    if (!empty($abundant_types)) {
        $recommendations[$difficulty][1] = [
            'game_set_id' => $game_set_id,
            'difficulty' => $difficulty,
            'types_count' => 1,
            'min_quantity' => $base_min_total,
            'max_quantity' => $base_max_total,
            'generation_weight' => 15,
            'notes' => "Chaque type: 10-12 symboles (1 type abondant, base 4 joueurs)"
        ];
    }

    // ========== 2 TYPES ==========
    // Chaque type: 5-6 symboles (total: ~10-12)
    $recommendations[$difficulty][2] = [
        'game_set_id' => $game_set_id,
        'difficulty' => $difficulty,
        'types_count' => 2,
        'min_quantity' => ceil($base_min_total / 2),
        'max_quantity' => ceil($base_max_total / 2),
        'generation_weight' => 25,
        'notes' => "Chaque type: 5-6 symboles (total: ~10-12, base 4 joueurs)"
    ];

    // ========== 3 TYPES ==========
    // Chaque type: 3-4 symboles (total: ~9-12)
    $recommendations[$difficulty][3] = [
        'game_set_id' => $game_set_id,
        'difficulty' => $difficulty,
        'types_count' => 3,
        'min_quantity' => ceil($base_min_total / 3),
        'max_quantity' => ceil($base_max_total / 3),
        'generation_weight' => 35,
        'notes' => "Chaque type: 3-4 symboles (total: ~9-12, base 4 joueurs)"
    ];

    // ========== 4 TYPES ==========
    // Chaque type: 2-3 symboles (total: ~8-12)
    $recommendations[$difficulty][4] = [
        'game_set_id' => $game_set_id,
        'difficulty' => $difficulty,
        'types_count' => 4,
        'min_quantity' => ceil($base_min_total / 4),
        'max_quantity' => ceil($base_max_total / 4),
        'generation_weight' => 20,
        'notes' => "Chaque type: 2-3 symboles (total: ~8-12, base 4 joueurs)"
    ];

    // ========== 5 TYPES ==========
    // Chaque type: 2 symboles (total: ~10)
    $recommendations[$difficulty][5] = [
        'game_set_id' => $game_set_id,
        'difficulty' => $difficulty,
        'types_count' => 5,
        'min_quantity' => 2,
        'max_quantity' => 2,
        'generation_weight' => 5,
        'notes' => "Chaque type: 2 symboles (total: ~10, base 4 joueurs)"
    ];

    return $recommendations;
}

/**
 * Calcule les limites maximales par type selon le nombre de joueurs
 * Retourne pour chaque type_id la quantité maximale réaliste
 *
 * @param PDO $pdo Instance PDO
 * @param int $game_set_id ID du jeu
 * @param int $player_count Nombre de joueurs
 * @return array [type_id => max_quantity]
 */
function get_type_limits_by_player_count($pdo, $game_set_id, $player_count) {
    $distribution = analyze_type_distribution($game_set_id);

    if (empty($distribution)) {
        return [];
    }

    $limits = [];

    foreach ($distribution as $type_id => $data) {
        // Nombre total de symboles disponibles pour ce type
        $total_symbols = $data['total_symbols'];

        // Maximum théorique = 90% du total divisé par le nombre de joueurs
        // Pour les types abondants, on peut être plus généreux
        $max_realistic = floor(($total_symbols * 0.90) / $player_count);

        // IMPORTANT: Pour les types abondants (>= 15 symboles), ne pas limiter trop strictement
        // car cela empêche d'atteindre les min/max configurés
        if ($total_symbols >= 15) {
            // Types abondants: limite très généreuse
            $limits[$type_id] = max(15, $max_realistic);
        } else {
            // Types rares: limite stricte à 2
            $limits[$type_id] = min(2, $max_realistic);
        }
    }

    return $limits;
}

/**
 * Sélectionne des types de manière pondérée selon la rareté et la difficulté
 *
 * @param array $distribution Distribution des types
 * @param array $rarity_scores Scores de rareté
 * @param int $types_count Nombre de types à sélectionner
 * @param string $difficulty Niveau de difficulté
 * @return array IDs des types sélectionnés
 */
function select_types_weighted($distribution, $rarity_scores, $types_count, $difficulty) {
    if (empty($distribution)) {
        return [];
    }

    $selected = [];

    // Facteur de rareté selon la difficulté
    // Plus le facteur est élevé, plus on favorise les types rares
    $rarity_factor = [
        'facile' => 0.2,      // Favorise les types communs
        'moyen' => 0.5,       // Équilibré
        'difficile' => 0.75,  // Favorise les types rares
        'expert' => 0.95      // Très orienté vers les types rares
    ][$difficulty] ?? 0.5;

    // Créer un pool pondéré
    $weighted_pool = [];
    foreach ($distribution as $type_id => $data) {
        // Poids de base = fréquence (nombre de cartes)
        $base_weight = $data['card_count'];

        // Ajustement selon la rareté et la difficulté
        // En facile : on favorise les types communs (poids élevé)
        // En difficile : on favorise les types rares (poids basé sur rareté)
        $rarity_adjustment = (1 - $rarity_factor) * 100 +
                            ($rarity_factor * $rarity_scores[$type_id] * 100);

        $weighted_pool[$type_id] = $base_weight + $rarity_adjustment;
    }

    // Limiter au nombre de types disponibles
    $types_count = min($types_count, count($weighted_pool));

    // Sélection aléatoire pondérée
    for ($i = 0; $i < $types_count; $i++) {
        if (empty($weighted_pool)) {
            break;
        }

        $type_id = weighted_random($weighted_pool);
        $selected[] = $type_id;
        unset($weighted_pool[$type_id]); // Éviter les doublons
    }

    return $selected;
}

/**
 * Sélection aléatoire pondérée
 *
 * @param array $weights Tableau [id => poids]
 * @return mixed ID sélectionné
 */
function weighted_random($weights) {
    if (empty($weights)) {
        return null;
    }

    $total = array_sum($weights);
    $random = mt_rand(1, (int)$total);

    foreach ($weights as $id => $weight) {
        if ($random <= $weight) {
            return $id;
        }
        $random -= $weight;
    }

    return array_key_first($weights);
}

/**
 * Attribue des quantités réalistes basées sur les cartes disponibles
 *
 * @param array $selected_types IDs des types sélectionnés
 * @param array $distribution Distribution des types
 * @param array $difficulty_config Configuration de difficulté
 * @return array Objectif [type_id => quantity]
 */
function assign_quantities_realistic($selected_types, $distribution, $difficulty_config) {
    $objective = [];

    foreach ($selected_types as $type_id) {
        if (!isset($distribution[$type_id])) {
            continue;
        }

        $data = $distribution[$type_id];

        // Calculer la quantité max réaliste
        // On ne peut pas demander plus que le nombre de cartes disponibles
        $realistic_max = min(
            (int)$difficulty_config['max_quantity'],
            $data['card_count']
        );

        // S'assurer que min <= max
        $min_qty = min(
            (int)$difficulty_config['min_quantity'],
            $realistic_max
        );

        $quantity = rand($min_qty, $realistic_max);

        $objective[$type_id] = $quantity;
    }

    return $objective;
}

/**
 * Valide et ajuste un objectif pour s'assurer qu'il est réalisable
 *
 * @param array $objective Objectif [type_id => quantity]
 * @param array $distribution Distribution des types
 * @return array Objectif validé et ajusté
 */
function validate_objective($objective, $distribution) {
    foreach ($objective as $type_id => $required_qty) {
        if (!isset($distribution[$type_id])) {
            // Type introuvable, on le retire
            unset($objective[$type_id]);
            continue;
        }

        $available = $distribution[$type_id]['card_count'];

        if ($required_qty > $available) {
            // Ajuster à la valeur max disponible
            $objective[$type_id] = $available;
        }

        // S'assurer qu'on demande au moins 1
        if ($objective[$type_id] < 1) {
            $objective[$type_id] = 1;
        }
    }

    return $objective;
}

/**
 * Calcule un score de difficulté pour l'objectif généré
 * Plus le score est élevé, plus l'objectif est difficile
 *
 * @param array $objective Objectif [type_id => quantity]
 * @param array $distribution Distribution des types
 * @param array $rarity_scores Scores de rareté
 * @return float Score de difficulté
 */
function calculate_difficulty_score($objective, $distribution, $rarity_scores) {
    $score = 0;

    foreach ($objective as $type_id => $required_qty) {
        if (!isset($distribution[$type_id])) {
            continue;
        }

        $data = $distribution[$type_id];

        // Facteur 1 : Rareté du type (0-10 points)
        $rarity_score = $rarity_scores[$type_id] * 10;

        // Facteur 2 : Ratio demandé/disponible (0-10 points)
        $ratio_score = ($required_qty / $data['card_count']) * 10;

        // Facteur 3 : Difficulté de collecte (0-5 points)
        // Plus la quantité demandée approche du max sur une carte, plus c'est difficile
        $collection_score = ($required_qty / max($data['max_on_card'], 1)) * 5;

        $score += $rarity_score + $ratio_score + $collection_score;
    }

    // Bonus pour le nombre total de types différents
    $type_diversity_bonus = count($objective) * 2;
    $score += $type_diversity_bonus;

    return $score;
}

/**
 * Sélectionne le nombre de types pour un objectif selon les poids de génération configurés
 *
 * @param PDO $pdo Instance PDO
 * @param int $game_set_id ID du jeu
 * @param string $difficulty Difficulté (easy/normal/hard)
 * @param int $max_types Nombre maximum de types disponibles
 * @return int Nombre de types sélectionné (1-5)
 */
function select_types_count_weighted($pdo, $game_set_id, $difficulty, $max_types) {
    // Récupérer les poids de génération depuis la configuration
    $stmt = $pdo->prepare("
        SELECT types_count, generation_weight
        FROM " . DB_PREFIX . "difficulty_config
        WHERE game_set_id = ? AND difficulty = ? AND generation_weight > 0
        ORDER BY types_count ASC
    ");
    $stmt->execute([$game_set_id, $difficulty]);
    $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si aucun poids configuré, utiliser les valeurs par défaut
    if (empty($weights)) {
        $default_weights = [
            1 => 15,  // 15% pour 1 type
            2 => 25,  // 25% pour 2 types
            3 => 35,  // 35% pour 3 types (optimal)
            4 => 20,  // 20% pour 4 types
            5 => 5    // 5% pour 5 types
        ];

        $weights = [];
        foreach ($default_weights as $types_count => $weight) {
            if ($types_count <= $max_types) {
                $weights[] = ['types_count' => $types_count, 'generation_weight' => $weight];
            }
        }
    }

    // Filtrer les types_count qui dépassent le max disponible
    $weights = array_filter($weights, function($w) use ($max_types) {
        return $w['types_count'] <= $max_types;
    });

    if (empty($weights)) {
        // Fallback : retourner un nombre aléatoire entre 1 et max_types
        return rand(1, min(3, $max_types));
    }

    // Créer un tableau pondéré pour la sélection aléatoire
    $weighted_pool = [];
    foreach ($weights as $weight_data) {
        $weighted_pool[$weight_data['types_count']] = $weight_data['generation_weight'];
    }

    // Sélection aléatoire pondérée
    return weighted_random($weighted_pool);
}

/**
 * Applique un ajustement simple sur les quantités d'objectif selon le nombre de joueurs
 *
 * Logique SIMPLIFIÉE : 4 joueurs = BASE, puis on ajoute/enlève selon le nombre de joueurs
 * - Plus il y a de joueurs, plus c'est difficile (moins de cartes par joueur)
 * - Moins il y a de joueurs, plus c'est facile (plus de cartes par joueur)
 *
 * Ajustement :
 * - 2 joueurs : +2 par rapport à la base 4J (ex: 7 Garous → 9 Garous)
 * - 3 joueurs : +1 par rapport à la base 4J (ex: 7 Garous → 8 Garous)
 * - 4 joueurs : BASE (ex: 7 Garous)
 * - 5 joueurs : -1 par rapport à la base 4J (ex: 7 Garous → 6 Garous, min 1)
 * - 6 joueurs : -2 par rapport à la base 4J (ex: 7 Garous → 5 Garous, min 1)
 * - 7+ joueurs : continue de -1 par joueur supplémentaire (min 1 toujours)
 *
 * @param array $objective Objectif [type_id => quantity]
 * @param int $player_count Nombre de joueurs (2-10)
 * @return array Objectif ajusté
 */
function apply_player_count_adjustment($objective, $player_count) {
    // Ajustement par rapport à 4 joueurs (la base)
    $adjustment = 4 - $player_count;

    // Exemples :
    // 2 joueurs : 4 - 2 = +2
    // 3 joueurs : 4 - 3 = +1
    // 4 joueurs : 4 - 4 = 0 (pas de changement)
    // 5 joueurs : 4 - 5 = -1
    // 6 joueurs : 4 - 6 = -2

    $adjusted_objective = [];
    foreach ($objective as $type_id => $quantity) {
        // Appliquer l'ajustement, avec un minimum absolu de 1
        $adjusted_quantity = max(1, $quantity + $adjustment);
        $adjusted_objective[$type_id] = (int)$adjusted_quantity;
    }

    return $adjusted_objective;
}

/**
 * Vérifie si deux objectifs sont identiques
 * Deux objectifs sont considérés identiques s'ils ont exactement les mêmes types avec les mêmes quantités
 *
 * @param array $objective1 Premier objectif [type_id => quantity]
 * @param array $objective2 Deuxième objectif [type_id => quantity]
 * @return bool True si les objectifs sont identiques, false sinon
 */
function objectives_are_identical($objective1, $objective2) {
    // Si les objectifs n'ont pas le même nombre de types, ils sont différents
    if (count($objective1) !== count($objective2)) {
        return false;
    }

    // Vérifier que chaque type_id existe dans les deux objectifs avec la même quantité
    foreach ($objective1 as $type_id => $quantity) {
        if (!isset($objective2[$type_id]) || $objective2[$type_id] !== $quantity) {
            return false;
        }
    }

    return true;
}

/**
 * Génère un objectif en assignant à chaque type une quantité aléatoire dans la plage min/max
 *
 * IMPORTANT: min_per_type et max_per_type sont les limites PER TYPE, pas le total !
 * Ex: 3 types avec min=4, max=8 → chaque type aura entre 4-8 symboles
 *
 * @param array $selected_types Types sélectionnés pour l'objectif
 * @param array $distribution Distribution des types du jeu
 * @param array $type_limits Limites max par type selon le nombre de joueurs
 * @param int $min_per_type Quantité minimum par type (ex: 4)
 * @param int $max_per_type Quantité maximum par type (ex: 8)
 * @return array Objectif [type_id => quantity]
 */
function generate_balanced_objective($selected_types, $distribution, $type_limits, $min_per_type, $max_per_type) {
    $types_count = count($selected_types);

    if ($types_count == 0) {
        return [];
    }

    $objective = [];

    foreach ($selected_types as $type) {
        $type_id = $type['id'];

        // Vérifier si c'est un type rare (< 15 symboles total)
        $is_rare_type = isset($distribution[$type_id]) && $distribution[$type_id]['total_symbols'] < 15;

        // Déterminer les limites pour ce type spécifique
        if ($is_rare_type) {
            // Types rares : max 2 symboles (TOUJOURS)
            $effective_min = 1;
            $effective_max = 2;
        } else {
            // Types abondants : utiliser la plage configurée
            $effective_min = $min_per_type;
            $effective_max = $max_per_type;
        }

        // Limite configurée par le type lui-même (rare)
        if ($type['is_limited'] && $type['max_quantity']) {
            $effective_max = min($effective_max, $type['max_quantity']);
        }

        // Générer une quantité aléatoire dans la plage effective
        $quantity = rand($effective_min, $effective_max);

        $objective[$type_id] = $quantity;
    }

    return $objective;
}
