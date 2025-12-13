<?php
/**
 * Plugin Name: User Audit Scheduler
 * Plugin URI: https://example.com/user-audit-scheduler
 * Description: Streamline periodic WordPress user audits by automating report generation and maintaining change logs
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: user-audit-scheduler
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'UAS_VERSION', '1.0.0' );
define( 'UAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once UAS_PLUGIN_DIR . 'includes/core/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/tracking/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/export/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/email/functions.php';
require_once UAS_PLUGIN_DIR . 'includes/admin/menu.php';
require_once UAS_PLUGIN_DIR . 'includes/admin/settings.php';
require_once UAS_PLUGIN_DIR . 'includes/admin/users-list.php';

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
	
	// Register settings
	add_action( 'admin_init', 'uas_register_settings' );
	
	// Handle admin actions (send test email, export CSV)
	add_action( 'admin_init', 'uas_handle_admin_actions' );
	
	// Add Last Login column to users list
	add_filter( 'manage_users_columns', 'uas_add_last_login_column' );
	add_filter( 'manage_users_custom_column', 'uas_show_last_login_column', 10, 3 );
	add_filter( 'manage_users_sortable_columns', 'uas_make_last_login_sortable' );
	add_action( 'pre_get_users', 'uas_sort_last_login_column' );
}
add_action( 'plugins_loaded', 'uas_init' );

/**
 * Activation hook - runs when plugin is activated
 * 
 * Sets default options if they don't exist
 */
function uas_activate() {
	// Set default options if they don't exist
	$default_options = array(
		'email_recipients' => get_option( 'admin_email' ), // Default to site admin email
		'email_subject'    => 'WordPress User Audit Report',
	);
	
	add_option( 'uas_settings', $default_options );
}
register_activation_hook( __FILE__, 'uas_activate' );

/**
 * Deactivation hook - runs when plugin is deactivated
 * 
 * Currently does nothing, but available for future cleanup
 */
function uas_deactivate() {
	// Future: Remove scheduled cron events here when we add Phase 2
}
register_deactivation_hook( __FILE__, 'uas_deactivate' );