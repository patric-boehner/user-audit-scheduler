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
 * Retrieves users based on role settings
 * Includes username, display name, email, role, and last login
 * 
 * @return array Array of user data objects
 */
function uas_get_audit_users() {
	// Get settings to determine which roles to include
	$settings = get_option( 'uas_settings', array() );
	$included_roles = isset( $settings['included_roles'] ) ? $settings['included_roles'] : array();
	
	// If no roles selected, default to all non-subscriber roles
	if ( empty( $included_roles ) ) {
		global $wp_roles;
		$all_roles = $wp_roles->get_names();
		foreach ( $all_roles as $role_slug => $role_name ) {
			if ( $role_slug !== 'subscriber' ) {
				$included_roles[] = $role_slug;
			}
		}
	}
	
	$users = get_users( array(
		'orderby' => 'display_name',
		'order'   => 'ASC',
	) );
	
	$audit_data = array();
	
	foreach ( $users as $user ) {
		// Get user roles
		$roles = $user->roles;
		
		// Skip if user has no roles
		if ( empty( $roles ) ) {
			continue;
		}
		
		// Check if user has any of the included roles
		$has_included_role = false;
		foreach ( $roles as $role ) {
			if ( in_array( $role, $included_roles ) ) {
				$has_included_role = true;
				break;
			}
		}
		
		// Skip if user doesn't have any included roles
		if ( ! $has_included_role ) {
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
 * @return string Formatted last login time or "-" if no login recorded
 */
function uas_get_user_last_login( $user_id ) {
	$last_login = get_user_meta( $user_id, '_user_last_login', true );
	
	if ( empty( $last_login ) ) {
		return '-';
	}
	
	$current_time = current_time( 'timestamp' );
	$time_diff_seconds = $current_time - $last_login;
	
	// If within last 24 hours, show relative time
	if ( $time_diff_seconds < 86400 ) {
		return human_time_diff( $last_login, $current_time ) . ' ago';
	}
	
	// After 24 hours, show formatted date
	return wp_date( 'M j, Y g:i A', $last_login );
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
	
	// Return formatted date for CSV - use wp_date for WordPress timezone awareness
	return wp_date( 'Y-m-d H:i:s', $last_login );
}

/**
 * Output CSV data to browser
 * 
 * WordPress-compliant CSV output that avoids direct file operations
 * Uses output buffering instead of fopen/fclose
 * 
 * @param array $data 2D array of CSV data (rows and columns)
 */
function uas_output_csv( $data ) {
	if ( empty( $data ) ) {
		return;
	}
	
	// Use output buffering to build CSV content
	ob_start();
	
	foreach ( $data as $row ) {
		// Manually build CSV row to avoid fopen/fclose
		$escaped_row = array();
		foreach ( $row as $field ) {
			// Convert null to empty string for CSV output
			$field = (string) $field;
			
			// Escape fields that contain commas, quotes, or newlines
			if ( strpos( $field, ',' ) !== false || strpos( $field, '"' ) !== false || strpos( $field, "\n" ) !== false ) {
				$field = '"' . str_replace( '"', '""', $field ) . '"';
			}
			$escaped_row[] = $field;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data is manually escaped above per CSV standards
		echo implode( ',', $escaped_row ) . "\n";
	}
	
	$csv_content = ob_get_clean();
	
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV content is properly escaped above
	echo $csv_content;
}