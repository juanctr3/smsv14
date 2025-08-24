<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin_Settings {
    
    private $variables;

    public function __construct() {
        $this->set_variables();
        add_action( 'admin_menu', [ $this, 'add_admin_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_plugin_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_smsenlinea_send_test', [ $this, 'handle_ajax_send_test' ] );
        add_action( 'admin_init', [ $this, 'handle_clear_logs' ] );
    }

    private function set_variables() {
        $this->variables = [
            'Pedido' => ['{order_id}' => 'ID Pedido', '{order_status}' => 'Estado', '{order_date}' => 'Fecha', '{order_time}' => 'Hora', '{order_url}' => 'URL Ver Pedido', '{order_items}' => 'Lista Productos (Simple)', '{order_items_with_price}' => 'Lista Productos (con Precios)', '{order_item_count}' => 'Cant. de Productos', '{customer_order_note}' => 'Nota del Cliente en Pedido', ],
            'Importes' => ['{order_total}' => 'Total General', '{order_subtotal}' => 'Subtotal', '{order_shipping_total}' => 'Total Envío', '{order_tax_total}' => 'Total Impuestos', '{order_discount_total}' => 'Total Descuento', ],
            'Cliente' => ['{customer_name}' => 'Nombre', '{customer_lastname}' => 'Apellido', '{customer_fullname}' => 'Nombre Completo', '{customer_email}' => 'Email', '{customer_username}' => 'Usuario WP', '{billing_phone}' => 'Teléfono de Facturación', ],
            'Dirección' => ['{billing_city}' => 'Ciudad de Facturación', '{billing_country_name}' => 'País de Facturación', '{shipping_city}' => 'Ciudad de Envío', '{shipping_country_name}' => 'País de Envío', '{shipping_address}' => 'Dirección Envío Completa', '{billing_address}' => 'Dirección Facturación Completa', ],
            'Envío y Pago' => ['{shipping_method}' => 'Método Envío', '{payment_method}' => 'Método Pago', '{payment_url}' => 'URL de Pago', '{transaction_id}' => 'ID de Transacción', '{invoice_download_url}' => 'URL Factura (Mi Cuenta)', ],
            'Marketing y Reseñas' => [ '{review_url}' => 'URL para Dejar Reseña', '{my_account_url}' => 'URL Mi Cuenta', '{shop_name}' => 'Nombre de la Tienda' ],
            'Notas' => [ '{order_note}' => 'Nota del Admin' ]
        ];
    }
    
    private function render_variables_ui($textarea_id) {
        echo '<div class="variables-container">';
        echo '<a href="#" class="toggle-variables-link">' . __('Ver variables', 'smsenlinea-whatsapp-woocommerce') . '</a>';
        echo '<button type="button" class="button button-secondary button-small smsenlinea-emoji-picker-btn" data-target="' . esc_attr($textarea_id) . '">🙂 Emojis</button>';
        echo '<div class="variable-buttons-wrapper">';
        foreach($this->variables as $group_label => $group_vars) {
            echo '<div class="variable-group"><strong>' . esc_html($group_label) . ':</strong> ';
            foreach ($group_vars as $variable => $label) { echo '<button type="button" class="button button-small insert-variable-btn" data-variable="' . esc_attr($variable) . '">' . esc_html($label) . '</button> '; }
            echo '</div>';
        }
        echo '</div></div>';
    }

    private function render_channel_selector($name, $current_value) {
        ?>
        <div class="channel-selector">
            <label><input type="radio" name="wc_smsenlinea_settings[<?php echo esc_attr($name); ?>]" value="whatsapp" <?php checked($current_value, 'whatsapp'); ?>> WhatsApp</label>
            <label><input type="radio" name="wc_smsenlinea_settings[<?php echo esc_attr($name); ?>]" value="sms" <?php checked($current_value, 'sms'); ?>> SMS</label>
        </div>
        <?php
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_wc-smsenlinea-settings' !== $hook ) return;
        wp_enqueue_style( 'wc-smsenlinea-admin-style', SMSENLINEA_WC_PLUGIN_URL . 'assets/css/admin-styles.css', [], '1.4.0' );
        wp_enqueue_script( 'wc-smsenlinea-admin-script', SMSENLINEA_WC_PLUGIN_URL . 'assets/js/admin-scripts.js', ['jquery'], '1.4.0', true );
        wp_localize_script('wc-smsenlinea-admin-script', 'smsenlinea_ajax', [ 'ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('smsenlinea-test-nonce') ]);
    }

    public function add_admin_menu_page() {
        add_submenu_page('woocommerce', 'Ajustes de Notificaciones', 'Notificaciones WhatsApp', 'manage_woocommerce', 'wc-smsenlinea-settings', [ $this, 'render_settings_page' ]);
    }

    public function register_plugin_settings() {
        register_setting( 'wc_smsenlinea_options', 'wc_smsenlinea_settings' );
    }

    public function handle_clear_logs() {
        if ( isset($_GET['action'], $_GET['_wpnonce']) && $_GET['action'] === 'clear_logs' && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'smsenlinea_clear_logs_action')) {
            global $wpdb; $table_name = $wpdb->prefix . 'smsenlinea_logs'; $wpdb->query("TRUNCATE TABLE $table_name");
            wp_safe_redirect(admin_url('admin.php?page=wc-smsenlinea-settings&tab=log&logs_cleared=true'));
            exit;
        }
    }

    public function render_settings_page() {
        $options = get_option( 'wc_smsenlinea_settings', [] );
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key($_GET['tab']) : 'customer_notifications';
        if (isset($_GET['logs_cleared']) && $_GET['logs_cleared'] == 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>Historial de envíos borrado con éxito.</p></div>';
        }
        ?>
        <div class="wrap wc-smsenlinea-wrap">
            <h1>Ajustes de Notificaciones WhatsApp</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=wc-smsenlinea-settings&tab=customer_notifications" class="nav-tab <?php echo $active_tab == 'customer_notifications' ? 'nav-tab-active' : ''; ?>">Notificaciones de Pedidos</a>
                <a href="?page=wc-smsenlinea-settings&tab=admin_notifications" class="nav-tab <?php echo $active_tab == 'admin_notifications' ? 'nav-tab-active' : ''; ?>">Notificaciones al Admin</a>
                <a href="?page=wc-smsenlinea-settings&tab=log" class="nav-tab <?php echo $active_tab == 'log' ? 'nav-tab-active' : ''; ?>">Historial de Envíos</a>
                <a href="?page=wc-smsenlinea-settings&tab=api_testing" class="nav-tab <?php echo $active_tab == 'api_testing' ? 'nav-tab-active' : ''; ?>">API y Pruebas</a>
            </h2>
            <div id="tab-log" class="tab-content" style="<?php echo $active_tab == 'log' ? 'display:block;' : 'display:none;'; ?>">
                <h3>Historial de Notificaciones Enviadas</h3>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-smsenlinea-settings&tab=log&action=clear_logs'), 'smsenlinea_clear_logs_action'); ?>" class="button button-secondary" onclick="return confirm('¿Estás seguro de que quieres borrar todos los registros?');">Borrar Historial</a><br><br>
                <?php $log_table = new Log_List_Table(); $log_table->prepare_items(); $log_table->display(); ?>
            </div>
            <form action="options.php" method="post" style="<?php echo $active_tab == 'log' ? 'display:none;' : 'display:block;'; ?>">
                <?php settings_fields( 'wc_smsenlinea_options' ); ?>
                <div id="tab-customer_notifications" class="tab-content" style="<?php echo $active_tab != 'customer_notifications' ? 'display:none;' : ''; ?>">
                    <div class="notification-block"><h4>Nuevo Pedido</h4><label><input type="checkbox" name="wc_smsenlinea_settings[enable_new_order]" value="1" <?php checked(1, $options['enable_new_order'] ?? 0); ?>> Activar</label><?php $this->render_channel_selector('channel_new_order', $options['channel_new_order'] ?? 'whatsapp'); ?><textarea id="new_order_msg_area" name="wc_smsenlinea_settings[new_order_msg]" class="large-text" rows="3"><?php echo esc_textarea($options['new_order_msg'] ?? ''); ?></textarea><?php $this->render_variables_ui('new_order_msg_area'); ?></div>
                    <h4>Cambios de Estado de Pedido</h4>
                    <?php $order_statuses = wc_get_order_statuses(); foreach ($order_statuses as $slug => $label) { $textarea_id = 'status_msg_' . esc_attr($slug) . '_area'; ?><div class="notification-block indented"><h5><?php echo esc_html($label); ?></h5><label><input type="checkbox" name="wc_smsenlinea_settings[enable_status_<?php echo esc_attr($slug); ?>]" value="1" <?php checked(1, $options['enable_status_'.$slug] ?? 0); ?>> Activar</label><?php $this->render_channel_selector('channel_status_'.$slug, $options['channel_status_'.$slug] ?? 'whatsapp'); ?><textarea id="<?php echo $textarea_id; ?>" name="wc_smsenlinea_settings[status_msg_<?php echo esc_attr($slug); ?>]" class="large-text" rows="3"><?php echo esc_textarea($options['status_msg_'.$slug] ?? ''); ?></textarea><?php $this->render_variables_ui($textarea_id); ?></div><?php } ?>
                    <div class="notification-block"><h4>Nueva Nota para el Cliente</h4><label><input type="checkbox" name="wc_smsenlinea_settings[enable_new_note]" value="1" <?php checked(1, $options['enable_new_note'] ?? 0); ?>> Activar</label><?php $this->render_channel_selector('channel_new_note', $options['channel_new_note'] ?? 'whatsapp'); ?><textarea id="new_note_msg_area" name="wc_smsenlinea_settings[new_note_msg]" class="large-text" rows="3"><?php echo esc_textarea($options['new_note_msg'] ?? ''); ?></textarea><?php $this->render_variables_ui('new_note_msg_area'); ?></div>
                </div>
                <div id="tab-admin_notifications" class="tab-content" style="<?php echo $active_tab != 'admin_notifications' ? 'display:none;' : ''; ?>">
                    <table class="form-table"><tr><th scope="row"><label for="admin_phones">Números de Administradores</label></th><td><textarea id="admin_phones" name="wc_smsenlinea_settings[admin_phones]" class="regular-text" rows="3"><?php echo esc_textarea( $options['admin_phones'] ?? '' ); ?></textarea><p class="description">Separados por comas.</p></td></tr></table><hr>
                    <h4>Alertas de Pedido para Administradores</h4>
                    <div class="notification-block"><h5>Nuevo Pedido</h5><label><input type="checkbox" name="wc_smsenlinea_settings[enable_admin_new_order]" value="1" <?php checked(1, $options['enable_admin_new_order'] ?? 0); ?>> Activar</label><?php $this->render_channel_selector('channel_admin_new_order', $options['channel_admin_new_order'] ?? 'whatsapp'); ?><textarea id="admin_new_order_msg_area" name="wc_smsenlinea_settings[admin_new_order_msg]" class="large-text" rows="3"><?php echo esc_textarea($options['admin_new_order_msg'] ?? ''); ?></textarea><?php $this->render_variables_ui('admin_new_order_msg_area'); ?></div>
                    <?php foreach ($order_statuses as $slug => $label) { $textarea_id = 'admin_status_msg_' . esc_attr($slug) . '_area'; ?><div class="notification-block indented"><h5><?php echo esc_html($label); ?></h5><label><input type="checkbox" name="wc_smsenlinea_settings[enable_admin_status_<?php echo esc_attr($slug); ?>]" value="1" <?php checked(1, $options['enable_admin_status_'.$slug] ?? 0); ?>> Activar</label><?php $this->render_channel_selector('channel_admin_status_'.$slug, $options['channel_admin_status_'.$slug] ?? 'whatsapp'); ?><textarea id="<?php echo $textarea_id; ?>" name="wc_smsenlinea_settings[admin_status_msg_<?php echo esc_attr($slug); ?>]" class="large-text" rows="3"><?php echo esc_textarea($options['admin_status_msg_'.$slug] ?? ''); ?></textarea><?php $this->render_variables_ui($textarea_id); ?></div><?php } ?>
                </div>
                <div id="tab-api_testing" class="tab-content" style="<?php echo $active_tab != 'api_testing' ? 'display:none;' : ''; ?>">
                     <h3>Ajustes Generales de API</h3><table class="form-table"><tr><th scope="row"><label for="api_secret">API Secret</label></th><td><input type="password" id="api_secret" name="wc_smsenlinea_settings[api_secret]" value="<?php echo esc_attr( $options['api_secret'] ?? '' ); ?>" class="regular-text" /></td></tr><tr><th scope="row"><label for="account_id">Account Unique ID (WhatsApp)</label></th><td><input type="text" id="account_id" name="wc_smsenlinea_settings[account_id]" value="<?php echo esc_attr( $options['account_id'] ?? '' ); ?>" class="regular-text" /></td></tr><tr><th scope="row"><label for="default_country_code">Código de País por Defecto</label></th><td><input type="text" id="default_country_code" name="wc_smsenlinea_settings[default_country_code]" value="<?php echo esc_attr( $options['default_country_code'] ?? '57' ); ?>" class="regular-text" /><p class="description">Para formatear números.</p></td></tr></table><hr>
                    <h3>Ajustes Específicos para SMS</h3><table class="form-table"><tr><th scope="row">Modo de Envío SMS</th><td><label><input type="radio" name="wc_smsenlinea_settings[sms_mode]" value="devices" <?php checked($options['sms_mode'] ?? 'devices', 'devices'); ?>> Usar Dispositivos</label><br><label><input type="radio" name="wc_smsenlinea_settings[sms_mode]" value="credits" <?php checked($options['sms_mode'] ?? 'devices', 'credits'); ?>> Usar Créditos</label></td></tr><tr><th scope="row"><label for="sms_device_id">ID del Dispositivo ("devices")</label></th><td><input type="text" id="sms_device_id" name="wc_smsenlinea_settings[sms_device_id]" value="<?php echo esc_attr( $options['sms_device_id'] ?? '' ); ?>" class="regular-text" /><p class="description">Obligatorio.</p></td></tr><tr><th scope="row"><label for="sms_gateway_id">ID de Pasarela ("credits")</label></th><td><input type="text" id="sms_gateway_id" name="wc_smsenlinea_settings[sms_gateway_id]" value="<?php echo esc_attr( $options['sms_gateway_id'] ?? '' ); ?>" class="regular-text" /><p class="description">Obligatorio.</p></td></tr><tr><th scope="row">Ranura SIM ("devices")</th><td><label><input type="radio" name="wc_smsenlinea_settings[sms_sim_slot]" value="1" <?php checked($options['sms_sim_slot'] ?? 1, 1); ?>> SIM 1</label><br><label><input type="radio" name="wc_smsenlinea_settings[sms_sim_slot]" value="2" <?php checked($options['sms_sim_slot'] ?? 1, 2); ?>> SIM 2</label></td></tr></table><hr>
                    <h3>Prueba de Envío</h3><table class="form-table"><tr><th scope="row"><label for="test_phone">Número de Destino</label></th><td><input type="text" id="test_phone" class="regular-text" /></td></tr><tr><th scope="row"><label for="test_message">Mensaje de Prueba</label></th><td><textarea id="test_message_area" class="large-text" rows="4">Prueba de envío.</textarea><?php $this->render_variables_ui('test_message_area'); ?></td></tr><tr><th scope="row">Canal de Prueba</th><td><?php $this->render_channel_selector('test_channel', 'whatsapp'); ?></td></tr><tr><th scope="row"></th><td><button type="button" id="smsenlinea-send-test-btn" class="button button-primary">Enviar Prueba</button><span class="spinner"></span></td></tr></table><div id="smsenlinea-test-response"></div>
                </div>
                <div class="tab-content-submit"><br><?php submit_button(); ?></div>
            </form>
        </div>
        <?php
    }
    public function handle_ajax_send_test() {
        check_ajax_referer( 'smsenlinea-test-nonce', 'nonce' );
        $phone = sanitize_text_field( $_POST['phone'] ?? '' );
        $message = sanitize_textarea_field( $_POST['message'] ?? '' );
        $channel = sanitize_key( $_POST['channel'] ?? 'whatsapp' );
        if ( empty( $phone ) || empty( $message ) ) wp_send_json_error(['message' => 'El número y el mensaje no pueden estar vacíos.']);
        $api_handler = new API_Handler();
        $result = ($channel === 'sms') ? $api_handler->send_sms_message($phone, $message) : $api_handler->send_whatsapp_message($phone, $message);
        if ( $result['success'] ) {
            wp_send_json_success(['message' => '¡Éxito! Mensaje enviado a ' . $phone . ' vía ' . strtoupper($channel)]);
        } else {
            wp_send_json_error(['message' => 'Error al enviar a ' . $phone . '. Motivo: ' . esc_html($result['error'])]);
        }
        wp_die();
    }
}
