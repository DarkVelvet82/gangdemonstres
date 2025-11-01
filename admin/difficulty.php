<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/algorithm-v2.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

$page_title = "Configuration des Objectifs";
$page_description = "Paramétrage de la génération des objectifs";

// Actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_difficulty_config') {
        $game_set_id = (int)$_POST['game_set_id'];

        try {
            // Supprimer l'ancienne configuration
            $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "difficulty_config WHERE game_set_id = ?");
            $stmt->execute([$game_set_id]);

            // Sauvegarder la nouvelle configuration (une seule difficulté: normal)
            $difficulty = 'normal';

            for ($types_count = 1; $types_count <= 5; $types_count++) {
                $min_key = "types_{$types_count}_min";
                $max_key = "types_{$types_count}_max";
                $weight_key = "weight_{$types_count}";

                if (isset($_POST[$min_key]) && isset($_POST[$max_key])) {
                    $min_quantity = (int)$_POST[$min_key];
                    $max_quantity = (int)$_POST[$max_key];
                    $weight = isset($_POST[$weight_key]) ? (int)$_POST[$weight_key] : 0;

                    if ($min_quantity > 0 && $max_quantity >= $min_quantity) {
                        // Vérifier si la colonne generation_weight existe
                        try {
                            $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "difficulty_config (game_set_id, difficulty, types_count, min_quantity, max_quantity, generation_weight) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$game_set_id, $difficulty, $types_count, $min_quantity, $max_quantity, $weight]);
                        } catch (PDOException $e) {
                            // Si la colonne n'existe pas encore, créer sans
                            $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "difficulty_config (game_set_id, difficulty, types_count, min_quantity, max_quantity) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$game_set_id, $difficulty, $types_count, $min_quantity, $max_quantity]);
                        }
                    }
                }
            }

            $message = 'Configuration sauvegardée !';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Récupérer tous les jeux
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "game_sets ORDER BY display_order ASC");
$game_sets = $stmt->fetchAll();

// Sélection du jeu courant
$selected_game_set = isset($_GET['game_set']) ? (int)$_GET['game_set'] : (count($game_sets) > 0 ? $game_sets[0]['id'] : 0);

// Sélection de la difficulté pour les tests (par défaut: normal)
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : 'normal';

// Récupérer la configuration actuelle
$config_matrix = [];
if ($selected_game_set) {
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "difficulty_config WHERE game_set_id = ? ORDER BY difficulty, types_count");
    $stmt->execute([$selected_game_set]);
    $current_config = $stmt->fetchAll();

    foreach ($current_config as $config) {
        $config_matrix[$config['difficulty']][$config['types_count']] = $config;
    }
}

// Obtenir les recommandations basées sur la distribution réelle
$recommendations = recommend_difficulty_config($selected_game_set);

