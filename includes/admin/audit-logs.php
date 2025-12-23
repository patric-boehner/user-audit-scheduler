<?php
/**
 * Audit Logs Admin Page
 * 
 * Functions for displaying and managing the audit log interface
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Audit Logs submenu page
 * 
 * Adds a new page under Users menu for viewing audit logs
 */
function uas_add_audit_logs_menu() {
	add_users_page(
		'User Audit Logs',               // Page title
		'Audit Logs',                    // Menu title
		'manage_options',                 // Capability required
		'user-audit-logs',                // Menu slug
		'uas_render_audit_logs_page'      // Callback function
	);
}

/**
 * Handle audit logs page actions
 * 
 * Processes export CSV action
 */
function uas_handle_audit_logs_actions() {
	// Only process on our audit logs page
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'user-audit-logs' ) {
		return;
	}
	
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// Handle "Export Logs" action
	if ( isset( $_POST['uas_export_logs'] ) && check_admin_referer( 'uas_export_logs' ) ) {
		uas_export_logs_csv();
		// Script execution ends in uas_export_logs_csv() after sending file
	}
}

/**
 * Render the audit logs page
 * 
 * Displays filterable table of audit log entries
 */
function uas_render_audit_logs_page() {
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Get filter parameters from URL
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters, no data modification
	$filter_user_id = isset( $_GET['filter_user'] ) ? intval( $_GET['filter_user'] ) : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters, no data modification
	$filter_change_type = isset( $_GET['filter_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_type'] ) ) : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters, no data modification
	$filter_date_from = isset( $_GET['filter_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_from'] ) ) : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters, no data modification
	$filter_date_to = isset( $_GET['filter_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_to'] ) ) : null;
	
	// Pagination
	$per_page = 50;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter, no data modification
	$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
	$offset = ( $current_page - 1 ) * $per_page;
	
	// Build query args
	$query_args = array(
		'limit'  => $per_page,
		'offset' => $offset,
	);
	
	if ( $filter_user_id ) {
		$query_args['user_id'] = $filter_user_id;
	}
	
	if ( $filter_change_type ) {
		$query_args['change_type'] = $filter_change_type;
	}
	
	if ( $filter_date_from ) {
		$query_args['date_from'] = $filter_date_from . ' 00:00:00';
	}
	
	if ( $filter_date_to ) {
		$query_args['date_to'] = $filter_date_to . ' 23:59:59';
	}
	
	// Get logs and total count
	// Conditional logging means we only store security-relevant logs
	// No need for additional filtering at display time
	$logs = uas_get_log_entries( $query_args );
	$total_items = uas_get_log_entries_count( $query_args );
	$total_pages = ceil( $total_items / $per_page );
	
	?>
	<div class="wrap" style="max-width: 1400px;">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		
		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
			<h2>About Audit Logs</h2>
			<p>This page shows a complete history of security-relevant user changes. The system uses conditional logging to focus on what matters:</p>
			<ul style="margin-left: 20px;">
				<li><strong>Users created with elevated roles</strong> - Administrator, Editor, etc.</li>
				<li><strong>Role changes that cross the security boundary</strong> - Subscriber promoted to Editor, Editor demoted to Subscriber, etc.</li>
				<li><strong>Elevated role changes</strong> - Editor to Administrator, etc.</li>
				<li><strong>Profile updates for elevated roles</strong> - Email and display name changes</li>
				<li><strong>Deletions of elevated roles</strong> - When admins, editors, etc. are removed</li>
			</ul>
			<p><strong>Not logged:</strong> Subscriber registrations, subscriber email changes, subscriber deletions, and other non-security-relevant activity. This keeps the log focused and prevents database bloat on membership sites.</p>
			<p><strong>All logs are permanent:</strong> Entries cannot be modified or deleted.</p>
		</div>
		
		<!-- Filters -->
		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
			<h2>Filter Logs</h2>
			<form method="get" action="">
				<input type="hidden" name="page" value="user-audit-logs">
				
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
					<div>
						<label for="filter_user" style="display: block; margin-bottom: 5px; font-weight: 600;">User</label>
						<select name="filter_user" id="filter_user" style="width: 100%;">
							<option value="">All Users</option>
							<?php
							// Get all users for dropdown
							$all_users = get_users( array( 'orderby' => 'display_name' ) );
							foreach ( $all_users as $user ) {
								echo '<option value="' . esc_attr( $user->ID ) . '"';
								selected( $filter_user_id, $user->ID );
								echo '>' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_login ) . ')</option>';
							}
							?>
						</select>
					</div>
					
					<div>
						<label for="filter_type" style="display: block; margin-bottom: 5px; font-weight: 600;">Change Type</label>
						<select name="filter_type" id="filter_type" style="width: 100%;">
							<option value="">All Types</option>
							<option value="user_created" <?php selected( $filter_change_type, 'user_created' ); ?>>User Created</option>
							<option value="role_changed" <?php selected( $filter_change_type, 'role_changed' ); ?>>Role Changed</option>
							<option value="user_deleted" <?php selected( $filter_change_type, 'user_deleted' ); ?>>User Deleted</option>
							<option value="profile_updated" <?php selected( $filter_change_type, 'profile_updated' ); ?>>Profile Updated</option>
						</select>
					</div>
					
					<div>
						<label for="filter_from" style="display: block; margin-bottom: 5px; font-weight: 600;">Date From</label>
						<input type="date" name="filter_from" id="filter_from" value="<?php echo esc_attr( $filter_date_from ); ?>" style="width: 100%;">
					</div>
					
					<div>
						<label for="filter_to" style="display: block; margin-bottom: 5px; font-weight: 600;">Date To</label>
						<input type="date" name="filter_to" id="filter_to" value="<?php echo esc_attr( $filter_date_to ); ?>" style="width: 100%;">
					</div>
				</div>
				
				<div>
					<input type="submit" class="button button-primary" value="Apply Filters">
					<a href="<?php echo esc_url( admin_url( 'users.php?page=user-audit-logs' ) ); ?>" class="button">Clear Filters</a>
				</div>
			</form>
		</div>
		
		<!-- Export Section -->
		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
			<h2>Export Logs</h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'uas_export_logs' ); ?>
				<input type="submit" name="uas_export_logs" class="button button-secondary" value="Download Logs (CSV)">
				<p class="description">Export all audit logs to CSV. Respects current filters if applied.</p>
			</form>
		</div>
		
		<!-- Results Summary -->
		<div style="margin: 20px 0;">
			<p><strong>Showing <?php echo number_format( count( $logs ) ); ?> of <?php echo number_format( $total_items ); ?> log entries</strong></p>
		</div>
		
		<!-- Audit Logs Table -->
		<?php if ( ! empty( $logs ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 140px;">Date/Time</th>
						<th style="width: 120px;">Username</th>
						<th style="width: 120px;">Change Type</th>
						<th style="width: 150px;">Old Value</th>
						<th style="width: 150px;">New Value</th>
						<th>Notes</th>
						<th style="width: 120px;">Changed By</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $log->change_date ) ) ); ?></td>
							<td>
								<strong><?php echo esc_html( $log->username ); ?></strong>
								<?php if ( $log->change_type !== 'user_deleted' && get_userdata( $log->user_id ) ) : ?>
									<br><a href="<?php echo esc_url( get_edit_user_link( $log->user_id ) ); ?>">View User</a>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( uas_format_change_type( $log->change_type ) ); ?></td>
							<td><?php echo esc_html( $log->old_value ? $log->old_value : 'â€”' ); ?></td>
							<td><?php echo esc_html( $log->new_value ? $log->new_value : 'â€”' ); ?></td>
							<td><?php echo esc_html( $log->notes ); ?></td>
							<td><?php echo esc_html( $log->changed_by_username ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			
			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div style="margin-top: 20px;">
					<?php
					$pagination_args = array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $current_page,
						'total'     => $total_pages,
						'prev_text' => '&laquo; Previous',
						'next_text' => 'Next &raquo;',
					);
					// paginate_links() returns escaped output
					echo wp_kses_post( paginate_links( $pagination_args ) );
					?>
				</div>
			<?php endif; ?>
			
		<?php else : ?>
			<div style="background: #fff; border: 1px solid #ccd0d4; padding: 40px; margin: 20px 0; text-align: center;">
				<p><strong>No audit logs found.</strong></p>
				<p>Logs will appear here as users are created, modified, or deleted.</p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Format change type for display
 * 
 * Converts database change_type value to human-readable format
 * 
 * @param string $change_type Database change type
 * @return string Formatted change type
 */
