<?php
/**
 * Logging Functions
 * 
 * Functions for tracking user changes and maintaining audit logs
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create audit log database table
 * 
 * Called on plugin activation
 * Uses dbDelta for safe table creation/updates
 */
function uas_create_log_table() {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'uas_audit_log';
	$charset_collate = $wpdb->get_charset_collate();
	
	$sql = "CREATE TABLE $table_name (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		username varchar(60) NOT NULL,
		display_name varchar(250) NOT NULL,
		user_email varchar(100) NOT NULL,
		change_type varchar(50) NOT NULL,
		old_value text,
		new_value text,
		changed_by_id bigint(20) unsigned NOT NULL,
		changed_by_username varchar(60) NOT NULL,
		change_date datetime NOT NULL,
		notes text,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY change_type (change_type),
		KEY change_date (change_date),
		KEY changed_by_id (changed_by_id)
	) $charset_collate;";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
	// Store database version for future updates
	update_option( 'uas_db_version', '1.0' );
}

/**
 * Insert a log entry into the audit log
 * 
 * Captures complete snapshot of the change event
 * All user data is hard-coded so it persists even if users are deleted
 * 
 * @param array $args Log entry arguments
 * @return int|false Insert ID on success, false on failure
 */
function uas_insert_log_entry( $args ) {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'uas_audit_log';
	
	// Get current user making the change
	$current_user = wp_get_current_user();
	$changed_by_id = $current_user->ID ? $current_user->ID : 0;
	$changed_by_username = $current_user->user_login ? $current_user->user_login : 'system';
	
	// Prepare data for insertion
	$data = array(
		'user_id'             => $args['user_id'],
		'username'            => $args['username'],
		'display_name'        => $args['display_name'],
		'user_email'          => $args['user_email'],
		'change_type'         => $args['change_type'],
		'old_value'           => isset( $args['old_value'] ) ? $args['old_value'] : null,
		'new_value'           => isset( $args['new_value'] ) ? $args['new_value'] : null,
		'changed_by_id'       => $changed_by_id,
		'changed_by_username' => $changed_by_username,
		'change_date'         => current_time( 'mysql' ),
		'notes'               => isset( $args['notes'] ) ? $args['notes'] : '',
	);
	
	$format = array(
		'%d', // user_id
		'%s', // username
		'%s', // display_name
		'%s', // user_email
		'%s', // change_type
		'%s', // old_value
		'%s', // new_value
		'%d', // changed_by_id
		'%s', // changed_by_username
		'%s', // change_date
		'%s', // notes
	);
	
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert required for custom audit log table
	$result = $wpdb->insert( $table_name, $data, $format );
	
	if ( $result === false ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging errors for audit trail debugging
		error_log( 'User Audit Scheduler: Failed to insert log entry - ' . $wpdb->last_error );
		return false;
	}
	
	return $wpdb->insert_id;
}

/**
 * Log user creation
 * 
 * Only logs if user is created with an audited role
 * Conditional logging: subscriber creation is not logged (not security-relevant)
 * 
 * @param int $user_id User ID of newly created user
 */
function uas_log_user_created( $user_id ) {
	$user = get_userdata( $user_id );
	
	if ( ! $user ) {
		return;
	}
	
	// Only log if user has an audited role
	if ( ! uas_has_audited_role( $user->roles ) ) {
		return; // Skip logging subscribers - not security-relevant
	}
	
	$roles = uas_format_user_roles( $user->roles );
	
	uas_insert_log_entry( array(
		'user_id'      => $user_id,
		'username'     => $user->user_login,
		'display_name' => $user->display_name,
		'user_email'   => $user->user_email,
		'change_type'  => 'user_created',
		'old_value'    => null,
		'new_value'    => $roles,
		'notes'        => sprintf( 'New user created with role: %s', $roles ),
	) );
}

/**
 * Log role change
 * 
 * Only logs when transition crosses the audited boundary
 * Skips logging during user creation (user_register already logs this)
 * 
 * Examples:
 * - subscriber → editor: LOGGED (crosses boundary)
 * - editor → subscriber: LOGGED (crosses boundary)
 * - subscriber → contributor: NOT LOGGED (both non-audited)
 * - editor → admin: LOGGED (both audited, still security-relevant)
 * 
 * @param int $user_id User ID
 * @param string $role New role
 * @param array $old_roles Previous roles
 */
