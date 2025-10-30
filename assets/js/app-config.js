// app-config.js - Configuration pour l'application standalone

// Remplace les variables WordPress par des variables natives
window.objectif_ajax = {
    ajax_url: window.location.origin + '/gang-de-monstres-standalone/api/',
    nonce: 'standalone_nonce_' + Date.now(),
    objectif_url: window.location.origin + '/gang-de-monstres-standalone/public/objectif.php'
};

// Adapter jQuery pour compatibilité
(function() {
    // Créer un nonce simple (à améliorer avec une vraie génération côté serveur)
    if (!window.objectif_ajax.nonce) {
        window.objectif_ajax.nonce = 'nonce_' + Math.random().toString(36).substr(2, 9);
    }

    // Fonction utilitaire pour faire des requêtes AJAX
    window.objectifAjax = function(endpoint, action, data, successCallback, errorCallback) {
        const url = window.objectif_ajax.ajax_url + endpoint + '?action=' + action;

        // Ajouter le nonce aux données
        data.nonce = window.objectif_ajax.nonce;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                if (successCallback) successCallback(result);
            } else {
                if (errorCallback) errorCallback(result);
                else console.error('Error:', result.message);
            }
        })
        .catch(error => {
            console.error('Request failed:', error);
            if (errorCallback) errorCallback({success: false, message: error.message});
        });
    };

    // Adapter wp_send_json_success / wp_send_json_error pour jQuery
    if (window.jQuery) {
        const originalAjax = jQuery.ajax;

        jQuery.ajax = function(options) {
            // Intercepter les appels AJAX pour adapter les réponses
            const originalSuccess = options.success;

            options.success = function(response) {
                // Adapter le format WordPress vers le format standalone
                if (response && typeof response === 'object') {
                    if (response.success !== undefined) {
                        // Format standalone déjà correct
                        if (originalSuccess) originalSuccess(response);
                    } else {
                        // Format potentiellement différent
                        if (originalSuccess) originalSuccess(response);
                    }
                } else {
                    if (originalSuccess) originalSuccess(response);
                }
            };

            return originalAjax.call(this, options);
        };
    }
})();

console.log('✅ App Config chargé - Mode Standalone');
