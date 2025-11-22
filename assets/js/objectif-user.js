// js/objectif-user.js - Gestion des utilisateurs

(function($) {
    'use strict';

    // État utilisateur
    let currentUser = null;

    // Initialisation
    $(document).ready(function() {
        // Vérifier si déjà connecté
        const savedUser = localStorage.getItem('objectif_user');
        if (savedUser) {
            currentUser = JSON.parse(savedUser);
            showDashboard();
            loadUserData();
        }

        // Tabs
        $('.auth-tab').on('click', function() {
            const tab = $(this).data('tab');
            $('.auth-tab').removeClass('active');
            $(this).addClass('active');
            $('.auth-panel').removeClass('active');
            $('#panel-' + tab).addClass('active');
            hideError();
        });

        // Login
        $('#login-form').on('submit', function(e) {
            e.preventDefault();
            login();
        });

        // Register
        $('#register-form').on('submit', function(e) {
            e.preventDefault();
            register();
        });

        // Forgot code
        $('#forgot-code-link').on('click', function(e) {
            e.preventDefault();
            $('.auth-panel').removeClass('active');
            $('#panel-forgot').addClass('active');
        });

        $('#back-to-login').on('click', function() {
            $('.auth-panel').removeClass('active');
            $('#panel-login').addClass('active');
        });

        $('#forgot-form').on('submit', function(e) {
            e.preventDefault();
            sendCode();
        });

        // Continue after register
        $('#btn-continue-after-register').on('click', function() {
            showDashboard();
            loadUserData();
        });

        // Add player
        $('#btn-add-player').on('click', addPlayer);
        $('#new-player-name').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                addPlayer();
            }
        });

        // Logout
        $('#btn-logout').on('click', logout);

        // Code input formatting
        $('#login-code').on('input', function() {
            this.value = this.value.toUpperCase();
        });
    });

    function showError(message) {
        $('#auth-error').text(message).show();
    }

    function hideError() {
        $('#auth-error').hide();
    }

    function login() {
        const prenom = $('#login-prenom').val().trim();
        const code = $('#login-code').val().trim().toUpperCase();

        if (!prenom || !code) {
            showError('Veuillez remplir tous les champs');
            return;
        }

        if (code.length !== 5) {
            showError('Le code doit contenir 5 caractères');
            return;
        }

        const $btn = $('#btn-login');
        $btn.prop('disabled', true).text('Connexion...');
        hideError();

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_user_login',
                nonce: objectif_ajax.nonce,
                prenom: prenom,
                code: code
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Se connecter');

                if (response.success) {
                    currentUser = response.data;
                    currentUser.code = code; // Garder le code pour l'affichage
                    localStorage.setItem('objectif_user', JSON.stringify(currentUser));
                    showDashboard();
                    loadUserData();
                } else {
                    showError(response.message || 'Prénom ou code incorrect');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Se connecter');
                showError('Erreur de connexion au serveur');
            }
        });
    }

    function register() {
        const prenom = $('#register-prenom').val().trim();
        const email = $('#register-email').val().trim();
        const honeypot = $('#register-website').val(); // Honeypot anti-spam

        if (!prenom || !email) {
            showError('Veuillez remplir tous les champs');
            return;
        }

        const $btn = $('#btn-register');
        $btn.prop('disabled', true).text('Création...');
        hideError();

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_user_register',
                nonce: objectif_ajax.nonce,
                prenom: prenom,
                email: email,
                website: honeypot
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Créer mon compte');

                if (response.success) {
                    currentUser = {
                        user_id: response.data.user_id,
                        prenom: response.data.prenom,
                        code: response.data.code_unique,
                        players: []
                    };
                    localStorage.setItem('objectif_user', JSON.stringify(currentUser));

                    // Afficher le code
                    $('#new-user-code').text(response.data.code_unique);
                    $('.auth-panel').removeClass('active');
                    $('#panel-success').addClass('active');
                } else {
                    showError(response.message || 'Erreur lors de l\'inscription');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Créer mon compte');
                showError('Erreur de connexion au serveur');
            }
        });
    }

    function sendCode() {
        const email = $('#forgot-email').val().trim();

        if (!email) {
            showError('Veuillez entrer votre email');
            return;
        }

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_user_send_code',
                nonce: objectif_ajax.nonce,
                email: email
            },
            success: function(response) {
                alert('Si cet email est enregistré, vous recevrez votre code par email.');
                $('.auth-panel').removeClass('active');
                $('#panel-login').addClass('active');
            },
            error: function() {
                showError('Erreur de connexion au serveur');
            }
        });
    }

    function showDashboard() {
        $('#auth-section').hide();
        $('#user-dashboard').show();
        $('#user-prenom').text(currentUser.prenom);
        $('#user-code-display').text(currentUser.code || '');
    }

    function loadUserData() {
        // Charger les joueurs fréquents
        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_user_get_players',
                nonce: objectif_ajax.nonce,
                user_id: currentUser.user_id
            },
            success: function(response) {
                if (response.success) {
                    currentUser.players = response.data.players;
                    localStorage.setItem('objectif_user', JSON.stringify(currentUser));
                    renderPlayers();
                }
            }
        });

        // Charger l'historique
        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_user_get_history',
                nonce: objectif_ajax.nonce,
                user_id: currentUser.user_id,
                limit: 10
            },
            success: function(response) {
                if (response.success) {
                    renderHistory(response.data.games);
                }
            }
        });
    }

    function renderPlayers() {
        const $list = $('#players-list');
        $list.empty();

        if (!currentUser.players || currentUser.players.length === 0) {
            $list.html('<p style="color:#999; font-size:14px;">Aucun joueur enregistré</p>');
            return;
        }

        currentUser.players.forEach(function(player) {
            $list.append(`
                <div class="player-tag" data-id="${player.id}">
                    ${player.player_name}
                    <span class="remove-btn" title="Supprimer">&times;</span>
                </div>
            `);
        });

        // Event remove
        $list.find('.remove-btn').on('click', function() {
            const $tag = $(this).closest('.player-tag');
            const playerId = $tag.data('id');
            removePlayer(playerId, $tag);
        });
    }

    function addPlayer() {
        const name = $('#new-player-name').val().trim();

        if (!name) {
            return;
        }

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_user_add_player',
                nonce: objectif_ajax.nonce,
                user_id: currentUser.user_id,
                player_name: name
            },
            success: function(response) {
                if (response.success) {
                    $('#new-player-name').val('');
                    currentUser.players.push({
                        id: response.data.player_id,
                        player_name: response.data.player_name
                    });
                    localStorage.setItem('objectif_user', JSON.stringify(currentUser));
                    renderPlayers();
                } else {
                    alert(response.message || 'Erreur');
                }
            }
        });
    }

    function removePlayer(playerId, $element) {
        if (!confirm('Supprimer ce joueur de votre liste ?')) {
            return;
        }

        $.ajax({
            method: 'POST',
            url: objectif_ajax.ajax_url,
            data: {
                action: 'objectif_user_remove_player',
                nonce: objectif_ajax.nonce,
                user_id: currentUser.user_id,
                player_id: playerId
            },
            success: function(response) {
                if (response.success) {
                    currentUser.players = currentUser.players.filter(p => p.id !== playerId);
                    localStorage.setItem('objectif_user', JSON.stringify(currentUser));
                    $element.fadeOut(200, function() {
                        $(this).remove();
                        if (currentUser.players.length === 0) {
                            renderPlayers();
                        }
                    });
                }
            }
        });
    }

    function renderHistory(games) {
        const $list = $('#history-list');
        $list.empty();

        if (!games || games.length === 0) {
            $list.html('<p style="color:#999; text-align:center;">Aucune partie jouée</p>');
            return;
        }

        games.forEach(function(game) {
            const date = new Date(game.created_at).toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const playerNames = game.players.map(p => p.player_name).join(', ');
            const statusText = game.status === 'ended' ? 'Terminée' : 'En cours';
            const winnerHtml = game.winner_name
                ? `<span class="winner">Gagnant: ${game.winner_name}</span>`
                : '';

            $list.append(`
                <div class="history-item">
                    <div class="date">${date} - ${statusText}</div>
                    <div><strong>${game.config_name}</strong> (${game.player_count} joueurs)</div>
                    <div class="players">Joueurs: ${playerNames}</div>
                    ${winnerHtml}
                </div>
            `);
        });
    }

    function logout() {
        localStorage.removeItem('objectif_user');
        currentUser = null;
        $('#user-dashboard').hide();
        $('#auth-section').show();
        $('.auth-panel').removeClass('active');
        $('#panel-login').addClass('active');
        $('#login-prenom').val('');
        $('#login-code').val('');
    }

    // Exposer pour creer-partie.php
    window.ObjectifUser = {
        getCurrentUser: function() {
            return currentUser || JSON.parse(localStorage.getItem('objectif_user') || 'null');
        },
        getPlayers: function() {
            const user = this.getCurrentUser();
            return user ? user.players || [] : [];
        },
        isLoggedIn: function() {
            return this.getCurrentUser() !== null;
        }
    };

})(jQuery);
