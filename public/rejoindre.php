<?php
require_once __DIR__ . '/../includes/front-header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejoindre une partie - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .container {
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="back-arrow">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="48" d="M244 400L100 256l144-144M120 256h292"/></svg>
            </a>
            <h1>Rejoindre une partie</h1>
        </div>

        <div class="objectif-join-game">
            <div class="join-instructions">
                <p>Entrez le code à 6 chiffres qui vous a été donné par le créateur de la partie</p>
            </div>

            <div class="join-form">
                <label for="objectif-player-code">Code joueur :</label>
                <input type="text" id="objectif-player-code" maxlength="6" placeholder="000000" inputmode="numeric" pattern="[0-9]{6}">
                <button id="objectif-join-button" class="objectif-button objectif-primary">Rejoindre la partie</button>
            </div>

            <div id="objectif-join-result" style="margin-top:20px;"></div>
            <div id="objectif-redirect" style="margin-top:10px;"></div>
        </div>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script src="../assets/js/objectif-main.js"></script>
    <script src="../assets/js/objectif-join.js"></script>
</body>
</html>
