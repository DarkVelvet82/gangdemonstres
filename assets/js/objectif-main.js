// js/objectif-main.js - Fichier principal
jQuery(document).ready(function($) {

    console.log('✅ SCRIPT PRINCIPAL CHARGÉ');

    // Variables globales
    window.ObjectifGame = {
        creatorStatusInterval: null,
        playersData: null
    };

    // Au chargement de la page, vérifier les paramètres URL
    const urlParams = new URLSearchParams(window.location.search);
    const playerId = urlParams.get('player_id');
    const gameId = urlParams.get('game_id');
    const isCreator = urlParams.get('creator');
    const playerCode = urlParams.get('player_code');
    const autoJoin = urlParams.get('auto_join');

    console.log('🔍 Paramètres URL détectés:', {playerId, gameId, isCreator, playerCode, autoJoin});

    // Auto-join via QR code
    if (playerCode && autoJoin === '1') {
        ObjectifJoin.handleAutoJoin(playerCode);
    }

    // Stocker les paramètres URL normaux
    if (playerId && gameId) {
        console.log('📦 Stockage des paramètres URL dans localStorage');
        localStorage.setItem('objectif_player_id', playerId);
        localStorage.setItem('objectif_game_id', gameId);
        localStorage.setItem('objectif_is_creator', isCreator || '0');
    }

    // Gestion dynamique des champs de prénoms
    $(document).on('change', '#objectif-player-count', function() {
        const count = parseInt($(this).val());
        const container = $('#other-players-inputs');
        container.empty();
        
        for (let i = 2; i <= count; i++) {
            const input = $('<input>');
            input.attr({
                'type': 'text',
                'class': 'form-control other-player-name',
                'placeholder': `Prénom du joueur ${i}`,
                'required': true
            });
            container.append(input);
        }
    });

    // Initialiser les champs de prénoms au chargement
    if ($('#objectif-player-count').length > 0) {
        $('#objectif-player-count').trigger('change');
    }

    // Nettoyage lors du déchargement de la page
    $(window).on('beforeunload', function() {
        if (ObjectifGame.creatorStatusInterval) {
            clearInterval(ObjectifGame.creatorStatusInterval);
        }
    });

    // Fonctions utilitaires de debug
    window.objectifDebug = function() {
        console.log('🔍 Debug localStorage:');
        console.log('- player_id:', localStorage.getItem('objectif_player_id'));
        console.log('- game_id:', localStorage.getItem('objectif_game_id'));
        console.log('- is_creator:', localStorage.getItem('objectif_is_creator'));
        console.log('- URL params:', window.location.search);
    };

    window.objectifCleanup = function() {
        localStorage.removeItem('objectif_player_id');
        localStorage.removeItem('objectif_game_id');
        localStorage.removeItem('objectif_is_creator');
        console.log('✅ localStorage nettoyé');
    };

});