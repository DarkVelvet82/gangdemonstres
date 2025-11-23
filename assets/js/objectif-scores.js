// js/objectif-scores.js - Module scores (VERSION FINALE CORRIGÉE)
window.ObjectifScores = (function($) {
    'use strict';

    // Variable pour tracker l'état de la partie
    let gameEnded = false;

    function displayCreatorManagementButtons(gameId) {
        console.log('🎮 Affichage des boutons de gestion créateur');

        // Supprimer les anciens éléments
        $('#creator-management, #end-game-modal, #post-game-modal, #scores-modal').remove();
        // Supprimer aussi les éléments du drawer mobile
        $('.management-sticky-button, .management-drawer, .management-drawer-overlay').remove();

        // Afficher les boutons selon l'état de la partie
        $('#objectif-state').append(generateManagementHTML());

        // Ajouter le drawer mobile
        addMobileDrawer();

        // Ajouter les modales
        addAllModals();
    }

    // Générer le HTML du drawer mobile
    function addMobileDrawer() {
        // Ajouter la classe use-drawer au bloc de gestion pour le masquer sur mobile
        $('#creator-management').addClass('use-drawer');

        // Ajouter le bouton sticky
        const stickyButtonHtml = `
            <button class="management-sticky-button visible" id="open-management-drawer">
                Gestion de la partie
            </button>
        `;
        $('body').append(stickyButtonHtml);

        // Ajouter l'overlay
        $('body').append('<div class="management-drawer-overlay"></div>');

        // Ajouter le drawer
        const drawerHtml = `
            <div class="management-drawer">
                <div class="drawer-handle"></div>
                <div class="drawer-header">
                    <h4>${gameEnded ? '🎯 Partie terminée' : '🎮 Gestion de la partie'}</h4>
                    <button class="drawer-close-btn" id="close-management-drawer">✕</button>
                </div>
                <div class="drawer-content">
                    ${generateDrawerContentHTML()}
                </div>
            </div>
        `;
        $('body').append(drawerHtml);
    }

    // Générer le contenu du drawer selon l'état de la partie
    function generateDrawerContentHTML() {
        if (gameEnded) {
            return `
                <p>La partie est terminée. Que souhaitez-vous faire ?</p>
                <div class="management-buttons">
                    <button id="drawer-new-game-button" class="objectif-button objectif-primary">
                        🔄 Nouvelle partie
                    </button>
                    <button id="drawer-quit-session-button" class="objectif-button objectif-secondary">
                        🚪 Quitter la session
                    </button>
                </div>
                <div class="secondary-buttons">
                    <button id="drawer-view-scores-button" class="objectif-button objectif-secondary">
                        🏆 Voir les scores
                    </button>
                </div>
            `;
        } else {
            return `
                <p>En tant que créateur, vous pouvez gérer cette partie :</p>
                <div class="management-buttons">
                    <button id="drawer-end-game-button" class="objectif-button objectif-primary">
                        🏆 Terminer la partie
                    </button>
                </div>
                <div class="secondary-buttons">
                    <button id="drawer-check-status-button" class="objectif-button objectif-secondary">
                        📊 Vérifier le statut
                    </button>
                    <button id="drawer-view-scores-button" class="objectif-button objectif-secondary">
                        🏆 Voir les scores
                    </button>
                </div>
            `;
        }
    }

    // Ouvrir le drawer
    function openDrawer() {
        $('.management-drawer-overlay').addClass('active');
        $('.management-drawer').addClass('open');
        $('body').css('overflow', 'hidden'); // Empêcher le scroll du body
    }

    // Fermer le drawer
    function closeDrawer() {
        $('.management-drawer-overlay').removeClass('active');
        $('.management-drawer').removeClass('open');
        $('body').css('overflow', ''); // Réactiver le scroll
    }

    // Mettre à jour le drawer après fin de partie
    function updateDrawerContent() {
        $('.management-drawer .drawer-header h4').text(gameEnded ? '🎯 Partie terminée' : '🎮 Gestion de la partie');
        $('.management-drawer .drawer-content').html(generateDrawerContentHTML());
    }

    function generateManagementHTML() {
        if (gameEnded) {
            // Si la partie est terminée, afficher les options post-game
            return `
                <div id="creator-management" class="creator-management">
                    <h4>🎯 Partie terminée</h4>
                    <p>La partie est terminée. Que souhaitez-vous faire ?</p>
                    
                    <div class="management-buttons">
                        <button id="new-game-button" class="objectif-button objectif-primary">
                            🔄 Nouvelle partie
                        </button>
                        <button id="quit-session-button" class="objectif-button objectif-secondary">
                            🚪 Quitter la session
                        </button>
                    </div>
                    
                    <div style="margin-top:15px;">
                        <button id="view-scores-button" class="objectif-button objectif-secondary">
                            🏆 Voir les scores
                        </button>
                    </div>
                </div>
            `;
        } else {
            // Partie en cours, afficher le bouton terminer
            return `
                <div id="creator-management" class="creator-management">
                    <h4>🎮 Gestion de la partie</h4>
                    <p>En tant que créateur, vous pouvez gérer cette partie :</p>
                    
                    <div class="management-buttons">
                        <button id="end-game-with-scores-button" class="objectif-button objectif-primary">
                            🏆 Terminer la partie
                        </button>
                    </div>
                    
                    <div style="margin-top:15px;">
                        <button id="check-status-button" class="objectif-button objectif-secondary">
                            📊 Vérifier le statut
                        </button>
                        <button id="view-scores-button" class="objectif-button objectif-secondary">
                            🏆 Voir les scores
                        </button>
                    </div>
                </div>
            `;
        }
    }

    function addAllModals() {
        // Modal de fin de partie (sélection gagnant)
        $('#objectif-state').append(`
            <div id="end-game-modal" class="objectif-modal" style="display:none;">
                <div class="objectif-modal-content" style="max-width: 500px;">
                    <h3>🏆 Fin de partie</h3>
                    <p>Qui a gagné cette partie ? (Sélectionnez un seul gagnant)</p>
                    
                    <div id="players-list-loading">⏳ Chargement des joueurs...</div>
                    <div id="players-list" style="display:none;">
                        <!-- Les joueurs seront chargés ici -->
                    </div>
                    
                    <div class="objectif-modal-buttons" style="margin-top: 20px;">
                        <button id="confirm-end-game" class="objectif-button objectif-primary" disabled>
                            ✅ Terminer avec ce gagnant
                        </button>
                        <button id="cancel-end-game" class="objectif-button objectif-cancel">
                            ❌ Annuler
                        </button>
                    </div>
                </div>
            </div>
        `);

        // Modal APRÈS fin de partie
        $('#objectif-state').append(`
            <div id="post-game-modal" class="objectif-modal" style="display:none;">
                <div class="objectif-modal-content">
                    <h3>🎯 Partie terminée !</h3>
                    <div id="winner-announcement"></div>
                    <p>Que souhaitez-vous faire maintenant ?</p>
                    <div class="objectif-modal-buttons">
                        <button id="new-game-from-modal" class="objectif-button objectif-primary">
                            🔄 Nouvelle partie
                        </button>
                        <button id="quit-session-from-modal" class="objectif-button objectif-secondary">
                            🚪 Quitter la session
                        </button>
                        <button id="view-scores-from-end" class="objectif-button objectif-secondary">
                            🏆 Voir les scores
                        </button>
                        <button id="stay-in-game" class="objectif-button objectif-cancel">
                            🎮 Rester sur cette partie
                        </button>
                    </div>
                </div>
            </div>
        `);

        // Modal scores
        $('#objectif-state').append(`
            <div id="scores-modal" class="objectif-modal" style="display:none;">
                <div class="objectif-modal-content" style="max-width: 700px; max-height: 80vh; overflow-y: auto;">
                    <h3>🏆 Tableau des scores</h3>
                    
                    <div class="scores-filters" style="margin-bottom: 20px;">
                        <input type="text" id="player-filter" placeholder="Filtrer par nom de joueur..." style="padding: 8px; width: 200px; margin-right: 10px;">
                        <button id="refresh-scores" class="objectif-button objectif-secondary">🔄 Actualiser</button>
                    </div>
                    
                    <div id="scores-loading">⏳ Chargement des scores...</div>
                    <div id="scores-content" style="display:none;">
                        <div id="scores-table"></div>
                        <div id="recent-games" style="margin-top: 30px;"></div>
                    </div>
                    
                    <div class="objectif-modal-buttons" style="margin-top: 20px;">
                        <button id="close-scores" class="objectif-button objectif-cancel">❌ Fermer</button>
                    </div>
                </div>
            </div>
        `);
    }

    // Event handlers pour les boutons
    $(document).on('click', '#end-game-with-scores-button', function() {
        $('#end-game-modal').fadeIn(300);
        loadPlayersForEndGame();
    });

    $(document).on('click', '#view-scores-button, #view-scores-from-end', function() {
        $('#scores-modal').fadeIn(300);
        loadScores();
    });

    $(document).on('click', '#cancel-end-game', function() {
        $('#end-game-modal').fadeOut(300);
    });

    $(document).on('click', '#close-scores', function() {
        $('#scores-modal').fadeOut(300);
    });

    $(document).on('click', '#check-status-button', function() {
        checkGameStatus();
    });

    // Gestion des boutons de la modal post-game
    $(document).on('click', '#new-game-from-modal, #new-game-button', function() {
        $('.objectif-modal').fadeOut(300);
        restartGame();
    });

    $(document).on('click', '#quit-session-from-modal, #quit-session-button', function() {
        $('.objectif-modal').fadeOut(300);
        quitSession();
    });

    $(document).on('click', '#stay-in-game', function() {
        $('#post-game-modal').fadeOut(300);
        // Rester dans l'état "partie terminée" mais sans modal
        updateManagementButtonsAfterGameEnd();
    });

    // ==========================================
    // Event handlers pour le drawer mobile
    // ==========================================

    // Ouvrir le drawer
    $(document).on('click', '#open-management-drawer', function() {
        openDrawer();
    });

    // Fermer le drawer (bouton X)
    $(document).on('click', '#close-management-drawer', function() {
        closeDrawer();
    });

    // Fermer le drawer en cliquant sur l'overlay
    $(document).on('click', '.management-drawer-overlay', function() {
        closeDrawer();
    });

    // Boutons du drawer - Terminer la partie
    $(document).on('click', '#drawer-end-game-button', function() {
        closeDrawer();
        $('#end-game-modal').fadeIn(300);
        loadPlayersForEndGame();
    });

    // Boutons du drawer - Vérifier le statut
    $(document).on('click', '#drawer-check-status-button', function() {
        closeDrawer();
        checkGameStatus();
    });

    // Boutons du drawer - Voir les scores
    $(document).on('click', '#drawer-view-scores-button', function() {
        closeDrawer();
        $('#scores-modal').fadeIn(300);
        loadScores();
    });

    // Boutons du drawer - Nouvelle partie
    $(document).on('click', '#drawer-new-game-button', function() {
        closeDrawer();
        restartGame();
    });

    // Boutons du drawer - Quitter la session
    $(document).on('click', '#drawer-quit-session-button', function() {
        closeDrawer();
        quitSession();
    });

    // ==========================================

    // Charger les VRAIS joueurs de la partie
    function loadPlayersForEndGame() {
        const gameId = localStorage.getItem('objectif_game_id');

        $('#players-list-loading').show();
        $('#players-list').hide();

        const ajaxStartTime = performance.now();
        console.log('⏱️ [PERF] Début chargement joueurs...');

        // Appel AJAX pour récupérer les vrais joueurs
        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_get_game_players',
                nonce: objectif_ajax.nonce,
                game_id: gameId
            },
            success: function(response) {
                const ajaxDuration = Math.round(performance.now() - ajaxStartTime);
                console.log(`⏱️ [PERF] Joueurs chargés en ${ajaxDuration}ms`);

                if (response.success) {
                    displayPlayersForSelection(response.data.players);
                } else {
                    displayDefaultPlayers();
                }
            },
            error: function() {
                const ajaxDuration = Math.round(performance.now() - ajaxStartTime);
                console.error(`⏱️ [PERF] Échec chargement joueurs après ${ajaxDuration}ms`);
                displayDefaultPlayers();
            }
        });
    }

    function displayPlayersForSelection(players) {
        $('#players-list-loading').hide();
        $('#players-list').show();
        
        let playersHtml = `
            <div class="players-selection">
                <p style="margin-bottom: 15px;"><strong>Sélectionnez le gagnant :</strong></p>
                <div id="players-checkboxes">
        `;
        
        players.forEach(function(player) {
            playersHtml += `
                <label class="player-checkbox">
                    <input type="radio" name="winner" value="${player.id}" data-player-name="${player.player_name}">
                    <span>${player.player_name}</span>
                </label>
            `;
        });
        
        playersHtml += `
                </div>
            </div>
        `;
        
        $('#players-list').html(playersHtml);
    }

    function displayDefaultPlayers() {
        $('#players-list-loading').hide();
        $('#players-list').show().html(`
            <div class="players-selection">
                <p style="margin-bottom: 15px;"><strong>Sélectionnez le gagnant :</strong></p>
                <div id="players-checkboxes">
                    <label class="player-checkbox">
                        <input type="radio" name="winner" value="1" data-player-name="Joueur 1">
                        <span>Joueur 1</span>
                    </label>
                    <label class="player-checkbox">
                        <input type="radio" name="winner" value="2" data-player-name="Joueur 2">
                        <span>Joueur 2</span>
                    </label>
                </div>
            </div>
        `);
    }

    // Gestion de la sélection du gagnant
    $(document).on('change', 'input[name="winner"]', function() {
        const selectedCount = $('input[name="winner"]:checked').length;
        $('#confirm-end-game').prop('disabled', selectedCount === 0);
    });

    $(document).on('click', '#confirm-end-game', function() {
        endGameWithScores();
    });

    function endGameWithScores() {
        const gameId = localStorage.getItem('objectif_game_id');
        const selectedWinner = $('input[name="winner"]:checked');
        
        if (selectedWinner.length === 0) {
            alert('Veuillez sélectionner un gagnant.');
            return;
        }
        
        const winnerId = selectedWinner.val();
        const winnerName = selectedWinner.data('player-name');
        
        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_end_game_with_scores',
                nonce: objectif_ajax.nonce,
                game_id: gameId,
                winners: [winnerId]
            },
            success: function(response) {
                if (response.success) {
                    // Marquer la partie comme terminée
                    gameEnded = true;
                    
                    $('#end-game-modal').fadeOut(300);

                    // Afficher la modal post-game avec le nom du gagnant
                    $('#winner-announcement').html(`
                        <div style="background: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #28a745;">
                            <strong>🏆 Gagnant : ${winnerName}</strong>
                        </div>
                    `);

                    // Désactiver les boutons pendant 10 secondes pour laisser les joueurs voir leur notification
                    disablePostGameButtons();

                    $('#post-game-modal').fadeIn(300);
                } else {
                    alert('Erreur lors de la sauvegarde des scores : ' + response.data);
                }
            },
            error: function() {
                alert('Erreur de connexion lors de la sauvegarde des scores.');
            }
        });
    }

    // Désactiver les boutons "Nouvelle partie" et "Quitter" pendant 10 secondes
    // pour laisser aux joueurs le temps de voir leur notification de fin de partie
    function disablePostGameButtons() {
        const $newGameBtn = $('#new-game-from-modal');
        const $quitBtn = $('#quit-session-from-modal');
        const waitTime = 10; // secondes

        // Désactiver les boutons
        $newGameBtn.prop('disabled', true);
        $quitBtn.prop('disabled', true);

        // Ajouter un style visuel pour montrer qu'ils sont désactivés
        $newGameBtn.css('opacity', '0.5');
        $quitBtn.css('opacity', '0.5');

        // Afficher un message d'attente
        const $waitMessage = $('<p id="wait-message" style="color: #666; font-size: 14px; margin-bottom: 15px; text-align: center;"></p>');
        $('#winner-announcement').after($waitMessage);

        let remaining = waitTime;
        $waitMessage.text(`⏳ Patientez ${remaining}s que les joueurs voient leur résultat...`);

        const countdown = setInterval(() => {
            remaining--;
            if (remaining > 0) {
                $waitMessage.text(`⏳ Patientez ${remaining}s que les joueurs voient leur résultat...`);
            } else {
                clearInterval(countdown);
                // Réactiver les boutons
                $newGameBtn.prop('disabled', false).css('opacity', '1');
                $quitBtn.prop('disabled', false).css('opacity', '1');
                $waitMessage.fadeOut(300, function() { $(this).remove(); });
            }
        }, 1000);
    }

    function updateManagementButtonsAfterGameEnd() {
        // Mettre à jour l'affichage des boutons pour refléter l'état "partie terminée"
        const gameId = localStorage.getItem('objectif_game_id');
        $('#creator-management').replaceWith(generateManagementHTML());
        // Ajouter la classe use-drawer pour le masquer sur mobile
        $('#creator-management').addClass('use-drawer');
        // Mettre à jour aussi le drawer mobile
        updateDrawerContent();
    }

    function loadScores() {
        const playerFilter = $('#player-filter').val();

        $('#scores-loading').show();
        $('#scores-content').hide();

        const ajaxStartTime = performance.now();
        console.log('⏱️ [PERF] Début chargement scores...');

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_get_scores',
                nonce: objectif_ajax.nonce,
                player_filter: playerFilter,
                limit: 50
            },
            success: function(response) {
                const ajaxDuration = Math.round(performance.now() - ajaxStartTime);
                console.log(`⏱️ [PERF] Scores chargés en ${ajaxDuration}ms`);

                $('#scores-loading').hide();
                $('#scores-content').show();

                if (response.success) {
                    displayScores(response.data.scores, response.data.recent_games);
                } else {
                    $('#scores-content').html('<p>❌ Erreur lors du chargement des scores</p>');
                }
            },
            error: function() {
                const ajaxDuration = Math.round(performance.now() - ajaxStartTime);
                console.error(`⏱️ [PERF] Échec scores après ${ajaxDuration}ms`);
                $('#scores-loading').hide();
                $('#scores-content').show().html('<p>❌ Erreur de connexion</p>');
            }
        });
    }

    function displayScores(scores, recentGames) {
        let scoresHtml = `
            <h4>🏆 Classement général</h4>
            <table class="scores-table">
                <thead>
                    <tr>
                        <th>🏅</th>
                        <th>Joueur</th>
                        <th>Victoires</th>
                        <th>Parties</th>
                        <th>% Victoires</th>
                        <th>Dernière partie</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        if (scores.length === 0) {
            scoresHtml += `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; color: #666;">
                        🏆 Aucun score enregistré pour le moment
                    </td>
                </tr>
            `;
        } else {
            scores.forEach((score, index) => {
                const rank = index + 1;
                const rankEmoji = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : rank.toString();
                const lastGame = new Date(score.last_game).toLocaleDateString();
                
                scoresHtml += `
                    <tr>
                        <td style="text-align: center;">${rankEmoji}</td>
                        <td><strong>${score.player_name}</strong></td>
                        <td style="text-align: center;">${score.total_wins}</td>
                        <td style="text-align: center;">${score.total_games}</td>
                        <td style="text-align: center;">${score.win_percentage}%</td>
                        <td style="text-align: center;">${lastGame}</td>
                    </tr>
                `;
            });
        }
        
        scoresHtml += '</tbody></table>';
        $('#scores-table').html(scoresHtml);
    }

    function restartGame() {
        const gameId = localStorage.getItem('objectif_game_id');

        const ajaxStartTime = performance.now();
        console.log('⏱️ [PERF] Début restart game...');

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_restart_game',
                nonce: objectif_ajax.nonce,
                game_id: gameId
            },
            success: function(response) {
                const ajaxDuration = Math.round(performance.now() - ajaxStartTime);
                console.log(`⏱️ [PERF] Restart terminé en ${ajaxDuration}ms`);

                if (response.success) {
                    // Réinitialiser l'état de la partie
                    gameEnded = false;

                    // Incrémenter le compteur de parties jouées (pour messages d'encouragement)
                    const gamesPlayed = parseInt(localStorage.getItem('objectif_games_played') || '0');
                    localStorage.setItem('objectif_games_played', gamesPlayed + 1);

                    $('#objectif-state').html(`
                        <div class="objectif-restart-success">
                            <h3>🔄 Nouvelle partie lancée !</h3>
                            <p>Tous les joueurs peuvent maintenant générer de nouveaux objectifs.</p>
                            <button id="objectif-generate-button" class="objectif-button objectif-primary">
                                🎯 Générer mon nouvel objectif
                            </button>
                        </div>
                    `);
                } else {
                    alert('Erreur lors du redémarrage : ' + response.data);
                }
            },
            error: function() {
                const ajaxDuration = Math.round(performance.now() - ajaxStartTime);
                console.error(`⏱️ [PERF] Échec restart après ${ajaxDuration}ms`);
                alert('Erreur lors du redémarrage de la partie.');
            }
        });
    }

    function quitSession() {
        const gameId = localStorage.getItem('objectif_game_id');
        const playerId = localStorage.getItem('objectif_player_id');
        const isCreator = localStorage.getItem('objectif_is_creator') === '1';

        // Si c'est le créateur, notifier les autres joueurs via l'API
        if (isCreator && gameId && playerId) {
            $.ajax({
                method: 'POST',
                url: objectif_ajax.ajax_url,
                data: {
                    action: 'objectif_close_session',
                    nonce: objectif_ajax.nonce,
                    game_id: gameId,
                    player_id: playerId
                },
                success: function(response) {
                    console.log('🚪 Session fermée:', response);
                },
                error: function() {
                    console.log('⚠️ Erreur lors de la fermeture de session');
                }
            });
        }

        // Nettoyer le localStorage
        localStorage.removeItem('objectif_player_id');
        localStorage.removeItem('objectif_game_id');
        localStorage.removeItem('objectif_is_creator');

        // Masquer le bloc de génération d'objectif
        $('.objective-generator').hide();

        $('#objectif-state').html(`
            <div class="objectif-quit-success">
                <h3>👋 Session terminée</h3>
                <p>Merci d'avoir joué !</p>
                <a href="index.php" class="objectif-button objectif-primary">🏠 Retour à l'accueil</a>
            </div>
        `);
    }

    function checkGameStatus() {
        const gameId = localStorage.getItem('objectif_game_id');
        
        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_check_game_status',
                nonce: objectif_ajax.nonce,
                game_id: gameId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data;
                    alert(`📊 Statut de la partie:\n\n${status.status_message}\n\n👥 Joueurs connectés: ${status.connected_players}/${status.total_players}\n🎯 Objectifs générés: ${status.players_with_objectives}/${status.total_players}`);
                } else {
                    alert('Erreur: ' + response.data);
                }
            },
            error: function() {
                alert('Erreur de connexion.');
            }
        });
    }

    // Event handlers pour les scores
    $(document).on('click', '#refresh-scores', function() {
        loadScores();
    });

    $(document).on('input', '#player-filter', function() {
        clearTimeout(window.scoresFilterTimeout);
        window.scoresFilterTimeout = setTimeout(() => {
            loadScores();
        }, 500);
    });

    return {
        displayCreatorManagementButtons,
        loadScores
    };

})(jQuery);