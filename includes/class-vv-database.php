<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class VV_Database {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'vem_vare_visitors';
    }

    public static function activate() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            reverse_dns varchar(255) DEFAULT '',
            city varchar(100) DEFAULT '',
            region varchar(100) DEFAULT '',
            country varchar(100) DEFAULT '',
            country_code varchar(10) DEFAULT '',
            isp varchar(255) DEFAULT '',
            org varchar(255) DEFAULT '',
            user_agent text DEFAULT '',
            page_visited text DEFAULT '',
            referer text DEFAULT '',
            comment text DEFAULT '',
            visit_count int(11) DEFAULT 1,
            first_visit datetime DEFAULT CURRENT_TIMESTAMP,
            last_visit datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ip (ip_address),
            KEY idx_last_visit (last_visit),
            KEY idx_country (country_code)
        ) {$charset_collate};";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'vv_db_version', VV_DB_VERSION );
        update_option( 'vv_installed_at', current_time( 'mysql' ) );
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'vv_cleanup_old_records' );
    }

    public static function uninstall() {
        global $wpdb;
        $table_name = self::get_table_name();
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
        delete_option( 'vv_db_version' );
        delete_option( 'vv_installed_at' );
        delete_option( 'vv_settings' );
    }
}
