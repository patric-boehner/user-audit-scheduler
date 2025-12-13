<?php
/**
 * Admin Settings
 * 
 * Functions for registering and displaying plugin settings
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register plugin settings
 * 
 * Uses WordPress Settings API to register our settings
 */
function uas_register_settings() {
	// Register the main settings option
	register_setting(
		'uas_settings_group',        // Option group
		'uas_settings',               // Option name
		'uas_sanitize_settings'       // Sanitization callback
	);
	
	// Add Email Configuration section
	add_settings_section(
		'uas_email_section',                    // Section ID
		'Email Configuration',                  // Section title
		'uas_email_section_callback',           // Callback
		'user-audit-settings'                   // Page slug
	);
	
	// Email Recipients field
	add_settings_field(
		'email_recipients',                     // Field ID
		'Email Recipients',                     // Field title
		'uas_email_recipients_callback',        // Callback
		'user-audit-settings',                  // Page slug
		'uas_email_section'                     // Section ID
	);
	
	// Email Subject field
	add_settings_field(
		'email_subject',                        // Field ID
		'Email Subject',                        // Field title
		'uas_email_subject_callback',           // Callback
		'user-audit-settings',                  // Page slug
		'uas_email_section'                     // Section ID
	);
}

/**
 * Sanitize settings before saving
 * 
 * Validates and cleans all input data
 * 
 * @param array $input Raw input from form
 * @return array Sanitized settings
 */
function uas_sanitize_settings( $input ) {
	$sanitized = array();
	
	// Sanitize email recipients
	if ( isset( $input['email_recipients'] ) ) {
		$sanitized['email_recipients'] = sanitize_textarea_field( $input['email_recipients'] );
	}
	
	// Sanitize email subject
	if ( isset( $input['email_subject'] ) ) {
		$sanitized['email_subject'] = sanitize_text_field( $input['email_subject'] );
	}
	
	return $sanitized;
}

/**
 * Email section description
 */
function uas_email_section_callback() {
	echo '<p>Configure where audit reports should be sent and customize the email subject line.</p>';
}

/**
 * Email recipients field
 */
function uas_email_recipients_callback() {
	$settings = get_option( 'uas_settings', array() );
	$value = isset( $settings['email_recipients'] ) ? $settings['email_recipients'] : '';
	
	echo '<textarea name="uas_settings[email_recipients]" rows="5" cols="30" class="large-text">' . esc_textarea( $value ) . '</textarea>';
	echo '<p class="description">Enter one email address per line. These addresses will receive the audit reports.</p>';
}

/**
 * Email subject field
 */
function uas_email_subject_callback() {
	$settings = get_option( 'uas_settings', array() );
	$value = isset( $settings['email_subject'] ) ? $settings['email_subject'] : 'WordPress User Audit Report';
	
	echo '<input type="text" name="uas_settings[email_subject]" value="' . esc_attr( $value ) . '" class="regular-text">';
	echo '<p class="description">The subject line for audit report emails.</p>';
}

/**
 * Render the settings page
 * 
 * Displays the main settings page with forms and action buttons
 */
function uas_render_settings_page() {
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Get current user count for display
	$users = uas_get_audit_users();
	$user_count = count( $users );
	
	?>
	<div class="wrap" style="max-width: 1200px;">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		
		<?php settings_errors( 'uas_messages' ); ?>
		
		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
			<h2>About This Plugin</h2>
			<p>User Audit Scheduler helps you maintain security by keeping track of who has elevated permissions on your WordPress site. Currently tracking <strong><?php echo esc_html( $user_count ); ?></strong> user<?php echo $user_count !== 1 ? 's' : ''; ?> with elevated permissions.</p>
			<p>Phase 1 features: Manual email sending, CSV export, and last login tracking.</p>
		</div>
		
		<!-- Settings Form -->
		<form method="post" action="options.php" style="max-width: 600px;">
			<?php
			settings_fields( 'uas_settings_group' );
			do_settings_sections( 'user-audit-settings' );
			submit_button( 'Save Settings' );
			?>
		</form>
		
		<!-- Testing & Actions Section -->
		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; max-width: 500px;">
			<h2>Testing & Actions</h2>
			
			<div style="margin-bottom: 20px;">
				<form method="post" action="" style="display: inline-block;">
					<?php wp_nonce_field( 'uas_send_test_email' ); ?>
					<input type="submit" name="uas_send_test_email" class="button button-primary" value="Send Test Email Now">
					<p class="description">Send a test audit email to the configured recipients above.</p>
				</form>
			</div>
			
			<div style="margin-bottom: 20px;">
				<form method="post" action="" style="display: inline-block;">
					<?php wp_nonce_field( 'uas_export_csv' ); ?>
					<input type="submit" name="uas_export_csv" class="button button-secondary" value="Download Current Audit (CSV)">
					<p class="description">Download a CSV file with the current user audit data.</p>
				</form>
			</div>
		</div>
		
		<!-- User Preview Section -->
		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
			<h2>Current Users Preview</h2>
			<p>This is a preview of the users that will be included in audit reports:</p>
			
			<?php if ( ! empty( $users ) ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Username</th>
							<th>Display Name</th>
							<th>Email</th>
							<th>Role</th>
							<th>Last Login</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $users as $user ) : ?>
							<tr>
								<td><?php echo esc_html( $user['username'] ); ?></td>
								<td><?php echo esc_html( $user['display_name'] ); ?></td>
								<td><?php echo esc_html( $user['email'] ); ?></td>
								<td><?php echo esc_html( $user['role'] ); ?></td>
								<td><?php echo esc_html( $user['last_login'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p>No users with elevated permissions found.</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}