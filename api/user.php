<?php
// api/user.php - Endpoints pour la gestion des utilisateurs

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'register':
            register_user();
            break;

        case 'login':
            login_user();
            break;

        case 'send_code':
            send_code_by_email();
            break;

        case 'get_players':
            get_user_players();
            break;

        case 'add_player':
            add_user_player();
            break;

        case 'remove_player':
            remove_user_player();
            break;

        case 'get_history':
            get_user_history();
            break;

        default:
            send_json_response(false, [], 'Action non reconnue');
    }

} catch (Exception $e) {
    log_error('API User Error: ' . $e->getMessage());
    send_json_response(false, [], DEBUG_MODE ? $e->getMessage() : 'Erreur serveur');
}

/**
 * Générer un code unique (4 chiffres + 1 lettre majuscule, position aléatoire)
 * Exemple: 549B3, 12A34, A1234
 * Note: Exclut la lettre O pour éviter confusion avec le chiffre 0
 */
function generate_unique_code($pdo) {
    $max_attempts = 100;
    $attempt = 0;

    // Lettres autorisées (A-Z sauf O)
    $allowed_letters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ';

    do {
        // 4 chiffres
        $digits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        // 1 lettre majuscule (sauf O)
        $letter = $allowed_letters[rand(0, strlen($allowed_letters) - 1)];

        // Position aléatoire pour la lettre (0-4)
        $position = rand(0, 4);

        // Insérer la lettre à la position
        $code = substr($digits, 0, $position) . $letter . substr($digits, $position);

        // Vérifier unicité
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "users WHERE code_unique = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetchColumn();

        $attempt++;

        if ($attempt >= $max_attempts) {
            throw new Exception("Impossible de générer un code unique");
        }

    } while ($exists > 0);

    return $code;
}

/**
 * Inscription d'un nouvel utilisateur
 */
function register_user() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    // Anti-spam: Honeypot check
    $honeypot = get_post_value('website', '');
    if (!empty($honeypot)) {
        // Les bots remplissent ce champ invisible - rejet silencieux
        log_error('Honeypot triggered - IP: ' . get_client_ip());
        send_json_response(false, [], 'Une erreur est survenue, veuillez réessayer');
    }

    // Anti-spam: Rate limiting (max 5 inscriptions par IP par heure)
    if (!check_rate_limit('register', get_client_ip(), 5, 3600)) {
        log_error('Rate limit exceeded for registration - IP: ' . get_client_ip());
        send_json_response(false, [], 'Trop de tentatives. Veuillez patienter avant de réessayer.');
    }

    $prenom = clean_string(get_post_value('prenom', ''));
    $email = filter_var(get_post_value('email', ''), FILTER_VALIDATE_EMAIL);

    if (empty($prenom)) {
        send_json_response(false, [], 'Le prénom est obligatoire');
    }

    if (!$email) {
        send_json_response(false, [], 'Email invalide');
    }

    // Vérifier si l'email existe déjà
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        send_json_response(false, [], 'Cet email est déjà utilisé. Utilisez "Code oublié" pour récupérer votre code.');
    }

    // Générer le code unique
    $code_unique = generate_unique_code($pdo);

    // Créer l'utilisateur
    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "users (prenom, email, code_unique) VALUES (?, ?, ?)");
    $stmt->execute([$prenom, $email, $code_unique]);

    $user_id = $pdo->lastInsertId();

    // Envoyer l'email avec le code (optionnel, peut échouer silencieusement)
    send_welcome_email($email, $prenom, $code_unique);

    send_json_response(true, [
        'user_id' => $user_id,
        'prenom' => $prenom,
        'code_unique' => $code_unique,
        'message' => 'Compte créé ! Notez bien votre code : ' . $code_unique
    ], 'Inscription réussie');
}

/**
 * Connexion d'un utilisateur
 */
function login_user() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $prenom = clean_string(get_post_value('prenom', ''));
    $code = strtoupper(clean_string(get_post_value('code', '')));

    if (empty($prenom) || empty($code)) {
        send_json_response(false, [], 'Prénom et code requis');
    }

    // Rechercher l'utilisateur (prénom insensible à la casse)
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "users
        WHERE LOWER(prenom) = LOWER(?) AND code_unique = ?");
    $stmt->execute([$prenom, $code]);
    $user = $stmt->fetch();

    if (!$user) {
        send_json_response(false, [], 'Prénom ou code incorrect');
    }

    // Mettre à jour last_login_at
    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "users SET last_login_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Récupérer les joueurs fréquents
    $stmt = $pdo->prepare("SELECT id, player_name FROM " . DB_PREFIX . "user_players
        WHERE user_id = ? ORDER BY player_name");
    $stmt->execute([$user['id']]);
    $players = $stmt->fetchAll();

    send_json_response(true, [
        'user_id' => $user['id'],
        'prenom' => $user['prenom'],
        'email' => $user['email'],
        'players' => $players
    ], 'Connexion réussie');
}

/**
 * Envoyer le code par email (code oublié)
 */
function send_code_by_email() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $email = filter_var(get_post_value('email', ''), FILTER_VALIDATE_EMAIL);

    if (!$email) {
        send_json_response(false, [], 'Email invalide');
    }

    // Rechercher l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Ne pas révéler si l'email existe ou non (sécurité)
        send_json_response(true, [], 'Si cet email existe, vous recevrez votre code.');
        return;
    }

    // Envoyer l'email
    $sent = send_reminder_email($user['email'], $user['prenom'], $user['code_unique']);

    send_json_response(true, [], 'Si cet email existe, vous recevrez votre code.');
}

