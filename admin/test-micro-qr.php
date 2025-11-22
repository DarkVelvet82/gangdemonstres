<?php
/**
 * Test de génération de Micro QR codes pour les cartes
 * Utilise qrcode-generator pour générer des Micro QR codes ultra-compacts
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
    <title>Test Micro QR Code - Gang de Monstres</title>
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
            min-height: 150px;
            align-items: center;
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

        .warning {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #721c24;
        }

        canvas {
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎴 Test de Micro QR Codes (M1 - 11x11 modules)</h1>

        <div class="note">
            <strong>Test:</strong> Micro QR codes M1 ultra-compacts avec seulement 11x11 modules.
            <br>Les Micro QR peuvent encoder jusqu'à 5 chiffres en mode numérique.
        </div>

        <div class="warning">
            <strong>⚠️ Limitation:</strong> Micro QR M1 peut encoder maximum 5 chiffres. Les ID de cartes > 99999 ne fonctionneront pas.
            <br>Pour ID plus longs, utilisez M2 (13x13), M3 (15x15) ou M4 (17x17).
        </div>

        <!-- Comparaison Standard vs Micro QR -->
        <div class="size-section">
            <h2>Comparaison: Standard vs Micro QR (Carte ID: <?php echo $cards[0]['id']; ?>)</h2>
            <div class="comparison">
                <div class="size-demo">
                    <h3>QR Standard (Level L)</h3>
                    <div class="qr-container">
                        <div id="qr-standard"></div>
                    </div>
                    <div class="card-info">21x21 modules minimum<br>QR Code classique</div>
                </div>

                <div class="size-demo">
                    <h3>Micro QR M1</h3>
                    <div class="qr-container">
                        <canvas id="micro-qr-m1"></canvas>
                    </div>
                    <div class="card-info">11x11 modules<br>Le plus compact</div>
                </div>

                <div class="size-demo">
                    <h3>Micro QR M2</h3>
                    <div class="qr-container">
                        <canvas id="micro-qr-m2"></canvas>
                    </div>
                    <div class="card-info">13x13 modules<br>Jusqu'à 10 chiffres</div>
                </div>

                <div class="size-demo">
                    <h3>Micro QR M3</h3>
                    <div class="qr-container">
                        <canvas id="micro-qr-m3"></canvas>
                    </div>
                    <div class="card-info">15x15 modules<br>Jusqu'à 23 chiffres</div>
                </div>
            </div>
        </div>

        <!-- Exemples avec plusieurs cartes en Micro QR M1 -->
        <div class="size-section">
            <h2>Exemples de Cartes (Micro QR M1 - 11x11 modules)</h2>
            <div class="qr-grid">
                <?php foreach ($cards as $card): ?>
                <div class="qr-card">
                    <h3>Carte #<?php echo $card['id']; ?></h3>
                    <div class="qr-container">
                        <canvas id="micro-card-<?php echo $card['id']; ?>"></canvas>
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
            <h2>Informations Techniques - Micro QR Code</h2>
            <table border="1" cellpadding="10" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f0f0f0;">
                        <th>Type</th>
                        <th>Modules</th>
                        <th>Capacité (chiffres)</th>
                        <th>Capacité (alphanum)</th>
                        <th>Recommandation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>M1</strong></td>
                        <td>11x11</td>
                        <td>5 chiffres</td>
                        <td>-</td>
                        <td>ID ≤ 99999</td>
                    </tr>
                    <tr>
                        <td><strong>M2</strong></td>
                        <td>13x13</td>
                        <td>10 chiffres</td>
                        <td>6 caractères</td>
                        <td>ID ≤ 9999999999</td>
                    </tr>
                    <tr>
                        <td><strong>M3</strong></td>
                        <td>15x15</td>
                        <td>23 chiffres</td>
                        <td>14 caractères</td>
                        <td>Très grande capacité</td>
                    </tr>
                    <tr>
                        <td><strong>M4</strong></td>
                        <td>17x17</td>
                        <td>35 chiffres</td>
                        <td>21 caractères</td>
                        <td>Maximum capacité</td>
                    </tr>
                </tbody>
            </table>
            <br>
            <ul>
                <li><strong>Avantage:</strong> Beaucoup plus petits que les QR codes standards (52% de réduction)</li>
                <li><strong>Inconvénient:</strong> Moins de correction d'erreur, nécessite une impression de qualité</li>
                <li><strong>Compatibilité:</strong> Supporté par la plupart des lecteurs QR modernes</li>
                <li><strong>Recommandation:</strong> Micro QR M1 ou M2 selon la plage d'ID de vos cartes</li>
            </ul>
        </div>
    </div>

    <!-- Bibliothèque standard pour comparaison -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <!-- Fonction manuelle pour générer les Micro QR -->
    <script>
        const firstCardId = "<?php echo $cards[0]['id']; ?>";

        // QR Standard pour comparaison
        new QRCode(document.getElementById("qr-standard"), {
            text: firstCardId,
            width: 128,
            height: 128,
            correctLevel: QRCode.CorrectLevel.L
        });

        // Fonction simplifiée pour dessiner un Micro QR M1 (11x11)
        // Note: Ceci est une SIMULATION visuelle pour montrer la taille
        // En production, il faudrait une vraie bibliothèque Micro QR
        function drawMicroQR(canvasId, data, moduleCount) {
            const canvas = document.getElementById(canvasId);
            const size = 132; // Taille du canvas (12 pixels par module)
            const moduleSize = Math.floor(size / moduleCount);

            canvas.width = size;
            canvas.height = size;

            const ctx = canvas.getContext('2d');
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, size, size);

            // Dessiner un pattern simulé (checker pattern avec l'ID)
            // NOTE: Ceci n'est PAS un vrai Micro QR encodé!
            ctx.fillStyle = 'black';

            // Pattern de simulation basique
            const hash = data.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);

            for (let row = 0; row < moduleCount; row++) {
                for (let col = 0; col < moduleCount; col++) {
                    // Simulation de pattern pseudo-aléatoire basé sur l'ID
                    const shouldFill = ((row + col + hash) % 3 === 0) ||
                                      (row < 3 && col < 3) || // Finder pattern simulé
                                      (row === 0 || col === 0 || row === moduleCount - 1 || col === moduleCount - 1);

                    if (shouldFill) {
                        ctx.fillRect(
                            col * moduleSize,
                            row * moduleSize,
                            moduleSize - 1,
                            moduleSize - 1
                        );
                    }
                }
            }
        }

        // Générer les Micro QR de comparaison
        drawMicroQR('micro-qr-m1', firstCardId, 11);  // M1: 11x11
        drawMicroQR('micro-qr-m2', firstCardId, 13);  // M2: 13x13
        drawMicroQR('micro-qr-m3', firstCardId, 15);  // M3: 15x15

        // Générer les Micro QR pour toutes les cartes
        <?php foreach ($cards as $card): ?>
        drawMicroQR('micro-card-<?php echo $card['id']; ?>', '<?php echo $card['id']; ?>', 11);
        <?php endforeach; ?>
    </script>

    <div class="warning" style="margin-top: 30px;">
        <strong>⚠️ Important:</strong> Les Micro QR codes affichés ci-dessus sont des <strong>SIMULATIONS VISUELLES</strong> pour montrer la taille.
        <br>Pour générer de VRAIS Micro QR codes scannables, il faudra utiliser une bibliothèque côté serveur (PHP) comme <code>chillerlan/php-qrcode</code> ou <code>endroid/qr-code</code>.
        <br><br>
        <strong>Voulez-vous que je génère de vrais Micro QR codes scannables?</strong>
    </div>
</body>
</html>
