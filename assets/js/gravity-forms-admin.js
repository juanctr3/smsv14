jQuery(document).ready(function($) {
    'use strict';

    // Initialize Gravity Forms admin enhancements
    const SMSenlineaGF = {
        
        init: function() {
            this.setupConditionalLogic();
            this.setupTestButtons();
            this.setupMessagePreview();
            this.setupPhoneValidation();
            this.setupMergeTagHelper();
        },
        
        // Setup conditional logic display
        setupConditionalLogic: function() {
            const $enableConditional = $('input[name="_gform_setting_enable_conditional"]');
            const $conditionalFields = $('.gform_setting_conditional_field, .gform_setting_conditional_operator, .gform_setting_conditional_value');
            
            function toggleConditionalFields() {
                if ($enableConditional.is(':checked')) {
                    $conditionalFields.show();
                } else {
                    $conditionalFields.hide();
                }
            }
            
            $enableConditional.on('change', toggleConditionalFields);
            toggleConditionalFields(); // Initial state
        },
        
        // Setup test message buttons
        setupTestButtons: function() {
            $('#smsenlinea-gf-test-customer').on('click', this.testCustomerMessage);
            $('#smsenlinea-gf-test-admin').on('click', this.testAdminMessage);
        },
        
        // Test customer message
        testCustomerMessage: function(e) {
            e.preventDefault();
            SMSenlineaGF.sendTestMessage('customer');
        },
        
        // Test admin message
        testAdminMessage: function(e) {
            e.preventDefault();
            SMSenlineaGF.sendTestMessage('admin');
        },
        
        // Send test message
        sendTestMessage: function(type) {
            const $button = $('#smsenlinea-gf-test-' + type);
            const $results = $('#smsenlinea-gf-test-results');
            const testPhone = $('input[name="_gform_setting_test_phone"]').val();
            
            if (!testPhone) {
                this.showTestResult('error', 'Please enter a test phone number first.');
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true).text('Sending...');
            $results.html('<div class="notice notice-info"><p>Sending test message...</p></div>');
            
            $.ajax({
                url: smsenlinea_gf_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smsenlinea_gf_test',
                    nonce: smsenlinea_gf_ajax.nonce,
                    form_id: smsenlinea_gf_ajax.form_id,
                    test_phone: testPhone,
                    message_type: type
                },
                success: function(response) {
                    if (response.success) {
                        SMSenlineaGF.showTestResult('success', response.data.message);
                    } else {
                        SMSenlineaGF.showTestResult('error', response.data.message || 'Unknown error occurred');
                    }
                },
                error: function() {
                    SMSenlineaGF.showTestResult('error', 'AJAX request failed. Please try again.');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text('Test ' + type.charAt(0).toUpperCase() + type.slice(1) + ' Message');
                }
            });
        },
        
        // Show test result
        showTestResult: function(type, message) {
            const $results = $('#smsenlinea-gf-test-results');
            const cssClass = type === 'success' ? 'notice-success' : 'notice-error';
            
            $results.html('<div class="notice ' + cssClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Auto-hide after 10 seconds
            setTimeout(function() {
                $results.fadeOut();
            }, 10000);
        },
        
        // Setup message preview
        setupMessagePreview: function() {
            const $customerMessage = $('textarea[name="_gform_setting_customer_message"]');
            const $adminMessage = $('textarea[name="_gform_setting_admin_message"]');
            
            // Add character counter
            this.addCharacterCounter($customerMessage, 'Customer Message');
            this.addCharacterCounter($adminMessage, 'Admin Message');
            
            // Add message preview
            $customerMessage.after('<div class="smsenlinea-message-preview" id="customer-preview"></div>');
            $adminMessage.after('<div class="smsenlinea-message-preview" id="admin-preview"></div>');
            
            $customerMessage.on('input', function() {
                SMSenlineaGF.updateMessagePreview($(this), '#customer-preview');
            });
            
            $adminMessage.on('input', function() {
                SMSenlineaGF.updateMessagePreview($(this), '#admin-preview');
            });
            
            // Initial preview
            this.updateMessagePreview($customerMessage, '#customer-preview');
            this.updateMessagePreview($adminMessage, '#admin-preview');
        },
        
        // Add character counter
        addCharacterCounter: function($textarea, label) {
            const counterId = $textarea.attr('id') + '-counter';
            $textarea.after('<div class="smsenlinea-char-counter" id="' + counterId + '"></div>');
            
            const updateCounter = function() {
                const length = $textarea.val().length;
                const $counter = $('#' + counterId);
                
                let color = '#666';
                if (length > 1600) color = '#dc3232'; // Error
                else if (length > 1200) color = '#ffb900'; // Warning
                else if (length > 800) color = '#00a0d2'; // Info
                
                $counter.html('<small style="color: ' + color + ';">' + label + ': ' + length + ' characters</small>');
                
                if (length > 1600) {
                    $counter.append('<br><small style="color: #dc3232;">⚠️ Message too long! Consider shortening it.</small>');
                } else if (length > 1200) {
                    $counter.append('<br><small style="color: #ffb900;">⚠️ Long message may be split into multiple parts.</small>');
                }
            };
            
            $textarea.on('input', updateCounter);
            updateCounter(); // Initial count
        },
        
        // Update message preview
        updateMessagePreview: function($textarea, previewSelector) {
            const message = $textarea.val();
            const $preview = $(previewSelector);
            
            if (message.length === 0) {
                $preview.hide();
                return;
            }
            
            // Simple preview (can't process actual merge tags without server-side processing)
            let previewText = message
                .replace(/\{([^}]+)\}/g, '<span class="merge-tag-preview">[$1]</span>')
                .replace(/\n/g, '<br>');
            
            $preview.html('<div class="message-preview-content"><strong>Preview:</strong><br>' + previewText + '</div>').show();
        },
        
        // Setup phone validation
        setupPhoneValidation: function() {
            const $testPhone = $('input[name="_gform_setting_test_phone"]');
            const $adminPhones = $('input[name="_gform_setting_admin_phones"]');
            
            // Add validation for test phone
            $testPhone.on('blur', function() {
                const phone = $(this).val();
                if (phone && !SMSenlineaGF.isValidPhoneFormat(phone)) {
                    SMSenlineaGF.showPhoneValidationError($(this), 'Please include country code (e.g., +1234567890)');
                } else {
                    SMSenlineaGF.clearPhoneValidationError($(this));
                }
            });
            
            // Add validation for admin phones
            $adminPhones.on('blur', function() {
                const phones = $(this).val();
                if (phones) {
                    const phoneList = phones.split(',').map(p => p.trim());
                    const invalidPhones = phoneList.filter(p => p && !SMSenlineaGF.isValidPhoneFormat(p));
                    
                    if (invalidPhones.length > 0) {
                        SMSenlineaGF.showPhoneValidationError(
                            $(this), 
                            'Invalid phone format: ' + invalidPhones.join(', ') + '. Include country codes.'
                        );
                    } else {
                        SMSenlineaGF.clearPhoneValidationError($(this));
                    }
                }
            });
        },
        
        // Check if phone format is valid (basic validation)
        isValidPhoneFormat: function(phone) {
            // Basic validation - should start with + and have at least 10 digits
            const cleanPhone = phone.replace(/[^\d+]/g, '');
            return /^\+\d{10,15}$/.test(cleanPhone);
        },
        
        // Show phone validation error
        showPhoneValidationError: function($input, message) {
            this.clearPhoneValidationError($input);
            $input.after('<div class="smsenlinea-validation-error" style="color: #dc3232; font-size: 12px; margin-top: 4px;">' + message + '</div>');
            $input.addClass('error');
        },
        
        // Clear phone validation error
        clearPhoneValidationError: function($input) {
            $input.removeClass('error');
            $input.siblings('.smsenlinea-validation-error').remove();
        },
        
        // Setup merge tag helper
        setupMergeTagHelper: function() {
            // Add helpful merge tag suggestions
            const commonMergeTags = [
                '{all_fields}', '{entry_id}', '{entry_url}', '{form_title}', 
                '{date_created}', '{user_ip}', '{embed_url}'
            ];
            
            const $messageFields = $('textarea[name*="_message"]');
            
            $messageFields.each(function() {
                const $textarea = $(this);
                const $wrapper = $textarea.parent();
                
                // Add merge tag helper button
                const helperId = $textarea.attr('id') + '-merge-helper';
                $wrapper.append('<button type="button" class="button button-secondary merge-tag-helper" data-target="' + $textarea.attr('id') + '" id="' + helperId + '">Common Merge Tags</button>');
                
                // Create dropdown
                let dropdownHtml = '<div class="merge-tag-dropdown" id="' + helperId + '-dropdown" style="display: none;">';
                commonMergeTags.forEach(function(tag) {
                    dropdownHtml += '<div class="merge-tag-option" data-tag="' + tag + '">' + tag + '</div>';
                });
                dropdownHtml += '</div>';
                
                $wrapper.append(dropdownHtml);
            });
            
            // Handle merge tag helper clicks
            $('.merge-tag-helper').on('click', function(e) {
                e.preventDefault();
                const dropdownId = $(this).attr('id') + '-dropdown';
                const $dropdown = $('#' + dropdownId);
                
                // Hide other dropdowns
                $('.merge-tag-dropdown').not($dropdown).hide();
                
                // Toggle current dropdown
                $dropdown.toggle();
            });
            
            // Handle merge tag selection
            $(document).on('click', '.merge-tag-option', function() {
                const tag = $(this).data('tag');
                const $dropdown = $(this).closest('.merge-tag-dropdown');
                const targetId = $dropdown.attr('id').replace('-dropdown', '').replace('-merge-helper', '');
                const $textarea = $('#' + targetId);
                
                // Insert merge tag at cursor position
                SMSenlineaGF.insertAtCursor($textarea[0], tag);
                
                // Hide dropdown
                $dropdown.hide();
                
                // Trigger input event to update preview
                $textarea.trigger('input');
            });
            
            // Hide dropdowns when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.merge-tag-helper, .merge-tag-dropdown').length) {
                    $('.merge-tag-dropdown').hide();
                }
            });
        },
        
        // Insert text at cursor position
        insertAtCursor: function(element, text) {
            if (element.selectionStart || element.selectionStart === 0) {
                const startPos = element.selectionStart;
                const endPos = element.selectionEnd;
                element.value = element.value.substring(0, startPos) + 
                               text + 
                               element.value.substring(endPos, element.value.length);
                element.selectionStart = startPos + text.length;
                element.selectionEnd = startPos + text.length;
            } else {
                element.value += text;
            }
            element.focus();
        },
        
        // Setup form dependency toggles
        setupDependencyToggles: function() {
            // Handle customer notification dependencies
            const $enableCustomer = $('input[name="_gform_setting_enable_customer"]');
            const $customerFields = $('.gform_setting_customer_phone_field, .gform_setting_customer_message, .gform_setting_customer_channel, .gform_setting_partial_message');
            
            function toggleCustomerFields() {
                if ($enableCustomer.is(':checked')) {
                    $customerFields.show();
                } else {
                    $customerFields.hide();
                }
            }
            
            $enableCustomer.on('change', toggleCustomerFields);
            toggleCustomerFields();
            
            // Handle admin notification dependencies
            const $enableAdmin = $('input[name="_gform_setting_enable_admin"]');
            const $adminFields = $('.gform_setting_admin_phones, .gform_setting_admin_message, .gform_setting_admin_channel');
            
            function toggleAdminFields() {
                if ($enableAdmin.is(':checked')) {
                    $adminFields.show();
                } else {
                    $adminFields.hide();
                }
            }
            
            $enableAdmin.on('change', toggleAdminFields);
            toggleAdminFields();
        },
        
        // Setup advanced settings
        setupAdvancedSettings: function() {
            // Add tooltips for advanced settings
            const tooltips = {
                'send_delay': 'Useful when form triggers payment processing. Delay ensures payment is complete before sending notification.',
                'skip_spam': 'Recommended to avoid sending notifications for spam submissions detected by Gravity Forms.',
                'enable_debug': 'Enable this only when troubleshooting issues. May impact performance.',
            };
            
            $.each(tooltips, function(fieldName, tooltip) {
                const $field = $('input[name="_gform_setting_' + fieldName + '"]');
                const $wrapper = $field.closest('.gform-settings-field__container');
                $wrapper.append('<div class="gform-settings-description" style="font-size: 12px; color: #666; margin-top: 4px;">' + tooltip + '</div>');
            });
        },
        
        // Setup real-time validation
        setupRealtimeValidation: function() {
            // Validate required fields on change
            const requiredFields = [
                { field: 'customer_phone_field', condition: 'enable_customer', message: 'Please select a phone field for customer notifications.' },
                { field: 'customer_message', condition: 'enable_customer', message: 'Please enter a message for customer notifications.' },
                { field: 'admin_message', condition: 'enable_admin', message: 'Please enter a message for admin notifications.' }
            ];
            
            requiredFields.forEach(function(fieldConfig) {
                const $field = $('[name="_gform_setting_' + fieldConfig.field + '"]');
                const $condition = $('[name="_gform_setting_' + fieldConfig.condition + '"]');
                
                function validateField() {
                    if ($condition.is(':checked') && !$field.val()) {
                        SMSenlineaGF.showFieldError($field, fieldConfig.message);
                    } else {
                        SMSenlineaGF.clearFieldError($field);
                    }
                }
                
                $field.on('blur', validateField);
                $condition.on('change', validateField);
            });
        },
        
        // Show field error
        showFieldError: function($field, message) {
            this.clearFieldError($field);
            $field.addClass('error').after('<div class="field-error" style="color: #dc3232; font-size: 12px; margin-top: 4px;">' + message + '</div>');
        },
        
        // Clear field error
        clearFieldError: function($field) {
            $field.removeClass('error').siblings('.field-error').remove();
        }
    };
    
    // Initialize when document is ready
    SMSenlineaGF.init();
    SMSenlineaGF.setupDependencyToggles();
    SMSenlineaGF.setupAdvancedSettings();
    SMSenlineaGF.setupRealtimeValidation();
    
    // Add save confirmation
    $('form#gform-settings').on('submit', function() {
        // Show saving indicator
        const $submitButton = $('input[type="submit"]', this);
        const originalText = $submitButton.val();
        $submitButton.val('Saving...').prop('disabled', true);
        
        // Re-enable after a delay (form submission will reload page anyway)
        setTimeout(function() {
            $submitButton.val(originalText).prop('disabled', false);
        }, 3000);
    });
    
    // Add form validation before submit
    $('form#gform-settings').on('submit', function(e) {
        const $enableNotifications = $('input[name="_gform_setting_enable_notifications"]');
        const $enableCustomer = $('input[name="_gform_setting_enable_customer"]');
        const $enableAdmin = $('input[name="_gform_setting_enable_admin"]');
        
        // If notifications are enabled but neither customer nor admin is enabled
        if ($enableNotifications.is(':checked') && !$enableCustomer.is(':checked') && !$enableAdmin.is(':checked')) {
            e.preventDefault();
            alert('Please enable either Customer or Admin notifications, or disable the notification feature entirely.');
            return false;
        }
        
        // Validate customer settings if enabled
        if ($enableCustomer.is(':checked')) {
            const phoneField = $('select[name="_gform_setting_customer_phone_field"]').val();
            const message = $('textarea[name="_gform_setting_customer_message"]').val();
            
            if (!phoneField || !message) {
                e.preventDefault();
                alert('Customer notifications are enabled but phone field or message is missing.');
                return false;
            }
        }
        
        // Validate admin settings if enabled
        if ($enableAdmin.is(':checked')) {
            const message = $('textarea[name="_gform_setting_admin_message"]').val();
            
            if (!message) {
                e.preventDefault();
                alert('Admin notifications are enabled but message is missing.');
                return false;
            }
        }
        
        return true;
    });
    
    // Add helpful hints
    const hints = {
        'customer_message': 'Tip: Use merge tags like {Name:1} to personalize messages. Keep messages concise and friendly.',
        'admin_message': 'Tip: Use {all_fields} to include all form data, or select specific merge tags for just the data you need.',
        'test_phone': 'Tip: Always include the country code (e.g., +573001234567 for Colombia).',
        'admin_phones': 'Tip: You can add multiple phone numbers separated by commas. Example: +573001234567, +573007654321',
    };
    
    $.each(hints, function(fieldName, hint) {
        const $field = $('[name="_gform_setting_' + fieldName + '"]');
        if ($field.length) {
            $field.after('<div class="smsenlinea-hint" style="font-size: 11px; color: #72777c; font-style: italic; margin-top: 4px;">' + hint + '</div>');
        }
    });
});
