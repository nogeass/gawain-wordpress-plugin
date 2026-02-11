<?php
/**
 * WordPress REST API endpoints for admin AJAX calls.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gawain_REST {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $namespace = 'gawain/v1';

        register_rest_route( $namespace, '/generate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'generate_video' ),
            'permission_callback' => array( $this, 'check_admin' ),
        ) );

        register_rest_route( $namespace, '/job/(?P<job_id>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_job_status' ),
            'permission_callback' => array( $this, 'check_admin' ),
        ) );

        register_rest_route( $namespace, '/deploy', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'deploy_video' ),
            'permission_callback' => array( $this, 'check_admin' ),
        ) );

        register_rest_route( $namespace, '/undeploy', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'undeploy_video' ),
            'permission_callback' => array( $this, 'check_admin' ),
        ) );

        register_rest_route( $namespace, '/delete', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'delete_video' ),
            'permission_callback' => array( $this, 'check_admin' ),
        ) );

        register_rest_route( $namespace, '/videos', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_videos' ),
            'permission_callback' => array( $this, 'check_admin' ),
        ) );
    }

    public function check_admin() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * POST /wp-json/gawain/v1/generate
     */
    public function generate_video( $request ) {
        $product_id = absint( $request->get_param( 'product_id' ) );
        if ( ! $product_id ) {
            return new WP_Error( 'invalid_product', 'Product ID is required', array( 'status' => 400 ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'not_found', 'Product not found', array( 'status' => 404 ) );
        }

        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';
        if ( ! $image_url ) {
            return new WP_Error( 'no_image', 'Product has no image', array( 'status' => 400 ) );
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
            return new WP_Error( 'api_error', $result['error'], array( 'status' => 500 ) );
        }

        if ( ! isset( $result['jobId'] ) ) {
            return new WP_Error( 'api_error', 'Unexpected API response', array( 'status' => 500 ) );
        }

        // Save to local DB
        global $wpdb;
        $table = $wpdb->prefix . 'gawain_videos';
        $wpdb->insert( $table, array(
            'product_id' => $product_id,
            'job_id'     => $result['jobId'],
            'status'     => 'pending',
        ), array( '%d', '%s', '%s' ) );

        return rest_ensure_response( array(
            'jobId'        => $result['jobId'],
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
        $job_id = sanitize_text_field( $request->get_param( 'job_id' ) );

        $api    = new Gawain_API();
        $result = $api->get_job( $job_id );

        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'api_error', $result['error'], array( 'status' => 500 ) );
        }

        // Update local DB if status changed
        if ( isset( $result['status'] ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'gawain_videos';
            $update = array( 'status' => $result['status'] );
            if ( isset( $result['previewUrl'] ) ) {
                $update['video_url'] = $result['previewUrl'];
            }
            $wpdb->update( $table, $update, array( 'job_id' => $job_id ), array( '%s', '%s' ), array( '%s' ) );
        }

        return rest_ensure_response( $result );
    }

    /**
     * POST /wp-json/gawain/v1/deploy
     */
    public function deploy_video( $request ) {
        $job_id = sanitize_text_field( $request->get_param( 'videoId' ) );
        if ( ! $job_id ) {
            return new WP_Error( 'invalid', 'videoId is required', array( 'status' => 400 ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gawain_videos';
        $row   = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %s LIMIT 1", $job_id
        ) );

        if ( ! $row || ! $row->video_url ) {
            return new WP_Error( 'not_found', 'Video not found or not ready', array( 'status' => 404 ) );
        }

        $product = wc_get_product( $row->product_id );
        $title   = $product ? $product->get_name() : '';

        // Deploy to Gawain remote DB
        $api    = new Gawain_API();
        $result = $api->deploy_video( $row->product_id, $row->video_url, $job_id, $title );

        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'api_error', $result['error'], array( 'status' => 500 ) );
        }

        // Update local DB
        $wpdb->update( $table, array( 'deployed' => 1 ), array( 'job_id' => $job_id ), array( '%d' ), array( '%s' ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * POST /wp-json/gawain/v1/undeploy
     */
    public function undeploy_video( $request ) {
        $job_id = sanitize_text_field( $request->get_param( 'videoId' ) );
        if ( ! $job_id ) {
            return new WP_Error( 'invalid', 'videoId is required', array( 'status' => 400 ) );
        }

        $api    = new Gawain_API();
        $result = $api->undeploy_video( $job_id );

        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'api_error', $result['error'], array( 'status' => 500 ) );
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
        if ( ! $job_id ) {
            return new WP_Error( 'invalid', 'videoId is required', array( 'status' => 400 ) );
        }

        // Delete from Gawain remote DB
        $api    = new Gawain_API();
        $result = $api->delete_video( $job_id );

        // Delete locally regardless of remote result
        global $wpdb;
        $table = $wpdb->prefix . 'gawain_videos';
        $wpdb->delete( $table, array( 'job_id' => $job_id ), array( '%s' ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * GET /wp-json/gawain/v1/videos?product_ids=1,2,3
     */
    public function get_videos( $request ) {
        $product_ids = $request->get_param( 'product_ids' );

        global $wpdb;
        $table = $wpdb->prefix . 'gawain_videos';

        if ( $product_ids ) {
            $ids         = array_map( 'absint', explode( ',', $product_ids ) );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
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
        foreach ( $rows as $row ) {
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
