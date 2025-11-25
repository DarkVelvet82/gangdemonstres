<?php
require_once __DIR__ . '/../includes/front-header.php';
require_once __DIR__ . '/../includes/functions.php';

// Récupérer les jeux/extensions disponibles
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "game_sets ORDER BY is_base_game DESC, display_order ASC");
$available_games = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Créer une partie - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <style>
        .objectif-create-game {
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="objectif-create-game">
            <?php echo render_page_header('Créer une nouvelle partie', 'index.php'); ?>
            <form id="objectif-create-form" class="modern-form">

                <!-- Sélection du jeu et extensions -->
                <div class="form-group">
                    <label class="section-label">Jeux et extensions :</label>

                    <?php if (empty($available_games)): ?>
                        <p class="no-games-warning">Aucun jeu configuré. Contactez l'administrateur.</p>
                    <?php else: ?>
                        <div class="game-cards">
                            <?php foreach ($available_games as $game): ?>
                                <label class="game-card <?php echo $game['is_base_game'] ? 'checked' : ''; ?>">
                                    <input type="checkbox" name="games[]" value="<?php echo $game['id']; ?>"
                                           data-is-base="<?php echo $game['is_base_game'] ? '1' : '0'; ?>"
                                           data-bonus-players="<?php echo (int)($game['bonus_players'] ?? ($game['is_base_game'] ? 4 : 2)); ?>"
                                           <?php echo $game['is_base_game'] ? 'checked' : ''; ?>>
                                    <?php if (!empty($game['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($game['image_url']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>" class="game-card-img">
                                    <?php else: ?>
                                        <div class="game-card-placeholder">
                                            <span><?php echo mb_substr($game['name'], 0, 1); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <span class="game-card-name"><?php echo htmlspecialchars($game['name']); ?></span>
                                    <?php if (!$game['is_base_game']): ?>
                                        <span class="game-card-badge">Ext.</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Configuration des joueurs -->
                <div class="form-group">
                    <label for="objectif-player-count" class="section-label">Nombre de joueurs :</label>
                    <div class="qty-selector">
                        <button type="button" class="qty-btn qty-minus" id="qty-minus">−</button>
                        <input type="number" id="objectif-player-count" min="2" max="4" value="2" class="form-control qty-input" readonly>
                        <button type="button" class="qty-btn qty-plus" id="qty-plus">+</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="objectif-creator-name" class="section-label">Votre prénom :</label>
                    <input type="text" id="objectif-creator-name" placeholder="Entrez votre prénom" class="form-control" required>
                </div>

                <!-- Sélection rapide des joueurs fréquents (si connecté) -->
                <div id="frequent-players-section" class="form-group" style="display:none;">
                    <label class="section-label">Joueurs habituels :</label>
                    <div id="frequent-players-list" class="frequent-players-grid">
                        <!-- Rempli par JS -->
                    </div>
                    <p class="hint-text" style="font-size:13px; color:#666; margin-top:8px;">
                        Cliquez pour ajouter/retirer un joueur
                    </p>
                </div>

                <div id="other-players-names" class="form-group">
                    <label class="section-label">Prénom des autres joueurs :</label>
                    <div id="other-players-inputs">
                        <input type="text" class="form-control other-player-name" placeholder="Prénom du joueur 2" required>
                    </div>
                </div>

                <button type="submit" id="objectif-create-button" class="objectif-button objectif-primary btn-create">
                    Créer la partie
                </button>
            </form>

            <div id="objectif-game-result" class="result-container"></div>
        </div>
    </div>

    <!-- Bouton sticky mobile pour créer la partie -->
    <div class="btn-create-wrapper">
        <button type="submit" form="objectif-create-form" class="objectif-button objectif-primary btn-create">
            Créer la partie
        </button>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script src="../assets/js/modal-component.js"></script>
    <script src="../assets/js/objectif-main.js"></script>
    <script src="../assets/js/objectif-qr.js"></script>
    <script src="../assets/js/objectif-status.js"></script>
    <script src="../assets/js/objectif-user.js"></script>
    <script src="../assets/js/objectif-creation.js"></script>

    <style>
        .back-arrow {
            display: inline-block;
            font-size: 24px;
            color: #003f53;
            text-decoration: none;
            margin-bottom: 15px;
        }
        .back-arrow:hover {
            color: #002a38;
        }
        .frequent-players-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .frequent-player-btn {
            padding: 10px 18px;
            border: 2px solid #003f53;
            border-radius: 25px;
            background: white;
            color: #003f53;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .frequent-player-btn:hover {
            background: #f0f8fa;
        }
        .frequent-player-btn.selected {
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            color: white;
            border-color: transparent;
        }
        .user-login-prompt {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .user-login-prompt a {
            color: #003f53;
            font-weight: 600;
        }
        .logged-in-badge {
            background: #d4edda;
            color: #155724;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 15px;
            display: inline-block;
        }
    </style>
</body>
</html>
