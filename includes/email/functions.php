<?php
/**
 * Email Functions - REFINED VERSION
 * 
 * Functions for sending audit report emails with improved structure
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send audit email
 * 
 * Sends an HTML email with user audit data to configured recipients
 * Can be called manually by admins or automatically via WP-Cron
 * 
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function uas_send_audit_email() {
	// Check user capabilities
	// Allow if user has permission OR if running via WP-Cron
	if ( ! wp_doing_cron() && ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'no_permission', 'You do not have sufficient permissions to send audit emails.' );
	}
	
	// Get settings
	$settings = get_option( 'uas_settings', array() );
	
	// Get recipients
	$recipients = uas_get_email_recipients();
	if ( empty( $recipients ) ) {
		return new WP_Error( 'no_recipients', 'No email recipients configured. Please add at least one email address in the settings.' );
	}
	
	// Get email subject
	$subject = isset( $settings['email_subject'] ) ? $settings['email_subject'] : 'User Audit Report: Review Changes for ' . $site_name;
	$subject = sanitize_text_field( $subject );
	// Remove any newlines to prevent email header injection
	$subject = str_replace( array( "\r", "\n" ), '', $subject );
	
	// Get audit data
	$users = uas_get_audit_users();
	if ( empty( $users ) ) {
		return new WP_Error( 'no_users', 'No users found to include in audit report.' );
	}
	
	// Generate email content
	$message = uas_generate_email_html( $users );
	
	// Set email headers for HTML
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
	);
	
	// Send email
	$sent = wp_mail( $recipients, $subject, $message, $headers );
	
	if ( ! $sent ) {
		return new WP_Error( 'email_failed', 'Failed to send email. Please check your email configuration.' );
	}
	
	return true;
}

/**
 * Get email recipients from settings
 * 
 * Parses the email recipients setting and returns an array of valid emails
 * 
 * @return array Array of email addresses
 */
function uas_get_email_recipients() {
	$settings = get_option( 'uas_settings', array() );
	$recipients_string = isset( $settings['email_recipients'] ) ? $settings['email_recipients'] : '';
	
	// Split by commas and newlines (for backward compatibility)
	$recipients = preg_split( '/[\n,]+/', $recipients_string );
	
	// Trim and validate each email
	$valid_recipients = array();
	foreach ( $recipients as $email ) {
		$email = trim( $email );
		if ( is_email( $email ) ) {
			$valid_recipients[] = $email;
		}
	}
	
	return $valid_recipients;
}

/**
 * Get smart activity summary for last 30 days
 * 
 * Returns human-readable summary focused on security-relevant changes
 * 
 * @return array Array of summary strings
 */
function uas_get_activity_summary() {
	$thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
	
	// Get all changes from last 30 days, excluding profile updates
	$changes = uas_get_log_entries( array(
		'date_from' => $thirty_days_ago,
		'limit'     => 0, // No limit
	) );
	
	// Categorize changes
	$users_created = 0;
	$admin_added = 0;
	$role_changes = 0;
	$admin_promoted = 0;
	$users_deleted = 0;
	
	foreach ( $changes as $change ) {
		// Skip profile updates
		if ( $change->change_type === 'profile_updated' ) {
			continue;
		}
		
		// New users
		if ( $change->change_type === 'user_created' ) {
			$users_created++;
			// Track new administrators separately
			if ( strpos( $change->new_value, 'Administrator' ) !== false ) {
				$admin_added++;
			}
		}
		
		// Role changes
		if ( $change->change_type === 'role_changed' ) {
			$role_changes++;
			// Track promotions to administrator separately
			if ( strpos( $change->new_value, 'Administrator' ) !== false ) {
				$admin_promoted++;
			}
		}
		
		// User deletions
		if ( $change->change_type === 'user_deleted' ) {
			$users_deleted++;
		}
	}
	
	// Build summary array
	$summary = array();
	
	// New users (show if any, highlight if admin)
	if ( $users_created > 0 ) {
		if ( $admin_added > 0 ) {
			$summary[] = $admin_added . ' new Administrator account' . ( $admin_added !== 1 ? 's' : '' ) . ' ' . ( $admin_added !== 1 ? 'were' : 'was' ) . ' added';
		} else {
			$summary[] = $users_created . ' new user' . ( $users_created !== 1 ? 's were' : ' was' ) . ' created';
		}
	}
	
	// Role changes (show if any, highlight if admin promotion)
	if ( $role_changes > 0 ) {
		if ( $admin_promoted > 0 ) {
			$summary[] = $admin_promoted . ' user' . ( $admin_promoted !== 1 ? 's were' : ' was' ) . ' upgraded to Administrator';
		} else {
			$summary[] = $role_changes . ' role change' . ( $role_changes !== 1 ? 's' : '' );
		}
	}
	
	// Deletions
	if ( $users_deleted === 0 ) {
		$summary[] = 'No unexpected deletions detected';
	} else {
		$summary[] = $users_deleted . ' user' . ( $users_deleted !== 1 ? 's were' : ' was' ) . ' deleted';
	}
	
	// If nothing happened at all
	if ( $users_created === 0 && $role_changes === 0 && $users_deleted === 0 ) {
		$summary = array( 'No significant changes detected' );
	}
	
	return $summary;
}

