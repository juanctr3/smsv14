<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'GFCommon' ) ) return;

\GFForms::include_addon_framework();

class Gravity_Forms_Addon extends \GFAddOn {

    protected $_slug = 'smsenlinea_gf_addon';
    protected $_version = '22.0.1';
    protected $_min_gravityforms_version = '2.5';
    protected $_title = 'Notificaciones WhatsApp por SMSenlinea';
    protected $_short_title = 'Notificaciones WhatsApp';
    private static $_instance = null;
    private $api_handler;

    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function pre_init() {
        parent::pre_init();
        $this->api_handler = new API_Handler();
        add_action( 'gform_after_submission', [ $this, 'process_submission' ], 10, 2 );
        add_action( 'gform_enqueue_scripts', [ $this, 'enqueue_form_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_smsenlinea_gf_test', [ $this, 'handle_test_message' ] );
    }

    public function form_settings_fields( $form ) {
        $phone_fields = $this->get_phone_fields($form);
        return [
            [
                'title'  => esc_html__( 'Ajustes de Notificaciones WhatsApp', 'smsenlinea-whatsapp-woocommerce' ),
                'fields' => [
                    [
                        'label'   => esc_html__( 'Habilitar Notificaciones', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_notifications',
                        'choices' => [ [ 'label' => esc_html__( 'Habilitar notificaciones de WhatsApp/SMS para este formulario', 'smsenlinea-whatsapp-woocommerce' ), 'name' => 'enable_notifications' ] ],
                    ],
                    [
                        'label'   => '<h4>' . esc_html__( 'Notificación al Usuario', 'smsenlinea-whatsapp-woocommerce' ) . '</h4><p class="description">Se envía a la persona que rellena el formulario.</p>',
                        'type'    => 'html',
                    ],
                    [
                        'label'   => esc_html__( 'Activar para el Usuario', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_customer',
                        'choices' => [ [ 'label' => esc_html__( 'Activada', 'smsenlinea-whatsapp-woocommerce' ), 'name' => 'enable_customer' ] ],
                    ],
                    [
                        'label'   => esc_html__( 'Campo del Teléfono del Usuario', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'select',
                        'name'    => 'customer_phone_field',
                        'choices' => $phone_fields,
                    ],
                    [
                        'label'   => esc_html__( 'Mensaje para el Usuario', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'textarea',
                        'name'    => 'customer_message', 'class'   => 'large',
                        'tooltip' => '<h6>' . esc_html__( 'Contenido del Mensaje', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Usa las Merge Tags de Gravity Forms (el icono a la derecha del campo) para personalizar el mensaje. Ej: ¡Gracias por tu registro, {Nombre:1}!', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                    [
                        'label'   => esc_html__( 'Canal de Envío', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'radio', 'name'    => 'customer_channel', 'horizontal' => true,
                        'choices' => [ [ 'label' => 'WhatsApp', 'value' => 'whatsapp' ], [ 'label' => 'SMS', 'value' => 'sms' ] ],
                        'default_value' => 'whatsapp'
                    ],
                    [
                        'label'   => '<hr><h4>' . esc_html__( 'Notificación al Administrador', 'smsenlinea-whatsapp-woocommerce' ) . '</h4>',
                        'type'    => 'html',
                    ],
                    [
                        'label'   => esc_html__( 'Activar para el Admin', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox', 'name'    => 'enable_admin',
                        'choices' => [ [ 'label' => esc_html__( 'Activada', 'smsenlinea-whatsapp-woocommerce' ), 'name' => 'enable_admin' ] ],
                    ],
                    [
                        'label'   => esc_html__( 'Números de Administradores', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'text', 'name'    => 'admin_phones', 'class'   => 'large',
                        'tooltip' => '<h6>' . esc_html__( 'Teléfonos del Admin', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Separados por comas. Incluir código de país. Si se deja en blanco, se usarán los números de la configuración global del plugin.', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                     [
                        'label'   => esc_html__( 'Mensaje para el Admin', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'textarea', 'name'    => 'admin_message', 'class'   => 'large',
                        'default_value' => 'Nueva entrada del formulario "' . $form['title'] . '": {all_fields}',
                    ],
                    [
                        'label'   => esc_html__( 'Canal de Envío', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'radio', 'name'    => 'admin_channel', 'horizontal' => true,
                        'choices' => [ [ 'label' => 'WhatsApp', 'value' => 'whatsapp' ], [ 'label' => 'SMS', 'value' => 'sms' ] ],
                        'default_value' => 'whatsapp'
                    ],
                ],
            ],
        ];
    }
    
    private function get_phone_fields( $form ) {
        $fields = [ [ 'label' => esc_html__( '-- Seleccionar Campo --', 'smsenlinea-whatsapp-woocommerce' ), 'value' => '' ] ];
        foreach ( $form['fields'] as $field ) {
            if ( $field->get_input_type() === 'phone' ) {
                $fields[] = [ 'label' => esc_html( $field->label ), 'value' => $field->id ];
            }
        }
        return $fields;
    }
    
    public function enqueue_form_scripts( $form, $is_ajax ) {
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
    
    private function is_form_settings_page() {
        return rgar( $_GET, 'page' ) === 'gf_edit_forms' && 
               rgar( $_GET, 'view' ) === 'settings' && 
               rgar( $_GET, 'subview' ) === $this->_slug;
    }
    
    public function handle_test_message() {
        check_ajax_referer( 'smsenlinea_gf_test_nonce', 'nonce' );
        $form_id = intval( $_POST['form_id'] ?? 0 );
        $test_phone = sanitize_text_field( $_POST['test_phone'] ?? '' );
        $message_type = sanitize_key( $_POST['message_type'] ?? '' );
        if ( empty( $form_id ) || empty( $test_phone ) || ! in_array( $message_type, [ 'customer', 'admin' ] ) ) {
            wp_send_json_error( [ 'message' => 'Parámetros inválidos' ] );
        }
        $form = \GFAPI::get_form( $form_id );
        $settings = $this->get_form_settings( $form );
        $dummy_entry = $this->create_dummy_entry( $form );
        $message_key = $message_type . '_message';
        $channel_key = $message_type . '_channel';
        $message = \GFCommon::replace_variables( 
            $settings[ $message_key ] ?? 'Mensaje de prueba', 
            $form, 
            $dummy_entry, 
            false, 
            true, 
            false, 
            'text' 
        );
        $channel = $settings[ $channel_key ] ?? 'whatsapp';
        $global_settings = get_option('wc_smsenlinea_settings', []);
        $default_dial_code = $global_settings['default_country_code'] ?? '57';
        $formatted_phone = $this->api_handler->format_phone_number( $test_phone, '', $default_dial_code );
        if ( ! $formatted_phone ) {
            wp_send_json_error( [ 'message' => 'Formato de número de teléfono inválido' ] );
        }
        $result = $this->api_handler->send_direct_message( $formatted_phone, $message, $channel );
        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => sprintf( 'Mensaje de prueba %s enviado exitosamente a %s vía %s', $message_type, $formatted_phone, strtoupper( $channel ) ) ] );
        } else {
            wp_send_json_error( [ 'message' => 'Error al enviar mensaje de prueba: ' . $result['error'] ] );
        }
    }

    private function create_dummy_entry( $form ) {
        $entry = [ 'id' => 'TEST', 'form_id' => $form['id'], 'date_created' => current_time( 'mysql' ), 'is_spam' => false, 'status' => 'active', ];
        foreach ( $form['fields'] as $field ) {
            switch ( $field->get_input_type() ) {
                case 'text': case 'email': $entry[ $field->id ] = 'Texto de ejemplo'; break;
                case 'phone': $entry[ $field->id ] = '+1234567890'; break;
                case 'textarea': $entry[ $field->id ] = 'Contenido de ejemplo'; break;
                case 'select': case 'radio': if ( ! empty( $field->choices ) ) { $entry[ $field->id ] = $field->choices[0]['value']; } break;
                case 'checkbox': if ( ! empty( $field->choices ) ) { $entry[ $field->id . '.1' ] = $field->choices[0]['value']; } break;
                case 'number': $entry[ $field->id ] = '123'; break;
                default: $entry[ $field->id ] = 'Valor de ejemplo';
            }
        }
        return $entry;
    }
    
    public function process_submission( $entry, $form ) {
        $settings = $this->get_form_settings($form);
        $global_settings = get_option('wc_smsenlinea_settings', []);
        $this->api_handler = new API_Handler();
        
        if ( !empty($settings['enable_customer']) && !empty($settings['customer_phone_field']) && !empty($settings['customer_message']) ) {
            $customer_phone = rgar( $entry, $settings['customer_phone_field'] );
            $message = \GFCommon::replace_variables($settings['customer_message'], $form, $entry, false, true, false, 'text');
            $channel = $settings['customer_channel'] ?? 'whatsapp';
            $default_dial_code = $global_settings['default_country_code'] ?? '';
            $formatted_phone = $this->api_handler->format_phone_number($customer_phone, '', $default_dial_code);
            if ($formatted_phone) {
                $this->api_handler->send_direct_message($formatted_phone, $message, $channel);
            }
        }
        
        if ( !empty($settings['enable_admin']) && !empty($settings['admin_message']) ) {
            $admin_phones_str = !empty($settings['admin_phones']) ? $settings['admin_phones'] : ($global_settings['admin_phones'] ?? '');
            if (!empty($admin_phones_str)) {
                $admin_phones = array_map('trim', explode(',', $admin_phones_str));
                $message = \GFCommon::replace_variables($settings['admin_message'], $form, $entry, false, true, false, 'text');
                $channel = $settings['admin_channel'] ?? 'whatsapp';
                foreach ($admin_phones as $phone) {
                    if (!empty($phone)) {
                        $this->api_handler->send_direct_message($phone, $message, $channel);
                    }
                }
            }
        }
    }
}
