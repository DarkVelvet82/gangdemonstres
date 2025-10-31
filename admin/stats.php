<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

// Récupérer les statistiques globales
$total_games = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "games")->fetchColumn();
$active_games = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "games WHERE status = 'active'")->fetchColumn();
$ended_games = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "games WHERE status = 'ended'")->fetchColumn();
$total_players = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "players WHERE used = 1")->fetchColumn();

// Statistiques des scores (si la table existe)
$total_scores = 0;
$total_winners = 0;
$unique_players = 0;

try {
    $total_scores = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "scores")->fetchColumn();
    $total_winners = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "scores WHERE is_winner = 1")->fetchColumn();
    $unique_players = (int)$pdo->query("SELECT COUNT(DISTINCT player_name) FROM " . DB_PREFIX . "scores")->fetchColumn();
} catch (PDOException $e) {
    // Table scores n'existe peut-être pas encore
}

// Récupérer les parties récentes
$stmt = $pdo->query("
    SELECT g.*, gs.name as game_set_name,
           (SELECT COUNT(*) FROM " . DB_PREFIX . "players WHERE game_id = g.id AND used = 1) as players_joined
    FROM " . DB_PREFIX . "games g
    LEFT JOIN " . DB_PREFIX . "game_sets gs ON g.game_set_id = gs.id
    ORDER BY g.created_at DESC
    LIMIT 20
");
$recent_games = $stmt->fetchAll();

// Statistiques par difficulté
$stmt = $pdo->query("
    SELECT difficulty, COUNT(*) as count
    FROM " . DB_PREFIX . "games
    GROUP BY difficulty
    ORDER BY count DESC
");
$difficulty_stats = $stmt->fetchAll();

// Statistiques par jeu
$stmt = $pdo->query("
    SELECT gs.name, COUNT(g.id) as count
    FROM " . DB_PREFIX . "game_sets gs
    LEFT JOIN " . DB_PREFIX . "games g ON g.game_set_id = gs.id
    GROUP BY gs.id, gs.name
    ORDER BY count DESC
");
$game_stats = $stmt->fetchAll();

// Récupérer le classement (si table scores existe)
$rankings = [];
try {
    $stmt = $pdo->query("
        SELECT
            player_name,
            COUNT(*) as total_games,
            SUM(is_winner) as total_wins,
            ROUND((SUM(is_winner) / COUNT(*)) * 100, 1) as win_percentage,
            MAX(created_at) as last_game
        FROM " . DB_PREFIX . "scores
        GROUP BY player_name
        ORDER BY total_wins DESC, win_percentage DESC, total_games DESC
        LIMIT 10
    ");
    $rankings = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table scores n'existe peut-être pas encore
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - Administration</title>
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
        .stats-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,.1); text-align:center; }
        .stat-card .icon { font-size:1.1em; color:#764ba2; margin-bottom:8px; font-weight:700; letter-spacing:.5px; }
        .stat-card .number { font-size:2.5em; font-weight:700; color:#667eea; margin:10px 0; }
        .stat-card .label { color:#666; font-size:1.1em; }
        .data-table { width:100%; border-collapse:collapse; margin-top:15px; }
        .data-table thead { background:#f8f9fa; }
        .data-table th { text-align:left; padding:12px; font-weight:600; border-bottom:2px solid #dee2e6; }
        .data-table td { padding:12px; border-bottom:1px solid #dee2e6; }
        .data-table tr:hover { background:#f8f9fa; }
        .logout-btn { padding:10px 20px; background:#dc3545; color:#fff; border:none; border-radius:8px; text-decoration:none; font-weight:600; }
        .back-btn { padding:10px 20px; background:#6c757d; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; margin-right:10px; }
        .badge { padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; color:#fff; display:inline-block; }
        .badge.active { background:#28a745; }
        .badge.ended { background:#6c757d; }
        .badge.easy { background:#28a745; }
        .badge.normal { background:#ffc107; color:#333; }
        .badge.hard { background:#dc3545; }
        .chart-bar { background:#667eea; height:30px; border-radius:4px; display:flex; align-items:center; padding:0 10px; color:#fff; font-weight:600; margin:5px 0; min-width:50px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>Statistiques</h1>
                <p style="margin:5px 0 0; color:#666;">Vue d'ensemble de l'activité</p>
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
            <a href="difficulty.php">Difficultés</a>
            <a href="stats.php" class="active">Statistiques</a>
        </div>

        <!-- Statistiques globales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">Parties</div>
                <div class="number"><?php echo $total_games; ?></div>
                <div class="label">Total de parties</div>
            </div>

            <div class="stat-card">
                <div class="icon">Actives</div>
                <div class="number"><?php echo $active_games; ?></div>
                <div class="label">Parties actives</div>
            </div>

            <div class="stat-card">
                <div class="icon">Terminées</div>
                <div class="number"><?php echo $ended_games; ?></div>
                <div class="label">Parties terminées</div>
            </div>

            <div class="stat-card">
                <div class="icon">Joueurs</div>
                <div class="number"><?php echo $total_players; ?></div>
                <div class="label">Joueurs connectés</div>
            </div>

            <?php if ($total_scores > 0): ?>
            <div class="stat-card">
                <div class="icon">Scores</div>
                <div class="number"><?php echo $total_scores; ?></div>
                <div class="label">Scores enregistrés</div>
            </div>

            <div class="stat-card">
                <div class="icon">Victoires</div>
                <div class="number"><?php echo $total_winners; ?></div>
                <div class="label">Victoires totales</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Statistiques par difficulté -->
        <div class="card">
            <h2>Répartition par difficulté</h2>
            <?php if (empty($difficulty_stats)): ?>
                <p style="color:#666;">Aucune donnée disponible.</p>
            <?php else: ?>
                <?php
                $max_count = max(array_column($difficulty_stats, 'count'));
                $difficulty_labels = [
                    'easy' => ['label' => '🟢 Facile', 'class' => 'easy'],
                    'normal' => ['label' => '🟡 Normal', 'class' => 'normal'],
                    'hard' => ['label' => '🔴 Difficile', 'class' => 'hard']
                ];
                ?>
                <?php foreach ($difficulty_stats as $stat): ?>
                    <?php
                    $width = ($stat['count'] / $max_count) * 100;
                    $info = $difficulty_labels[$stat['difficulty']] ?? ['label' => $stat['difficulty'], 'class' => ''];
                    ?>
                    <div style="margin-bottom:15px;">
                        <div style="margin-bottom:5px; font-weight:600;"><?php echo $info['label']; ?></div>
                        <div class="chart-bar" style="width:<?php echo $width; ?>%;">
                            <?php echo $stat['count']; ?> partie<?php echo $stat['count'] > 1 ? 's' : ''; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Statistiques par jeu -->
        <div class="card">
            <h2>Répartition par jeu</h2>
            <?php if (empty($game_stats)): ?>
                <p style="color:#666;">Aucune donnée disponible.</p>
            <?php else: ?>
                <?php $max_count = max(array_column($game_stats, 'count')); ?>
                <?php foreach ($game_stats as $stat): ?>
                    <?php $width = $stat['count'] > 0 ? ($stat['count'] / $max_count) * 100 : 5; ?>
                    <div style="margin-bottom:15px;">
                        <div style="margin-bottom:5px; font-weight:600;"><?php echo htmlspecialchars($stat['name']); ?></div>
                        <div class="chart-bar" style="width:<?php echo $width; ?>%; background:#764ba2;">
                            <?php echo $stat['count']; ?> partie<?php echo $stat['count'] > 1 ? 's' : ''; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($rankings)): ?>
        <!-- Classement des joueurs -->
        <div class="card">
            <h2>🏆 Classement des joueurs</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Joueur</th>
                        <th style="text-align:center;">Victoires</th>
                        <th style="text-align:center;">Parties</th>
                        <th style="text-align:center;">% Victoires</th>
                        <th>Dernière partie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankings as $index => $player):
                        $rank = $index + 1;
                        $rank_emoji = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : $rank));
                    ?>
                    <tr>
                        <td style="text-align:center; font-size:20px;"><?php echo $rank_emoji; ?></td>
                        <td><strong><?php echo htmlspecialchars($player['player_name']); ?></strong></td>
                        <td style="text-align:center;"><?php echo $player['total_wins']; ?></td>
                        <td style="text-align:center;"><?php echo $player['total_games']; ?></td>
                        <td style="text-align:center;">
                            <strong><?php echo $player['win_percentage']; ?>%</strong>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($player['last_game'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Parties récentes -->
        <div class="card">
            <h2>Parties récentes</h2>
            <?php if (empty($recent_games)): ?>
                <p style="color:#666;">Aucune partie trouvée.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date création</th>
                            <th>Jeu</th>
                            <th>Difficulté</th>
                            <th style="text-align:center;">Joueurs</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_games as $game):
                            $difficulty_labels = [
                                'easy' => '🟢 Facile',
                                'normal' => '🟡 Normal',
                                'hard' => '🔴 Difficile'
                            ];
                            $difficulty_label = $difficulty_labels[$game['difficulty']] ?? $game['difficulty'];
                        ?>
                        <tr>
                            <td><code>#<?php echo $game['id']; ?></code></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($game['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($game['game_set_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $difficulty_label; ?></td>
                            <td style="text-align:center;"><?php echo $game['player_count']; ?> / <?php echo $game['players_joined']; ?></td>
                            <td>
                                <?php if ($game['status'] === 'active'): ?>
                                    <span class="badge active">Active</span>
                                <?php else: ?>
                                    <span class="badge ended">Terminée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
