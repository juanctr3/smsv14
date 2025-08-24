<?php
namespace SMSenlinea_WhatsApp;

if ( ! defined( 'ABSPATH' ) ) exit;

// Asegurarse de que la clase base de WordPress esté disponible
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Log_List_Table extends \WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'Entrada de Log',
            'plural'   => 'Entradas de Log',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'timestamp' => 'Fecha y Hora',
            'recipient' => 'Destinatario',
            'channel'   => 'Canal',
            'status'    => 'Estado',
            'message'   => 'Mensaje',
            'response'  => 'Respuesta API'
        ];
    }

    public function column_default( $item, $column_name ) {
        return esc_html( $item[ $column_name ] );
    }

    public function column_status( $item ) {
        if ( strtolower($item['status']) === 'success' ) {
            return '<span style="color: #227122; font-weight: bold;">ÉXITO</span>';
        }
        return '<span style="color: #d63638; font-weight: bold;">ERROR</span>';
    }
    
    public function column_message( $item ) {
        return '<div style="white-space: pre-wrap; max-width: 300px; word-wrap: break-word;">' . esc_html($item['message']) . '</div>';
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smsenlinea_logs';

        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page
        ] );

        $offset = ( $current_page - 1 ) * $per_page;
        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);
    }
}