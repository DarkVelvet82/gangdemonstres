<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Récupérer les jeux/extensions disponibles
$stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "game_sets ORDER BY is_base_game DESC, display_order ASC");
$available_games = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une partie - Gang de Monstres</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="back-link">← Retour</a>
            <h1>✨ Créer une nouvelle partie</h1>
        </div>

        <div class="objectif-create-game">
            <form id="objectif-create-form" class="modern-form">

                <!-- Sélection du jeu et extensions -->
                <div class="form-group">
                    <label class="section-label">🎮 Configuration du jeu :</label>

                    <?php if (empty($available_games)): ?>
                        <div class="no-games-warning">
                            <p>⚠️ Aucun jeu configuré. Contactez l'administrateur.</p>
                        </div>
                    <?php else: ?>
                        <div class="game-selection">
                            <?php
                            $base_games = array_filter($available_games, function($game) { return $game['is_base_game']; });
                            $extensions = array_filter($available_games, function($game) { return !$game['is_base_game']; });
                            ?>

                            <!-- Jeux de base -->
                            <?php if (!empty($base_games)): ?>
                                <div class="base-games-section">
                                    <label class="subsection-label">Jeu de base :</label>
                                    <div class="games-grid">
                                        <?php foreach ($base_games as $game): ?>
                                            <label class="game-option base-game">
                                                <input type="radio" name="base_game" value="<?php echo $game['id']; ?>" required>
                                                <div class="game-info">
                                                    <strong><?php echo htmlspecialchars($game['name']); ?></strong>
                                                    <?php if ($game['description']): ?>
                                                        <p><?php echo htmlspecialchars($game['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Extensions -->
                            <?php if (!empty($extensions)): ?>
                                <div class="extensions-section">
                                    <label class="subsection-label">Extensions (optionnelles) :</label>
                                    <div class="games-grid">
                                        <?php foreach ($extensions as $extension): ?>
                                            <label class="game-option extension">
                                                <input type="checkbox" name="extensions[]" value="<?php echo $extension['id']; ?>">
                                                <div class="game-info">
                                                    <strong><?php echo htmlspecialchars($extension['name']); ?></strong>
                                                    <?php if ($extension['description']): ?>
                                                        <p><?php echo htmlspecialchars($extension['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Configuration des joueurs -->
                <div class="form-group">
                    <label for="objectif-player-count" class="section-label">👥 Nombre de joueurs :</label>
                    <input type="number" id="objectif-player-count" min="2" max="10" value="2" class="form-control">
                </div>

                <div class="form-group">
                    <label for="objectif-creator-name" class="section-label">🎯 Votre prénom :</label>
                    <input type="text" id="objectif-creator-name" placeholder="Entrez votre prénom" class="form-control" required>
                </div>

                <!-- Sélection rapide des joueurs fréquents (si connecté) -->
                <div id="frequent-players-section" class="form-group" style="display:none;">
                    <label class="section-label">⭐ Joueurs habituels :</label>
                    <div id="frequent-players-list" class="frequent-players-grid">
                        <!-- Rempli par JS -->
                    </div>
                    <p class="hint-text" style="font-size:13px; color:#666; margin-top:8px;">
                        Cliquez pour ajouter/retirer un joueur
                    </p>
                </div>

                <div id="other-players-names" class="form-group">
                    <label class="section-label">👫 Prénom des autres joueurs :</label>
                    <div id="other-players-inputs">
                        <input type="text" class="form-control other-player-name" placeholder="Prénom du joueur 2" required>
                    </div>
                </div>

                <button type="submit" id="objectif-create-button" class="objectif-button objectif-primary btn-create">
                    🎮 Créer la partie
                </button>
            </form>

            <div id="objectif-game-result" class="result-container"></div>
        </div>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script src="../assets/js/objectif-main.js"></script>
    <script src="../assets/js/objectif-qr.js"></script>
    <script src="../assets/js/objectif-status.js"></script>
    <script src="../assets/js/objectif-user.js"></script>
    <script src="../assets/js/objectif-creation.js"></script>

    <style>
        .frequent-players-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .frequent-player-btn {
            padding: 10px 18px;
            border: 2px solid #667eea;
            border-radius: 25px;
            background: white;
            color: #667eea;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .frequent-player-btn:hover {
            background: #f0f0ff;
        }
        .frequent-player-btn.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
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
