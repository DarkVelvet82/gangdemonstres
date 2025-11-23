<?php
require_once __DIR__ . '/../includes/front-header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scores - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .scores-container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3), inset 0 0 30px rgba(0,0,0,0.15);
            padding: 40px;
            border: 3px solid #eddeb6;
        }

        .scores-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .scores-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .scores-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .scores-table th {
            background: linear-gradient(135deg, #003f53 0%, #003547 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .scores-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e1e4e8;
        }

        .scores-table tr:hover {
            background: #f7f8fa;
        }

        .rank {
            font-size: 1.2em;
            font-weight: bold;
        }

        .rank-1 { color: #FFD700; }
        .rank-2 { color: #C0C0C0; }
        .rank-3 { color: #CD7F32; }

        .win-percentage {
            font-weight: 600;
            color: #28a745;
        }

        .filter-section {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-section input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e1e4e8;
            border-radius: 8px;
            font-size: 1em;
        }

        .filter-section button {
            padding: 10px 20px;
            background: #003f53;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .filter-section button:hover {
            background: #002a38;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .scores-container {
                padding: 20px;
                border-radius: 0;
                box-shadow: none;
                border: none;
            }

            .filter-section {
                flex-wrap: wrap;
            }

            .filter-section input {
                width: 100%;
                flex: none;
            }

            .filter-section button {
                flex: 1;
            }

            /* Cacher le tableau classique sur mobile */
            .scores-table table {
                display: none;
            }

            /* Afficher les cards sur mobile */
            .scores-cards {
                display: block;
            }

            .score-card {
                background: #f7f8fa;
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 12px;
                border-left: 4px solid #003f53;
            }

            .score-card.rank-1 {
                border-left-color: #FFD700;
                background: linear-gradient(135deg, #fffdf0 0%, #fff9e6 100%);
            }

            .score-card.rank-2 {
                border-left-color: #C0C0C0;
                background: linear-gradient(135deg, #f8f8f8 0%, #f0f0f0 100%);
            }

            .score-card.rank-3 {
                border-left-color: #CD7F32;
                background: linear-gradient(135deg, #fdf6f0 0%, #f9efe6 100%);
            }

            .score-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }

            .score-card-rank {
                font-size: 1.5em;
                font-weight: bold;
                color: #003f53;
            }

            .score-card.rank-1 .score-card-rank { color: #FFD700; }
            .score-card.rank-2 .score-card-rank { color: #C0C0C0; }
            .score-card.rank-3 .score-card-rank { color: #CD7F32; }

            .score-card-name {
                font-size: 1.2em;
                font-weight: 600;
                color: #333;
            }

            .score-card-stats {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .score-card-stat {
                font-size: 0.9em;
            }

            .score-card-stat-label {
                color: #666;
            }

            .score-card-stat-value {
                font-weight: 600;
                color: #333;
            }

            .score-card-stat-value.win-rate {
                color: #28a745;
            }
        }

        @media (min-width: 769px) {
            .scores-cards {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="scores-container">
        <?php echo render_page_header('Tableau des scores', 'compte.php'); ?>

        <div class="filter-section">
            <input type="text" id="player-filter" placeholder="Rechercher un joueur...">
            <button id="filter-btn">Rechercher</button>
            <button id="reset-btn" style="background: #6c757d;">Réinitialiser</button>
        </div>

        <!-- Tableau desktop -->
        <div class="scores-table">
            <table>
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Joueur</th>
                        <th>Parties jouées</th>
                        <th>Victoires</th>
                        <th>% Victoires</th>
                        <th>Dernière partie</th>
                    </tr>
                </thead>
                <tbody id="scores-tbody">
                    <tr>
                        <td colspan="6" class="loading">Chargement des scores...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Cards mobile -->
        <div class="scores-cards" id="scores-cards">
            <div class="loading">Chargement des scores...</div>
        </div>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script>
        jQuery(document).ready(function($) {
            function loadScores(playerFilter = '') {
                $('#scores-tbody').html('<tr><td colspan="6" class="loading">Chargement...</td></tr>');
                $('#scores-cards').html('<div class="loading">Chargement...</div>');

                $.ajax({
                    url: objectif_ajax.ajax_url + 'scores.php?action=get',
                    type: 'POST',
                    data: {
                        nonce: objectif_ajax.nonce,
                        limit: 100,
                        player_filter: playerFilter
                    },
                    success: function(response) {
                        if (response.success && response.data.scores) {
                            displayScores(response.data.scores);
                        } else {
                            $('#scores-tbody').html('<tr><td colspan="6" class="loading">Aucun score trouvé</td></tr>');
                            $('#scores-cards').html('<div class="loading">Aucun score trouvé</div>');
                        }
                    },
                    error: function() {
                        $('#scores-tbody').html('<tr><td colspan="6" class="loading">Erreur de chargement</td></tr>');
                        $('#scores-cards').html('<div class="loading">Erreur de chargement</div>');
                    }
                });
            }

            function displayScores(scores) {
                let tableHtml = '';
                let cardsHtml = '';

                if (scores.length === 0) {
                    tableHtml = '<tr><td colspan="6" class="loading">Aucun score enregistré</td></tr>';
                    cardsHtml = '<div class="loading">Aucun score enregistré</div>';
                } else {
                    scores.forEach((score, index) => {
                        const rank = index + 1;
                        const rankClass = rank <= 3 ? `rank-${rank}` : '';

                        // Version tableau (desktop)
                        tableHtml += `
                            <tr>
                                <td class="rank ${rankClass}">${rank}</td>
                                <td><strong>${escapeHtml(score.player_name)}</strong></td>
                                <td>${score.total_games}</td>
                                <td>${score.total_wins}</td>
                                <td class="win-percentage">${score.win_percentage}%</td>
                                <td>${formatDate(score.last_game)}</td>
                            </tr>
                        `;

                        // Version cards (mobile)
                        cardsHtml += `
                            <div class="score-card ${rankClass}">
                                <div class="score-card-header">
                                    <span class="score-card-rank">#${rank}</span>
                                    <span class="score-card-name">${escapeHtml(score.player_name)}</span>
                                </div>
                                <div class="score-card-stats">
                                    <div class="score-card-stat">
                                        <span class="score-card-stat-label">Parties</span>
                                        <span class="score-card-stat-value">${score.total_games}</span>
                                    </div>
                                    <div class="score-card-stat">
                                        <span class="score-card-stat-label">Victoires</span>
                                        <span class="score-card-stat-value">${score.total_wins}</span>
                                    </div>
                                    <div class="score-card-stat">
                                        <span class="score-card-stat-label">Taux</span>
                                        <span class="score-card-stat-value win-rate">${score.win_percentage}%</span>
                                    </div>
                                    <div class="score-card-stat">
                                        <span class="score-card-stat-label">Dernière</span>
                                        <span class="score-card-stat-value">${formatDateShort(score.last_game)}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }

                $('#scores-tbody').html(tableHtml);
                $('#scores-cards').html(cardsHtml);
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function formatDateShort(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit'
                });
            }

            // Event listeners
            $('#filter-btn').on('click', function() {
                const filter = $('#player-filter').val();
                loadScores(filter);
            });

            $('#reset-btn').on('click', function() {
                $('#player-filter').val('');
                loadScores();
            });

            $('#player-filter').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#filter-btn').click();
                }
            });

            // Charger les scores au démarrage
            loadScores();
        });
    </script>
</body>
</html>
