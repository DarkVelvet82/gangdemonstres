<?php
/**
 * Générateur de Micro QR codes réels via une API externe
 * Utilise l'API goqr.me pour générer des vrais QR codes
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Récupérer quelques cartes pour tester
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "cards LIMIT 15");
$cards = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générateur de vrais QR Codes - Gang de Monstres</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1400px;
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
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .qr-card {
            border: 2px solid #ddd;
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
            align-items: center;
            margin: 10px 0;
            min-height: 120px;
            background: white;
            padding: 10px;
            border-radius: 4px;
        }

        .qr-container img {
            image-rendering: pixelated;
            image-rendering: crisp-edges;
            width: 100%;
            max-width: 150px;
            height: auto;
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
            justify-content: center;
        }

        .size-demo {
            border: 2px solid #333;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            background: white;
            min-width: 200px;
        }

        .size-demo h3 {
            margin-top: 0;
            color: #333;
        }

        .note {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #0c5460;
        }

        .note strong {
            color: #0c5460;
        }

        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #155724;
        }

        .controls {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .controls label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .controls select, .controls input {
            padding: 8px;
            margin-left: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }

        .btn:hover {
            background: #0056b3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        table th {
            background: #f0f0f0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎴 Générateur de VRAIS QR Codes scannables</h1>

        <div class="success">
            <strong>✅ QR Codes réels:</strong> Ces QR codes sont générés par une API externe et sont 100% scannables!
            <br>Testez-les avec votre smartphone pour vérifier qu'ils fonctionnent.
        </div>

        <div class="controls">
            <h3>Paramètres de génération</h3>
            <label>
                Taille du QR Code:
                <select id="qr-size">
                    <option value="100">100x100 px (Petit)</option>
                    <option value="150" selected>150x150 px (Moyen)</option>
                    <option value="200">200x200 px (Grand)</option>
                    <option value="300">300x300 px (Très grand)</option>
                </select>
            </label>
            <label>
                Niveau de correction d'erreur:
                <select id="qr-error">
                    <option value="L" selected>L (7%) - Plus simple</option>
                    <option value="M">M (15%) - Équilibré</option>
                    <option value="Q">Q (25%) - Haute</option>
                    <option value="H">H (30%) - Maximale</option>
                </select>
            </label>
            <button class="btn" onclick="regenerateAll()">🔄 Régénérer tous les QR codes</button>
        </div>

        <!-- Comparaison de tailles -->
        <div class="size-section">
            <h2>Comparaison de Tailles (Carte ID: <?php echo $cards[0]['id']; ?>)</h2>
            <div class="comparison">
                <div class="size-demo">
                    <h3>Très Petit</h3>
                    <div class="qr-container">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?php echo $cards[0]['id']; ?>&ecc=L" alt="QR Code 80px">
                    </div>
                    <div class="card-info">80x80 px</div>
                </div>

                <div class="size-demo">
                    <h3>Petit</h3>
                    <div class="qr-container">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo $cards[0]['id']; ?>&ecc=L" alt="QR Code 100px">
                    </div>
                    <div class="card-info">100x100 px</div>
                </div>

                <div class="size-demo">
                    <h3>Moyen</h3>
                    <div class="qr-container">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $cards[0]['id']; ?>&ecc=L" alt="QR Code 150px">
                    </div>
                    <div class="card-info">150x150 px<br>⭐ Recommandé</div>
                </div>

                <div class="size-demo">
                    <h3>Grand</h3>
                    <div class="qr-container">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo $cards[0]['id']; ?>&ecc=L" alt="QR Code 200px">
                    </div>
                    <div class="card-info">200x200 px</div>
                </div>
            </div>
        </div>

        <!-- Comparaison des niveaux de correction -->
        <div class="size-section">
            <h2>Comparaison des Niveaux de Correction (150x150px)</h2>
            <div class="comparison">
                <div class="size-demo">
                    <h3>Niveau L (7%)</h3>
                    <div class="qr-container">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $cards[0]['id']; ?>&ecc=L" alt="QR Code Level L">
                    </div>
                    <div class="card-info">Le plus simple<br>Moins de modules</div>
                </div>

                <div class="size-demo">
                    <h3>Niveau M (15%)</h3>
                    <div class="qr-container">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $cards[0]['id']; ?>&ecc=M" alt="QR Code Level M">
                    </div>
                    <div class="card-info">Équilibré</div>
                </div>

                <div class="size-demo">
                    <h3>Niveau Q (25%)</h3>
                    <div class="qr-container">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $cards[0]['id']; ?>&ecc=Q" alt="QR Code Level Q">
                    </div>
                    <div class="card-info">Haute correction</div>
                </div>

                <div class="size-demo">
                    <h3>Niveau H (30%)</h3>
                    <div class="qr-container">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $cards[0]['id']; ?>&ecc=H" alt="QR Code Level H">
                    </div>
                    <div class="card-info">Le plus dense</div>
                </div>
            </div>
        </div>

        <!-- Toutes les cartes -->
        <div class="size-section">
            <h2>QR Codes pour toutes les cartes</h2>
            <div class="qr-grid" id="cards-grid">
                <?php foreach ($cards as $card): ?>
                <div class="qr-card">
                    <h3>Carte #<?php echo $card['id']; ?></h3>
                    <div class="qr-container">
                        <img class="card-qr"
                             src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $card['id']; ?>&ecc=L"
                             alt="QR Code Carte <?php echo $card['id']; ?>"
                             data-card-id="<?php echo $card['id']; ?>">
                    </div>
                    <div class="card-info">
                        <?php echo htmlspecialchars($card['card_name']); ?>
                    </div>
                    <button class="btn" onclick="downloadQR(<?php echo $card['id']; ?>, '<?php echo addslashes($card['card_name']); ?>')">
                        📥 Télécharger
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Informations -->
        <div class="size-section">
            <h2>Informations Techniques</h2>
            <table>
                <thead>
                    <tr>
                        <th>Paramètre</th>
                        <th>Valeur Recommandée</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Contenu</strong></td>
                        <td>ID numérique (ex: 42)</td>
                        <td>Juste l'ID de la carte pour un QR code minimal</td>
                    </tr>
                    <tr>
                        <td><strong>Taille</strong></td>
                        <td>150x150 px</td>
                        <td>Optimal pour l'impression sur carte</td>
                    </tr>
                    <tr>
                        <td><strong>Correction</strong></td>
                        <td>Niveau L (7%)</td>
                        <td>QR code le plus simple et compact</td>
                    </tr>
                    <tr>
                        <td><strong>Format</strong></td>
                        <td>PNG</td>
                        <td>Parfait pour l'impression</td>
                    </tr>
                    <tr>
                        <td><strong>DPI Recommandé</strong></td>
                        <td>300 DPI minimum</td>
                        <td>Pour une impression de qualité professionnelle</td>
                    </tr>
                </tbody>
            </table>

            <div class="note">
                <strong>💡 Astuce:</strong> Pour l'impression sur vos cartes, téléchargez les QR codes en haute résolution (300x300px minimum).
                <br>Pour tester si un QR code fonctionne, scannez-le avec votre smartphone!
            </div>
        </div>
    </div>

    <script>
        function regenerateAll() {
            const size = document.getElementById('qr-size').value;
            const ecc = document.getElementById('qr-error').value;

            // Régénérer tous les QR codes des cartes
            const qrImages = document.querySelectorAll('.card-qr');
            qrImages.forEach(img => {
                const cardId = img.getAttribute('data-card-id');
                const timestamp = new Date().getTime(); // Pour forcer le rechargement
                img.src = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${cardId}&ecc=${ecc}&t=${timestamp}`;
            });
        }

        function downloadQR(cardId, cardName) {
            const size = document.getElementById('qr-size').value;
            const ecc = document.getElementById('qr-error').value;

            // Créer un lien de téléchargement
            const link = document.createElement('a');
            link.href = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${cardId}&ecc=${ecc}&format=png`;
            link.download = `qr-card-${cardId}-${cardName.replace(/[^a-z0-9]/gi, '-')}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
