<?php
/**
 * Test de génération de QR codes pour les cartes
 * Affiche des QR codes de différentes tailles avec juste l'ID de la carte
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Récupérer quelques cartes pour tester
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "cards LIMIT 10");
$cards = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test QR Code - Gang de Monstres</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
        }

        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .qr-card {
            border: 1px solid #ddd;
            padding: 15px;
            text-align: center;
            border-radius: 4px;
            background: #fafafa;
        }

        .qr-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }

        .qr-container {
            display: flex;
            justify-content: center;
            margin: 10px 0;
        }

        .card-info {
            font-size: 12px;
            color: #888;
            margin-top: 10px;
        }

        .size-section {
            margin-bottom: 40px;
        }

        .size-section h2 {
            color: #555;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .comparison {
            display: flex;
            gap: 30px;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .size-demo {
            border: 2px solid #333;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            background: white;
        }

        .size-demo h3 {
            margin-top: 0;
        }

        .note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .note strong {
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎴 Test de QR Codes pour les Cartes</h1>

        <div class="note">
            <strong>Test:</strong> QR codes générés avec uniquement l'ID de la carte pour une taille minimale.
            <br>Plus le QR code est petit, moins il y a de marges d'erreur pour le scan.
        </div>

        <!-- Comparaison de complexité -->
        <div class="size-section">
            <h2>Comparaison de Complexité Visuelle (Carte ID: <?php echo $cards[0]['id']; ?>)</h2>
            <div class="comparison">
                <div class="size-demo">
                    <h3>Niveau L (7%)</h3>
                    <div id="qr-simple-l"></div>
                    <div class="card-info">Le plus simple<br>Moins de points</div>
                </div>

                <div class="size-demo">
                    <h3>Niveau M (15%)</h3>
                    <div id="qr-simple-m"></div>
                    <div class="card-info">Équilibré<br>Correction moyenne</div>
                </div>

                <div class="size-demo">
                    <h3>Niveau Q (25%)</h3>
                    <div id="qr-simple-q"></div>
                    <div class="card-info">Plus dense<br>Bonne correction</div>
                </div>

                <div class="size-demo">
                    <h3>Niveau H (30%)</h3>
                    <div id="qr-simple-h"></div>
                    <div class="card-info">Le plus dense<br>Meilleure correction</div>
                </div>
            </div>
        </div>

        <!-- Comparaison de tailles -->
        <div class="size-section">
            <h2>Comparaison de Tailles (Niveau L - Plus Simple)</h2>
            <div class="comparison">
                <div class="size-demo">
                    <h3>Très Petit (64x64px)</h3>
                    <div id="qr-tiny"></div>
                    <div class="card-info">Pour carte miniature</div>
                </div>

                <div class="size-demo">
                    <h3>Petit (96x96px)</h3>
                    <div id="qr-small"></div>
                    <div class="card-info">Recommandé minimum</div>
                </div>

                <div class="size-demo">
                    <h3>Moyen (128x128px)</h3>
                    <div id="qr-medium"></div>
                    <div class="card-info">Optimal pour scan</div>
                </div>

                <div class="size-demo">
                    <h3>Grand (192x192px)</h3>
                    <div id="qr-large"></div>
                    <div class="card-info">Très facile à scanner</div>
                </div>
            </div>
        </div>

        <!-- Exemples avec plusieurs cartes -->
        <div class="size-section">
            <h2>Exemples de Cartes (96x96px - Taille recommandée)</h2>
            <div class="qr-grid">
                <?php foreach ($cards as $card): ?>
                <div class="qr-card">
                    <h3>Carte #<?php echo $card['id']; ?></h3>
                    <div class="qr-container">
                        <div id="qr-card-<?php echo $card['id']; ?>"></div>
                    </div>
                    <div class="card-info">
                        <?php echo htmlspecialchars($card['card_name']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Informations techniques -->
        <div class="size-section">
            <h2>Informations Techniques</h2>
            <ul>
                <li><strong>Contenu:</strong> Juste l'ID numérique de la carte (ex: "1", "42", "123")</li>
                <li><strong>Format:</strong> QR Code version 1 ou 2 (selon la longueur de l'ID)</li>
                <li><strong>Niveaux de correction d'erreur:</strong>
                    <ul>
                        <li><strong>L (7%)</strong> - Le plus simple visuellement, moins de points noirs</li>
                        <li><strong>M (15%)</strong> - Équilibré entre simplicité et fiabilité</li>
                        <li><strong>Q (25%)</strong> - Plus de points, meilleure correction</li>
                        <li><strong>H (30%)</strong> - Le plus dense, correction maximale</li>
                    </ul>
                </li>
                <li><strong>Recommandation:</strong> Niveau L avec 96x96px pour un QR code simple et compact</li>
                <li><strong>Impression:</strong> À tester sur des cartes réelles pour vérifier la lisibilité</li>
            </ul>
        </div>
    </div>

    <script>
        // Générer les QR codes de comparaison avec la première carte
        const firstCardId = "<?php echo $cards[0]['id']; ?>";

        // Comparaison des niveaux de correction (128x128 pour bien voir la différence)
        new QRCode(document.getElementById("qr-simple-l"), {
            text: firstCardId,
            width: 128,
            height: 128,
            correctLevel: QRCode.CorrectLevel.L  // 7% - Le plus simple visuellement
        });

        new QRCode(document.getElementById("qr-simple-m"), {
            text: firstCardId,
            width: 128,
            height: 128,
            correctLevel: QRCode.CorrectLevel.M  // 15% - Équilibré
        });

        new QRCode(document.getElementById("qr-simple-q"), {
            text: firstCardId,
            width: 128,
            height: 128,
            correctLevel: QRCode.CorrectLevel.Q  // 25% - Plus dense
        });

        new QRCode(document.getElementById("qr-simple-h"), {
            text: firstCardId,
            width: 128,
            height: 128,
            correctLevel: QRCode.CorrectLevel.H  // 30% - Le plus dense
        });

        // Comparaison de tailles avec niveau L (le plus simple)
        new QRCode(document.getElementById("qr-tiny"), {
            text: firstCardId,
            width: 64,
            height: 64,
            correctLevel: QRCode.CorrectLevel.L
        });

        new QRCode(document.getElementById("qr-small"), {
            text: firstCardId,
            width: 96,
            height: 96,
            correctLevel: QRCode.CorrectLevel.L
        });

        new QRCode(document.getElementById("qr-medium"), {
            text: firstCardId,
            width: 128,
            height: 128,
            correctLevel: QRCode.CorrectLevel.L
        });

        new QRCode(document.getElementById("qr-large"), {
            text: firstCardId,
            width: 192,
            height: 192,
            correctLevel: QRCode.CorrectLevel.L
        });

        // Générer les QR codes pour toutes les cartes exemples (niveau L)
        <?php foreach ($cards as $card): ?>
        new QRCode(document.getElementById("qr-card-<?php echo $card['id']; ?>"), {
            text: "<?php echo $card['id']; ?>",
            width: 96,
            height: 96,
            correctLevel: QRCode.CorrectLevel.L
        });
        <?php endforeach; ?>
    </script>
</body>
</html>
