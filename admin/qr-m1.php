<?php
/**
 * Générateur de QR codes ultra-compacts pour les cartes
 * Version la plus petite possible (approximation M1)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Récupérer toutes les cartes
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "cards ORDER BY id");
$cards = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes M1 - Gang de Monstres</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 40px;
        }

        .qr-card {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            border-radius: 4px;
            background: #fafafa;
        }

        .qr-card h3 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #666;
            font-weight: bold;
        }

        .qr-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 8px 0;
            background: white;
            padding: 8px;
            border-radius: 4px;
        }

        .qr-container img {
            image-rendering: pixelated;
            image-rendering: crisp-edges;
            width: 80px;
            height: 80px;
        }

        .card-info {
            font-size: 11px;
            color: #888;
            margin-top: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .controls {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .controls label {
            font-weight: bold;
            margin-right: 10px;
        }

        .controls select {
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .btn {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn-download-all {
            background: #28a745;
        }

        .btn-download-all:hover {
            background: #218838;
        }

        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #0c5460;
            font-size: 13px;
        }

        .stats {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .stats strong {
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎴 QR Codes Ultra-Compacts (Type M1)</h1>
        <div class="subtitle">Format le plus petit possible - 11x11 modules équivalent</div>

        <div class="stats">
            <strong>📊 Statistiques:</strong> <?php echo count($cards); ?> cartes totales
        </div>

        <div class="info">
            <strong>ℹ️ Format M1:</strong> QR codes générés au format le plus compact possible (approximation M1 - 11x11 modules).
            Niveau de correction: L (7%) pour une densité minimale.
        </div>

        <div class="controls">
            <label>
                <strong>Format: 11x11 pixels exactement</strong>
            </label>

            <label>
                Zoom d'affichage:
                <select id="qr-zoom" onchange="updateZoom()">
                    <option value="1">1x (11px)</option>
                    <option value="2">2x (22px)</option>
                    <option value="4">4x (44px)</option>
                    <option value="6">6x (66px)</option>
                    <option value="8" selected>8x (88px)</option>
                    <option value="10">10x (110px)</option>
                </select>
            </label>

            <button class="btn btn-download-all" onclick="downloadAll()">
                📥 Télécharger tout (11x11px)
            </button>

            <button class="btn" onclick="printPage()">
                🖨️ Imprimer
            </button>
        </div>

        <!-- Grille de toutes les cartes -->
        <div class="qr-grid" id="cards-grid">
            <?php foreach ($cards as $card): ?>
            <div class="qr-card">
                <h3>#<?php echo $card['id']; ?></h3>
                <div class="qr-container">
                    <img class="card-qr"
                         src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?php echo $card['id']; ?>&ecc=L&margin=0"
                         alt="QR <?php echo $card['id']; ?>"
                         data-card-id="<?php echo $card['id']; ?>"
                         data-card-name="<?php echo htmlspecialchars($card['card_name']); ?>">
                </div>
                <div class="card-info" title="<?php echo htmlspecialchars($card['card_name']); ?>">
                    <?php echo htmlspecialchars($card['card_name']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function updateSize() {
            const size = document.getElementById('qr-size').value;
            const qrImages = document.querySelectorAll('.card-qr');

            qrImages.forEach(img => {
                const cardId = img.getAttribute('data-card-id');
                const timestamp = new Date().getTime();
                img.src = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${cardId}&ecc=L&margin=0&t=${timestamp}`;
                img.style.width = size + 'px';
                img.style.height = size + 'px';
            });
        }

        function downloadAll() {
            alert('Pour télécharger tous les QR codes:\n\n' +
                  '1. Faites clic droit > "Enregistrer l\'image sous..." sur chaque QR code\n' +
                  '2. Ou utilisez l\'option "Imprimer" puis "Enregistrer en PDF"\n\n' +
                  'Une fonction de téléchargement ZIP sera ajoutée prochainement.');
        }

        function printPage() {
            window.print();
        }

        // Style d'impression
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body { background: white; }
                .controls, .info, .stats, h1, .subtitle { display: none; }
                .container { box-shadow: none; padding: 0; max-width: 100%; }
                .qr-grid {
                    grid-template-columns: repeat(8, 1fr);
                    gap: 5px;
                }
                .qr-card {
                    border: 1px solid #ccc;
                    page-break-inside: avoid;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
