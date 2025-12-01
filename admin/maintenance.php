<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: text/html; charset=UTF-8');

// Fonctions pour gérer les settings (si pas déjà définies)
if (!function_exists('get_setting')) {
    function get_setting($pdo, $key, $default = '') {
        $stmt = $pdo->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    }
}

if (!function_exists('set_setting')) {
    function set_setting($pdo, $key, $value) {
        $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "settings (setting_key, setting_value)
            VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
}

// Traitement du formulaire
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle maintenance
    if (isset($_POST['maintenance_enabled'])) {
        set_setting($pdo, 'maintenance_enabled', '1');
    } else {
        set_setting($pdo, 'maintenance_enabled', '0');
    }

    // Titre
    if (isset($_POST['maintenance_title'])) {
        set_setting($pdo, 'maintenance_title', trim($_POST['maintenance_title']));
    }

    // Texte
    if (isset($_POST['maintenance_text'])) {
        set_setting($pdo, 'maintenance_text', trim($_POST['maintenance_text']));
    }

    // Bouton texte
    if (isset($_POST['maintenance_button_text'])) {
        set_setting($pdo, 'maintenance_button_text', trim($_POST['maintenance_button_text']));
    }

    // Bouton URL
    if (isset($_POST['maintenance_button_url'])) {
        set_setting($pdo, 'maintenance_button_url', trim($_POST['maintenance_button_url']));
    }

    // Upload de l'image
    if (isset($_FILES['maintenance_image']) && $_FILES['maintenance_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../assets/uploads/';

        // Créer le dossier s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file = $_FILES['maintenance_image'];
        $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

        if (in_array($file['type'], $allowed_types)) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'maintenance_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Supprimer l'ancienne image si existante
                $old_image = get_setting($pdo, 'maintenance_image');
                if ($old_image) {
                    $old_path = __DIR__ . '/../' . ltrim($old_image, '../');
                    if (file_exists($old_path)) {
                        @unlink($old_path);
                    }
                }

                set_setting($pdo, 'maintenance_image', '../assets/uploads/' . $filename);
                $message = 'Image de maintenance mise à jour !';
            } else {
                $error = 'Erreur lors de l\'upload du fichier';
            }
        } else {
            $error = 'Type de fichier non autorisé. Utilisez PNG, JPG, GIF ou WebP.';
        }
    }

    // Supprimer l'image
    if (isset($_POST['remove_image'])) {
        $old_image = get_setting($pdo, 'maintenance_image');
        if ($old_image) {
            $image_path = __DIR__ . '/../' . ltrim($old_image, '../');
            if (file_exists($image_path)) {
                @unlink($image_path);
            }
            set_setting($pdo, 'maintenance_image', '');
            $message = 'Image supprimée';
        }
    }

    if (!$message && !$error) {
        $message = 'Paramètres de maintenance enregistrés';
    }
}

// Récupérer les valeurs actuelles
$maintenance_enabled = get_setting($pdo, 'maintenance_enabled', '0') === '1';
$maintenance_title = get_setting($pdo, 'maintenance_title', 'Site en maintenance');
$maintenance_text = get_setting($pdo, 'maintenance_text', 'Nous effectuons actuellement des travaux de maintenance. Merci de revenir plus tard.');
$maintenance_image = get_setting($pdo, 'maintenance_image', '');
$maintenance_button_text = get_setting($pdo, 'maintenance_button_text', '');
$maintenance_button_url = get_setting($pdo, 'maintenance_button_url', '');

$page_title = "Mode Maintenance";
$page_description = "Activer/désactiver le mode maintenance du site";

