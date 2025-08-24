<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gravity Forms Admin Settings Addon
 * 
 * Provides enhanced admin interface for configuring SMS/WhatsApp notifications
 * for individual Gravity Forms with advanced options
 */
class Gravity_Forms_Admin_Addon extends \GFAddOn {

    protected $_version = '1.4.0';
    protected $_min_gravityforms_version = '2.5';
    protected $_slug = 'smsenlinea_gf_notifications';
    protected $_title = 'SMSenlinea Notifications';
    protected $_short_title = 'SMSenlinea';
    
    private static $_instance = null;
    private $api_handler;
    
    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function init() {
        parent::init();
        $this->api_handler = new API_Handler();
        
        // Add processing hook
        add_action( 'gform_after_submission', [ $this, 'process_submission' ], 10, 2 );
        
        // Add admin styles and scripts
        add_action( 'gform_enqueue_scripts', [ $this, 'enqueue_form_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        
        // Add test functionality
        add_action( 'wp_ajax_smsenlinea_gf_test', [ $this, 'handle_test_message' ] );
    }
    
    /**
     * Define form settings fields
     */
    public function form_settings_fields( $form ) {
        $phone_fields = $this->get_phone_fields( $form );
        $text_fields = $this->get_text_fields( $form );
        
        return [
            [
                'title'  => esc_html__( 'SMSenlinea Notification Settings', 'smsenlinea-whatsapp-woocommerce' ),
                'fields' => [
                    // General Settings
                    [
                        'label'   => '<h3>' . esc_html__( 'General Settings', 'smsenlinea-whatsapp-woocommerce' ) . '</h3>',
                        'type'    => 'html',
                    ],
                    [
                        'label'   => esc_html__( 'Enable Notifications', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_notifications',
                        'choices' => [
                            [
                                'label' => esc_html__( 'Enable SMS/WhatsApp notifications for this form', 'smsenlinea-whatsapp-woocommerce' ),
                                'name'  => 'enable_notifications'
                            ]
                        ],
                        'tooltip' => '<h6>' . esc_html__( 'Master Switch', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Turn this on to enable all notification features for this form.', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                    
                    // Conditional Logic Section
                    [
                        'label'   => '<hr><h3>' . esc_html__( 'Conditional Logic (Optional)', 'smsenlinea-whatsapp-woocommerce' ) . '</h3>',
                        'type'    => 'html',
                    ],
                    [
                        'label'   => esc_html__( 'Enable Conditional Logic', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_conditional',
                        'choices' => [
                            [
                                'label' => esc_html__( 'Only send notifications when conditions are met', 'smsenlinea-whatsapp-woocommerce' ),
                                'name'  => 'enable_conditional'
                            ]
                        ],
                    ],
                    [
                        'label'       => esc_html__( 'Conditional Field', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'select',
                        'name'        => 'conditional_field',
                        'choices'     => $text_fields,
                        'dependency'  => [
                            'field'  => 'enable_conditional',
                            'values' => [ '1' ]
                        ],
                    ],
                    [
                        'label'       => esc_html__( 'Condition', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'select',
                        'name'        => 'conditional_operator',
                        'choices'     => [
                            [ 'label' => 'Equals', 'value' => 'is' ],
                            [ 'label' => 'Does not equal', 'value' => 'isnot' ],
                            [ 'label' => 'Contains', 'value' => 'contains' ],
                            [ 'label' => 'Does not contain', 'value' => 'not_contains' ],
                            [ 'label' => 'Is greater than', 'value' => 'greater_than' ],
                            [ 'label' => 'Is less than', 'value' => 'less_than' ],
                        ],
                        'dependency'  => [
                            'field'  => 'enable_conditional',
                            'values' => [ '1' ]
                        ],
                    ],
                    [
                        'label'       => esc_html__( 'Value', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'text',
                        'name'        => 'conditional_value',
                        'class'       => 'medium',
                        'dependency'  => [
                            'field'  => 'enable_conditional',
                            'values' => [ '1' ]
                        ],
                    ],
                    
                    // Customer Notification Section
                    [
                        'label'   => '<hr><h3>' . esc_html__( 'Customer Notification', 'smsenlinea-whatsapp-woocommerce' ) . '</h3>',
                        'type'    => 'html',
                    ],
                    [
                        'label'   => esc_html__( 'Send to Customer', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_customer',
                        'choices' => [
                            [
                                'label' => esc_html__( 'Send notification to person who submitted the form', 'smsenlinea-whatsapp-woocommerce' ),
                                'name'  => 'enable_customer'
                            ]
                        ],
                    ],
                    [
                        'label'       => esc_html__( 'Customer Phone Field', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'select',
                        'name'        => 'customer_phone_field',
                        'choices'     => $phone_fields,
                        'dependency'  => [
                            'field'  => 'enable_customer',
                            'values' => [ '1' ]
                        ],
                        'tooltip'     => '<h6>' . esc_html__( 'Phone Field', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Select the field that contains the customer\'s phone number.', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                    [
                        'label'       => esc_html__( 'Customer Message', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'textarea',
                        'name'        => 'customer_message',
                        'class'       => 'large merge-tag-support mt-position-right',
                        'dependency'  => [
                            'field'  => 'enable_customer',
                            'values' => [ '1' ]
                        ],
                        'default_value' => 'Hi {Name:1}! Thank you for your submission. We\'ll get back to you soon.',
                        'tooltip'     => '<h6>' . esc_html__( 'Message Template', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Use merge tags to personalize the message. Click the merge tag icon to see available options.', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                    [
                        'label'       => esc_html__( 'Customer Channel', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'radio',
                        'name'        => 'customer_channel',
                        'choices'     => [
                            [ 'label' => 'WhatsApp', 'value' => 'whatsapp' ],
                            [ 'label' => 'SMS', 'value' => 'sms' ]
                        ],
                        'default_value' => 'whatsapp',
                        'horizontal'  => true,
                        'dependency'  => [
                            'field'  => 'enable_customer',
                            'values' => [ '1' ]
                        ],
                    ],
                    
                    // Multi-page form support
                    [
                        'label'       => esc_html__( 'Partial Submission Message (Multi-page Forms)', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'textarea',
                        'name'        => 'partial_message',
                        'class'       => 'medium merge-tag-support mt-position-right',
                        'dependency'  => [
                            'field'  => 'enable_customer',
                            'values' => [ '1' ]
                        ],
                        'tooltip'     => '<h6>' . esc_html__( 'Partial Submissions', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Message sent when user completes part of a multi-page form. Leave empty to disable.', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                    
                    // Admin Notification Section
                    [
                        'label'   => '<hr><h3>' . esc_html__( 'Admin Notification', 'smsenlinea-whatsapp-woocommerce' ) . '</h3>',
                        'type'    => 'html',
                    ],
                    [
                        'label'   => esc_html__( 'Send to Admin', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_admin',
                        'choices' => [
                            [
                                'label' => esc_html__( 'Send notification to administrators', 'smsenlinea-whatsapp-woocommerce' ),
                                'name'  => 'enable_admin'
                            ]
                        ],
                    ],
                    [
                        'label'       => esc_html__( 'Admin Phone Numbers', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'text',
                        'name'        => 'admin_phones',
                        'class'       => 'large',
                        'dependency'  => [
                            'field'  => 'enable_admin',
                            'values' => [ '1' ]
                        ],
                        'default_value' => 'New form submission for "' . rgar($form, 'title', 'Untitled Form') . '"' . "\n\n{all_fields}",
                        'tooltip'     => '<h6>' . esc_html__( 'Admin Message', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Message sent to administrators. Use {all_fields} to include all form data.', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                    [
                        'label'       => esc_html__( 'Admin Channel', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'radio',
                        'name'        => 'admin_channel',
                        'choices'     => [
                            [ 'label' => 'WhatsApp', 'value' => 'whatsapp' ],
                            [ 'label' => 'SMS', 'value' => 'sms' ]
                        ],
                        'default_value' => 'whatsapp',
                        'horizontal'  => true,
                        'dependency'  => [
                            'field'  => 'enable_admin',
                            'values' => [ '1' ]
                        ],
                    ],
                    
                    // Advanced Settings Section
                    [
                        'label'   => '<hr><h3>' . esc_html__( 'Advanced Settings', 'smsenlinea-whatsapp-woocommerce' ) . '</h3>',
                        'type'    => 'html',
                    ],
                    [
                        'label'   => esc_html__( 'Delay Sending', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'text',
                        'name'    => 'send_delay',
                        'class'   => 'small',
                        'tooltip' => '<h6>' . esc_html__( 'Send Delay', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Delay in seconds before sending notification. Useful for payment processing. Default: 0 (immediate)', 'smsenlinea-whatsapp-woocommerce' ),
                        'default_value' => '0',
                    ],
                    [
                        'label'   => esc_html__( 'Skip Spam Entries', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox',
                        'name'    => 'skip_spam',
                        'choices' => [
                            [
                                'label' => esc_html__( 'Don\'t send notifications for entries marked as spam', 'smsenlinea-whatsapp-woocommerce' ),
                                'name'  => 'skip_spam'
                            ]
                        ],
                        'default_value' => '1',
                    ],
                    [
                        'label'   => esc_html__( 'Enable Debug Logging', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_debug',
                        'choices' => [
                            [
                                'label' => esc_html__( 'Log detailed information for troubleshooting', 'smsenlinea-whatsapp-woocommerce' ),
                                'name'  => 'enable_debug'
                            ]
                        ],
                    ],
                    
                    // Test Section
                    [
                        'label'   => '<hr><h3>' . esc_html__( 'Test Notifications', 'smsenlinea-whatsapp-woocommerce' ) . '</h3>',
                        'type'    => 'html',
                    ],
                    [
                        'label'   => esc_html__( 'Test Phone Number', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'text',
                        'name'    => 'test_phone',
                        'class'   => 'medium',
                        'tooltip' => '<h6>' . esc_html__( 'Test Number', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Enter a phone number to test notifications (include country code)', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                    [
                        'label'   => '',
                        'type'    => 'html',
                        'html'    => '<button type="button" id="smsenlinea-gf-test-customer" class="button button-secondary">Test Customer Message</button> <button type="button" id="smsenlinea-gf-test-admin" class="button button-secondary">Test Admin Message</button><div id="smsenlinea-gf-test-results"></div>',
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Process form submission
     */
    public function process_submission( $entry, $form ) {
        $settings = $this->get_form_settings( $form );
        
        if ( empty( $settings['enable_notifications'] ) ) {
            return;
        }
        
        // Check conditional logic
        if ( ! empty( $settings['enable_conditional'] ) && ! $this->check_conditional_logic( $entry, $settings ) ) {
            $this->log_debug( $form['id'], $entry['id'], 'Conditional logic not met - skipping notification' );
            return;
        }
        
        // Check spam
        if ( ! empty( $settings['skip_spam'] ) && ( ! empty( $entry['is_spam'] ) || $entry['status'] === 'spam' ) ) {
            $this->log_debug( $form['id'], $entry['id'], 'Entry marked as spam - skipping notification' );
            return;
        }
        
        // Apply delay if configured
        $delay = intval( $settings['send_delay'] ?? 0 );
        if ( $delay > 0 ) {
            wp_schedule_single_event( time() + $delay, 'smsenlinea_delayed_notification', [ $entry['id'], $form['id'] ] );
            return;
        }
        
        $this->send_notifications( $entry, $form, $settings );
    }
    
    /**
     * Send notifications
     */
    private function send_notifications( $entry, $form, $settings ) {
        try {
            // Send customer notification
            if ( $this->should_send_customer_notification( $settings ) ) {
                $this->send_customer_notification( $entry, $form, $settings );
            }
            
            // Send admin notification
            if ( $this->should_send_admin_notification( $settings ) ) {
                $this->send_admin_notification( $entry, $form, $settings );
            }
            
            $this->log_debug( $form['id'], $entry['id'], 'Notifications processed successfully' );
            
        } catch ( Exception $e ) {
            $this->log_debug( $form['id'], $entry['id'], 'Error processing notifications: ' . $e->getMessage() );
            
            // Add entry note
            if ( method_exists( 'GFFormsModel', 'add_note' ) ) {
                \GFFormsModel::add_note( $entry['id'], 0, 'SMSenlinea Error', $e->getMessage() );
            }
        }
    }
    
    /**
     * Send customer notification
     */
    private function send_customer_notification( $entry, $form, $settings ) {
        $phone_field = $settings['customer_phone_field'] ?? '';
        $phone = rgar( $entry, $phone_field );
        
        if ( empty( $phone ) ) {
            throw new Exception( 'Customer phone number is empty' );
        }
        
        $message = \GFCommon::replace_variables( 
            $settings['customer_message'] ?? '', 
            $form, 
            $entry, 
            false, 
            true, 
            false, 
            'text' 
        );
        
        $channel = $settings['customer_channel'] ?? 'whatsapp';
        $formatted_phone = $this->format_phone_number( $phone );
        
        if ( ! $formatted_phone ) {
            throw new Exception( 'Invalid phone number format: ' . $phone );
        }
        
        $result = $this->api_handler->send_direct_message( $formatted_phone, $message, $channel );
        
        if ( ! $result['success'] ) {
            throw new Exception( 'Failed to send customer notification: ' . $result['error'] );
        }
        
        // Add success note
        if ( method_exists( 'GFFormsModel', 'add_note' ) ) {
            \GFFormsModel::add_note( 
                $entry['id'], 
                0, 
                'SMSenlinea Customer Notification', 
                sprintf( '%s notification sent to %s', ucfirst( $channel ), $formatted_phone )
            );
        }
        
        $this->log_debug( $form['id'], $entry['id'], "Customer notification sent via $channel to $formatted_phone" );
    }
    
    /**
     * Send admin notification
     */
    private function send_admin_notification( $entry, $form, $settings ) {
        $admin_phones = $this->get_admin_phones( $settings );
        
        if ( empty( $admin_phones ) ) {
            throw new Exception( 'No admin phone numbers configured' );
        }
        
        $message = \GFCommon::replace_variables( 
            $settings['admin_message'] ?? '', 
            $form, 
            $entry, 
            false, 
            true, 
            false, 
            'text' 
        );
        
        $channel = $settings['admin_channel'] ?? 'whatsapp';
        $success_count = 0;
        $errors = [];
        
        foreach ( $admin_phones as $phone ) {
            $formatted_phone = $this->format_phone_number( $phone );
            
            if ( ! $formatted_phone ) {
                $errors[] = "Invalid admin phone: $phone";
                continue;
            }
            
            $result = $this->api_handler->send_direct_message( $formatted_phone, $message, $channel );
            
            if ( $result['success'] ) {
                $success_count++;
                $this->log_debug( $form['id'], $entry['id'], "Admin notification sent via $channel to $formatted_phone" );
            } else {
                $errors[] = "Failed to notify $formatted_phone: " . $result['error'];
            }
        }
        
        // Add entry note
        if ( method_exists( 'GFFormsModel', 'add_note' ) ) {
            $note = sprintf( 
                '%s admin notifications sent: %d/%d', 
                ucfirst( $channel ),
                $success_count, 
                count( $admin_phones ) 
            );
            
            if ( ! empty( $errors ) ) {
                $note .= "\nErrors: " . implode( '; ', $errors );
            }
            
            \GFFormsModel::add_note( $entry['id'], 0, 'SMSenlinea Admin Notification', $note );
        }
        
        if ( $success_count === 0 ) {
            throw new Exception( 'All admin notifications failed: ' . implode( '; ', $errors ) );
        }
    }
    
    /**
     * Check if should send customer notification
     */
    private function should_send_customer_notification( $settings ) {
        return ! empty( $settings['enable_customer'] ) && 
               ! empty( $settings['customer_phone_field'] ) && 
               ! empty( $settings['customer_message'] );
    }
    
    /**
     * Check if should send admin notification
     */
    private function should_send_admin_notification( $settings ) {
        return ! empty( $settings['enable_admin'] ) && 
               ! empty( $settings['admin_message'] );
    }
    
    /**
     * Check conditional logic
     */
    private function check_conditional_logic( $entry, $settings ) {
        if ( empty( $settings['conditional_field'] ) || empty( $settings['conditional_operator'] ) ) {
            return true;
        }
        
        $field_value = rgar( $entry, $settings['conditional_field'] );
        $compare_value = $settings['conditional_value'] ?? '';
        $operator = $settings['conditional_operator'];
        
        switch ( $operator ) {
            case 'is':
                return $field_value === $compare_value;
            case 'isnot':
                return $field_value !== $compare_value;
            case 'contains':
                return strpos( $field_value, $compare_value ) !== false;
            case 'not_contains':
                return strpos( $field_value, $compare_value ) === false;
            case 'greater_than':
                return floatval( $field_value ) > floatval( $compare_value );
            case 'less_than':
                return floatval( $field_value ) < floatval( $compare_value );
            default:
                return true;
        }
    }
    
    /**
     * Get admin phone numbers
     */
    private function get_admin_phones( $settings ) {
        $phones_str = $settings['admin_phones'] ?? '';
        
        // Fall back to global settings
        if ( empty( $phones_str ) ) {
            $global_settings = get_option( 'wc_smsenlinea_settings', [] );
            $phones_str = $global_settings['admin_phones'] ?? '';
        }
        
        if ( empty( $phones_str ) ) {
            return [];
        }
        
        return array_filter( array_map( 'trim', explode( ',', $phones_str ) ) );
    }
    
    /**
     * Format phone number
     */
    private function format_phone_number( $phone ) {
        $global_settings = get_option( 'wc_smsenlinea_settings', [] );
        $default_country_code = $global_settings['default_country_code'] ?? '57';
        
        return $this->api_handler->format_phone_number( $phone, '', $default_country_code );
    }
    
    /**
     * Get phone fields from form
     */
    private function get_phone_fields( $form ) {
        $fields = [
            [
                'label' => esc_html__( '-- Select Phone Field --', 'smsenlinea-whatsapp-woocommerce' ),
                'value' => ''
            ]
        ];
        
        foreach ( $form['fields'] as $field ) {
            if ( in_array( $field->get_input_type(), [ 'phone', 'text', 'number' ] ) ) {
                $fields[] = [
                    'label' => sprintf( '%s (%s)', $field->label, $field->get_input_type() ),
                    'value' => $field->id
                ];
            }
        }
        
        return $fields;
    }
    
    /**
     * Get text fields for conditional logic
     */
    private function get_text_fields( $form ) {
        $fields = [
            [
                'label' => esc_html__( '-- Select Field --', 'smsenlinea-whatsapp-woocommerce' ),
                'value' => ''
            ]
        ];
        
        foreach ( $form['fields'] as $field ) {
            if ( in_array( $field->get_input_type(), [ 'text', 'textarea', 'select', 'radio', 'checkbox', 'number', 'email' ] ) ) {
                $fields[] = [
                    'label' => sprintf( '%s (%s)', $field->label, $field->get_input_type() ),
                    'value' => $field->id
                ];
            }
        }
        
        return $fields;
    }
    
    /**
     * Enqueue form scripts
     */
    public function enqueue_form_scripts( $form, $is_ajax ) {
        // Only enqueue on form settings pages
        if ( ! $this->is_form_settings_page() ) {
            return;
        }
        
        wp_enqueue_script( 
            'smsenlinea-gf-admin', 
            SMSENLINEA_WC_PLUGIN_URL . 'assets/js/gravity-forms-admin.js', 
            [ 'jquery' ], 
            '1.4.0', 
            true 
        );
        
        wp_localize_script( 'smsenlinea-gf-admin', 'smsenlinea_gf_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'smsenlinea_gf_test_nonce' ),
            'form_id'  => rgar( $_GET, 'id' ),
        ] );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'gf_' ) === false ) {
            return;
        }
        
        wp_enqueue_style( 
            'smsenlinea-gf-admin-style', 
            SMSENLINEA_WC_PLUGIN_URL . 'assets/css/gravity-forms-admin.css', 
            [], 
            '1.4.0' 
        );
    }
    
    /**
     * Handle test message AJAX
     */
    public function handle_test_message() {
        check_ajax_referer( 'smsenlinea_gf_test_nonce', 'nonce' );
        
        $form_id = intval( $_POST['form_id'] ?? 0 );
        $test_phone = sanitize_text_field( $_POST['test_phone'] ?? '' );
        $message_type = sanitize_key( $_POST['message_type'] ?? '' );
        
        if ( empty( $form_id ) || empty( $test_phone ) || ! in_array( $message_type, [ 'customer', 'admin' ] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid parameters' ] );
        }
        
        $form = \GFAPI::get_form( $form_id );
        if ( ! $form ) {
            wp_send_json_error( [ 'message' => 'Form not found' ] );
        }
        
        $settings = $this->get_form_settings( $form );
        
        // Create dummy entry for testing
        $dummy_entry = $this->create_dummy_entry( $form );
        
        $message_key = $message_type . '_message';
        $channel_key = $message_type . '_channel';
        
        $message = \GFCommon::replace_variables( 
            $settings[ $message_key ] ?? 'Test message', 
            $form, 
            $dummy_entry, 
            false, 
            true, 
            false, 
            'text' 
        );
        
        $channel = $settings[ $channel_key ] ?? 'whatsapp';
        $formatted_phone = $this->format_phone_number( $test_phone );
        
        if ( ! $formatted_phone ) {
            wp_send_json_error( [ 'message' => 'Invalid phone number format' ] );
        }
        
        $result = $this->api_handler->send_direct_message( $formatted_phone, $message, $channel );
        
        if ( $result['success'] ) {
            wp_send_json_success( [ 
                'message' => sprintf( 
                    'Test %s message sent successfully via %s to %s', 
                    $message_type, 
                    strtoupper( $channel ), 
                    $formatted_phone 
                ) 
            ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to send test message: ' . $result['error'] ] );
        }
    }
    
    /**
     * Create dummy entry for testing
     */
    private function create_dummy_entry( $form ) {
        $entry = [
            'id' => 'TEST',
            'form_id' => $form['id'],
            'date_created' => current_time( 'mysql' ),
            'is_spam' => false,
            'status' => 'active',
        ];
        
        // Add dummy values for all fields
        foreach ( $form['fields'] as $field ) {
            switch ( $field->get_input_type() ) {
                case 'text':
                case 'email':
                    $entry[ $field->id ] = 'Sample Text';
                    break;
                case 'phone':
                    $entry[ $field->id ] = '+1234567890';
                    break;
                case 'textarea':
                    $entry[ $field->id ] = 'Sample textarea content';
                    break;
                case 'select':
                case 'radio':
                    if ( ! empty( $field->choices ) ) {
                        $entry[ $field->id ] = $field->choices[0]['value'];
                    }
                    break;
                case 'checkbox':
                    if ( ! empty( $field->choices ) ) {
                        $entry[ $field->id . '.1' ] = $field->choices[0]['value'];
                    }
                    break;
                case 'number':
                    $entry[ $field->id ] = '123';
                    break;
                default:
                    $entry[ $field->id ] = 'Sample Value';
            }
        }
        
        return $entry;
    }
    
    /**
     * Check if current page is form settings
     */
    private function is_form_settings_page() {
        return rgar( $_GET, 'page' ) === 'gf_edit_forms' && 
               rgar( $_GET, 'view' ) === 'settings' && 
               rgar( $_GET, 'subview' ) === $this->_slug;
    }
    
    /**
     * Log debug information
     */
    private function log_debug( $form_id, $entry_id, $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 
                'SMSenlinea GF Debug - Form: %s, Entry: %s, Message: %s', 
                $form_id, 
                $entry_id, 
                $message 
            ) );
        }
    }
    
    /**
     * Plugin page
     */
    public function plugin_page() {
        echo '<div class="wrap">';
        echo '<h1>SMSenlinea Gravity Forms Integration</h1>';
        echo '<p>Configure SMS and WhatsApp notifications for individual forms in their respective form settings.</p>';
        echo '<p><a href="' . admin_url( 'admin.php?page=gf_edit_forms' ) . '" class="button button-primary">Manage Forms</a></p>';
        echo '</div>';
    }
}

// Initialize the addon
if ( class_exists( 'GFForms' ) ) {
    \GFForms::include_addon_framework();
    
    add_action( 'gform_loaded', function() {
        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }
        \SMSenlinea_WhatsApp\Gravity_Forms_Admin_Addon::get_instance();
    }, 5 );
}
                        'tooltip'     => '<h6>' . esc_html__( 'Admin Numbers', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Comma-separated phone numbers with country codes. Leave blank to use global settings.', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                    [
                        'label'       => esc_html__( 'Admin Message', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'        => 'textarea',
                        'name'        => 'admin_message',
                        'class'       => 'large merge-tag-support mt-position-right',
                        'dependency'  => [
                            'field'  => 'enable_admin',
                            'values' => [ '1' ]
                        ],
