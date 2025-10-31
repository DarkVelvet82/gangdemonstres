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

        // Ajouter un type
        if ($_POST['action'] === 'add_type') {
            $name = trim($_POST['type_name']);
            $image_url = '';

            // Gérer l'upload d'image
            if (isset($_FILES['type_image']) && $_FILES['type_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../assets/uploads/types/';

                // Créer le dossier s'il n'existe pas
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_tmp = $_FILES['type_image']['tmp_name'];
                $file_name = $_FILES['type_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                // Vérifier l'extension
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                if (!in_array($file_ext, $allowed_extensions)) {
                    $message = 'Format d\'image non autorisé. Utilisez: JPG, PNG, GIF, WEBP ou SVG';
                    $message_type = 'error';
                } else {
                    // Générer un nom de fichier à partir du nom du type
                    $filename_base = $name;
                    $filename_base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename_base);
                    $filename_base = strtolower($filename_base);
                    $filename_base = preg_replace('/[^a-z0-9]+/', '-', $filename_base);
                    $filename_base = trim($filename_base, '-');

                    $new_filename = $filename_base . '-' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $image_url = '../assets/uploads/types/' . $new_filename;
                    } else {
                        $message = 'Erreur lors de l\'upload de l\'image';
                        $message_type = 'error';
                    }
                }
            }

            // Insérer en base si pas d'erreur d'upload
            if (empty($message) && !empty($image_url)) {
                try {
                    // Obtenir le prochain display_order
                    $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM " . DB_PREFIX . "types");
                    $next_order = $stmt->fetchColumn();

                    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "types (name, image_url, display_order) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $image_url, $next_order]);

                    $message = 'Type ajouté avec succès !';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Erreur lors de l\'ajout du type : ' . $e->getMessage();
                    $message_type = 'error';
                }
            } elseif (empty($message)) {
                $message = 'Veuillez sélectionner une image';
                $message_type = 'error';
            }
        }

        // Supprimer un type
        if ($_POST['action'] === 'delete_type' && isset($_POST['type_id'])) {
            $type_id = (int)$_POST['type_id'];

            try {
                $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "types WHERE id = ?");
                $stmt->execute([$type_id]);

                $message = 'Type supprimé avec succès !';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erreur lors de la suppression : ' . $e->getMessage();
                $message_type = 'error';
            }
        }

        // Mettre à jour l'ordre
        if ($_POST['action'] === 'update_order' && isset($_POST['type_orders'])) {
            try {
                foreach ($_POST['type_orders'] as $type_id => $order) {
                    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "types SET display_order = ? WHERE id = ?");
                    $stmt->execute([(int)$order, (int)$type_id]);
                }

                $message = 'Ordre mis à jour avec succès !';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erreur lors de la mise à jour : ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Récupérer tous les types
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "types ORDER BY display_order ASC");
$types = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Types d'objectifs - Administration</title>
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
        .form-table th { text-align:left; padding:15px 10px 15px 0; width:180px; font-weight:600; }
        .form-table td { padding:15px 0; }
        .regular-text { width:100%; max-width:400px; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
        .small-text { width:80px; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:24px; text-align:center; }
        .submit-button { background:#667eea; color:#fff; padding:12px 30px; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:15px; transition: all .3s; }
        .submit-button:hover { background:#5568d3; transform: translateY(-2px); }
        .data-table { width:100%; border-collapse:collapse; margin-top:15px; }
        .data-table thead { background:#f8f9fa; }
        .data-table th { text-align:left; padding:12px; font-weight:600; border-bottom:2px solid #dee2e6; }
        .data-table td { padding:12px; border-bottom:1px solid #dee2e6; }
        .data-table tr:hover { background:#f8f9fa; }
        .btn-delete { background:#dc3545; color:#fff; padding:6px 12px; border:none; border-radius:6px; cursor:pointer; font-size:13px; }
        .btn-delete:hover { background:#c82333; }
        .number-input { width:60px; padding:6px; border:1px solid #ddd; border-radius:4px; text-align:center; }
        .message { padding:15px; border-radius:8px; margin-bottom:20px; }
        .message.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .message.error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .image-preview img { max-width:100px; max-height:100px; margin-top:10px; border-radius:6px; }
        .upload-btn { background:#28a745; color:#fff; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
        .upload-btn:hover { background:#218838; }
        .logout-btn { padding:10px 20px; background:#dc3545; color:#fff; border:none; border-radius:8px; text-decoration:none; font-weight:600; }
        .back-btn { padding:10px 20px; background:#6c757d; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; margin-right:10px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>Types d'Objectifs</h1>
                <p style="margin:5px 0 0; color:#666;">Gestion des types de monstres</p>
            </div>
            <div>
                <a href="index.php" class="back-btn">← Dashboard</a>
                <a href="logout.php" class="logout-btn">Déconnexion</a>
            </div>
        </div>

        <div class="admin-nav">
            <a href="index.php">Dashboard</a>
            <a href="types.php" class="active">Types d'objectifs</a>
            <a href="games.php">Jeux & Extensions</a>
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
            <h2>Ajouter un nouveau type</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_type">
                <table class="form-table">
                    <tr>
                        <th>Nom du type</th>
                        <td>
                            <input type="text" name="type_name" required class="regular-text" placeholder="Ex: Zombie, Sorcière...">
                        </td>
                    </tr>
                    <tr>
                        <th>Image</th>
                        <td>
                            <input type="file" name="type_image" required accept="image/*" class="regular-text">
                            <small style="display:block; margin-top:5px; color:#666;">Formats acceptés: JPG, PNG, GIF, WEBP, SVG (obligatoire)</small>
                            <div id="image-preview" style="margin-top:10px; display:none;">
                                <img id="preview-img" src="" style="max-width:150px; max-height:150px; border-radius:8px; border:2px solid #ddd;">
                            </div>
                        </td>
                    </tr>
                </table>
                <button type="submit" class="submit-button">Ajouter le type</button>
            </form>
        </div>

        <!-- Liste des types existants -->
        <div class="card">
            <h2>Types existants</h2>
            <?php if (empty($types)): ?>
                <p style="color:#666;">Aucun type configuré.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="update_order">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ordre</th>
                                <th>Nom</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($types as $type): ?>
                            <tr>
                                <td>
                                    <input type="number" name="type_orders[<?php echo $type['id']; ?>]"
                                           value="<?php echo $type['display_order']; ?>"
                                           class="number-input" min="1">
                                </td>
                                <td><strong><?php echo htmlspecialchars($type['name']); ?></strong></td>
                                <td>
                                    <?php if (!empty($type['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($type['image_url']); ?>"
                                             style="width:50px; height:50px; object-fit:contain;">
                                    <?php else: ?>
                                        <em style="color:#999;">Aucune image</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" style="display:inline;"
                                          onsubmit="return confirm('Supprimer ce type ?');">
                                        <input type="hidden" name="action" value="delete_type">
                                        <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                                        <button type="submit" class="btn-delete">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="submit-button" style="margin-top:20px;">Mettre à jour l'ordre</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Prévisualisation de l'image avant upload
    document.querySelector('input[name="type_image"]').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const preview = document.getElementById('image-preview');
                const previewImg = document.getElementById('preview-img');
                previewImg.src = event.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html>
