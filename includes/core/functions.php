<?php
/**
 * Core Functions
 * 
 * Core functionality for retrieving and formatting user data
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get all users with audit-relevant data
 * 
 * Retrieves all users excluding subscribers (unless they have elevated permissions)
 * Includes username, display name, email, role, and last login
 * 
 * @return array Array of user data objects
 */
function uas_get_audit_users() {
	$users = get_users( array(
		'orderby' => 'display_name',
		'order'   => 'ASC',
	) );
	
	$audit_data = array();
	
	foreach ( $users as $user ) {
		// Get user roles
		$roles = $user->roles;
		
		// Skip subscribers (they're typically not relevant for audits)
		// But include them if they have additional capabilities
		if ( in_array( 'subscriber', $roles ) && count( $roles ) === 1 ) {
			continue;
		}
		
		$audit_data[] = array(
			'ID'           => $user->ID,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'role'         => uas_format_user_roles( $roles ),
			'last_login'   => uas_get_user_last_login( $user->ID ),
			'edit_url'     => get_edit_user_link( $user->ID ),
		);
	}
	
	return $audit_data;
}

/**
 * Format user roles for display
 * 
 * Converts array of role slugs into human-readable format
 * 
 * @param array $roles Array of role slugs
 * @return string Formatted role string
 */
function uas_format_user_roles( $roles ) {
	if ( empty( $roles ) ) {
		return 'No Role';
	}
	
	global $wp_roles;
	$role_names = array();
	
	foreach ( $roles as $role ) {
		if ( isset( $wp_roles->role_names[ $role ] ) ) {
			$role_names[] = translate_user_role( $wp_roles->role_names[ $role ] );
		} else {
			$role_names[] = $role;
		}
	}
	
	return implode( ', ', $role_names );
}

/**
 * Get formatted last login time for a user
 * 
 * Returns human-readable format:
 * - Within 24 hours: "2 hours ago"
 * - After 24 hours: "Dec 11, 2024 3:45 PM"
 * 
 * @param int $user_id User ID
 * @return string Formatted last login time or "Never" if no login recorded
 */
function uas_get_user_last_login( $user_id ) {
	$last_login = get_user_meta( $user_id, '_user_last_login', true );
	
	if ( empty( $last_login ) ) {
		return '—';
	}
	
	$current_time = current_time( 'timestamp' );
	$time_diff_seconds = $current_time - $last_login;
	
	// If within last 24 hours, show relative time
	if ( $time_diff_seconds < 86400 ) {
		return human_time_diff( $last_login, $current_time ) . ' ago';
	}
	
	// After 24 hours, show formatted date
	return date( 'M j, Y g:i A', $last_login );
}

/**
 * Get formatted last login timestamp for export
 * 
 * Returns a machine-readable timestamp suitable for CSV export
 * 
 * @param int $user_id User ID
 * @return string Formatted datetime or "Never"
 */
function uas_get_user_last_login_timestamp( $user_id ) {
	$last_login = get_user_meta( $user_id, '_user_last_login', true );
	
	if ( empty( $last_login ) ) {
		return 'Never';
	}
	
	// Return formatted date for CSV
	return date( 'Y-m-d H:i:s', $last_login );
}