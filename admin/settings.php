<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: text/html; charset=UTF-8');

// Créer la table settings si elle n'existe pas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Table existe déjà
}

// Fonctions pour gérer les settings
function get_setting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

function set_setting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "settings (setting_key, setting_value)
        VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

// Traitement du formulaire
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload du logo
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../assets/images/';

        // Créer le dossier s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file = $_FILES['site_logo'];
        $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp'];

        if (in_array($file['type'], $allowed_types)) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Supprimer l'ancien logo si existant
                $old_logo = get_setting($pdo, 'site_logo');
                if ($old_logo && file_exists($upload_dir . basename($old_logo))) {
                    @unlink($upload_dir . basename($old_logo));
                }

                set_setting($pdo, 'site_logo', '../assets/images/' . $filename);
                $message = 'Logo mis à jour avec succès !';
            } else {
                $error = 'Erreur lors de l\'upload du fichier';
            }
        } else {
            $error = 'Type de fichier non autorisé. Utilisez PNG, JPG, GIF, SVG ou WebP.';
        }
    }

    // Supprimer le logo
    if (isset($_POST['remove_logo'])) {
        $old_logo = get_setting($pdo, 'site_logo');
        if ($old_logo) {
            $logo_path = __DIR__ . '/../assets/images/' . basename($old_logo);
            if (file_exists($logo_path)) {
                @unlink($logo_path);
            }
            set_setting($pdo, 'site_logo', '');
            $message = 'Logo supprimé';
        }
    }

    // Nom du site
    if (isset($_POST['site_name'])) {
        set_setting($pdo, 'site_name', trim($_POST['site_name']));
        if (!$message) $message = 'Paramètres enregistrés';
    }
}

// Récupérer les valeurs actuelles
$site_logo = get_setting($pdo, 'site_logo', '');
$site_name = get_setting($pdo, 'site_name', 'Gang de Monstres');

$page_title = "Paramètres";
$page_description = "Configuration du site";

$extra_styles = '
<style>
    .settings-form {
        max-width: 600px;
    }
    .form-group {
        margin-bottom: 25px;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }
    .form-group input[type="text"] {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e1e4e8;
        border-radius: 8px;
        font-size: 15px;
        transition: border-color 0.2s;
    }
    .form-group input[type="text"]:focus {
        outline: none;
        border-color: #667eea;
    }
    .logo-preview {
        margin: 15px 0;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        text-align: center;
    }
    .logo-preview img {
        max-width: 250px;
        max-height: 100px;
        object-fit: contain;
    }
    .logo-preview .no-logo {
        color: #999;
        font-style: italic;
    }
    .file-input-wrapper {
        position: relative;
        display: inline-block;
    }
    .file-input-wrapper input[type="file"] {
        padding: 10px;
        border: 2px dashed #ccc;
        border-radius: 8px;
        width: 100%;
        cursor: pointer;
    }
    .file-input-wrapper input[type="file"]:hover {
        border-color: #667eea;
    }
    .btn-remove {
        background: #dc3545;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        margin-top: 10px;
    }
    .btn-remove:hover {
        background: #c82333;
    }
    .form-hint {
        font-size: 13px;
        color: #666;
        margin-top: 5px;
    }
    .sidebar-preview {
        margin-top: 30px;
        padding: 20px;
        background: #1a1a1a;
        border-radius: 12px;
        color: white;
    }
    .sidebar-preview h3 {
        color: #999;
        font-size: 14px;
        margin-bottom: 15px;
        text-transform: uppercase;
    }
    .sidebar-preview .preview-header {
        padding: 20px;
        background: rgba(0,0,0,0.2);
        border-radius: 8px;
        text-align: center;
    }
    .sidebar-preview .preview-header img {
        max-width: 180px;
        max-height: 60px;
        object-fit: contain;
    }
    .sidebar-preview .preview-header .text-fallback h4 {
        font-size: 16px;
        margin: 0;
    }
    .sidebar-preview .preview-header .text-fallback p {
        font-size: 12px;
        opacity: 0.7;
        margin: 5px 0 0 0;
    }
</style>
';

require_once __DIR__ . '/includes/admin-layout.php';
?>

<?php if ($message): ?>
    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h2>Apparence</h2>

    <form method="POST" enctype="multipart/form-data" class="settings-form">
        <div class="form-group">
            <label>Logo du site</label>
            <div class="logo-preview">
                <?php if ($site_logo && file_exists(__DIR__ . '/' . $site_logo)): ?>
                    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo actuel">
                <?php else: ?>
                    <span class="no-logo">Aucun logo configuré</span>
                <?php endif; ?>
            </div>

            <div class="file-input-wrapper">
                <input type="file" name="site_logo" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp">
            </div>
            <p class="form-hint">Formats acceptés : PNG, JPG, GIF, SVG, WebP. Taille recommandée : 250x80px</p>

            <?php if ($site_logo): ?>
                <button type="submit" name="remove_logo" value="1" class="btn-remove">Supprimer le logo</button>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="site_name">Nom du site (si pas de logo)</label>
            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" placeholder="Gang de Monstres">
            <p class="form-hint">Affiché dans le menu latéral si aucun logo n'est configuré</p>
        </div>

        <button type="submit" class="submit-button">Enregistrer</button>
    </form>

    <div class="sidebar-preview">
        <h3>Aperçu du menu</h3>
        <div class="preview-header">
            <?php if ($site_logo && file_exists(__DIR__ . '/' . $site_logo)): ?>
                <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo">
            <?php else: ?>
                <div class="text-fallback">
                    <h4><?php echo htmlspecialchars($site_name); ?></h4>
                    <p>Administration</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
