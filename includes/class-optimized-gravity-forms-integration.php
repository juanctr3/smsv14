<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Optimized Gravity Forms Integration for SMSenlinea WhatsApp Plugin
 * 
 * This class provides enhanced integration with Gravity Forms including:
 * - Better field detection (phone, text, email)
 * - Conditional logic support
 * - Multiple message templates
 * - Enhanced error handling
 * - Logging capabilities
 * - Merge tags support
 */
class Optimized_Gravity_Forms_Integration {
    
    private static $_instance = null;
    private $api_handler;
    private $settings;
    
    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    private function __construct() {
        // Only initialize if Gravity Forms is active
        if ( ! class_exists( 'GFCommon' ) ) {
            return;
        }
        
        $this->api_handler = new API_Handler();
        $this->settings = get_option( 'wc_smsenlinea_settings', [] );
        
        // Initialize hooks
        $this->init_hooks();
        
        // Add admin hooks for form settings
        add_action( 'gform_form_settings', [ $this, 'add_form_settings' ], 10, 2 );
        add_filter( 'gform_form_settings_fields', [ $this, 'add_form_settings_fields' ], 10, 2 );
        add_action( 'gform_pre_form_settings_save', [ $this, 'save_form_settings' ] );
    }
    
    /**
     * Initialize main hooks
     */
    private function init_hooks() {
        // Main submission hook with priority to ensure it runs after other processes
        add_action( 'gform_after_submission', [ $this, 'process_form_submission' ], 15, 2 );
        
        // Optional: Hook for partial submissions (multi-page forms)
        add_action( 'gform_partial_entry_created', [ $this, 'handle_partial_submission' ], 10, 2 );
    }
    
    /**
     * Process form submission and send notifications
     */
    public function process_form_submission( $entry, $form ) {
        try {
            $form_settings = $this->get_form_notification_settings( $form['id'] );
            
            if ( empty( $form_settings ) || ! $this->should_process_form( $form, $entry, $form_settings ) ) {
                return;
            }
            
            // Process customer notification
            if ( $this->is_customer_notification_enabled( $form_settings ) ) {
                $this->send_customer_notification( $entry, $form, $form_settings );
            }
            
            // Process admin notification
            if ( $this->is_admin_notification_enabled( $form_settings ) ) {
                $this->send_admin_notification( $entry, $form, $form_settings );
            }
            
            // Log successful processing
            $this->log_form_processing( $form['id'], $entry['id'], 'success', 'Form processed successfully' );
            
        } catch ( Exception $e ) {
            // Log error
            $this->log_form_processing( $form['id'], $entry['id'], 'error', $e->getMessage() );
            
            // Optional: Add entry note for debugging
            if ( class_exists( 'GFFormsModel' ) ) {
                \GFFormsModel::add_note( $entry['id'], 0, 'SMSenlinea Error', $e->getMessage() );
            }
        }
    }
    
    /**
     * Handle partial submissions for multi-page forms
     */
    public function handle_partial_submission( $entry, $form ) {
        $form_settings = $this->get_form_notification_settings( $form['id'] );
        
        // Only send partial notifications if enabled
        if ( ! empty( $form_settings['enable_partial_notifications'] ) ) {
            // Send a "partial submission received" message
            $this->send_partial_notification( $entry, $form, $form_settings );
        }
    }
    
