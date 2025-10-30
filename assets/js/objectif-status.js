// js/objectif-status.js - Module statut de partie
window.ObjectifStatus = (function($) {
    'use strict';

    function generateStatusHTML() {
        return `
            <div id="creator-game-status" class="creator-game-status">
                <h4>📊 Statut de la partie</h4>
                <div id="status-loading">⏳ Vérification du statut...</div>
                <div id="status-content" style="display:none;">
                    <p id="status-message" class="status-message"></p>
                    <div id="status-details" class="status-details"></div>
                    
                    <div id="creator-objective-section" style="display:none; margin-top:20px; text-align:center;">
                        <button id="creator-go-objectives" class="objectif-button objectif-primary creator-go">
                            🎯 Aller à mes objectifs
                        </button>
                    </div>
                    
                    <div style="text-align:center; margin-top:15px;">
                        <button id="refresh-creator-status" class="objectif-button objectif-secondary">
                            🔄 Actualiser le statut
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    function checkCreatorGameStatus(gameId) {
        console.log('🔍 Vérification statut créateur pour game_id:', gameId);
        
        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_check_game_status',
                nonce: objectif_ajax.nonce,
                game_id: gameId
            },
            timeout: 10000,
            success: function(response) {
                updateStatusDisplay(response, gameId);
            },
            error: function(xhr, status, error) {
                handleStatusError(xhr);
            }
        });
    }

    function updateStatusDisplay(response, gameId) {
        $('#status-loading').hide();
        $('#status-content').show();
        
        if (response.success) {
            const status = response.data;
            console.log('📊 Statut créateur reçu:', status);

            $('#status-message').text(status.status_message);
            
            $('#status-details').html(`
                <span class="status-item ${status.all_connected ? 'status-ok' : 'status-waiting'}">
                    👥 Joueurs connectés: ${status.connected_players}/${status.total_players}
                </span>
            `);
            
            if (status.all_connected) {
                showObjectiveButton(gameId);
            } else {
                $('#creator-objective-section').hide();
            }
        } else {
            $('#status-message').text('⚠️ Erreur: ' + response.data);
            $('#status-details').html('');
        }
    }

    function showObjectiveButton(gameId) {
        $('#creator-objective-section').show();
        
        const creatorUrl = objectif_ajax.objectif_url
            + '?player_id=' + localStorage.getItem('objectif_player_id')
            + '&game_id=' + gameId
            + '&creator=1';
        
        $('#creator-go-objectives').off('click').on('click', function() {
            window.location.href = creatorUrl;
        });
    }

    function handleStatusError(xhr) {
        console.error('❌ Erreur AJAX statut créateur:', xhr);
        $('#status-loading').hide();
        $('#status-content').show();
        $('#status-message').text(`⚠️ Erreur de connexion (${xhr.status})`);
        $('#status-details').html('');
    }

    function startCreatorStatusAutoRefresh() {
        const gameId = localStorage.getItem('objectif_game_id');
        if (gameId && $('#creator-game-status').length > 0) {
            ObjectifGame.creatorStatusInterval = setInterval(() => {
                if ($('#creator-objective-section:visible').length === 0) {
                    console.log('🔄 Auto-refresh statut créateur');
                    checkCreatorGameStatus(gameId);
                } else {
                    clearInterval(ObjectifGame.creatorStatusInterval);
                    console.log('✅ Auto-refresh arrêté - tous connectés');
                }
            }, 10000);
        }
    }

    // Event handlers
    $(document).on('click', '#refresh-creator-status', function() {
        console.log('🔄 Refresh statut créateur');
        
        $('#status-loading').show();
        $('#status-content').hide();
        
        const gameId = localStorage.getItem('objectif_game_id');
        if (gameId) {
            setTimeout(() => {
                checkCreatorGameStatus(gameId);
            }, 500);
        }
    });

    return {
        generateStatusHTML,
        checkCreatorGameStatus,
        startCreatorStatusAutoRefresh
    };

})(jQuery);