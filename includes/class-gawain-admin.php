<?php
/**
 * Admin page for Gawain AI Video.
 *
 * Adds a WooCommerce submenu page with product listing, video generation controls,
 * and settings — matching the Shopify embedded app experience.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gawain_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            'Gawain AI 動画',
            'Gawain AI 動画',
            'manage_woocommerce',
            'gawain-ai-video',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'woocommerce_page_gawain-ai-video' !== $hook ) {
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

        // Pass data to JS
        wp_localize_script( 'gawain-admin', 'gawainData', array(
            'restUrl' => esc_url_raw( rest_url( 'gawain/v1/' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ) );
    }

    public function register_settings() {
        register_setting( 'gawain_settings_group', 'gawain_settings', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );
    }

    public function sanitize_settings( $input ) {
        $clean = array();
        if ( isset( $input['api_key'] ) ) {
            $clean['api_key'] = sanitize_text_field( $input['api_key'] );
        }
        if ( isset( $input['api_url'] ) ) {
            $clean['api_url'] = esc_url_raw( $input['api_url'] );
        }
        return $clean;
    }

    public function render_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'videos';
        ?>
        <div class="wrap gawain-wrap">
            <div class="gawain-header">
                <h1 class="gawain-title">Gawain AI プロモーション動画生成</h1>
                <p class="gawain-subtitle">商品のプロモーション動画をAIで生成できます。</p>
            </div>

            <nav class="nav-tab-wrapper gawain-tabs">
                <a href="?page=gawain-ai-video&tab=videos"
                   class="nav-tab <?php echo 'videos' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    動画管理
                </a>
                <a href="?page=gawain-ai-video&tab=settings"
                   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    設定
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
        ?>
        <form method="post" action="options.php" class="gawain-settings-form">
            <?php settings_fields( 'gawain_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="gawain_api_key">API Key</label></th>
                    <td>
                        <input type="password" id="gawain_api_key" name="gawain_settings[api_key]"
                               value="<?php echo esc_attr( isset( $options['api_key'] ) ? $options['api_key'] : '' ); ?>"
                               class="regular-text" placeholder="gawain_live_..." />
                        <p class="description">
                            <a href="https://gawain.nogeass.com" target="_blank" rel="noopener">gawain.nogeass.com</a> でAPIキーを取得できます。
                            未設定でも無料プレビュー（ウォーターマーク付き）が利用可能です。
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gawain_api_url">API URL</label></th>
                    <td>
                        <input type="url" id="gawain_api_url" name="gawain_settings[api_url]"
                               value="<?php echo esc_attr( isset( $options['api_url'] ) ? $options['api_url'] : 'https://gawain.nogeass.com' ); ?>"
                               class="regular-text" />
                        <p class="description">通常は変更不要です。</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( '設定を保存' ); ?>
        </form>
        <?php
    }

    private function render_videos_tab() {
        // Check WooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="notice notice-error"><p>WooCommerceがインストールされていません。</p></div>';
            return;
        }

        // Check API key
        $api_key = Gawain_AI_Video::get_option( 'api_key' );
        if ( ! $api_key ) {
            echo '<div class="notice notice-warning"><p>APIキーが未設定です。<a href="?page=gawain-ai-video&tab=settings">設定</a>からAPIキーを入力してください。未設定でも無料プレビューは利用可能です。</p></div>';
        }

        // Get WooCommerce products
        $products = wc_get_products( array(
            'status' => 'publish',
            'limit'  => 50,
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );

        ?>
        <div id="gawain-app"
             data-products='<?php echo esc_attr( wp_json_encode( $this->products_to_json( $products ) ) ); ?>'>

            <!-- Section 1: 商品一覧 -->
            <section class="gawain-section">
                <h2 class="gawain-section-title">商品一覧</h2>
                <div class="gawain-scroll-container">
                    <div class="gawain-product-grid" id="gawain-products">
                        <!-- Rendered by JS -->
                    </div>
                </div>
            </section>

            <!-- Section 2: 生成した動画 -->
            <section class="gawain-section" id="gawain-videos-section" style="display:none">
                <h2 class="gawain-section-title">生成した動画</h2>
                <div class="gawain-scroll-container">
                    <div class="gawain-video-grid" id="gawain-videos">
                        <!-- Rendered by JS -->
                    </div>
                </div>
            </section>
        </div>

        <div id="gawain-toast-container"></div>
        <?php
    }

    private function products_to_json( $products ) {
        $data = array();
        foreach ( $products as $product ) {
            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
            $full_url  = $image_id ? wp_get_attachment_url( $image_id ) : '';

            $data[] = array(
                'id'          => (string) $product->get_id(),
                'title'       => $product->get_name(),
                'description' => $product->get_short_description() ?: $product->get_description(),
                'image'       => $full_url ?: null,
                'thumb'       => $image_url ?: null,
                'price'       => $product->get_price(),
            );
        }
        return $data;
    }
}
