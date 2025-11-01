<?php
/**
 * Test du multiplicateur de joueurs
 * Démontre comment les objectifs sont ajustés selon le nombre de joueurs
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/algorithm-v2.php';

$page_title = "Test - Multiplicateur de Joueurs";
$page_description = "Visualisation de l'ajustement des objectifs selon le nombre de joueurs";

// Objectif de base (configuré pour 4 joueurs)
$base_objective = [
    1 => 7,  // Type 1: 7 monstres (ex: Garoux)
    2 => 8   // Type 2: 8 monstres (ex: Faucheuse)
];

// Calculer pour différents nombres de joueurs
$player_counts = [2, 3, 4, 5, 6];
$results = [];

foreach ($player_counts as $count) {
    $adjusted = apply_player_count_multiplier($base_objective, $count);
    $results[$count] = $adjusted;
}

// Récupérer les noms des types pour l'affichage
$type_names = [];
$stmt = $pdo->query("SELECT id, name FROM " . DB_PREFIX . "types ORDER BY display_order ASC LIMIT 2");
$types = $stmt->fetchAll();
foreach ($types as $type) {
    $type_names[$type['id']] = $type['name'];
}

$extra_styles = '<style>
    .test-table { width:100%; border-collapse:collapse; margin-top:20px; }
    .test-table th, .test-table td { padding:15px; text-align:center; border:1px solid #dee2e6; }
    .test-table th { background:#f8f9fa; font-weight:600; }
    .test-table th:first-child { text-align:left; }
    .test-table td:first-child { text-align:left; font-weight:600; }
    .highlight-base { background:#e3f2fd; }
    .multiplier-badge { display:inline-block; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600; margin-left:8px; }
    .badge-easy { background:#d4edda; color:#155724; }
    .badge-medium { background:#fff3cd; color:#856404; }
    .badge-hard { background:#f8d7da; color:#721c24; }
    .example-box { background:#f8f9fa; padding:20px; border-radius:8px; margin-top:20px; border-left:4px solid #667eea; }
</style>';

require_once __DIR__ . '/includes/admin-layout.php';
?>

<div class="card">
    <h2>🎯 Objectif de base (configuré pour 4 joueurs)</h2>
    <div style="background:#f8f9fa; padding:20px; border-radius:8px; margin-top:15px;">
        <p style="font-size:18px; margin:0;">
            <?php foreach ($base_objective as $type_id => $qty): ?>
                <strong><?php echo $qty; ?> <?php echo htmlspecialchars($type_names[$type_id] ?? "Type $type_id"); ?></strong>
                <?php if ($type_id !== array_key_last($base_objective)): ?>
                    +
                <?php endif; ?>
            <?php endforeach; ?>
        </p>
    </div>
</div>

<div class="card">
    <h2>📊 Ajustement selon le nombre de joueurs</h2>
    <p style="color:#666; margin-bottom:20px;">
        Plus il y a de joueurs, plus c'est difficile (moins de cartes par joueur).
        Les objectifs sont donc AUGMENTÉS pour moins de joueurs afin de maintenir l'équilibre.
    </p>

    <table class="test-table">
        <thead>
            <tr>
                <th>Nombre de joueurs</th>
                <th>Cartes par joueur<br><small>(sur 36 cartes)</small></th>
                <th>Multiplicateur</th>
                <?php foreach ($base_objective as $type_id => $qty): ?>
                    <th><?php echo htmlspecialchars($type_names[$type_id] ?? "Type $type_id"); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($player_counts as $count):
                $cards_per_player = round(36 / $count, 1);
                $multiplier_display = $count == 2 ? '153%' : ($count == 3 ? '127%' : ($count == 4 ? '100%' : ($count == 5 ? '85%' : '73%')));
                $badge_class = $count <= 3 ? 'badge-easy' : ($count == 4 ? 'badge-medium' : 'badge-hard');
                $is_base = ($count == 4);
            ?>
            <tr <?php echo $is_base ? 'class="highlight-base"' : ''; ?>>
                <td>
                    <?php echo $count; ?> joueurs
                    <?php if ($is_base): ?>
                        <span class="multiplier-badge badge-medium">📍 Base</span>
                    <?php endif; ?>
                </td>
                <td>~<?php echo $cards_per_player; ?> cartes</td>
                <td>
                    <span class="multiplier-badge <?php echo $badge_class; ?>">
                        <?php echo $multiplier_display; ?>
                    </span>
                </td>
                <?php foreach ($base_objective as $type_id => $base_qty):
                    $adjusted_qty = $results[$count][$type_id];
                    $diff = $adjusted_qty - $base_qty;
                    $diff_display = $diff > 0 ? "+$diff" : ($diff < 0 ? "$diff" : "0");
                ?>
                <td>
                    <strong style="font-size:18px; color:#667eea;"><?php echo $adjusted_qty; ?></strong>
                    <?php if ($diff != 0): ?>
                        <small style="display:block; color:#666; margin-top:4px;">
                            (<?php echo $diff_display; ?>)
                        </small>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>💡 Exemples concrets</h2>

    <div class="example-box">
        <h3 style="margin-top:0; color:#667eea;">Exemple 1 : Partie à 2 joueurs (le plus facile)</h3>
        <p>Avec 36 cartes divisées entre 2 joueurs, chaque joueur a ~18 cartes.</p>
        <p><strong>Objectif généré :</strong></p>
        <ul style="margin-left:20px; line-height:1.8;">
            <li><?php echo $results[2][1]; ?> <?php echo $type_names[1]; ?> (au lieu de 7 pour 4 joueurs)</li>
            <li><?php echo $results[2][2]; ?> <?php echo $type_names[2]; ?> (au lieu de 8 pour 4 joueurs)</li>
        </ul>
        <p style="color:#666; font-style:italic;">
            ➜ Les quantités sont augmentées car chaque joueur voit plus de cartes, donc plus de symboles.
        </p>
    </div>

    <div class="example-box" style="margin-top:15px;">
        <h3 style="margin-top:0; color:#667eea;">Exemple 2 : Partie à 4 joueurs (référence)</h3>
        <p>Avec 36 cartes divisées entre 4 joueurs, chaque joueur a ~9 cartes.</p>
        <p><strong>Objectif généré :</strong></p>
        <ul style="margin-left:20px; line-height:1.8;">
            <li><?php echo $results[4][1]; ?> <?php echo $type_names[1]; ?> (valeur de base configurée)</li>
            <li><?php echo $results[4][2]; ?> <?php echo $type_names[2]; ?> (valeur de base configurée)</li>
        </ul>
        <p style="color:#666; font-style:italic;">
            ➜ C'est la configuration de référence, utilisée telle quelle.
        </p>
    </div>

    <div class="example-box" style="margin-top:15px;">
        <h3 style="margin-top:0; color:#667eea;">Exemple 3 : Partie à 6 joueurs (plus difficile)</h3>
        <p>Avec 36 cartes divisées entre 6 joueurs, chaque joueur a ~6 cartes.</p>
        <p><strong>Objectif généré :</strong></p>
        <ul style="margin-left:20px; line-height:1.8;">
            <li><?php echo $results[6][1]; ?> <?php echo $type_names[1]; ?> (réduit de 7 pour 4 joueurs)</li>
            <li><?php echo $results[6][2]; ?> <?php echo $type_names[2]; ?> (réduit de 8 pour 4 joueurs)</li>
        </ul>
        <p style="color:#666; font-style:italic;">
            ➜ Les quantités sont réduites car chaque joueur voit moins de cartes, donc moins de symboles.
        </p>
    </div>
</div>

<div class="card">
    <h2>🔧 Implémentation technique</h2>
    <p>La fonction <code>apply_player_count_multiplier()</code> dans <code>algorithm-v2.php</code> applique automatiquement ces ajustements lors de la génération des objectifs.</p>
    <p style="margin-top:10px;">Les multiplicateurs sont basés sur le ratio théorique de cartes par joueur dans un deck de 36 cartes.</p>
</div>

<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
