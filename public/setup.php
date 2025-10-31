<?php
// Minimal installer runner accessible via /setup.php
// Purpose: create required wp_objectif_* tables on production, then self-delete.

header('Content-Type: text/html; charset=UTF-8');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/install.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Erreur</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

$shouldRun = (isset($_GET['run']) && $_GET['run'] === 'install');

?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installation - Gang de Monstres</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif; background:#f7f8fa; margin:0; padding:40px; }
    .card { max-width:720px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:24px; }
    .btn { display:inline-block; padding:12px 18px; background:#667eea; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; }
    pre { background:#1f2937; color:#9ae6b4; padding:12px; border-radius:8px; overflow:auto; }
    .ok { background:#d4edda; color:#155724; padding:12px; border-radius:8px; border:1px solid #c3e6cb; }
    .warn { background:#fff3cd; color:#856404; padding:12px; border-radius:8px; border:1px solid #ffeeba; }
  </style>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta http-equiv="Content-Language" content="fr" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="robots" content="noindex,nofollow" />
  <meta name="referrer" content="no-referrer" />
</head>
<body>
  <div class="card">
    <h1>Installation</h1>
    <?php if (!$shouldRun): ?>
      <p>Cliquez pour créer les tables nécessaires (wp_objectif_*). Cette page va s'auto-supprimer après succès.</p>
      <p><a class="btn" href="?run=install">Lancer l'installation</a></p>
      <p class="warn">Après installation, reconnectez-vous sur l'admin et supprimez le cache du navigateur si besoin.</p>
    <?php else: ?>
      <div class="ok">Démarrage de l'installation…</div>
      <pre><?php
        ob_start();
        try {
            install_database();
            $out = ob_get_clean();
            echo htmlspecialchars($out ?: 'Installation terminée.');
            // Tentative d'auto-suppression
            @unlink(__FILE__);
            echo "\n\nCette page a été supprimée automatiquement.";
        } catch (Throwable $e) {
            $out = ob_get_clean();
            echo htmlspecialchars($out);
            echo "\n\nErreur: " . htmlspecialchars($e->getMessage());
        }
      ?></pre>
      <p><a class="btn" href="/admin/login.php">Aller à l'admin</a></p>
    <?php endif; ?>
  </div>
</body>
</html>

