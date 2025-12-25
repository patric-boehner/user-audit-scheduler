<?php
/**
 * Plugin Name: User Audit Scheduler
 * Plugin URI: 
 * Description: Streamline your WordPress user audits with automated logging of role changes, profile updates, and account creations/deletions.
 * Version: 1.3.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Patrick Boehner
 * Author URI: https://patrickboehner.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: user-audit-scheduler
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'UAS_VERSION', '1.3.1' );
define( 'UAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once UAS_PLUGIN_DIR . 'includes/core/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/tracking/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/export/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/email/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/scheduler/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/logging/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/admin/menu.php';
require_once UAS_PLUGIN_DIR . 'includes/admin/settings.php';
require_once UAS_PLUGIN_DIR . 'includes/admin/users-list.php';
require_once UAS_PLUGIN_DIR . 'includes/admin/audit-logs.php';

/**
 * Initialize the plugin
 * 
 * Hooks into WordPress to set up all plugin functionality
 */
function uas_init() {
	// Track last login time when users log in
	add_action( 'wp_login', 'uas_track_user_login', 10, 2 );
	
	// Set up admin menu
	add_action( 'admin_menu', 'uas_add_admin_menu' );
	add_action( 'admin_menu', 'uas_add_audit_logs_menu' );
	
	// Register settings
	add_action( 'admin_init', 'uas_register_settings' );
	
	// Handle admin actions (send test email, export CSV, export logs)
	add_action( 'admin_init', 'uas_handle_admin_actions' );
	add_action( 'admin_init', 'uas_handle_audit_logs_actions' );
	
	// Add Last Login column to users list
	add_filter( 'manage_users_columns', 'uas_add_last_login_column' );
	add_filter( 'manage_users_custom_column', 'uas_show_last_login_column', 10, 3 );
	add_filter( 'manage_users_sortable_columns', 'uas_make_last_login_sortable' );
	add_action( 'pre_get_users', 'uas_sort_last_login_column' );
	
	// Hook for scheduled email sending
	add_action( 'uas_send_scheduled_email', 'uas_send_scheduled_email_callback' );
	
	// Logging hooks - track user changes
	add_action( 'user_register', 'uas_log_user_created' );
	add_action( 'set_user_role', 'uas_log_role_change', 10, 3 );
	add_action( 'delete_user', 'uas_log_user_deleted' );
	add_action( 'profile_update', 'uas_log_profile_update', 10, 2 );

	// Log cleanup - runs daily
	add_action( 'uas_cleanup_old_logs', 'uas_cleanup_old_logs_callback' );
}
add_action( 'plugins_loaded', 'uas_init' );

/**
 * Add settings link to plugin row actions
 * 
 * Adds a "Settings" link on the Plugins page for quick access
 * 
 * @param array $links Existing plugin action links
 * @return array Modified links with Settings added
 */
function uas_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'users.php?page=user-audit-settings' ) . '">Settings</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'uas_add_settings_link' );

/**
 * Activation hook - runs when plugin is activated
 * 
 * Sets default options if they don't exist and creates database table
 */
function uas_activate() {
	// Get all non-subscriber roles for default
	global $wp_roles;
	$default_roles = array();
	if ( ! empty( $wp_roles->get_names() ) ) {
		foreach ( $wp_roles->get_names() as $role_slug => $role_name ) {
			if ( $role_slug !== 'subscriber' ) {
				$default_roles[] = $role_slug;
			}
		}
	}
	
	// Set default options if they don't exist
	$default_options = array(
		'email_recipients'     => get_option( 'admin_email' ),
		'email_subject'        => 'User Audit Report: Review Changes',
		'schedule_enabled'     => false,
		'schedule_frequency'   => 'monthly',
		'included_roles'       => $default_roles,
	);
	
	add_option( 'uas_settings', $default_options );
	
	// Create audit log database table
	uas_create_log_table();

	// Schedule daily log cleanup at 3am
	if ( ! wp_next_scheduled( 'uas_cleanup_old_logs' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 3:00am' ), 'daily', 'uas_cleanup_old_logs' );
	}
}
register_activation_hook( __FILE__, 'uas_activate' );

/**
 * Deactivation hook - runs when plugin is deactivated
 * 
 * Removes all scheduled cron events
 */
function uas_deactivate() {
	wp_clear_scheduled_hook( 'uas_send_scheduled_email' );
	wp_clear_scheduled_hook( 'uas_cleanup_old_logs' );
	
	// Update settings to mark schedule as disabled
	$settings = get_option( 'uas_settings', array() );
	if ( isset( $settings['schedule_enabled'] ) && $settings['schedule_enabled'] ) {
		$settings['schedule_enabled'] = false;
		update_option( 'uas_settings', $settings );
	}
}
register_deactivation_hook( __FILE__, 'uas_deactivate' );
