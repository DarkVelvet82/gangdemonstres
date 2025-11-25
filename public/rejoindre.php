<?php
require_once __DIR__ . '/../includes/front-header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejoindre une partie - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="../assets/css/objectif.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-height: 100dvh;
        }
        .page-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 420px;
            margin: 0 auto;
            width: 100%;
        }
        .objectif-join-game {
            background: #fff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .join-instructions {
            text-align: center;
            margin-bottom: 25px;
        }
        .join-instructions p {
            color: #666;
            font-size: 15px;
            line-height: 1.5;
            margin: 0;
        }
        .join-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .join-form label {
            display: none;
        }
        .code-input-wrapper {
            display: flex;
            justify-content: center;
            gap: 6px;
        }
        .code-digit {
            width: 42px;
            height: 52px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: #f8f9fa;
            transition: all 0.2s ease;
            color: #333;
            padding: 0;
        }
        @media (max-width: 360px) {
            .code-input-wrapper {
                gap: 4px;
            }
            .code-digit {
                width: 38px;
                height: 48px;
                font-size: 22px;
                border-radius: 8px;
            }
        }
        .code-digit:focus {
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            outline: none;
        }
        .code-digit.filled {
            border-color: #667eea;
            background: #fff;
        }
        .code-digit.error {
            border-color: #dc3545;
            background: #fff5f5;
            animation: shake 0.4s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        #objectif-player-code {
            display: none;
        }
        #objectif-join-button {
            padding: 16px 24px;
            font-size: 17px;
            font-weight: 600;
            border-radius: 12px;
            margin-top: 10px;
        }
        #objectif-join-result {
            text-align: center;
        }
        #objectif-join-result .error {
            background: #fee;
            color: #c00;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
        }
        #objectif-join-result .success {
            background: #efe;
            color: #060;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="container">
            <?php echo render_page_header('Rejoindre une partie', 'index.php'); ?>

            <div class="objectif-join-game">
                <div class="join-instructions">
                    <p>Entrez le code a 6 chiffres qui vous a ete donne par le createur de la partie</p>
                </div>

                <div class="join-form">
                    <label for="objectif-player-code">Code joueur :</label>
                    <div class="code-input-wrapper">
                        <input type="text" class="code-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="0">
                        <input type="text" class="code-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="1">
                        <input type="text" class="code-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="2">
                        <input type="text" class="code-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="3">
                        <input type="text" class="code-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="4">
                        <input type="text" class="code-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="5">
                    </div>
                    <input type="hidden" id="objectif-player-code">
                    <button id="objectif-join-button" class="objectif-button objectif-primary">Rejoindre la partie</button>
                </div>

                <div id="objectif-join-result" style="margin-top:20px;"></div>
                <div id="objectif-redirect" style="margin-top:10px;"></div>
            </div>
        </div>
    </div>

    <script src="../assets/js/app-config.js"></script>
    <script src="../assets/js/modal-component.js"></script>
    <script src="../assets/js/objectif-main.js"></script>
    <script src="../assets/js/objectif-join.js"></script>
    <script>
    (function() {
        const digits = document.querySelectorAll('.code-digit');
        const hiddenInput = document.getElementById('objectif-player-code');

        function updateHiddenInput() {
            let code = '';
            digits.forEach(d => code += d.value);
            hiddenInput.value = code;
        }

        function focusNext(index) {
            if (index < digits.length - 1) {
                digits[index + 1].focus();
            }
        }

        function focusPrev(index) {
            if (index > 0) {
                digits[index - 1].focus();
            }
        }

        digits.forEach((digit, index) => {
            digit.addEventListener('input', function(e) {
                const value = this.value.replace(/[^0-9]/g, '');
                this.value = value.slice(-1);

                if (value) {
                    this.classList.add('filled');
                    focusNext(index);
                } else {
                    this.classList.remove('filled');
                }

                this.classList.remove('error');
                updateHiddenInput();
            });

            digit.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace') {
                    if (!this.value) {
                        focusPrev(index);
                    } else {
                        this.value = '';
                        this.classList.remove('filled');
                        updateHiddenInput();
                    }
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    focusPrev(index);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    focusNext(index);
                }
            });

            digit.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const numbers = paste.replace(/[^0-9]/g, '').slice(0, 6);

                numbers.split('').forEach((num, i) => {
                    if (digits[i]) {
                        digits[i].value = num;
                        digits[i].classList.add('filled');
                    }
                });

                updateHiddenInput();

                if (numbers.length > 0) {
                    const lastIndex = Math.min(numbers.length, digits.length) - 1;
                    digits[lastIndex].focus();
                }
            });

            digit.addEventListener('focus', function() {
                this.select();
            });
        });

        // Focus first digit on page load
        setTimeout(() => digits[0].focus(), 100);
    })();
    </script>
</body>
</html>
