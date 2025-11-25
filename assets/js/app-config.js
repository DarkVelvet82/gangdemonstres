// app-config.js - Configuration pour l'application standalone

// Mode debug si ?debug=1 dans l'URL
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('debug') === '1') {
        // Créer un conteneur pour les logs
        const debugDiv = document.createElement('div');
        debugDiv.id = 'debug-console';
        debugDiv.style.cssText = 'position:fixed;bottom:0;left:0;right:0;max-height:40vh;overflow-y:auto;background:#111;color:#0f0;font-family:monospace;font-size:11px;padding:10px;z-index:99999;';
        document.addEventListener('DOMContentLoaded', () => document.body.appendChild(debugDiv));

        const log = (type, ...args) => {
            const div = document.getElementById('debug-console');
            if (div) {
                const colors = {log:'#0f0',error:'#f00',warn:'#ff0',info:'#0ff'};
                const msg = document.createElement('div');
                msg.style.color = colors[type] || '#fff';
                msg.textContent = `[${type}] ${args.map(a => typeof a === 'object' ? JSON.stringify(a) : a).join(' ')}`;
                div.appendChild(msg);
                div.scrollTop = div.scrollHeight;
            }
        };

        // Intercepter console
        ['log','error','warn','info'].forEach(m => {
            const orig = console[m];
            console[m] = (...args) => { log(m, ...args); orig.apply(console, args); };
        });

        // Capturer les erreurs
        window.onerror = (msg, url, line, col, err) => {
            log('error', `${msg} at ${url}:${line}:${col}`);
            return false;
        };

        console.log('🔧 Debug mode activé');
    }
})();

// Remplace les variables WordPress par des variables natives
// Détecter le chemin de base automatiquement (pour local vs prod)
(function() {
    const path = window.location.pathname;
    // Trouver le dossier de base (ex: /gang-de-monstres-standalone/public/...)
    const match = path.match(/^(\/[^/]+)?\/public\//);
    const basePath = match ? (match[1] || '') : '';

    window.objectif_ajax = {
        ajax_url: window.location.origin + basePath + '/api/',
        nonce: 'standalone_nonce_' + Date.now(),
        objectif_url: window.location.origin + basePath + '/public/objectif.php'
    };

    console.log('📍 Base path détecté:', basePath || '(racine)');
    console.log('📍 API URL:', window.objectif_ajax.ajax_url);
})();

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
