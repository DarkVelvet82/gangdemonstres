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

        // Ajouter une carte
        if ($_POST['action'] === 'add_card') {
            $name = trim($_POST['card_name']);
            $card_type = $_POST['card_type'];
            $game_set_id = (int)$_POST['game_set_id'];
            $quantity = max(1, (int)$_POST['quantity']); // Au moins 1 exemplaire
            $main_type_id = !empty($_POST['main_type_id']) ? (int)$_POST['main_type_id'] : null;
            $has_eye = isset($_POST['has_eye']) ? 1 : 0;
            $power_text = trim($_POST['power_text'] ?? '');
            $is_visible = isset($_POST['is_visible']) ? 1 : 0;
            $image_url = '';

            // Gérer l'upload d'image
            if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../assets/uploads/cards/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_tmp = $_FILES['card_image']['tmp_name'];
                $file_name = $_FILES['card_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($file_ext, $allowed_extensions)) {
                    $message = 'Format d\'image non autorisé. Utilisez: JPG, PNG, GIF, WEBP';
                    $message_type = 'error';
                } else {
                    $filename_base = $name;
                    $filename_base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename_base);
                    $filename_base = strtolower($filename_base);
                    $filename_base = preg_replace('/[^a-z0-9]+/', '-', $filename_base);
                    $filename_base = trim($filename_base, '-');

                    $new_filename = $filename_base . '-' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $image_url = '../assets/uploads/cards/' . $new_filename;
                    } else {
                        $message = 'Erreur lors de l\'upload de l\'image';
                        $message_type = 'error';
                    }
                }
            }

            if (empty($message)) {
                try {
                    $pdo->beginTransaction();

                    // Obtenir le prochain display_order
                    $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM " . DB_PREFIX . "cards");
                    $next_order = $stmt->fetchColumn();

                    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "cards (name, card_type, game_set_id, quantity, main_type_id, has_eye, image_url, power_text, is_visible, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $card_type, $game_set_id, $quantity, $main_type_id, $has_eye, $image_url, $power_text, $is_visible, $next_order]);

                    $card_id = $pdo->lastInsertId();

                    // Si c'est un monstre, enregistrer les quantités de types
                    if ($card_type === 'monster' && !empty($_POST['type_quantities'])) {
                        foreach ($_POST['type_quantities'] as $type_id => $quantity) {
                            $quantity = (int)$quantity;
                            if ($quantity > 0) {
                                $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "card_types (card_id, type_id, quantity) VALUES (?, ?, ?)");
                                $stmt->execute([$card_id, (int)$type_id, $quantity]);
                            }
                        }
                    }

                    $pdo->commit();

                    $message = 'Carte ajoutée avec succès !';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = 'Erreur lors de l\'ajout de la carte : ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }

        // Modifier une carte
        if ($_POST['action'] === 'edit_card' && isset($_POST['card_id'])) {
            $card_id = (int)$_POST['card_id'];
            $name = trim($_POST['card_name']);
            $card_type = $_POST['card_type'];
            $game_set_id = (int)$_POST['game_set_id'];
            $quantity = max(1, (int)$_POST['quantity']); // Au moins 1 exemplaire
            $main_type_id = !empty($_POST['main_type_id']) ? (int)$_POST['main_type_id'] : null;
            $has_eye = isset($_POST['has_eye']) ? 1 : 0;
            $power_text = trim($_POST['power_text'] ?? '');
            $is_visible = isset($_POST['is_visible']) ? 1 : 0;
            $image_url = $_POST['existing_image_url'] ?? '';

            // Gérer l'upload d'une nouvelle image (optionnel)
            if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../assets/uploads/cards/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_tmp = $_FILES['card_image']['tmp_name'];
                $file_name = $_FILES['card_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($file_ext, $allowed_extensions)) {
                    $filename_base = $name;
                    $filename_base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename_base);
                    $filename_base = strtolower($filename_base);
                    $filename_base = preg_replace('/[^a-z0-9]+/', '-', $filename_base);
                    $filename_base = trim($filename_base, '-');

                    $new_filename = $filename_base . '-' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $image_url = '../assets/uploads/cards/' . $new_filename;
                    }
                }
            }

            try {
                $pdo->beginTransaction();

                // Mettre à jour la carte
                $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "cards SET name = ?, card_type = ?, game_set_id = ?, quantity = ?, main_type_id = ?, has_eye = ?, image_url = ?, power_text = ?, is_visible = ? WHERE id = ?");
                $stmt->execute([$name, $card_type, $game_set_id, $quantity, $main_type_id, $has_eye, $image_url, $power_text, $is_visible, $card_id]);

                // Supprimer les anciennes quantités de types
                $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "card_types WHERE card_id = ?");
                $stmt->execute([$card_id]);

                // Réinsérer les nouvelles quantités
                if ($card_type === 'monster' && !empty($_POST['type_quantities'])) {
                    foreach ($_POST['type_quantities'] as $type_id => $quantity) {
                        $quantity = (int)$quantity;
                        if ($quantity > 0) {
                            $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "card_types (card_id, type_id, quantity) VALUES (?, ?, ?)");
                            $stmt->execute([$card_id, (int)$type_id, $quantity]);
                        }
                    }
                }

                $pdo->commit();

                $message = 'Carte modifiée avec succès !';
                $message_type = 'success';

                // Rediriger pour fermer la modale et afficher le message
                header('Location: cards.php?success=1');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = 'Erreur lors de la modification : ' . $e->getMessage();
                $message_type = 'error';
            }
        }

        // Supprimer une carte
        if ($_POST['action'] === 'delete_card' && isset($_POST['card_id'])) {
            $card_id = (int)$_POST['card_id'];

            try {
                $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "cards WHERE id = ?");
                $stmt->execute([$card_id]);

                $message = 'Carte supprimée avec succès !';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erreur lors de la suppression : ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Mode édition : récupérer la carte à éditer
$edit_card = null;
$edit_card_types = [];
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "cards WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_card = $stmt->fetch();

    if ($edit_card) {
        // Récupérer les types de cette carte
        $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "card_types WHERE card_id = ?");
        $stmt->execute([$edit_id]);
        $edit_card_types_raw = $stmt->fetchAll();

        // Organiser par type_id pour faciliter l'affichage
        foreach ($edit_card_types_raw as $ct) {
            if ($ct['type_id'] !== null) {
                $edit_card_types[$ct['type_id']] = $ct['quantity'];
            }
        }
    }
}

