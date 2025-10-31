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
    if (isset($_POST['action'])) {

        // Ajouter un jeu/extension
        if ($_POST['action'] === 'add_game_set') {
            $name = trim($_POST['game_name']);
            $description = trim($_POST['game_description'] ?? '');
            $is_base = isset($_POST['is_base_game']) ? 1 : 0;

            try {
                // Obtenir le prochain display_order
                $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM " . DB_PREFIX . "game_sets");
                $next_order = $stmt->fetchColumn();

                $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "game_sets (name, description, is_base_game, display_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $description, $is_base, $next_order]);

                $game_set_id = $pdo->lastInsertId();

                // Associer les types sélectionnés
                if (!empty($_POST['selected_types'])) {
                    foreach ($_POST['selected_types'] as $type_id) {
                        $is_limited = isset($_POST["type_limited_$type_id"]) ? 1 : 0;
                        $max_quantity = isset($_POST["type_max_$type_id"]) ? (int)$_POST["type_max_$type_id"] : null;

                        $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "set_types (game_set_id, type_id, is_limited, max_quantity) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$game_set_id, (int)$type_id, $is_limited, $max_quantity]);
                    }
                }

                $message = 'Jeu/Extension ajouté avec succès !';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erreur lors de l\'ajout : ' . $e->getMessage();
                $message_type = 'error';
            }
        }

        // Supprimer un jeu/extension
        if ($_POST['action'] === 'delete_game_set' && isset($_POST['game_set_id'])) {
            $game_set_id = (int)$_POST['game_set_id'];

            try {
                // Supprimer les associations d'abord
                $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "set_types WHERE game_set_id = ?");
                $stmt->execute([$game_set_id]);

                // Puis supprimer le jeu
                $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "game_sets WHERE id = ?");
                $stmt->execute([$game_set_id]);

                $message = 'Jeu/Extension supprimé avec succès !';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erreur lors de la suppression : ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Récupérer tous les jeux
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "game_sets ORDER BY display_order ASC");
$game_sets = $stmt->fetchAll();

