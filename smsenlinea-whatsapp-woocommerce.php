<?php
/**
 * Plugin Name:       Notificaciones WhatsApp y SMS para WooCommerce (SMSenlinea)
 * Plugin URI:        https://www.smsenlinea.com/
 * Description:       Potencia la comunicación con tus clientes enviando notificaciones automáticas por WhatsApp y SMS para cada estado de pedido de WooCommerce. Integrado con la API de SMSenlinea.com.
 * Version:           1.4.1
 * Author:            SmsEnLinea.com
 * Author URI:        https://www.smsenlinea.com/
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smsenlinea-whatsapp-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 4.0
 * WC tested up to:  8.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Función para crear la tabla de logs al activar
function smsenlinea_wc_install() {
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
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    // Guardar la versión actual para futuras actualizaciones
    add_option( 'smsenlinea_wc_version', '1.4.1' );
}
register_activation_hook( __FILE__, 'smsenlinea_wc_install' );

// Rutina de actualización
function smsenlinea_wc_update_check() {
    $current_version = get_option( 'smsenlinea_wc_version' );
    if ( version_compare( $current_version, '1.4.1', '<' ) ) {
        smsenlinea_wc_install();
        update_option( 'smsenlinea_wc_version', '1.4.1' );
    }
}
add_action( 'plugins_loaded', 'smsenlinea_wc_update_check', 5 );


// Verificar si WooCommerce está activo.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p><strong>Notificaciones WhatsApp por SMSenlinea:</strong> Este plugin requiere que WooCommerce esté instalado y activo.</p></div>';
    });
    return;
}

// Definir constantes del plugin.
define( 'SMSENLINEA_WC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMSENLINEA_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMSENLINEA_WC_VERSION', '1.4.1' );

// Incluir el cargador principal del plugin.
require_once SMSENLINEA_WC_PLUGIN_PATH . 'includes/class-main.php';

/**
 * Inicia el plugin.
 */
function smsenlinea_wc_run() {
    return \SMSenlinea_WhatsApp\Main::instance();
}
add_action( 'plugins_loaded', 'smsenlinea_wc_run', 10 );
