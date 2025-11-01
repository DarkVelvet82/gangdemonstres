<?php
/**
 * Migration: Ajouter la colonne generation_weight à difficulty_config
 */

session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

try {
    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "difficulty_config LIKE 'generation_weight'");
    $column_exists = $stmt->fetch();

    if (!$column_exists) {
        // Ajouter la colonne
        $pdo->exec("ALTER TABLE " . DB_PREFIX . "difficulty_config ADD COLUMN generation_weight INT DEFAULT 0 AFTER max_quantity");

        // Définir les poids par défaut
        $default_weights = [
            1 => 15,  // 15% pour 1 type
            2 => 25,  // 25% pour 2 types
            3 => 35,  // 35% pour 3 types
            4 => 20,  // 20% pour 4 types
            5 => 5    // 5% pour 5 types
        ];

        // Mettre à jour les poids existants
        foreach ($default_weights as $types_count => $weight) {
            $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "difficulty_config SET generation_weight = ? WHERE types_count = ?");
            $stmt->execute([$weight, $types_count]);
        }

        $message = '✅ Colonne generation_weight ajoutée avec succès ! Les poids par défaut ont été appliqués.';
        $message_type = 'success';
    } else {
        $message = 'ℹ️ La colonne generation_weight existe déjà.';
        $message_type = 'success';
    }
} catch (PDOException $e) {
    $message = '❌ Erreur : ' . $e->getMessage();
    $message_type = 'error';
}

$page_title = "Migration - Generation Weight";
$page_description = "Ajout de la colonne pour les poids de génération";

require_once __DIR__ . '/includes/admin-layout.php';
?>

<div class="card">
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>" style="padding:20px; border-radius:8px; margin-bottom:20px; <?php echo $message_type === 'success' ? 'background:#d4edda; color:#155724; border:1px solid #c3e6cb;' : 'background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;'; ?>">
            <strong><?php echo $message; ?></strong>
        </div>
    <?php endif; ?>

    <h2>Migration de la base de données</h2>
    <p>Cette page ajoute la colonne <code>generation_weight</code> à la table <code>difficulty_config</code>.</p>

    <?php if ($message_type === 'success'): ?>
        <p style="margin-top:20px;">
            <a href="difficulty.php" class="submit-button" style="display:inline-block; text-decoration:none;">
                → Aller à la configuration des difficultés
            </a>
        </p>
    <?php else: ?>
        <p style="margin-top:20px;">
            <a href="add-generation-weight.php" class="submit-button" style="display:inline-block; text-decoration:none;">
                🔄 Réessayer la migration
            </a>
        </p>
    <?php endif; ?>
</div>

<style>
    .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,.1); margin-bottom:25px; }
    .card h2 { margin-top:0; color:#333; }
    .submit-button { background:#1a1a1a; color:#fff; padding:12px 30px; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:15px; transition:all .3s; margin-top:15px; }
    .submit-button:hover { background:#2a2a2a; }
    code { background:#f4f4f4; padding:2px 6px; border-radius:3px; font-family:monospace; }
</style>

<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
