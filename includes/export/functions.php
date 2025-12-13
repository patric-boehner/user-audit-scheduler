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
	// Get audit data
	$users = uas_get_audit_users();
	
	if ( empty( $users ) ) {
		return new WP_Error( 'no_users', 'No users found to export.' );
	}
	
	// Set headers for CSV download
	$filename = 'user-audit-' . date( 'Y-m-d-His' ) . '.csv';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	
	// Open output stream
	$output = fopen( 'php://output', 'w' );
	
	// Write CSV header row
	fputcsv( $output, array(
		'Username',
		'Display Name',
		'Email',
		'Role',
		'Last Login',
		'Edit URL',
	) );
	
	// Write data rows
	foreach ( $users as $user ) {
		fputcsv( $output, array(
			$user['username'],
			$user['display_name'],
			$user['email'],
			$user['role'],
			uas_get_user_last_login_timestamp( $user['ID'] ),
			$user['edit_url'],
		) );
	}
	
	fclose( $output );
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
	$users = uas_get_audit_users();
	
	if ( empty( $users ) ) {
		return '';
	}
	
	// Use output buffering to capture CSV content
	ob_start();
	$output = fopen( 'php://output', 'w' );
	
	// Write CSV header row
	fputcsv( $output, array(
		'Username',
		'Display Name',
		'Email',
		'Role',
		'Last Login',
		'Edit URL',
	) );
	
	// Write data rows
	foreach ( $users as $user ) {
		fputcsv( $output, array(
			$user['username'],
			$user['display_name'],
			$user['email'],
			$user['role'],
			uas_get_user_last_login_timestamp( $user['ID'] ),
			$user['edit_url'],
		) );
	}
	
	fclose( $output );
	return ob_get_clean();
}