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

// Delete plugin options
delete_option( 'uas_settings' );

// Delete last login meta from all users
// This gets all user IDs and removes the last login meta
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_user_last_login'" );

// That's it for Phase 1
// In future phases, we'll also need to drop custom tables here