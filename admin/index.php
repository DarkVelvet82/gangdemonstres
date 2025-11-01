<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: text/html; charset=UTF-8');

$page_title = "Dashboard";
$page_description = "Vue d'ensemble de l'administration";

$total_games = (int) $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "games")->fetchColumn();
$active_games = (int) $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "games WHERE status = 'active'")->fetchColumn();
$total_players = (int) $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "players WHERE used = 1")->fetchColumn();
$total_types = (int) $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "types")->fetchColumn();
$total_game_sets = (int) $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "game_sets")->fetchColumn();
$total_cards = (int) $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "cards")->fetchColumn();

$extra_styles = '
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
    }
    .stat-card .icon {
        font-size: 1.1em;
        color: #764ba2;
        margin-bottom: 8px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .stat-card .number {
        font-size: 2.5em;
        font-weight: 700;
        color: #667eea;
        margin: 10px 0;
    }
    .stat-card .label {
        color: #666;
        font-size: 1.1em;
    }
</style>
';

require_once __DIR__ . '/includes/admin-layout.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="icon">Statistiques</div>
        <div class="number"><?php echo $total_games; ?></div>
        <div class="label">Parties créées</div>
    </div>

    <div class="stat-card">
        <div class="icon">Actives</div>
        <div class="number"><?php echo $active_games; ?></div>
        <div class="label">Parties actives</div>
    </div>

    <div class="stat-card">
        <div class="icon">Utilisateurs</div>
        <div class="number"><?php echo $total_players; ?></div>
        <div class="label">Joueurs connectés</div>
    </div>

    <div class="stat-card">
        <div class="icon">Types</div>
        <div class="number"><?php echo $total_types; ?></div>
        <div class="label">Types d'objectifs</div>
    </div>

    <div class="stat-card">
        <div class="icon">Jeux</div>
        <div class="number"><?php echo $total_game_sets; ?></div>
        <div class="label">Jeux configurés</div>
    </div>

    <div class="stat-card">
        <div class="icon">Cartes</div>
        <div class="number"><?php echo $total_cards; ?></div>
        <div class="label">Total de cartes</div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
