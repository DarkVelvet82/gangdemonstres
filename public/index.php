<?php
require_once __DIR__ . '/../includes/front-header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_name); ?> - Objectifs Multijoueur</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <style>
        .home-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3), inset 0 0 30px rgba(0,0,0,0.15);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
            border: 3px solid #eddeb6;
        }

        .site-logo {
            max-width: 280px;
            max-height: 100px;
            margin-bottom: 20px;
        }

        .home-title {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .home-subtitle {
            color: #666;
            font-size: 1.1em;
            margin: 0 0 40px 0;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
        }

        .action-btn {
            display: block;
            padding: 20px;
            font-size: 1.1em;
            font-weight: 600;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 63, 83, 0.4);
        }

        .btn-secondary {
            background: #f7f8fa;
            color: #333;
            border: 2px solid #e1e4e8;
        }

        .btn-secondary:hover {
            background: #e1e4e8;
        }

        .admin-link {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e4e8;
        }

        .admin-link a {
            color: #666;
            text-decoration: none;
            font-size: 0.9em;
        }

        .admin-link a:hover {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="home-container">
        <?php if ($logo_path && file_exists(__DIR__ . '/' . $logo_path)): ?>
            <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="<?php echo htmlspecialchars($site_name); ?>" class="site-logo">
        <?php else: ?>
            <h1 class="home-title">🎮 <?php echo htmlspecialchars($site_name); ?></h1>
        <?php endif; ?>

        <p>Bienvenue dans le générateur d'objectifs pour <?php echo htmlspecialchars($site_name); ?>! Créez une partie ou rejoignez-en une existante.</p>

        <div class="action-buttons">
            <a href="creer-partie.php" class="action-btn btn-primary">
                Créer une nouvelle partie
            </a>

            <a href="rejoindre.php" class="action-btn btn-secondary">
                Rejoindre une partie existante
            </a>

            <a href="compte.php" class="action-btn btn-secondary">
                Mon compte / créer un compte
            </a>

            <a href="scores.php" class="action-btn btn-secondary">
                Voir les scores
            </a>
        </div>

        <div class="admin-link">
            <a href="../admin/">Administration</a>
        </div>
    </div>
</body>
</html>
