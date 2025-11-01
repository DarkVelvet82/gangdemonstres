<?php
/**
 * Layout d'administration avec menu latéral
 * Usage: require_once __DIR__ . '/includes/admin-layout.php';
 */

// Vérifier la session admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Déterminer la page active pour le highlight
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Administration'; ?> - Gang de Monstres</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
            background: #f7f8fa;
            display: flex;
            min-height: 100vh;
        }

        /* Menu latéral */
        .admin-sidebar {
            width: 260px;
            background: #1a1a1a;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.3);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h1 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 12px;
            opacity: 0.7;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            display: block;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-item.active {
            background: rgba(255,255,255,0.15);
            border-left-color: #667eea;
            color: white;
            font-weight: 600;
        }

        .nav-item .icon {
            display: inline-block;
            width: 20px;
            margin-right: 10px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px 20px;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-footer a {
            display: block;
            padding: 8px 12px;
            margin-bottom: 5px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            text-align: center;
            transition: all 0.2s;
        }

        .sidebar-footer .btn-account {
            background: rgba(23, 162, 184, 0.3);
        }

        .sidebar-footer .btn-account:hover {
            background: rgba(23, 162, 184, 0.5);
        }

        .sidebar-footer .btn-public {
            background: rgba(40, 167, 69, 0.3);
        }

        .sidebar-footer .btn-public:hover {
            background: rgba(40, 167, 69, 0.5);
        }

        .sidebar-footer .btn-logout {
            background: rgba(220, 53, 69, 0.3);
        }

        .sidebar-footer .btn-logout:hover {
            background: rgba(220, 53, 69, 0.5);
        }

        /* Contenu principal */
        .admin-main {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
            width: calc(100% - 260px);
        }

        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }

        .page-header p {
            color: #666;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                position: relative;
            }

            .admin-main {
                margin-left: 0;
                width: 100%;
            }

            .sidebar-footer {
                position: relative;
            }
        }
    </style>
    <?php if (isset($extra_styles)) echo $extra_styles; ?>
</head>
<body>
    <!-- Menu latéral -->
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <h1>Gang de Monstres</h1>
            <p>Administration</p>
        </div>

        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <span class="icon">📊</span> Dashboard
            </a>
            <a href="types.php" class="nav-item <?php echo $current_page === 'types.php' ? 'active' : ''; ?>">
                <span class="icon">🏷️</span> Types d'objectifs
            </a>
            <a href="games.php" class="nav-item <?php echo $current_page === 'games.php' ? 'active' : ''; ?>">
                <span class="icon">🎮</span> Jeux & Extensions
            </a>
            <a href="cards.php" class="nav-item <?php echo $current_page === 'cards.php' ? 'active' : ''; ?>">
                <span class="icon">🃏</span> Cartes
            </a>
            <a href="difficulty.php" class="nav-item <?php echo $current_page === 'difficulty.php' ? 'active' : ''; ?>">
                <span class="icon">⚡</span> Difficultés
            </a>
            <a href="analyze-distribution.php" class="nav-item <?php echo $current_page === 'analyze-distribution.php' ? 'active' : ''; ?>">
                <span class="icon">📈</span> Analyse Distribution
            </a>
            <a href="stats.php" class="nav-item <?php echo $current_page === 'stats.php' ? 'active' : ''; ?>">
                <span class="icon">📉</span> Statistiques
            </a>
            <a href="test-player-multiplier.php" class="nav-item <?php echo $current_page === 'test-player-multiplier.php' ? 'active' : ''; ?>">
                <span class="icon">🧪</span> Test Multiplicateur
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="account.php" class="btn-account">👤 Mon compte</a>
            <a href="../public/" class="btn-public">🌐 Voir le site</a>
            <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
        </div>
    </aside>

    <!-- Contenu principal -->
    <main class="admin-main">
        <?php if (isset($page_title)): ?>
        <div class="page-header">
            <div>
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
                <?php if (isset($page_description)): ?>
                    <p><?php echo htmlspecialchars($page_description); ?></p>
                <?php endif; ?>
            </div>
            <?php if (isset($page_header_button)): ?>
                <div>
                    <?php echo $page_header_button; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Le contenu de la page sera inséré ici -->
