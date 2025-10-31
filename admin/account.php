<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Fetch current admin
$stmt = $pdo->prepare('SELECT * FROM ' . DB_PREFIX . 'users WHERE id = ? AND is_admin = 1');
$stmt->execute([$_SESSION['admin_user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    $error = "Utilisateur admin introuvable";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        $error = 'Nonce invalide';
    } else {
        $current_password = (string) get_post_value('current_password', '');
        $new_username = trim((string) get_post_value('new_username', ''));
        $new_password = (string) get_post_value('new_password', '');
        $confirm_password = (string) get_post_value('confirm_password', '');

        if (empty($current_password)) {
            $error = 'Mot de passe actuel requis';
        } elseif (!$currentUser || !password_verify($current_password, $currentUser['password'])) {
            $error = 'Mot de passe actuel incorrect';
        } else {
            // Build update query dynamically
            $updates = [];
            $params = [];

            if ($new_username !== '' && $new_username !== $currentUser['username']) {
                // Ensure uniqueness
                $check = $pdo->prepare('SELECT COUNT(*) FROM ' . DB_PREFIX . 'users WHERE username = ? AND id <> ?');
                $check->execute([$new_username, $currentUser['id']]);
                if ($check->fetchColumn() > 0) {
                    $error = "Nom d'utilisateur déjà pris";
                } else {
                    $updates[] = 'username = ?';
                    $params[] = $new_username;
                }
            }

            if (empty($error) && $new_password !== '') {
                if (strlen($new_password) < 6) {
                    $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'La confirmation ne correspond pas';
                } else {
                    $hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $updates[] = 'password = ?';
                    $params[] = $hash;
                }
            }

            if (empty($error) && !empty($updates)) {
                $params[] = $currentUser['id'];
                $sql = 'UPDATE ' . DB_PREFIX . 'users SET ' . implode(', ', $updates) . ' WHERE id = ?';
                $upd = $pdo->prepare($sql);
                $upd->execute($params);

                if ($new_username !== '' && $new_username !== $currentUser['username']) {
                    $_SESSION['admin_username'] = $new_username;
                    $currentUser['username'] = $new_username;
                }
                if (!empty($hash)) {
                    $currentUser['password'] = $hash;
                }

                $message = 'Informations mises à jour avec succès';
            } elseif (empty($error)) {
                $message = "Aucune modification à enregistrer";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon compte - Administration</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <style>
        body { background: #f7f8fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif; }
        .container { max-width: 600px; margin: 40px auto; padding: 20px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,.1); padding: 24px; }
        .title { margin: 0 0 6px 0; }
        .muted { color: #666; margin: 0 0 16px 0; }
        .form-group { margin-bottom: 16px; }
        label { display:block; font-weight:600; margin-bottom:6px; }
        input { width:100%; padding:12px; border:2px solid #e1e4e8; border-radius:8px; font-size: 1em; }
        input:focus { outline:none; border-color:#667eea; }
        .actions { display:flex; gap:10px; align-items:center; margin-top: 12px; }
        .btn { padding: 12px 18px; border:none; border-radius:8px; font-weight:600; cursor:pointer; }
        .btn-primary { background:#667eea; color:#fff; }
        .btn-secondary { background:#f7f8fa; border:2px solid #e1e4e8; }
        .alert { padding:12px; border-radius:8px; margin-bottom:12px; }
        .alert-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .alert-ok { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; }
        a.link { text-decoration:none; color:#667eea; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="topbar">
                <div>
                    <h2 class="title">Mon compte</h2>
                    <p class="muted">Modifier votre identifiant et/ou mot de passe</p>
                </div>
                <div>
                    <a class="link" href="index.php">← Retour au dashboard</a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($message): ?>
                <div class="alert alert-ok"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="nonce" value="<?php echo htmlspecialchars(create_nonce()); ?>">

                <div class="form-group">
                    <label for="current_password">Mot de passe actuel</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_username">Nouvel identifiant (optionnel)</label>
                    <input type="text" id="new_username" name="new_username" value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe (optionnel)</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Laisser vide pour ne pas changer">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Répétez le mot de passe">
                </div>

                <div class="actions">
                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                    <a class="btn btn-secondary" href="index.php">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

