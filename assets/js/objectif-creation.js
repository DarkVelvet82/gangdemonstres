// js/objectif-creation.js - Module création de partie
window.ObjectifCreation = (function($) {
    'use strict';

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
        const difficulty = $('input[name="difficulty"]:checked').val();
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
        
        if (!data.difficulty) {
            alert('Veuillez sélectionner une difficulté');
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
                extensions: data.extensions
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
    }

    return {
        collectFormData,
        validateFormData,
        createGame
    };

})(jQuery);