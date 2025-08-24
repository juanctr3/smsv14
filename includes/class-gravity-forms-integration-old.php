<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'GFCommon' ) ) return;

\GFForms::include_addon_framework();

class Gravity_Forms_Integration extends \GFAddOn {

    protected $_slug = 'smsenlinea_whatsapp_gf';
    protected $_title = 'Notificaciones WhatsApp por SMSenlinea';
    protected $_short_title = 'Notificaciones WhatsApp';
    private static $_instance = null;

    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function init() {
        parent::init();
        add_action( 'gform_after_submission', [ $this, 'process_submission' ], 10, 2 );
    }

    public function form_settings_fields( $form ) {
        $phone_fields = $this->get_phone_fields($form);

        return [
            [
                'title'  => esc_html__( 'Ajustes de Notificaciones WhatsApp', 'smsenlinea-whatsapp-woocommerce' ),
                'fields' => [
                    // Notificación al Usuario
                    [
                        'label'   => '<h4>' . esc_html__( 'Notificación al Usuario', 'smsenlinea-whatsapp-woocommerce' ) . '</h4>',
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
                        'tooltip' => '<h6>' . esc_html__( 'Campo de Teléfono', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Selecciona el campo de tu formulario que contiene el número de teléfono del usuario.', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                    [
                        'label'   => esc_html__( 'Mensaje para el Usuario', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'textarea',
                        'name'    => 'customer_message',
                        'class'   => 'large',
                        'tooltip' => '<h6>' . esc_html__( 'Contenido del Mensaje', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Usa las Merge Tags de Gravity Forms (el icono a la derecha del campo) para personalizar el mensaje. Ej: ¡Gracias por tu registro, {Nombre:1}!', 'smsenlinea-whatsapp-woocommerce' ),
                    ],

                     // Notificación al Administrador
                    [
                        'label'   => '<hr><h4>' . esc_html__( 'Notificación al Administrador', 'smsenlinea-whatsapp-woocommerce' ) . '</h4>',
                        'type'    => 'html',
                    ],
                    [
                        'label'   => esc_html__( 'Activar para el Admin', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_admin',
                        'choices' => [ [ 'label' => esc_html__( 'Activada', 'smsenlinea-whatsapp-woocommerce' ), 'name' => 'enable_admin' ] ],
                    ],
                    [
                        'label'   => esc_html__( 'Números de Administradores', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'text',
                        'name'    => 'admin_phones',
                        'class'   => 'large',
                        'tooltip' => '<h6>' . esc_html__( 'Teléfonos del Admin', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Separados por comas. Incluir código de país. Si se deja en blanco, se usarán los números de la configuración global del plugin.', 'smsenlinea-whatsapp-woocommerce' ),
                    ],
                     [
                        'label'   => esc_html__( 'Mensaje para el Admin', 'smsenlinea-whatsapp-woocommerce' ),
                        'type'    => 'textarea',
                        'name'    => 'admin_message',
                        'class'   => 'large',
                        'default_value' => 'Nueva entrada del formulario "' . $form['title'] . '": {all_fields}',
                        'tooltip' => '<h6>' . esc_html__( 'Contenido del Mensaje', 'smsenlinea-whatsapp-woocommerce' ) . '</h6>' . esc_html__( 'Usa las Merge Tags de Gravity Forms para personalizar el mensaje.', 'smsenlinea-whatsapp-woocommerce' ),
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

    public function process_submission( $entry, $form ) {
        $settings = $this->get_form_settings($form);
        $global_settings = get_option('wc_smsenlinea_settings', []);
        $api_handler = new API_Handler();
        
        // Procesar notificación al cliente
        if ( !empty($settings['enable_customer']) && !empty($settings['customer_phone_field']) && !empty($settings['customer_message']) ) {
            $customer_phone = rgar( $entry, $settings['customer_phone_field'] );
            $message = \GFCommon::replace_variables($settings['customer_message'], $form, $entry, false, true, false, 'text');
            $default_dial_code = $global_settings['default_country_code'] ?? '';
            $billing_country = rgar($entry, 'source_url') ? strtoupper(substr(parse_url(rgar($entry, 'source_url'), PHP_URL_HOST), 0, 2)) : '';
            $formatted_phone = $this->format_phone_number($customer_phone, $billing_country, $default_dial_code);

            if ($formatted_phone) {
                $api_handler->send_whatsapp_message($formatted_phone, $message);
            }
        }
        
        // Procesar notificación al administrador
        if ( !empty($settings['enable_admin']) && !empty($settings['admin_message']) ) {
            $admin_phones_str = !empty($settings['admin_phones']) ? $settings['admin_phones'] : ($global_settings['admin_phones'] ?? '');
            if (!empty($admin_phones_str)) {
                $admin_phones = array_map('trim', explode(',', $admin_phones_str));
                $message = \GFCommon::replace_variables($settings['admin_message'], $form, $entry, false, true, false, 'text');
                foreach ($admin_phones as $phone) {
                    if (!empty($phone)) {
                        $api_handler->send_whatsapp_message($phone, $message);
                    }
                }
            }
        }
    }

    private function format_phone_number( $phone, $country_code, $default_dial_code ) {
        if ( empty( $phone ) ) return false;
        $cleaned_phone = preg_replace( '/[^\d]/', '', $phone );
        $dialing_codes = ['CO'=>'57','MX'=>'52','PE'=>'51','EC'=>'593','CL'=>'56','AR'=>'54','US'=>'1','ES'=>'34','PA'=>'507','VE'=>'58'];
        $target_dial_code = $dialing_codes[$country_code] ?? $default_dial_code;
        if ( empty( $target_dial_code ) ) return $cleaned_phone;
        if ( substr( $cleaned_phone, 0, strlen( $target_dial_code ) ) === (string) $target_dial_code ) return $cleaned_phone;
        return $target_dial_code . $cleaned_phone;
    }
}
