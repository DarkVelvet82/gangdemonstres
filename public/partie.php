<?php
require_once __DIR__ . '/../includes/front-header.php';

// Récupérer l'ID de la partie depuis l'URL
$game_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Partie en cours - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <style>
        .partie-container {
            max-width: 600px;
            width: 100%;
        }

        .loading-state {
            text-align: center;
            padding: 40px 20px;
        }

        .loading-state .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e1e4e8;
            border-top-color: #003f53;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .error-state {
            text-align: center;
            padding: 40px 20px;
            color: #dc3545;
        }

        .error-state h3 {
            margin-bottom: 15px;
        }

        .creator-success {
            background: #f0fff4;
            border: 2px solid #28a745;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .game-status-section {
            text-align: center;
            padding: 20px;
            margin: 20px 0;
        }

        .game-status-section .status-text {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        /* Boîte d'instruction didactique */
        .instruction-box {
            background: linear-gradient(135deg, rgba(0, 63, 83, 0.1) 0%, rgba(0, 53, 71, 0.1) 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
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

        .creator-success h3 {
            color: #28a745;
            margin: 0 0 10px 0;
        }

        .success-message {
            color: #28a745;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .game-config-summary {
            background: #f7f8fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .cancel-game-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }

        #cancel-game-btn {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        #cancel-game-btn:hover {
            background: #c82333;
        }

        .other-players-codes {
            margin: 20px 0;
            text-align: center;
        }

        .other-players-codes h4 {
            margin-bottom: 15px;
            color: #003f53;
        }

        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            justify-content: center;
            gap: 15px;
        }

        .player-card {
            background: white;
            border: 2px solid #e1e4e8;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .player-card h5 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .player-code-display {
            background: #f7f8fa;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .player-code {
            font-size: 1.5em;
            color: #003f53;
            font-family: monospace;
            letter-spacing: 2px;
        }

        .player-qr {
            margin: 10px auto;
        }

        .player-qr canvas {
            max-width: 100%;
            height: auto;
        }

        .qr-instruction {
            font-size: 0.85em;
            color: #666;
            margin: 5px 0 0 0;
        }

        .qr-code-section {
            background: #f7f8fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .qr-code-section h4 {
            margin: 0 0 10px 0;
            color: #003f53;
        }

        .qr-container {
            margin: 15px 0;
        }

        .qr-container canvas {
            max-width: 200px;
            height: auto;
        }

        .qr-url {
            word-break: break-all;
            font-size: 0.9em;
        }

        .qr-url a {
            color: #003f53;
        }

        /* Player join status */
        .player-card.joined {
            border-color: #28a745;
            background: #f0fff4;
        }

        .player-card.pending {
            border-color: #ffc107;
            background: #fffbeb;
        }

        .player-status {
            font-size: 0.85em;
            padding: 5px 10px;
            border-radius: 20px;
            margin-top: 10px;
            display: inline-block;
        }

        .player-status.joined {
            background: #d4edda;
            color: #155724;
        }

        .player-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .players-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .player-code {
                font-size: 1.2em;
            }

            /* Padding en bas pour le sticky */
            .partie-container {
                padding-bottom: calc(100px + env(safe-area-inset-bottom, 0px));
            }
        }

        /* Bouton sticky "Voir mon objectif" */
        .sticky-view-objective {
            display: none;
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

        .sticky-view-objective a {
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

        /* Cacher le bouton inline sur mobile quand le sticky est visible */
        @media (max-width: 768px) {
            .game-status-section .objectif-button {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container partie-container">
        <?php echo render_page_header('Partie en cours', 'index.php'); ?>

        <div id="partie-content">
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Chargement de la partie...</p>
            </div>
        </div>
    </div>

    <!-- Bouton sticky "Voir mon objectif" (affiché quand tous les joueurs sont connectés) -->
    <div class="sticky-view-objective" id="sticky-view-objective">
        <a href="objectif.php?auto_generate=1">Voir mon objectif</a>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script src="../assets/js/modal-component.js"></script>
    <script src="../assets/js/objectif-main.js"></script>
    <script src="../assets/js/objectif-qr.js"></script>
    <script src="../assets/js/objectif-status.js"></script>
    <script src="../assets/js/objectif-partie.js"></script>
    <script>
        // Passer l'ID de la partie au JavaScript
        window.partieGameId = <?php echo $game_id; ?>;

        // Fonction globale pour afficher/masquer le sticky "Voir mon objectif"
        window.showViewObjectiveSticky = function(show) {
            const sticky = document.getElementById('sticky-view-objective');
            if (sticky) {
                sticky.style.display = show ? 'block' : 'none';
            }
        };
    </script>
</body>
</html>
