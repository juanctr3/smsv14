jQuery(document).ready(function($) {
    'use strict';

    /**
     * INICIO: Lógica del Selector de Emojis
     */
    function initialize_emoji_picker() {
        // Si el panel ya existe, no hacer nada.
        if ($('#smsenlinea-emoji-panel').length > 0) {
            return;
        }

        var emojiCategories = {
            'Sugeridos': ['👋', '👍', '✅', '💡', '🚚', '📦', '💳', '💰', '🎉', '🚀', '🗓️', '⏰', '📞', '✉️', '😊', '🙏', '⭐', '🔥', '💯', 'ℹ️'],
            'Caritas y Personas': ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '🙂', '😉', '😇', '🥰', '😍', '🤩', '😘', '😗', '😋', '😛', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🥳', '🤯', '🤔', '🤗', '🤝', '🤞', ' G'],
            'Animales y Naturaleza': ['🐶', '🐱', '🐭', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐵', '🌸', '🌹', '🌷', '🌻', '🌲', '🌵', '🌿'],
            'Comida y Bebida': ['🍇', '🍈', '🍉', '🍊', '🍋', '🍌', '🍍', '🥭', '🍎', '🍓', '🍒', '🍑', '🥝', '🍅', '🥥', '🥑', '🍆', '🌶️', '🍔', '🍕', '🎂', '☕'],
            'Objetos y Símbolos': ['❤️', '💔', '✔️', '❌', '❓', '❗', '💬', '📢', '🔔', '🎁', '💡', '💻', '📱', '☎️', '✉️', '📎', '✏️', '🔒', '🔑', '⚙️']
        };

        var emojiPanel = $('<div id="smsenlinea-emoji-panel"></div>').hide();
        var panelContent = '';

        $.each(emojiCategories, function(category, emojiList) {
            panelContent += '<div class="emoji-category"><h4>' + category + '</h4>';
            emojiList.forEach(function(emoji) {
                panelContent += '<span class="smsenlinea-emoji">' + emoji + '</span>';
            });
            panelContent += '</div>';
        });

        emojiPanel.html(panelContent);
        $('body').append(emojiPanel);

        var currentTextarea = null;

        // Mostrar/ocultar el panel al hacer clic en el botón de emoji
        $(document).on('click', '.smsenlinea-emoji-picker-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            currentTextarea = $('#' + $(this).data('target'));
            var buttonPos = $(this).offset();
            
            emojiPanel.css({
                top: buttonPos.top + $(this).outerHeight() + 5, // Aparece debajo del botón
                left: buttonPos.left
            }).toggle();
        });

        // Insertar el emoji en el textarea
        $(document).on('click', '.smsenlinea-emoji', function(e) {
            e.preventDefault();
            var emoji = $(this).text();
            if (currentTextarea && currentTextarea.length > 0) {
                var currentVal = currentTextarea.val();
                var cursorPos = currentTextarea[0].selectionStart;
                var newVal = currentVal.substring(0, cursorPos) + emoji + currentVal.substring(cursorPos);
                currentTextarea.val(newVal).focus();
                currentTextarea[0].selectionStart = currentTextarea[0].selectionEnd = cursorPos + emoji.length;
            }
        });

        // Ocultar el panel si se hace clic en cualquier otro lugar
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#smsenlinea-emoji-panel, .smsenlinea-emoji-picker-btn').length) {
                emojiPanel.hide();
            }
        });
    }

    initialize_emoji_picker();

    /**
     * FIN: Lógica del Selector de Emojis
     */


    // --- Lógica para el botón de prueba de envío ---
    $('#smsenlinea-send-test-btn').on('click', function(e) {
        e.preventDefault();
        var button = $(this), spinner = button.siblings('.spinner'), responseDiv = $('#smsenlinea-test-response');
        var testPhone = $('#test_phone').val(), testMessage = $('#test_message_area').val();
        var selectedChannel = $('input[name="wc_smsenlinea_settings[test_channel]"]:checked').val();

        if (!testPhone || !testMessage) {
            responseDiv.html('<div class="notice notice-error is-dismissible"><p>Por favor, ingresa un número y un mensaje.</p></div>').slideDown();
            return;
        }

        button.prop('disabled', true);
        spinner.addClass('is-active');
        responseDiv.slideUp().empty();

        $.ajax({
            url: smsenlinea_ajax.ajax_url,
            type: 'POST',
            data: { 
                action: 'smsenlinea_send_test', 
                nonce: smsenlinea_ajax.nonce, 
                phone: testPhone, 
                message: testMessage,
                channel: selectedChannel 
            },
            success: function(response) {
                if (response && typeof response.success !== 'undefined') {
                    var noticeClass = response.success ? 'notice-success' : 'notice-error';
                    var message = response.data && response.data.message ? response.data.message : 'Respuesta desconocida del servidor.';
                    responseDiv.html('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
                } else {
                    responseDiv.html('<div class="notice notice-error is-dismissible"><p>Error: El servidor devolvió una respuesta inesperada.</p></div>');
                }
            },
            error: function() {
                responseDiv.html('<div class="notice notice-error is-dismissible"><p>Ocurrió un error inesperado al procesar la solicitud.</p></div>');
            },
            complete: function() {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
                responseDiv.slideDown();
            }
        });
    });

    // Lógica para insertar variables en los textareas
    $(document).on('click', '.insert-variable-btn', function(e) {
        e.preventDefault();
        var variable = $(this).data('variable');
        var textarea = $(this).closest('.notification-block, .indented, .form-table tr').find('textarea');
        
        if (textarea.length > 0) {
            var currentVal = textarea.val();
            var cursorPos = textarea[0].selectionStart;
            var newVal = currentVal.substring(0, cursorPos) + ' ' + variable + ' ' + currentVal.substring(cursorPos);
            textarea.val(newVal).focus();
            textarea[0].selectionStart = textarea[0].selectionEnd = cursorPos + variable.length + 2;
        }
    });

    // Lógica para mostrar/ocultar las variables
    $(document).on('click', '.toggle-variables-link', function(e) {
        e.preventDefault();
        var wrapper = $(this).siblings('.variable-buttons-wrapper');
        var link = $(this);
        wrapper.slideToggle('fast', function() {
            if ($(this).is(':visible')) {
                link.text('Ocultar variables');
            } else {
                link.text('Ver variables');
            }
        });
    });
});