// Récupérer toutes les cartes
$filter_game_set = isset($_GET['game_set']) ? (int)$_GET['game_set'] : 0;
$filter_card_type = isset($_GET['card_type']) ? $_GET['card_type'] : '';
$filter_main_type = isset($_GET['main_type']) ? (int)$_GET['main_type'] : 0;
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'type_alpha'; // Par défaut: type puis alphabétique

$sql = "SELECT c.*, gs.name as game_set_name, t.name as main_type_name, t.display_order as type_order
        FROM " . DB_PREFIX . "cards c
        LEFT JOIN " . DB_PREFIX . "game_sets gs ON c.game_set_id = gs.id
        LEFT JOIN " . DB_PREFIX . "types t ON c.main_type_id = t.id
        WHERE 1=1";

if ($filter_game_set > 0) {
    $sql .= " AND c.game_set_id = " . $filter_game_set;
}
if (!empty($filter_card_type)) {
    $sql .= " AND c.card_type = " . $pdo->quote($filter_card_type);
}
if ($filter_main_type > 0) {
    $sql .= " AND c.main_type_id = " . $filter_main_type;
}

// Gestion de l'ordre
if ($sort_order === 'alpha') {
    $sql .= " ORDER BY c.name ASC";
} else {
    // Par défaut: type puis alphabétique
    $sql .= " ORDER BY t.display_order ASC, c.name ASC";
}

