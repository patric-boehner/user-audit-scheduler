=== User Audit Scheduler ===
Contributors: patrick-b
Tags: user management, audit, security, admin, user tracking
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Streamline WordPress user audits by tracking elevated roles, logging security-relevant changes, and automating email reports.

== Description ==

User Audit Scheduler helps WordPress administrators maintain a complete audit trail of user activity. Focused on security-relevant events, it reduces database clutter while providing actionable insights.

**Key Features:**
* **Last Login Tracking:** Automatically track when users log in.
* **Automated Audit Emails:** Schedule weekly, monthly, or quarterly reports.
* **Manual Reports:** Send audit emails on demand.
* **CSV Export:** Download user data or audit logs for analysis.
* **Change Logging:** Track user creation, role changes, deletions, and profile updates.
* **Filterable Logs:** Review changes by user, date range, or change type.
* **Clean Interface:** Accessible settings under the Users menu.

**Conditional Logging Highlights:**
* Logs user creation, deletions, and profile updates for elevated roles (Editor, Admin, Author, etc.).
* Logs role changes crossing security boundaries (e.g., Subscriber → Editor, Editor → Admin).
* Excludes routine Subscriber activity by default to prevent database bloat.
* Logs persist even if a user is deleted.
* Extensible via filters for site-specific needs.

== Installation ==

1. Upload the `user-audit-scheduler` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under **Users → User Audit Settings**
4. View audit history under **Users → Audit Logs**

== Frequently Asked Questions ==

= Will this plugin log all subscribers? =
No. By default, subscriber activity is excluded. Only elevated roles are tracked unless you explicitly enable other roles.

= Can I customize which roles are tracked? =
Yes. Select roles in **Users → User Audit Settings** under the Report Options section.

= What happens when a user is deleted? =
Audit logs persist! All user information is saved in the log entry, providing a complete history even after deletion.

= Can I export the logs? =
Yes. The Audit Logs page allows CSV export of all logs or filtered results.

= How long are logs kept? =
Logs are automatically deleted after 1 year by default. Customize retention using the `uas_log_retention_days` filter (set to 0 to never delete).

= Does it support custom user roles? =
Yes. Any custom roles created by other plugins appear in role selection options.

== Screenshots ==

1. Settings page with email configuration and role selection
2. Audit Logs page showing filterable change history
3. Last Login column in WordPress Users list
4. Sample automated email report

== Changelog ==

= 1.3.1 - 2025-12-23 =
* Added automatic log cleanup (default: 1 year; customizable)
* Centralized all logging decisions into `uas_should_log_event()`
* Defensive array handling for filters
* Improved user deletion logging

= 1.3.0 - 2025-12-22 =
* Added comprehensive user change logging
* Conditional logging of security-relevant events
* New "Audit Logs" admin page with filters and CSV export

= 1.2.0 - 2025-12-21 =
* Role selection settings for configurable reports
* Multi-role support in filtering

= 1.1.0 - 2025-12-20 =
* Automated email scheduling (weekly, monthly, quarterly)
* Visual schedule indicators and intelligent cron restoration

= 1.0.0 - 2025-12-19 =
* Initial release: core tracking, manual reports, CSV export, Last Login column

== Privacy & Data ==

This plugin stores:

* Last login timestamps (user meta)
* Plugin settings (wp_options)
* Audit logs of security-relevant changes (custom table)

Audit logs include user ID, username, display name, email, change type, old/new values, who made the change, timestamp, and contextual notes.

**Data Retention & GDPR:**  
All data is removed on uninstall. Administrators should inform users that login times and account changes are tracked. Export logs to CSV before uninstalling if needed.

== Support ==

For support, visit the plugin forum on WordPress.org or contact the plugin author.

== License ==

GPLv2 or later. No warranty is provided. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
