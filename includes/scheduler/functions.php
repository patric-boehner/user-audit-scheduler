<?php
/**
 * Scheduler Functions
 * 
 * Functions for managing automated email scheduling with WordPress cron
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedule the next audit email
 * 
 * Uses single scheduled events instead of recurring events
 * This avoids "unknown schedule" issues when plugin is deactivated
 * 
 * @param string $frequency Frequency: 'weekly', 'monthly', or 'quarterly'
 */
function uas_schedule_audit_email( $frequency ) {
	// Clear any existing schedule first
	uas_clear_scheduled_audit_email();
	
	// Only schedule if frequency is valid
	if ( ! in_array( $frequency, array( 'weekly', 'monthly', 'quarterly' ) ) ) {
		return;
	}
	
	// Calculate next send time
	$timestamp = uas_get_next_schedule_time( $frequency );
	
	// Schedule as SINGLE event (not recurring)
	// The callback will reschedule the next one
	wp_schedule_single_event( $timestamp, 'uas_send_scheduled_email' );
}

/**
 * Clear scheduled audit email
 * 
 * Removes ALL existing scheduled cron events
 * Loops until no events remain to handle multiple scheduled instances
 */
function uas_clear_scheduled_audit_email() {
	// Loop to remove ALL instances, not just the first one
	// wp_next_scheduled() only returns the first match
	while ( $timestamp = wp_next_scheduled( 'uas_send_scheduled_email' ) ) {
		wp_unschedule_event( $timestamp, 'uas_send_scheduled_email' );
	}
}

/**
 * Get recurrence name for WordPress cron
 * 
 * Converts our frequency names to WordPress cron recurrence names
 * 
 * @param string $frequency Frequency setting
 * @return string WordPress cron recurrence name
 */
function uas_get_recurrence_name( $frequency ) {
	$recurrence_map = array(
		'weekly'    => 'weekly',
		'monthly'   => 'uas_monthly',
		'quarterly' => 'uas_quarterly',
	);
	
	return isset( $recurrence_map[ $frequency ] ) ? $recurrence_map[ $frequency ] : 'weekly';
}

/**
 * Calculate next schedule time based on frequency
 * 
 * Returns timestamp for when the next email should be sent
 * Schedules for Monday 9am for weekly, 1st of month for monthly/quarterly
 * 
 * @param string $frequency Frequency setting
 * @return int Unix timestamp for next scheduled send
 */
function uas_get_next_schedule_time( $frequency ) {
	$current_time = current_time( 'timestamp' );
	
	switch ( $frequency ) {
		case 'weekly':
			// Schedule for next Monday at 9am
			$next_monday = strtotime( 'next Monday 9:00', $current_time );
			// If it's already Monday and past 9am, schedule for next week
			if ( $next_monday <= $current_time ) {
				$next_monday = strtotime( 'next Monday 9:00', $current_time + WEEK_IN_SECONDS );
			}
			return $next_monday;
			
		case 'monthly':
			// Schedule for 1st of next month at 9am
			$next_month = strtotime( 'first day of next month 9:00', $current_time );
			return $next_month;
			
		case 'quarterly':
			// Schedule for 1st of next quarter at 9am
			$current_month = wp_date( 'n', $current_time );
			$current_year = wp_date( 'Y', $current_time );
			
			// Determine next quarter start month
			if ( $current_month <= 3 ) {
				$next_quarter_month = 4; // April
				$next_quarter_year = $current_year;
			} elseif ( $current_month <= 6 ) {
				$next_quarter_month = 7; // July
				$next_quarter_year = $current_year;
			} elseif ( $current_month <= 9 ) {
				$next_quarter_month = 10; // October
				$next_quarter_year = $current_year;
			} else {
				$next_quarter_month = 1; // January
				$next_quarter_year = $current_year + 1;
			}
			
			return strtotime( $next_quarter_year . '-' . $next_quarter_month . '-01 09:00' );
			
		default:
			// Default to one week from now
			return $current_time + WEEK_IN_SECONDS;
	}
}

/**
 * Add custom recurrences to WordPress cron schedules
 * 
 * DEPRECATED: No longer used as of version 1.1.0
 * We now use single events that reschedule themselves instead of recurring events
 * This function is kept for backwards compatibility only
 * 
 * @deprecated 1.1.0
 * @param array $schedules Existing schedules
 * @return array Modified schedules
 */
function uas_add_cron_schedules( $schedules ) {
	// No longer adds custom schedules
	// Single events are used instead
	return $schedules;
}

/**
 * Send scheduled audit email
 * 
 * This is the function that WordPress cron calls
 * After sending, it automatically schedules the next email
 */
function uas_send_scheduled_email_callback() {
	$result = uas_send_audit_email();
	
	// Log the result for debugging
	if ( is_wp_error( $result ) ) {
		error_log( 'User Audit Scheduler: Failed to send scheduled email - ' . $result->get_error_message() );
	} else {
		error_log( 'User Audit Scheduler: Successfully sent scheduled email' );
	}
	
	// Schedule the next email if automation is still enabled
	$settings = get_option( 'uas_settings', array() );
	if ( ! empty( $settings['schedule_enabled'] ) && ! empty( $settings['schedule_frequency'] ) ) {
		// Calculate and schedule the next run
		$next_timestamp = uas_get_next_schedule_time( $settings['schedule_frequency'] );
		wp_schedule_single_event( $next_timestamp, 'uas_send_scheduled_email' );
		
		error_log( 'User Audit Scheduler: Next email scheduled for ' . wp_date( 'Y-m-d H:i:s', $next_timestamp ) );
	}
}

/**
 * Get next scheduled send time
 * 
 * Returns formatted string of when next email will be sent
 * Returns false if no email is scheduled
 * 
 * @return string|false Formatted datetime string or false
 */
function uas_get_next_scheduled_send() {
	$timestamp = wp_next_scheduled( 'uas_send_scheduled_email' );
	
	if ( ! $timestamp ) {
		return false;
	}
	
	// Format as readable date and time
	return wp_date( 'l, F j, Y \a\t g:i A', $timestamp );
}

/**
 * Check if automated emails are currently enabled
 * 
 * @return bool True if emails are scheduled, false otherwise
 */
function uas_is_scheduled() {
	$timestamp = wp_next_scheduled( 'uas_send_scheduled_email' );
	return (bool) $timestamp;
}