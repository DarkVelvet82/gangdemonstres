// js/modal-component.js - Composant modal réutilisable
window.AppModal = (function($) {
    'use strict';

    // Créer le conteneur de modal s'il n'existe pas
    function ensureModalContainer() {
        if ($('#app-modal-container').length === 0) {
            $('body').append('<div id="app-modal-container"></div>');
        }
    }

    // Afficher une alerte (remplace alert())
    function showAlert(message, options = {}) {
        return new Promise((resolve) => {
            ensureModalContainer();

            const title = options.title || 'Information';
            const buttonText = options.buttonText || 'OK';
            const type = options.type || 'info'; // info, success, error, warning

            const icons = {
                info: 'ℹ️',
                success: '✅',
                error: '❌',
                warning: '⚠️'
            };

            const colors = {
                info: '#007cba',
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107'
            };

            const modalHtml = `
                <div class="app-modal-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    animation: appModalFadeIn 0.2s ease;
                ">
                    <div class="app-modal" style="
                        background: white;
                        padding: 30px;
                        border-radius: 16px;
                        text-align: center;
                        max-width: 320px;
                        width: calc(100% - 40px);
                        margin: 20px;
                        animation: appModalSlideUp 0.3s ease;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                    ">
                        <div style="font-size: 48px; margin-bottom: 15px;">${icons[type]}</div>
                        <h3 style="margin: 0 0 15px 0; color: #003f53; font-size: 20px;">${title}</h3>
                        <p style="color: #666; margin: 0 0 20px 0; font-size: 15px; line-height: 1.5;">${message}</p>
                        <button type="button" class="app-modal-btn-primary" style="
                            background: ${colors[type]};
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 8px;
                            font-size: 15px;
                            font-weight: 600;
                            cursor: pointer;
                            min-width: 100px;
                        ">${buttonText}</button>
                    </div>
                </div>
            `;

            const $modal = $(modalHtml);
            $('#app-modal-container').append($modal);

            // Fermer au clic sur le bouton
            $modal.find('.app-modal-btn-primary').on('click', function() {
                closeModal($modal, resolve, true);
            });

            // Fermer au clic sur l'overlay
            $modal.on('click', function(e) {
                if ($(e.target).hasClass('app-modal-overlay')) {
                    closeModal($modal, resolve, true);
                }
            });
        });
    }

    // Afficher une confirmation (remplace confirm())
    function showConfirm(message, options = {}) {
        return new Promise((resolve) => {
            ensureModalContainer();

            const title = options.title || 'Confirmation';
            const confirmText = options.confirmText || 'Confirmer';
            const cancelText = options.cancelText || 'Annuler';
            const type = options.type || 'warning'; // warning, danger, info
            const icon = options.icon || '❓';
            const image = options.image || null;

            const confirmColors = {
                warning: '#ffc107',
                danger: '#dc3545',
                info: '#007cba'
            };

            // Utiliser une image si fournie, sinon l'icône
            const iconHtml = image
                ? `<img src="${image}" alt="" style="width: 100%; max-width: 280px; height: auto; object-fit: contain; border-radius: 8px; margin-bottom: 15px;">`
                : `<div style="font-size: 48px; margin-bottom: 15px;">${icon}</div>`;

            const modalHtml = `
                <div class="app-modal-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    animation: appModalFadeIn 0.2s ease;
                ">
                    <div class="app-modal" style="
                        background: white;
                        padding: 30px;
                        border-radius: 16px;
                        text-align: center;
                        max-width: 340px;
                        width: calc(100% - 40px);
                        margin: 20px;
                        animation: appModalSlideUp 0.3s ease;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                    ">
                        ${iconHtml}
                        <h3 style="margin: 0 0 15px 0; color: #003f53; font-size: 20px;">${title}</h3>
                        <p style="color: #666; margin: 0 0 20px 0; font-size: 15px; line-height: 1.5;">${message}</p>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="app-modal-btn-cancel" style="
                                flex: 1;
                                background: #f0f0f0;
                                color: #333;
                                border: none;
                                padding: 12px 16px;
                                border-radius: 8px;
                                font-size: 15px;
                                font-weight: 600;
                                cursor: pointer;
                            ">${cancelText}</button>
                            <button type="button" class="app-modal-btn-confirm" style="
                                flex: 1;
                                background: ${confirmColors[type]};
                                color: ${type === 'warning' ? '#333' : 'white'};
                                border: none;
                                padding: 12px 16px;
                                border-radius: 8px;
                                font-size: 15px;
                                font-weight: 600;
                                cursor: pointer;
                            ">${confirmText}</button>
                        </div>
                    </div>
                </div>
            `;

            const $modal = $(modalHtml);
            $('#app-modal-container').append($modal);

            // Confirmer
            $modal.find('.app-modal-btn-confirm').on('click', function() {
                closeModal($modal, resolve, true);
            });

            // Annuler
            $modal.find('.app-modal-btn-cancel').on('click', function() {
                closeModal($modal, resolve, false);
            });

            // Fermer au clic sur l'overlay = annuler
            $modal.on('click', function(e) {
                if ($(e.target).hasClass('app-modal-overlay')) {
                    closeModal($modal, resolve, false);
                }
            });
        });
    }

    // Fermer la modal avec animation
    function closeModal($modal, resolve, result) {
        $modal.css('animation', 'appModalFadeOut 0.2s ease');
        $modal.find('.app-modal').css('animation', 'appModalSlideDown 0.2s ease');

        setTimeout(() => {
            $modal.remove();
            resolve(result);
        }, 180);
    }

    // Ajouter les styles CSS une seule fois
    if ($('#app-modal-styles').length === 0) {
        $('<style id="app-modal-styles">').text(`
            @keyframes appModalFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes appModalFadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
            @keyframes appModalSlideUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes appModalSlideDown {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(20px); }
            }
            .app-modal-btn-primary:hover,
            .app-modal-btn-confirm:hover {
                opacity: 0.9;
                transform: translateY(-1px);
            }
            .app-modal-btn-cancel:hover {
                background: #e0e0e0 !important;
            }
        `).appendTo('head');
    }

    return {
        alert: showAlert,
        confirm: showConfirm
    };

})(jQuery);
