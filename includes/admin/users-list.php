<?php
/**
 * Users List Table
 * 
 * Functions for adding last login column to the WordPress Users list
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Last Login column to users list table
 * 
 * @param array $columns Existing columns
 * @return array Modified columns with Last Login added
 */
function uas_add_last_login_column( $columns ) {
	// Add the column at the end (after posts)
	$columns['last_login'] = 'Last Login';
	
	return $columns;
}

/**
 * Display Last Login column content
 * 
 * @param string $output Custom column output (empty by default)
 * @param string $column_name Name of the column
 * @param int $user_id User ID
 * @return string Column content
 */
function uas_show_last_login_column( $output, $column_name, $user_id ) {
	if ( $column_name === 'last_login' ) {
		$last_login = get_user_meta( $user_id, '_user_last_login', true );
		
		if ( empty( $last_login ) ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">Never</span>';
		}
		
		$current_time = current_time( 'timestamp' );
		$time_diff_seconds = $current_time - $last_login;
		
		// If within last 24 hours, show relative time
		if ( $time_diff_seconds < 86400 ) {
			$time_diff = human_time_diff( $last_login, $current_time );
			$display = $time_diff . ' ago';
		} else {
			// After 24 hours, show formatted date
			$display = wp_date( 'M j, Y', $last_login );
		}
		
		// Always show full date/time on hover
		$full_date = wp_date( 'F j, Y \a\t g:i A', $last_login );
		
		return '<span title="' . esc_attr( $full_date ) . '">' . esc_html( $display ) . '</span>';
	}
	
	return $output;
}

/**
 * Make Last Login column sortable
 * 
 * @param array $columns Sortable columns
 * @return array Modified sortable columns
 */
function uas_make_last_login_sortable( $columns ) {
	$columns['last_login'] = 'last_login';
	return $columns;
}

/**
 * Handle Last Login column sorting
 * 
 * Modifies the user query to sort by last login when requested
 * 
 * @param WP_User_Query $query User query object
 */
function uas_sort_last_login_column( $query ) {
	// Only modify query on users list page in admin
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'users' ) {
		return;
	}
	
	// Check if sorting by last_login
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sorting parameter from WordPress core users list table
	if ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'last_login' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sorting parameter from WordPress core users list table
		$order = isset( $_GET['order'] ) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
		
		// Modify query to sort by meta value
		$query->set( 'meta_key', '_user_last_login' );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', $order );
	}
}