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
	
	// Add Automated Schedule section
	add_settings_section(
		'uas_schedule_section',                 // Section ID
		'Automated Schedule',                   // Section title
		'uas_schedule_section_callback',        // Callback
		'user-audit-settings'                   // Page slug
	);
	
	// Schedule Enabled field
	add_settings_field(
		'schedule_enabled',                     // Field ID
		'Enable Automated Emails',              // Field title
		'uas_schedule_enabled_callback',        // Callback
		'user-audit-settings',                  // Page slug
		'uas_schedule_section'                  // Section ID
	);
	
	// Schedule Frequency field
	add_settings_field(
		'schedule_frequency',                   // Field ID
		'Email Frequency',                      // Field title
		'uas_schedule_frequency_callback',      // Callback
		'user-audit-settings',                  // Page slug
		'uas_schedule_section'                  // Section ID
	);
	
	// Add Report Options section
	add_settings_section(
		'uas_report_section',                   // Section ID
		'Report Options',                       // Section title
		'uas_report_section_callback',          // Callback
		'user-audit-settings'                   // Page slug
	);
	
	// Included Roles field
	add_settings_field(
		'included_roles',                       // Field ID
		'Included User Roles',                  // Field title
		'uas_included_roles_callback',          // Callback
		'user-audit-settings',                  // Page slug
		'uas_report_section'                    // Section ID
	);
}

/**
 * Sanitize settings before saving
 * 
 * Validates and cleans all input data
 * Also handles scheduling/unscheduling based on settings
 * 
 * @param array $input Raw input from form
 * @return array Sanitized settings
 */