$extra_styles = '
<style>
    .maintenance-form {
        max-width: 700px;
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
    .form-group input[type="text"],
    .form-group input[type="url"],
    .form-group textarea {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e1e4e8;
        border-radius: 8px;
        font-size: 15px;
        transition: border-color 0.2s;
        box-sizing: border-box;
    }
    .form-group textarea {
        min-height: 120px;
        resize: vertical;
        font-family: inherit;
    }
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
    }
    .form-hint {
        font-size: 13px;
        color: #666;
        margin-top: 5px;
    }

    /* Toggle switch */
    .toggle-group {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 12px;
        margin-bottom: 30px;
    }
    .toggle-group.active {
        background: #fff3cd;
        border: 2px solid #ffc107;
    }
    .toggle-switch {
        position: relative;
        width: 60px;
        height: 32px;
        flex-shrink: 0;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: 0.3s;
        border-radius: 32px;
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 24px;
        width: 24px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .toggle-switch input:checked + .toggle-slider {
        background-color: #ffc107;
    }
    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(28px);
    }
    .toggle-label {
        font-size: 16px;
        font-weight: 600;
    }
    .toggle-label .status {
        display: block;
        font-size: 13px;
        font-weight: normal;
        color: #666;
        margin-top: 2px;
    }
    .toggle-group.active .toggle-label .status {
        color: #856404;
    }

    /* Image preview */
    .image-preview {
        margin: 15px 0;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        text-align: center;
    }
    .image-preview img {
        max-width: 100%;
        max-height: 200px;
        object-fit: contain;
        border-radius: 8px;
    }
    .image-preview .no-image {
        color: #999;
        font-style: italic;
    }
    .file-input-wrapper input[type="file"] {
        padding: 10px;
        border: 2px dashed #ccc;
        border-radius: 8px;
        width: 100%;
        cursor: pointer;
        box-sizing: border-box;
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

    /* Button fields row */
    .button-fields {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    @media (max-width: 600px) {
        .button-fields {
            grid-template-columns: 1fr;
        }
    }

    /* Preview section */
    .maintenance-preview {
        margin-top: 40px;
        padding: 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        color: white;
    }
    .maintenance-preview h3 {
        margin: 0 0 20px 0;
        font-size: 14px;
        text-transform: uppercase;
        opacity: 0.8;
    }
    .preview-content {
        background: white;
        border-radius: 12px;
        padding: 40px 30px;
        text-align: center;
        color: #333;
    }
    .preview-content img {
        max-width: 100%;
        max-height: 150px;
        object-fit: contain;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    .preview-content h4 {
        font-size: 24px;
        margin: 0 0 15px 0;
        color: #333;
    }
    .preview-content p {
        font-size: 16px;
        color: #666;
        line-height: 1.6;
        margin: 0 0 20px 0;
    }
    .preview-content .preview-button {
        display: inline-block;
        padding: 12px 24px;
        background: linear-gradient(135deg, #003f53 0%, #003547 100%);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
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
    <h2>Configuration du mode maintenance</h2>

    <form method="POST" enctype="multipart/form-data" class="maintenance-form">

        <!-- Toggle ON/OFF -->
        <div class="toggle-group <?php echo $maintenance_enabled ? 'active' : ''; ?>" id="toggle-group">
            <label class="toggle-switch">
                <input type="checkbox" name="maintenance_enabled" id="maintenance_enabled" <?php echo $maintenance_enabled ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
            <div class="toggle-label">
                Mode Maintenance
                <span class="status" id="toggle-status">
                    <?php echo $maintenance_enabled ? '⚠️ Le site est actuellement en maintenance' : '✅ Le site est accessible normalement'; ?>
                </span>
            </div>
        </div>

        <!-- Titre -->
        <div class="form-group">
            <label for="maintenance_title">Titre</label>
            <input type="text" id="maintenance_title" name="maintenance_title"
                   value="<?php echo htmlspecialchars($maintenance_title); ?>"
                   placeholder="Site en maintenance">
        </div>

        <!-- Image -->
        <div class="form-group">
            <label>Image</label>
            <div class="image-preview">
                <?php if ($maintenance_image): ?>
                    <img src="<?php echo htmlspecialchars($maintenance_image); ?>" alt="Image de maintenance">
                <?php else: ?>
                    <span class="no-image">Aucune image configurée</span>
                <?php endif; ?>
            </div>

            <div class="file-input-wrapper">
                <input type="file" name="maintenance_image" accept="image/png,image/jpeg,image/gif,image/webp">
            </div>
            <p class="form-hint">Formats acceptés : PNG, JPG, GIF, WebP</p>

            <?php if ($maintenance_image): ?>
                <button type="submit" name="remove_image" value="1" class="btn-remove">Supprimer l'image</button>
            <?php endif; ?>
        </div>

        <!-- Texte -->
        <div class="form-group">
            <label for="maintenance_text">Texte</label>
            <textarea id="maintenance_text" name="maintenance_text"
                      placeholder="Nous effectuons actuellement des travaux de maintenance..."><?php echo htmlspecialchars($maintenance_text); ?></textarea>
        </div>

        <!-- Bouton -->
        <div class="button-fields">
            <div class="form-group">
                <label for="maintenance_button_text">Texte du bouton (optionnel)</label>
                <input type="text" id="maintenance_button_text" name="maintenance_button_text"
                       value="<?php echo htmlspecialchars($maintenance_button_text); ?>"
                       placeholder="Ex: Nous contacter">
            </div>
            <div class="form-group">
                <label for="maintenance_button_url">URL du bouton</label>
                <input type="url" id="maintenance_button_url" name="maintenance_button_url"
                       value="<?php echo htmlspecialchars($maintenance_button_url); ?>"
                       placeholder="https://...">
            </div>
        </div>
        <p class="form-hint" style="margin-top: -15px;">Laissez vide pour ne pas afficher de bouton</p>

        <button type="submit" class="submit-button">Enregistrer</button>
    </form>

    <!-- Aperçu -->
    <div class="maintenance-preview">
        <h3>Aperçu de la page maintenance</h3>
        <div class="preview-content">
            <?php if ($maintenance_image): ?>
                <img src="<?php echo htmlspecialchars($maintenance_image); ?>" alt="">
            <?php endif; ?>
            <h4 id="preview-title"><?php echo htmlspecialchars($maintenance_title); ?></h4>
            <p id="preview-text"><?php echo nl2br(htmlspecialchars($maintenance_text)); ?></p>
            <?php if ($maintenance_button_text && $maintenance_button_url): ?>
                <a href="#" class="preview-button" id="preview-button"><?php echo htmlspecialchars($maintenance_button_text); ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Mise à jour dynamique du toggle
document.getElementById('maintenance_enabled').addEventListener('change', function() {
    const toggleGroup = document.getElementById('toggle-group');
    const status = document.getElementById('toggle-status');

    if (this.checked) {
        toggleGroup.classList.add('active');
        status.innerHTML = '⚠️ Le site sera en maintenance après sauvegarde';
    } else {
        toggleGroup.classList.remove('active');
        status.innerHTML = '✅ Le site sera accessible après sauvegarde';
    }
});

// Mise à jour de l'aperçu en temps réel
document.getElementById('maintenance_title').addEventListener('input', function() {
    document.getElementById('preview-title').textContent = this.value || 'Site en maintenance';
});

document.getElementById('maintenance_text').addEventListener('input', function() {
    document.getElementById('preview-text').innerHTML = (this.value || 'Texte de maintenance...').replace(/\n/g, '<br>');
});
</script>

<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
