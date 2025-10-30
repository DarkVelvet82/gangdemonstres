// js/objectif-qr.js - Module QR codes
window.ObjectifQR = (function($) {
    'use strict';

    function generateQRCode(url, containerId) {
        console.log('🔍 Génération QR code pour URL:', url, 'Container:', containerId);
        
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('❌ Conteneur QR code introuvable:', containerId);
            return;
        }
        
        // Vider le conteneur et ajouter un placeholder
        container.innerHTML = '<div class="qr-loading">⏳ Génération du QR code...</div>';
        
        tryGenerateQR(url, container);
    }

    function tryGenerateQR(url, container) {
        try {
            // Google Charts (plus fiable)
            let qrUrl = `https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=${encodeURIComponent(url)}`;
            
            const img = new Image();
            
            img.onload = function() {
                console.log('✅ QR code Google Charts chargé');
                container.innerHTML = '';
                container.appendChild(img);
            };
            
            img.onerror = function() {
                console.warn('⚠️ Google Charts échoué, tentative fallback...');
                tryFallbackQR(url, container);
            };
            
            img.src = qrUrl;
            img.alt = 'QR Code';
            img.className = 'qr-code-image';
            
        } catch (e) {
            console.error('❌ Erreur génération QR:', e);
            showQRError(url, container);
        }
    }

    function tryFallbackQR(url, container) {
        const fallbackUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&format=png&data=${encodeURIComponent(url)}`;
        
        const img2 = new Image();
        img2.onload = function() {
            console.log('✅ QR code qrserver chargé');
            container.innerHTML = '';
            container.appendChild(img2);
        };
        
        img2.onerror = function() {
            console.error('❌ Tous les services QR ont échoué');
            showQRError(url, container);
        };
        
        img2.src = fallbackUrl;
        img2.alt = 'QR Code';
        img2.className = 'qr-code-image';
    }

    function showQRError(url, container) {
        container.innerHTML = `
            <div class="qr-error">
                <p>❌ QR code indisponible</p>
                <small>Cliquez pour copier: <br><span onclick="navigator.clipboard.writeText('${url}')" style="cursor:pointer;color:#007cba;">${url}</span></small>
            </div>
        `;
    }

    return {
        generateQRCode
    };

})(jQuery);