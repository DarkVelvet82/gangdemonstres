# Algorithme de Génération d'Objectifs - Gang de Monstres

## Vue d'ensemble

L'algorithme génère des objectifs personnalisés pour chaque joueur en fonction du jeu sélectionné, de la difficulté choisie, et du nombre de joueurs. Chaque objectif est une combinaison de types de monstres avec des quantités à collecter.

---

## Architecture Actuelle

### 1. Point d'entrée : `api/player.php`

**Fonction principale :** `generate_objective()`

**Flux de traitement :**
```
Client (AJAX) → api/player.php → generate_objective()
                                        ↓
                                  Récupération config
                                        ↓
                                  Sélection types
                                        ↓
                                  Attribution quantités
                                        ↓
                                  Génération pictos
                                        ↓
                                  Stockage en DB
                                        ↓
                                  Réponse JSON
```

### 2. Paramètres d'entrée

| Paramètre | Type | Description |
|-----------|------|-------------|
| `game_set_id` | int | ID du jeu/extension sélectionné |
| `difficulty` | string | Niveau de difficulté (facile, moyen, difficile, expert) |
| `players_count` | int | Nombre de joueurs dans la partie |
| `player_name` | string | Nom du joueur (optionnel) |

### 3. Tables utilisées

#### `wp_objectif_types`
Définit les types de monstres disponibles
```sql
- id: Identifiant unique
- name: Nom du type (ex: "Faucheuse", "Garou")
- image_url: URL de l'icône
- display_order: Ordre d'affichage
```

#### `wp_objectif_game_sets`
Définit les jeux et extensions
```sql
- id: Identifiant unique
- name: Nom du jeu (ex: "Jeu de Base")
- description: Description
- is_base_game: Booléen (1 = jeu de base)
- display_order: Ordre d'affichage
```

#### `wp_objectif_set_types`
Association entre jeux et types (junction table)
```sql
- game_set_id: ID du jeu
- type_id: ID du type
- is_limited: Le type a une limite de quantité max
- max_quantity: Quantité maximale autorisée (si is_limited = 1)
```

#### `wp_objectif_difficulty`
Configuration des difficultés
```sql
- difficulty_level: Clé de difficulté
- min_types: Nombre minimum de types différents
- max_types: Nombre maximum de types différents
- min_quantity: Quantité minimum par type
- max_quantity: Quantité maximum par type
- multiplayer_bonus: Bonus pour parties multijoueurs
```

#### `wp_objectif_players`
Stockage des objectifs générés
```sql
- id: Identifiant unique
- game_id: ID du jeu sélectionné
- difficulty: Niveau de difficulté
- player_name: Nom du joueur
- objective: JSON de l'objectif
- pictos: JSON des pictos
- used: Booléen (objectif utilisé ou non)
- created_at: Date de création
```

### 4. Logique actuelle

#### Étape 1 : Récupération de la configuration de difficulté
```php
$stmt = $pdo->prepare("SELECT * FROM wp_objectif_difficulty WHERE difficulty_level = ?");
$difficulty_config = $stmt->fetch();
```

**Exemple de config :**
```json
{
  "difficulty_level": "moyen",
  "min_types": 3,
  "max_types": 5,
  "min_quantity": 2,
  "max_quantity": 4,
  "multiplayer_bonus": 1
}
```

#### Étape 2 : Récupération des types disponibles pour le jeu
```php
$stmt = $pdo->prepare("
    SELECT t.*, st.is_limited, st.max_quantity
    FROM wp_objectif_types t
    INNER JOIN wp_objectif_set_types st ON t.id = st.type_id
    WHERE st.game_set_id = ?
    ORDER BY t.display_order
");
```

#### Étape 3 : Calcul du nombre de types à inclure
```php
$types_count = rand($min_types, $max_types);

// Bonus multijoueur
if ($players_count > 1) {
    $types_count += $multiplayer_bonus;
}
```

#### Étape 4 : Sélection aléatoire des types
```php
shuffle($available_types);
$selected_types = array_slice($available_types, 0, $types_count);
```

#### Étape 5 : Attribution des quantités
```php
foreach ($selected_types as $type) {
    if ($type['is_limited'] && $type['max_quantity']) {
        $max = min($max_quantity, $type['max_quantity']);
    }

    $quantity = rand($min_quantity, $max_quantity);
    $objectif[$type['id']] = $quantity;
}
```

