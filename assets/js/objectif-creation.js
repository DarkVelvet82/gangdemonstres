// js/objectif-creation.js - Module création de partie
window.ObjectifCreation = (function($) {
    'use strict';

    // Joueurs fréquents sélectionnés
    let selectedFrequentPlayers = [];

    // Initialisation au chargement
    $(document).ready(function() {
        initFrequentPlayers();
        initPlayerCountListener();
    });

    // Initialiser les joueurs fréquents si connecté
    function initFrequentPlayers() {
        if (typeof ObjectifUser !== 'undefined' && ObjectifUser.isLoggedIn()) {
            const user = ObjectifUser.getCurrentUser();
            const players = ObjectifUser.getPlayers();

            // Afficher la section
            $('#frequent-players-section').show();

            // Pré-remplir le prénom du créateur
            if (user && user.prenom) {
                $('#objectif-creator-name').val(user.prenom);
            }

            // Afficher les joueurs fréquents
            const $list = $('#frequent-players-list');
            $list.empty();

            if (players && players.length > 0) {
                players.forEach(function(player) {
                    $list.append(`
                        <button type="button" class="frequent-player-btn" data-name="${player.player_name}">
                            ${player.player_name}
                        </button>
                    `);
                });

                // Event click sur les boutons
                $list.find('.frequent-player-btn').on('click', function() {
                    const name = $(this).data('name');
                    toggleFrequentPlayer(name, $(this));
                });
            } else {
                $list.html('<p style="color:#999; font-size:14px;">Aucun joueur enregistré. <a href="compte.php">Gérer mes joueurs</a></p>');
            }
        }
    }

    // Toggle un joueur fréquent
    function toggleFrequentPlayer(name, $btn) {
        const index = selectedFrequentPlayers.indexOf(name);
        const playerCount = parseInt($('#objectif-player-count').val()) || 2;
        const maxOtherPlayers = playerCount - 1;

        if (index > -1) {
            // Retirer
            selectedFrequentPlayers.splice(index, 1);
            $btn.removeClass('selected');
        } else {
            // Vérifier qu'on n'a pas atteint la limite
            if (selectedFrequentPlayers.length >= maxOtherPlayers) {
                // Limite atteinte, ne pas ajouter
                return;
            }
            // Ajouter
            selectedFrequentPlayers.push(name);
            $btn.addClass('selected');
        }

        // Mettre à jour les inputs et l'état des boutons
        updateOtherPlayersInputs();
        updateFrequentPlayersButtonsState();
    }

    // Mettre à jour l'état visuel des boutons (désactiver si limite atteinte)
    function updateFrequentPlayersButtonsState() {
        const playerCount = parseInt($('#objectif-player-count').val()) || 2;
        const maxOtherPlayers = playerCount - 1;
        const limitReached = selectedFrequentPlayers.length >= maxOtherPlayers;

        $('.frequent-player-btn').each(function() {
            const name = $(this).data('name');
            const isSelected = selectedFrequentPlayers.indexOf(name) > -1;

            if (limitReached && !isSelected) {
                // Désactiver les boutons non sélectionnés
                $(this).addClass('disabled').css('opacity', '0.5').css('cursor', 'not-allowed');
            } else {
                // Activer le bouton
                $(this).removeClass('disabled').css('opacity', '1').css('cursor', 'pointer');
            }
        });
    }

    // Mettre à jour les inputs des autres joueurs
    function updateOtherPlayersInputs() {
        const playerCount = parseInt($('#objectif-player-count').val()) || 2;
        const otherPlayersNeeded = playerCount - 1;

        const $container = $('#other-players-inputs');
        $container.empty();

        // D'abord les joueurs fréquents sélectionnés
        selectedFrequentPlayers.slice(0, otherPlayersNeeded).forEach(function(name, i) {
            $container.append(`
                <div class="player-input-row" style="display:flex; gap:10px; margin-bottom:10px;">
                    <input type="text" class="form-control other-player-name" value="${name}" placeholder="Prénom du joueur ${i + 2}" required style="flex:1;">
                    <button type="button" class="remove-frequent-btn" data-name="${name}" style="padding:8px 12px; background:#dc3545; color:white; border:none; border-radius:6px; cursor:pointer;">×</button>
                </div>
            `);
        });

        // Compléter avec des inputs vides
        const remainingSlots = otherPlayersNeeded - Math.min(selectedFrequentPlayers.length, otherPlayersNeeded);
        for (let i = 0; i < remainingSlots; i++) {
            const playerNum = selectedFrequentPlayers.slice(0, otherPlayersNeeded).length + i + 2;
            $container.append(`
                <input type="text" class="form-control other-player-name" placeholder="Prénom du joueur ${playerNum}" required style="margin-bottom:10px;">
            `);
        }

        // Event pour retirer un joueur fréquent
        $container.find('.remove-frequent-btn').on('click', function() {
            const name = $(this).data('name');
            const index = selectedFrequentPlayers.indexOf(name);
            if (index > -1) {
                selectedFrequentPlayers.splice(index, 1);
                $(`.frequent-player-btn[data-name="${name}"]`).removeClass('selected');
                updateOtherPlayersInputs();
                updateFrequentPlayersButtonsState();
            }
        });
    }

    // Écouter les changements de nombre de joueurs
    function initPlayerCountListener() {
        $('#objectif-player-count').on('change', function() {
            const playerCount = parseInt($(this).val()) || 2;
            const maxOtherPlayers = playerCount - 1;

            // Si on a trop de joueurs sélectionnés, retirer les derniers
            while (selectedFrequentPlayers.length > maxOtherPlayers) {
                const removedName = selectedFrequentPlayers.pop();
                $(`.frequent-player-btn[data-name="${removedName}"]`).removeClass('selected');
            }

            updateOtherPlayersInputs();
            updateFrequentPlayersButtonsState();
        });
    }

    // Création de partie avec formulaire
    $(document).on('submit', '#objectif-create-form', function(e) {
        e.preventDefault();

        const formData = collectFormData();
        if (!validateFormData(formData)) {
            return;
        }

        createGame(formData);
    });

    function collectFormData() {
        const playerCount = parseInt($('#objectif-player-count').val());
        const creatorName = $('#objectif-creator-name').val().trim();
        const difficulty = 'normal'; // Difficulté unique
        const baseGame = $('input[name="base_game"]:checked').val();
        const extensions = [];

        $('input[name="extensions[]"]:checked').each(function() {
            extensions.push($(this).val());
        });

        const otherNames = [];
        $('.other-player-name').each(function() {
            const name = $(this).val().trim();
            if (name) {
                otherNames.push(name);
            }
        });

        return {
            playerCount,
            creatorName,
            difficulty,
            baseGame,
            extensions,
            otherNames
        };
    }

    function validateFormData(data) {
        if (!data.creatorName) {
            alert('Veuillez entrer votre prénom');
            return false;
        }

        if (!data.baseGame) {
            alert('Veuillez sélectionner un jeu de base');
            return false;
        }

        if (data.otherNames.length !== (data.playerCount - 1)) {
            alert('Veuillez remplir tous les prénoms des autres joueurs');
            return false;
        }

        return true;
    }

    function createGame(data) {
        const $button = $('#objectif-create-button');
        const originalText = $button.text();
        $button.prop('disabled', true).text('🎮 Création...');

        // Récupérer user_id si connecté
        let userId = null;
        if (typeof ObjectifUser !== 'undefined' && ObjectifUser.isLoggedIn()) {
            const user = ObjectifUser.getCurrentUser();
            userId = user ? user.user_id : null;
        }

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_create_game',
                nonce: objectif_ajax.nonce,
                player_count: data.playerCount,
                creator_name: data.creatorName,
                other_names: data.otherNames,
                difficulty: data.difficulty,
                base_game: data.baseGame,
                extensions: data.extensions,
                user_id: userId
            },
            success: function(response) {
                $button.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    handleGameCreationSuccess(response.data);
                } else {
                    alert('Erreur : ' + response.data);
                }
            },
            error: function(err) {
                $button.prop('disabled', false).text(originalText);
                alert('Erreur AJAX.');
            }
        });
    }

    function handleGameCreationSuccess(data) {
        const resultDiv = $('#objectif-game-result');
        resultDiv.empty();

        // Auto-connexion du créateur
        localStorage.setItem('objectif_player_id', data.creator_player_id);
        localStorage.setItem('objectif_game_id', data.game_id);
        localStorage.setItem('objectif_is_creator', '1');

        // Générer l'HTML d'affichage
        const html = generateCreationSuccessHTML(data);
        resultDiv.html(html);

        // Masquer le formulaire
        $('#objectif-create-form').hide();

        // Démarrer les tâches post-création
        startPostCreationTasks(data);
    }

    function generateCreationSuccessHTML(data) {
        let html = `
            <div class="creator-success">
                <h3>👋 Bonjour ${data.creator_name} !</h3>
                <p class="success-message">✅ Partie créée avec succès !</p>
                <p><strong>Vous êtes automatiquement connecté en tant que créateur.</strong></p>
                <div class="game-config-summary">
                    <p><strong>Configuration :</strong> ${data.game_config_name} | Difficulté : ${data.difficulty_display}</p>
                </div>
                <div class="cancel-game-section" style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ddd;">
                    <button type="button" id="cancel-game-btn" class="objectif-button" style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;">
                        ❌ Annuler la partie
                    </button>
                </div>
            </div>
        `;

        // Section de statut
        html += ObjectifStatus.generateStatusHTML();

        // Codes des autres joueurs
        if (data.players_data.length > 0) {
            html += generatePlayersCodesHTML(data.players_data);
            ObjectifGame.playersData = data.players_data;
        }

        // QR Code général
        html += generateGeneralQRHTML(data.join_page_url);

        return html;
    }

    function generatePlayersCodesHTML(playersData) {
        let html = `
            <div class="other-players-codes">
                <h4>🎫 Codes pour les autres joueurs :</h4>
                <div class="players-grid">
        `;
        
        playersData.forEach(function(player, index) {
            html += `
                <div class="player-card">
                    <h5>${player.name}</h5>
                    <div class="player-code-display">
                        <strong class="player-code">${player.code}</strong>
                    </div>
                    <div class="player-qr" id="qr-player-${index}"></div>
                    <p class="qr-instruction">Scanner pour connexion directe</p>
                </div>
            `;
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

    function startPostCreationTasks(data) {
        // Démarrer la vérification du statut
        setTimeout(() => {
            ObjectifStatus.checkCreatorGameStatus(data.game_id);
        }, 1000);

        // Démarrer l'auto-refresh
        ObjectifStatus.startCreatorStatusAutoRefresh();

        // Générer les QR codes
        setTimeout(() => {
            ObjectifQR.generateQRCode(data.join_page_url, 'qr-code-container');

            if (ObjectifGame.playersData) {
                ObjectifGame.playersData.forEach(function(player, index) {
                    const playerUrl = objectif_ajax.objectif_url
                        + '?player_code=' + player.code
                        + '&auto_join=1';
                    ObjectifQR.generateQRCode(playerUrl, `qr-player-${index}`);
                });
            }
        }, 1500);

        // Event listener pour annuler la partie
        $(document).on('click', '#cancel-game-btn', function() {
            cancelGame(data.game_id);
        });
    }

    function cancelGame(gameId) {
        if (!confirm('Êtes-vous sûr de vouloir annuler cette partie ? Cette action est irréversible.')) {
            return;
        }

        const $button = $('#cancel-game-btn');
        $button.prop('disabled', true).text('⏳ Annulation...');

        const playerId = localStorage.getItem('objectif_player_id');

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_cancel_game',
                nonce: objectif_ajax.nonce,
                game_id: gameId,
                player_id: playerId
            },
            success: function(response) {
                if (response.success) {
                    // Nettoyer le localStorage
                    localStorage.removeItem('objectif_player_id');
                    localStorage.removeItem('objectif_game_id');
                    localStorage.removeItem('objectif_is_creator');

                    // Arrêter l'auto-refresh
                    if (typeof ObjectifStatus !== 'undefined' && ObjectifStatus.stopCreatorStatusAutoRefresh) {
                        ObjectifStatus.stopCreatorStatusAutoRefresh();
                    }

                    // Afficher le message et rediriger
                    alert('Partie annulée avec succès.');
                    window.location.href = 'index.php';
                } else {
                    alert('Erreur : ' + (response.message || response.data || 'Erreur inconnue'));
                    $button.prop('disabled', false).text('❌ Annuler la partie');
                }
            },
            error: function() {
                alert('Erreur de connexion.');
                $button.prop('disabled', false).text('❌ Annuler la partie');
            }
        });
    }

    return {
        collectFormData,
        validateFormData,
        createGame
    };

})(jQuery);