/**
 * Récupérer les joueurs fréquents d'un utilisateur
 */
function get_user_players() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $user_id = clean_int(get_post_value('user_id', 0));

    if (!$user_id) {
        send_json_response(false, [], 'User ID manquant');
    }

    $stmt = $pdo->prepare("SELECT id, player_name, created_at FROM " . DB_PREFIX . "user_players
        WHERE user_id = ? ORDER BY player_name");
    $stmt->execute([$user_id]);
    $players = $stmt->fetchAll();

    send_json_response(true, [
        'players' => $players,
        'count' => count($players)
    ]);
}

/**
 * Ajouter un joueur fréquent
 */
function add_user_player() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $user_id = clean_int(get_post_value('user_id', 0));
    $player_name = clean_string(get_post_value('player_name', ''));

    if (!$user_id) {
        send_json_response(false, [], 'User ID manquant');
    }

    if (empty($player_name)) {
        send_json_response(false, [], 'Nom du joueur requis');
    }

    // Vérifier que l'utilisateur existe
    $stmt = $pdo->prepare("SELECT id FROM " . DB_PREFIX . "users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        send_json_response(false, [], 'Utilisateur introuvable');
    }

    // Vérifier si le joueur existe déjà pour cet utilisateur
    $stmt = $pdo->prepare("SELECT id FROM " . DB_PREFIX . "user_players
        WHERE user_id = ? AND LOWER(player_name) = LOWER(?)");
    $stmt->execute([$user_id, $player_name]);
    if ($stmt->fetch()) {
        send_json_response(false, [], 'Ce joueur existe déjà dans votre liste');
    }

    // Ajouter le joueur
    $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "user_players (user_id, player_name) VALUES (?, ?)");
    $stmt->execute([$user_id, $player_name]);

    $player_id = $pdo->lastInsertId();

    send_json_response(true, [
        'player_id' => $player_id,
        'player_name' => $player_name
    ], 'Joueur ajouté');
}

/**
 * Supprimer un joueur fréquent
 */
function remove_user_player() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $user_id = clean_int(get_post_value('user_id', 0));
    $player_id = clean_int(get_post_value('player_id', 0));

    if (!$user_id || !$player_id) {
        send_json_response(false, [], 'IDs manquants');
    }

    // Supprimer (vérifie que le joueur appartient bien à l'utilisateur)
    $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "user_players WHERE id = ? AND user_id = ?");
    $stmt->execute([$player_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        send_json_response(false, [], 'Joueur non trouvé');
    }

    send_json_response(true, [], 'Joueur supprimé');
}

/**
 * Récupérer l'historique des parties d'un utilisateur
 */
function get_user_history() {
    global $pdo;

    $nonce = get_post_value('nonce');
    if (!verify_nonce($nonce)) {
        send_json_response(false, [], 'Nonce invalide');
    }

    $user_id = clean_int(get_post_value('user_id', 0));
    $limit = clean_int(get_post_value('limit', 20));

    if (!$user_id) {
        send_json_response(false, [], 'User ID manquant');
    }

    // Récupérer les parties créées par cet utilisateur
    $stmt = $pdo->prepare("
        SELECT
            g.id,
            g.player_count,
            g.status,
            g.difficulty,
            g.game_config,
            g.created_at,
            g.ended_at
        FROM " . DB_PREFIX . "games g
        WHERE g.user_id = ?
        ORDER BY g.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    $games = $stmt->fetchAll();

    // Enrichir avec les infos des joueurs et gagnants
    foreach ($games as &$game) {
        $game['config_name'] = get_game_config_name($pdo, $game['game_config']);

        $config = json_decode($game['game_config'], true) ?: [];
        $game['winner_name'] = $config['winner_name'] ?? null;

        // Récupérer les joueurs de cette partie
        $stmt = $pdo->prepare("SELECT player_name, is_creator FROM " . DB_PREFIX . "players
            WHERE game_id = ? ORDER BY is_creator DESC, player_name");
        $stmt->execute([$game['id']]);
        $game['players'] = $stmt->fetchAll();
    }

    send_json_response(true, [
        'games' => $games,
        'count' => count($games)
    ]);
}

/**
 * Envoyer un email de bienvenue avec le code
 */
function send_welcome_email($email, $prenom, $code) {
    $subject = "Bienvenue sur Gang de Monstres - Votre code : $code";
    $message = "
Bonjour $prenom,

Bienvenue sur Gang de Monstres !

Votre code personnel est : $code

Conservez-le précieusement, il vous permettra de vous reconnecter.

Pour vous connecter, entrez simplement :
- Votre prénom : $prenom
- Votre code : $code

Bon jeu !
L'équipe Gang de Monstres
";

    $headers = "From: noreply@gangdemonstres.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, $subject, $message, $headers);
}

/**
 * Envoyer un email de rappel du code
 */
function send_reminder_email($email, $prenom, $code) {
    $subject = "Gang de Monstres - Rappel de votre code";
    $message = "
Bonjour $prenom,

Vous avez demandé un rappel de votre code personnel.

Votre code est : $code

Pour vous connecter, entrez :
- Votre prénom : $prenom
- Votre code : $code

Bon jeu !
L'équipe Gang de Monstres
";

    $headers = "From: noreply@gangdemonstres.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, $subject, $message, $headers);
}
