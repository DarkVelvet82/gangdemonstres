// js/objectif-partie.js - Module pour la page partie.php
window.ObjectifPartie = (function($) {
    'use strict';

    let gameId = null;
    let playersData = [];
    let statusInterval = null;

    // Initialisation au chargement
    $(document).ready(function() {
        gameId = window.partieGameId || 0;

        if (!gameId) {
            showError('Aucune partie spécifiée.');
            return;
        }

        // Vérifier qu'on a un player_id stocké
        const playerId = localStorage.getItem('objectif_player_id');
        const storedGameId = localStorage.getItem('objectif_game_id');

        if (!playerId) {
            showError('Vous n\'êtes pas connecté à cette partie.');
            return;
        }

        // Si le game_id stocké ne correspond pas, on vérifie quand même via l'API
        // L'API vérifiera si le player_id appartient bien à cette partie
        loadGameStatus();
    });

    function loadGameStatus() {
        const playerId = localStorage.getItem('objectif_player_id');

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url + 'game.php?action=full_status',
            data: {
                nonce: objectif_ajax.nonce,
                game_id: gameId,
                player_id: playerId
            },
            success: function(response) {
                console.log('API Response:', response);
                if (response.success) {
                    displayGameStatus(response.data);
                    startStatusAutoRefresh();
                } else {
                    const errorMsg = response.message || response.data || 'Erreur lors du chargement de la partie.';
                    console.error('API Error:', errorMsg);
                    showError(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                showError('Erreur de connexion au serveur.');
            }
        });
    }

    function displayGameStatus(data) {
        const $content = $('#partie-content');
        const creatorName = localStorage.getItem('objectif_creator_name') || data.creator_name || 'Créateur';

        let html = `
            <div class="creator-success">
                <p class="success-message">✅ Partie créée avec succès !</p>
                <p><strong>Vous êtes automatiquement connecté en tant que créateur.</strong></p>
                <div class="game-config-summary">
                    <p><strong>Configuration :</strong> ${escapeHtml(data.game_config_name || 'Jeu de base')}</p>
                </div>
                <div class="cancel-game-section">
                    <button type="button" id="cancel-game-btn">
                        ❌ Annuler la partie
                    </button>
                </div>
            </div>
        `;

        // Section de statut
        html += generateStatusHTML(data);

        // Codes des autres joueurs
        const allJoined = data.players && data.players.every(p => p.has_joined);

        if (allJoined) {
            // Tous connectés : afficher le message didactique pour générer l'objectif
            html += `
                <div class="instruction-box">
                    <p class="instruction-text">Tous les joueurs sont prêts ! Clique sur le bouton ci-dessous pour voir ton objectif secret.</p>
                    <div class="arrow-down">&#8595;</div>
                </div>
            `;
        } else {
            // En attente : afficher la liste des joueurs et les QR codes
            if (data.players && data.players.length > 0) {
                html += generatePlayersCodesHTML(data.players);
                playersData = data.players;
            }
            html += generateGeneralQRHTML(data.join_page_url);
        }

        $content.html(html);

        // Afficher le sticky "Voir mon objectif" si tous les joueurs sont connectés
        if (typeof window.showViewObjectiveSticky === 'function') {
            window.showViewObjectiveSticky(allJoined);
        }

        // Générer le QR code général
        setTimeout(function() {
            if (typeof ObjectifQR !== 'undefined') {
                ObjectifQR.generateQRCode(data.join_page_url, 'qr-code-container');
            }
        }, 500);

        // Event listener pour l'accordéon
        $(document).off('click', '.player-accordion-header').on('click', '.player-accordion-header', function() {
            const $item = $(this).closest('.player-accordion-item');

            // Ne pas ouvrir si le joueur est déjà connecté
            if ($item.hasClass('joined')) {
                return;
            }

            const wasOpen = $item.hasClass('open');
            const index = $item.data('index');

            // Fermer tous les autres accordéons
            $('.player-accordion-item').removeClass('open');

            // Ouvrir celui-ci si il n'était pas déjà ouvert
            if (!wasOpen) {
                $item.addClass('open');

                // Générer le QR code pour ce joueur si pas encore fait
                const $qrContainer = $item.find('.player-qr');
                if ($qrContainer.length && $qrContainer.children().length === 0) {
                    const player = playersData[index];
                    if (player && !player.has_joined) {
                        const playerUrl = objectif_ajax.objectif_url
                            + '?player_code=' + player.code
                            + '&auto_join=1';
                        ObjectifQR.generateQRCode(playerUrl, `qr-player-${index}`);
                    }
                }
            }
        });

        // Event listener pour annuler
        $('#cancel-game-btn').on('click', function() {
            cancelGame();
        });
    }

    function generateStatusHTML(data) {
        const allJoined = data.players && data.players.every(p => p.has_joined);
        const joinedCount = data.players ? data.players.filter(p => p.has_joined).length : 0;
        const totalPlayers = data.players ? data.players.length : 0;

        let statusClass = allJoined ? 'all-ready' : 'waiting';
        let statusIcon = allJoined ? '✅' : '⏳';
        let statusText = allJoined
            ? 'Tous les joueurs sont prêts !'
            : `En attente des joueurs (${joinedCount + 1}/${totalPlayers + 1})`;

        let html = `
            <div class="game-status-section status-${statusClass}">
                <p class="status-text">${statusIcon} ${statusText}</p>
        `;

        if (allJoined) {
            html += `
                <a href="objectif.php" class="objectif-button objectif-primary" style="margin-top: 15px; display: inline-block;">
                    🎯 Voir mon objectif
                </a>
            `;
        }

        html += `</div>`;
        return html;
    }

    function generatePlayersCodesHTML(players) {
        let html = `
            <div class="other-players-codes">
                <h4>🎫 Codes pour les autres joueurs :</h4>
                <div class="players-accordion">
        `;

        players.forEach(function(player, index) {
            const statusClass = player.has_joined ? 'joined' : 'pending';
            const statusText = player.has_joined ? '✓ Connecté' : '⏳ En attente';

            html += `
                <div class="player-accordion-item ${statusClass}" data-index="${index}">
                    <div class="player-accordion-header">
                        <h5>${escapeHtml(player.name)}</h5>
                        <div class="player-header-info">
                            <span class="player-status ${statusClass}">${statusText}</span>
                            <span class="accordion-toggle">▼</span>
                        </div>
                    </div>
            `;

            if (!player.has_joined) {
                html += `
                    <div class="player-accordion-content">
                        <div class="player-code-display">
                            <strong class="player-code">${player.code}</strong>
                        </div>
                        <div class="player-qr" id="qr-player-${index}"></div>
                        <p class="qr-instruction">Scanner pour connexion directe</p>
                    </div>
                `;
            }

            html += `</div>`;
        });

        html += `</div></div>`;
        return html;
    }

    function generateGeneralQRHTML(joinPageUrl) {
        return `
            <div class="qr-code-section">
                <h4>📱 Alternative : Page de connexion générale</h4>
                <p><strong>Si les QR codes individuels ne fonctionnent pas :</strong></p>
                <div id="qr-code-container" class="qr-container"></div>
                <p class="qr-url"><a href="${joinPageUrl}" target="_blank">${joinPageUrl}</a></p>
            </div>
        `;
    }

    function startStatusAutoRefresh() {
        if (statusInterval) {
            clearInterval(statusInterval);
        }

        statusInterval = setInterval(function() {
            refreshStatus();
        }, 5000);
    }

    function refreshStatus() {
        const playerId = localStorage.getItem('objectif_player_id');

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url + 'game.php?action=full_status',
            data: {
                nonce: objectif_ajax.nonce,
                game_id: gameId,
                player_id: playerId
            },
            success: function(response) {
                if (response.success) {
                    updateStatusDisplay(response.data);
                }
            }
        });
    }

    function updateStatusDisplay(data) {
        // Mettre à jour la section de statut
        const $statusSection = $('.game-status-section');
        if ($statusSection.length) {
            $statusSection.replaceWith(generateStatusHTML(data));
        }

        // Vérifier si tous les joueurs sont connectés
        const allJoined = data.players && data.players.every(p => p.has_joined);

        // Mettre à jour les items accordéon des joueurs
        if (data.players) {
            data.players.forEach(function(player, index) {
                const $item = $(`.player-accordion-item[data-index="${index}"]`);
                if ($item.length) {
                    const wasJoined = $item.hasClass('joined');
                    const isNowJoined = player.has_joined;

                    if (!wasJoined && isNowJoined) {
                        // Le joueur vient de rejoindre
                        $item.removeClass('pending open').addClass('joined');
                        $item.find('.player-accordion-content').remove();
                        $item.find('.player-status')
                            .removeClass('pending')
                            .addClass('joined')
                            .text('✓ Connecté');
                    }
                }
            });
            playersData = data.players;
        }

        // Masquer les codes et QR code général si tous les joueurs sont connectés
        if (allJoined) {
            $('.other-players-codes').hide();
            $('.qr-code-section').hide();
        }

        // Afficher/masquer le sticky "Voir mon objectif"
        if (typeof window.showViewObjectiveSticky === 'function') {
            window.showViewObjectiveSticky(allJoined);
        }
    }

    async function cancelGame() {
        const confirmed = await AppModal.confirm('Cette action est irréversible. La partie sera supprimée pour tous les joueurs.', {
            title: 'Annuler la partie ?',
            confirmText: 'Oui, annuler',
            cancelText: 'Non, garder',
            type: 'danger',
            image: '../assets/images/mitard.jpg'
        });

        if (!confirmed) {
            return;
        }

        const $button = $('#cancel-game-btn');
        $button.prop('disabled', true).text('⏳ Annulation...');

        const playerId = localStorage.getItem('objectif_player_id');

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url + 'game.php?action=cancel',
            data: {
                nonce: objectif_ajax.nonce,
                game_id: gameId,
                player_id: playerId
            },
            success: async function(response) {
                if (response.success) {
                    // Nettoyer le localStorage
                    localStorage.removeItem('objectif_player_id');
                    localStorage.removeItem('objectif_game_id');
                    localStorage.removeItem('objectif_is_creator');
                    localStorage.removeItem('objectif_creator_name');

                    // Arrêter l'auto-refresh
                    if (statusInterval) {
                        clearInterval(statusInterval);
                    }

                    await AppModal.alert('La partie a été annulée avec succès.', {
                        title: 'Partie annulée',
                        type: 'success'
                    });
                    window.location.href = 'index.php';
                } else {
                    AppModal.alert(response.message || response.data || 'Erreur inconnue', {
                        title: 'Erreur',
                        type: 'error'
                    });
                    $button.prop('disabled', false).text('❌ Annuler la partie');
                }
            },
            error: function() {
                AppModal.alert('Impossible de contacter le serveur. Vérifiez votre connexion.', {
                    title: 'Erreur de connexion',
                    type: 'error'
                });
                $button.prop('disabled', false).text('❌ Annuler la partie');
            }
        });
    }

    function showError(message) {
        $('#partie-content').html(`
            <div class="error-state">
                <h3>❌ Erreur</h3>
                <p>${escapeHtml(message)}</p>
                <a href="index.php" class="objectif-button objectif-primary" style="margin-top: 20px; display: inline-block;">
                    Retour à l'accueil
                </a>
            </div>
        `);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    return {
        loadGameStatus,
        cancelGame
    };

})(jQuery);
