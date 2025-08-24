<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;

class WooCommerce_Hooks {
    private $api_handler;

    public function __construct( API_Handler $api_handler ) {
        $this->api_handler = $api_handler;
        $this->add_woocommerce_hooks();
    }

    private function add_woocommerce_hooks() {
        add_action( 'woocommerce_new_order', [ $this, 'handle_new_order' ], 10, 1 );
        add_action( 'woocommerce_order_status_changed', [ $this, 'handle_status_change' ], 10, 4 );
        add_action( 'woocommerce_new_customer_note', [ $this, 'handle_new_note' ], 10, 1 );
    }

    public function handle_new_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        
        $transient_key = 'smsenlinea_lock_order_' . $order_id;
        if ( get_transient($transient_key) ) return;

        $settings = get_option( 'wc_smsenlinea_settings', [] );
        $status_slug = 'wc-' . $order->get_status();
        $enable_key = 'enable_status_' . $status_slug;
        $message_key = 'status_msg_' . $status_slug;
        $channel_key = 'channel_status_' . $status_slug;
        
        $message_sent = false;
        if ( ! empty( $settings[$enable_key] ) && ! empty( $settings[$message_key] ) ) {
            $message = $this->format_message( $settings[$message_key], $order );
            $channel = $settings[$channel_key] ?? 'whatsapp';
            $this->send_customer_notification( $order, $message, $channel );
            $message_sent = true;
        } 
        else if ( ! empty( $settings['enable_new_order'] ) && ! empty( $settings['new_order_msg'] ) ) {
            $message = $this->format_message( $settings['new_order_msg'], $order );
            $channel = $settings['channel_new_order'] ?? 'whatsapp';
            $this->send_customer_notification( $order, $message, $channel );
            $message_sent = true;
        }

        $this->send_admin_notification( $order, 'new_order', $settings );