function uas_format_change_type( $change_type ) {
	$types = array(
		'user_created'    => 'User Created',
		'role_changed'    => 'Role Changed',
		'user_deleted'    => 'User Deleted',
		'profile_updated' => 'Profile Updated',
	);
	
	return isset( $types[ $change_type ] ) ? $types[ $change_type ] : $change_type;
}

/**
 * Export audit logs to CSV
 * 
 * Generates and downloads a CSV file with audit log data
 * Respects any active filters
 * Terminates script execution after sending file
 */
function uas_export_logs_csv() {
	// Get filter parameters from URL (same as display page)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters, nonce checked in calling function
	$filter_user_id = isset( $_GET['filter_user'] ) ? intval( $_GET['filter_user'] ) : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters, nonce checked in calling function
	$filter_change_type = isset( $_GET['filter_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_type'] ) ) : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters, nonce checked in calling function
	$filter_date_from = isset( $_GET['filter_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_from'] ) ) : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters, nonce checked in calling function
	$filter_date_to = isset( $_GET['filter_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_to'] ) ) : null;
	
	// Build query args (no limit for export - get all matching records)
	$query_args = array(
		'limit' => 0, // No limit
	);
	
	if ( $filter_user_id ) {
		$query_args['user_id'] = $filter_user_id;
	}
	
	if ( $filter_change_type ) {
		$query_args['change_type'] = $filter_change_type;
	}
	
	if ( $filter_date_from ) {
		$query_args['date_from'] = $filter_date_from . ' 00:00:00';
	}
	
	if ( $filter_date_to ) {
		$query_args['date_to'] = $filter_date_to . ' 23:59:59';
	}
	
	// Get logs - conditional logging means all stored logs are relevant
	$logs = uas_get_log_entries( $query_args );
	
	if ( empty( $logs ) ) {
		wp_die( 'No logs found to export.' );
	}
	
	// Set headers for CSV download
	$filename = 'user-audit-logs-' . gmdate( 'Y-m-d-His' ) . '.csv';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	
	// Build CSV content
	$csv_data = array();
	
	// Add header row
	$csv_data[] = array(
		'Date/Time',
		'User ID',
		'Username',
		'Display Name',
		'Email',
		'Change Type',
		'Old Value',
		'New Value',
		'Changed By ID',
		'Changed By Username',
		'Notes',
	);
	
	// Add data rows
	foreach ( $logs as $log ) {
		$csv_data[] = array(
			$log->change_date,
			$log->user_id,
			$log->username,
			$log->display_name,
			$log->user_email,
			uas_format_change_type( $log->change_type ),
			$log->old_value,
			$log->new_value,
			$log->changed_by_id,
			$log->changed_by_username,
			$log->notes,
		);
	}
	
	// Output CSV
	uas_output_csv( $csv_data );
	exit;
}