// Récupérer tous les types
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "types ORDER BY display_order ASC");
$types = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jeux & Extensions - Administration</title>
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
        .form-table { width:100%; }
        .form-table th { text-align:left; padding:15px 10px 15px 0; width:180px; font-weight:600; vertical-align:top; }
        .form-table td { padding:15px 0; }
        .regular-text { width:100%; max-width:400px; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
        .large-text { width:100%; max-width:600px; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; min-height:80px; }
        .submit-button { background:#667eea; color:#fff; padding:12px 30px; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:15px; transition: all .3s; }
        .submit-button:hover { background:#5568d3; transform: translateY(-2px); }
        .data-table { width:100%; border-collapse:collapse; margin-top:15px; }
        .data-table thead { background:#f8f9fa; }
        .data-table th { text-align:left; padding:12px; font-weight:600; border-bottom:2px solid #dee2e6; }
        .data-table td { padding:12px; border-bottom:1px solid #dee2e6; }
        .data-table tr:hover { background:#f8f9fa; }
        .btn-delete { background:#dc3545; color:#fff; padding:6px 12px; border:none; border-radius:6px; cursor:pointer; font-size:13px; }
        .btn-delete:hover { background:#c82333; }
        .message { padding:15px; border-radius:8px; margin-bottom:20px; }
        .message.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .message.error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .logout-btn { padding:10px 20px; background:#dc3545; color:#fff; border:none; border-radius:8px; text-decoration:none; font-weight:600; }
        .back-btn { padding:10px 20px; background:#6c757d; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; margin-right:10px; }
        .badge { padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; color:#fff; display:inline-block; }
        .badge.base-game { background:#28a745; }
        .badge.extension { background:#007bff; }
        .types-selection { max-height:400px; overflow-y:auto; border:1px solid #ddd; border-radius:6px; padding:10px; }
        .type-option { margin-bottom:15px; padding:15px; border:1px solid #e0e0e0; border-radius:8px; background:#f9f9f9; }
        .type-option:hover { background:#f0f0f0; }
        .type-option label { display:flex; align-items:center; margin-bottom:10px; cursor:pointer; }
        .type-constraints { margin-left:30px; display:none; padding:10px; background:#fff; border-radius:6px; }
        .max-quantity-field { margin-left:20px; display:none; margin-top:8px; }
        .number-input { width:60px; padding:6px; border:1px solid #ddd; border-radius:4px; text-align:center; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>Jeux & Extensions</h1>
                <p style="margin:5px 0 0; color:#666;">Configuration des jeux de base et extensions</p>
            </div>
            <div>
                <a href="index.php" class="back-btn">← Dashboard</a>
                <a href="logout.php" class="logout-btn">Déconnexion</a>
            </div>
        </div>

        <div class="admin-nav">
            <a href="index.php">Dashboard</a>
            <a href="types.php">Types d'objectifs</a>
            <a href="games.php" class="active">Jeux & Extensions</a>
            <a href="difficulty.php">Difficultés</a>
            <a href="stats.php">Statistiques</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout -->
        <div class="card">
            <h2>Ajouter un nouveau jeu ou extension</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_game_set">
                <table class="form-table">
                    <tr>
                        <th>Nom</th>
                        <td>
                            <input type="text" name="game_name" required class="regular-text" placeholder="Ex: Jeu de Base, Extension Zombies...">
                        </td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td>
                            <textarea name="game_description" class="large-text" placeholder="Description optionnelle..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <label style="display:flex; align-items:center;">
                                <input type="checkbox" name="is_base_game" value="1" style="margin-right:8px;">
                                <span>Jeu de base (cochez si c'est le jeu principal, pas une extension)</span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Types d'objectifs inclus</th>
                        <td>
                            <?php if (empty($types)): ?>
                                <p style="color:#dc3545;"><em>Aucun type configuré. <a href="types.php">Créez d'abord des types</a>.</em></p>
                            <?php else: ?>
                                <div class="types-selection">
                                    <?php foreach ($types as $type): ?>
                                    <div class="type-option">
                                        <label>
                                            <input type="checkbox" name="selected_types[]" value="<?php echo $type['id']; ?>" class="type-checkbox" style="margin-right:10px;">
                                            <?php if (!empty($type['image_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($type['image_url']); ?>" style="width:30px; height:30px; object-fit:contain; vertical-align:middle; margin-right:10px;">
                                            <?php endif; ?>
                                            <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                        </label>
                                        <div class="type-constraints" data-type-id="<?php echo $type['id']; ?>">
                                            <label style="display:block; margin-bottom:5px;">
                                                <input type="checkbox" name="type_limited_<?php echo $type['id']; ?>" class="limited-checkbox">
                                                Type limité (quantité maximale restreinte)
                                            </label>
                                            <div class="max-quantity-field">
                                                <label>
                                                    Quantité maximale :
                                                    <input type="number" name="type_max_<?php echo $type['id']; ?>" min="1" max="20" value="2" class="number-input">
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <button type="submit" class="submit-button">Ajouter le jeu/extension</button>
            </form>
        </div>

        <!-- Liste des jeux existants -->
        <div class="card">
            <h2>Jeux et extensions configurés</h2>
            <?php if (empty($game_sets)): ?>
                <p style="color:#666;">Aucun jeu configuré.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Types inclus</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($game_sets as $game_set):
                            // Récupérer les types inclus
                            $stmt = $pdo->prepare("
                                SELECT t.*, st.is_limited, st.max_quantity
                                FROM " . DB_PREFIX . "types t
                                JOIN " . DB_PREFIX . "set_types st ON t.id = st.type_id
                                WHERE st.game_set_id = ?
                                ORDER BY t.display_order
                            ");
                            $stmt->execute([$game_set['id']]);
                            $included_types = $stmt->fetchAll();
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($game_set['name']); ?></strong></td>
                            <td>
                                <?php if ($game_set['is_base_game']): ?>
                                    <span class="badge base-game">JEU DE BASE</span>
                                <?php else: ?>
                                    <span class="badge extension">EXTENSION</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($game_set['description']); ?></td>
                            <td>
                                <?php foreach ($included_types as $type): ?>
                                    <span style="display:inline-block; margin-right:10px; margin-bottom:5px;">
                                        <?php if (!empty($type['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($type['image_url']); ?>" style="width:24px; height:24px; object-fit:contain; vertical-align:middle; margin-right:5px;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                        <?php if ($type['is_limited']): ?>
                                            <small style="color:#dc3545;">(max: <?php echo $type['max_quantity']; ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce jeu/extension ?');">
                                    <input type="hidden" name="action" value="delete_game_set">
                                    <input type="hidden" name="game_set_id" value="<?php echo $game_set['id']; ?>">
                                    <button type="submit" class="btn-delete">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Gestion de l'affichage des contraintes de types
    document.querySelectorAll('.type-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const typeOption = this.closest('.type-option');
            const constraints = typeOption.querySelector('.type-constraints');

            if (this.checked) {
                constraints.style.display = 'block';
            } else {
                constraints.style.display = 'none';
                const limitedCheckbox = constraints.querySelector('.limited-checkbox');
                limitedCheckbox.checked = false;
                constraints.querySelector('.max-quantity-field').style.display = 'none';
            }
        });
    });

    // Gestion de l'affichage du champ quantité maximale
    document.querySelectorAll('.limited-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const maxQuantityField = this.closest('.type-constraints').querySelector('.max-quantity-field');

            if (this.checked) {
                maxQuantityField.style.display = 'block';
            } else {
                maxQuantityField.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>
