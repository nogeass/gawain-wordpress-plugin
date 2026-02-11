<?php
/**
 * Gawain API Client.
 *
 * All outbound HTTP requests go through request().
 * Every public method checks Gawain_AI_Video::can_call_api() first so that
 * no remote call can happen unless the user has toggled consent ON.
 *
 * @package Gawain_AI_Video
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gawain_API {

    /** @var string */
    private $api_base;

    /** @var string */
    private $api_key;

    public function __construct() {
        $this->api_base = rtrim( Gawain_AI_Video::get_option( 'api_url', 'https://gawain.nogeass.com' ), '/' );
        $this->api_key  = Gawain_AI_Video::get_option( 'api_key', '' );
    }

    /**
     * Create a video generation job.
     *
     * Data sent to gawain.nogeass.com/api/v1/jobs:
     *  - installId  : site hostname (e.g. example.com)
     *  - product.id : WooCommerce product ID (integer as string)
     *  - product.title       : product name (max 80 chars, HTML stripped)
     *  - product.description : short description (max 200 chars, HTML stripped)
     *  - product.images[]    : single product image URL
     *  - product.price       : { amount, currency }
     *  - product.metadata    : { source: "wordpress", productType: "woocommerce" }
     *
     * @param int    $product_id  WooCommerce product ID.
     * @param string $title       Product title.
     * @param string $description Product description.
     * @param string $image_url   Full-size product image URL.
     * @param string $price       Product price (numeric string).
     * @return array API response or error array.
     */
    public function create_job( $product_id, $title, $description, $image_url, $price = null ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return array( 'error' => __( 'External processing is not enabled. Please enable it in settings.', 'gawain-ai-video' ) );
        }

        $body = array(
            'installId' => $this->get_site_id(),
            'product'   => array(
                'id'          => (string) absint( $product_id ),
                'title'       => mb_substr( wp_strip_all_tags( $title ), 0, 80 ),
                'description' => mb_substr( wp_strip_all_tags( $description ), 0, 200 ),
                'images'      => array( esc_url_raw( $image_url ) ),
                'metadata'    => array(
                    'source'      => 'wordpress',
                    'productType' => 'woocommerce',
                ),
            ),
        );

        if ( $price ) {
            $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'JPY';
            $body['product']['price'] = array(
                'amount'   => sanitize_text_field( (string) $price ),
                'currency' => sanitize_text_field( $currency ),
            );
        }

        return $this->request( 'POST', '/api/v1/jobs', $body );
    }

    /**
     * Get job status.
     *
     * @param string $job_id UUID returned by create_job.
     * @return array
     */
    public function get_job( $job_id ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return array( 'error' => __( 'External processing is not enabled.', 'gawain-ai-video' ) );
        }

        return $this->request( 'GET', '/api/v1/jobs/' . urlencode( sanitize_text_field( $job_id ) ) );
    }

    /**
     * Deploy video to storefront (saves to Gawain remote database).
     *
     * Data sent: site hostname, product ID, video URL, video ID, product title.
     *
     * @param int    $product_id    WooCommerce product ID.
     * @param string $video_url     CDN video URL.
     * @param string $video_id      Job/video UUID.
     * @param string $product_title Product name.
     * @return array
     */
    public function deploy_video( $product_id, $video_url, $video_id, $product_title = '' ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return array( 'error' => __( 'External processing is not enabled.', 'gawain-ai-video' ) );
        }

        return $this->request( 'POST', '/api/wordpress/deploy-video', array(
            'site'         => $this->get_site_id(),
            'productId'    => (string) absint( $product_id ),
            'videoUrl'     => esc_url_raw( $video_url ),
            'videoId'      => sanitize_text_field( $video_id ),
            'productTitle' => sanitize_text_field( $product_title ),
        ) );
    }

    /**
     * Undeploy video from storefront.
     *
     * @param string $video_id Job/video UUID.
     * @return array
     */
    public function undeploy_video( $video_id ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return array( 'error' => __( 'External processing is not enabled.', 'gawain-ai-video' ) );
        }

        return $this->request( 'POST', '/api/wordpress/undeploy-video', array(
            'site'    => $this->get_site_id(),
            'videoId' => sanitize_text_field( $video_id ),
        ) );
    }

    /**
     * Delete video record from remote database.
     *
     * @param string $video_id Job/video UUID.
     * @return array
     */
    public function delete_video( $video_id ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return array( 'error' => __( 'External processing is not enabled.', 'gawain-ai-video' ) );
        }

        return $this->request( 'POST', '/api/wordpress/delete-video', array(
            'site'    => $this->get_site_id(),
            'videoId' => sanitize_text_field( $video_id ),
        ) );
    }

    /**
     * Check existing videos for product IDs.
     *
     * @param int[] $product_ids Array of WooCommerce product IDs.
     * @return array
     */
    public function check_videos( $product_ids ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return array( 'error' => __( 'External processing is not enabled.', 'gawain-ai-video' ) );
        }

        $ids = implode( ',', array_map( 'absint', $product_ids ) );
        return $this->request(
            'GET',
            '/api/wordpress/check-videos?site=' . urlencode( $this->get_site_id() ) . '&productIds=' . urlencode( $ids )
        );
    }

    /**
     * Site identifier sent as installId / site field.
     *
     * @return string Hostname of the WordPress site.
     */
    private function get_site_id() {
        return sanitize_text_field( wp_parse_url( home_url(), PHP_URL_HOST ) );
    }

    /**
     * Perform an HTTP request to the Gawain API.
     *
     * Uses wp_remote_request (WP HTTP API) exclusively.
     * Never logs the full API key.
     *
     * @param string     $method   HTTP method.
     * @param string     $endpoint API path (appended to api_base).
     * @param array|null $body     Request body (JSON-encoded for non-GET).
     * @return array Decoded JSON response or error array.
     */
    private function request( $method, $endpoint, $body = null ) {
        $url = $this->api_base . $endpoint;

        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
        );

        if ( $this->api_key ) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->api_key;
        }

        if ( $body && 'GET' !== $method ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            return array(
                'error'  => isset( $data['message'] ) ? sanitize_text_field( $data['message'] ) : 'API error',
                'code'   => isset( $data['code'] ) ? sanitize_key( $data['code'] ) : 'UNKNOWN',
                'status' => (int) $code,
            );
        }

        return is_array( $data ) ? $data : array();
    }
}
