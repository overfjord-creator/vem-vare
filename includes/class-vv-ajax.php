<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class VV_Ajax {

    private static function verify() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Ej behörig', 'vem-vare' ) );
        }
        if ( ! check_ajax_referer( 'vv_nonce', 'nonce', false ) ) {
            wp_send_json_error( __( 'Ogiltig nonce', 'vem-vare' ) );
        }
    }

    /**
     * Get visitors with filtering, sorting, pagination
     */
    public static function get_visitors() {
        self::verify();
        global $wpdb;
        $table = VV_Database::get_table_name();

        $page     = absint( $_POST['page'] ?? 1 );
        $per_page = absint( $_POST['per_page'] ?? 25 );
        $search   = sanitize_text_field( $_POST['search'] ?? '' );
        $country  = sanitize_text_field( $_POST['country'] ?? '' );
        $period   = sanitize_text_field( $_POST['period'] ?? '' );
        $comment  = sanitize_text_field( $_POST['comment_filter'] ?? '' );
        $sort     = sanitize_text_field( $_POST['sort'] ?? 'last_visit' );
        $order    = 'DESC';

        $allowed_sort = array( 'last_visit', 'first_visit', 'visit_count', 'ip_address' );
        if ( ! in_array( $sort, $allowed_sort ) ) {
            $sort = 'last_visit';
        }
        if ( $sort === 'ip_address' ) {
            $order = 'ASC';
        }

        $where = array( '1=1' );
        $params = array();

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = '(ip_address LIKE %s OR reverse_dns LIKE %s OR city LIKE %s OR country LIKE %s OR org LIKE %s OR comment LIKE %s)';
            $params = array_merge( $params, array( $like, $like, $like, $like, $like, $like ) );
        }

        if ( $country ) {
            $where[] = 'country_code = %s';
            $params[] = $country;
        }

        if ( $period === 'today' ) {
            $where[] = 'DATE(last_visit) = CURDATE()';
        } elseif ( $period === 'week' ) {
            $where[] = 'last_visit >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif ( $period === 'month' ) {
            $where[] = 'last_visit >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }

        if ( $comment === 'with' ) {
            $where[] = "comment != ''";
        } elseif ( $comment === 'without' ) {
            $where[] = "(comment = '' OR comment IS NULL)";
        }

        $where_clause = implode( ' AND ', $where );

        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if ( $params ) {
            $total = $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );
        } else {
            $total = $wpdb->get_var( $count_sql );
        }

        $offset = ( $page - 1 ) * $per_page;

        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$sort} {$order} LIMIT %d OFFSET %d";
        $all_params = array_merge( $params, array( $per_page, $offset ) );
        $visitors = $wpdb->get_results( $wpdb->prepare( $query, ...$all_params ), ARRAY_A );

        // Get country list with visitor counts for filter dropdown
        $countries = $wpdb->get_results(
            "SELECT country_code, country, COUNT(*) as visitor_count
             FROM {$table}
             WHERE country_code != ''
             GROUP BY country_code, country
             ORDER BY visitor_count DESC",
            ARRAY_A
        );

        wp_send_json_success( array(
            'visitors'   => $visitors,
            'total'      => (int) $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'pages'      => ceil( $total / $per_page ),
            'countries'  => $countries,
        ) );
    }

    /**
     * Save a comment for a visitor
     */
    public static function save_comment() {
        self::verify();
        global $wpdb;
        $table = VV_Database::get_table_name();

        $id      = absint( $_POST['visitor_id'] ?? 0 );
        $comment = sanitize_textarea_field( $_POST['comment'] ?? '' );

        if ( ! $id ) {
            wp_send_json_error( __( 'Ogiltigt ID', 'vem-vare' ) );
        }

        $updated = $wpdb->update(
            $table,
            array( 'comment' => $comment ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $updated !== false ) {
            wp_send_json_success( array( 'message' => __( 'Kommentar sparad', 'vem-vare' ) ) );
        }

        wp_send_json_error( __( 'Kunde inte spara kommentar', 'vem-vare' ) );
    }

    /**
     * Delete a visitor record
     */
    public static function delete_visitor() {
        self::verify();
        global $wpdb;
        $table = VV_Database::get_table_name();

        $id = absint( $_POST['visitor_id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( __( 'Ogiltigt ID', 'vem-vare' ) );
        }

        $deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        if ( $deleted ) {
            wp_send_json_success( array( 'message' => __( 'Besökare borttagen', 'vem-vare' ) ) );
        }

        wp_send_json_error( __( 'Kunde inte ta bort', 'vem-vare' ) );
    }

    /**
     * Export CSV
     */
    public static function export_csv() {
        self::verify();
        global $wpdb;
        $table = VV_Database::get_table_name();

        $visitors = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY last_visit DESC", ARRAY_A );

        $headers = array(
            __( 'IP-adress', 'vem-vare' ),
            __( 'Reverse DNS', 'vem-vare' ),
            __( 'Organisation', 'vem-vare' ),
            __( 'Stad', 'vem-vare' ),
            __( 'Land', 'vem-vare' ),
            __( 'Besök', 'vem-vare' ),
            __( 'Första besök', 'vem-vare' ),
            __( 'Senaste besök', 'vem-vare' ),
            __( 'Kommentar', 'vem-vare' ),
        );

        $csv = implode( ',', $headers ) . "\n";
        foreach ( $visitors as $v ) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s",%d,"%s","%s","%s"' . "\n",
                $v['ip_address'],
                $v['reverse_dns'],
                $v['org'],
                $v['city'],
                $v['country'],
                $v['visit_count'],
                $v['first_visit'],
                $v['last_visit'],
                str_replace( '"', '""', $v['comment'] )
            );
        }

        wp_send_json_success( array( 'csv' => $csv ) );
    }

    /**
     * Get dashboard stats
     */
    public static function get_stats() {
        self::verify();
        global $wpdb;
        $table = VV_Database::get_table_name();

        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $today = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE DATE(last_visit) = CURDATE()" );
        $countries = $wpdb->get_var( "SELECT COUNT(DISTINCT country_code) FROM {$table} WHERE country_code != ''" );
        $comments = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE comment != '' AND comment IS NOT NULL" );

        wp_send_json_success( array(
            'total'     => (int) $total,
            'today'     => (int) $today,
            'countries' => (int) $countries,
            'comments'  => (int) $comments,
        ) );
    }

    /**
     * Get country statistics — visitors per country, sorted by count
     */
    public static function get_country_stats() {
        self::verify();
        global $wpdb;
        $table = VV_Database::get_table_name();

        $countries = $wpdb->get_results(
            "SELECT country_code, country, COUNT(*) as visitor_count,
                    SUM(visit_count) as total_visits,
                    MAX(last_visit) as latest_visit
             FROM {$table}
             WHERE country_code != ''
             GROUP BY country_code, country
             ORDER BY visitor_count DESC",
            ARRAY_A
        );

        $total_visitors = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        wp_send_json_success( array(
            'countries'      => $countries,
            'total_visitors' => (int) $total_visitors,
        ) );
    }
}