function uas_sanitize_settings( $input ) {
	$sanitized = array();
	
	// Get current settings to compare for changes
	$settings = get_option( 'uas_settings', array() );
	
	// Sanitize email recipients
	if ( isset( $input['email_recipients'] ) ) {
		$sanitized['email_recipients'] = sanitize_textarea_field( $input['email_recipients'] );
	}
	
	// Sanitize email subject
	if ( isset( $input['email_subject'] ) ) {
		$sanitized['email_subject'] = sanitize_text_field( $input['email_subject'] );
	}
	
	// Sanitize schedule enabled (checkbox)
	$old_enabled = isset( $settings['schedule_enabled'] ) ? $settings['schedule_enabled'] : false;
	$sanitized['schedule_enabled'] = isset( $input['schedule_enabled'] ) ? true : false;
	
	// Sanitize schedule frequency
	$old_frequency = isset( $settings['schedule_frequency'] ) ? $settings['schedule_frequency'] : 'monthly';
	if ( isset( $input['schedule_frequency'] ) ) {
		$frequency = sanitize_text_field( $input['schedule_frequency'] );
		// Only allow valid frequencies
		if ( in_array( $frequency, array( 'weekly', 'monthly', 'quarterly' ) ) ) {
			$sanitized['schedule_frequency'] = $frequency;
		} else {
			$sanitized['schedule_frequency'] = 'monthly'; // Default fallback
		}
	} else {
		// If not in input, preserve existing value
		$sanitized['schedule_frequency'] = $old_frequency;
	}
	
	// Sanitize included roles (array of role slugs)
	if ( isset( $input['included_roles'] ) && is_array( $input['included_roles'] ) ) {
		$sanitized['included_roles'] = array_map( 'sanitize_text_field', $input['included_roles'] );
	} else {
		// Default to all non-subscriber roles if not set
		$sanitized['included_roles'] = array();
	}
	
	// Handle scheduling based on settings
	if ( $sanitized['schedule_enabled'] ) {
		// Check if schedule actually exists
		$was_scheduled = uas_is_scheduled();
		
		// Schedule or reschedule emails
		uas_schedule_audit_email( $sanitized['schedule_frequency'] );
		
		// Add success message
		$next_send = uas_get_next_scheduled_send();
		if ( ! $old_enabled ) {
			// Just enabled
			add_settings_error(
				'uas_messages',
				'uas_schedule_enabled',
				'Automated emails enabled! Next email scheduled for: ' . $next_send,
				'success'
			);
		} elseif ( $old_frequency !== $sanitized['schedule_frequency'] ) {
			// Changed frequency
			add_settings_error(
				'uas_messages',
				'uas_schedule_updated',
				'Email schedule updated! Next email scheduled for: ' . $next_send,
				'success'
			);
		} elseif ( ! $was_scheduled ) {
			// Was enabled in settings but cron was missing (e.g. after reactivation)
			add_settings_error(
				'uas_messages',
				'uas_schedule_restored',
				'Automated emails re-enabled! Next email scheduled for: ' . $next_send,
				'success'
			);
		}
	} else {
		// Disable scheduled emails
		uas_clear_scheduled_audit_email();
		
		// Add notice if we just disabled it
		if ( $old_enabled ) {
			add_settings_error(
				'uas_messages',
				'uas_schedule_disabled',
				'Automated emails disabled.',
				'info'
			);
		}
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
	
	echo '<input type="text" name="uas_settings[email_recipients]" value="' . esc_attr( $value ) . '" class="large-text">';
	echo '<p class="description">Enter email addresses separated by commas (e.g., admin@example.com, manager@example.com)</p>';
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
 * Schedule section description
 */
function uas_schedule_section_callback() {
	echo '<p>Configure automated email delivery. When enabled, audit reports will be sent automatically on your chosen schedule.</p>';
}

/**
 * Schedule enabled checkbox field
 */
function uas_schedule_enabled_callback() {
	$settings = get_option( 'uas_settings', array() );
	$enabled = isset( $settings['schedule_enabled'] ) ? $settings['schedule_enabled'] : false;
	
	echo '<label>';
	echo '<input type="checkbox" name="uas_settings[schedule_enabled]" value="1"' . checked( $enabled, true, false ) . '>';
	echo ' Send audit emails automatically on the schedule below';
	echo '</label>';
	
	// Show current schedule status
	if ( uas_is_scheduled() ) {
		$next_send = uas_get_next_scheduled_send();
		echo '<p class="description">Currently enabled. Next email: ' . esc_html( $next_send ) . '</p>';
	} elseif ( $enabled ) {
		// Checkbox is checked but no cron scheduled (orphaned setting)
		echo '<p class="description" style="color: #d63638;">Enabled in settings but not scheduled. Click "Save Settings" to activate.</p>';
	} else {
		echo '<p class="description">Currently disabled.</p>';
	}
}

/**
 * Schedule frequency dropdown field
 */
function uas_schedule_frequency_callback() {
	$settings = get_option( 'uas_settings', array() );
	$frequency = isset( $settings['schedule_frequency'] ) ? $settings['schedule_frequency'] : 'monthly';
	
	echo '<select name="uas_settings[schedule_frequency]">';
	echo '<option value="weekly"' . selected( $frequency, 'weekly', false ) . '>Weekly (Every Monday at 9am)</option>';
	echo '<option value="monthly"' . selected( $frequency, 'monthly', false ) . '>Monthly (1st of each month at 9am)</option>';
	echo '<option value="quarterly"' . selected( $frequency, 'quarterly', false ) . '>Quarterly (Jan 1, Apr 1, Jul 1, Oct 1 at 9am)</option>';
	echo '</select>';
	echo '<p class="description">How often should automated audit emails be sent?</p>';
}

/**
 * Report section description
 */
function uas_report_section_callback() {
	echo '<p>Choose which user roles should be included in audit reports and logs.</p>';
	echo '<p><strong>Conditional Logging:</strong> Only security-relevant changes are logged. This includes users created with selected roles, role changes that involve selected roles, and profile updates for users with selected roles. Subscriber-only activity (registrations, email changes, deletions) is not logged to prevent database bloat on membership sites.</p>';
}

/**
 * Included roles checkboxes field
 */
function uas_included_roles_callback() {
	$settings = get_option( 'uas_settings', array() );
	$included_roles = isset( $settings['included_roles'] ) ? $settings['included_roles'] : array();
	
	// If empty, default to all non-subscriber roles
	if ( empty( $included_roles ) ) {
		global $wp_roles;
		$all_roles = $wp_roles->get_names();
		foreach ( $all_roles as $role_slug => $role_name ) {
			if ( $role_slug !== 'subscriber' ) {
				$included_roles[] = $role_slug;
			}
		}
	}
	
	// Get all WordPress roles
	global $wp_roles;
	$all_roles = $wp_roles->get_names();
	
	echo '<fieldset>';
	foreach ( $all_roles as $role_slug => $role_name ) {
		$is_checked = in_array( $role_slug, $included_roles );
		$role_display = translate_user_role( $role_name );
		
		echo '<label style="display: block; margin-bottom: 8px;">';
		echo '<input type="checkbox" name="uas_settings[included_roles][]" value="' . esc_attr( $role_slug ) . '"';
		checked( $is_checked, true );
		echo '>';
		echo ' ' . esc_html( $role_display );
		echo '</label>';
	}
	echo '</fieldset>';
	echo '<p class="description">Select which user roles are security-relevant. Only these roles will be tracked in audit logs and reports. Changes involving these roles (promotions, demotions, deletions) will be logged.</p>';
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
		
		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; max-width: 800px;">
			<h2>About This Plugin</h2>
			<p>Configure which user roles to audit. By default, all roles except subscribers are included. Only security-relevant changes are tracked: role changes, new users, deleted users, and email/display name updates.</p>
			<p>Currently tracking <strong><?php echo esc_html( $user_count ); ?></strong> user<?php echo $user_count !== 1 ? 's' : ''; ?> with elevated permissions.</p>
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