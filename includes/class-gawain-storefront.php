<?php
/**
 * Storefront video carousel for WooCommerce product pages.
 *
 * Displays deployed videos via:
 * 1. WooCommerce hook (automatic on product pages)
 * 2. [gawain_videos] shortcode (manual placement)
 *
 * The storefront JS fetches videos from the external API (gawain.nogeass.com)
 * to display on product pages. This only happens when consent is enabled.
 *
 * @package Gawain_AI_Video
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gawain_Storefront {

    public function __construct() {
        add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_product_videos' ), 15 );
        add_shortcode( 'gawain_videos', array( $this, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
    }

    /**
     * Enqueue storefront assets only on product pages or pages with shortcode,
     * and only when external consent is enabled.
     */
    public function maybe_enqueue() {
        if ( ! Gawain_AI_Video::has_consent() ) {
            return;
        }

        if ( is_product() || $this->page_has_shortcode() ) {
            wp_enqueue_style(
                'gawain-storefront',
                GAWAIN_PLUGIN_URL . 'assets/css/storefront.css',
                array(),
                GAWAIN_VERSION
            );
            wp_enqueue_script(
                'gawain-storefront',
                GAWAIN_PLUGIN_URL . 'assets/js/storefront.js',
                array(),
                GAWAIN_VERSION,
                true
            );
            wp_localize_script( 'gawain-storefront', 'gawainStorefront', array(
                'apiBase' => esc_url_raw( rtrim( Gawain_AI_Video::get_option( 'api_url', 'https://gawain.nogeass.com' ), '/' ) ),
                'site'    => sanitize_text_field( wp_parse_url( home_url(), PHP_URL_HOST ) ),
            ) );
        }
    }

    /**
     * WooCommerce hook — auto-render on product pages.
     */
    public function render_product_videos() {
        if ( ! Gawain_AI_Video::has_consent() ) {
            return;
        }

        global $product;
        if ( ! $product ) {
            return;
        }
        echo '<div class="gawain-video-section" data-product-id="' . esc_attr( $product->get_id() ) . '"></div>';
    }

    /**
     * Shortcode [gawain_videos product_id="123"].
     */
    public function shortcode( $atts ) {
        if ( ! Gawain_AI_Video::has_consent() ) {
            return '';
        }

        $atts = shortcode_atts( array(
            'product_id' => '',
        ), $atts, 'gawain_videos' );

        $product_id = absint( $atts['product_id'] );
        if ( ! $product_id && is_product() ) {
            global $product;
            $product_id = $product ? $product->get_id() : 0;
        }

        if ( ! $product_id ) {
            return '';
        }

        return '<div class="gawain-video-section" data-product-id="' . esc_attr( $product_id ) . '"></div>';
    }

    private function page_has_shortcode() {
        global $post;
        return $post && has_shortcode( $post->post_content, 'gawain_videos' );
    }
}
