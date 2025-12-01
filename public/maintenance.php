<?php
/**
 * Page de maintenance publique
 * Affichée quand le mode maintenance est activé
 */

require_once __DIR__ . '/../config/database.php';

// Fonctions pour récupérer les settings
function get_setting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Récupérer les paramètres de maintenance
$maintenance_title = get_setting($pdo, 'maintenance_title', 'Site en maintenance');
$maintenance_text = get_setting($pdo, 'maintenance_text', 'Nous effectuons actuellement des travaux de maintenance. Merci de revenir plus tard.');
$maintenance_image = get_setting($pdo, 'maintenance_image', '');
$maintenance_button_text = get_setting($pdo, 'maintenance_button_text', '');
$maintenance_button_url = get_setting($pdo, 'maintenance_button_url', '');
$site_name = get_setting($pdo, 'site_name', 'Gang de Monstres');
$site_logo = get_setting($pdo, 'site_logo', '');

// Envoyer le header HTTP 503 Service Unavailable
http_response_code(503);
header('Retry-After: 3600'); // Suggérer de réessayer dans 1 heure
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($maintenance_title); ?> - <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            padding: 20px;
        }

        .maintenance-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .site-logo {
            max-width: 200px;
            max-height: 80px;
            object-fit: contain;
            margin-bottom: 30px;
        }

        .maintenance-image {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            margin-bottom: 30px;
            border-radius: 12px;
        }

        .maintenance-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .maintenance-text {
            font-size: 16px;
            color: #666;
            line-height: 1.7;
            margin-bottom: 30px;
        }

        .maintenance-button {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .maintenance-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 63, 83, 0.3);
        }

        .footer-text {
            margin-top: 40px;
            font-size: 13px;
            color: #999;
        }

        @media (max-width: 480px) {
            .maintenance-container {
                padding: 40px 25px;
            }

            h1 {
                font-size: 24px;
            }

            .maintenance-text {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <?php if ($site_logo): ?>
            <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?>" class="site-logo">
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($maintenance_title); ?></h1>

        <?php if ($maintenance_image): ?>
            <img src="<?php echo htmlspecialchars($maintenance_image); ?>" alt="" class="maintenance-image">
        <?php else: ?>
            <div class="maintenance-icon">🔧</div>
        <?php endif; ?>

        <p class="maintenance-text">
            <?php echo nl2br(strip_tags($maintenance_text, '<b><strong>')); ?>
        </p>

        <?php if ($maintenance_button_text && $maintenance_button_url): ?>
            <a href="<?php echo htmlspecialchars($maintenance_button_url); ?>" class="maintenance-button">
                <?php echo htmlspecialchars($maintenance_button_text); ?>
            </a>
        <?php endif; ?>

        <p class="footer-text">
            <?php echo htmlspecialchars($site_name); ?>
        </p>
    </div>
</body>
</html>
