// js/objectif-notifications.js - Module notifications
window.ObjectifNotifications = (function($) {
    'use strict';

    let notificationInterval = null;
    // Initialiser avec le timestamp actuel pour ignorer les anciennes notifications au chargement
    let lastNotificationCheck = Math.floor(Date.now() / 1000);

    // Démarrer la vérification des notifications
    function startNotificationChecking() {
        const playerId = localStorage.getItem('objectif_player_id');
        if (!playerId) return;

        // IMPORTANT: Arrêter l'ancien interval avant d'en créer un nouveau
        if (notificationInterval) {
            console.log('🔔 Arrêt de l\'ancien interval avant redémarrage');
            clearInterval(notificationInterval);
            notificationInterval = null;
        }

        console.log('🔔 Démarrage de la vérification des notifications (ignorant avant ' + lastNotificationCheck + ')');

        // Vérification immédiate
        checkNotifications();

        // Puis toutes les 5 secondes
        notificationInterval = setInterval(() => {
            checkNotifications();
        }, 5000);
    }

    // Arrêter la vérification des notifications
    function stopNotificationChecking() {
        if (notificationInterval) {
            clearInterval(notificationInterval);
            notificationInterval = null;
            console.log('🔔 Arrêt de la vérification des notifications');
        }
    }

    // Vérifier les notifications
    function checkNotifications() {
        const playerId = localStorage.getItem('objectif_player_id');
        if (!playerId) return;

        const ajaxStartTime = performance.now();

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_check_notifications',
                nonce: objectif_ajax.nonce,
                player_id: playerId
            },
            success: function(response) {
                const ajaxDuration = Math.round(performance.now() - ajaxStartTime);
                // Log uniquement si > 500ms pour éviter le spam
                if (ajaxDuration > 500) {
                    console.warn(`⏱️ [PERF] Notification check LENT: ${ajaxDuration}ms`);
                }
                if (response.success) {
                    handleNotifications(response.data);
                }
            },
            error: function() {
                // Erreur silencieuse pour ne pas spammer
            }
        });
    }

    // Traiter les notifications reçues
    function handleNotifications(data) {
        if (!data.notifications || data.notifications.length === 0) {
            return;
        }

        // Le créateur ne reçoit pas les notifications (il a déjà ses modals)
        const isCreator = localStorage.getItem('objectif_is_creator') === '1';

        data.notifications.forEach(notification => {
            // Éviter de montrer plusieurs fois la même notification
            if (notification.timestamp <= lastNotificationCheck) {
                return;
            }

            if (notification.type === 'game_ended') {
                // Le créateur a déjà la modal post-game, pas besoin de notification
                if (!isCreator) {
                    showGameEndedNotification(notification);
                }
            } else if (notification.type === 'game_restarted') {
                // Le créateur a lancé le restart lui-même, pas besoin de notification
                if (!isCreator) {
                    showGameRestartedNotification(notification);
                }
            } else if (notification.type === 'session_closed') {
                // Le créateur a fermé la session lui-même, pas besoin de notification
                if (!isCreator) {
                    showSessionClosedNotification(notification);
                }
            }
        });

        // Mettre à jour le timestamp de la dernière vérification
        lastNotificationCheck = Math.max(...data.notifications.map(n => n.timestamp));
    }

    // Afficher la notification de fin de partie
    function showGameEndedNotification(notification) {
        console.log('🎯 Notification fin de partie:', notification);

        // Supprimer les anciennes notifications
        $('.game-notification').remove();

        // Vider l'affichage de l'objectif en cours pour éviter la confusion
        // L'utilisateur verra un message indiquant que la partie est terminée
        $('.objective-result').fadeOut(300, function() {
            $(this).remove();
        });
        // Masquer aussi les boutons de gestion créateur
        $('#creator-management').fadeOut(300);

        const isWinner = notification.is_winner;
        const resultClass = isWinner ? 'winner' : 'loser';
        const resultIcon = isWinner ? '🏆' : '😔';
        const resultText = isWinner ? 'Félicitations ! Vous avez gagné !' : 'Dommage, vous avez perdu...';
        const resultBg = isWinner ? '#d4edda' : '#f8d7da';
        const resultBorder = isWinner ? '#28a745' : '#dc3545';

        // Stocker le nom du gestionnaire pour le message d'attente
        const managerName = notification.ended_by;

        const notificationHtml = `
            <div class="game-notification game-ended ${resultClass}" data-manager-name="${managerName}" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${resultBg};
                border: 2px solid ${resultBorder};
                border-radius: 12px;
                padding: 20px;
                max-width: 350px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                z-index: 10000;
                animation: slideInRight 0.5s ease-out;
            ">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <span style="font-size: 32px; margin-right: 15px;">${resultIcon}</span>
                    <div>
                        <h3 style="margin: 0; color: ${isWinner ? '#155724' : '#721c24'}; font-size: 18px;">
                            Partie terminée !
                        </h3>
                        <p style="margin: 5px 0 0 0; color: ${isWinner ? '#155724' : '#721c24'}; font-weight: bold;">
                            ${resultText}
                        </p>
                    </div>
                </div>

                <div style="background: rgba(255,255,255,0.7); padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="margin: 0; font-size: 14px; color: #333;">
                        <strong>🏆 Gagnant :</strong> ${notification.winner_name}<br>
                        <strong>👑 Terminé par :</strong> ${notification.ended_by}
                    </p>
                </div>

                <div style="text-align: center;">
                    <button id="close-notification" style="
                        background: #6c757d;
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 14px;
                    ">
                        ✕ Fermer
                    </button>
                </div>
            </div>
        `;

        // Utiliser le conteneur dédié s'il existe, sinon body
        const $notifRoot = $('#notifications-root');
        if ($notifRoot.length) {
            $notifRoot.append(notificationHtml);
        } else {
            $('body').append(notificationHtml);
        }

        // Fonction pour afficher le message d'attente
        function showWaitingMessage() {
            const isCreator = localStorage.getItem('objectif_is_creator');

            // Afficher le message d'attente uniquement pour les non-créateurs
            if (isCreator !== '1' && isCreator !== 1 && isCreator !== true) {
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
                            Patientez avant le lancement de la prochaine partie par <strong>${managerName}</strong>.
                        </p>
                        <p style="color: #777; margin: 10px 0 0 0; font-size: 14px;">
                            Vous pourrez alors générer un nouvel objectif.
                        </p>
                    </div>
                `;
                $('#objectif-state').html(waitingHtml);
            }
        }

        // Auto-fermeture après 10 secondes + affichage message d'attente
        setTimeout(() => {
            $('.game-notification').fadeOut(300, function() {
                $(this).remove();
                showWaitingMessage();
            });
        }, 10000);
    }

    // Afficher la notification de nouvelle partie
    function showGameRestartedNotification(notification) {
        console.log('🔄 Notification nouvelle partie:', notification);

        // Incrémenter le compteur de parties jouées (pour messages d'encouragement)
        const gamesPlayed = parseInt(localStorage.getItem('objectif_games_played') || '0');
        localStorage.setItem('objectif_games_played', gamesPlayed + 1);

        // Supprimer les anciennes notifications
        $('.game-notification').remove();

        // Vider l'ancien objectif et afficher le bouton pour en générer un nouveau
        $('.objective-result').fadeOut(300, function() {
            $(this).remove();
        });
        $('#creator-management').fadeOut(300);

        // Vider le contenu de #objectif-state
        $('#objectif-state').html('');

        // Réafficher la section de génération (bouton + titre)
        $('.objective-generator').fadeIn(300);

        const notificationHtml = `
            <div class="game-notification game-restarted" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #cce7ff 0%, #b3d7ff 100%);
                border: 2px solid #007cba;
                border-radius: 12px;
                padding: 20px;
                max-width: 350px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                z-index: 10000;
                animation: slideInRight 0.5s ease-out;
            ">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <span style="font-size: 32px; margin-right: 15px;">🔄</span>
                    <div>
                        <h3 style="margin: 0; color: #004085; font-size: 18px;">
                            Nouvelle partie !
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #004085; font-weight: bold;">
                            Une nouvelle partie a été lancée
                        </p>
                    </div>
                </div>
                
                <div style="background: rgba(255,255,255,0.7); padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="margin: 0; font-size: 14px; color: #333;">
                        <strong>👑 Lancée par :</strong> ${notification.restarted_by}
                    </p>
                </div>
                
                <div style="text-align: center;">
                    <button id="generate-new-objective" style="
                        background: #007cba;
                        color: white;
                        border: none;
                        padding: 12px 20px;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 16px;
                        font-weight: bold;
                        margin-right: 10px;
                    ">
                        🎯 Nouvel objectif
                    </button>
                    <button id="close-restart-notification" style="
                        background: #6c757d;
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 14px;
                    ">
                        ✕ Fermer
                    </button>
                </div>
            </div>
        `;

        // Utiliser le conteneur dédié s'il existe, sinon body
        const $notifRoot = $('#notifications-root');
        if ($notifRoot.length) {
            $notifRoot.append(notificationHtml);
        } else {
            $('body').append(notificationHtml);
        }

        // Auto-fermeture après 15 secondes
        setTimeout(() => {
            $('.game-notification').fadeOut(300, function() {
                $(this).remove();
                // Afficher le sticky bouton pour générer l'objectif
                showGenerateSticky();
            });
        }, 15000);
    }

    // Afficher le sticky bouton pour générer l'objectif
    function showGenerateSticky() {
        // Ne pas afficher si le message d'attente est présent (partie pas encore relancée)
        if ($('.waiting-message').length > 0) {
            return;
        }

        // Réafficher la section de génération si elle était cachée
        $('.objective-generator').show();

        // Sur objectif.php, le sticky s'appelle #sticky-objective
        const $stickyObjective = $('#sticky-objective');
        if ($stickyObjective.length) {
            $stickyObjective.show();
        }
    }

    // Afficher la notification de fermeture de session
    function showSessionClosedNotification(notification) {
        console.log('🚪 Notification fermeture de session:', notification);

        // Supprimer les anciennes notifications
        $('.game-notification').remove();

        // Arrêter les vérifications de notifications
        stopNotificationChecking();

        const notificationHtml = `
            <div class="notification-overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 9999;
            "></div>
            <div class="game-notification session-closed" style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border: 2px solid #6c757d;
                border-radius: 16px;
                padding: 30px;
                width: calc(100% - 40px);
                max-width: 400px;
                box-sizing: border-box;
                box-shadow: 0 8px 32px rgba(0,0,0,0.3);
                z-index: 10000;
                text-align: center;
            ">
                <div style="margin-bottom: 20px;">
                    <img src="../assets/images/mitard.jpg" alt="" style="width: 100%; max-width: 280px; height: auto; border-radius: 8px;">
                </div>

                <h3 style="margin: 0 0 15px 0; color: #333; font-size: 22px;">
                    Session terminée
                </h3>

                <div style="background: rgba(255,255,255,0.8); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0; font-size: 16px; color: #555;">
                        <strong>${notification.closed_by}</strong> a mis fin à la session.
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 14px; color: #666;">
                        Merci d'avoir joué ! À bientôt
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 14px; color: #555;">
                        Merci de nous laisser un avis sur Google :
                    </p>
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="https://www.google.com/maps/place//data=!4m3!3m2!1s0x682068fe27ee1531:0xe09a89dc6aeb165c!12e1" target="_blank" style="
                        display: inline-block;
                        background: #4285F4;
                        color: white;
                        border: none;
                        padding: 14px 28px;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 16px;
                        font-weight: bold;
                        text-decoration: none;
                        transition: transform 0.2s, box-shadow 0.2s;
                    ">
                        ⭐ Laisser un avis Google
                    </a>
                    <a href="index.php" class="session-closed-btn" style="
                        display: inline-block;
                        background: linear-gradient(135deg, #003f53 0%, #003547 100%);
                        color: white;
                        border: none;
                        padding: 14px 28px;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 16px;
                        font-weight: bold;
                        text-decoration: none;
                        transition: transform 0.2s, box-shadow 0.2s;
                    ">
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        `;

        // Utiliser le conteneur dédié s'il existe, sinon body
        const $notifRoot = $('#notifications-root');
        if ($notifRoot.length) {
            $notifRoot.append(notificationHtml);
        } else {
            $('body').append(notificationHtml);
        }

        // Nettoyer le localStorage
        localStorage.removeItem('objectif_player_id');
        localStorage.removeItem('objectif_game_id');
        localStorage.removeItem('objectif_is_creator');

        // Masquer le contenu de la page
        $('.objective-generator').hide();
        $('#objectif-state').hide();
    }

    // Event handlers pour les notifications
    $(document).on('click', '#close-notification', function() {
        const $notification = $(this).closest('.game-notification');
        const managerName = $notification.data('manager-name');

        $notification.fadeOut(300, function() {
            $(this).remove();

            // Afficher le message d'attente pour les non-créateurs
            const isCreator = localStorage.getItem('objectif_is_creator');
            if (isCreator !== '1' && isCreator !== 1 && isCreator !== true && managerName) {
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
                            Patientez avant le lancement de la prochaine partie par <strong>${managerName}</strong>.
                        </p>
                        <p style="color: #777; margin: 10px 0 0 0; font-size: 14px;">
                            Vous pourrez alors générer un nouvel objectif.
                        </p>
                    </div>
                `;
                $('#objectif-state').html(waitingHtml);
            }
        });
    });

    $(document).on('click', '#close-restart-notification', function() {
        $('.game-notification').fadeOut(300, function() {
            $(this).remove();
            // Afficher le sticky bouton pour générer l'objectif
            showGenerateSticky();
        });
    });

    $(document).on('click', '#generate-new-objective', function() {
        $('.game-notification').fadeOut(300, function() {
            $(this).remove();
        });
        
        // Déclencher la génération d'objectif
        if (window.ObjectifObjectives && typeof window.ObjectifObjectives.generateObjective === 'function') {
            ObjectifObjectives.generateObjective();
        } else {
            // Fallback : déclencher le clic sur le bouton s'il existe
            $('#objectif-generate-button').trigger('click');
        }
    });

    // Ajouter les styles CSS pour les animations
    $('<style>').text(`
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .game-notification {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .game-notification h3 {
            font-weight: bold;
        }
        
        .game-notification button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .game-notification.winner {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
        }
        
        .game-notification.loser {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%) !important;
        }
        
        @media (max-width: 768px) {
            .game-notification {
                top: 10px !important;
                right: 10px !important;
                left: 10px !important;
                max-width: none !important;
            }
        }
    `).appendTo('head');

    // Démarrer automatiquement si on est sur la page d'objectifs
    $(document).ready(function() {
        if ($('#objectif-state').length > 0 || $('#welcome-message').length > 0) {
            // On est sur la page d'objectifs, démarrer les notifications
            setTimeout(() => {
                startNotificationChecking();
            }, 2000);
        }
    });

    // Arrêter les notifications quand on quitte la page
    $(window).on('beforeunload', function() {
        stopNotificationChecking();
    });

    return {
        startNotificationChecking,
        stopNotificationChecking,
        checkNotifications
    };

})(jQuery);