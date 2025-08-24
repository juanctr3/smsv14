<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Main {
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) self::$_instance = new self();
        return self::$_instance;
    }
    private function __construct() {
        $this->load_dependencies();
        $this->init_classes();
    }
    private function load_dependencies() {
        require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-api-handler.php';
        require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-admin-settings.php';
        require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-woocommerce-hooks.php';
        require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-log-list-table.php';
    }
    private function init_classes() {
        $api_handler = new API_Handler();
        new Admin_Settings();
        new WooCommerce_Hooks( $api_handler );
    }
}