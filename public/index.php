<?php
require_once __DIR__ . '/../includes/front-header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_name); ?> - Objectifs Multijoueur</title>

    <!-- PWA / Icône écran d'accueil -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#003f53">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Gang Monstres">
    <link rel="apple-touch-icon" href="../assets/images/icon-512.jpg">

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

        @media (max-width: 768px) {
            body {
                padding: 15px;
                align-items: center;
            }

            .home-container {
                padding: 25px 20px;
            }
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

        /* Bouton installer */
        .install-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e4e8;
        }

        .btn-install {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 0.95em;
            font-weight: 500;
            text-decoration: none;
            border-radius: 8px;
            border: 2px dashed #003f53;
            background: transparent;
            color: #003f53;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-install:hover {
            background: rgba(0, 63, 83, 0.1);
            border-style: solid;
        }

        /* Modal d'instructions */
        .install-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .install-modal-overlay.active {
            display: flex;
        }

        .install-modal {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: calc(100% - 40px);
            max-height: 80vh;
            overflow-y: auto;
            text-align: left;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .install-modal h3 {
            margin: 0 0 20px 0;
            color: #003f53;
            font-size: 1.3em;
            text-align: center;
        }

        .install-step {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 15px;
            padding: 12px;
            background: #f7f8fa;
            border-radius: 8px;
        }

        .install-step-number {
            background: #003f53;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            flex-shrink: 0;
        }

        .install-step-text {
            flex: 1;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
        }

        .install-step-icon {
            font-size: 20px;
        }

        .install-modal-close {
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: #003f53;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .install-modal-close:hover {
            background: #002a38;
        }

        .install-ios, .install-android {
            display: none;
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
        </div>

        <!-- Section installer -->
        <div class="install-section" id="install-section">
            <button type="button" class="btn-install" id="install-btn">
                <span>📲</span> Installer sur mon téléphone
            </button>
        </div>

    </div>

    <!-- Modal d'instructions d'installation -->
    <div class="install-modal-overlay" id="install-modal">
        <div class="install-modal">
            <!-- Instructions iOS -->
            <div class="install-ios" id="install-ios">
                <h3>📲 Installer sur iPhone/iPad</h3>
                <div class="install-step">
                    <div class="install-step-number">1</div>
                    <div class="install-step-text">
                        Appuyez sur le bouton <strong>Partager</strong> <span class="install-step-icon">⬆️</span> en bas de Safari
                    </div>
                </div>
                <div class="install-step">
                    <div class="install-step-number">2</div>
                    <div class="install-step-text">
                        Faites défiler et appuyez sur <strong>"Sur l'écran d'accueil"</strong> <span class="install-step-icon">➕</span>
                    </div>
                </div>
                <div class="install-step">
                    <div class="install-step-number">3</div>
                    <div class="install-step-text">
                        Appuyez sur <strong>"Ajouter"</strong> en haut à droite
                    </div>
                </div>
            </div>

            <!-- Instructions Android -->
            <div class="install-android" id="install-android">
                <h3>📲 Installer sur Android</h3>
                <div class="install-step">
                    <div class="install-step-number">1</div>
                    <div class="install-step-text">
                        Appuyez sur le menu <strong>⋮</strong> (3 points) en haut à droite de Chrome
                    </div>
                </div>
                <div class="install-step">
                    <div class="install-step-number">2</div>
                    <div class="install-step-text">
                        Appuyez sur <strong>"Ajouter à l'écran d'accueil"</strong> ou <strong>"Installer l'application"</strong>
                    </div>
                </div>
                <div class="install-step">
                    <div class="install-step-number">3</div>
                    <div class="install-step-text">
                        Confirmez en appuyant sur <strong>"Ajouter"</strong>
                    </div>
                </div>
            </div>

            <button type="button" class="install-modal-close" id="install-modal-close">Compris !</button>
        </div>
    </div>

    <script>
    (function() {
        const installBtn = document.getElementById('install-btn');
        const installModal = document.getElementById('install-modal');
        const installModalClose = document.getElementById('install-modal-close');
        const installSection = document.getElementById('install-section');
        const installIos = document.getElementById('install-ios');
        const installAndroid = document.getElementById('install-android');

        let deferredPrompt = null;

        // Détecter si l'app est déjà installée (mode standalone)
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
            // L'app est déjà installée, masquer le bouton
            installSection.style.display = 'none';
        }

        // Capturer l'événement beforeinstallprompt (Chrome/Edge/Android)
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
        });

        // Détecter le système
        function getOS() {
            const ua = navigator.userAgent;
            if (/iPad|iPhone|iPod/.test(ua)) return 'ios';
            if (/android/i.test(ua)) return 'android';
            return 'other';
        }

        installBtn.addEventListener('click', function() {
            // Si on a le prompt natif (Android Chrome), l'utiliser
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        installSection.style.display = 'none';
                    }
                    deferredPrompt = null;
                });
                return;
            }

            // Sinon, afficher les instructions manuelles
            const os = getOS();
            if (os === 'ios') {
                installIos.style.display = 'block';
                installAndroid.style.display = 'none';
            } else {
                installIos.style.display = 'none';
                installAndroid.style.display = 'block';
            }
            installModal.classList.add('active');
        });

        installModalClose.addEventListener('click', function() {
            installModal.classList.remove('active');
        });

        installModal.addEventListener('click', function(e) {
            if (e.target === installModal) {
                installModal.classList.remove('active');
            }
        });

        // Enregistrer le Service Worker (requis pour PWA avec icône)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../sw.js')
                .then(function(reg) {
                    console.log('Service Worker enregistré:', reg.scope);
                })
                .catch(function(err) {
                    console.log('Erreur Service Worker:', err);
                });
        }
    })();
    </script>
</body>
</html>
