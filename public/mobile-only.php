<?php
/**
 * Page affichée aux utilisateurs desktop
 * L'application est réservée aux mobiles
 */
require_once __DIR__ . '/../config/database.php';

// Récupérer le logo
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

$site_logo = get_setting($pdo, 'site_logo', '');
$site_name = get_setting($pdo, 'site_name', 'Gang de Monstres');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gang de Monstres - Application Mobile</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .message-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3), inset 0 0 30px rgba(0,0,0,0.15);
            max-width: 500px;
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
        h1 {
            color: #003f53;
            font-size: 26px;
            margin: 0 0 20px 0;
        }
        p {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
            margin: 0 0 20px 0;
        }
        .highlight {
            color: #003f53;
            font-weight: 600;
        }
        .qr-section {
            margin: 25px 0;
        }
        .qr-section img {
            width: 180px;
            height: 180px;
        }
        .qr-hint {
            background: #f7f8fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #e1e4e8;
        }
        .qr-hint p {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <?php if ($site_logo && file_exists(__DIR__ . '/' . $site_logo)): ?>
            <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?>" class="site-logo">
        <?php else: ?>
            <h1><?php echo htmlspecialchars($site_name); ?></h1>
        <?php endif; ?>

        <h1>Application Mobile Uniquement</h1>
        <p>
            <span class="highlight">Gang de Monstres</span> est une application
            conçue pour être utilisée sur <strong>smartphone</strong> pendant vos parties de jeu de société.
        </p>
        <p>
            Scannez le QR code ou ouvrez ce lien sur votre téléphone pour accéder à l'application.
        </p>
        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=https://gangdemonstres.com" alt="QR Code vers Gang de Monstres">
        </div>
        <div class="qr-hint">
            <p>Chaque joueur utilise son propre téléphone pour recevoir ses objectifs secrets !</p>
        </div>
    </div>
</body>
</html>
