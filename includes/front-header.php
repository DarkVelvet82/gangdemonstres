<?php
/**
 * Header Front avec logo dynamique
 * Usage: require_once __DIR__ . '/../includes/front-header.php';
 */

require_once __DIR__ . '/../config/database.php';

// Détection mobile - Rediriger les desktop vers une page d'info
function is_mobile_device() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobile_agents = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'webOS',
        'BlackBerry', 'Opera Mini', 'IEMobile', 'Windows Phone'
    ];
    foreach ($mobile_agents as $agent) {
        if (stripos($user_agent, $agent) !== false) {
            return true;
        }
    }
    return false;
}

// Rediriger les non-mobiles (sauf si on est déjà sur la page mobile-only)
// TEMPORAIREMENT DÉSACTIVÉ pour test PC
/*
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
if (!is_mobile_device() && $current_page !== 'mobile-only.php') {
    header('Location: mobile-only.php');
    exit;
}
*/

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
    // Table existe déjà ou erreur ignorée
}

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

// Vérifier le mode maintenance (sauf pour les pages admin et en local)
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$current_path = $_SERVER['PHP_SELF'] ?? '';
$is_admin_page = strpos($current_path, '/admin/') !== false;
$is_local = (strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false);
$maintenance_enabled = get_front_setting($pdo, 'maintenance_enabled', '0') === '1';

if ($maintenance_enabled && $current_page !== 'maintenance.php' && !$is_admin_page && !$is_local) {
    header('Location: maintenance.php');
    exit;
}

$site_logo = get_front_setting($pdo, 'site_logo', '');
$site_name = get_front_setting($pdo, 'site_name', 'Gang de Monstres');

// Construire le chemin du logo pour les pages front (qui sont dans /public/)
$logo_path = '';
if ($site_logo) {
    // Le logo est stocké avec un chemin relatif depuis /admin/ (ex: ../assets/images/logo.png)
    // Depuis /public/, les chemins sont identiques
    $logo_path = $site_logo;
}

/**
 * Génère le HTML du header avec logo et titre
 * @param string $title Le titre de la page (affiché à côté du logo ou seul)
 * @param string|null $back_url URL du bouton retour (null = pas de bouton)
 * @param bool $show_logo Afficher le logo (true par défaut)
 * @return string HTML du header
 */
function render_page_header($title, $back_url = null, $show_logo = true) {
    global $logo_path, $site_name;

    $html = '<div class="header">';

    // Bouton retour
    if ($back_url !== null) {
        $html .= '<a href="' . htmlspecialchars($back_url) . '" class="back-arrow">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="48" d="M244 400L100 256l144-144M120 256h292"/></svg>';
        $html .= '</a>';
    }

    // Logo + Titre
    // Le logo_path est stocké relatif depuis /admin/ (ex: ../assets/images/logo.png)
    // Depuis /includes/, on va vers le dossier parent puis le chemin relatif
    $logo_file = __DIR__ . '/../admin/' . $logo_path;
    if ($show_logo && $logo_path && file_exists($logo_file)) {
        $html .= '<div class="header-with-logo">';
        $html .= '<img src="' . htmlspecialchars($logo_path) . '" alt="' . htmlspecialchars($site_name) . '" class="header-logo">';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '</div>';
    } else {
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
    }

    $html .= '</div>';

    return $html;
}
?>
