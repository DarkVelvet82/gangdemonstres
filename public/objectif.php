<?php
require_once __DIR__ . '/../includes/front-header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon objectif - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .container {
            max-width: 500px;
        }

        /* Boîte d'instruction didactique */
        .instruction-box {
            background: linear-gradient(135deg, rgba(0, 63, 83, 0.1) 0%, rgba(0, 53, 71, 0.1) 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .instruction-text {
            color: #003f53;
            font-size: 16px;
            line-height: 1.5;
            margin: 0 0 10px 0;
            font-weight: 500;
        }

        .arrow-down {
            font-size: 32px;
            color: #003f53;
            animation: bounce 1s ease infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(8px); }
        }

        /* Bouton sticky mobile */
        .sticky-objective {
            display: none;
        }

        @media (max-width: 768px) {
            .sticky-objective {
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

            .sticky-objective button {
                display: block;
                width: 100%;
                padding: 16px;
                background: linear-gradient(135deg, #003f53 0%, #003547 100%);
                color: white;
                text-align: center;
                border: none;
                border-radius: 12px;
                font-size: 18px;
                font-weight: 600;
                cursor: pointer;
            }

            /* Cacher le bouton dans le contenu sur mobile */
            .objective-generator .generate-btn {
                display: none;
            }

            /* Padding en bas pour le sticky */
            .container {
                padding-bottom: calc(100px + env(safe-area-inset-bottom, 0px));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="objectif-player-zone modern-objective-page">
            <div class="welcome-section">
                <h1 id="welcome-message" class="welcome-title">Bienvenue !</h1>
                <p class="welcome-subtitle">On vous souhaite bonne chance lors de cette partie.</p>
            </div>

            <div class="objective-generator">
                <div class="instruction-box">
                    <p class="instruction-text">La partie va commencer ! Génère ton objectif secret en cliquant sur le bouton ci-dessous.</p>
                    <div class="arrow-down">&#8595;</div>
                </div>

                <button id="objectif-generate-button" class="generate-btn">
                    Générer mon objectif
                </button>
            </div>

            <div id="objectif-state" class="objective-display"></div>
        </div>
    </div>

    <!-- Bouton sticky mobile -->
    <div class="sticky-objective" id="sticky-objective">
        <button type="button" id="sticky-generate-btn">Générer mon objectif</button>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script>
        // Passer le chemin du logo au JavaScript
        window.siteLogo = '<?php echo htmlspecialchars($logo_path); ?>';
    </script>
    <script src="../assets/js/objectif-main.js"></script>
    <script src="../assets/js/objectif-notifications.js"></script>
    <script src="../assets/js/objectif-objectives.js"></script>
    <script src="../assets/js/objectif-scores.js"></script>
    <script>
        $(document).ready(function() {
            // Le bouton sticky déclenche le même comportement que le bouton principal
            $('#sticky-generate-btn').on('click', function() {
                $('#objectif-generate-button').click();
            });

            // Observer pour masquer le sticky quand l'objectif est généré
            const observer = new MutationObserver(function(mutations) {
                // Si la section objective-generator est cachée, cacher le sticky
                if ($('.objective-generator').is(':hidden') || $('.objective-generator').css('display') === 'none') {
                    $('#sticky-objective').hide();
                }
            });

            const generator = document.querySelector('.objective-generator');
            if (generator) {
                observer.observe(generator, { attributes: true, attributeFilter: ['style'] });
            }
        });
    </script>
</body>
</html>
