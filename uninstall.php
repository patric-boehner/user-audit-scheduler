<?php
/**
 * Uninstall Script
 * 
 * Runs when the plugin is deleted (not just deactivated)
 * Removes all plugin data from the database
 */

// Make sure we're actually uninstalling
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clear all scheduled cron events
wp_clear_scheduled_hook( 'uas_send_scheduled_email' );

// Delete plugin options
delete_option( 'uas_settings' );
delete_option( 'uas_db_version' );

// Delete last login meta from all users
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup, direct query and no caching are appropriate here
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", '_user_last_login' ) );

// Drop audit log table
// Use esc_sql() to properly escape the table name identifier
$table_name = esc_sql( $wpdb->prefix . 'uas_audit_log' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table name is escaped with esc_sql(), uninstall cleanup
$wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );