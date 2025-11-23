<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

$page_title = "Objectifs Spéciaux";
$page_description = "Gestion des objectifs spéciaux (ex: Le Parrain)";

$message = '';
$message_type = '';

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // Ajouter un objectif spécial
        if ($_POST['action'] === 'add_special') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $probability = floatval($_POST['probability']);
            $max_per_game = intval($_POST['max_per_game']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Requirements vide - les images contiennent directement les infos
            $requirements = new stdClass();

            if (empty($name)) {
                $message = 'Le nom est obligatoire';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "special_objectives
                        (name, description, requirements, probability, max_per_game, is_active)
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $description, json_encode($requirements), $probability, $max_per_game, $is_active]);

                    $special_id = $pdo->lastInsertId();

                    // Gérer les images par nombre de joueurs
                    $upload_dir = __DIR__ . '/../assets/uploads/special/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    for ($pc = 2; $pc <= 6; $pc++) {
                        $file_key = 'image_' . $pc . 'p';
                        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                            $file_tmp = $_FILES[$file_key]['tmp_name'];
                            $file_ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));

                            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            if (in_array($file_ext, $allowed)) {
                                $filename = sanitize_filename($name) . '-' . $pc . 'p-' . time() . '.' . $file_ext;
                                $upload_path = $upload_dir . $filename;

                                if (move_uploaded_file($file_tmp, $upload_path)) {
                                    $image_url = '../assets/uploads/special/' . $filename;

                                    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "special_objective_images
                                        (special_objective_id, player_count, image_url) VALUES (?, ?, ?)");
                                    $stmt->execute([$special_id, $pc, $image_url]);
                                }
                            }
                        }
                    }

                    $message = 'Objectif spécial ajouté avec succès !';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Erreur : ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }

        // Supprimer un objectif spécial
        if ($_POST['action'] === 'delete_special' && isset($_POST['special_id'])) {
            $special_id = intval($_POST['special_id']);
            try {
                $pdo->prepare("DELETE FROM " . DB_PREFIX . "special_objective_images WHERE special_objective_id = ?")->execute([$special_id]);
                $pdo->prepare("DELETE FROM " . DB_PREFIX . "special_objectives WHERE id = ?")->execute([$special_id]);
                $message = 'Objectif spécial supprimé !';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erreur : ' . $e->getMessage();
                $message_type = 'error';
            }
        }

        // Toggle actif/inactif
        if ($_POST['action'] === 'toggle_active' && isset($_POST['special_id'])) {
            $special_id = intval($_POST['special_id']);
            try {
                $pdo->prepare("UPDATE " . DB_PREFIX . "special_objectives SET is_active = NOT is_active WHERE id = ?")->execute([$special_id]);
                $message = 'Statut modifié !';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erreur : ' . $e->getMessage();
                $message_type = 'error';
            }
        }

        // Modifier un objectif spécial
        if ($_POST['action'] === 'edit_special' && isset($_POST['special_id'])) {
            $special_id = intval($_POST['special_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $probability = floatval($_POST['probability']);
            $max_per_game = intval($_POST['max_per_game']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            try {
                $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "special_objectives
                    SET name = ?, description = ?, probability = ?, max_per_game = ?, is_active = ?
                    WHERE id = ?");
                $stmt->execute([$name, $description, $probability, $max_per_game, $is_active, $special_id]);

                // Gérer les nouvelles images uploadées
                $upload_dir = __DIR__ . '/../assets/uploads/special/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                for ($pc = 2; $pc <= 6; $pc++) {
                    $file_key = 'image_' . $pc . 'p';
                    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES[$file_key]['tmp_name'];
                        $file_ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));

                        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        if (in_array($file_ext, $allowed)) {
                            $filename = sanitize_filename($name) . '-' . $pc . 'p-' . time() . '.' . $file_ext;
                            $upload_path = $upload_dir . $filename;

                            if (move_uploaded_file($file_tmp, $upload_path)) {
                                $image_url = '../assets/uploads/special/' . $filename;

                                // Supprimer l'ancienne image
                                $pdo->prepare("DELETE FROM " . DB_PREFIX . "special_objective_images
                                    WHERE special_objective_id = ? AND player_count = ?")->execute([$special_id, $pc]);

                                // Insérer la nouvelle
                                $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "special_objective_images
                                    (special_objective_id, player_count, image_url) VALUES (?, ?, ?)");
                                $stmt->execute([$special_id, $pc, $image_url]);
                            }
                        }
                    }
                }

                $message = 'Objectif spécial modifié !';
                $message_type = 'success';
                header('Location: special-objectives.php?success=1');
                exit;
            } catch (PDOException $e) {
                $message = 'Erreur : ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Mode édition
$edit_special = null;
$edit_images = [];
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "special_objectives WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_special = $stmt->fetch();

    if ($edit_special) {
        $stmt = $pdo->prepare("SELECT player_count, image_url FROM " . DB_PREFIX . "special_objective_images WHERE special_objective_id = ?");
        $stmt->execute([$edit_id]);
        while ($row = $stmt->fetch()) {
            $edit_images[$row['player_count']] = $row['image_url'];
        }
    }
}

// Récupérer tous les objectifs spéciaux
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "special_objectives ORDER BY display_order ASC, id ASC");
$special_objectives = $stmt->fetchAll();

