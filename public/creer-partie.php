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

                <!-- Sélection de la difficulté -->
                <div class="form-group">
                    <label for="objectif-difficulty" class="section-label">⚙️ Difficulté de la partie :</label>
                    <div class="difficulty-selection">
                        <label class="difficulty-option easy">
                            <input type="radio" name="difficulty" value="easy" required>
                            <div class="difficulty-info">
                                <span class="difficulty-icon">🟢</span>
                                <div>
                                    <strong>Facile</strong>
                                    <p>Objectifs simples, parties courtes</p>
                                </div>
                            </div>
                        </label>

                        <label class="difficulty-option normal">
                            <input type="radio" name="difficulty" value="normal" checked required>
                            <div class="difficulty-info">
                                <span class="difficulty-icon">🟡</span>
                                <div>
                                    <strong>Normal</strong>
                                    <p>Équilibre parfait entre défi et plaisir</p>
                                </div>
                            </div>
                        </label>

                        <label class="difficulty-option hard">
                            <input type="radio" name="difficulty" value="hard" required>
                            <div class="difficulty-info">
                                <span class="difficulty-icon">🔴</span>
                                <div>
                                    <strong>Difficile</strong>
                                    <p>Objectifs challenging, parties longues</p>
                                </div>
                            </div>
                        </label>
                    </div>
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
    <script src="../assets/js/objectif-creation.js"></script>
</body>
</html>
