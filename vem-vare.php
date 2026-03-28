<?php
/**
 * Plugin Name: Vem vare?
 * Plugin URI: https://example.com/vem-vare
 * Description: Identifiera dina webbplatsbesökare med IP-spårning, Reverse DNS, geolokalisering och kommentarer. Inspirerat av Leadinfo.
 * Version: 1.1.0
 * Author: Vem vare? Team
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vem-vare
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'VV_VERSION', '1.1.0' );
define( 'VV_DB_VERSION', '1.0.0' );
define( 'VV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class VemVare {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once VV_PLUGIN_DIR . 'includes/class-vv-database.php';
        require_once VV_PLUGIN_DIR . 'includes/class-vv-tracker.php';
        require_once VV_PLUGIN_DIR . 'includes/class-vv-admin.php';
        require_once VV_PLUGIN_DIR . 'includes/class-vv-ajax.php';
    }

    private function init_hooks() {
        register_activation_hook( __FILE__, array( 'VV_Database', 'activate' ) );
        register_deactivation_hook( __FILE__, array( 'VV_Database', 'deactivate' ) );

        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'plugins_loaded', array( $this, 'check_db_version' ) );
        add_action( 'wp', array( 'VV_Tracker', 'track_visitor' ) );
        add_action( 'admin_menu', array( 'VV_Admin', 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( 'VV_Admin', 'enqueue_assets' ) );

        // AJAX hooks
        add_action( 'wp_ajax_vv_get_visitors', array( 'VV_Ajax', 'get_visitors' ) );
        add_action( 'wp_ajax_vv_save_comment', array( 'VV_Ajax', 'save_comment' ) );
        add_action( 'wp_ajax_vv_delete_visitor', array( 'VV_Ajax', 'delete_visitor' ) );
        add_action( 'wp_ajax_vv_export_csv', array( 'VV_Ajax', 'export_csv' ) );
        add_action( 'wp_ajax_vv_get_stats', array( 'VV_Ajax', 'get_stats' ) );
        add_action( 'wp_ajax_vv_get_country_stats', array( 'VV_Ajax', 'get_country_stats' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'vem-vare',
            false,
            dirname( VV_PLUGIN_BASENAME ) . '/languages'
        );
    }

    public function check_db_version() {
        $installed_version = get_option( 'vv_db_version', '0' );
        if ( version_compare( $installed_version, VV_DB_VERSION, '<' ) ) {
            VV_Database::activate();
        }
    }
}

// Initialize
function vem_vare() {
    return VemVare::instance();
}
vem_vare();