    /**
     * Check if form should be processed
     */
    private function should_process_form( $form, $entry, $form_settings ) {
        // Check if notifications are globally disabled
        if ( empty( $form_settings['enable_notifications'] ) ) {
            return false;
        }
        
        // Check conditional logic if configured
        if ( ! empty( $form_settings['conditional_logic'] ) ) {
            return $this->evaluate_conditional_logic( $entry, $form_settings['conditional_logic'] );
        }
        
        // Check for spam entries
        if ( ! empty( $entry['is_spam'] ) || $entry['status'] === 'spam' ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Send customer notification
     */
    private function send_customer_notification( $entry, $form, $form_settings ) {
        $phone_number = $this->get_customer_phone( $entry, $form_settings );
        
        if ( empty( $phone_number ) ) {
            throw new Exception( 'Customer phone number not found or empty' );
        }
        
        $message = $this->build_customer_message( $entry, $form, $form_settings );
        $channel = $form_settings['customer_channel'] ?? 'whatsapp';
        
        // Format phone number
        $formatted_phone = $this->format_phone_number( $phone_number );
        
        if ( ! $formatted_phone ) {
            throw new Exception( 'Invalid phone number format: ' . $phone_number );
        }
        
        // Send message
        $result = $this->api_handler->send_direct_message( $formatted_phone, $message, $channel );
        
        if ( ! $result['success'] ) {
            throw new Exception( 'Failed to send customer notification: ' . $result['error'] );
        }
        
        // Add success note to entry
        if ( class_exists( 'GFFormsModel' ) ) {
            \GFFormsModel::add_note( 
                $entry['id'], 
                0, 
                'SMSenlinea Notification', 
                sprintf( '%s notification sent to customer (%s)', ucfirst($channel), $formatted_phone )
            );
        }
    }
    
    /**
     * Send admin notification
     */
    private function send_admin_notification( $entry, $form, $form_settings ) {
        $admin_phones = $this->get_admin_phones( $form_settings );
        
        if ( empty( $admin_phones ) ) {
            throw new Exception( 'Admin phone numbers not configured' );
        }
        
        $message = $this->build_admin_message( $entry, $form, $form_settings );
        $channel = $form_settings['admin_channel'] ?? 'whatsapp';
        
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
            } else {
                $errors[] = "Failed to notify admin $formatted_phone: " . $result['error'];
            }
        }
        
        // Add note to entry
        if ( class_exists( 'GFFormsModel' ) ) {
            $note_message = sprintf( 
                '%s admin notifications sent successfully: %d/%d', 
                ucfirst($channel),
                $success_count, 
                count($admin_phones) 
            );
            
            if ( ! empty( $errors ) ) {
                $note_message .= "\nErrors: " . implode( ', ', $errors );
            }
            
            \GFFormsModel::add_note( $entry['id'], 0, 'SMSenlinea Admin Notification', $note_message );
        }
        
        if ( $success_count === 0 ) {
            throw new Exception( 'All admin notifications failed: ' . implode( ', ', $errors ) );
        }
    }
    
    /**
     * Get customer phone number from entry
     */
    private function get_customer_phone( $entry, $form_settings ) {
        $phone_field_id = $form_settings['customer_phone_field'] ?? '';
        
        if ( empty( $phone_field_id ) ) {
            return false;
        }
        
        return rgar( $entry, $phone_field_id );
    }
    
    /**
     * Get admin phone numbers
     */
    private function get_admin_phones( $form_settings ) {
        // Try form-specific admin phones first
        $admin_phones_str = $form_settings['admin_phones'] ?? '';
        
        // Fall back to global settings
        if ( empty( $admin_phones_str ) ) {
            $admin_phones_str = $this->settings['admin_phones'] ?? '';
        }
        
        if ( empty( $admin_phones_str ) ) {
            return [];
        }
        
        return array_filter( array_map( 'trim', explode( ',', $admin_phones_str ) ) );
    }
    
    /**
     * Build customer message with merge tags
     */
    private function build_customer_message( $entry, $form, $form_settings ) {
        $template = $form_settings['customer_message'] ?? '';
        
        if ( empty( $template ) ) {
            $template = 'Thank you for your submission!';
        }
        
        // Process Gravity Forms merge tags
        $message = \GFCommon::replace_variables( $template, $form, $entry, false, true, false, 'text' );
        
        // Process custom shortcodes if any
        $message = do_shortcode( $message );
        
        return $message;
    }
    
    /**
     * Build admin message with merge tags
     */
    private function build_admin_message( $entry, $form, $form_settings ) {
        $template = $form_settings['admin_message'] ?? '';
        
        if ( empty( $template ) ) {
            $template = 'New submission received for form "' . $form['title'] . '": {all_fields}';
        }
        
        // Process Gravity Forms merge tags
        $message = \GFCommon::replace_variables( $template, $form, $entry, false, true, false, 'text' );
        
        // Add form context information
        $message .= "\n\nForm: " . $form['title'];
        $message .= "\nSubmission ID: " . $entry['id'];
        $message .= "\nDate: " . $entry['date_created'];
        
        // Process custom shortcodes if any
        $message = do_shortcode( $message );
        
        return $message;
    }
    
    /**
     * Format phone number using the API handler
     */
    private function format_phone_number( $phone ) {
        $default_country_code = $this->settings['default_country_code'] ?? '57';
        return $this->api_handler->format_phone_number( $phone, '', $default_country_code );
    }
    