// Récupérer les images pour chaque objectif
$special_images = [];
foreach ($special_objectives as $so) {
    $stmt = $pdo->prepare("SELECT player_count, image_url FROM " . DB_PREFIX . "special_objective_images WHERE special_objective_id = ?");
    $stmt->execute([$so['id']]);
    $special_images[$so['id']] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Fonction helper pour sanitize filename
function sanitize_filename($name) {
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    return trim($name, '-');
}

if (isset($_GET['success'])) {
    $message = 'Modifications enregistrées !';
    $message_type = 'success';
}

$extra_styles = '<style>
    .form-section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
    .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
    .form-input:focus { border-color: #667eea; outline: none; }
    .submit-button { background: #1a1a1a; color: #fff; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
    .submit-button:hover { background: #2a2a2a; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .data-table th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; border-bottom: 2px solid #dee2e6; }
    .data-table td { padding: 12px; border-bottom: 1px solid #dee2e6; vertical-align: top; }
    .data-table tr:hover { background: #f8f9fa; }
    .btn-delete { background: #dc3545; color: #fff; padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; }
    .btn-edit { background: #667eea; color: #fff; padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
    .btn-toggle { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; }
    .btn-toggle.active { background: #28a745; color: white; }
    .btn-toggle.inactive { background: #6c757d; color: white; }
    .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .message.success { background: #d4edda; color: #155724; }
    .message.error { background: #f8d7da; color: #721c24; }
    .image-grid { display: flex; gap: 10px; flex-wrap: wrap; }
    .image-grid img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
    .images-upload { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-top: 15px; }
    .image-upload-box { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #ddd; }
    .image-upload-box label { font-weight: 600; display: block; margin-bottom: 10px; }
    .image-upload-box img { max-width: 80px; max-height: 80px; margin-bottom: 10px; border-radius: 4px; }
    .probability-info { font-size: 12px; color: #666; margin-top: 5px; }
</style>';

require_once __DIR__ . '/includes/admin-layout.php';
?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Formulaire d'ajout/édition -->
<div class="form-section">
    <h2 style="margin: 0 0 20px 0; font-size: 20px;">
        <?php echo $edit_special ? 'Modifier l\'objectif spécial' : 'Ajouter un objectif spécial'; ?>
    </h2>

    <?php if ($edit_special): ?>
        <p style="color:#667eea; margin-bottom:15px; font-weight:600;">
            Mode édition - <a href="special-objectives.php" style="color:#dc3545;">Annuler</a>
        </p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $edit_special ? 'edit_special' : 'add_special'; ?>">
        <?php if ($edit_special): ?>
            <input type="hidden" name="special_id" value="<?php echo $edit_special['id']; ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label>Nom de l'objectif</label>
                <input type="text" name="name" class="form-input" required placeholder="Ex: Le Parrain"
                       value="<?php echo $edit_special ? htmlspecialchars($edit_special['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label>Probabilité (0 à 1)</label>
                <input type="number" name="probability" class="form-input" step="0.001" min="0" max="1"
                       value="<?php echo $edit_special ? $edit_special['probability'] : '0.083'; ?>">
                <div class="probability-info">0.083 = environ 1/12 des parties</div>
            </div>
            <div class="form-group">
                <label>Max par partie</label>
                <input type="number" name="max_per_game" class="form-input" min="1" max="6"
                       value="<?php echo $edit_special ? $edit_special['max_per_game'] : '1'; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Description (optionnel)</label>
            <textarea name="description" class="form-input" rows="2"><?php echo $edit_special ? htmlspecialchars($edit_special['description']) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" <?php echo (!$edit_special || $edit_special['is_active']) ? 'checked' : ''; ?>>
                Actif
            </label>
        </div>

        <hr style="margin: 20px 0;">

        <h3>Images par nombre de joueurs</h3>
        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
            Uploadez une image pour chaque configuration de joueurs. L'image sera affichée au joueur qui reçoit cet objectif.
        </p>
        <div class="images-upload">
            <?php for ($pc = 2; $pc <= 6; $pc++): ?>
            <div class="image-upload-box">
                <label><?php echo $pc; ?> joueurs</label>
                <?php if (isset($edit_images[$pc])): ?>
                    <img src="<?php echo htmlspecialchars($edit_images[$pc]); ?>">
                <?php endif; ?>
                <input type="file" name="image_<?php echo $pc; ?>p" accept="image/*" style="width: 100%;">
            </div>
            <?php endfor; ?>
        </div>

        <div style="margin-top: 25px;">
            <button type="submit" class="submit-button">
                <?php echo $edit_special ? 'Modifier' : 'Ajouter l\'objectif spécial'; ?>
            </button>
        </div>
    </form>
</div>

<!-- Liste des objectifs spéciaux -->
<div class="form-section">
    <h2>Objectifs spéciaux existants</h2>

    <?php if (empty($special_objectives)): ?>
        <p style="color: #666;">Aucun objectif spécial configuré.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Probabilité</th>
                    <th>Max/partie</th>
                    <th>Images</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($special_objectives as $so): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($so['name']); ?></strong></td>
                    <td><?php echo round($so['probability'] * 100, 1); ?>%</td>
                    <td><?php echo $so['max_per_game']; ?></td>
                    <td>
                        <div class="image-grid">
                            <?php
                            $images = $special_images[$so['id']] ?? [];
                            foreach ($images as $pc => $url):
                            ?>
                                <img src="<?php echo htmlspecialchars($url); ?>" title="<?php echo $pc; ?> joueurs">
                            <?php endforeach; ?>
                            <?php if (empty($images)): ?>
                                <em style="color: #999; font-size: 12px;">Aucune image</em>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="special_id" value="<?php echo $so['id']; ?>">
                            <button type="submit" class="btn-toggle <?php echo $so['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $so['is_active'] ? 'Actif' : 'Inactif'; ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <a href="special-objectives.php?edit=<?php echo $so['id']; ?>" class="btn-edit">Modifier</a>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Supprimer cet objectif spécial ?');">
                            <input type="hidden" name="action" value="delete_special">
                            <input type="hidden" name="special_id" value="<?php echo $so['id']; ?>">
                            <button type="submit" class="btn-delete">Supprimer</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>


<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
