<?php
/**
 * Email Functions
 * 
 * Functions for sending audit report emails
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send audit email
 * 
 * Sends an HTML email with user audit data to configured recipients
 * 
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function uas_send_audit_email() {
	// Get settings
	$settings = get_option( 'uas_settings', array() );
	
	// Get recipients
	$recipients = uas_get_email_recipients();
	if ( empty( $recipients ) ) {
		return new WP_Error( 'no_recipients', 'No email recipients configured. Please add at least one email address in the settings.' );
	}
	
	// Get email subject
	$subject = isset( $settings['email_subject'] ) ? $settings['email_subject'] : 'WordPress User Audit Report';
	$subject = sanitize_text_field( $subject );
	
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
	
	// Split by newlines and commas
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
 * Generate HTML email content
 * 
 * Creates formatted HTML table with user audit data
 * No gradients, no gray text - just clean, readable design
 * 
 * @param array $users Array of user data
 * @return string HTML content
 */
function uas_generate_email_html( $users ) {
	$site_name = get_bloginfo( 'name' );
	$site_url = get_site_url();
	$date = date( 'F j, Y' );
	
	// Start building HTML
	$html = '<!DOCTYPE html>';
	$html .= '<html lang="en">';
	$html .= '<head>';
	$html .= '<meta charset="UTF-8">';
	$html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
	$html .= '<title>User Audit Report</title>';
	$html .= '</head>';
	$html .= '<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; color: #000000; max-width: 800px; margin: 0 auto; padding: 20px;">';
	
	// Header
	$html .= '<div style="margin-bottom: 30px;">';
	$html .= '<h1 style="color: #000000; margin: 0 0 10px 0;">User Audit Report</h1>';
	$html .= '<p style="color: #000000; margin: 0;"><strong>' . esc_html( $site_name ) . '</strong> | ' . esc_html( $date ) . '</p>';
	$html .= '</div>';
	
	// Introduction text
	$html .= '<p style="color: #000000; margin-bottom: 20px;">This report contains all users with elevated permissions on your WordPress site. Review this list to ensure all accounts are still necessary and appropriate.</p>';
	
	// Table
	$html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">';
	
	// Table header
	$html .= '<thead>';
	$html .= '<tr style="background-color: #f5f5f5;">';
	$html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600;">Username</th>';
	$html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600;">Display Name</th>';
	$html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600;">Email</th>';
	$html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600;">Role</th>';
	$html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600;">Last Login</th>';
	$html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dddddd; color: #000000; font-weight: 600;">Action</th>';
	$html .= '</tr>';
	$html .= '</thead>';
	
	// Table body
	$html .= '<tbody>';
	foreach ( $users as $user ) {
		$html .= '<tr>';
		$html .= '<td style="padding: 12px; border: 1px solid #dddddd; color: #000000;">' . esc_html( $user['username'] ) . '</td>';
		$html .= '<td style="padding: 12px; border: 1px solid #dddddd; color: #000000;">' . esc_html( $user['display_name'] ) . '</td>';
		$html .= '<td style="padding: 12px; border: 1px solid #dddddd; color: #000000;">' . esc_html( $user['email'] ) . '</td>';
		$html .= '<td style="padding: 12px; border: 1px solid #dddddd; color: #000000;">' . esc_html( $user['role'] ) . '</td>';
		$html .= '<td style="padding: 12px; border: 1px solid #dddddd; color: #000000;">' . esc_html( $user['last_login'] ) . '</td>';
		$html .= '<td style="padding: 12px; border: 1px solid #dddddd; color: #000000;"><a href="' . esc_url( $user['edit_url'] ) . '" style="color: #0073aa; text-decoration: none;">Edit User</a></td>';
		$html .= '</tr>';
	}
	$html .= '</tbody>';
	$html .= '</table>';
	
	// Footer
	$html .= '<p style="color: #000000; font-size: 14px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dddddd;">This is an automated report from <a href="' . esc_url( $site_url ) . '" style="color: #0073aa; text-decoration: none;">' . esc_html( $site_name ) . '</a>. To manage these reports, visit the User Audit Scheduler settings in your WordPress admin.</p>';
	
	$html .= '</body>';
	$html .= '</html>';
	
	return $html;
}