=== User Audit Scheduler ===
Contributors: patrick-b
Tags: user management, audit, security, admin, user tracking
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Streamline periodic WordPress user audits by automating report generation and maintaining comprehensive change logs.

== Description ==

User Audit Scheduler helps WordPress administrators keep track of users with elevated permissions and maintain a complete audit trail of all user changes. It provides:

* **Last Login Tracking**: Automatically tracks when users log in
* **Automated Email Reports**: Schedule weekly, monthly, or quarterly audit emails
* **Manual Email Reports**: Send audit reports on demand to configured recipients
* **CSV Export**: Download user audit data as a spreadsheet
* **Change Logging**: Complete history of user creation, role changes, deletions, and profile updates
* **Filterable Audit Logs**: Review changes by user, date range, or change type
* **Clean Interface**: Simple settings page under Users menu

= Conditional Logging Approach =

The plugin uses smart conditional logging to focus on security-relevant changes:

**What IS Logged:**
* User creation with elevated roles (Administrator, Editor, Author, etc.)
* Role changes crossing the security boundary (Subscriber → Editor, Editor → Subscriber, etc.)
* Profile updates for elevated roles (email and display name changes)
* Deletions of elevated roles

**What is NOT Logged:**
* Subscriber registrations (unless Subscriber is marked as audited)
* Subscriber email/profile changes
* Subscriber deletions
* Role changes between non-audited roles

This approach prevents database bloat on membership sites with thousands of subscribers while capturing all security-relevant events.

= Features =

**Phase 1 - Core Tracking & Manual Reporting** ✓
* Basic user list generation with configurable role selection
* Manual email sending with HTML table
* CSV export with all user data
* Last login tracking via user meta
* Last Login column in WordPress Users list (sortable!)

**Phase 2 - Automation** ✓
* Scheduled email delivery (weekly, monthly, quarterly)
* Automated cron-based sending
* Schedule management with visual status indicators
* Intelligent schedule restoration after plugin reactivation

**Phase 3 - Change Logging** ✓
* Comprehensive audit log database with conditional logging
* Smart logging: Only security-relevant changes are logged
* Records who made each change and when
* Filterable log display by user, change type, and date range
* CSV export of audit logs
* Hard-coded snapshots (data persists even after user deletion)
* Automatic contextual notes for all changes

== Installation ==

1. Upload the `user-audit-scheduler` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Users → User Audit Settings to configure email settings
4. Go to Users → Audit Logs to view change history

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. The plugin only tracks login times and user changes, which are infrequent operations. The conditional logging approach ensures minimal database impact.

= Will it log all my subscribers on a membership site? =

No! By default, subscriber activity is not logged. Only users with elevated roles (Administrator, Editor, Author, Contributor) are tracked. This prevents database bloat on membership sites.

= Can I customize which roles are tracked? =

Yes! Go to Users → User Audit Settings and select which roles should be considered "security-relevant" in the Report Options section.

= What happens to logs when a user is deleted? =

The logs persist! All user information is hard-coded in the log entry, so you maintain a complete forensic record even after user deletion.

= Can I export the logs? =

Yes! The Audit Logs page has a "Download Logs (CSV)" button that exports all logs (or filtered logs) to a spreadsheet.

= Does the plugin work with custom user roles? =

Yes! Any custom roles created by other plugins will appear in the role selection options.

== Screenshots ==

1. Settings page with email configuration and role selection
2. Audit Logs page showing filterable change history
3. Last Login column in WordPress Users list
4. Email report with user audit data

== Changelog ==

= 1.3.0 - 2024-12-22 =
* Added: Phase 3 - User Change Logging
* Added: Custom database table for storing audit logs
* Added: Automatic tracking of security-relevant user changes
* Added: Conditional logging approach (only elevated roles)
* Added: New "Audit Logs" admin page under Users menu
* Added: Filterable audit log display (by user, change type, date range)
* Added: Pagination support for large log datasets
* Added: CSV export functionality for audit logs
* Added: Hard-coded user data in logs (persists after deletion)
* Added: Automatic notes generation for all logged events
* Changed: Implemented conditional logging to prevent database bloat
* Fixed: Prevented duplicate log entries during user creation
* Technical: Created wp_uas_audit_log database table with indexes
* Technical: Enhanced uninstall script to remove audit log table

= 1.2.0 - 2024-12-21 =
* Added: Role selection settings for configurable audit reports
* Added: New "Report Options" section with checkbox interface
* Added: Multi-role support in user filtering
* Changed: Updated user filtering logic for role-based inclusion
* Changed: Default role selection includes all non-subscriber roles

= 1.1.0 - 2024-12-20 =
* Added: Automated email scheduling (weekly, monthly, quarterly)
* Added: Schedule configuration with enable/disable toggle
* Added: Visual schedule status indicators
* Added: Intelligent schedule detection and restoration
* Added: WordPress admin notices for schedule status changes
* Changed: Switched to single cron events (from recurring events)
* Changed: Improved settings sanitization for schedule changes
* Fixed: Resolved "unknown schedule" errors after reactivation
* Fixed: Orphaned settings where checkbox was enabled but no cron job existed

= 1.0.0 - 2024-12-19 =
* Initial release
* Core user tracking with last login timestamps
* Manual email report sending
* CSV export functionality
* Settings page with configuration options
* Last Login column in Users list (sortable)
* Clean, accessible design

== Upgrade Notice ==

= 1.3.0 =
Major update! Adds comprehensive user change logging with conditional logging to track only security-relevant changes. New Audit Logs page under Users menu.

= 1.2.0 =
Adds role selection settings - choose which user roles to include in audit reports and logs.

= 1.1.0 =
Adds automated email scheduling! Set weekly, monthly, or quarterly audit reports.

= 1.0.0 =
Initial release of User Audit Scheduler.

== Privacy & Data ==

This plugin stores the following data:

* Last login timestamps for all users (stored in user meta)
* Plugin settings (stored in wp_options)
* Audit log entries for security-relevant user changes (custom database table)

The audit logs include:
* User ID, username, display name, email
* Type of change (created, role changed, deleted, profile updated)
* Old and new values
* Who made the change
* When the change occurred
* Contextual notes

**Data Retention**: All data persists until the plugin is uninstalled (not just deactivated). When uninstalled, ALL data is removed:
* Last login timestamps
* Plugin settings
* Complete audit log history
* Custom database table

**GDPR Compliance**: Site administrators should inform users that login times and account changes are being tracked. Export logs to CSV before uninstalling if you need to retain historical records.

== Support ==

For support, please visit the plugin support forum on WordPress.org or contact the plugin author.

== License ==

This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.

This plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this plugin. If not, see https://www.gnu.org/licenses/gpl-2.0.html.