**Exemple d'objectif généré :**
```json
{
  "1": 3,  // 3x Faucheuse
  "2": 2,  // 2x Garou
  "4": 4   // 4x Citrouille
}
```

#### Étape 6 : Génération des pictos
```php
$pictos_v2 = [];
foreach ($available_types as $type) {
    $pictos_v2[$type['id']] = [
        'type' => 'image',
        'value' => $type['image_url'],
        'name' => $type['name']
    ];
}
```

#### Étape 7 : Stockage en base de données
```php
$stmt = $pdo->prepare("
    INSERT INTO wp_objectif_players
    (game_id, difficulty, player_name, objective, pictos, used)
    VALUES (?, ?, ?, ?, ?, 0)
");
$stmt->execute([
    $game_set_id,
    $difficulty,
    $player_name,
    json_encode($objectif),
    json_encode($pictos_v2)
]);
```

---

## Limitations Actuelles

### 1. **Distribution purement aléatoire**
- Ne prend pas en compte la répartition réelle des cartes dans le jeu
- Peut générer des objectifs impossibles ou très difficiles
- Exemple : demander 5x un type qui n'a que 3 cartes dans le deck

### 2. **Pas de gestion de la rareté**
- Tous les types ont la même probabilité d'être sélectionnés
- Ne reflète pas la difficulté réelle de collecte

### 3. **Manque de validation**
- Aucune vérification de la faisabilité de l'objectif
- Pas de contrôle sur les combinaisons générées

### 4. **Absence de stratégie**
- Ne crée pas de parcours progressifs
- Pas de variation dans les styles de jeu

---

## Plan d'Amélioration : Algorithme Basé sur les Cartes

### Objectif
Utiliser la base de données des cartes pour générer des objectifs **réalistes**, **équilibrés** et **variés**.

### Nouvelles Tables Utilisées

#### `wp_objectif_cards`
Base de données complète des cartes
```sql
- id: ID de la carte
- name: Nom (ex: "Boss des nonos")
- card_type: Type (monster | dirty_trick)
- game_set_id: Jeu d'appartenance
- is_visible: Visibilité frontend (0 = cachée)
```

#### `wp_objectif_card_types`
Types présents sur chaque carte monstre
```sql
- card_id: ID de la carte
- type_id: ID du type (NULL pour "vide")
- quantity: Nombre de symboles de ce type (1-5)
```

### Phase 1 : Analyse du Pool de Cartes

#### 1.1 Calculer la distribution réelle des types
```php
function analyze_type_distribution($game_set_id) {
    global $pdo;

    // Récupérer toutes les cartes monstres visibles du jeu
    $stmt = $pdo->prepare("
        SELECT c.id, c.name
        FROM wp_objectif_cards c
        WHERE c.game_set_id = ?
        AND c.card_type = 'monster'
        AND c.is_visible = 1
    ");
    $stmt->execute([$game_set_id]);
    $cards = $stmt->fetchAll();

    $distribution = [];

    foreach ($cards as $card) {
        // Récupérer les types de cette carte
        $stmt = $pdo->prepare("
            SELECT type_id, quantity
            FROM wp_objectif_card_types
            WHERE card_id = ?
        ");
        $stmt->execute([$card['id']]);
        $card_types = $stmt->fetchAll();

        foreach ($card_types as $ct) {
            $type_id = $ct['type_id'];
            $qty = $ct['quantity'];

            if (!isset($distribution[$type_id])) {
                $distribution[$type_id] = [
                    'total_symbols' => 0,
                    'card_count' => 0,
                    'max_on_card' => 0
                ];
            }

            $distribution[$type_id]['total_symbols'] += $qty;
            $distribution[$type_id]['card_count']++;
            $distribution[$type_id]['max_on_card'] = max(
                $distribution[$type_id]['max_on_card'],
                $qty
            );
        }
    }

    return $distribution;
}
```

**Exemple de résultat :**
```json
{
  "1": {  // Faucheuse
    "total_symbols": 45,  // 45 symboles au total dans le deck
    "card_count": 18,     // 18 cartes ont ce type
    "max_on_card": 4      // Max 4 symboles sur une seule carte
  },
  "2": {  // Garou
    "total_symbols": 38,
    "card_count": 15,
    "max_on_card": 3
  }
}
```

#### 1.2 Calculer la rareté relative
```php
function calculate_rarity($distribution) {
    $rarity_scores = [];
    $total_cards = array_sum(array_column($distribution, 'card_count'));

    foreach ($distribution as $type_id => $data) {
        // Score de rareté : plus c'est rare, plus le score est élevé
        $rarity_scores[$type_id] = 1 - ($data['card_count'] / $total_cards);
    }

    return $rarity_scores;
}
```

