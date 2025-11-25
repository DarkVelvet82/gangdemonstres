// js/objectif-join.js - Module connexion
window.ObjectifJoin = (function($) {
    'use strict';

    // Connexion via code
    $('#objectif-join-button').on('click', function() {
        const code = $('#objectif-player-code').val();
        joinGameWithCode(code);
    });

    function joinGameWithCode(code) {
        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url + 'game.php?action=join',
            data: {
                nonce: objectif_ajax.nonce,
                code: code
            },
            success: function(response) {
                handleJoinSuccess(response);
            },
            error: function(err) {
                AppModal.alert('Impossible de se connecter. Vérifiez votre connexion.', {
                    title: 'Erreur de connexion',
                    type: 'error'
                });
            }
        });
    }

    function handleJoinSuccess(response) {
        if (response.success) {
            console.log('🔍 Réponse du serveur:', response.data);

            // Stocker les données
            localStorage.setItem('objectif_player_id', response.data.player_id);
            localStorage.setItem('objectif_game_id', response.data.game_id);
            const isCreator = response.data.is_creator ? '1' : '0';
            localStorage.setItem('objectif_is_creator', isCreator);

            // Redirection directe vers la page d'objectif
            if (objectif_ajax.objectif_url) {
                const redirectUrl = objectif_ajax.objectif_url
                    + '?player_id=' + response.data.player_id
                    + '&game_id=' + response.data.game_id
                    + '&creator=' + response.data.is_creator;

                window.location.href = redirectUrl;
            }
        } else {
            AppModal.alert(response.data || 'Code invalide ou partie non trouvée', {
                title: 'Erreur',
                type: 'error'
            });
        }
    }

    function handleAutoJoin(playerCode) {
        console.log('🔗 Auto-join détecté avec le code:', playerCode);
        
        // Page de connexion
        if ($('#objectif-player-code').length > 0) {
            console.log('📝 Page de connexion détectée, auto-remplissage...');
            $('#objectif-player-code').val(playerCode);
            
            setTimeout(() => {
                $('#objectif-join-button').trigger('click');
            }, 500);
            
        } else {
            // Page objectifs - connexion directe
            console.log('🎯 Page objectifs détectée, connexion AJAX directe...');

            $.ajax({
                method: 'POST',
                url: objectif_ajax.ajax_url + 'game.php?action=join',
                data: {
                    nonce: objectif_ajax.nonce,
                    code: playerCode
                },
                success: function(response) {
                    handleAutoJoinSuccess(response);
                },
                error: function(err) {
                    console.error('❌ Erreur AJAX auto-connexion:', err);
                    AppModal.alert('Impossible de se connecter automatiquement. Veuillez réessayer.', {
                        title: 'Erreur de connexion',
                        type: 'error'
                    });
                }
            });
        }
    }

    function handleAutoJoinSuccess(response) {
        if (response.success) {
            console.log('✅ Auto-connexion réussie:', response.data);

            // Stocker les données
            localStorage.setItem('objectif_player_id', response.data.player_id);
            localStorage.setItem('objectif_game_id', response.data.game_id);
            localStorage.setItem('objectif_is_creator', response.data.is_creator ? '1' : '0');

            // Supprimer les anciens toasts avant d'en créer un nouveau
            $('.auto-join-toast').remove();

            // Afficher un toast discret qui disparaît
            const $toast = $(`
                <div class="auto-join-toast" style="
                    position: fixed;
                    top: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #28a745;
                    color: white;
                    padding: 12px 24px;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                    z-index: 9999;
                ">
                    ✅ Connexion réussie !
                </div>
            `);

            // Utiliser le conteneur dédié s'il existe, sinon body
            const $notifRoot = $('#notifications-root');
            if ($notifRoot.length) {
                $notifRoot.append($toast);
            } else {
                $('body').append($toast);
            }

            // Disparaît après 2 secondes
            setTimeout(function() {
                $('.auto-join-toast').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 2000);

        } else {
            console.error('❌ Erreur auto-connexion:', response.data);
            AppModal.alert(response.data || 'Code invalide ou partie non trouvée', {
                title: 'Erreur de connexion',
                type: 'error'
            });
        }
    }

    return {
        joinGameWithCode,
        handleAutoJoin
    };

})(jQuery);