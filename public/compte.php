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
        body {
            align-items: center !important;
        }
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

        /* Bouton sticky mobile pour nouvelle partie */
        .sticky-new-game {
            display: none;
        }

        @media (max-width: 768px) {
            .sticky-new-game {
                display: block;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 15px 20px;
                padding-bottom: calc(15px + env(safe-area-inset-bottom, 0px));
                background: white;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
                z-index: 1000;
            }

            .sticky-new-game a {
                display: block;
                width: 100%;
                padding: 16px;
                background: linear-gradient(135deg, #003f53 0%, #003547 100%);
                color: white;
                text-align: center;
                text-decoration: none;
                border-radius: 12px;
                font-size: 18px;
                font-weight: 600;
            }

            /* Cacher toute la section Actions sur mobile */
            .dashboard-section.actions-section {
                display: none;
            }

            /* Ajouter du padding en bas pour éviter que le contenu soit caché par le sticky */
            .user-dashboard {
                padding-bottom: calc(100px + env(safe-area-inset-bottom, 0px));
            }
        }

    </style>
</head>
<body>
    <div class="container" id="auth-wrapper">
        <?php echo render_page_header('Mon compte', 'index.php'); ?>

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
    </div>

    <!-- Dashboard utilisateur connecté -->
    <div class="container" id="dashboard-container" style="display:none;">
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

            <!-- Actions rapides (visible uniquement sur desktop) -->
            <div class="dashboard-section actions-section">
                <h3>Actions</h3>
                <a href="creer-partie.php" class="btn-primary" style="display:block; text-align:center; text-decoration:none; margin-bottom:10px;">
                    Créer une nouvelle partie
                </a>
            </div>

            <!-- Scores -->
            <div class="dashboard-section">
                <a href="scores.php" class="btn-secondary" style="display:block; text-align:center; text-decoration:none;">
                    🏆 Voir les scores
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

    <!-- Bouton sticky mobile -->
    <div class="sticky-new-game" id="sticky-new-game" style="display:none;">
        <a href="creer-partie.php" id="sticky-action-btn">Créer une nouvelle partie</a>
    </div>

    <!-- Modale confirmation email envoyé -->
    <div id="modal-email-sent" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <img src="../assets/images/logo_1763819204.png" alt="Gang de Monstres" class="modal-logo">
            <h3>Email envoyé !</h3>
            <p>Si cette adresse est enregistrée, vous recevrez votre code par email.</p>
            <p class="modal-hint">Pensez à vérifier vos spams</p>
            <button type="button" class="btn-primary" id="modal-email-close">OK, compris !</button>
        </div>
    </div>

    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            max-width: 320px;
            margin: 20px;
            animation: slideUp 0.3s ease;
        }
        .modal-logo {
            width: 80px;
            height: auto;
            margin-bottom: 20px;
        }
        .modal-content h3 {
            margin: 0 0 15px 0;
            color: #003f53;
            font-size: 22px;
        }
        .modal-content p {
            color: #666;
            margin: 0 0 10px 0;
            font-size: 15px;
        }
        .modal-hint {
            font-size: 13px !important;
            color: #999 !important;
            font-style: italic;
        }
        .modal-content .btn-primary {
            margin-top: 20px;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <script src="../assets/js/app-config.js"></script>
    <script src="../assets/js/objectif-user.js"></script>
    <script>
        // Flag pour savoir si le check API est terminé
        let gameCheckComplete = false;

        $(document).ready(function() {
            // Vérifier s'il y a une partie en cours
            checkActiveGame();

            // Afficher le sticky uniquement quand l'utilisateur est connecté ET que le check API est terminé
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'style' && gameCheckComplete) {
                        updateStickyVisibility();
                    }
                });
            });

            const dashboardContainer = document.getElementById('dashboard-container');
            if (dashboardContainer) {
                observer.observe(dashboardContainer, { attributes: true });
            }
        });

        function checkActiveGame() {
            const gameId = localStorage.getItem('objectif_game_id');
            const playerId = localStorage.getItem('objectif_player_id');
            const isCreator = localStorage.getItem('objectif_is_creator') === '1';

            if (gameId && playerId) {
                // Vérifier que la partie existe encore via l'API
                $.ajax({
                    method: 'POST',
                    url: objectif_ajax.ajax_url + 'game.php?action=status',
                    data: {
                        nonce: objectif_ajax.nonce,
                        game_id: gameId
                    },
                    success: function(response) {
                        if (response.success) {
                            // La partie existe, configurer le bouton "Reprendre"
                            setupResumeButton(gameId, isCreator);
                        } else {
                            // La partie n'existe plus, nettoyer le localStorage
                            clearGameData();
                        }
                        // Marquer le check comme terminé et afficher le sticky
                        gameCheckComplete = true;
                        updateStickyVisibility();
                    },
                    error: function() {
                        // En cas d'erreur, on configure quand même le bouton reprendre
                        setupResumeButton(gameId, isCreator);
                        gameCheckComplete = true;
                        updateStickyVisibility();
                    }
                });
            } else {
                // Pas de partie en cours, marquer comme terminé et afficher le sticky normal
                gameCheckComplete = true;
                updateStickyVisibility();
            }
        }

        function setupResumeButton(gameId, isCreator) {
            // Définir le lien selon si c'est le créateur ou un joueur
            const resumeUrl = isCreator ? 'partie.php?id=' + gameId : 'objectif.php';

            // Modifier le bouton sticky
            const $stickyBtn = $('#sticky-action-btn');
            $stickyBtn.attr('href', resumeUrl);
            $stickyBtn.text('Reprendre la partie');
            $stickyBtn.css('background', 'linear-gradient(135deg, #28a745 0%, #1e7e34 100%)');

            // Modifier le bouton desktop dans la section Actions
            const $desktopBtn = $('.actions-section .btn-primary');
            if ($desktopBtn.length) {
                $desktopBtn.attr('href', resumeUrl);
                $desktopBtn.text('Reprendre la partie en cours');
                $desktopBtn.css('background', 'linear-gradient(135deg, #28a745 0%, #1e7e34 100%)');
            }
        }

        function updateStickyVisibility() {
            const dashboardContainer = document.getElementById('dashboard-container');
            const sticky = document.getElementById('sticky-new-game');
            if (dashboardContainer && sticky) {
                const isVisible = dashboardContainer.style.display === 'block';
                sticky.style.display = isVisible ? '' : 'none';
            }
        }

        function clearGameData() {
            localStorage.removeItem('objectif_game_id');
            localStorage.removeItem('objectif_player_id');
            localStorage.removeItem('objectif_is_creator');
            localStorage.removeItem('objectif_creator_name');
        }
    </script>
</body>
</html>