### Phase 2 : Génération Intelligente

#### 2.1 Sélection pondérée des types
```php
function select_types_weighted($distribution, $rarity_scores, $types_count, $difficulty) {
    $selected = [];

    // Définir le facteur de rareté selon la difficulté
    $rarity_factor = [
        'facile' => 0.3,   // Favorise les types communs
        'moyen' => 0.5,    // Équilibré
        'difficile' => 0.7, // Favorise les types rares
        'expert' => 0.9    // Très orienté vers les types rares
    ][$difficulty];

    // Créer un pool pondéré
    $weighted_pool = [];
    foreach ($distribution as $type_id => $data) {
        // Poids = fréquence + (rareté * facteur)
        $weight = $data['card_count'] +
                  ($rarity_scores[$type_id] * $rarity_factor * 100);

        $weighted_pool[$type_id] = $weight;
    }

    // Sélection aléatoire pondérée
    for ($i = 0; $i < $types_count; $i++) {
        $type_id = weighted_random($weighted_pool);
        $selected[] = $type_id;
        unset($weighted_pool[$type_id]); // Éviter les doublons
    }

    return $selected;
}

function weighted_random($weights) {
    $total = array_sum($weights);
    $random = rand(1, $total);

    foreach ($weights as $id => $weight) {
        if ($random <= $weight) {
            return $id;
        }
        $random -= $weight;
    }

    return array_key_first($weights);
}
```

#### 2.2 Attribution réaliste des quantités
```php
function assign_quantities($selected_types, $distribution, $difficulty_config) {
    $objective = [];

    foreach ($selected_types as $type_id) {
        $data = $distribution[$type_id];

        // Calculer la quantité max réaliste
        // On ne peut pas demander plus que le nombre de cartes disponibles
        $realistic_max = min(
            $difficulty_config['max_quantity'],
            $data['card_count']
        );

        $quantity = rand(
            $difficulty_config['min_quantity'],
            $realistic_max
        );

        $objective[$type_id] = $quantity;
    }

    return $objective;
}
```

### Phase 3 : Validation et Ajustement

#### 3.1 Vérifier la faisabilité
```php
function validate_objective($objective, $distribution) {
    foreach ($objective as $type_id => $required_qty) {
        $available = $distribution[$type_id]['card_count'];

        if ($required_qty > $available) {
            // Ajuster à la valeur max disponible
            $objective[$type_id] = $available;
        }
    }

    return $objective;
}
```

#### 3.2 Calculer le score de difficulté
```php
function calculate_difficulty_score($objective, $distribution, $rarity_scores) {
    $score = 0;

    foreach ($objective as $type_id => $required_qty) {
        $data = $distribution[$type_id];

        // Facteurs de difficulté :
        // 1. Rareté du type
        $rarity_score = $rarity_scores[$type_id] * 10;

        // 2. Ratio demandé/disponible
        $ratio_score = ($required_qty / $data['card_count']) * 10;

        // 3. Bonus si quantité proche du max
        $max_bonus = ($required_qty / $data['max_on_card']) * 5;

        $score += $rarity_score + $ratio_score + $max_bonus;
    }

    return $score;
}
```

### Phase 4 : Nouvelle Fonction Complète

