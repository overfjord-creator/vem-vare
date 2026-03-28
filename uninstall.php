<?php
/**
 * Vem vare? Uninstall
 * Removes all plugin data when uninstalled via WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop the table
$table_name = $wpdb->prefix . 'vem_vare_visitors';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Remove options
delete_option( 'vv_db_version' );
delete_option( 'vv_installed_at' );
delete_option( 'vv_settings' );

// Clean up transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_vv_geo_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_vv_geo_%'" );
