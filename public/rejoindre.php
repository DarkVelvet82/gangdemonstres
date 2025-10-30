<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejoindre une partie - Gang de Monstres</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="back-link">← Retour</a>
            <h1>🔗 Rejoindre une partie</h1>
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