```php
function generate_objective_v2() {
    global $pdo;

    // 1. Récupérer les paramètres
    $game_set_id = (int)($_POST['game_set_id'] ?? 1);
    $difficulty = $_POST['difficulty'] ?? 'moyen';
    $players_count = (int)($_POST['players_count'] ?? 1);

    // 2. Récupérer la config de difficulté
    $stmt = $pdo->prepare("SELECT * FROM wp_objectif_difficulty WHERE difficulty_level = ?");
    $stmt->execute([$difficulty]);
    $difficulty_config = $stmt->fetch();

    // 3. Analyser la distribution réelle des cartes
    $distribution = analyze_type_distribution($game_set_id);

    if (empty($distribution)) {
        send_json_response(false, [], "Aucune carte disponible pour ce jeu");
    }

    // 4. Calculer la rareté
    $rarity_scores = calculate_rarity($distribution);

    // 5. Calculer le nombre de types
    $types_count = rand(
        $difficulty_config['min_types'],
        $difficulty_config['max_types']
    );

    if ($players_count > 1) {
        $types_count += $difficulty_config['multiplayer_bonus'];
    }

    // Limiter au nombre de types disponibles
    $types_count = min($types_count, count($distribution));

    // 6. Sélectionner les types (pondéré par rareté)
    $selected_types = select_types_weighted(
        $distribution,
        $rarity_scores,
        $types_count,
        $difficulty
    );

    // 7. Attribuer les quantités réalistes
    $objective = assign_quantities(
        $selected_types,
        $distribution,
        $difficulty_config
    );

    // 8. Valider et ajuster
    $objective = validate_objective($objective, $distribution);

    // 9. Calculer le score de difficulté
    $difficulty_score = calculate_difficulty_score(
        $objective,
        $distribution,
        $rarity_scores
    );

    // 10. Générer les pictos (identique à v1)
    $pictos_v2 = generate_pictos($game_set_id);

    // 11. Stocker en base
    $stmt = $pdo->prepare("
        INSERT INTO wp_objectif_players
        (game_id, difficulty, player_name, objective, pictos, difficulty_score, used)
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([
        $game_set_id,
        $difficulty,
        $_POST['player_name'] ?? 'Anonyme',
        json_encode($objective),
        json_encode($pictos_v2),
        $difficulty_score
    ]);

    // 12. Retourner la réponse
    send_json_response(true, [
        'objective' => $objective,
        'pictos' => $pictos_v2,
        'difficulty_score' => round($difficulty_score, 2),
        'player_id' => $pdo->lastInsertId()
    ]);
}
```

---

## Bénéfices de l'Amélioration

### 1. **Réalisme**
- Les objectifs sont toujours réalisables
- Respecte la composition réelle du deck

### 2. **Équilibrage**
- Difficulté progressive et cohérente
- Types rares = objectifs plus difficiles

### 3. **Variété**
- Sélection pondérée = combinaisons plus diverses
- Évite la répétition d'objectifs similaires

### 4. **Extensibilité**
- Facile d'ajouter de nouveaux critères
- Support natif des nouvelles extensions (via `is_visible`)

### 5. **Traçabilité**
- Score de difficulté calculé et stocké
- Permet l'analyse statistique des parties

---

## Migration Progressive

### Étape 1 : Créer `generate_objective_v2()`
- Développer en parallèle de la v1
- Tester sur un environnement de dev

### Étape 2 : Feature Flag
```php
define('USE_OBJECTIVE_V2', false); // Flag de feature

if (USE_OBJECTIVE_V2) {
    generate_objective_v2();
} else {
    generate_objective(); // Version actuelle
}
```

### Étape 3 : Tests A/B
- Comparer les scores de satisfaction
- Analyser les taux de complétion

### Étape 4 : Déploiement complet
- Activer la v2 pour tous
- Archiver l'ancienne fonction

---

## Métriques de Succès

### À mesurer :
1. **Taux de complétion** : % de joueurs qui finissent leurs objectifs
2. **Temps moyen** : Durée moyenne pour compléter un objectif
3. **Distribution des scores** : Vérifier l'équilibrage des difficultés
4. **Satisfaction** : Feedback qualitatif des joueurs

### Requête d'analyse :
```sql
SELECT
    difficulty,
    AVG(difficulty_score) as avg_score,
    COUNT(*) as total_games,
    SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_count
FROM wp_objectif_players
WHERE used = 1
GROUP BY difficulty
ORDER BY avg_score ASC;
```

---

## Prochaines Évolutions Possibles

### 1. Objectifs thématiques
- "Nuit des morts-vivants" : bonus pour Faucheuses
- "Pleine lune" : bonus pour Garous

### 2. Objectifs dynamiques
- Ajuster en temps réel selon le jeu en cours
- Proposer des objectifs secondaires

### 3. Système de combos
- Bonus pour certaines combinaisons de types
- Malus pour d'autres (types "adverses")

### 4. Intégration QR codes
- Scanner une carte = progression automatique
- Vérification de la validité en temps réel

### 5. Mode campagne
- Suite d'objectifs progressifs
- Déblocage de types rares au fil du temps

---

## Conclusion

L'algorithme actuel fonctionne bien pour un prototype, mais l'amélioration basée sur les données réelles des cartes apportera :
- Plus de **réalisme**
- Meilleur **équilibrage**
- Expérience de jeu plus **engageante**

La base de données des cartes est maintenant en place, ce qui permet de commencer l'implémentation de la v2 de l'algorithme.
