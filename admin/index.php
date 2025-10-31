<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// VÃ©rifier l'authentification
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

// RÃ©cupÃ©rer les statistiques
$stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "games");
$total_games = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "games WHERE status = 'active'");
$active_games = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "players WHERE used = 1");
$total_players = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "types");
$total_types = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "game_sets");
$total_game_sets = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gang de Monstres</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <style>
        body {
            background: #f7f8fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .admin-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-nav {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .admin-nav a {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .admin-nav a:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .admin-nav a.active {
            background: #764ba2;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .icon {
            font-size: 3em;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }

        .stat-card .label {
            color: #666;
            font-size: 1.1em;
        }

        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: #c82333;
        }

        .public-link {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-right: 10px;
        }

        .public-link:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>ðŸŽ® Administration - Gang de Monstres</h1>
                <p style="margin: 5px 0 0 0; color: #666;">Tableau de bord</p>
            </div>
            <div>
                <a href="account.php" class="public-link" style="background:#17a2b8">Mon compte</a>
                <a href="../public/" class="public-link">Voir le site</a>
                <a href="logout.php" class="logout-btn">Deconnexion</a>
            </div>
        </div>

        <div class="admin-nav">
            <a href="index.php" class="active">ðŸ“Š Dashboard</a>
            <a href="types.php">ðŸŽ¯ Types d'objectifs</a>
            <a href="games.php">ðŸŽ® Jeux & Extensions</a>
            <a href="difficulty.php">âš™ï¸ DifficultÃ©s</a>
            <a href="stats.php">ðŸ“ˆ Statistiques</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">ðŸŽ¯</div>
                <div class="number"><?php echo $total_games; ?></div>
                <div class="label">Parties crÃ©Ã©es</div>
            </div>

            <div class="stat-card">
                <div class="icon">ðŸ”¥</div>
                <div class="number"><?php echo $active_games; ?></div>
                <div class="label">Parties actives</div>
            </div>

            <div class="stat-card">
                <div class="icon">ðŸ‘¥</div>
                <div class="number"><?php echo $total_players; ?></div>
                <div class="label">Joueurs connectÃ©s</div>
            </div>

            <div class="stat-card">
                <div class="icon">ðŸŽ²</div>
                <div class="number"><?php echo $total_types; ?></div>
                <div class="label">Types d'objectifs</div>
            </div>

            <div class="stat-card">
                <div class="icon">ðŸŽ®</div>
                <div class="number"><?php echo $total_game_sets; ?></div>
                <div class="label">Jeux configurÃ©s</div>
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>ðŸš€ Actions rapides</h2>
            <p>Bienvenue dans le backoffice de Gang de Monstres! Utilisez le menu ci-dessus pour gÃ©rer votre application.</p>

            <ul style="margin-top: 20px; line-height: 2;">
                <li><strong>Types d'objectifs:</strong> GÃ©rer les types de monstres et leurs icÃ´nes</li>
                <li><strong>Jeux & Extensions:</strong> Configurer les jeux de base et les extensions</li>
                <li><strong>DifficultÃ©s:</strong> ParamÃ©trer les niveaux de difficultÃ©</li>
                <li><strong>Statistiques:</strong> Voir les performances et les scores</li>
            </ul>
        </div>
    </div>
</body>
</html>

