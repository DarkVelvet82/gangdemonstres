<?php
/**
 * API Router - Redirige les actions vers les bons endpoints
 * Compatible avec le format WordPress (action dans POST data)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Récupérer l'action depuis GET ou POST
$action = isset($_GET['action']) ? $_GET['action'] : '';
if (empty($action) && isset($_POST['action'])) {
    $action = $_POST['action'];
}

// Router les actions vers les bons fichiers/actions
switch ($action) {
    // Actions de game.php
    case 'objectif_create_game':
        $_GET['action'] = 'create';
        require_once __DIR__ . '/game.php';
        break;

    case 'objectif_join_game':
        $_GET['action'] = 'join';
        require_once __DIR__ . '/game.php';
        break;

    case 'objectif_check_game_status':
        $_GET['action'] = 'status';
        require_once __DIR__ . '/game.php';
        break;

    case 'objectif_get_game_players':
        $_GET['action'] = 'players';
        require_once __DIR__ . '/game.php';
        break;

    case 'objectif_restart_game':
        $_GET['action'] = 'restart';
        require_once __DIR__ . '/game.php';
        break;

    case 'objectif_cancel_game':
        $_GET['action'] = 'cancel';
        require_once __DIR__ . '/game.php';
        break;

    // Actions de player.php
    case 'objectif_get_objective':
        $_GET['action'] = 'check';
        require_once __DIR__ . '/player.php';
        break;

    case 'objectif_generate':
    case 'objectif_generate_objective':
        $_GET['action'] = 'generate';
        require_once __DIR__ . '/player.php';
        break;

    case 'objectif_reveal_objective':
        $_GET['action'] = 'reveal';
        require_once __DIR__ . '/player.php';
        break;

    case 'objectif_get_player_info':
        $_GET['action'] = 'info';
        require_once __DIR__ . '/player.php';
        break;

    // Actions de scores.php
    case 'objectif_end_game_with_scores':
    case 'objectif_submit_score':
        $_GET['action'] = 'save';
        require_once __DIR__ . '/scores.php';
        break;

    case 'objectif_get_scores':
        $_GET['action'] = 'get';
        require_once __DIR__ . '/scores.php';
        break;

    case 'objectif_check_notifications':
        $_GET['action'] = 'notifications';
        require_once __DIR__ . '/scores.php';
        break;

    case 'objectif_close_session':
        $_GET['action'] = 'close_session';
        require_once __DIR__ . '/scores.php';
        break;

    // Actions de user.php
    case 'objectif_user_register':
        $_GET['action'] = 'register';
        require_once __DIR__ . '/user.php';
        break;

    case 'objectif_user_login':
        $_GET['action'] = 'login';
        require_once __DIR__ . '/user.php';
        break;

    case 'objectif_user_send_code':
        $_GET['action'] = 'send_code';
        require_once __DIR__ . '/user.php';
        break;

    case 'objectif_user_get_players':
        $_GET['action'] = 'get_players';
        require_once __DIR__ . '/user.php';
        break;

    case 'objectif_user_add_player':
        $_GET['action'] = 'add_player';
        require_once __DIR__ . '/user.php';
        break;

    case 'objectif_user_remove_player':
        $_GET['action'] = 'remove_player';
        require_once __DIR__ . '/user.php';
        break;

    case 'objectif_user_get_history':
        $_GET['action'] = 'get_history';
        require_once __DIR__ . '/user.php';
        break;

    default:
        echo json_encode([
            'success' => false,
            'data' => [],
            'message' => 'Action non reconnue: ' . $action
        ]);
        break;
}