$extra_styles = '<style>
    .difficulty-config-table { width:100%; border-collapse:collapse; margin-top:15px; }
    .difficulty-config-table thead { background:#f8f9fa; }
    .difficulty-config-table th { text-align:center; padding:12px; font-weight:600; border:1px solid #dee2e6; }
    .difficulty-config-table td { text-align:center; padding:12px; border:1px solid #dee2e6; }
    .difficulty-config-table th:first-child, .difficulty-config-table td:first-child { text-align:left; font-weight:600; background:#f8f9fa; }
    .number-input { width:60px; padding:6px; border:1px solid #ddd; border-radius:4px; text-align:center; }
    .difficulty-explanation, .difficulty-notes { background:#f0f6fc; padding:15px; border-radius:8px; margin:15px 0; border-left:4px solid #667eea; }
    .difficulty-notes ul { margin-left:20px; line-height:1.8; }
    select { padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; min-width:250px; }
</style>';

require_once __DIR__ . '/includes/admin-layout.php';
?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if (empty($game_sets)): ?>
    <div class="message warning">
        <p><strong>Aucun jeu configuré.</strong> <a href="games.php">Créez d'abord un jeu</a>.</p>
    </div>
<?php else: ?>
    <!-- Sélecteur de jeu -->
    <div class="card">
        <h2>Sélectionner un jeu/extension</h2>
        <form method="get">
            <input type="hidden" name="page" value="difficulty">
            <select name="game_set" onchange="this.form.submit();">
                <?php foreach ($game_sets as $game_set): ?>
                    <option value="<?php echo $game_set['id']; ?>" <?php echo $selected_game_set == $game_set['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($game_set['name']); ?>
                        <?php echo $game_set['is_base_game'] ? ' (Jeu de base)' : ' (Extension)'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($selected_game_set):
        // Récupérer le nom du jeu sélectionné
        $stmt = $pdo->prepare("SELECT name FROM " . DB_PREFIX . "game_sets WHERE id = ?");
        $stmt->execute([$selected_game_set]);
        $game_name = $stmt->fetchColumn();
    ?>
        <!-- Configuration des difficultés -->
        <div class="card">
            <h2>Configuration pour : <?php echo htmlspecialchars($game_name); ?></h2>

            <?php if (!empty($recommendations)): ?>
                <div class="difficulty-explanation" style="background:#e7f3ff; border-left-color:#007bff;">
                    <p><strong>💡 Recommandations automatiques :</strong> L'algorithme v2 a analysé la distribution réelle de vos cartes et propose des valeurs min/max optimales basées sur :</p>
                    <ul style="line-height:1.8; margin-left:20px;">
                        <li>Le nombre total de symboles disponibles dans le deck</li>
                        <li>La distribution des types entre les cartes</li>
                        <li>Des facteurs d'équilibrage selon la difficulté et le nombre de types</li>
                    </ul>
                    <p><strong>Les valeurs recommandées (⚡) sont affichées sous chaque champ.</strong> Vous pouvez les ajuster manuellement si besoin.</p>
                    <button type="button" onclick="applyAllRecommendations()" class="submit-button" style="background:#007bff; margin-top:10px;">
                        ⚡ Appliquer toutes les recommandations
                    </button>
                </div>
            <?php endif; ?>

            <div class="difficulty-explanation">
                <p><strong>⚖️ Nouvelle approche équilibrée :</strong></p>
                <p style="margin-top:10px;">Les valeurs <strong>Min/Max</strong> représentent le <strong>TOTAL de symboles</strong> à collecter, réparti équitablement entre les types.</p>
                <ul style="line-height:1.8; margin-left:20px; margin-top:8px;">
                    <li><strong>1 type (10-12 total)</strong> : Ex: 11 Garous</li>
                    <li><strong>2 types (10-12 total)</strong> : Ex: 6 Garous + 5 Citrouilles (répartition automatique)</li>
                    <li><strong>3 types (10-12 total)</strong> : Ex: 4 Garous + 4 Citrouilles + 3 Faucheuse</li>
                    <li><strong>4+ types (10-12 total)</strong> : Ex: 3+3+3+2 symboles</li>
                </ul>
                <p style="margin-top:10px; font-weight:600; color:#667eea;">
                    ✅ Tous les objectifs ont maintenant la même difficulté, quel que soit le nombre de types !
                </p>
            </div>

            <div class="difficulty-explanation" style="background:#fff3cd; border-left-color:#ffc107;">
                <p><strong>🎲 Poids de génération :</strong></p>
                <p style="margin-top:10px;">Le <strong>poids (%)</strong> détermine la probabilité de générer un objectif avec ce nombre de types.</p>
                <ul style="line-height:1.8; margin-left:20px; margin-top:8px;">
                    <li><strong>1 type (15%)</strong> : Trop visible, les adversaires peuvent facilement bloquer</li>
                    <li><strong>2 types (25%)</strong> : Bon équilibre entre discrétion et concentration</li>
                    <li><strong>3 types (35%)</strong> : Optimal - diversifié mais pas trop dilué</li>
                    <li><strong>4 types (20%)</strong> : Plus difficile à compléter</li>
                    <li><strong>5 types (5%)</strong> : Trop dilué, souvent trop facile</li>
                </ul>
                <p style="margin-top:10px;">
                    <strong>Total des poids : <span id="weight-total" style="color:#ffc107; font-size:18px;">100</span>%</strong>
                    <span id="weight-warning" style="color:#dc3545; font-weight:600; margin-left:10px; display:none;">⚠️ Le total doit être égal à 100%</span>
                </p>
            </div>

            <div class="difficulty-explanation" style="background:#fff8e1; border-left-color:#ffa000;">
                <p><strong>👥 Prévisualisation selon le nombre de joueurs :</strong></p>
                <p style="margin-top:10px;">Les valeurs que vous configurez ci-dessous sont <strong>pour 4 joueurs</strong> (difficulté de référence).</p>
                <p style="margin-top:8px;">Utilisez le sélecteur ci-dessous pour prévisualiser comment les objectifs seront ajustés pour un nombre différent de joueurs :</p>

                <div style="margin-top:15px; display:flex; align-items:center; gap:15px;">
                    <label style="font-weight:600;">Prévisualiser pour :</label>
                    <select id="player-count-preview" style="padding:8px 15px; border:2px solid #ffa000; border-radius:6px; font-size:14px; font-weight:600; background:#fff;">
                        <option value="2">2 joueurs (~18 cartes chacun)</option>
                        <option value="3">3 joueurs (~12 cartes chacun)</option>
                        <option value="4" selected>4 joueurs (~9 cartes chacun) - Base</option>
                        <option value="5">5 joueurs (~7 cartes chacun)</option>
                        <option value="6">6 joueurs (~6 cartes chacun)</option>
                    </select>
                    <span id="multiplier-display" style="padding:6px 12px; background:#fff; border:2px solid #ffa000; border-radius:6px; font-weight:600; color:#ffa000;">
                        Multiplicateur : 100%
                    </span>
                </div>

                <p style="margin-top:10px; font-size:12px; color:#666; font-style:italic;">
                    💡 Les valeurs du tableau ci-dessous s'ajusteront automatiquement pour refléter ce que les joueurs recevront réellement dans une partie.
                </p>
            </div>

            <form method="post" id="difficultyForm">
                <input type="hidden" name="action" value="save_difficulty_config">
                <input type="hidden" name="game_set_id" value="<?php echo $selected_game_set; ?>">

                <table class="difficulty-config-table">
                    <thead>
                        <tr>
                            <th>Nombre de types</th>
                            <th style="background:#fff3cd;">Poids<br><small style="font-weight:400; opacity:0.8;">(%)</small></th>
                            <th>Min Total<br><small style="font-weight:400; opacity:0.7;">(Base / Ajusté)</small></th>
                            <th>Max Total<br><small style="font-weight:400; opacity:0.7;">(Base / Ajusté)</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Poids par défaut recommandés
                        $default_weights = [
                            1 => 15,  // 15% pour 1 type
                            2 => 25,  // 25% pour 2 types
                            3 => 35,  // 35% pour 3 types (optimal)
                            4 => 20,  // 20% pour 4 types
                            5 => 5    // 5% pour 5 types
                        ];

                        $difficulty = 'normal'; // Une seule difficulté

                        for ($types_count = 1; $types_count <= 5; $types_count++):
                            // Récupérer le poids et la config existante
                            $current_weight = null;
                            $current = null;
                            if (isset($config_matrix['normal'][$types_count])) {
                                $current = $config_matrix['normal'][$types_count];
                                $current_weight = $current['generation_weight'] ?? null;
                            }

                            $weight_value = $current_weight !== null ? $current_weight : $default_weights[$types_count];
                            $recommended = isset($recommendations[$difficulty][$types_count]) ? $recommendations[$difficulty][$types_count] : null;

                            // Utiliser la valeur actuelle si elle existe, sinon la recommandation
                            $min_value = $current ? $current['min_quantity'] : ($recommended ? $recommended['min_quantity'] : '');
                            $max_value = $current ? $current['max_quantity'] : ($recommended ? $recommended['max_quantity'] : '');

                            // Placeholder pour afficher la recommandation
                            $min_placeholder = $recommended ? "Rec: " . $recommended['min_quantity'] : 'Min';
                            $max_placeholder = $recommended ? "Rec: " . $recommended['max_quantity'] : 'Max';
                        ?>
                        <tr>
                            <td><strong><?php echo $types_count; ?> type<?php echo $types_count > 1 ? 's' : ''; ?></strong></td>

                            <!-- Colonne Poids -->
                            <td style="background:#fffbf0;">
                                <input type="number"
                                       name="weight_<?php echo $types_count; ?>"
                                       value="<?php echo $weight_value; ?>"
                                       min="0"
                                       max="100"
                                       class="number-input weight-input"
                                       placeholder="%"
                                       style="width:50px; font-weight:600;">
                                <small style="display:block; font-size:10px; color:#856404; margin-top:2px;">%</small>
                            </td>

                            <!-- Colonne Min -->
                            <td>
                                <input type="number"
                                       name="types_<?php echo $types_count; ?>_min"
                                       value="<?php echo $min_value; ?>"
                                       min="1" max="50"
                                       class="number-input base-value"
                                       placeholder="<?php echo $min_placeholder; ?>"
                                       data-recommended="<?php echo $recommended ? $recommended['min_quantity'] : ''; ?>"
                                       data-base-value="<?php echo $min_value; ?>">
                                <div class="adjusted-value-display" style="font-size:13px; font-weight:600; color:#ffa000; margin-top:4px;">
                                    → <span class="adjusted-value"><?php echo $min_value; ?></span>
                                </div>
                                <?php if ($recommended && (!$current || $current['min_quantity'] != $recommended['min_quantity'])): ?>
                                    <small style="display:block; color:#667eea; font-size:10px; margin-top:2px;">
                                        ⚡ <?php echo $recommended['min_quantity']; ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <!-- Colonne Max -->
                            <td>
                                <input type="number"
                                       name="types_<?php echo $types_count; ?>_max"
                                       value="<?php echo $max_value; ?>"
                                       min="1" max="50"
                                       class="number-input base-value"
                                       placeholder="<?php echo $max_placeholder; ?>"
                                       data-recommended="<?php echo $recommended ? $recommended['max_quantity'] : ''; ?>"
                                       data-base-value="<?php echo $max_value; ?>">
                                <div class="adjusted-value-display" style="font-size:13px; font-weight:600; color:#ffa000; margin-top:4px;">
                                    → <span class="adjusted-value"><?php echo $max_value; ?></span>
                                </div>
                                <?php if ($recommended && (!$current || $current['max_quantity'] != $recommended['max_quantity'])): ?>
                                    <small style="display:block; color:#667eea; font-size:10px; margin-top:2px;">
                                        ⚡ <?php echo $recommended['max_quantity']; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <div class="difficulty-notes">
                    <h3>💡 Conseils</h3>
                    <ul>
                        <li><strong>Min/Max Total :</strong> Représentent le TOTAL de symboles à collecter (réparti automatiquement entre les types)</li>
                        <li><strong>Poids :</strong> Probabilité de générer un objectif avec ce nombre de types (total doit = 100%)</li>
                        <li><strong>Tous les objectifs ont la même difficulté</strong> car même nombre total de symboles</li>
                        <li>Recommandation : 10-12 symboles total pour un équilibre optimal (base 4 joueurs)</li>
                    </ul>
                </div>

                <button type="submit" class="submit-button">Sauvegarder la configuration</button>
            </form>

            <button type="button" onclick="testObjectiveGeneration()" class="submit-button" style="background:#28a745; margin-top:20px;">
                🧪 Tester la génération d'objectifs
            </button>

            <div id="test-results" style="margin-top:30px; display:none;"></div>
        </div>

        <script>
        // Multiplicateurs selon le nombre de joueurs
        const PLAYER_MULTIPLIERS = {
            2: 1.53,
            3: 1.27,
            4: 1.00,
            5: 0.85,
            6: 0.73,
            7: 0.63,
            8: 0.56,
            9: 0.50,
            10: 0.45
        };

        function applyPlayerCountMultiplier(baseValue, playerCount) {
            const multiplier = PLAYER_MULTIPLIERS[playerCount] || 1.00;
            return Math.max(1, Math.round(baseValue * multiplier));
        }

        function updateAdjustedValues() {
            const playerCount = parseInt(document.getElementById('player-count-preview').value);
            const multiplier = PLAYER_MULTIPLIERS[playerCount] || 1.00;
            const multiplierPercent = Math.round(multiplier * 100);

            // Mettre à jour l'affichage du multiplicateur
            const multiplierDisplay = document.getElementById('multiplier-display');
            multiplierDisplay.textContent = `Multiplicateur : ${multiplierPercent}%`;

            // Changer la couleur selon le multiplicateur
            if (multiplier > 1.0) {
                multiplierDisplay.style.borderColor = '#28a745';
                multiplierDisplay.style.color = '#28a745';
            } else if (multiplier < 1.0) {
                multiplierDisplay.style.borderColor = '#dc3545';
                multiplierDisplay.style.color = '#dc3545';
            } else {
                multiplierDisplay.style.borderColor = '#ffa000';
                multiplierDisplay.style.color = '#ffa000';
            }

            // Mettre à jour toutes les valeurs ajustées
            const baseInputs = document.querySelectorAll('.base-value');
            baseInputs.forEach(input => {
                const baseValue = parseInt(input.value) || 0;
                if (baseValue > 0) {
                    const adjustedValue = applyPlayerCountMultiplier(baseValue, playerCount);
                    const adjustedSpan = input.parentElement.querySelector('.adjusted-value');
                    if (adjustedSpan) {
                        adjustedSpan.textContent = adjustedValue;

                        // Mettre en évidence si différent de la base
                        const displayDiv = input.parentElement.querySelector('.adjusted-value-display');
                        if (displayDiv) {
                            if (adjustedValue !== baseValue) {
                                displayDiv.style.display = 'block';
                            } else {
                                displayDiv.style.display = 'none';
                            }
                        }
                    }
                }
            });
        }

        // Mettre à jour quand on change le nombre de joueurs
        document.getElementById('player-count-preview').addEventListener('change', updateAdjustedValues);

        // Mettre à jour quand on modifie une valeur de base
        document.querySelectorAll('.base-value').forEach(input => {
            input.addEventListener('input', updateAdjustedValues);
        });

        // Calculer le total des poids
        function updateWeightTotal() {
            const weightInputs = document.querySelectorAll('.weight-input');
            let total = 0;
            weightInputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });

            const totalSpan = document.getElementById('weight-total');
            const warningSpan = document.getElementById('weight-warning');

            totalSpan.textContent = total;

            if (total === 100) {
                totalSpan.style.color = '#28a745';
                warningSpan.style.display = 'none';
            } else {
                totalSpan.style.color = '#dc3545';
                warningSpan.style.display = 'inline';
            }
        }

        // Mettre à jour quand on modifie un poids
        document.querySelectorAll('.weight-input').forEach(input => {
            input.addEventListener('input', updateWeightTotal);
        });

        // Initialiser au chargement
        document.addEventListener('DOMContentLoaded', function() {
            updateAdjustedValues();
            updateWeightTotal();
        });

        function applyAllRecommendations() {
            const inputs = document.querySelectorAll('input[data-recommended]');
            inputs.forEach(input => {
                const recommended = input.getAttribute('data-recommended');
                if (recommended && recommended !== '') {
                    input.value = recommended;
                    input.setAttribute('data-base-value', recommended);
                }
            });
            alert('✅ Toutes les recommandations ont été appliquées ! N\'oubliez pas de sauvegarder.');

            // Recalculer les valeurs ajustées
            updateAdjustedValues();
        }

        function testObjectiveGeneration() {
            const gameSetId = <?php echo $selected_game_set; ?>;
            const difficulty = '<?php echo htmlspecialchars($difficulty); ?>';
            const testButton = event.target;

            testButton.disabled = true;
            testButton.textContent = '🔄 Génération en cours...';

            // Simuler la génération d'objectifs pour 2, 3 et 4 joueurs
            fetch('../api/test-objective.php?action=generate_test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    game_set_id: gameSetId,
                    difficulty: difficulty,
                    player_counts: [2, 3, 4]
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayTestResults(data.data);
                } else {
                    alert('❌ Erreur : ' + (data.message || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                alert('❌ Erreur de connexion : ' + error.message);
            })
            .finally(() => {
                testButton.disabled = false;
                testButton.textContent = '🧪 Tester la génération d\'objectifs';
            });
        }

        function displayTestResults(results) {
            const container = document.getElementById('test-results');

            let html = '<div style="background:#f8f9fa; padding:25px; border-radius:12px; border:2px solid #28a745;">';
            html += '<h3 style="margin-top:0; color:#28a745;">🎯 Résultats du test de génération</h3>';
            html += `<p style="color:#666; margin-bottom:20px;">Nombre de types généré aléatoirement pour chaque joueur selon les poids configurés</p>`;

            html += '<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:20px;">';

            // Pour chaque nombre de joueurs (2, 3, 4)
            results.player_results.forEach(playerResult => {
                const playerCount = playerResult.player_count;
                const objectives = playerResult.objectives;
                const pictos = results.pictos;
                const multiplier = PLAYER_MULTIPLIERS[playerCount] || 1.00;
                const multiplierPercent = Math.round(multiplier * 100);

                // Badge de couleur selon le multiplicateur
                let badgeColor = '#ffa000';
                if (multiplier > 1.0) badgeColor = '#28a745';
                else if (multiplier < 1.0) badgeColor = '#dc3545';

                html += '<div style="background:white; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
                html += `<h4 style="margin-top:0; color:#333; display:flex; align-items:center; justify-content:space-between;">`;
                html += `<span>${playerCount} joueurs</span>`;
                html += `<span style="background:${badgeColor}; color:white; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600;">${multiplierPercent}%</span>`;
                html += `</h4>`;
                html += '<p style="font-size:12px; color:#666; margin:5px 0 15px;">~' + Math.round(36 / playerCount) + ' cartes par joueur</p>';

                // Afficher chaque objectif de joueur
                objectives.forEach((objective, index) => {
                    const typesInObjective = Object.keys(objective).length;

                    // Calculer le total de symboles pour cet objectif
                    const totalSymbols = Object.values(objective).reduce((sum, qty) => sum + qty, 0);

                    html += '<div style="margin-bottom:12px; padding:12px; background:#f8f9fa; border-radius:6px;">';
                    html += `<div style="font-size:11px; font-weight:600; color:#666; margin-bottom:6px; display:flex; align-items:center; gap:8px;">`;
                    html += `<span>JOUEUR ${index + 1} <span style="color:#667eea;">(${typesInObjective} type${typesInObjective > 1 ? 's' : ''})</span></span>`;
                    html += `<span style="background:#28a745; color:white; padding:3px 8px; border-radius:12px; font-size:10px;">TOTAL: ${totalSymbols}</span>`;
                    html += `</div>`;
                    html += '<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">';

                    // Afficher chaque type dans l'objectif (pictos en ligne)
                    for (const [typeId, quantity] of Object.entries(objective)) {
                        const picto = pictos[typeId];
                        if (!picto) continue;

                        html += '<div style="display:flex; align-items:center; gap:4px; padding:6px 10px; background:white; border-radius:6px; border:2px solid #667eea;">';

                        // Picto (image ou emoji)
                        if (picto.type === 'image') {
                            html += `<img src="${picto.value}" alt="${picto.name}" style="width:24px; height:24px; object-fit:contain;" title="${picto.name}">`;
                        } else {
                            html += `<span style="font-size:24px;" title="${picto.name}">${picto.value}</span>`;
                        }

                        html += `<span style="font-size:16px; font-weight:700; color:#667eea;">${quantity}</span>`;
                        html += '</div>';
                    }

                    html += '</div>';
                    html += '</div>';
                });

                html += '</div>';
            });

            html += '</div>';
            html += '</div>';

            container.innerHTML = html;
            container.style.display = 'block';

            // Scroll vers les résultats
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        </script>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
