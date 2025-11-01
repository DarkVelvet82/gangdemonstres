<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/algorithm-v2.php';

$page_title = "Analyse de Distribution";
$page_description = "Visualisation des données pour l'algorithme v2";

// Récupérer tous les jeux
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "game_sets ORDER BY display_order ASC");
$game_sets = $stmt->fetchAll();

// Récupérer tous les types
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "types ORDER BY display_order ASC");
$types = $stmt->fetchAll();

// Créer un index des types par ID
$types_index = [];
foreach ($types as $type) {
    $types_index[$type['id']] = $type;
}

// Analyse par défaut : tous les jeux
$selected_game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;

// Analyser la distribution
$distribution = analyze_type_distribution($selected_game_id === 0 ? null : $selected_game_id);
$rarity_scores = calculate_rarity($distribution);

// Récupérer le nom du jeu
if ($selected_game_id === 0) {
    $game_name = "Tous les jeux";
} else {
    $stmt = $pdo->prepare("SELECT name FROM " . DB_PREFIX . "game_sets WHERE id = ?");
    $stmt->execute([$selected_game_id]);
    $game_name = $stmt->fetchColumn();
}

$extra_styles = '
<style>
    .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,.1); margin-bottom:25px; }
    .card h2 { margin-top:0; color:#333; }
    select { padding:10px 15px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
    table { width:100%; border-collapse: collapse; margin-top:20px; }
    th, td { padding:12px; text-align:left; border-bottom:1px solid #eee; }
    th { background:#f8f9fa; font-weight:600; color:#333; }
    .type-icon { width:30px; height:30px; object-fit:contain; margin-right:10px; vertical-align:middle; }
    .progress-bar { background:#e9ecef; border-radius:10px; height:24px; overflow:hidden; position:relative; }
    .progress-fill { background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height:100%; display:flex; align-items:center; justify-content:center; color:white; font-size:12px; font-weight:600; transition: width 0.3s; }
    .stat-badge { display:inline-block; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600; margin:0 3px; }
    .badge-rarity-low { background:#d4edda; color:#155724; }
    .badge-rarity-medium { background:#fff3cd; color:#856404; }
    .badge-rarity-high { background:#f8d7da; color:#721c24; }
    .summary-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:30px; }
    .summary-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; padding:20px; border-radius:12px; text-align:center; }
    .summary-card .number { font-size:32px; font-weight:700; margin:10px 0; }
    .summary-card .label { font-size:14px; opacity:0.9; }
</style>
';

require_once __DIR__ . '/includes/admin-layout.php';
?>

<!-- Sélecteur de jeu -->
<div class="card">
    <h2>Sélectionner un jeu</h2>
    <form method="get">
        <select name="game_id" onchange="this.form.submit()">
            <option value="0" <?php echo $selected_game_id === 0 ? 'selected' : ''; ?>>
                🎲 Tous les jeux (distribution globale)
            </option>
            <?php foreach ($game_sets as $game): ?>
                <option value="<?php echo $game['id']; ?>" <?php echo $game['id'] == $selected_game_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($game['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (empty($distribution)): ?>
    <div class="card">
        <h2>Aucune donnée</h2>
        <p style="color:#dc3545; font-weight:600;">
            Aucune carte monstre trouvée pour "<?php echo htmlspecialchars($game_name); ?>".
        </p>
        <p style="color:#666;">
            Ajoutez des cartes via <a href="cards.php" style="color:#667eea;">la page Cartes</a> pour voir l'analyse.
        </p>
    </div>
<?php else: ?>

<!-- Résumé global -->
<div class="summary-grid">
    <div class="summary-card">
        <div class="label">Types disponibles</div>
        <div class="number"><?php echo count($distribution); ?></div>
    </div>
    <div class="summary-card">
        <div class="label">Total de symboles</div>
        <div class="number"><?php echo array_sum(array_column($distribution, 'total_symbols')); ?></div>
    </div>
    <div class="summary-card">
        <div class="label">Cartes analysées</div>
        <div class="number"><?php echo array_sum(array_column($distribution, 'card_count')); ?></div>
    </div>
</div>

<!-- Tableau de distribution -->
<div class="card">
    <h2>Distribution détaillée pour "<?php echo htmlspecialchars($game_name); ?>"</h2>
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Symboles totaux</th>
                <th>Cartes</th>
                <th>Fréquence</th>
                <th>Max/carte</th>
                <th>Densité moy.</th>
                <th>Rareté</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($distribution as $type_id => $data):
                $type = $types_index[$type_id] ?? null;
                if (!$type) continue;

                $rarity = $rarity_scores[$type_id];
                $rarity_class = $rarity > 0.6 ? 'badge-rarity-high' :
                               ($rarity > 0.3 ? 'badge-rarity-medium' : 'badge-rarity-low');
                $rarity_label = $rarity > 0.6 ? 'Rare' :
                               ($rarity > 0.3 ? 'Moyen' : 'Commun');
            ?>
            <tr>
                <td>
                    <?php if (!empty($type['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($type['image_url']); ?>" alt="" class="type-icon">
                    <?php endif; ?>
                    <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                </td>
                <td>
                    <strong style="font-size:18px; color:#667eea;"><?php echo $data['total_symbols']; ?></strong>
                </td>
                <td>
                    <strong style="font-size:18px;"><?php echo $data['card_count']; ?></strong> cartes
                </td>
                <td>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($data['frequency'] * 100); ?>%;">
                            <?php echo round($data['frequency'] * 100, 1); ?>%
                        </div>
                    </div>
                </td>
                <td>
                    <span class="stat-badge" style="background:#e3f2fd; color:#1565c0;">
                        Max: <?php echo $data['max_on_card']; ?>
                    </span>
                </td>
                <td>
                    <?php echo round($data['avg_density'], 2); ?> symboles/carte
                </td>
                <td>
                    <span class="stat-badge <?php echo $rarity_class; ?>">
                        <?php echo $rarity_label; ?> (<?php echo round($rarity * 100, 1); ?>%)
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Détails par type -->
<div class="card">
    <h2>Détail des cartes par type</h2>
    <?php foreach ($distribution as $type_id => $data):
        $type = $types_index[$type_id] ?? null;
        if (!$type) continue;
    ?>
    <div style="margin-bottom:30px; padding:20px; background:#f8f9fa; border-radius:8px;">
        <h3 style="margin-top:0; display:flex; align-items:center;">
            <?php if (!empty($type['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($type['image_url']); ?>" alt="" class="type-icon">
            <?php endif; ?>
            <?php echo htmlspecialchars($type['name']); ?>
            <span style="margin-left:auto; color:#667eea; font-size:16px;">
                <?php echo $data['card_count']; ?> cartes total (<?php echo count($data['cards_list']); ?> uniques)
            </span>
        </h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:10px;">
            <?php foreach ($data['cards_list'] as $card_info): ?>
                <div style="padding:10px; background:white; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong><?php echo htmlspecialchars($card_info['card_name']); ?></strong>
                        <div style="font-size:11px; color:#666; margin-top:2px;">
                            <?php echo $card_info['card_quantity']; ?> exemplaire<?php echo $card_info['card_quantity'] > 1 ? 's' : ''; ?> ×
                            <?php echo $card_info['symbols_per_card']; ?> symbole<?php echo $card_info['symbols_per_card'] > 1 ? 's' : ''; ?>
                        </div>
                    </div>
                    <span style="background:#667eea; color:white; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600;">
                        <?php echo $card_info['total_symbols']; ?>× total
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
