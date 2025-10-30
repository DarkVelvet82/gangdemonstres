// js/objectif-notifications.js - Module notifications
window.ObjectifNotifications = (function($) {
    'use strict';

    let notificationInterval = null;
    let lastNotificationCheck = 0;

    // Démarrer la vérification des notifications
    function startNotificationChecking() {
        const playerId = localStorage.getItem('objectif_player_id');
        if (!playerId) return;

        console.log('🔔 Démarrage de la vérification des notifications');
        
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

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_check_notifications',
                nonce: objectif_ajax.nonce,
                player_id: playerId
            },
            success: function(response) {
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

        data.notifications.forEach(notification => {
            // Éviter de montrer plusieurs fois la même notification
            if (notification.timestamp <= lastNotificationCheck) {
                return;
            }

            if (notification.type === 'game_ended') {
                showGameEndedNotification(notification);
            } else if (notification.type === 'game_restarted') {
                showGameRestartedNotification(notification);
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

        const isWinner = notification.is_winner;
        const resultClass = isWinner ? 'winner' : 'loser';
        const resultIcon = isWinner ? '🏆' : '😔';
        const resultText = isWinner ? 'Félicitations ! Vous avez gagné !' : 'Dommage, vous avez perdu...';
        const resultBg = isWinner ? '#d4edda' : '#f8d7da';
        const resultBorder = isWinner ? '#28a745' : '#dc3545';

        const notificationHtml = `
            <div class="game-notification game-ended ${resultClass}" style="
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

        $('body').append(notificationHtml);

        // Auto-fermeture après 10 secondes
        setTimeout(() => {
            $('.game-notification').fadeOut(300, function() {
                $(this).remove();
            });
        }, 10000);
    }

    // Afficher la notification de nouvelle partie
    function showGameRestartedNotification(notification) {
        console.log('🔄 Notification nouvelle partie:', notification);

        // Supprimer les anciennes notifications
        $('.game-notification').remove();

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

        $('body').append(notificationHtml);

        // Auto-fermeture après 15 secondes
        setTimeout(() => {
            $('.game-notification').fadeOut(300, function() {
                $(this).remove();
            });
        }, 15000);
    }

    // Event handlers pour les notifications
    $(document).on('click', '#close-notification, #close-restart-notification', function() {
        $('.game-notification').fadeOut(300, function() {
            $(this).remove();
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