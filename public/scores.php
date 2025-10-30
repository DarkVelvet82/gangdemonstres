<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scores - Gang de Monstres</title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .scores-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .filter-section button:hover {
            background: #5568d3;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="scores-container">
        <div class="header">
            <a href="index.php" class="back-link">← Retour</a>
            <h1 class="scores-header">🏆 Tableau des scores</h1>
        </div>

        <div class="filter-section">
            <input type="text" id="player-filter" placeholder="Rechercher un joueur...">
            <button id="filter-btn">Rechercher</button>
            <button id="reset-btn" style="background: #6c757d;">Réinitialiser</button>
        </div>

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
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script>
        jQuery(document).ready(function($) {
            function loadScores(playerFilter = '') {
                $('#scores-tbody').html('<tr><td colspan="6" class="loading">Chargement...</td></tr>');

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
                        }
                    },
                    error: function() {
                        $('#scores-tbody').html('<tr><td colspan="6" class="loading">Erreur de chargement</td></tr>');
                    }
                });
            }

            function displayScores(scores) {
                let html = '';

                if (scores.length === 0) {
                    html = '<tr><td colspan="6" class="loading">Aucun score enregistré</td></tr>';
                } else {
                    scores.forEach((score, index) => {
                        const rank = index + 1;
                        const rankClass = rank <= 3 ? `rank-${rank}` : '';

                        html += `
                            <tr>
                                <td class="rank ${rankClass}">${rank}</td>
                                <td><strong>${escapeHtml(score.player_name)}</strong></td>
                                <td>${score.total_games}</td>
                                <td>${score.total_wins}</td>
                                <td class="win-percentage">${score.win_percentage}%</td>
                                <td>${formatDate(score.last_game)}</td>
                            </tr>
                        `;
                    });
                }

                $('#scores-tbody').html(html);
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