$stmt = $pdo->query($sql);
$cards = $stmt->fetchAll();

// Récupérer tous les jeux
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "game_sets ORDER BY display_order ASC");
$game_sets = $stmt->fetchAll();

// Récupérer tous les types
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "types ORDER BY display_order ASC");
$types = $stmt->fetchAll();

// Configuration du layout
$page_title = 'Gestion des Cartes';
$page_description = 'Base de données des cartes du jeu';
$page_header_button = '<button onclick="openModal()" class="submit-button" style="margin-top:0;">+ Ajouter une nouvelle carte</button>';

// Styles spécifiques à cette page
$extra_styles = '<style>
    .card {
        background:white;
        padding:25px;
        border-radius:12px;
        box-shadow:0 2px 4px rgba(0,0,0,.1);
        margin-bottom:25px;
    }
    .card h2 {
        margin-top:0;
        color:#333;
    }
    .form-table {
        width:100%;
    }
    .form-table th {
        text-align:left;
        padding:15px 10px 15px 0;
        width:180px;
        font-weight:600;
        vertical-align:top;
    }
    .form-table td {
        padding:15px 0;
    }
    .regular-text {
        width:100%;
        max-width:400px;
        padding:10px;
        border:1px solid #ddd;
        border-radius:6px;
        font-size:14px;
    }
    .large-text {
        width:100%;
        max-width:600px;
        padding:10px;
        border:1px solid #ddd;
        border-radius:6px;
        font-size:14px;
        min-height:80px;
    }
    .submit-button {
        background:#1a1a1a;
        color:#fff;
        padding:12px 30px;
        border:none;
        border-radius:8px;
        font-weight:600;
        cursor:pointer;
        font-size:15px;
        transition: all .3s;
    }
    .submit-button:hover {
        background:#2a2a2a;
    }
    .btn-delete {
        background:#dc3545;
        color:#fff;
        padding:6px 12px;
        border:none;
        border-radius:6px;
        cursor:pointer;
        font-size:13px;
    }
    .btn-delete:hover {
        background:#c82333;
    }
    .message {
        padding:15px;
        border-radius:8px;
        margin-bottom:20px;
    }
    .message.success {
        background:#d4edda;
        color:#155724;
        border:1px solid #c3e6cb;
    }
    .message.error {
        background:#f8d7da;
        color:#721c24;
        border:1px solid #f5c6cb;
    }
    .type-quantities {
        display:grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap:15px;
        margin-top:10px;
    }
    .type-quantity-item {
        padding:10px;
        border:1px solid #ddd;
        border-radius:6px;
        background:#f9f9f9;
    }
    .type-quantity-item label {
        display:block;
        font-weight:600;
        margin-bottom:5px;
        font-size:13px;
    }
    .type-quantity-item input {
        width:60px;
        padding:6px;
        border:1px solid #ddd;
        border-radius:4px;
        text-align:center;
    }
    .type-quantity-item img {
        width:30px;
        height:30px;
        object-fit:contain;
        margin-right:8px;
        vertical-align:middle;
    }
    .card-type-selector {
        margin:15px 0;
    }
    .card-type-selector label {
        display:inline-block;
        margin-right:20px;
        cursor:pointer;
    }
    .card-type-selector input[type="radio"] {
        margin-right:5px;
    }
    .cards-grid {
        display:grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap:25px;
        margin-top:20px;
    }
    .card-item {
        border:1px solid #ddd;
        border-radius:8px;
        padding:15px;
        background:#fff;
        position:relative;
    }
    .card-item .card-image-container {
        width:100%;
        aspect-ratio: 63/88;
        overflow:hidden;
        border-radius:6px;
        margin-bottom:10px;
        background:#f5f5f5;
    }
    .card-item img {
        width:100%;
        height:100%;
        object-fit:contain;
    }
    .card-item h3 {
        margin:10px 0 5px 0;
        font-size:16px;
    }
    .card-item .badge {
        display:inline-block;
        padding:3px 8px;
        border-radius:10px;
        font-size:11px;
        font-weight:600;
        color:#fff;
        margin-right:5px;
    }
    .card-item .badge.monster {
        background:#28a745;
    }
    .card-item .badge.dirty-trick {
        background:#fd7e14;
    }
    .card-item .power-text {
        font-size:12px;
        color:#666;
        margin:8px 0;
        font-style:italic;
    }
    .card-item .types-info {
        display:flex;
        gap:5px;
        flex-wrap:wrap;
        margin-top:8px;
    }
    .card-item .type-badge {
        display:inline-flex;
        align-items:center;
        padding:4px 8px;
        background:#f0f0f0;
        border-radius:12px;
        font-size:11px;
    }
    .card-item .type-badge img {
        width:16px;
        height:16px;
        margin-right:4px;
    }
    .filters {
        background:#f8f9fa;
        padding:15px;
        border-radius:8px;
        margin-bottom:20px;
        display:flex;
        gap:15px;
        align-items:center;
        flex-wrap:wrap;
    }
    select {
        padding:8px 12px;
        border:1px solid #ddd;
        border-radius:6px;
        font-size:14px;
    }

    /* Modal styles */
    .modal {
        display:none;
        position:fixed;
        z-index:1000;
        left:0;
        top:0;
        width:100%;
        height:100%;
        overflow:auto;
        background-color:rgba(0,0,0,0.5);
    }
    .modal.active {
        display:block;
    }
    .modal-content {
        background-color:#fff;
        margin:2% auto;
        padding:0;
        border-radius:12px;
        width:90%;
        max-width:800px;
        max-height:90vh;
        display:flex;
        flex-direction:column;
        box-shadow:0 4px 20px rgba(0,0,0,0.3);
    }
    .modal-header {
        padding:20px 25px;
        background:#2c3e50;
        color:white;
        border-radius:12px 12px 0 0;
        display:flex;
        justify-content:space-between;
        align-items:center;
        position:sticky;
        top:0;
        z-index:10;
        box-shadow:0 2px 8px rgba(0,0,0,0.1);
    }
    .modal-header h2 {
        margin:0;
        font-size:22px;
    }
    .modal-close {
        color:white;
        font-size:32px;
        font-weight:bold;
        cursor:pointer;
        background:none;
        border:none;
        padding:0;
        line-height:1;
    }
    .modal-close:hover {
        opacity:0.8;
    }
    .modal-body {
        padding:25px;
        overflow-y:auto;
        flex:1;
    }
    .modal-footer {
        padding:20px 25px;
        background:#f8f9fa;
        border-top:1px solid #dee2e6;
        position:sticky;
        bottom:0;
        z-index:10;
        border-radius:0 0 12px 12px;
    }
