<?php
/**
 * Admin Menu
 * 
 * Functions for setting up the admin menu structure
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add plugin menu to WordPress admin
 * 
 * Adds submenu under Users menu with Settings and Export pages
 */
function uas_add_admin_menu() {
	// Main settings page under Users menu
	add_users_page(
		'User Audit Scheduler Settings',           // Page title
		'User Audit',                    // Menu title
		'manage_options',                 // Capability required
		'user-audit-settings',            // Menu slug
		'uas_render_settings_page'        // Callback function
	);
}

/**
 * Handle admin actions
 * 
 * Processes form submissions for sending test emails and exporting CSV
 * Checks nonces and capabilities before executing
 */
function uas_handle_admin_actions() {
	// Only process on our admin pages
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'user-audit-settings' ) {
		return;
	}
	
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// Handle "Send Test Email" action
	if ( isset( $_POST['uas_send_test_email'] ) && check_admin_referer( 'uas_send_test_email' ) ) {
		$result = uas_send_audit_email();
		
		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'uas_messages',
				'uas_email_error',
				$result->get_error_message(),
				'error'
			);
		} else {
			add_settings_error(
				'uas_messages',
				'uas_email_success',
				'Test email sent successfully! Check your inbox.',
				'success'
			);
		}
	}
	
	// Handle "Export CSV" action
	if ( isset( $_POST['uas_export_csv'] ) && check_admin_referer( 'uas_export_csv' ) ) {
		uas_export_csv();
		// Script execution ends in uas_export_csv() after sending file
	}
}