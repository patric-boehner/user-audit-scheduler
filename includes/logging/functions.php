<?php
/**
 * Logging Functions
 * 
 * Functions for tracking user changes and maintaining audit logs
 * 
 * All audit logging decisions flow through uas_should_log_event()
 * This prevents accidental logging of non-security-relevant events
 * and ensures consistency across the entire logging system.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determine if an event should be logged
 * 
 * This is the ONLY function that decides what gets written to the audit log.
 * All logging functions must call this before inserting any log entry.
 * 
 * Without a central decision point, logging decisions get scattered across
 * multiple functions, creating risk of:
 * - Accidentally logging thousands of subscriber events on membership sites
 * - Inconsistent application of audit rules
 * - Silent drift as new features are added
 * - Debugging nightmares when trying to understand "why was this logged?"
 * 
 * Nothing gets logged unless it explicitly returns true.
 * 
 * DECISION LOGIC:
 * 1. Is this a recognized event type? (user_created, role_changed, etc.)
 * 2. Does this involve an audited user role? (configured in settings)
 * 3. For role changes specifically, does it cross the security boundary?
 * 4. Allow filters to override for site-specific needs
 * 
 * EXAMPLES:
 * Event: subscriber created
 * Result: false (not an audited role)
 * 
 * Event: editor created  
 * Result: true (audited role)
 * 
 * Event: subscriber → editor (role change)
 * Result: true (crosses security boundary)
 * 
 * Event: subscriber → contributor (role change)
 * Result: false (neither role is audited by default)
 * 
 * Event: editor → admin (role change)
 * Result: true (both roles audited, still security-relevant)
 * 
 * Event: subscriber email changed
 * Result: false (not an audited role)
 * 
 * Event: editor email changed
 * Result: true (audited role, email is security-relevant)
 * 
 * @param string $event_type Type of event (user_created, role_changed, user_deleted, profile_updated)
 * @param WP_User $user User object (represents current/final state)
 * @param array $context Additional context (e.g., old_roles for role changes)
 * @return bool True if event should be logged, false otherwise
 */
function uas_should_log_event( $event_type, $user, $context = array() ) {
	// 1. Only log recognized event types
	$allowed_events = array(
		'user_created',
		'user_deleted',
		'role_changed',
		'profile_updated',
	);
	
	if ( ! in_array( $event_type, $allowed_events, true ) ) {
		return false;
	}
	
	// 2. Get current and previous roles with defensive normalization
	// Protects against malformed filters or unexpected plugin interference
	$current_roles = isset( $user->roles ) && is_array( $user->roles ) ? $user->roles : array();
	$old_roles = isset( $context['old_roles'] ) && is_array( $context['old_roles'] ) ? $context['old_roles'] : array();
	
	// 3. Event-specific logic
	switch ( $event_type ) {
		case 'role_changed':
			// For role changes, check if transition crosses the security boundary
			$should_log = uas_transition_crosses_boundary( $old_roles, $current_roles );
			break;
			
		case 'user_deleted':
			// For deletions, check the roles the user HAD before deletion
			// Prefer explicit old_roles from context for clarity and future safety
			$roles_to_check = ! empty( $old_roles ) ? $old_roles : $current_roles;
			$should_log = uas_has_audited_role( $roles_to_check );
			break;
			
		case 'user_created':
		case 'profile_updated':
		default:
			// For creation and profile updates, check current roles
			$should_log = uas_has_audited_role( $current_roles );
			break;
	}
	
	// 4. Allow filters to override for site-specific requirements
	// Example: A site might want to log ALL subscriber activity
	// add_filter( 'uas_should_log_event', function( $should_log, $event_type, $user ) {
	//     return true; // Log everything
	// }, 10, 3 );
	return apply_filters(
		'uas_should_log_event',
		$should_log,
		$event_type,
		$user,
		$context
	);
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
 * This function is now "dumb" - it just prepares the log entry.
 * The decision about whether to log happens in one central place.
 * 
 * @param int $user_id User ID of newly created user
 */
function uas_log_user_created( $user_id ) {
	$user = get_userdata( $user_id );
	
	if ( ! $user ) {
		return;
	}
	
	// CENTRAL DECISION POINT
	// All logging logic consolidated in uas_should_log_event()
	if ( ! uas_should_log_event( 'user_created', $user ) ) {
		return;
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
 * Skips logging during user creation (user_register already logs this)
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
	
	// CENTRAL DECISION POINT
	// Pass old_roles in context for boundary checking
	if ( ! uas_should_log_event( 'role_changed', $user, array( 'old_roles' => $old_roles ) ) ) {
		return;
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
 * Explicitly passes roles in context for clarity and future safety
 * 
 * @param int $user_id User ID being deleted
 */
function uas_log_user_deleted( $user_id ) {
	$user = get_userdata( $user_id );
	
	if ( ! $user ) {
		return;
	}
	
	// Explicitly pass roles in context - don't rely on hook timing
	if ( ! uas_should_log_event( 'user_deleted', $user, array( 'old_roles' => $user->roles ) ) ) {
		return;
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
 * Tracks email and display name changes for audited users
 * 
 * @param int $user_id User ID being updated
 * @param WP_User $old_user_data User object before update
 */
function uas_log_profile_update( $user_id, $old_user_data ) {
	$user = get_userdata( $user_id );
	
	if ( ! $user ) {
		return;
	}
	
	// CENTRAL DECISION POINT
	if ( ! uas_should_log_event( 'profile_updated', $user ) ) {
		return;
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
 * Examples:
 * subscriber → editor: false → true = true (log it)
 * editor → subscriber: true → false = true (log it)
 * subscriber → subscriber: false → false = false (don't log)
 * 
 * @param array $old_roles Previous roles
 * @param array $new_roles New roles
 * @return bool True if transition crosses audited boundary
 */
function uas_transition_crosses_boundary( $old_roles, $new_roles ) {
	$old_has_audited = uas_has_audited_role( $old_roles );
	$new_has_audited = uas_has_audited_role( $new_roles );
	
	return $old_has_audited || $new_has_audited;
}

/**
 * Clean up old audit logs
 * 
 * Automatically deletes logs older than the retention period
 * This prevents unbounded database growth while maintaining
 * sufficient history for security audits and compliance.
 * 
 * Default: 365 days (1 year)
 * 
 * The retention period can be customized via filter:
 * 
 * Examples:
 * Never delete logs (compliance requirement)
 * add_filter( 'uas_log_retention_days', '__return_zero' );
 * 
 * 2 years for healthcare compliance
 * add_filter( 'uas_log_retention_days', function() { return 730; } );
 * 
 * Called daily via WordPress cron at 3am
 */
function uas_cleanup_old_logs_callback() {
	global $wpdb;
	
	// Default: 1 year retention
	// Set to 0 to never delete logs
	$retention_days = apply_filters( 'uas_log_retention_days', 365 );
	
	// If set to 0, never delete
	if ( $retention_days === 0 ) {
		return;
	}
	
	$table_name = $wpdb->prefix . 'uas_audit_log';
	$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
	
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup operation, direct query appropriate
	$deleted = $wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix
			"DELETE FROM $table_name WHERE change_date < %s",
			$cutoff_date
		)
	);
	
	if ( $deleted ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging cleanup operations for audit trail
		error_log( sprintf(
			'User Audit Scheduler: Cleaned up %d log entries older than %d days',
			$deleted,
			$retention_days
		) );
	}
}