function uas_log_role_change( $user_id, $role, $old_roles ) {
	$user = get_userdata( $user_id );
	
	if ( ! $user ) {
		return;
	}
	
	// Skip if this is a new user creation (old_roles will be empty)
	// user_register hook already logs user creation
	if ( empty( $old_roles ) ) {
		return;
	}
	
	$old_value = uas_format_user_roles( $old_roles );
	$new_value = uas_format_user_roles( $user->roles );
	
	// Don't log if roles haven't actually changed
	if ( $old_value === $new_value ) {
		return;
	}
	
	// Only log if transition crosses audited boundary
	if ( ! uas_transition_crosses_boundary( $old_roles, $user->roles ) ) {
		return; // Skip subscriber → contributor changes, etc.
	}
	
	uas_insert_log_entry( array(
		'user_id'      => $user_id,
		'username'     => $user->user_login,
		'display_name' => $user->display_name,
		'user_email'   => $user->user_email,
		'change_type'  => 'role_changed',
		'old_value'    => $old_value,
		'new_value'    => $new_value,
		'notes'        => sprintf( 'Role changed from %s to %s', $old_value, $new_value ),
	) );
}

/**
 * Log user deletion
 * 
 * Only logs if user has an audited role
 * Conditional logging: subscriber deletions are not logged (not security-relevant)
 * Avoids logging thousands of membership cancellations
 * 
 * @param int $user_id User ID being deleted
 */
function uas_log_user_deleted( $user_id ) {
	$user = get_userdata( $user_id );
	
	if ( ! $user ) {
		return;
	}
	
	// Only log if user has an audited role
	if ( ! uas_has_audited_role( $user->roles ) ) {
		return; // Skip logging subscriber deletions
	}
	
	$roles = uas_format_user_roles( $user->roles );
	
	// Count user's content before deletion
	$post_count = count_user_posts( $user_id );
	
	$notes = sprintf( 'User account deleted. Had role: %s', $roles );
	if ( $post_count > 0 ) {
		$notes .= sprintf( '. Had %d published post%s.', $post_count, $post_count === 1 ? '' : 's' );
	}
	
	uas_insert_log_entry( array(
		'user_id'      => $user_id,
		'username'     => $user->user_login,
		'display_name' => $user->display_name,
		'user_email'   => $user->user_email,
		'change_type'  => 'user_deleted',
		'old_value'    => $roles,
		'new_value'    => null,
		'notes'        => $notes,
	) );
}

/**
 * Log profile update
 * 
 * Only logs if user has an audited role
 * Conditional logging: subscriber email changes are not logged (not security-relevant)
 * Email changes are security-relevant for elevated roles (password resets, account recovery)
 * 
 * @param int $user_id User ID being updated
 * @param WP_User $old_user_data User object before update
 */
function uas_log_profile_update( $user_id, $old_user_data ) {
	$user = get_userdata( $user_id );
	
	if ( ! $user ) {
		return;
	}
	
	// Only log if user has an audited role
	if ( ! uas_has_audited_role( $user->roles ) ) {
		return; // Skip logging subscriber profile changes
	}
	
	// Check for email change
	if ( $old_user_data->user_email !== $user->user_email ) {
		uas_insert_log_entry( array(
			'user_id'      => $user_id,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'user_email'   => $user->user_email,
			'change_type'  => 'profile_updated',
			'old_value'    => $old_user_data->user_email,
			'new_value'    => $user->user_email,
			'notes'        => sprintf( 'Email changed from %s to %s', $old_user_data->user_email, $user->user_email ),
		) );
	}
	
	// Check for display name change
	if ( $old_user_data->display_name !== $user->display_name ) {
		uas_insert_log_entry( array(
			'user_id'      => $user_id,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'user_email'   => $user->user_email,
			'change_type'  => 'profile_updated',
			'old_value'    => $old_user_data->display_name,
			'new_value'    => $user->display_name,
			'notes'        => sprintf( 'Display name changed from "%s" to "%s"', $old_user_data->display_name, $user->display_name ),
		) );
	}
}

/**
 * Get audit log entries
 * 
 * Retrieves log entries with optional filtering
 * 
 * @param array $args Query arguments
 * @return array Array of log entries
 */
