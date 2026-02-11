<?php
/**
 * WordPress REST API endpoints for admin AJAX calls.
 *
 * All endpoints that touch the external API check Gawain_AI_Video::can_call_api().
 * Permission callback requires manage_woocommerce capability.
 * WP REST nonce is verified automatically by the REST API infrastructure.
 *
 * @package Gawain_AI_Video
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gawain_REST {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $ns = 'gawain/v1';

        register_rest_route( $ns, '/generate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'generate_video' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'product_id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ( $val ) {
                        return absint( $val ) > 0;
                    },
                ),
            ),
        ) );

        register_rest_route( $ns, '/job/(?P<job_id>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_job_status' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'job_id' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $ns, '/deploy', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'deploy_video' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'videoId' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $ns, '/undeploy', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'undeploy_video' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'videoId' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $ns, '/delete', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'delete_video' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'videoId' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $ns, '/videos', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_videos' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'product_ids' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Permission callback — requires manage_woocommerce.
     *
     * @return bool
     */
    public function check_permission() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * POST /wp-json/gawain/v1/generate
     */
    public function generate_video( $request ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return new WP_Error( 'consent_required', __( 'External processing is not enabled.', 'gawain-ai-video' ), array( 'status' => 403 ) );
        }

        $product_id = absint( $request->get_param( 'product_id' ) );

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'not_found', __( 'Product not found.', 'gawain-ai-video' ), array( 'status' => 404 ) );
        }

        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';
        if ( ! $image_url ) {
            return new WP_Error( 'no_image', __( 'Product has no image.', 'gawain-ai-video' ), array( 'status' => 400 ) );
        }

        $api    = new Gawain_API();
        $result = $api->create_job(
            $product_id,
            $product->get_name(),
            $product->get_short_description() ?: $product->get_description(),
            $image_url,
            $product->get_price()
        );

        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'api_error', sanitize_text_field( $result['error'] ), array( 'status' => 502 ) );
        }

        if ( ! isset( $result['jobId'] ) ) {
            return new WP_Error( 'api_error', __( 'Unexpected API response.', 'gawain-ai-video' ), array( 'status' => 502 ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gawain_videos';
        $wpdb->insert( $table, array(
            'product_id' => $product_id,
            'job_id'     => sanitize_text_field( $result['jobId'] ),
            'status'     => 'pending',
        ), array( '%d', '%s', '%s' ) );

        return rest_ensure_response( array(
            'jobId'        => sanitize_text_field( $result['jobId'] ),
            'productId'    => $product_id,
            'productTitle' => $product->get_name(),
            'status'       => 'pending',
            'progress'     => 5,
        ) );
    }

    /**
     * GET /wp-json/gawain/v1/job/{job_id}
     */
    public function get_job_status( $request ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return new WP_Error( 'consent_required', __( 'External processing is not enabled.', 'gawain-ai-video' ), array( 'status' => 403 ) );
        }

        $job_id = sanitize_text_field( $request->get_param( 'job_id' ) );

        $api    = new Gawain_API();
        $result = $api->get_job( $job_id );

        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'api_error', sanitize_text_field( $result['error'] ), array( 'status' => 502 ) );
        }

        if ( isset( $result['status'] ) ) {
            global $wpdb;
            $table  = $wpdb->prefix . 'gawain_videos';
            $update = array( 'status' => sanitize_key( $result['status'] ) );
            if ( isset( $result['previewUrl'] ) ) {
                $update['video_url'] = esc_url_raw( $result['previewUrl'] );
            }
            $wpdb->update( $table, $update, array( 'job_id' => $job_id ), null, array( '%s' ) );
        }

        return rest_ensure_response( $result );
    }

    /**
     * POST /wp-json/gawain/v1/deploy
     */
    public function deploy_video( $request ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return new WP_Error( 'consent_required', __( 'External processing is not enabled.', 'gawain-ai-video' ), array( 'status' => 403 ) );
        }

        $job_id = sanitize_text_field( $request->get_param( 'videoId' ) );

        global $wpdb;
        $table = $wpdb->prefix . 'gawain_videos';
        $row   = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %s LIMIT 1", $job_id
        ) );

        if ( ! $row || ! $row->video_url ) {
            return new WP_Error( 'not_found', __( 'Video not found or not ready.', 'gawain-ai-video' ), array( 'status' => 404 ) );
        }

        $product = wc_get_product( $row->product_id );
        $title   = $product ? $product->get_name() : '';

        $api    = new Gawain_API();
        $result = $api->deploy_video( $row->product_id, $row->video_url, $job_id, $title );

        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'api_error', sanitize_text_field( $result['error'] ), array( 'status' => 502 ) );
        }

        $wpdb->update( $table, array( 'deployed' => 1 ), array( 'job_id' => $job_id ), array( '%d' ), array( '%s' ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * POST /wp-json/gawain/v1/undeploy
     */
    public function undeploy_video( $request ) {
        if ( ! Gawain_AI_Video::can_call_api() ) {
            return new WP_Error( 'consent_required', __( 'External processing is not enabled.', 'gawain-ai-video' ), array( 'status' => 403 ) );
        }

        $job_id = sanitize_text_field( $request->get_param( 'videoId' ) );

        $api    = new Gawain_API();
        $result = $api->undeploy_video( $job_id );

        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'api_error', sanitize_text_field( $result['error'] ), array( 'status' => 502 ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gawain_videos';
        $wpdb->update( $table, array( 'deployed' => 0 ), array( 'job_id' => $job_id ), array( '%d' ), array( '%s' ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * POST /wp-json/gawain/v1/delete
     */
    public function delete_video( $request ) {
        $job_id = sanitize_text_field( $request->get_param( 'videoId' ) );

        // Try remote delete only if consent is on (best-effort).
        if ( Gawain_AI_Video::can_call_api() ) {
            $api = new Gawain_API();
            $api->delete_video( $job_id );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gawain_videos';
        $wpdb->delete( $table, array( 'job_id' => $job_id ), array( '%s' ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * GET /wp-json/gawain/v1/videos?product_ids=1,2,3
     *
     * Local-only query — no external API call.
     */
    public function get_videos( $request ) {
        $product_ids = sanitize_text_field( $request->get_param( 'product_ids' ) );

        global $wpdb;
        $table = $wpdb->prefix . 'gawain_videos';

        if ( $product_ids ) {
            $ids          = array_map( 'absint', explode( ',', $product_ids ) );
            $ids          = array_filter( $ids );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built from count.
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id IN ({$placeholders}) ORDER BY created_at DESC",
                ...$ids
            ) );
        } else {
            $rows = $wpdb->get_results(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100"
            );
        }

        $videos = array();
        foreach ( (array) $rows as $row ) {
            $videos[] = array(
                'jobId'     => $row->job_id,
                'productId' => (string) $row->product_id,
                'status'    => $row->status,
                'progress'  => $this->status_to_progress( $row->status ),
                'videoUrl'  => $row->video_url,
                'deployed'  => (bool) $row->deployed,
            );
        }

        return rest_ensure_response( array( 'success' => true, 'videos' => $videos ) );
    }

    private function status_to_progress( $status ) {
        switch ( $status ) {
            case 'completed':
                return 100;
            case 'processing':
                return 50;
            case 'failed':
                return 0;
            default:
                return 10;
        }
    }
}
