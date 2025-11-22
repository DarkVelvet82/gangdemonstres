<?php
/**
 * Header Front avec logo dynamique
 * Usage: require_once __DIR__ . '/../includes/front-header.php';
 */

require_once __DIR__ . '/../config/database.php';

// Récupérer les paramètres du site
function get_front_setting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$site_logo = get_front_setting($pdo, 'site_logo', '');
$site_name = get_front_setting($pdo, 'site_name', 'Gang de Monstres');

// Construire le chemin du logo pour les pages front (qui sont dans /public/)
$logo_path = '';
if ($site_logo) {
    // Le logo est stocké avec un chemin relatif depuis /admin/ (ex: ../assets/images/logo.png)
    // Depuis /public/, on doit ajuster le chemin
    $logo_path = str_replace('../', '../', $site_logo);
}
?>
