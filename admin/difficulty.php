<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

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

            // Sauvegarder la nouvelle configuration
            $difficulties = ['easy', 'normal', 'hard'];

            foreach ($difficulties as $difficulty) {
                for ($types_count = 1; $types_count <= 5; $types_count++) {
                    $min_key = "{$difficulty}_types_{$types_count}_min";
                    $max_key = "{$difficulty}_types_{$types_count}_max";

                    if (isset($_POST[$min_key]) && isset($_POST[$max_key])) {
                        $min_quantity = (int)$_POST[$min_key];
                        $max_quantity = (int)$_POST[$max_key];

                        if ($min_quantity > 0 && $max_quantity >= $min_quantity) {
                            $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "difficulty_config (game_set_id, difficulty, types_count, min_quantity, max_quantity) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$game_set_id, $difficulty, $types_count, $min_quantity, $max_quantity]);
                        }
                    }
                }
            }

            $message = 'Configuration de difficulté sauvegardée !';
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration des Difficultés - Administration</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <style>
        body { background: #f7f8fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif; }
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .admin-header { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; display:flex; justify-content: space-between; align-items:center; }
        .admin-nav { display:flex; gap:15px; margin-bottom:30px; background:white; padding:15px; border-radius:12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-nav a { padding:10px 20px; background:#667eea; color:#fff; text-decoration:none; border-radius:8px; font-weight:600; transition: all .3s; }
        .admin-nav a:hover { background:#5568d3; transform: translateY(-2px); }
        .admin-nav a.active { background:#764ba2; }
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,.1); margin-bottom:25px; }
        .card h2 { margin-top:0; color:#333; }
        .submit-button { background:#667eea; color:#fff; padding:12px 30px; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:15px; transition: all .3s; }
        .submit-button:hover { background:#5568d3; transform: translateY(-2px); }
        .message { padding:15px; border-radius:8px; margin-bottom:20px; }
        .message.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .message.error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .message.warning { background:#fff3cd; color:#856404; border:1px solid #ffeaa7; }
        .logout-btn { padding:10px 20px; background:#dc3545; color:#fff; border:none; border-radius:8px; text-decoration:none; font-weight:600; }
        .back-btn { padding:10px 20px; background:#6c757d; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; margin-right:10px; }
        .difficulty-config-table { width:100%; border-collapse:collapse; margin-top:15px; }
        .difficulty-config-table thead { background:#f8f9fa; }
        .difficulty-config-table th { text-align:center; padding:12px; font-weight:600; border:1px solid #dee2e6; }
        .difficulty-config-table td { text-align:center; padding:12px; border:1px solid #dee2e6; }
        .difficulty-config-table th:first-child, .difficulty-config-table td:first-child { text-align:left; font-weight:600; background:#f8f9fa; }
        .number-input { width:60px; padding:6px; border:1px solid #ddd; border-radius:4px; text-align:center; }
        .difficulty-explanation, .difficulty-notes { background:#f0f6fc; padding:15px; border-radius:8px; margin:15px 0; border-left:4px solid #667eea; }
        .difficulty-notes ul { margin-left:20px; line-height:1.8; }
        select { padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; min-width:250px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>Configuration des Difficultés</h1>
                <p style="margin:5px 0 0; color:#666;">Paramétrage des niveaux de difficulté</p>
            </div>
            <div>
                <a href="index.php" class="back-btn">← Dashboard</a>
                <a href="logout.php" class="logout-btn">Déconnexion</a>
            </div>
        </div>

        <div class="admin-nav">
            <a href="index.php">Dashboard</a>
            <a href="types.php">Types d'objectifs</a>
            <a href="games.php">Jeux & Extensions</a>
            <a href="difficulty.php" class="active">Difficultés</a>
            <a href="stats.php">Statistiques</a>
        </div>

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

                    <div class="difficulty-explanation">
                        <p><strong>Explication :</strong> Configurez les plages de quantités en fonction du nombre de types d'objectifs dans une mission :</p>
                        <ul style="line-height:1.8; margin-left:20px;">
                            <li><strong>1 type</strong> : Mission avec un seul type d'objectif (ex: seulement des zombies)</li>
                            <li><strong>2 types</strong> : Mission avec deux types (ex: zombies + sorcières)</li>
                            <li><strong>3+ types</strong> : Mission avec trois types ou plus</li>
                        </ul>
                    </div>

                    <form method="post">
                        <input type="hidden" name="action" value="save_difficulty_config">
                        <input type="hidden" name="game_set_id" value="<?php echo $selected_game_set; ?>">

                        <table class="difficulty-config-table">
                            <thead>
                                <tr>
                                    <th rowspan="2">Nombre de types dans l'objectif</th>
                                    <th colspan="2">🟢 Facile</th>
                                    <th colspan="2">🟡 Normal</th>
                                    <th colspan="2">🔴 Difficile</th>
                                </tr>
                                <tr>
                                    <th>Min</th>
                                    <th>Max</th>
                                    <th>Min</th>
                                    <th>Max</th>
                                    <th>Min</th>
                                    <th>Max</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($types_count = 1; $types_count <= 5; $types_count++): ?>
                                <tr>
                                    <td><strong><?php echo $types_count; ?> type<?php echo $types_count > 1 ? 's' : ''; ?></strong></td>

                                    <?php foreach (['easy', 'normal', 'hard'] as $difficulty): ?>
                                        <?php
                                        $current = isset($config_matrix[$difficulty][$types_count]) ? $config_matrix[$difficulty][$types_count] : null;
                                        $min_value = $current ? $current['min_quantity'] : '';
                                        $max_value = $current ? $current['max_quantity'] : '';
                                        ?>
                                        <td>
                                            <input type="number"
                                                   name="<?php echo $difficulty; ?>_types_<?php echo $types_count; ?>_min"
                                                   value="<?php echo $min_value; ?>"
                                                   min="1" max="50"
                                                   class="number-input"
                                                   placeholder="Min">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="<?php echo $difficulty; ?>_types_<?php echo $types_count; ?>_max"
                                                   value="<?php echo $max_value; ?>"
                                                   min="1" max="50"
                                                   class="number-input"
                                                   placeholder="Max">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>

                        <div class="difficulty-notes">
                            <h3>💡 Conseils</h3>
                            <ul>
                                <li><strong>Facile :</strong> Quantités faibles, objectifs réalisables rapidement</li>
                                <li><strong>Normal :</strong> Équilibre entre défi et accessibilité</li>
                                <li><strong>Difficile :</strong> Quantités élevées, parties longues et challenging</li>
                                <li><strong>Plus de types = quantités plus faibles</strong> par type pour équilibrer</li>
                                <li>Laissez vide les lignes non utilisées (ex: si vous n'avez jamais d'objectifs à 5 types)</li>
                            </ul>
                        </div>

                        <button type="submit" class="submit-button">Sauvegarder la configuration</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
