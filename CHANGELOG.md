# Changelog

All notable changes to User Audit Scheduler will be documented in this file.

## [1.3.1] - 2025-12-23

### Added
- **Automatic log cleanup**: Logs older than 1 year are automatically deleted to prevent unbounded database growth
- Daily scheduled cleanup runs at 3am via WordPress cron
- Filter hook `uas_log_retention_days` for customizing retention period (default: 365 days)
- Retention policy note added to Audit Logs page

### Changed - Architectural Improvement
- Centralized all audit logging decisions into single `uas_should_log_event()` function
- All logging functions now delegate decision-making to this central point of truth
- Prevents accidental logging of non-security-relevant events
- Makes logging intent explicit and easier to debug
- Reduces risk of future drift as features are added

### Technical
- Added `uas_should_log_event()` as the single source of truth for all logging decisions
- Refactored `uas_log_user_created()` to use centralized decision function
- Refactored `uas_log_role_change()` to use centralized decision function  
- Refactored `uas_log_user_deleted()` to use centralized decision function with explicit role context
- Refactored `uas_log_profile_update()` to use centralized decision function
- Added comprehensive documentation explaining architectural reasoning
- Added filter hook `uas_should_log_event` for site-specific logging overrides
- Logging functions are now "dumb" - they only prepare log entries, not make decisions
- Added defensive array normalization to prevent edge cases from malformed filters or plugin interference
- User deletion now explicitly passes roles in context rather than relying on hook timing assumptions
- Added `uas_cleanup_old_logs_callback()` for automatic log maintenance
- Cleanup runs daily at 3am, logging operations are logged for transparency
- Cleanup respects filter hook for custom retention periods (365 days default, 0 = never delete)

### Benefits
- **Prevents accidental database bloat**: No more risk of logging thousands of subscriber events
- **Easier debugging**: One place to look when asking "why was this logged?"
- **Safer feature additions**: New code must pass through the central gate
- **Clear intent**: Decision logic is explicit, not scattered across multiple functions
- **Future-proof**: Reduces risk of silent drift as plugin evolves

### Breaking Changes
None - behavior is identical to 1.3.0, only internal architecture changed

## [1.3.0] - 2025-12-22

### Added - Phase 3: User Change Logging
- Custom database table for storing audit logs
- Automatic tracking of security-relevant user changes with conditional logging
- Tracks user creation for elevated roles (Administrator, Editor, etc.)
- Tracks all role changes that cross the security boundary (subscriberâ†’editor, editorâ†’admin, etc.)
- Tracks user deletions for elevated roles
- Tracks profile updates (email and display name changes) for elevated roles
- New "Audit Logs" admin page under Users menu
- Filterable audit log display (by user, change type, date range)
- Pagination support for large log datasets (50 entries per page)
- CSV export functionality for audit logs
- Hard-coded user data in logs (persists even after user deletion)
- Automatic notes generation for all logged events
- Complete forensic record of who changed what and when

### Changed
- **Implemented conditional logging approach**: Only security-relevant changes are logged to prevent database bloat on membership sites
- Subscriber-only activity (registrations, email changes, deletions, subscriberâ†’contributor changes) is not logged
- Role changes that cross the audited boundary ARE logged (subscriberâ†’editor, editorâ†’subscriber)
- Role selection settings control which roles are considered "audited" (security-relevant)
- This approach balances forensic value with database efficiency

### Fixed
- Prevented duplicate log entries during user creation (both user_register and set_user_role hooks fire, now only user_register creates a log)
- Role change logging now skips initial role assignment during user creation by checking for empty old_roles array

### Technical
- Created `wp_uas_audit_log` database table with indexes
- Added logging hooks: `user_register`, `set_user_role`, `delete_user`, `profile_update`
- Implemented `uas_insert_log_entry()` for consistent log writing
- Added `uas_get_log_entries()` with filtering and pagination support
- Implemented `uas_get_log_entries_count()` for pagination
- Added `uas_has_audited_role()` to check if user has security-relevant role
- Added `uas_transition_crosses_boundary()` to detect security-relevant role changes
- Enhanced uninstall script to remove audit log table
- Conditional logging at the INSERT level prevents unnecessary database growth

## [1.2.0] - 2025-12-21

### Added
- Role selection settings: Admins can now choose which user roles to include in audit reports
- New "Report Options" section in settings page with checkbox interface for role selection
- Multi-role support: Users with multiple roles are included if any of their roles are selected
- Default role selection includes all non-subscriber roles on plugin activation

### Changed
- Updated `uas_get_audit_users()` to filter users based on selected role settings
- Improved user filtering logic to support configurable role inclusion
- Updated plugin activation to set default included roles

## [1.1.0] - 2025-12-20

### Added
- Automated email scheduling with configurable frequency (weekly, monthly, quarterly)
- Schedule configuration section in settings page with enable/disable toggle
- Visual schedule status indicators showing next scheduled send time
- Intelligent schedule detection and restoration after plugin reactivation
- WordPress admin notices for schedule status changes with clear feedback

### Changed
- Switched from recurring cron events to single events that reschedule themselves
- Removed dependency on custom cron recurrence schedules (resolves "unknown schedule" errors)
- Improved settings sanitization to handle schedule state changes
- Enhanced schedule cleanup during plugin deactivation
- Updated schedule status display to show orphaned state warnings

### Fixed
- Resolved "unknown schedule" errors when plugin was deactivated and reactivated
- Fixed orphaned settings where checkbox was enabled but no cron job existed
- Improved cron cleanup to remove all scheduled instances (not just first match)
- Better handling of schedule state across plugin lifecycle events

### Technical
- Deprecated `uas_add_cron_schedules()` function (no longer needed with single events)
- Enhanced error logging for scheduled email operations
- Automatic rescheduling after successful email send

## [1.0.0] - 2025-12-19

### Added
- Core user tracking functionality with last login timestamps
- Manual email report sending with HTML table format
- CSV export functionality for user audit data
- Settings page under Users menu with configuration options
- Email recipient configuration (multiple addresses supported)
- Customizable email subject line
- Last Login column in WordPress Users list table
- Sortable Last Login column with smart date formatting
- User preview table on settings page
- Test email sending functionality
- Clean, accessible design following WordPress standards

### Features
- Tracks last login time for all users via `wp_login` hook
- Stores last login as user meta (`_user_last_login`)
- Filters to show only users with elevated permissions (non-subscribers)
- Smart date formatting: relative time within 24 hours, full date after
- HTML email template with responsive table layout
- Direct links to edit user profiles in admin from email reports
- CSV export with timestamp-based filenames
- Proper WordPress coding standards throughout

### Security
- Nonce verification on all form submissions
- Capability checks for admin-only access
- Input sanitization and output escaping
- Secure direct access prevention
- Email validation for recipients

### Technical
- Procedural PHP code structure (no classes)
- Feature-based file organization
- WordPress Settings API integration
- Proper hook usage throughout
- Clean uninstall with data removal

## Roadmap

### Phase 4 - Polish (Planned)
- Enhanced UI/UX refinements
- Additional email template options
- Comprehensive documentation
- Help text and tooltips
- Performance optimizations
- Log retention policies
- Advanced filtering options

---

## Version History

- **1.3.0** - User change logging (Phase 3)
- **1.2.0** - Role selection settings
- **1.1.0** - Automated email scheduling (Phase 2)
- **1.0.0** - Initial release with manual reporting and tracking (Phase 1)