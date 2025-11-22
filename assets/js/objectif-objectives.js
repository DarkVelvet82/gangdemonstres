// js/objectif-objectives.js - Module objectifs (VERSION MISE À JOUR)
window.ObjectifObjectives = (function($) {
    'use strict';

    // Messages d'encouragement variés
    const encouragementMessages = [
        "C'est reparti {name} ! 🔥",
        "À toi de jouer {name} ! 🎲",
        "Prêt pour une nouvelle partie {name} ? 🚀",
        "Bonne chance {name} ! 🍀",
        "En piste {name} ! 🏁",
        "Let's go {name} ! 💪",
        "Que la chasse commence {name} ! 🎯",
        "Tu vas tout déchirer {name} ! ⚡",
        "Nouvelle partie, nouvelle chance {name} ! ✨",
        "Montre-leur de quoi tu es capable {name} ! 👊"
    ];

    // Obtenir un message de bienvenue approprié
    function getWelcomeMessage(playerName) {
        const gamesPlayed = parseInt(localStorage.getItem('objectif_games_played') || '0');

        if (gamesPlayed === 0) {
            // Première partie : message de bienvenue classique
            return `🎮 Bienvenue ${playerName} !`;
        } else {
            // Parties suivantes : message d'encouragement aléatoire
            const randomIndex = Math.floor(Math.random() * encouragementMessages.length);
            return encouragementMessages[randomIndex].replace('{name}', playerName);
        }
    }

    // Au chargement, vérifier si un objectif existe déjà et l'afficher
    $(document).ready(function() {
        checkExistingObjective();
    });

    // Vérifier si le joueur a déjà un objectif et l'afficher automatiquement
    function checkExistingObjective() {
        const playerId = getPlayerIdFromStorageOrURL();

        console.log('🔍 checkExistingObjective - playerId:', playerId);

        if (!playerId) {
            console.log('❌ Pas de player_id, abandon');
            return;
        }

        console.log('🔍 Vérification objectif existant au chargement...');

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_get_objective',
                nonce: objectif_ajax.nonce,
                player_id: playerId
            },
            success: function(response) {
                console.log('📥 Réponse check_objective:', response);

                if (response.success && response.data) {
                    console.log('📊 game_status:', response.data.game_status, '| player_name:', response.data.player_name);

                    // Afficher le nom du joueur avec message adapté
                    if (response.data.player_name) {
                        $('#welcome-message').text(getWelcomeMessage(response.data.player_name));
                    }

                    // Vérifier si la partie est terminée
                    if (response.data.game_status === 'ended' || response.data.game_status === 'terminated') {
                        console.log('🛑 Partie terminée, affichage message d\'attente');

                        // Masquer le bouton de génération
                        $('.objective-generator').hide();

                        // Afficher le message d'attente
                        const creatorName = response.data.creator_name || 'le gestionnaire';
                        const isCreator = localStorage.getItem('objectif_is_creator');

                        if (isCreator === '1' || isCreator === 1 || isCreator === true) {
                            // Le créateur voit ses boutons de gestion
                            if (window.ObjectifScores && typeof window.ObjectifScores.displayCreatorManagementButtons === 'function') {
                                ObjectifScores.displayCreatorManagementButtons(localStorage.getItem('objectif_game_id'));
                            }
                        } else {
                            // Les autres joueurs voient le message d'attente
                            const waitingHtml = `
                                <div class="waiting-message" style="
                                    text-align: center;
                                    padding: 30px 20px;
                                    background: linear-gradient(135deg, rgba(0, 63, 83, 0.1) 0%, rgba(0, 53, 71, 0.1) 100%);
                                    border-radius: 12px;
                                    margin-top: 20px;
                                ">
                                    <div style="font-size: 48px; margin-bottom: 15px;">⏳</div>
                                    <h3 style="color: #003f53; margin: 0 0 15px 0; font-size: 20px;">
                                        En attente de la prochaine partie
                                    </h3>
                                    <p style="color: #555; margin: 0; font-size: 16px; line-height: 1.5;">
                                        Patientez avant le lancement de la prochaine partie par <strong>${creatorName}</strong>.
                                    </p>
                                    <p style="color: #777; margin: 10px 0 0 0; font-size: 14px;">
                                        Vous pourrez alors générer un nouvel objectif.
                                    </p>
                                </div>
                            `;
                            $('#objectif-state').html(waitingHtml);
                        }

                        // Démarrer les notifications pour être informé du restart
                        if (window.ObjectifNotifications) {
                            ObjectifNotifications.startNotificationChecking();
                        }
                        return;
                    }

                    // Si un objectif existe déjà, l'afficher automatiquement
                    if (response.data.objective && Object.keys(response.data.objective).length > 0) {
                        console.log('✅ Objectif existant trouvé, affichage automatique');
                        handleObjectiveSuccess({
                            objective: response.data.objective,
                            player_name: response.data.player_name,
                            pictos: response.data.pictos,
                            already_generated: true
                        });

                        // Démarrer les notifications
                        if (window.ObjectifNotifications) {
                            ObjectifNotifications.startNotificationChecking();
                        }
                    } else {
                        console.log('ℹ️ Pas d\'objectif existant, attente du clic sur Générer');
                    }
                }
            },
            error: function() {
                console.log('⚠️ Erreur lors de la vérification de l\'objectif existant');
            }
        });
    }

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
        $button.text('Génération...').prop('disabled', true);

        const ajaxStartTime = performance.now();
        console.log('⏱️ [PERF] Début génération objectif...');

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_generate',
                nonce: objectif_ajax.nonce,
                player_id: playerId
            },
            success: function(response) {
                const ajaxDuration = Math.round(performance.now() - ajaxStartTime);
                console.log(`⏱️ [PERF] Génération terminée en ${ajaxDuration}ms`);

                // Afficher les timings du serveur si disponibles
                if (response.data && response.data.debug_timing) {
                    console.log('⏱️ [PERF] Timing serveur:', response.data.debug_timing);
                }

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
                const ajaxDuration = Math.round(performance.now() - ajaxStartTime);
                console.error(`⏱️ [PERF] Échec après ${ajaxDuration}ms`);
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
        // Afficher le nom du joueur avec message adapté
        if (data.player_name) {
            $('#welcome-message').text(getWelcomeMessage(data.player_name));
        }

        // Masquer la section de génération (bouton + titre "Votre objectif à réaliser")
        $('.objective-generator').fadeOut(300);

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