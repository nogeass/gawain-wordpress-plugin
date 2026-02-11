<?php
/**
 * Gawain AI Video — Uninstall handler.
 *
 * Fires when the plugin is deleted via WordPress admin.
 * Respects the "Delete data on uninstall" option:
 *  - When enabled: drops the video tracking table and deletes plugin options.
 *  - When disabled: leaves all data intact.
 *
 * @package Gawain_AI_Video
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$options = get_option( 'gawain_settings', array() );

if ( empty( $options['delete_on_uninstall'] ) ) {
    return;
}

// Delete plugin options.
delete_option( 'gawain_settings' );

// Drop the video tracking table.
global $wpdb;
$table = $wpdb->prefix . 'gawain_videos';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional cleanup on uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
