<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: text/html; charset=UTF-8');

// Traitement suppression en masse
$delete_message = '';
$delete_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_users'])) {
    $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : [];

    if (!empty($user_ids)) {
        try {
            // Supprimer les joueurs fréquents associés
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "user_players WHERE user_id IN ($placeholders)");
            $stmt->execute($user_ids);

            // Supprimer les utilisateurs
            $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "users WHERE id IN ($placeholders)");
            $stmt->execute($user_ids);

            $delete_message = count($user_ids) . ' utilisateur(s) supprimé(s)';
        } catch (Exception $e) {
            $delete_error = 'Erreur lors de la suppression';
        }
    }
}

// Compter le total (avant filtre)
$total_users = (int) $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "users")->fetchColumn();

$page_title = "Utilisateurs (" . $total_users . ")";
$page_description = "Gestion des comptes utilisateurs";

// Filtre de recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fonction pour détecter les comptes suspects
function is_suspicious_user($user) {
    $reasons = [];

    // Email suspect (domaines jetables courants)
    $suspicious_domains = ['tempmail', 'throwaway', 'guerrillamail', 'mailinator', 'yopmail', '10minutemail', 'trashmail'];
    $email_domain = strtolower(substr(strrchr($user['email'], '@'), 1));
    foreach ($suspicious_domains as $domain) {
        if (strpos($email_domain, $domain) !== false) {
            $reasons[] = 'Email jetable';
            break;
        }
    }

    // Jamais connecté et pas de parties
    if (empty($user['last_login_at']) && $user['game_count'] == 0) {
        $reasons[] = 'Jamais actif';
    }

    // Prénom trop court ou bizarre
    if (strlen($user['prenom']) < 2) {
        $reasons[] = 'Prénom invalide';
    }

    // Prénom avec chiffres ou caractères spéciaux
    if (preg_match('/[0-9@#$%^&*()_+=\[\]{}|\\\\<>]/', $user['prenom'])) {
        $reasons[] = 'Prénom suspect';
    }

    return $reasons;
}

// Récupérer les utilisateurs avec leurs statistiques
$sql = "
    SELECT
        u.*,
        (SELECT COUNT(*) FROM " . DB_PREFIX . "user_players WHERE user_id = u.id) as player_count,
        (SELECT COUNT(*) FROM " . DB_PREFIX . "games WHERE user_id = u.id) as game_count
    FROM " . DB_PREFIX . "users u
";

