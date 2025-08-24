<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Plugin Class
 * 
 * Handles the initialization and loading of all plugin components
 * 
 * @since 1.0.0
 */
final class Main {
    
    /**
     * Plugin instance
     * 
     * @var Main
     */
    private static $_instance = null;
    
    /**
     * API Handler instance
     * 
     * @var API_Handler
     */
    private $api_handler;
    
    /**
     * Get plugin instance
     * 
     * @return Main
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_classes();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes - Always load these
        $this->load_core_classes();
        
        // Conditional loading for integrations
        add_action( 'plugins_loaded', [ $this, 'load_integrations' ], 20 );
    }
    
    /**
     * Load core plugin classes
     */
    private function load_core_classes() {
        // API Handler - Core functionality
        if ( file_exists( SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-api-handler.php' ) ) {
            require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-api-handler.php';
        }
        
        // Admin Settings
        if ( file_exists( SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-admin-settings.php' ) ) {
            require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-admin-settings.php';
        }
        
        // WooCommerce Hooks
        if ( file_exists( SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-woocommerce-hooks.php' ) ) {
            require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-woocommerce-hooks.php';
        }
        
        // Log Table - Only load when needed
        if ( is_admin() && file_exists( SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-log-list-table.php' ) ) {
            require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-log-list-table.php';
        }
    }
    
    /**
     * Load integration classes conditionally
     */
    public function load_integrations() {
        // Gravity Forms Integration
        if ( class_exists( 'GFForms' ) || class_exists( 'GFCommon' ) ) {
            $this->load_gravity_forms_integration();
        }
        
        // Add more integrations here as needed
        // Example: Contact Form 7, WPForms, etc.
    }
    
    /**
     * Load Gravity Forms integration
     */
    private function load_gravity_forms_integration() {
        // Check if Gravity Forms is actually loaded
        if ( ! did_action( 'gform_loaded' ) ) {
            // Wait for Gravity Forms to load
            add_action( 'gform_loaded', [ $this, 'init_gravity_forms' ], 5 );
        } else {
            // Gravity Forms is already loaded
            $this->init_gravity_forms();
        }
    }
    
    /**
     * Initialize Gravity Forms addon
     */
    public function init_gravity_forms() {
        // Only load if GFForms class exists
        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }
        
        // Include the addon framework
        \GFForms::include_addon_framework();
        
        // Load our Gravity Forms addon class
        $gf_addon_file = SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-gravity-forms-addon.php';
        
        if ( file_exists( $gf_addon_file ) ) {
            require_once $gf_addon_file;
            
            // Register the addon
            if ( class_exists( '\SMSenlinea_WhatsApp\Gravity_Forms_Addon' ) ) {
                \GFForms::include_addon_framework();
                Gravity_Forms_Addon::get_instance();
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Plugin activation/deactivation hooks
        register_activation_hook( SMSENLINEA_WC_PLUGIN_PATH . 'smsenlinea-whatsapp-woocommerce.php', [ $this, 'activate' ] );
        register_deactivation_hook( SMSENLINEA_WC_PLUGIN_PATH . 'smsenlinea-whatsapp-woocommerce.php', [ $this, 'deactivate' ] );
        
        // Load plugin textdomain
        add_action( 'init', [ $this, 'load_textdomain' ] );
        
        // Check for plugin updates
        add_action( 'admin_init', [ $this, 'check_version' ] );
    }
    
    /**
     * Initialize main classes
     */
    private function init_classes() {
        // Initialize API Handler
        if ( class_exists( '\SMSenlinea_WhatsApp\API_Handler' ) ) {
            $this->api_handler = new API_Handler();
        }
        
        // Initialize Admin Settings
        if ( class_exists( '\SMSenlinea_WhatsApp\Admin_Settings' ) ) {
            new Admin_Settings();
        }
        
        // Initialize WooCommerce Hooks
        if ( class_exists( '\SMSenlinea_WhatsApp\WooCommerce_Hooks' ) && $this->api_handler ) {
            new WooCommerce_Hooks( $this->api_handler );
        }
    }
    
    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'smsenlinea-whatsapp-woocommerce',
            false,
            dirname( plugin_basename( SMSENLINEA_WC_PLUGIN_PATH . 'smsenlinea-whatsapp-woocommerce.php' ) ) . '/languages'
        );
    }
    
    /**
     * Check plugin version and run updates if needed
     */
    public function check_version() {
        $current_version = get_option( 'smsenlinea_wc_version', '0' );
        
        if ( version_compare( $current_version, SMSENLINEA_WC_VERSION, '<' ) ) {
            $this->run_updates( $current_version );
            update_option( 'smsenlinea_wc_version', SMSENLINEA_WC_VERSION );
        }
    }
    
    /**
     * Run database updates based on version
     * 
     * @param string $current_version Current installed version
     */
    private function run_updates( $current_version ) {
        // Run updates based on version comparisons
        if ( version_compare( $current_version, '1.3.0', '<' ) ) {
            $this->update_to_1_3_0();
        }
        
        if ( version_compare( $current_version, '1.4.0', '<' ) ) {
            $this->update_to_1_4_0();
        }
    }
    
    /**
     * Update to version 1.3.0
     */
    private function update_to_1_3_0() {
        // Create or update database tables
        $this->create_tables();
    }
    
    /**
     * Update to version 1.4.0
     */
    private function update_to_1_4_0() {
        // Future updates for 1.4.0
        // Add any necessary database or settings updates here
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smsenlinea_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            recipient varchar(25) NOT NULL,
            channel varchar(10) NOT NULL,
            status varchar(10) NOT NULL,
            message text NOT NULL,
            response text NOT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY status (status),
            KEY channel (channel)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Clear any cached data
        wp_cache_flush();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events if any
        wp_clear_scheduled_hook( 'smsenlinea_daily_cleanup' );
        
        // Clear cache
        wp_cache_flush();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_settings = [
            'default_country_code' => '57', // Colombia
            'sms_mode' => 'devices',
            'sms_sim_slot' => 1,
        ];
        
        $current_settings = get_option( 'wc_smsenlinea_settings', [] );
        
        // Only add defaults for missing values
        foreach ( $default_settings as $key => $value ) {
            if ( ! isset( $current_settings[ $key ] ) ) {
                $current_settings[ $key ] = $value;
            }
        }
        
        update_option( 'wc_smsenlinea_settings', $current_settings );
    }
    
    /**
     * Get API handler instance
     * 
     * @return API_Handler|null
     */
    public function get_api_handler() {
        return $this->api_handler;
    }
    
    /**
     * Check if WooCommerce is active
     * 
     * @return bool
     */
    public static function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }
    
    /**
     * Check if Gravity Forms is active
     * 
     * @return bool
     */
    public static function is_gravity_forms_active() {
        return class_exists( 'GFForms' ) || class_exists( 'GFCommon' );
    }
    
    /**
     * Log debug information
     * 
     * @param string $message
     * @param string $level
     */
    public static function log( $message, $level = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            error_log( sprintf( '[SMSenlinea %s]: %s', strtoupper( $level ), $message ) );
        }
    }
}
