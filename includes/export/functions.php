<?php
/**
 * Export Functions
 * 
 * Functions for exporting user audit data to CSV
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export audit data to CSV
 * 
 * Generates and downloads a CSV file with current user audit data
 * Terminates script execution after sending file
 */
function uas_export_csv() {
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions to export user data.' );
	}
	
	// Get audit data
	$users = uas_get_audit_users();
	
	if ( empty( $users ) ) {
		return new WP_Error( 'no_users', 'No users found to export.' );
	}
	
	// Set headers for CSV download
	$filename = 'user-audit-' . gmdate( 'Y-m-d-His' ) . '.csv';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	
	// Build CSV content
	$csv_data = array();
	
	// Add header row
	$csv_data[] = array(
		'User ID',
		'Username',
		'Display Name',
		'Email',
		'Role',
		'Last Login',
		'Edit URL',
	);

	// Add data rows
	foreach ( $users as $user ) {
		$csv_data[] = array(
			$user['ID'],
			$user['username'],
			$user['display_name'],
			$user['email'],
			$user['role'],
			uas_get_user_last_login_timestamp( $user['ID'] ),
			$user['edit_url'],
		);
	}
	
	// Output CSV
	uas_output_csv( $csv_data );
	exit;
}

/**
 * Get CSV content as string
 * 
 * Generates CSV content without triggering download
 * Useful for email attachments or other purposes
 * 
 * @return string CSV content
 */
function uas_get_csv_content() {	
	// Get audit data
	$users = uas_get_audit_users();
	
	if ( empty( $users ) ) {
		return '';
	}
	
	// Build CSV data array
	$csv_data = array();
	
	// Add header row
	$csv_data[] = array(
		'Username',
		'Display Name',
		'Email',
		'Role',
		'Last Login',
		'Edit URL',
	);
	
	// Add data rows
	foreach ( $users as $user ) {
		$csv_data[] = array(
			$user['username'],
			$user['display_name'],
			$user['email'],
			$user['role'],
			uas_get_user_last_login_timestamp( $user['ID'] ),
			$user['edit_url'],
		);
	}
	
	// Use output buffering to capture CSV
	ob_start();
	
	foreach ( $csv_data as $row ) {
		$escaped_row = array();
		foreach ( $row as $field ) {
			if ( strpos( $field, ',' ) !== false || strpos( $field, '"' ) !== false || strpos( $field, "\n" ) !== false ) {
				$field = '"' . str_replace( '"', '""', $field ) . '"';
			}
			$escaped_row[] = $field;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data is manually escaped above per CSV standards
		echo implode( ',', $escaped_row ) . "\n";
	}
	
	return ob_get_clean();
}