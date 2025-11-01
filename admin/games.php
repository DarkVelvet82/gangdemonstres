<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

$page_title = "Jeux & Extensions";
$page_description = "Configuration des jeux de base et extensions";

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

                // Associer les types sélectionnés (simplifié - plus de contraintes)
                if (!empty($_POST['selected_types'])) {
                    foreach ($_POST['selected_types'] as $type_id) {
                        $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "set_types (game_set_id, type_id) VALUES (?, ?)");
                        $stmt->execute([$game_set_id, (int)$type_id]);
                    }
                }

                $message = 'Jeu/Extension ajouté avec succès !';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erreur lors de l\'ajout : ' . $e->getMessage();
                $message_type = 'error';
            }
        }

        // Modifier un jeu/extension
        if ($_POST['action'] === 'edit_game_set' && isset($_POST['game_set_id'])) {
            $game_set_id = (int)$_POST['game_set_id'];
            $name = trim($_POST['game_name']);
            $description = trim($_POST['game_description'] ?? '');
            $is_base = isset($_POST['is_base_game']) ? 1 : 0;

            try {
                // Mettre à jour le jeu
                $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "game_sets SET name = ?, description = ?, is_base_game = ? WHERE id = ?");
                $stmt->execute([$name, $description, $is_base, $game_set_id]);

                // Supprimer les anciennes associations
                $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "set_types WHERE game_set_id = ?");
                $stmt->execute([$game_set_id]);

                // Re-créer les associations
                if (!empty($_POST['selected_types'])) {
                    foreach ($_POST['selected_types'] as $type_id) {
                        $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "set_types (game_set_id, type_id) VALUES (?, ?)");
                        $stmt->execute([$game_set_id, (int)$type_id]);
                    }
                }

                $message = 'Jeu/Extension modifié avec succès !';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erreur lors de la modification : ' . $e->getMessage();
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

$extra_styles = '<style>
    .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,.1); margin-bottom:25px; }
    .card h2 { margin-top:0; color:#333; }
    .form-table { width:100%; }
    .form-table th { text-align:left; padding:15px 10px 15px 0; width:180px; font-weight:600; vertical-align:top; }
    .form-table td { padding:15px 0; }
    .regular-text { width:100%; max-width:500px; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
    .large-text { width:100%; max-width:500px; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; min-height:80px; font-family:inherit; }
    .submit-button { background:#1a1a1a; color:#fff; padding:12px 30px; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:15px; transition:all .3s; margin-top:15px; }
    .submit-button:hover { background:#2a2a2a; }
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
    .badge { padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; color:#fff; display:inline-block; }
    .badge.base-game { background:#28a745; }
    .badge.extension { background:#007bff; }
    .types-selection { display:flex; flex-wrap:wrap; gap:10px; padding:15px; background:#fafafa; border-radius:6px; }
    .type-option input[type="checkbox"] { display:none; }
    .type-option label {
        display:flex;
        align-items:center;
        gap:8px;
        padding:10px 15px;
        border:2px solid #ddd;
        border-radius:8px;
        background:#fff;
        cursor:pointer;
        transition:all .2s;
        font-weight:500;
    }
    .type-option label:hover { background:#f8f8f8; border-color:#999; }
    .type-option input[type="checkbox"]:checked + label {
        background:#1a1a1a;
        color:#fff;
        border-color:#1a1a1a;
    }
    .type-option img { width:24px; height:24px; object-fit:contain; }
    .btn-edit { background:#667eea; color:#fff; padding:6px 12px; border:none; border-radius:6px; cursor:pointer; font-size:13px; margin-right:5px; }
    .btn-edit:hover { background:#5568d3; }

    /* Modal */
    .modal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
    .modal-content { background:#fff; margin:50px auto; padding:0; border-radius:12px; max-width:800px; max-height:90vh; overflow-y:auto; box-shadow:0 4px 20px rgba(0,0,0,0.3); }
    .modal-header { padding:20px 25px; border-bottom:1px solid #dee2e6; display:flex; justify-content:space-between; align-items:center; }
    .modal-header h2 { margin:0; color:#333; }
    .modal-close { cursor:pointer; font-size:28px; font-weight:300; color:#999; line-height:1; }
    .modal-close:hover { color:#333; }
    .modal-body { padding:25px; }
</style>';

require_once __DIR__ . '/includes/admin-layout.php';
?>

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
                                <input type="checkbox" name="selected_types[]" value="<?php echo $type['id']; ?>" id="type_<?php echo $type['id']; ?>">
                                <label for="type_<?php echo $type['id']; ?>">
                                    <?php if (!empty($type['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($type['image_url']); ?>" alt="">
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($type['name']); ?></span>
                                </label>
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
                        SELECT t.*
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
                            </span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <button type="button" class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($game_set), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode(array_column($included_types, 'id')), ENT_QUOTES); ?>)">Modifier</button>
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

<!-- Modal d'édition -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Modifier le jeu/extension</h2>
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="post" id="editForm">
                <input type="hidden" name="action" value="edit_game_set">
                <input type="hidden" name="game_set_id" id="edit_game_set_id">

                <table class="form-table">
                    <tr>
                        <th>Nom</th>
                        <td>
                            <input type="text" name="game_name" id="edit_game_name" required class="regular-text" placeholder="Ex: Jeu de Base, Extension Zombies...">
                        </td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td>
                            <textarea name="game_description" id="edit_game_description" class="large-text" placeholder="Description optionnelle..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <label style="display:flex; align-items:center;">
                                <input type="checkbox" name="is_base_game" id="edit_is_base_game" value="1" style="margin-right:8px;">
                                <span>Jeu de base (cochez si c'est le jeu principal, pas une extension)</span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Types d'objectifs inclus</th>
                        <td>
                            <div class="types-selection" id="edit_types_selection">
                                <?php foreach ($types as $type): ?>
                                <div class="type-option">
                                    <input type="checkbox" name="selected_types[]" value="<?php echo $type['id']; ?>" id="edit_type_<?php echo $type['id']; ?>">
                                    <label for="edit_type_<?php echo $type['id']; ?>">
                                        <?php if (!empty($type['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($type['image_url']); ?>" alt="">
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($type['name']); ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                </table>
                <button type="submit" class="submit-button">Enregistrer les modifications</button>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(gameSet, selectedTypes) {
    document.getElementById('edit_game_set_id').value = gameSet.id;
    document.getElementById('edit_game_name').value = gameSet.name;
    document.getElementById('edit_game_description').value = gameSet.description || '';
    document.getElementById('edit_is_base_game').checked = gameSet.is_base_game == 1;

    // Décocher tous les types
    document.querySelectorAll('#edit_types_selection input[type="checkbox"]').forEach(cb => cb.checked = false);

    // Cocher les types sélectionnés
    selectedTypes.forEach(typeId => {
        const checkbox = document.getElementById('edit_type_' + typeId);
        if (checkbox) checkbox.checked = true;
    });

    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Fermer le modal en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
    }
}
</script>

<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
