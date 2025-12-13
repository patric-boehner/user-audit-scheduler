<?php
/**
 * Tracking Functions
 * 
 * Functions for tracking user activity (login times, etc.)
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Track when a user logs in
 * 
 * Updates user meta with current timestamp whenever they log in
 * This allows us to show "last login" in audit reports
 * 
 * @param string $user_login Username
 * @param WP_User $user User object
 */
function uas_track_user_login( $user_login, $user ) {
	// Store current timestamp in user meta
	update_user_meta( $user->ID, '_user_last_login', current_time( 'timestamp' ) );
}