</style>';

// Inclure le layout
require_once __DIR__ . '/includes/admin-layout.php';
?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Modal pour le formulaire d'ajout/édition -->
<div id="cardModal" class="modal <?php echo $edit_card ? 'active' : ''; ?>">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo $edit_card ? 'Modifier la carte' : 'Ajouter une nouvelle carte'; ?></h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php if ($edit_card): ?>
                <p style="color:#667eea; margin-bottom:15px; font-weight:600;">
                    Mode édition - <a href="cards.php" style="color:#dc3545;">Annuler et revenir</a>
                </p>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" id="card-form">
                <input type="hidden" name="action" value="<?php echo $edit_card ? 'edit_card' : 'add_card'; ?>">
                <?php if ($edit_card): ?>
                    <input type="hidden" name="card_id" value="<?php echo $edit_card['id']; ?>">
                    <input type="hidden" name="existing_image_url" value="<?php echo htmlspecialchars($edit_card['image_url']); ?>">
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th>Nom de la carte</th>
                        <td>
                            <input type="text" name="card_name" required class="regular-text"
                                   value="<?php echo $edit_card ? htmlspecialchars($edit_card['name']) : ''; ?>"
                                   placeholder="Ex: Boss des nonos">
                        </td>
                    </tr>
                    <tr>
                        <th>Type de carte</th>
                        <td>
                            <div class="card-type-selector">
                                <label>
                                    <input type="radio" name="card_type" value="monster"
                                           <?php echo (!$edit_card || $edit_card['card_type'] === 'monster') ? 'checked' : ''; ?>
                                           onchange="toggleTypeQuantities()">
                                    Monstre
                                </label>
                                <label>
                                    <input type="radio" name="card_type" value="dirty_trick"
                                           <?php echo ($edit_card && $edit_card['card_type'] === 'dirty_trick') ? 'checked' : ''; ?>
                                           onchange="toggleTypeQuantities()">
                                    Coup bas
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Jeu/Extension</th>
                        <td>
                            <select name="game_set_id" required class="regular-text">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($game_sets as $game_set): ?>
                                    <option value="<?php echo $game_set['id']; ?>"
                                            <?php echo ($edit_card && $edit_card['game_set_id'] == $game_set['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($game_set['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Nombre d'exemplaires</th>
                        <td>
                            <input type="number" name="quantity" required class="regular-text" min="1" max="20"
                                   value="<?php echo $edit_card ? (int)$edit_card['quantity'] : '1'; ?>"
                                   style="max-width:100px;">
                            <small style="display:block; margin-top:5px; color:#666;">
                                Nombre d'exemplaires de cette carte dans le deck (généralement 1 à 5)
                            </small>
                        </td>
                    </tr>
                    <tr>
                        <th>Type principal</th>
                        <td>
                            <select name="main_type_id" class="regular-text">
                                <option value="">-- Aucun type principal --</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>"
                                            <?php echo ($edit_card && $edit_card['main_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display:block; margin-top:5px; color:#666;">
                                Le type principal de la carte (ex: Psychocourge = Citrouille)
                            </small>
                        </td>
                    </tr>
                    <tr>
                        <th>Pouvoir "Oeil"</th>
                        <td>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="checkbox" name="has_eye" value="1"
                                       <?php echo ($edit_card && $edit_card['has_eye'] == 1) ? 'checked' : ''; ?>
                                       style="width:20px; height:20px; cursor:pointer;">
                                <span style="font-weight:600;">Cette carte a le pouvoir "Oeil"</span>
                            </label>
                            <small style="display:block; margin-top:5px; color:#666;">
                                Permet de voir l'objectif d'un autre joueur quand on joue cette carte
                            </small>
                        </td>
                    </tr>
                    <tr>
                        <th>Image de la carte</th>
                        <td>
                            <?php if ($edit_card && !empty($edit_card['image_url'])): ?>
                                <div style="margin-bottom:10px;">
                                    <img src="<?php echo htmlspecialchars($edit_card['image_url']); ?>" style="max-width:150px; border-radius:6px; border:2px solid #ddd;">
                                    <p style="color:#666; font-size:12px; margin:5px 0;">Image actuelle - Uploadez une nouvelle image pour la remplacer</p>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="card_image" accept="image/*" class="regular-text">
                            <small style="display:block; margin-top:5px; color:#666;">
                                Formats acceptés: JPG, PNG, GIF, WEBP <?php echo $edit_card ? '(optionnel - laissez vide pour garder l\'actuelle)' : '(optionnel)'; ?>
                            </small>
                        </td>
                    </tr>
                    <tr>
                        <th>Pouvoir / Texte</th>
                        <td>
                            <textarea name="power_text" class="large-text" placeholder="La magie n'a aucun effet sur moi."><?php echo $edit_card ? htmlspecialchars($edit_card['power_text']) : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Visibilité</th>
                        <td>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="checkbox" name="is_visible" value="1"
                                       <?php echo (!$edit_card || !isset($edit_card['is_visible']) || $edit_card['is_visible'] == 1) ? 'checked' : ''; ?>
                                       style="width:20px; height:20px; cursor:pointer;">
                                <span style="font-weight:600;">Carte visible en frontend</span>
                            </label>
                            <small style="display:block; margin-top:5px; color:#666;">
                                Décochez pour cacher cette carte en frontend (utile pour préparer une extension sans la dévoiler)
                            </small>
                        </td>
                    </tr>
                    <tr id="type-quantities-row">
                        <th>Quantités par type</th>
                        <td>
                            <div class="type-quantities">
                                <?php foreach ($types as $type): ?>
                                <div class="type-quantity-item">
                                    <label>
                                        <?php if (!empty($type['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($type['image_url']); ?>" alt="">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </label>
                                    <input type="number" name="type_quantities[<?php echo $type['id']; ?>]" min="0" max="5"
                                           value="<?php echo isset($edit_card_types[$type['id']]) ? $edit_card_types[$type['id']] : '0'; ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <small style="display:block; margin-top:10px; color:#666;">Max 5 emplacements de types sur une carte</small>
                        </td>
                    </tr>
                </table>
        </div>
        <div class="modal-footer">
            <button type="submit" class="submit-button">
                <?php echo $edit_card ? 'Modifier la carte' : 'Ajouter la carte'; ?>
            </button>
        </div>
            </form>
    </div>
</div>

<!-- Filtres -->
<div class="card">
    <h2>Filtrer les cartes</h2>
    <form method="get" class="filters">
        <div>
            <label>Jeu/Extension:</label>
            <select name="game_set" onchange="this.form.submit()">
                <option value="0">Tous les jeux</option>
                <?php foreach ($game_sets as $game_set): ?>
                    <option value="<?php echo $game_set['id']; ?>" <?php echo $filter_game_set == $game_set['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($game_set['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Type de carte:</label>
            <select name="card_type" onchange="this.form.submit()">
                <option value="">Tous les types</option>
                <option value="monster" <?php echo $filter_card_type === 'monster' ? 'selected' : ''; ?>>Monstres</option>
                <option value="dirty_trick" <?php echo $filter_card_type === 'dirty_trick' ? 'selected' : ''; ?>>Coups bas</option>
            </select>
        </div>
        <div>
            <label>Type principal:</label>
            <select name="main_type" onchange="this.form.submit()">
                <option value="0">Tous les types principaux</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?php echo $type['id']; ?>" <?php echo $filter_main_type == $type['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="cards.php" style="padding:8px 15px; background:#6c757d; color:#fff; text-decoration:none; border-radius:6px; font-size:14px;">Réinitialiser</a>
    </form>
</div>

<!-- Liste des cartes -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">Cartes (<?php echo count($cards); ?>)</h2>
        <form method="get" style="display:flex; align-items:center; gap:10px;">
            <input type="hidden" name="game_set" value="<?php echo $filter_game_set; ?>">
            <input type="hidden" name="card_type" value="<?php echo $filter_card_type; ?>">
            <input type="hidden" name="main_type" value="<?php echo $filter_main_type; ?>">
            <label style="font-weight:600; font-size:14px;">Ordre:</label>
            <select name="sort" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px;">
                <option value="type_alpha" <?php echo $sort_order === 'type_alpha' ? 'selected' : ''; ?>>Type puis Alphabétique</option>
                <option value="alpha" <?php echo $sort_order === 'alpha' ? 'selected' : ''; ?>>Alphabétique</option>
            </select>
        </form>
    </div>
    <?php if (empty($cards)): ?>
        <p style="color:#666;">Aucune carte trouvée.</p>
    <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($cards as $card):
                // Récupérer les types de la carte
                $stmt = $pdo->prepare("SELECT ct.*, t.name as type_name, t.image_url as type_image
                                      FROM " . DB_PREFIX . "card_types ct
                                      LEFT JOIN " . DB_PREFIX . "types t ON ct.type_id = t.id
                                      WHERE ct.card_id = ?");
                $stmt->execute([$card['id']]);
                $card_types = $stmt->fetchAll();
            ?>
            <div class="card-item">
                <div class="card-image-container">
                    <?php if (!empty($card['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>">
                    <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#999;">
                            Pas d'image
                        </div>
                    <?php endif; ?>
                </div>

                <h3><?php echo htmlspecialchars($card['name']); ?></h3>

                <div style="display:flex; gap:5px; flex-wrap:wrap; align-items:center; margin-bottom:5px;">
                    <span class="badge <?php echo $card['card_type']; ?>">
                        <?php echo $card['card_type'] === 'monster' ? 'Monstre' : 'Coup bas'; ?>
                    </span>
                    <?php if (isset($card['is_visible']) && $card['is_visible'] == 0): ?>
                        <span class="badge" style="background:#6c757d;">Cachée</span>
                    <?php endif; ?>
                    <?php if (isset($card['has_eye']) && $card['has_eye'] == 1): ?>
                        <span class="badge" style="background:#9b59b6;">Oeil</span>
                    <?php endif; ?>
                    <?php
                        $qty = isset($card['quantity']) ? (int)$card['quantity'] : 1;
                        $qty_label = $qty > 1 ? 'exemplaires' : 'exemplaire';
                    ?>
                    <span class="badge" style="background:#17a2b8;">×<?php echo $qty; ?> <?php echo $qty_label; ?></span>
                </div>
                <div style="display:flex; gap:5px; align-items:center; margin-bottom:8px;">
                    <small style="color:#666;"><?php echo htmlspecialchars($card['game_set_name']); ?></small>
                    <?php if (!empty($card['main_type_name'])): ?>
                        <span style="color:#667eea; font-size:11px; font-weight:600;">• <?php echo htmlspecialchars($card['main_type_name']); ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($card['power_text'])): ?>
                    <div class="power-text"><?php echo htmlspecialchars($card['power_text']); ?></div>
                <?php endif; ?>

                <?php if ($card['card_type'] === 'monster' && !empty($card_types)): ?>
                    <div class="types-info">
                        <?php foreach ($card_types as $ct): ?>
                            <?php if ($ct['type_id'] !== null): ?>
                                <span class="type-badge">
                                    <?php if (!empty($ct['type_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($ct['type_image']); ?>" alt="">
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($ct['type_name'] ?? 'Type #' . $ct['type_id']); ?>: <?php echo $ct['quantity']; ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top:10px; display:flex; gap:8px;">
                    <a href="cards.php?edit=<?php echo $card['id']; ?>" style="flex:1; padding:6px 12px; background:#667eea; color:#fff; text-align:center; border-radius:6px; text-decoration:none; font-size:13px; font-weight:600; display:inline-block; box-sizing:border-box;">
                        Modifier
                    </a>
                    <form method="post" style="flex:1; margin:0;" onsubmit="return confirm('Supprimer cette carte ?');">
                        <input type="hidden" name="action" value="delete_card">
                        <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                        <button type="submit" style="width:100%; padding:6px 12px; background:#dc3545; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; box-sizing:border-box;">
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function openModal() {
    document.getElementById('cardModal').classList.add('active');
}

function closeModal() {
    document.getElementById('cardModal').classList.remove('active');
}

// Fermer la modale si on clique en dehors
window.onclick = function(event) {
    const modal = document.getElementById('cardModal');
    if (event.target === modal) {
        closeModal();
    }
}

function toggleTypeQuantities() {
    const cardType = document.querySelector('input[name="card_type"]:checked').value;
    const typeQuantitiesRow = document.getElementById('type-quantities-row');

    if (cardType === 'monster') {
        typeQuantitiesRow.style.display = 'table-row';
    } else {
        typeQuantitiesRow.style.display = 'none';
    }
}

// Au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    toggleTypeQuantities();
});
</script>

<?php
// Inclure la fin du layout
require_once __DIR__ . '/includes/admin-layout-end.php';
?>