if ($search !== '') {
    $sql .= " WHERE u.email LIKE :search1 OR u.prenom LIKE :search2 OR u.code_unique LIKE :search3";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($search !== '') {
    $search_param = '%' . $search . '%';
    $stmt->execute(['search1' => $search_param, 'search2' => $search_param, 'search3' => $search_param]);
} else {
    $stmt->execute();
}
$users = $stmt->fetchAll();

$extra_styles = '
<style>
    .users-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .users-table th {
        background: #1a1a1a;
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
    }
    .users-table td {
        padding: 15px;
        border-bottom: 1px solid #eee;
        font-size: 14px;
    }
    .users-table tr:last-child td {
        border-bottom: none;
    }
    .users-table tr:hover td {
        background: #f8f9fa;
    }
    .code-display {
        font-family: monospace;
        font-size: 16px;
        font-weight: 700;
        background: #f0f0ff;
        padding: 5px 10px;
        border-radius: 6px;
        color: #667eea;
        letter-spacing: 2px;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-info {
        background: #e3f2fd;
        color: #1976d2;
    }
    .badge-success {
        background: #e8f5e9;
        color: #388e3c;
    }
    .no-data {
        text-align: center;
        padding: 40px;
        color: #999;
        font-style: italic;
    }
    .copy-btn {
        background: #667eea;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        margin-left: 8px;
    }
    .copy-btn:hover {
        background: #764ba2;
    }
    .players-list {
        font-size: 13px;
        color: #666;
        max-width: 200px;
    }
    .players-list span {
        display: inline-block;
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 4px;
        margin: 2px;
    }
    .search-section {
        margin-bottom: 25px;
    }
    .search-form {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .search-input {
        flex: 1;
        max-width: 400px;
        padding: 12px 16px;
        border: 2px solid #e1e4e8;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s;
    }
    .search-input:focus {
        outline: none;
        border-color: #667eea;
    }
    .search-btn {
        padding: 12px 24px;
        background: #1a1a1a;
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .search-btn:hover {
        background: #333;
    }
    .reset-btn {
        padding: 12px 20px;
        background: #dc3545;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
    }
    .reset-btn:hover {
        background: #c82333;
    }
    .search-results-count {
        padding: 12px 16px;
        background: #e3f2fd;
        color: #1976d2;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
    }
    .checkbox-col {
        width: 40px;
        text-align: center;
    }
    .checkbox-col input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }
    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }
    .suspicion-col {
        max-width: 150px;
    }
    .suspicion-tag {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
        background: #fff3cd;
        color: #856404;
        margin: 1px;
    }
    .bulk-actions {
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .bulk-actions.hidden {
        display: none;
    }
    .selected-count {
        font-weight: 600;
        color: #333;
    }
    .btn-delete-bulk {
        padding: 10px 20px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-delete-bulk:hover {
        background: #c82333;
    }
    .btn-select-suspects {
        padding: 10px 20px;
        background: #ffc107;
        color: #333;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-select-suspects:hover {
        background: #e0a800;
    }
    .row-suspect {
        background: #fff8e1 !important;
    }
    .row-suspect:hover td {
        background: #fff3cd !important;
    }
</style>
';

require_once __DIR__ . '/includes/admin-layout.php';
?>

<?php if ($delete_message): ?>
    <div class="message success"><?php echo htmlspecialchars($delete_message); ?></div>
<?php endif; ?>
<?php if ($delete_error): ?>
    <div class="message error"><?php echo htmlspecialchars($delete_error); ?></div>
<?php endif; ?>

<div class="search-section">
    <form method="GET" action="" class="search-form">
        <input type="text" name="search" placeholder="Rechercher par email, prenom ou code..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
        <button type="submit" class="search-btn">Rechercher</button>
        <?php if ($search !== ''): ?>
            <a href="users.php" class="reset-btn">Reinitialiser</a>
            <span class="search-results-count"><?php echo count($users); ?> resultat(s)</span>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($users)): ?>
    <div class="card">
        <p class="no-data">Aucun utilisateur inscrit pour le moment.</p>
    </div>
<?php else: ?>
    <form method="POST" action="" id="bulk-delete-form">
        <div class="bulk-actions hidden" id="bulk-actions">
            <span class="selected-count"><span id="selected-count">0</span> sélectionné(s)</span>
            <button type="button" class="btn-select-suspects" onclick="selectAllSuspects()">Sélectionner les suspects</button>
            <button type="submit" name="delete_users" class="btn-delete-bulk" onclick="return confirm('Supprimer les utilisateurs sélectionnés ?')">Supprimer la sélection</button>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <table class="users-table">
                <thead>
                    <tr>
                        <th class="checkbox-col"><input type="checkbox" id="select-all" onclick="toggleSelectAll()"></th>
                        <th>ID</th>
                        <th>Prenom</th>
                        <th>Email</th>
                        <th>Code unique</th>
                        <th>Parties</th>
                        <th>Suspect</th>
                        <th>Inscription</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $suspicions = is_suspicious_user($user);
                        $is_suspect = !empty($suspicions);
                    ?>
                        <tr class="<?php echo $is_suspect ? 'row-suspect' : ''; ?>" data-suspect="<?php echo $is_suspect ? '1' : '0'; ?>">
                            <td class="checkbox-col">
                                <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="user-checkbox" onchange="updateBulkActions()">
                            </td>
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['prenom']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="code-display"><?php echo htmlspecialchars($user['code_unique']); ?></span>
                                <button type="button" class="copy-btn" onclick="copyCode('<?php echo htmlspecialchars($user['code_unique']); ?>')">Copier</button>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $user['game_count']; ?> parties</span>
                            </td>
                            <td class="suspicion-col">
                                <?php if ($is_suspect): ?>
                                    <?php foreach ($suspicions as $reason): ?>
                                        <span class="suspicion-tag"><?php echo htmlspecialchars($reason); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: #28a745;">OK</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
<?php endif; ?>

<script>
// Gestion de la selection
function updateBulkActions() {
    var checkboxes = document.querySelectorAll('.user-checkbox:checked');
    var count = checkboxes.length;
    var bulkActions = document.getElementById('bulk-actions');
    var countDisplay = document.getElementById('selected-count');

    countDisplay.textContent = count;

    if (count > 0) {
        bulkActions.classList.remove('hidden');
    } else {
        bulkActions.classList.add('hidden');
    }

    // Mettre a jour le checkbox "select all"
    var allCheckboxes = document.querySelectorAll('.user-checkbox');
    var selectAll = document.getElementById('select-all');
    selectAll.checked = (count === allCheckboxes.length && count > 0);
    selectAll.indeterminate = (count > 0 && count < allCheckboxes.length);
}

function toggleSelectAll() {
    var selectAll = document.getElementById('select-all');
    var checkboxes = document.querySelectorAll('.user-checkbox');

    checkboxes.forEach(function(cb) {
        cb.checked = selectAll.checked;
    });

    updateBulkActions();
}

function selectAllSuspects() {
    var rows = document.querySelectorAll('tr[data-suspect="1"]');

    rows.forEach(function(row) {
        var checkbox = row.querySelector('.user-checkbox');
        if (checkbox) {
            checkbox.checked = true;
        }
    });

    updateBulkActions();
}

// Fonctions de copie
function copyCode(code) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(function() {
            showCopySuccess(code);
        }).catch(function() {
            fallbackCopy(code);
        });
    } else {
        fallbackCopy(code);
    }
}

function fallbackCopy(code) {
    var textArea = document.createElement('textarea');
    textArea.value = code;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    textArea.style.top = '-9999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        var successful = document.execCommand('copy');
        if (successful) {
            showCopySuccess(code);
        } else {
            prompt('Copiez ce code :', code);
        }
    } catch (err) {
        prompt('Copiez ce code :', code);
    }

    document.body.removeChild(textArea);
}

function showCopySuccess(code) {
    var msg = document.createElement('div');
    msg.textContent = 'Code copie : ' + code;
    msg.style.cssText = 'position:fixed;top:20px;right:20px;background:#28a745;color:white;padding:15px 25px;border-radius:8px;z-index:9999;font-weight:600;box-shadow:0 4px 15px rgba(0,0,0,0.2);';
    document.body.appendChild(msg);
    setTimeout(function() {
        msg.remove();
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/includes/admin-layout-end.php'; ?>
