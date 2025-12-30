<?php
/**
 * User Profile Functions
 * 
 * Functions for adding audit information to user profile pages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display User Audit Information on profile page
 * 
 * @param WP_User $user User object being edited
 */
function uas_render_user_audit_meta_box( $user ) {
	// Get last login
	$last_login = get_user_meta( $user->ID, '_user_last_login', true );
	
	// Check if we should show the activity log link
	// Only show if: 1) current user is admin, AND 2) this user has tracked role
	$show_activity_link = false;
	
	if ( current_user_can( 'manage_options' ) ) {
		// Check if this user has a tracked role
		$settings = get_option( 'uas_settings', array() );
		$included_roles = isset( $settings['included_roles'] ) ? $settings['included_roles'] : array();
		
		// If no roles configured, default to all non-subscriber roles
		if ( empty( $included_roles ) ) {
			global $wp_roles;
			if ( ! empty( $wp_roles->get_names() ) ) {
				foreach ( $wp_roles->get_names() as $role_slug => $role_name ) {
					if ( $role_slug !== 'subscriber' ) {
						$included_roles[] = $role_slug;
					}
				}
			}
		}
		
		// Check if user has any tracked role
		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $included_roles, true ) ) {
				$show_activity_link = true;
				break;
			}
		}
	}
	
	// Build audit logs URL
	$logs_url = admin_url( 'users.php?page=user-audit-logs&filter_user=' . $user->ID );
	?>
	
	<h2>User Audit Information</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th>Last Login</th>
			<td>
				<?php if ( $last_login ) : ?>
					<?php echo esc_html( wp_date( 'F j, Y \a\t g:i A', $last_login ) ); ?>
				<?php else : ?>
					<em>Never logged in</em>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $show_activity_link ) : ?>
			<tr>
				<th>Activity Log</th>
				<td>
					<a href="<?php echo esc_url( $logs_url ); ?>" class="button button-secondary">
						View Activity Log &rarr;
					</a>
					<p class="description">
						Shows all role changes, profile updates, and account modifications for this user.
					</p>
				</td>
			</tr>
		<?php endif; ?>
	</table>
	
	<?php
}