function uas_get_log_entries( $args = array() ) {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'uas_audit_log';
	
	// Default arguments
	$defaults = array(
		'user_id'     => null,
		'change_type' => null,
		'date_from'   => null,
		'date_to'     => null,
		'limit'       => 100,
		'offset'      => 0,
		'orderby'     => 'change_date',
		'order'       => 'DESC',
	);
	
	$args = wp_parse_args( $args, $defaults );
	
	// Build WHERE clauses
	$where = array( '1=1' );
	$prepare_values = array();
	
	if ( ! empty( $args['user_id'] ) ) {
		$where[] = 'user_id = %d';
		$prepare_values[] = $args['user_id'];
	}
	
	if ( ! empty( $args['change_type'] ) ) {
		$where[] = 'change_type = %s';
		$prepare_values[] = $args['change_type'];
	}
	
	if ( ! empty( $args['date_from'] ) ) {
		$where[] = 'change_date >= %s';
		$prepare_values[] = $args['date_from'];
	}
	
	if ( ! empty( $args['date_to'] ) ) {
		$where[] = 'change_date <= %s';
		$prepare_values[] = $args['date_to'];
	}
	
	$where_sql = implode( ' AND ', $where );
	
	// Validate and sanitize ORDER BY clause
	$allowed_orderby = array( 'change_date', 'username', 'change_type' );
	$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'change_date';
	$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
	
	// Add limit and offset to prepare values
	if ( $args['limit'] > 0 ) {
		$prepare_values[] = $args['limit'];
		$prepare_values[] = $args['offset'];
		$limit_clause = ' LIMIT %d OFFSET %d';
	} else {
		$limit_clause = '';
	}
	
	// Build complete query
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, ORDER BY validated against whitelist
	$sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY $orderby $order" . $limit_clause;
	
	// Only prepare if we have values to bind
	if ( ! empty( $prepare_values ) ) {
		$prepared_sql = $wpdb->prepare( $sql, $prepare_values );
	} else {
		$prepared_sql = $sql;
	}
	
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is prepared above when values exist
	$results = $wpdb->get_results( $prepared_sql );
	
	return $results ? $results : array();
}

/**
 * Get total count of log entries
 * 
 * Used for pagination
 * 
 * @param array $args Query arguments (same as uas_get_log_entries)
 * @return int Total count
 */
function uas_get_log_entries_count( $args = array() ) {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'uas_audit_log';
	
	// Build WHERE clauses (same as uas_get_log_entries)
	$where = array( '1=1' );
	$prepare_values = array();
	
	if ( ! empty( $args['user_id'] ) ) {
		$where[] = 'user_id = %d';
		$prepare_values[] = $args['user_id'];
	}
	
	if ( ! empty( $args['change_type'] ) ) {
		$where[] = 'change_type = %s';
		$prepare_values[] = $args['change_type'];
	}
	
	if ( ! empty( $args['date_from'] ) ) {
		$where[] = 'change_date >= %s';
		$prepare_values[] = $args['date_from'];
	}
	
	if ( ! empty( $args['date_to'] ) ) {
		$where[] = 'change_date <= %s';
		$prepare_values[] = $args['date_to'];
	}
	
	$where_sql = implode( ' AND ', $where );
	
	// Build complete query
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix
	$sql = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
	
	// Only prepare if we have values to bind
	if ( ! empty( $prepare_values ) ) {
		$prepared_sql = $wpdb->prepare( $sql, $prepare_values );
	} else {
		$prepared_sql = $sql;
	}
	
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is prepared above when values exist
	return (int) $wpdb->get_var( $prepared_sql );
}

/**
 * Check if a role is in the audited roles list
 * 
 * Used to filter which logs are displayed (not which are created)
 * Follows "log broadly, report selectively" principle
 * 
 * @param string $role Role slug to check
 * @return bool True if role should be shown in reports
 */
function uas_is_audited_role( $role ) {
	// Get settings for audited roles
	$settings = get_option( 'uas_settings', array() );
	$audited_roles = isset( $settings['included_roles'] ) ? $settings['included_roles'] : array();
	
	// If no roles configured, default to all non-subscriber roles
	if ( empty( $audited_roles ) ) {
		global $wp_roles;
		$all_roles = $wp_roles->get_names();
		foreach ( $all_roles as $role_slug => $role_name ) {
			if ( $role_slug !== 'subscriber' ) {
				$audited_roles[] = $role_slug;
			}
		}
	}
	
	return in_array( $role, $audited_roles, true );
}

/**
 * Check if any role in a list is audited
 * 
 * Used for conditional logging - determines if we should log based on user's roles
 * 
 * @param array $roles Array of role slugs
 * @return bool True if any role is audited
 */
function uas_has_audited_role( $roles ) {
	if ( empty( $roles ) ) {
		return false;
	}
	
	foreach ( $roles as $role ) {
		if ( uas_is_audited_role( $role ) ) {
			return true;
		}
	}
	
	return false;
}

/**
 * Check if role transition crosses the audited boundary
 * 
 * Returns true if the change involves moving into or out of audited roles
 * 
 * @param array $old_roles Previous roles
 * @param array $new_roles New roles
 * @return bool True if transition crosses audited boundary
 */
function uas_transition_crosses_boundary( $old_roles, $new_roles ) {
	$old_has_audited = uas_has_audited_role( $old_roles );
	$new_has_audited = uas_has_audited_role( $new_roles );
	
	// Log if either side has an audited role (crossing the boundary)
	// subscriber → editor: false → true = true (log it)
	// editor → subscriber: true → false = true (log it)
	// subscriber → subscriber: false → false = false (don't log)
	// editor → admin: true → true = true (log it)
	
	return $old_has_audited || $new_has_audited;
}