        if ($message_sent) {
            set_transient($transient_key, 'sent', 3 * MINUTE_IN_SECONDS);
        }
    }

    public function handle_status_change( $order_id, $old_status, $new_status, $order ) {
        if ( ! $order ) return;
        $transient_key = 'smsenlinea_lock_order_' . $order_id;
        if ( get_transient($transient_key) ) return;

        $settings = get_option( 'wc_smsenlinea_settings', [] );
        $status_slug = 'wc-' . $new_status;
        $enable_key = 'enable_status_' . $status_slug;
        $message_key = 'status_msg_' . $status_slug;
        $channel_key = 'channel_status_' . $status_slug;
        
        if ( ! empty( $settings[$enable_key] ) && ! empty( $settings[$message_key] ) ) {
            $message = $this->format_message( $settings[$message_key], $order );
            $channel = $settings[$channel_key] ?? 'whatsapp';
            $this->send_customer_notification( $order, $message, $channel );
        }
        $this->send_admin_notification( $order, 'status_change', $settings, $new_status );
    }

    public function handle_new_note( $args ) {
        $settings = get_option( 'wc_smsenlinea_settings', [] );
        $order = wc_get_order( $args['order_id'] );
        if ( ! $order ) return;
        if ( ! empty( $settings['enable_new_note'] ) && ! empty( $settings['new_note_msg'] ) ) {
            $message = $this->format_message( $settings['new_note_msg'], $order, $args['customer_note'] );
            $channel = $settings['channel_new_note'] ?? 'whatsapp';
            $this->send_customer_notification( $order, $message, $channel );
        }
    }

    private function format_message( $template, $order, $note = '' ) {
        if ( ! is_a($order, 'WC_Order') && ! is_a($order, 'Automattic\WooCommerce\Admin\Overrides\Order') ) {
            return $template;
        }

        $items_list = '';
        $items_with_price_list = '';
        $review_url = '';
        $billing_country_name = '';
        $shipping_country_name = '';
        $username = '';
        $currency_args = array('currency' => $order->get_currency());

        $items = $order->get_items();
        if (!empty($items)) {
            $first_item = reset($items);
            if ($first_item) {
                $product_id = $first_item->get_product_id();
                if ($product_id) $review_url = get_permalink($product_id) . '#reviews';
            }
            foreach ( $items as $item ) {
                $items_list .= '  - ' . $item->get_name() . ' x ' . $item->get_quantity() . "\n";
                $line_total_formatted = wc_price($item->get_total() + $item->get_total_tax(), $currency_args);
                $clean_line_total = html_entity_decode(wp_strip_all_tags($line_total_formatted));
                $items_with_price_list .= '- ' . $item->get_name() . ' (x' . $item->get_quantity() . '): ' . $clean_line_total . "\n";
            }
        }
        
        if (function_exists('WC') && isset(WC()->countries)) {
            $countries = WC()->countries->get_countries();
            $billing_country_code = $order->get_billing_country();
            $shipping_country_code = $order->get_shipping_country();
            $billing_country_name = $countries[$billing_country_code] ?? $billing_country_code;
            $shipping_country_name = $countries[$shipping_country_code] ?? $shipping_country_code;
        }

        if ($order->get_customer_id()) {
            $user = get_user_by('id', $order->get_customer_id());
            if ($user) $username = $user->user_login;
        }

        $replacements = [
            '{shop_name}'               => get_bloginfo('name'),
            '{my_account_url}'          => wc_get_page_permalink('myaccount'),
            '{order_id}'                => $order->get_order_number(),
            '{order_status}'            => wc_get_order_status_name( $order->get_status() ),
            '{order_total}'             => html_entity_decode( wp_strip_all_tags( wc_price($order->get_total(), $currency_args) ) ),
            '{order_subtotal}'          => html_entity_decode( wp_strip_all_tags( wc_price($order->get_subtotal(), $currency_args) ) ),
            '{order_shipping_total}'    => html_entity_decode( wp_strip_all_tags( wc_price($order->get_shipping_total(), $currency_args) ) ),
            '{order_tax_total}'         => html_entity_decode( wp_strip_all_tags( wc_price($order->get_total_tax(), $currency_args) ) ),
            '{order_discount_total}'    => html_entity_decode( wp_strip_all_tags( wc_price($order->get_discount_total(), $currency_args) ) ),
            '{order_date}'              => $order->get_date_created()->format('d/m/Y'),
            '{order_time}'              => $order->get_date_created()->format('g:i a'),
            '{order_url}'               => $order->get_view_order_url(),
            '{order_items}'             => trim($items_list),
            '{order_items_with_price}'  => trim($items_with_price_list),
            '{order_item_count}'        => $order->get_item_count(),
            '{order_note}'              => wp_strip_all_tags( $note ),
            '{customer_order_note}'     => $order->get_customer_note(),
            '{customer_name}'           => $order->get_billing_first_name(),
            '{customer_lastname}'       => $order->get_billing_last_name(),
            '{customer_fullname}'       => $order->get_formatted_billing_full_name(),
            '{customer_email}'          => $order->get_billing_email(),
            '{customer_username}'       => $username,
            '{billing_phone}'           => $order->get_billing_phone(),
            '{billing_city}'            => $order->get_billing_city(),
            '{billing_country_name}'    => $billing_country_name,
            '{shipping_city}'           => $order->get_shipping_city(),
            '{shipping_country_name}'   => $shipping_country_name,
            '{shipping_method}'         => $order->get_shipping_method(),
            '{shipping_address}'        => str_replace('<br/>', "\n", $order->get_formatted_shipping_address() ?? ''),
            '{billing_address}'         => str_replace('<br/>', "\n", $order->get_formatted_billing_address() ?? ''),
            '{payment_method}'          => $order->get_payment_method_title(),
            '{payment_url}'             => $order->get_checkout_payment_url(),
            '{transaction_id}'          => $order->get_transaction_id(),
            '{invoice_download_url}'    => $order->get_view_order_url(), // Enlace a la vista del pedido donde suelen estar los botones de factura
            '{review_url}'              => $review_url,
        ];
        
        $message_with_vars = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

        // Procesar shortcodes de otros plugins
        $final_message = do_shortcode($message_with_vars);

        return $final_message;
    }
    
    private function send_customer_notification( $order, $message, $channel = 'whatsapp' ) {
        $settings = get_option( 'wc_smsenlinea_settings', [] );
        $default_dial_code = $settings['default_country_code'] ?? '';
        $raw_phone = $order->get_billing_phone();
        $billing_country = $order->get_billing_country();
        $formatted_phone = $this->api_handler->format_phone_number( $raw_phone, $billing_country, $default_dial_code );
        if ( ! $formatted_phone ) { if (is_a($order, 'WC_Order') || is_a($order, 'Automattic\WooCommerce\Admin\Overrides\Order')) $order->add_order_note( 'Envío cancelado: Teléfono de cliente vacío.' ); return; }
        $result = ($channel === 'sms') ? $this->api_handler->send_sms_message($formatted_phone, $message) : $this->api_handler->send_whatsapp_message($formatted_phone, $message);
        if ( is_a($order, 'WC_Order') || is_a($order, 'Automattic\WooCommerce\Admin\Overrides\Order') ) {
            if ( $result['success'] ) { $order->add_order_note( sprintf( 'Notificación de %s enviada al cliente (%s).', strtoupper($channel), $formatted_phone ) );
            } else { $order->add_order_note( sprintf( 'Error al enviar %s al cliente %s. Motivo: %s', strtoupper($channel), $formatted_phone, $result['error'] ) ); }
        }
    }

    private function send_admin_notification( $order, $event_type, $settings, $new_status = '' ) {
        $admin_phones_str = $settings['admin_phones'] ?? '';
        if (empty($admin_phones_str)) return;
        $enable_key = 'enable_admin_' . $event_type;
        $message_key = 'admin_' . $event_type . '_msg';
        $channel_key = 'channel_admin_' . $event_type;
        if ( $event_type === 'status_change' && !empty($new_status) ) {
            $status_slug = 'wc-' . $new_status;
            $enable_key = 'enable_admin_status_' . $status_slug;
            $message_key = 'admin_status_msg_' . $status_slug;
            $channel_key = 'channel_admin_status_' . $status_slug;
        }
        if ( empty($settings[$enable_key]) || empty($settings[$message_key]) ) return;
        $channel = $settings[$channel_key] ?? 'whatsapp';
        $admin_phones = array_map('trim', explode(',', $admin_phones_str));
        $message_template = $settings[$message_key];
        $formatted_message = $this->format_message($message_template, $order);
        foreach ( $admin_phones as $phone ) {
            if ( ! empty($phone) ) {
                $result = ($channel === 'sms') ? $this->api_handler->send_sms_message($phone, $formatted_message) : $this->api_handler->send_whatsapp_message($phone, $formatted_message);
                if ( $result['success'] ) { $order->add_order_note( sprintf( 'Notificación de %s enviada al admin (%s).', strtoupper($channel), $phone ) );
                } else { $order->add_order_note( sprintf( 'Error al notificar al admin %s vía %s: %s', $phone, strtoupper($channel), $result['error'] ) ); }
            }
        }
    }
}