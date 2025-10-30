<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon objectif - Gang de Monstres</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="objectif-player-zone modern-objective-page">
            <div class="welcome-section">
                <h1 id="welcome-message" class="welcome-title">🎮 Bienvenue !</h1>
                <p class="welcome-subtitle">On vous souhaite bonne chance lors de cette partie.</p>
            </div>

            <div class="objective-generator">
                <h2 class="section-title">Votre objectif à réaliser :</h2>

                <button id="objectif-generate-button" class="generate-btn">
                    🎯 Générer mon objectif
                </button>
            </div>

            <div id="objectif-state" class="objective-display"></div>
        </div>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script src="../assets/js/objectif-main.js"></script>
    <script src="../assets/js/objectif-notifications.js"></script>
    <script src="../assets/js/objectif-objectives.js"></script>
    <script src="../assets/js/objectif-scores.js"></script>
</body>
</html>
