<?php
/**
 * Admin page for Gawain AI Video.
 *
 * - WooCommerce submenu page (動画管理 + 設定).
 * - Consent toggle for external API calls (default OFF).
 * - API key masked in HTML — never printed in full.
 * - Graceful notice when WooCommerce is not active.
 *
 * @package Gawain_AI_Video
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gawain_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
    }

    /**
     * Show a persistent admin notice when WooCommerce is not active.
     */
    public function woocommerce_missing_notice() {
        if ( class_exists( 'WooCommerce' ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html__( 'Gawain AI Video', 'gawain-ai-video' ); ?>:</strong>
                <?php echo esc_html__( 'WooCommerce is required but not active. Please install and activate WooCommerce.', 'gawain-ai-video' ); ?>
            </p>
        </div>
        <?php
    }

    public function add_menu() {
        // Register under WooCommerce when available, else under Tools.
        $parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'tools.php';
        add_submenu_page(
            $parent,
            esc_html__( 'Gawain AI Video', 'gawain-ai-video' ),
            esc_html__( 'Gawain AI Video', 'gawain-ai-video' ),
            'manage_woocommerce',
            'gawain-ai-video',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'woocommerce_page_gawain-ai-video' !== $hook && 'tools_page_gawain-ai-video' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'gawain-admin',
            GAWAIN_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GAWAIN_VERSION
        );

        wp_enqueue_script(
            'gawain-admin',
            GAWAIN_PLUGIN_URL . 'assets/js/admin.js',
            array(),
            GAWAIN_VERSION,
            true
        );

        wp_localize_script( 'gawain-admin', 'gawainData', array(
            'restUrl'    => esc_url_raw( rest_url( 'gawain/v1/' ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'hasConsent' => Gawain_AI_Video::has_consent(),
        ) );
    }

    public function register_settings() {
        register_setting( 'gawain_settings_group', 'gawain_settings', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );
    }

    /**
     * Sanitize settings on save.
     *
     * @param array $input Raw form input.
     * @return array Sanitized values.
     */
    public function sanitize_settings( $input ) {
        $existing = get_option( 'gawain_settings', array() );
        $clean    = array();

        // API key — keep existing if placeholder submitted.
        if ( isset( $input['api_key'] ) ) {
            $raw = sanitize_text_field( $input['api_key'] );
            if ( '' === $raw || preg_match( '/^\*+$/', $raw ) ) {
                // User didn't change the key; keep what we had.
                $clean['api_key'] = isset( $existing['api_key'] ) ? $existing['api_key'] : '';
            } else {
                $clean['api_key'] = $raw;
            }
        }

        if ( isset( $input['api_url'] ) ) {
            $clean['api_url'] = esc_url_raw( $input['api_url'] );
        }

        // Consent toggle — checkbox: present = '1', absent = not in $input.
        $clean['external_consent'] = ! empty( $input['external_consent'] ) ? '1' : '';

        // Delete data on uninstall toggle.
        $clean['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] ) ? '1' : '';

        return $clean;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'gawain-ai-video' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'videos';
        ?>
        <div class="wrap gawain-wrap">
            <div class="gawain-header">
                <h1 class="gawain-title"><?php echo esc_html__( 'Gawain AI Video', 'gawain-ai-video' ); ?></h1>
                <p class="gawain-subtitle"><?php echo esc_html__( 'Generate AI promotional videos for your WooCommerce products.', 'gawain-ai-video' ); ?></p>
            </div>

            <nav class="nav-tab-wrapper gawain-tabs">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=gawain-ai-video&tab=videos' ) ); ?>"
                   class="nav-tab <?php echo 'videos' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Videos', 'gawain-ai-video' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=gawain-ai-video&tab=settings' ) ); ?>"
                   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Settings', 'gawain-ai-video' ); ?>
                </a>
            </nav>

            <?php if ( 'settings' === $active_tab ) : ?>
                <?php $this->render_settings(); ?>
            <?php else : ?>
                <?php $this->render_videos_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_settings() {
        $options = get_option( 'gawain_settings', array() );
        $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $api_url = isset( $options['api_url'] ) ? $options['api_url'] : 'https://gawain.nogeass.com';
        $consent = ! empty( $options['external_consent'] );
        $delete  = ! empty( $options['delete_on_uninstall'] );

        // Masked key for display — never expose full key in HTML.
        $masked_key = '';
        if ( $api_key ) {
            $len = strlen( $api_key );
            if ( $len > 8 ) {
                $masked_key = substr( $api_key, 0, 4 ) . str_repeat( '*', $len - 8 ) . substr( $api_key, -4 );
            } else {
                $masked_key = str_repeat( '*', $len );
            }
        }
        ?>
        <form method="post" action="options.php" class="gawain-settings-form">
            <?php settings_fields( 'gawain_settings_group' ); ?>

            <h2><?php echo esc_html__( 'External Service', 'gawain-ai-video' ); ?></h2>
            <div class="notice notice-info inline" style="margin:0 0 16px;padding:12px">
                <p style="margin:0">
                    <?php
                    printf(
                        /* translators: %s: service domain */
                        esc_html__( 'This plugin sends product data to %s for AI video generation. No data is sent until you enable the toggle below and explicitly click "Generate".', 'gawain-ai-video' ),
                        '<code>gawain.nogeass.com</code>'
                    );
                    ?>
                </p>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Enable external processing', 'gawain-ai-video' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gawain_settings[external_consent]" value="1"
                                <?php checked( $consent ); ?> />
                            <?php echo esc_html__( 'I consent to sending product data to gawain.nogeass.com for video generation', 'gawain-ai-video' ); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__( 'When OFF, no data is sent to any external server. Default: OFF.', 'gawain-ai-video' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gawain_api_key"><?php echo esc_html__( 'API Key', 'gawain-ai-video' ); ?></label></th>
                    <td>
                        <input type="password" id="gawain_api_key" name="gawain_settings[api_key]"
                               value="<?php echo esc_attr( $masked_key ); ?>"
                               class="regular-text" placeholder="gawain_live_..."
                               autocomplete="off" />
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: link to service */
                                esc_html__( 'Get your API key at %s. Optional — free preview (watermarked) works without a key.', 'gawain-ai-video' ),
                                '<a href="https://gawain.nogeass.com" target="_blank" rel="noopener noreferrer">gawain.nogeass.com</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gawain_api_url"><?php echo esc_html__( 'API URL', 'gawain-ai-video' ); ?></label></th>
                    <td>
                        <input type="url" id="gawain_api_url" name="gawain_settings[api_url]"
                               value="<?php echo esc_attr( $api_url ); ?>"
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__( 'Default value is correct for most users.', 'gawain-ai-video' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__( 'Data Management', 'gawain-ai-video' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Delete data on uninstall', 'gawain-ai-video' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gawain_settings[delete_on_uninstall]" value="1"
                                <?php checked( $delete ); ?> />
                            <?php echo esc_html__( 'Remove all plugin settings and the video tracking table when the plugin is deleted', 'gawain-ai-video' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button( esc_html__( 'Save Settings', 'gawain-ai-video' ) ); ?>
        </form>
        <?php
    }

    private function render_videos_tab() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce is required. Please install and activate WooCommerce.', 'gawain-ai-video' ) . '</p></div>';
            return;
        }

        if ( ! Gawain_AI_Video::has_consent() ) {
            echo '<div class="notice notice-warning"><p>';
            printf(
                /* translators: %s: link to settings */
                esc_html__( 'External processing is disabled. Enable it in %s to generate videos.', 'gawain-ai-video' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=gawain-ai-video&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'gawain-ai-video' ) . '</a>'
            );
            echo '</p></div>';
        }

        $products = wc_get_products( array(
            'status'  => 'publish',
            'limit'   => 50,
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );

        ?>
        <div id="gawain-app"
             data-products="<?php echo esc_attr( wp_json_encode( $this->products_to_json( $products ) ) ); ?>"
             data-consent="<?php echo esc_attr( Gawain_AI_Video::has_consent() ? '1' : '' ); ?>">

            <section class="gawain-section">
                <h2 class="gawain-section-title"><?php echo esc_html__( 'Products', 'gawain-ai-video' ); ?></h2>
                <div class="gawain-scroll-container">
                    <div class="gawain-product-grid" id="gawain-products"></div>
                </div>
            </section>

            <section class="gawain-section" id="gawain-videos-section" style="display:none">
                <h2 class="gawain-section-title"><?php echo esc_html__( 'Generated Videos', 'gawain-ai-video' ); ?></h2>
                <div class="gawain-scroll-container">
                    <div class="gawain-video-grid" id="gawain-videos"></div>
                </div>
            </section>
        </div>

        <div id="gawain-toast-container"></div>
        <?php
    }

    /**
     * Convert WC_Product objects to a safe JSON-serializable array.
     *
     * @param WC_Product[] $products WooCommerce product objects.
     * @return array
     */
    private function products_to_json( $products ) {
        $data = array();
        foreach ( $products as $product ) {
            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
            $full_url  = $image_id ? wp_get_attachment_url( $image_id ) : '';

            $data[] = array(
                'id'    => (string) $product->get_id(),
                'title' => $product->get_name(),
                'image' => $full_url ? esc_url( $full_url ) : null,
                'thumb' => $image_url ? esc_url( $image_url ) : null,
                'price' => $product->get_price(),
            );
        }
        return $data;
    }
}
