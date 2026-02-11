<?php
/**
 * Gawain API Client.
 *
 * Communicates with the Gawain video generation API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gawain_API {

    private $api_base;
    private $api_key;

    public function __construct() {
        $this->api_base = rtrim( Gawain_AI_Video::get_option( 'api_url', 'https://gawain.nogeass.com' ), '/' );
        $this->api_key  = Gawain_AI_Video::get_option( 'api_key', '' );
    }

    /**
     * Create a video generation job.
     */
    public function create_job( $product_id, $title, $description, $image_url, $price = null ) {
        $body = array(
            'installId' => $this->get_site_id(),
            'product'   => array(
                'id'          => (string) $product_id,
                'title'       => mb_substr( wp_strip_all_tags( $title ), 0, 80 ),
                'description' => mb_substr( wp_strip_all_tags( $description ), 0, 200 ),
                'images'      => array( $image_url ),
                'metadata'    => array(
                    'source'      => 'wordpress',
                    'productType' => 'woocommerce',
                ),
            ),
        );

        if ( $price ) {
            $body['product']['price'] = array(
                'amount'   => (string) $price,
                'currency' => get_woocommerce_currency(),
            );
        }

        return $this->request( 'POST', '/api/v1/jobs', $body );
    }

    /**
     * Get job status.
     */
    public function get_job( $job_id ) {
        return $this->request( 'GET', '/api/v1/jobs/' . urlencode( $job_id ) );
    }

    /**
     * Deploy video to storefront (saves to Gawain D1 database).
     */
    public function deploy_video( $product_id, $video_url, $video_id, $product_title = '' ) {
        return $this->request( 'POST', '/api/wordpress/deploy-video', array(
            'site'         => $this->get_site_id(),
            'productId'    => (string) $product_id,
            'videoUrl'     => $video_url,
            'videoId'      => $video_id,
            'productTitle' => $product_title,
        ) );
    }

    /**
     * Undeploy video from storefront.
     */
    public function undeploy_video( $video_id ) {
        return $this->request( 'POST', '/api/wordpress/undeploy-video', array(
            'site'    => $this->get_site_id(),
            'videoId' => $video_id,
        ) );
    }

    /**
     * Delete video record.
     */
    public function delete_video( $video_id ) {
        return $this->request( 'POST', '/api/wordpress/delete-video', array(
            'site'    => $this->get_site_id(),
            'videoId' => $video_id,
        ) );
    }

    /**
     * Check existing videos for product IDs.
     */
    public function check_videos( $product_ids ) {
        $ids = implode( ',', array_map( 'intval', $product_ids ) );
        return $this->request( 'GET', '/api/wordpress/check-videos?site=' . urlencode( $this->get_site_id() ) . '&productIds=' . urlencode( $ids ) );
    }

    /**
     * Get site identifier (domain without protocol).
     */
    private function get_site_id() {
        return wp_parse_url( home_url(), PHP_URL_HOST );
    }

    /**
     * Make an HTTP request to the Gawain API.
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

        if ( $body && $method !== 'GET' ) {
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
                'error'   => isset( $data['message'] ) ? $data['message'] : 'API error',
                'code'    => isset( $data['code'] ) ? $data['code'] : 'UNKNOWN',
                'status'  => $code,
            );
        }

        return $data ? $data : array();
    }
}
