// js/objectif-objectives.js - Module objectifs (VERSION MISE À JOUR)
window.ObjectifObjectives = (function($) {
    'use strict';

    // Génération de l'objectif du joueur
    $(document).on('click', '#objectif-generate-button', function() {
        generateObjective();
    });

    function generateObjective() {
        let playerId = getPlayerIdFromStorageOrURL();

        if (!playerId) {
            alert('Aucun joueur identifié. Veuillez rejoindre une partie d\'abord.');
            return;
        }

        const $button = $('#objectif-generate-button');
        const originalText = $button.text();
        $button.text('🎲 Génération...').prop('disabled', true);

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_generate',
                nonce: objectif_ajax.nonce,
                player_id: playerId
            },
            success: function(response) {
                $button.text(originalText).prop('disabled', false);
                
                if (response.success) {
                    handleObjectiveSuccess(response.data);
                    
                    // Démarrer les notifications après génération d'objectif
                    if (window.ObjectifNotifications) {
                        ObjectifNotifications.startNotificationChecking();
                    }
                } else {
                    alert('Erreur : ' + response.data);
                }
            },
            error: function(err) {
                $button.text(originalText).prop('disabled', false);
                console.error('Erreur AJAX lors de la génération de l\'objectif:', err);
                alert('Erreur AJAX lors de la génération de l\'objectif.');
            }
        });
    }

    function getPlayerIdFromStorageOrURL() {
        let playerId = localStorage.getItem('objectif_player_id');

        // Fallback : récupérer depuis les paramètres URL si localStorage est vide
        if (!playerId) {
            const urlParams = new URLSearchParams(window.location.search);
            playerId = urlParams.get('player_id');
            
            // Stocker aussi les autres paramètres si disponibles
            if (playerId) {
                localStorage.setItem('objectif_player_id', playerId);
                localStorage.setItem('objectif_game_id', urlParams.get('game_id') || '');
                localStorage.setItem('objectif_is_creator', urlParams.get('creator') || '0');
            }
        }

        return playerId;
    }

    function handleObjectiveSuccess(data) {
        // Afficher le nom du joueur
        if (data.player_name) {
            $('#welcome-message').text(`🎮 Bienvenue ${data.player_name} !`);
        }

        // Créer l'affichage des objectifs
        const objectiveItems = generateObjectiveItems(data.objective, data.pictos);
        const html = generateObjectiveHTML(objectiveItems, data.already_generated);
        
        $('#objectif-state').html(html);

        // Vérifier si c'est le créateur pour afficher les boutons de gestion
        checkCreatorAndDisplayManagement();
    }

    function generateObjectiveItems(objective, pictos) {
        let objectiveItems = '';

        if (typeof objective === 'object' && objective !== null) {
            for (const [typeId, quantity] of Object.entries(objective)) {
                const pictoData = pictos[typeId];
                let pictoDisplay = '❓';
                let typeName = 'Type inconnu';

                if (pictoData) {
                    // Utiliser le nom fourni par l'API
                    typeName = pictoData.name || 'Type inconnu';

                    if (pictoData.type === 'image') {
                        pictoDisplay = `<img src="${pictoData.value}" alt="${typeName}" class="objective-icon-img" />`;
                    } else {
                        pictoDisplay = pictoData.value;
                    }
                }

                objectiveItems += `
                    <div class="objective-item">
                        <div class="objective-icon">${pictoDisplay}</div>
                        <div class="objective-details">
                            <span class="objective-name">${typeName}</span>
                            <span class="objective-quantity">x${quantity}</span>
                        </div>
                    </div>
                `;
            }
        }

        return objectiveItems;
    }

    function generateObjectiveHTML(objectiveItems, alreadyGenerated) {
        return `
            <div class="objective-result">
                <div class="objective-header">
                    <h3 class="objective-title">🎯 Votre mission</h3>
                    ${alreadyGenerated ? '<span class="already-generated">Déjà généré</span>' : '<span class="newly-generated">Nouvellement généré</span>'}
                </div>
                <div class="objectives-list">
                    ${objectiveItems}
                </div>
            </div>
        `;
    }

    function checkCreatorAndDisplayManagement() {
        const isCreator = localStorage.getItem('objectif_is_creator');
        const gameId = localStorage.getItem('objectif_game_id');
        
        console.log('🎮 is_creator =', isCreator, typeof isCreator);

        if (isCreator === '1' || isCreator === 1 || isCreator === true) {
            console.log('➡️ Créateur détecté, affichage des boutons de gestion...');
            
            setTimeout(() => {
                if (window.ObjectifScores && typeof window.ObjectifScores.displayCreatorManagementButtons === 'function') {
                    ObjectifScores.displayCreatorManagementButtons(gameId);
                }
            }, 500);
        } else {
            console.log('🙅 Pas le créateur, pas de boutons de gestion');
        }
    }

    return {
        generateObjective,
        getPlayerIdFromStorageOrURL
    };

})(jQuery);