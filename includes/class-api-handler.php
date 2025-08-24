<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;

class API_Handler {

    const API_URL_WHATSAPP = "https://whatsapp.smsenlinea.com/api/send/whatsapp";
    const API_URL_SMS = "https://whatsapp.smsenlinea.com/api/send/sms";

    public function send_direct_message( $recipient, $message, $channel = 'whatsapp' ) {
        if ($channel === 'sms') {
            return $this->send_sms_message($recipient, $message);
        }
        return $this->send_whatsapp_message($recipient, $message);
    }

    public function send_whatsapp_message( $recipient, $message ) {
        $settings = get_option( 'wc_smsenlinea_settings', [] );
        $api_secret = $settings['api_secret'] ?? '';
        $account_id = $settings['account_id'] ?? '';
        if ( empty( $api_secret ) || empty( $account_id ) ) {
            return [ 'success' => false, 'error' => 'API Secret o Account ID no configurados.' ];
        }
        $payload = [ 'secret' => $api_secret, 'account' => $account_id, 'recipient' => $recipient, 'type' => 'text', 'message' => $message ];
        return $this->execute_request(self::API_URL_WHATSAPP, $payload, 'WhatsApp');
    }

    public function send_sms_message( $recipient, $message ) {
        $settings = get_option( 'wc_smsenlinea_settings', [] );
        $api_secret = $settings['api_secret'] ?? '';
        if ( empty( $api_secret ) ) {
            return [ 'success' => false, 'error' => 'API Secret no configurado.' ];
        }
        $payload = [ 'secret' => $api_secret, 'phone' => $recipient, 'message' => $message ];
        $sms_mode = $settings['sms_mode'] ?? 'devices';
        $payload['mode'] = $sms_mode;
        if ($sms_mode === 'devices') {
            $device_id = $settings['sms_device_id'] ?? '';
            $sim_slot = $settings['sms_sim_slot'] ?? 1;
            if (empty($device_id)) {
                 return [ 'success' => false, 'error' => 'Modo "devices" seleccionado, pero el ID del Dispositivo no está configurado.' ];
            }
            $payload['device'] = $device_id;
            $payload['sim'] = $sim_slot;
        } else {
            $gateway_id = $settings['sms_gateway_id'] ?? '';
             if (empty($gateway_id)) {
                 return [ 'success' => false, 'error' => 'Modo "credits" seleccionado, pero el ID de Pasarela/Gateway no está configurado.' ];
            }
            $payload['gateway'] = $gateway_id;
        }
        return $this->execute_request(self::API_URL_SMS, $payload, 'SMS');
    }

    private function execute_request($url, $payload, $channel) {
        $response = wp_remote_post( $url, [ 'body' => $payload, 'timeout' => 20 ] );
        $log_data = [ 'recipient' => $payload['phone'] ?? ( $payload['recipient'] ?? '' ), 'channel'   => $channel, 'message'   => $payload['message'] ];
        if ( is_wp_error( $response ) ) {
            $log_data['status'] = 'error'; $log_data['response'] = $response->get_error_message();
            $this->add_to_log($log_data);
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }
        $http_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $http_code === 200 ) {
            $log_data['status'] = 'success'; $log_data['response'] = $body;
        } else {
            $log_data['status'] = 'error'; $log_data['response'] = "HTTP Code: $http_code, Body: $body";
        }
        $this->add_to_log($log_data);
        return $log_data['status'] === 'success' 
            ? [ 'success' => true, 'response' => $body ] 
            : [ 'success' => false, 'error' => $log_data['response'] ];
    }

    public function add_to_log($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smsenlinea_logs';
        $result = $wpdb->insert($table_name, [
            'timestamp' => current_time('mysql'), 'recipient' => sanitize_text_field($data['recipient']),
            'channel'   => sanitize_text_field($data['channel']), 'status'    => sanitize_text_field($data['status']),
            'message'   => sanitize_textarea_field($data['message']), 'response'  => sanitize_text_field($data['response'])
        ]);
        
        // Loguear error de base de datos si la depuración está activa
        if ($result === false && defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('Error en SMSenlinea Plugin DB: ' . $wpdb->last_error);
        }
    }

    public function format_phone_number( $phone, $country_code, $default_dial_code ) {
        if ( empty( $phone ) ) return false;
        $cleaned_phone = preg_replace( '/[^\d]/', '', $phone );
        $dialing_codes = ['CO'=>'57','MX'=>'52','PE'=>'51','EC'=>'593','CL'=>'56','AR'=>'54','US'=>'1','ES'=>'34','PA'=>'507','VE'=>'58'];
        $target_dial_code = $dialing_codes[$country_code] ?? $default_dial_code;
        if ( empty( $target_dial_code ) ) return $cleaned_phone;
        if ( substr( $cleaned_phone, 0, strlen( $target_dial_code ) ) === (string) $target_dial_code ) return $cleaned_phone;
        return $target_dial_code . $cleaned_phone;
    }
}