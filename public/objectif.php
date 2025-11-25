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
        html, body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }

        /* S'assurer que les overlays/notifications ne sont pas affectés par le flexbox */
        .notification-overlay,
        .game-notification,
        .auto-join-toast {
            position: fixed !important;
        }

        .notification-overlay {
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
        }

        .game-notification.session-closed {
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
        }

        .container {
            max-width: 500px;
            width: 100%;
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

        /* Bouton voir les cartes */
        .btn-view-cards {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            margin-top: 20px;
            background: #f0f0f0;
            color: #333;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-view-cards:hover {
            background: #e0e0e0;
        }

        /* Drawer cartes */
        .cards-drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        .cards-drawer-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .cards-drawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            max-height: 85vh;
            background: white;
            border-radius: 20px 20px 0 0;
            z-index: 2001;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .cards-drawer-overlay.open .cards-drawer {
            transform: translateY(0);
        }

        .drawer-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        .drawer-header h3 {
            margin: 0;
            font-size: 18px;
            color: #003f53;
        }
        .drawer-close {
            width: 36px;
            height: 36px;
            border: none;
            background: #f0f0f0;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Onglets jeux/extensions */
        .drawer-tabs {
            display: flex;
            gap: 5px;
            padding: 15px 20px;
            overflow-x: auto;
            flex-shrink: 0;
            border-bottom: 1px solid #eee;
        }
        .drawer-tab {
            padding: 10px 16px;
            background: #f0f0f0;
            border: none;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }
        .drawer-tab.active {
            background: #003f53;
            color: white;
        }

        /* Contenu cartes */
        .drawer-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            padding-bottom: calc(20px + env(safe-area-inset-bottom, 0px));
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .card-item {
            background: #f8f9fa;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .card-item:active {
            transform: scale(0.98);
        }

        .card-item-image {
            width: 100%;
            aspect-ratio: 3/4;
            object-fit: cover;
            background: #e0e0e0;
        }
        .card-item-noimage {
            width: 100%;
            aspect-ratio: 3/4;
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            padding: 10px;
        }

        .card-item-info {
            padding: 10px;
        }
        .card-item-name {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .card-item-type {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        /* Overlay carte en grand */
        .card-fullview-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.9);
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card-fullview-overlay.open {
            display: flex;
        }

        .card-fullview {
            max-width: 100%;
            max-height: 80vh;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            animation: zoomIn 0.2s ease;
        }
        @keyframes zoomIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .card-fullview img {
            width: 100%;
            max-height: 60vh;
            object-fit: contain;
        }
        .card-fullview-info {
            padding: 20px;
            text-align: center;
        }
        .card-fullview-name {
            font-size: 20px;
            font-weight: 700;
            color: #003f53;
            margin: 0 0 5px 0;
        }
        .card-fullview-power {
            font-size: 14px;
            color: #666;
            margin: 10px 0 0 0;
            line-height: 1.5;
        }
        .card-fullview-noimage {
            width: 100%;
            height: 300px;
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
        }

        .card-fullview-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 44px;
            height: 44px;
            background: white;
            border: none;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        .cards-loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <!-- Conteneur pour les notifications (hors du flux flex) -->
    <div id="notifications-root" style="position: fixed; top: 0; left: 0; width: 0; height: 0; z-index: 9998;"></div>

    <div class="container">
        <div class="objectif-player-zone modern-objective-page">
            <div class="welcome-section">
                <h1 id="welcome-message" class="welcome-title">Bienvenue !</h1>
                <p class="welcome-subtitle">On te souhaite bonne chance pour cette partie !</p>
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

            <!-- Bouton voir les cartes -->
            <button type="button" class="btn-view-cards" id="btn-view-cards" style="display:none;">
                🃏 Voir les cartes du jeu
            </button>
        </div>
    </div>

    <!-- Bouton sticky mobile -->
    <div class="sticky-objective" id="sticky-objective">
        <button type="button" id="sticky-generate-btn">Générer mon objectif</button>
    </div>

    <!-- Drawer cartes -->
    <div class="cards-drawer-overlay" id="cards-drawer-overlay">
        <div class="cards-drawer">
            <div class="drawer-header">
                <h3>🃏 Cartes du jeu</h3>
                <button type="button" class="drawer-close" id="drawer-close">&times;</button>
            </div>
            <div class="drawer-tabs" id="drawer-tabs">
                <!-- Onglets générés dynamiquement -->
            </div>
            <div class="drawer-content" id="drawer-content">
                <div class="cards-loading">Chargement des cartes...</div>
            </div>
        </div>
    </div>

    <!-- Overlay carte en grand -->
    <div class="card-fullview-overlay" id="card-fullview-overlay">
        <button type="button" class="card-fullview-close" id="card-fullview-close">&times;</button>
        <div class="card-fullview" id="card-fullview">
            <!-- Contenu généré dynamiquement -->
        </div>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script src="../assets/js/modal-component.js"></script>
    <script>
        // Passer le chemin du logo au JavaScript
        window.siteLogo = '<?php echo htmlspecialchars($logo_path); ?>';
    </script>
    <script src="../assets/js/objectif-join.js"></script>
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

            // Afficher le bouton "Voir les cartes" quand l'objectif est généré
            const objectifState = document.getElementById('objectif-state');
            const observerCards = new MutationObserver(function(mutations) {
                if (objectifState.innerHTML.trim() !== '') {
                    $('#btn-view-cards').fadeIn(300);
                }
            });
            if (objectifState) {
                observerCards.observe(objectifState, { childList: true, subtree: true });
            }
        });

        // Variables globales pour les cartes
        let cardsData = null;

        // Ouvrir le drawer
        $('#btn-view-cards').on('click', function() {
            $('#cards-drawer-overlay').addClass('open');
            if (!cardsData) {
                loadCards();
            }
        });

        // Fermer le drawer
        $('#drawer-close, #cards-drawer-overlay').on('click', function(e) {
            if (e.target === this) {
                $('#cards-drawer-overlay').removeClass('open');
            }
        });

        // Charger les cartes
        function loadCards() {
            const gameId = localStorage.getItem('objectif_game_id');
            if (!gameId) {
                $('#drawer-content').html('<div class="cards-loading">Erreur: partie non trouvée</div>');
                return;
            }

            $.ajax({
                method: 'POST',
                url: objectif_ajax.ajax_url + 'game.php?action=cards',
                data: {
                    nonce: objectif_ajax.nonce,
                    game_id: gameId
                },
                success: function(response) {
                    if (response.success && response.data.game_sets) {
                        cardsData = response.data.game_sets;
                        renderTabs();
                        renderCards(0);
                    } else {
                        $('#drawer-content').html('<div class="cards-loading">Aucune carte trouvée</div>');
                    }
                },
                error: function() {
                    $('#drawer-content').html('<div class="cards-loading">Erreur de chargement</div>');
                }
            });
        }

        // Afficher les onglets
        function renderTabs() {
            const $tabs = $('#drawer-tabs');
            $tabs.empty();

            cardsData.forEach(function(gameSet, index) {
                const activeClass = index === 0 ? 'active' : '';
                $tabs.append(`<button class="drawer-tab ${activeClass}" data-index="${index}">${gameSet.name}</button>`);
            });

            // Event sur les onglets
            $tabs.find('.drawer-tab').on('click', function() {
                const index = $(this).data('index');
                $tabs.find('.drawer-tab').removeClass('active');
                $(this).addClass('active');
                renderCards(index);
            });
        }

        // Afficher les cartes d'un jeu
        function renderCards(gameSetIndex) {
            const gameSet = cardsData[gameSetIndex];
            const $content = $('#drawer-content');

            if (!gameSet.cards || gameSet.cards.length === 0) {
                $content.html('<div class="cards-loading">Aucune carte dans ce jeu</div>');
                return;
            }

            let html = '<div class="cards-grid">';
            gameSet.cards.forEach(function(card) {
                const cardTypes = {
                    'monster': 'Monstre',
                    'power': 'Pouvoir',
                    'special': 'Spécial'
                };
                const typeLabel = cardTypes[card.card_type] || card.card_type;

                const cardImageUrl = card.image_url ? card.image_url.replace('../assets/', '/assets/') : null;
                const imageHtml = cardImageUrl
                    ? `<img src="${cardImageUrl}" alt="${card.name}" class="card-item-image">`
                    : `<div class="card-item-noimage">${card.name}</div>`;

                html += `
                    <div class="card-item" data-image="${cardImageUrl || ''}">
                        ${imageHtml}
                        <div class="card-item-info">
                            <p class="card-item-name">${card.name}</p>
                            <p class="card-item-type">${typeLabel}</p>
                        </div>
                    </div>
                `;
            });
            html += '</div>';

            $content.html(html);

            // Event sur les cartes
            $content.find('.card-item').on('click', function() {
                const imageUrl = $(this).data('image');
                if (imageUrl) {
                    showCardFullview(imageUrl);
                }
            });
        }

        // Afficher une carte en grand (juste l'image)
        function showCardFullview(imageUrl) {
            const $overlay = $('#card-fullview-overlay');
            const $fullview = $('#card-fullview');

            $fullview.html(`<img src="${imageUrl}" alt="">`);
            $overlay.addClass('open');
        }

        // Fermer la vue carte en grand
        $('#card-fullview-close, #card-fullview-overlay').on('click', function(e) {
            if (e.target === this) {
                $('#card-fullview-overlay').removeClass('open');
            }
        });
    </script>
</body>
</html>