    /**
     * Check if customer notification is enabled
     */
    private function is_customer_notification_enabled( $form_settings ) {
        return ! empty( $form_settings['enable_customer'] ) && 
               ! empty( $form_settings['customer_phone_field'] ) && 
               ! empty( $form_settings['customer_message'] );
    }
    
    /**
     * Check if admin notification is enabled
     */
    private function is_admin_notification_enabled( $form_settings ) {
        return ! empty( $form_settings['enable_admin'] ) && 
               ! empty( $form_settings['admin_message'] );
    }
    
    /**
     * Get form notification settings
     */
    private function get_form_notification_settings( $form_id ) {
        return get_option( "gf_smsenlinea_form_{$form_id}_settings", [] );
    }
    
    /**
     * Evaluate conditional logic
     */
    private function evaluate_conditional_logic( $entry, $conditional_logic ) {
        // This is a simplified version - you can expand this based on your needs
        if ( empty( $conditional_logic['field_id'] ) || empty( $conditional_logic['operator'] ) ) {
            return true;
        }
        
        $field_value = rgar( $entry, $conditional_logic['field_id'] );
        $compare_value = $conditional_logic['value'] ?? '';
        $operator = $conditional_logic['operator'];
        
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
     * Send partial notification for multi-page forms
     */
    private function send_partial_notification( $entry, $form, $form_settings ) {
        if ( empty( $form_settings['partial_message'] ) ) {
            return;
        }
        
        $phone_number = $this->get_customer_phone( $entry, $form_settings );
        
        if ( empty( $phone_number ) ) {
            return;
        }
        
        $message = \GFCommon::replace_variables( 
            $form_settings['partial_message'], 
            $form, 
            $entry, 
            false, 
            true, 
            false, 
            'text' 
        );
        
        $formatted_phone = $this->format_phone_number( $phone_number );
        $channel = $form_settings['customer_channel'] ?? 'whatsapp';
        
        if ( $formatted_phone ) {
            $this->api_handler->send_direct_message( $formatted_phone, $message, $channel );
        }
    }
    
    /**
     * Log form processing
     */
    private function log_form_processing( $form_id, $entry_id, $status, $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 
                'SMSenlinea GF Integration - Form: %d, Entry: %d, Status: %s, Message: %s', 
                $form_id, 
                $entry_id, 
                $status, 
                $message 
            ) );
        }
    }
    
    /**
     * Add form settings section to Gravity Forms
     */
    public function add_form_settings( $form ) {
        // This method would be used to add settings UI to individual forms
        // Implementation depends on GF version and requirements
    }
    
    /**
     * Add form settings fields
     */
    public function add_form_settings_fields( $fields, $form ) {
        // Add custom fields to form settings
        // Implementation would go here
        return $fields;
    }
    
    /**
     * Save form settings
     */
    public function save_form_settings( $form ) {
        // Save custom form settings
        // Implementation would go here
    }
    
    /**
     * Get available phone fields from form
     */
    public function get_phone_fields( $form ) {
        $fields = [
            [
                'label' => esc_html__( '-- Select Field --', 'smsenlinea-whatsapp-woocommerce' ),
                'value' => ''
            ]
        ];
        
        foreach ( $form['fields'] as $field ) {
            // Include phone fields and text fields that might contain phone numbers
            if ( in_array( $field->get_input_type(), [ 'phone', 'text', 'number' ] ) ) {
                $fields[] = [
                    'label' => esc_html( $field->label . ' (' . $field->get_input_type() . ')' ),
                    'value' => $field->id
                ];
            }
        }
        
        return $fields;
    }
    
    /**
     * Get available text fields for conditional logic
     */
    public function get_text_fields( $form ) {
        $fields = [];
        
        foreach ( $form['fields'] as $field ) {
            if ( in_array( $field->get_input_type(), [ 'text', 'textarea', 'select', 'radio', 'checkbox' ] ) ) {
                $fields[] = [
                    'label' => esc_html( $field->label ),
                    'value' => $field->id
                ];
            }
        }
        
        return $fields;
    }
}

// Initialize the optimized integration
if ( class_exists( 'GFCommon' ) ) {
    add_action( 'init', function() {
        \SMSenlinea_WhatsApp\Optimized_Gravity_Forms_Integration::get_instance();
    }, 15 );
}