/**
 * Get recent changes grouped by type for detailed display
 * 
 * Returns changes from last 30 days, excluding profile updates,
 * grouped by change type and ordered newest first
 * 
 * @return array Associative array with changes grouped by type
 */
function uas_get_recent_changes_detailed() {
	$thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
	
	// Get all changes from last 30 days, excluding profile updates
	$all_changes = uas_get_log_entries( array(
		'date_from' => $thirty_days_ago,
		'limit'     => 0, // No limit
		'orderby'   => 'change_date',
		'order'     => 'DESC', // Newest first
	) );
	
	// Group by type
	$grouped = array(
		'user_created' => array(),
		'role_changed' => array(),
		'user_deleted' => array(),
	);
	
	foreach ( $all_changes as $change ) {
		if ( isset( $grouped[ $change->change_type ] ) ) {
			$grouped[ $change->change_type ][] = $change;
		}
	}
	
	return $grouped;
}

/**
 * Generate HTML email content
 * 
 * Creates formatted HTML email with clear hierarchy and actionable guidance
 * 
 * @param array $users Array of user data
 * @return string HTML content
 */
function uas_generate_email_html( $users ) {
	$site_name = get_bloginfo( 'name' );
	$site_url = get_site_url();
	
	// Get recent changes data
	$summary = uas_get_activity_summary();
	$detailed = uas_get_recent_changes_detailed();
	
	// Build links
	$thirty_days_ago = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
	$today = gmdate( 'Y-m-d' );
	$logs_url = admin_url( 'users.php?page=user-audit-logs&filter_from=' . $thirty_days_ago . '&filter_to=' . $today );
	$users_url = admin_url( 'users.php' );
	$settings_url = admin_url( 'users.php?page=user-audit-settings' );
	
	// Separate high-priority changes (admin-related and deletions)
	$high_priority = array();
	$other_activity = array(
		'user_created' => array(),
		'role_changed' => array(),
	);
	
	// Categorize new users
	if ( ! empty( $detailed['user_created'] ) ) {
		foreach ( $detailed['user_created'] as $change ) {
			if ( strpos( $change->new_value, 'Administrator' ) !== false ) {
				$high_priority[] = array(
					'type' => 'admin_created',
					'data' => $change,
				);
			} else {
				$other_activity['user_created'][] = $change;
			}
		}
	}
	
	// Categorize role changes
	if ( ! empty( $detailed['role_changed'] ) ) {
		foreach ( $detailed['role_changed'] as $change ) {
			if ( strpos( $change->new_value, 'Administrator' ) !== false || strpos( $change->old_value, 'Administrator' ) !== false ) {
				$high_priority[] = array(
					'type' => 'admin_role_change',
					'data' => $change,
				);
			} else {
				$other_activity['role_changed'][] = $change;
			}
		}
	}
	
	// All deletions are high priority
	if ( ! empty( $detailed['user_deleted'] ) ) {
		foreach ( $detailed['user_deleted'] as $change ) {
			$high_priority[] = array(
				'type' => 'deleted',
				'data' => $change,
			);
		}
	}
	
	$has_high_priority = ! empty( $high_priority );
	$has_other_activity = ! empty( $other_activity['user_created'] ) || ! empty( $other_activity['role_changed'] );
	
	// Start building HTML
	$html = '<!DOCTYPE html>';
	$html .= '<html lang="en">';
	$html .= '<head>';
	$html .= '<meta charset="UTF-8">';
	$html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
	$html .= '<title>User Audit Report</title>';
	$html .= '</head>';
	$html .= '<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; color: #000000; max-width: 700px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">';
	
	// Email container
	$html .= '<div style="background-color: #ffffff; padding: 30px; border-radius: 4px;">';
	
	// Subject line as header
	$html .= '<h1 style="color: #000000; margin: 0 0 20px 0; font-size: 24px; font-weight: 600;">User Audit Report: Review Changes</h1>';
	
	// Introduction
	$html .= '<p style="color: #000000; margin: 0 0 10px 0; line-height: 1.6;">Hello,</p>';
	$html .= '<p style="color: #000000; margin: 0 0 10px 0; line-height: 1.6;">This is your scheduled WordPress User Audit Report for <strong>' . esc_html( $site_name ) . '</strong>, covering changes since the last report.</p>';
	
	// Actionable guidance
	$html .= '<p style="color: #000000; margin: 0 0 30px 0; line-height: 1.6;">If any of the changes below are unexpected, please review the affected user accounts.</p>';
	
	// High Priority Section (if applicable)
	if ( $has_high_priority ) {
		$html .= '<div style="border: 2px solid #000000; padding: 20px; margin-bottom: 30px; background-color: #fafafa;">';
		$html .= '<h2 style="color: #000000; margin: 0 0 8px 0; font-size: 18px; font-weight: 700;">Changes That May Require Review</h2>';
		$html .= '<p style="color: #000000; margin: 0 0 20px 0; font-size: 14px;">The following changes typically require manual confirmation:</p>';
		
		foreach ( $high_priority as $item ) {
			$change = $item['data'];
			$change_date = wp_date( 'M j', strtotime( $change->change_date ) );
			
			$html .= '<div style="margin: 0 0 20px 0; padding-bottom: 20px; border-bottom: 1px solid #dddddd;">';
			
			if ( $item['type'] === 'admin_created' ) {
				$html .= '<p style="margin: 0 0 4px 0; color: #000000; font-weight: 600; font-size: 15px;">' . esc_html( $change->display_name ) . ' <span style="font-weight: 400; color: #666;">(' . esc_html( $change->user_email ) . ')</span></p>';
				$html .= '<p style="margin: 0 0 4px 0; color: #000000;">New ' . esc_html( $change->new_value ) . ' account created</p>';
				$html .= '<p style="margin: 0 0 8px 0; color: #666; font-size: 14px;">Added on ' . esc_html( $change_date ) . ' by ' . esc_html( $change->changed_by_username ) . '</p>';
				$html .= '<p style="margin: 0; color: #0073aa; font-size: 14px;"><strong>Action:</strong> Verify this role assignment is correct</p>';
			} elseif ( $item['type'] === 'admin_role_change' ) {
				$html .= '<p style="margin: 0 0 4px 0; color: #000000; font-weight: 600; font-size: 15px;">' . esc_html( $change->display_name ) . ' <span style="font-weight: 400; color: #666;">(' . esc_html( $change->user_email ) . ')</span></p>';
				$html .= '<p style="margin: 0 0 4px 0; color: #000000;">Role changed from ' . esc_html( $change->old_value ) . ' to ' . esc_html( $change->new_value ) . '</p>';
				$html .= '<p style="margin: 0 0 8px 0; color: #666; font-size: 14px;">Changed on ' . esc_html( $change_date ) . ' by ' . esc_html( $change->changed_by_username ) . '</p>';
				$html .= '<p style="margin: 0; color: #0073aa; font-size: 14px;"><strong>Action:</strong> Verify this change was authorized</p>';
			} elseif ( $item['type'] === 'deleted' ) {
				$html .= '<p style="margin: 0 0 4px 0; color: #000000; font-weight: 600; font-size: 15px;">' . esc_html( $change->display_name ) . ' <span style="font-weight: 400; color: #666;">(' . esc_html( $change->user_email ) . ')</span></p>';
				$html .= '<p style="margin: 0 0 4px 0; color: #000000;">' . esc_html( $change->old_value ) . ' account deleted</p>';
				$html .= '<p style="margin: 0 0 8px 0; color: #666; font-size: 14px;">Deleted on ' . esc_html( $change_date ) . ' by ' . esc_html( $change->changed_by_username ) . '</p>';
				$html .= '<p style="margin: 0; color: #0073aa; font-size: 14px;"><strong>Action:</strong> Confirm this deletion was intentional</p>';
			}
			
			$html .= '</div>';
		}
		
		// Remove extra border from last item
		$html = substr( $html, 0, -6 ) . '</div>'; // Remove the border-bottom from last item
		
		$html .= '</div>';
	}
	
	// Activity Summary Section
	$html .= '<h2 style="color: #000000; margin: 0 0 12px 0; font-size: 18px; font-weight: 600; border-bottom: 2px solid #000000; padding-bottom: 8px;">Activity Summary (Last 30 Days)</h2>';
	$html .= '<ul style="color: #000000; margin: 0 0 30px 0; padding-left: 20px; line-height: 1.8;">';
	foreach ( $summary as $item ) {
		$html .= '<li>' . esc_html( $item ) . '</li>';
	}
	$html .= '</ul>';
	
	// Other Account Activity Section (if applicable)
	if ( $has_other_activity ) {
		$html .= '<h2 style="color: #000000; margin: 0 0 15px 0; font-size: 18px; font-weight: 600; border-bottom: 2px solid #000000; padding-bottom: 8px;">Other Account Activity</h2>';
		
		// New Users Created (non-admin)
		if ( ! empty( $other_activity['user_created'] ) ) {
			$html .= '<p style="color: #000000; margin: 0 0 12px 0; font-weight: 600;">New Users Created:</p>';
			$html .= '<ul style="color: #000000; margin: 0 0 20px 0; padding-left: 20px; line-height: 1.8;">';
			
			foreach ( $other_activity['user_created'] as $change ) {
				$change_date = wp_date( 'M j', strtotime( $change->change_date ) );
				$html .= '<li><strong>' . esc_html( $change->display_name ) . '</strong> <span style="color: #666;">(' . esc_html( $change->user_email ) . ')</span> — ' . esc_html( $change->new_value ) . '<br>';
				$html .= '<span style="color: #666; font-size: 14px;">Added on ' . esc_html( $change_date ) . ' by ' . esc_html( $change->changed_by_username ) . '</span></li>';
			}
			
			$html .= '</ul>';
		}
		
		// Role Changes (non-admin)
		if ( ! empty( $other_activity['role_changed'] ) ) {
			$html .= '<p style="color: #000000; margin: 0 0 12px 0; font-weight: 600;">Role Changes:</p>';
			$html .= '<ul style="color: #000000; margin: 0 0 20px 0; padding-left: 20px; line-height: 1.8;">';
			
			foreach ( $other_activity['role_changed'] as $change ) {
				$change_date = wp_date( 'M j', strtotime( $change->change_date ) );
				$html .= '<li><strong>' . esc_html( $change->display_name ) . '</strong> <span style="color: #666;">(' . esc_html( $change->user_email ) . ')</span><br>';
				$html .= esc_html( $change->old_value ) . ' → ' . esc_html( $change->new_value ) . '<br>';
				$html .= '<span style="color: #666; font-size: 14px;">Changed on ' . esc_html( $change_date ) . ' by ' . esc_html( $change->changed_by_username ) . '</span></li>';
			}
			
			$html .= '</ul>';
		}
		
		$html .= '<div style="margin: 0 0 30px 0;"></div>';
	}
	
	// Current Users Section
	$html .= '<h2 style="color: #000000; margin: 0 0 15px 0; font-size: 18px; font-weight: 600; border-bottom: 2px solid #000000; padding-bottom: 8px;">Current Users</h2>';
	
	// Table
	$html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">';
	
	// Table header
	$html .= '<thead>';
	$html .= '<tr style="background-color: #f5f5f5;">';
	$html .= '<th style="padding: 10px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600; font-size: 14px;">Username</th>';
	$html .= '<th style="padding: 10px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600; font-size: 14px;">Display Name</th>';
	$html .= '<th style="padding: 10px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600; font-size: 14px;">Role</th>';
	$html .= '<th style="padding: 10px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600; font-size: 14px;">Email</th>';
	$html .= '<th style="padding: 10px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600; font-size: 14px;">Last Login</th>';
	$html .= '<th style="padding: 10px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600; font-size: 14px;">Action</th>';
	$html .= '</tr>';
	$html .= '</thead>';
	
	// Table body
	$html .= '<tbody>';
	foreach ( $users as $user ) {
		$html .= '<tr>';
		$html .= '<td style="padding: 10px; border: 1px solid #dddddd; color: #000000; font-size: 14px;">' . esc_html( $user['username'] ) . '</td>';
		$html .= '<td style="padding: 10px; border: 1px solid #dddddd; color: #000000; font-size: 14px;">' . esc_html( $user['display_name'] ) . '</td>';
		$html .= '<td style="padding: 10px; border: 1px solid #dddddd; color: #000000; font-size: 14px;">' . esc_html( $user['role'] ) . '</td>';
		$html .= '<td style="padding: 10px; border: 1px solid #dddddd; color: #000000; font-size: 14px;">' . esc_html( $user['email'] ) . '</td>';
		$html .= '<td style="padding: 10px; border: 1px solid #dddddd; color: #000000; font-size: 14px;">' . esc_html( $user['last_login'] ) . '</td>';
		$html .= '<td style="padding: 10px; border: 1px solid #dddddd; color: #000000; font-size: 14px;"><a href="' . esc_url( $user['edit_url'] ) . '" style="color: #0073aa; text-decoration: none;">Edit User</a></td>';
		$html .= '</tr>';
	}
	$html .= '</tbody>';
	$html .= '</table>';
	
	// What to do next section
	$html .= '<h3 style="color: #000000; margin: 25px 0 12px 0; font-size: 16px; font-weight: 600;">What to do next</h3>';
	$html .= '<ul style="color: #000000; margin: 0 0 30px 0; padding-left: 20px; line-height: 1.8;">';
	$html .= '<li>Review any unexpected changes above</li>';
	$html .= '<li>Remove access if no longer needed</li>';
	$html .= '<li>Use the links below to take action</li>';
	$html .= '</ul>';
	
	// Action buttons
	$html .= '<div style="text-align: center;">';
	$html .= '<a href="' . esc_url( $users_url ) . '" style="display: inline-block; padding: 12px 24px; margin: 0 5px 10px 5px; background-color: #000000; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px;">View All Users</a>';
	$html .= '<a href="' . esc_url( $logs_url ) . '" style="display: inline-block; padding: 12px 24px; margin: 0 5px 10px 5px; background-color: #000000; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px;">View Audit Logs</a>';
	$html .= '<a href="' . esc_url( $settings_url ) . '" style="display: inline-block; padding: 12px 24px; margin: 0 5px 10px 5px; background-color: #ffffff; color: #000000; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px; border: 2px solid #000000;">Manage Settings</a>';
	$html .= '</div>';
	
	$html .= '</div>'; // End email container
	
	$html .= '</body>';
	$html .= '</html>';
	
	return $html;
}