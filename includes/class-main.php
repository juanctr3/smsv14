<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Main {
    private static $_instance = null;
    
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_classes();
        $this->setup_hooks();
    }
    
    /**
     * Define plugin constants
     */
    private function define_constants() {
        if ( ! defined( 'SMSENLINEA_WC_VERSION' ) ) {
            define( 'SMSENLINEA_WC_VERSION', '1.4.0' );
        }
        
        if ( ! defined( 'SMSENLINEA_WC_PLUGIN_BASENAME' ) ) {
            define( 'SMSENLINEA_WC_PLUGIN_BASENAME', plugin_basename( SMSENLINEA_WC_PLUGIN_PATH . 'smsenlinea-whatsapp-woocommerce.php' ) );
        }
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-api-handler.php';
        require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-admin-settings.php';
        require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-log-list-table.php';
        
        // WooCommerce integration
        if ( $this->is_woocommerce_active() ) {
            require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-woocommerce-hooks.php';
        }
        
        // Gravity Forms integration (optimized version)
        if ( $this->is_gravity_forms_active() ) {
            require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-optimized-gravity-forms-integration.php';
            require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-gravity-forms-admin-addon.php';
        }
    }
    
    /**
     * Initialize plugin classes
     */
    private function init_classes() {
        // Always initialize core classes
        $this->api_handler = new API_Handler();
        new Admin_Settings();
        
        // Initialize WooCommerce hooks if WooCommerce is active
        if ( $this->is_woocommerce_active() ) {
            new WooCommerce_Hooks( $this->api_handler );
        }
        
        // Initialize Gravity Forms integration if Gravity Forms is active
        if ( $this->is_gravity_forms_active() ) {
            $this->init_gravity_forms_integration();
        }
    }
    
    /**
     * Setup plugin hooks
     */
    private function setup_hooks() {
        // Plugin lifecycle hooks
        register_activation_hook( SMSENLINEA_WC_PLUGIN_PATH . 'smsenlinea-whatsapp-woocommerce.php', [ $this, 'on_activation' ] );
        register_deactivation_hook( SMSENLINEA_WC_PLUGIN_PATH . 'smsenlinea-whatsapp-woocommerce.php', [ $this, 'on_deactivation' ] );
        
        // Admin notices
        add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );
        
        // Plugin links
        add_filter( 'plugin_action_links_' . SMSENLINEA_WC_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
        
        // Scheduled events for delayed notifications
        add_action( 'smsenlinea_delayed_notification', [ $this, 'handle_delayed_notification' ], 10, 2 );
        
        // AJAX hooks for testing
        add_action( 'wp_ajax_smsenlinea_test_api_connection', [ $this, 'test_api_connection' ] );
        
        // Internationalization
        add_action( 'init', [ $this, 'load_textdomain' ] );
    }
    
    /**
     * Initialize Gravity Forms integration
     */
    private function init_gravity_forms_integration() {
        // Initialize the optimized integration
        add_action( 'init', function() {
            Optimized_Gravity_Forms_Integration::get_instance();
        }, 15 );
        
        // Initialize the admin addon
        add_action( 'gform_loaded', function() {
            if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
                return;
            }
            
            \GFForms::include_addon_framework();
            Gravity_Forms_Admin_Addon::get_instance();
        }, 5 );
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) ) ) ||
               ( is_multisite() && array_key_exists( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins', [] ) ) );
    }
    
    /**
     * Check if Gravity Forms is active
     */
    private function is_gravity_forms_active() {
        return class_exists( 'GFForms' ) && class_exists( 'GFCommon' );
    }
    
    /**
     * Plugin activation hook
     */
    public function on_activation() {
        // Create database tables
        $this->create_database_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cleanup cron
        if ( ! wp_next_scheduled( 'smsenlinea_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'weekly', 'smsenlinea_cleanup_logs' );
        }
        
        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation hook
     */
    public function on_deactivation() {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'smsenlinea_cleanup_logs' );
        wp_clear_scheduled_hook( 'smsenlinea_delayed_notification' );
        
        // Clean up transients
        $this->cleanup_transients();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smsenlinea_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            recipient varchar(25) NOT NULL,
            channel varchar(10) NOT NULL,
            status varchar(10) NOT NULL,
            message text NOT NULL,
            response text NOT NULL,
            form_id varchar(50) DEFAULT NULL,
            entry_id varchar(50) DEFAULT NULL,
            order_id varchar(50) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY status (status),
            KEY channel (channel)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        // Update version
        update_option( 'smsenlinea_wc_version', SMSENLINEA_WC_VERSION );
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = [
            'default_country_code' => '57',
            'sms_mode' => 'devices',
            'sms_sim_slot' => 1,
            'enable_new_order' => 0,
            'channel_new_order' => 'whatsapp',
            'new_order_msg' => 'Hi {customer_fullname}! Your order #{order_id} has been received. Total: {order_total}. We\'ll notify you when it\'s ready! 🛒✅',
            'enable_admin_new_order' => 0,
            'channel_admin_new_order' => 'whatsapp',
            'admin_new_order_msg' => '🔔 New order #{order_id} from {customer_fullname} - {order_total}',
        ];
        
        $existing_options = get_option( 'wc_smsenlinea_settings', [] );
        $updated_options = array_merge( $defaults, $existing_options );
        
        update_option( 'wc_smsenlinea_settings', $updated_options );
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check if WooCommerce is missing
        if ( ! $this->is_woocommerce_active() ) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>SMSenlinea WhatsApp Notifications:</strong> ';
            echo __( 'WooCommerce is required for full functionality. Some features may be limited.', 'smsenlinea-whatsapp-woocommerce' );
            echo '</p></div>';
        }
        
        // Check API configuration
        $settings = get_option( 'wc_smsenlinea_settings', [] );
        if ( empty( $settings['api_secret'] ) ) {
            $settings_url = admin_url( 'admin.php?page=wc-smsenlinea-settings&tab=api_testing' );
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>SMSenlinea WhatsApp Notifications:</strong> ';
            echo sprintf( 
                __( 'Please configure your API credentials in the <a href="%s">plugin settings</a>.', 'smsenlinea-whatsapp-woocommerce' ),
                $settings_url
            );
            echo '</p></div>';
        }
        
        // Show Gravity Forms integration notice if available
        if ( $this->is_gravity_forms_active() ) {
            $dismissed = get_option( 'smsenlinea_gf_notice_dismissed', false );
            if ( ! $dismissed && ! isset( $_GET['smsenlinea_dismiss_gf_notice'] ) ) {
                echo '<div class="notice notice-info is-dismissible"><p>';
                echo '<strong>SMSenlinea:</strong> ';
                echo __( 'Gravity Forms integration is now available! You can configure SMS/WhatsApp notifications for your forms in each form\'s settings.', 'smsenlinea-whatsapp-woocommerce' );
                echo ' <a href="' . add_query_arg( 'smsenlinea_dismiss_gf_notice', '1' ) . '">' . __( 'Dismiss', 'smsenlinea-whatsapp-woocommerce' ) . '</a>';
                echo '</p></div>';
            }
        }
        
        // Handle notice dismissal
        if ( isset( $_GET['smsenlinea_dismiss_gf_notice'] ) ) {
            update_option( 'smsenlinea_gf_notice_dismissed', true );
            wp_safe_redirect( remove_query_arg( 'smsenlinea_dismiss_gf_notice' ) );
            exit;
        }
    }
    
    /**
     * Add plugin action links
     */
    public function plugin_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-smsenlinea-settings' ) . '">' . __( 'Settings', 'smsenlinea-whatsapp-woocommerce' ) . '</a>';
        array_unshift( $links, $settings_link );
        
        return $links;
    }
    
    /**
     * Add plugin row meta
     */
    public function plugin_row_meta( $meta, $file ) {
        if ( SMSENLINEA_WC_PLUGIN_BASENAME === $file ) {
            $meta[] = '<a href="https://www.smsenlinea.com/" target="_blank">' . __( 'Visit SMSenlinea.com', 'smsenlinea-whatsapp-woocommerce' ) . '</a>';
            $meta[] = '<a href="https://www.smsenlinea.com/support" target="_blank">' . __( 'Support', 'smsenlinea-whatsapp-woocommerce' ) . '</a>';
            $meta[] = '<a href="https://www.smsenlinea.com/docs" target="_blank">' . __( 'Documentation', 'smsenlinea-whatsapp-woocommerce' ) . '</a>';
        }
        
        return $meta;
    }
    
    /**
     * Handle delayed notifications
     */
    public function handle_delayed_notification( $entry_id, $form_id ) {
        if ( ! $this->is_gravity_forms_active() ) {
            return;
        }
        
        $entry = \GFAPI::get_entry( $entry_id );
        $form = \GFAPI::get_form( $form_id );
        
        if ( is_wp_error( $entry ) || is_wp_error( $form ) ) {
            return;
        }
        
        $integration = Optimized_Gravity_Forms_Integration::get_instance();
        if ( $integration ) {
            $integration->process_form_submission( $entry, $form );
        }
    }
    
    /**
     * Test API connection via AJAX
     */
    public function test_api_connection() {
        check_ajax_referer( 'smsenlinea_test_api', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
        }
        
        $settings = get_option( 'wc_smsenlinea_settings', [] );
        
        if ( empty( $settings['api_secret'] ) ) {
            wp_send_json_error( [ 'message' => 'API Secret not configured' ] );
        }
        
        // Test with a simple API call (you can customize this based on SMSenlinea's API)
        $response = wp_remote_post( 'https://whatsapp.smsenlinea.com/api/test', [
            'body' => [ 'secret' => $settings['api_secret'] ],
            'timeout' => 15
        ] );
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Connection failed: ' . $response->get_error_message() ] );
        }
        
        $http_code = wp_remote_retrieve_response_code( $response );
        
        if ( $http_code === 200 ) {
            wp_send_json_success( [ 'message' => 'API connection successful!' ] );
        } else {
            wp_send_json_error( [ 'message' => 'API connection failed. HTTP Code: ' . $http_code ] );
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 
            'smsenlinea-whatsapp-woocommerce', 
            false, 
            dirname( SMSENLINEA_WC_PLUGIN_BASENAME ) . '/languages/' 
        );
    }
    
    /**
     * Cleanup old transients
     */
    private function cleanup_transients() {
        global $wpdb;
        
        // Clean up plugin-specific transients
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_smsenlinea_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_smsenlinea_%'" );
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return SMSENLINEA_WC_VERSION;
    }
    
    /**
     * Get API handler instance
     */
    public function get_api_handler() {
        return $this->api_handler;
    }
    
    /**
     * Check if plugin is properly configured
     */
    public function is_configured() {
        $settings = get_option( 'wc_smsenlinea_settings', [] );
        return ! empty( $settings['api_secret'] );
    }
    
    /**
     * Get plugin settings
     */
    public function get_settings() {
        return get_option( 'wc_smsenlinea_settings', [] );
    }
    
    /**
     * Update plugin settings
     */
    public function update_settings( $new_settings ) {
        $current_settings = $this->get_settings();
        $updated_settings = array_merge( $current_settings, $new_settings );
        return update_option( 'wc_smsenlinea_settings', $updated_settings );
    }
    
    /**
     * Log plugin activity
     */
    public function log( $message, $level = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $formatted_message = sprintf( 
                '[SMSenlinea %s] %s: %s', 
                SMSENLINEA_WC_VERSION,
                strtoupper( $level ), 
                $message 
            );
            error_log( $formatted_message );
        }
    }
    
    /**
     * Get integration status
     */
    public function get_integration_status() {
        return [
            'woocommerce' => $this->is_woocommerce_active(),
            'gravity_forms' => $this->is_gravity_forms_active(),
            'configured' => $this->is_configured(),
            'version' => $this->get_version(),
        ];
    }
}

// Scheduled cleanup for old logs
add_action( 'smsenlinea_cleanup_logs', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'smsenlinea_logs';
    
    // Keep logs for 90 days
    $cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
    $wpdb->query( $wpdb->prepare( 
        "DELETE FROM $table_name WHERE timestamp < %s", 
        $cutoff_date 
    ) );
} );
