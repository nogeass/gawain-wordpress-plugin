<?php
/**
 * Plugin Name: Gawain AI Video
 * Plugin URI: https://github.com/nogeass/gawain-wordpress-plugin
 * Description: AI-powered promotional video generation for WooCommerce products.
 * Version: 0.1.0
 * Author: nogeass
 * Author URI: https://nogeass.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gawain-ai-video
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GAWAIN_VERSION', '0.1.0' );
define( 'GAWAIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GAWAIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GAWAIN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once GAWAIN_PLUGIN_DIR . 'includes/class-gawain-api.php';
require_once GAWAIN_PLUGIN_DIR . 'includes/class-gawain-admin.php';
require_once GAWAIN_PLUGIN_DIR . 'includes/class-gawain-rest.php';
require_once GAWAIN_PLUGIN_DIR . 'includes/class-gawain-storefront.php';

/**
 * Main plugin class.
 */
final class Gawain_AI_Video {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
    }

    public function init() {
        // Always load admin (shows WooCommerce-missing notice if needed).
        new Gawain_Admin();

        // REST + Storefront only when WooCommerce is active.
        if ( class_exists( 'WooCommerce' ) ) {
            new Gawain_REST();
            new Gawain_Storefront();
        }
    }

    /**
     * Activation — create DB table.  No remote calls here.
     */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'gawain_videos';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            job_id VARCHAR(255) NOT NULL,
            video_url TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            deployed TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_product_id (product_id),
            KEY idx_job_id (job_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get a single plugin option.
     *
     * @param string $key     Option key inside the gawain_settings array.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function get_option( $key, $default = '' ) {
        $options = get_option( 'gawain_settings', array() );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    /**
     * Whether the user has explicitly granted consent for external API calls.
     *
     * @return bool
     */
    public static function has_consent() {
        return (bool) self::get_option( 'external_consent', false );
    }

    /**
     * Whether external calls are allowed (consent ON).
     * This does NOT require an API key — free-tier works without one.
     *
     * @return bool
     */
    public static function can_call_api() {
        return self::has_consent();
    }
}

Gawain_AI_Video::instance();
