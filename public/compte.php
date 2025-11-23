<?php
require_once __DIR__ . '/../includes/front-header.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon compte - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .container {
            max-width: 500px;
        }
        .auth-container {
            max-width: 450px;
            margin: 0 auto;
        }
        .auth-tabs {
            display: flex;
            margin-bottom: 20px;
            border-radius: 10px;
            overflow: hidden;
            background: #f0f0f0;
        }
        .auth-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            background: transparent;
        }
        .auth-tab.active {
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            color: white;
        }
        .auth-panel {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .auth-panel.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #003f53;
            outline: none;
        }
        .code-input {
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 700;
            font-size: 20px !important;
            text-align: center;
        }
        .btn-primary {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 63, 83, 0.4);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            width: 100%;
            padding: 12px;
            background: #f0f0f0;
            color: #333;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .success-box .code-display {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 5px;
            color: #155724;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: none;
        }
        .forgot-link {
            text-align: center;
            margin-top: 15px;
        }
        .forgot-link a {
            color: #003f53;
            text-decoration: none;
            font-size: 14px;
        }
        .forgot-link a:hover {
            text-decoration: underline;
        }
        .user-dashboard {
            display: none;
        }
        .user-header {
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }
        .user-header h2 {
            margin: 0 0 5px 0;
        }
        .user-code {
            font-size: 14px;
            opacity: 0.9;
        }
        .dashboard-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .dashboard-section h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
        }
        .players-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        .player-tag {
            background: #f0f0f0;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .player-tag .remove-btn {
            cursor: pointer;
            color: #999;
            font-weight: bold;
        }
        .player-tag .remove-btn:hover {
            color: #dc3545;
        }
        .add-player-form {
            display: flex;
            gap: 10px;
        }
        .add-player-form input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        .add-player-form button {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .history-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .history-item .date {
            font-size: 12px;
            color: #999;
        }
        .history-item .players {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .history-item .winner {
            color: #28a745;
            font-weight: 600;
        }
        .btn-logout {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="back-arrow">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="48" d="M244 400L100 256l144-144M120 256h292"/></svg>
            </a>
            <h1>Mon compte</h1>
        </div>

        <!-- Formulaires Auth -->
        <div class="auth-container" id="auth-section">
            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="login">Connexion</button>
                <button class="auth-tab" data-tab="register">Inscription</button>
            </div>

            <div class="error-message" id="auth-error"></div>

            <!-- Panel Connexion -->
            <div class="auth-panel active" id="panel-login">
                <form id="login-form">
                    <div class="form-group">
                        <label>Votre prénom</label>
                        <input type="text" id="login-prenom" placeholder="Ex: Florine" required>
                    </div>
                    <div class="form-group">
                        <label>Votre code (5 caractères)</label>
                        <input type="text" id="login-code" class="code-input" placeholder="Ex: 54B93" maxlength="5" required>
                    </div>
                    <button type="submit" class="btn-primary" id="btn-login">Se connecter</button>
                </form>
                <div class="forgot-link">
                    <a href="#" id="forgot-code-link">Code oublié ?</a>
                </div>
            </div>

            <!-- Panel Inscription -->
            <div class="auth-panel" id="panel-register">
                <form id="register-form">
                    <div class="form-group">
                        <label>Votre prénom</label>
                        <input type="text" id="register-prenom" placeholder="Ex: Florine" required>
                    </div>
                    <div class="form-group">
                        <label>Votre email</label>
                        <input type="email" id="register-email" placeholder="Pour récupérer votre code" required>
                    </div>
                    <!-- Honeypot anti-spam (invisible pour les humains) -->
                    <div style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;" aria-hidden="true">
                        <label for="website">Ne pas remplir</label>
                        <input type="text" id="register-website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <button type="submit" class="btn-primary" id="btn-register">Créer mon compte</button>
                </form>
            </div>

            <!-- Panel Code oublié -->
            <div class="auth-panel" id="panel-forgot">
                <h3 style="margin-bottom:15px;">Code oublié ?</h3>
                <p style="color:#666; margin-bottom:20px;">Entrez votre email et nous vous renverrons votre code.</p>
                <form id="forgot-form">
                    <div class="form-group">
                        <label>Votre email</label>
                        <input type="email" id="forgot-email" required>
                    </div>
                    <button type="submit" class="btn-primary">Recevoir mon code</button>
                    <button type="button" class="btn-secondary" id="back-to-login">Retour à la connexion</button>
                </form>
            </div>

            <!-- Succès inscription -->
            <div class="auth-panel" id="panel-success">
                <div class="success-box">
                    <h3>Compte créé avec succès !</h3>
                    <p>Votre code personnel est :</p>
                    <div class="code-display" id="new-user-code"></div>
                    <p style="color:#155724; font-size:14px;">Notez-le précieusement !</p>
                </div>
                <button type="button" class="btn-primary" id="btn-continue-after-register">Continuer</button>
            </div>
        </div>

        <!-- Dashboard utilisateur connecté -->
        <div class="user-dashboard" id="user-dashboard">
            <div class="user-header">
                <h2>Bonjour <span id="user-prenom"></span> !</h2>
                <div class="user-code">Code: <strong id="user-code-display"></strong></div>
            </div>

            <!-- Joueurs fréquents -->
            <div class="dashboard-section">
                <h3>Mes joueurs habituels</h3>
                <div class="players-list" id="players-list">
                    <!-- Rempli par JS -->
                </div>
                <div class="add-player-form">
                    <input type="text" id="new-player-name" placeholder="Ajouter un joueur...">
                    <button type="button" id="btn-add-player">+ Ajouter</button>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="dashboard-section">
                <h3>Actions</h3>
                <a href="creer-partie.php" class="btn-primary" style="display:block; text-align:center; text-decoration:none; margin-bottom:10px;">
                    Créer une nouvelle partie
                </a>
            </div>

            <!-- Historique -->
            <div class="dashboard-section">
                <h3>Mes dernières parties</h3>
                <div id="history-list">
                    <p style="color:#999; text-align:center;">Chargement...</p>
                </div>
            </div>

            <div style="text-align:center; margin-top:20px;">
                <button class="btn-logout" id="btn-logout">Déconnexion</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script src="../assets/js/objectif-user.js"></script>
</body>
</html>
