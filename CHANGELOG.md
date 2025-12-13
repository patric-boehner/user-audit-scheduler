# Changelog

All notable changes to the User Audit Scheduler plugin will be documented in this file.

## [1.0.0] - 2024-12-11

### Added - Phase 1 MVP
- Last login tracking for all users
- Last Login column in WordPress Users list (sortable, with hover tooltips)
- Smart date formatting: relative time within 24 hours, formatted date after
- Manual email sending with HTML table format
- CSV export functionality
- Settings page under Users menu
- Email recipient configuration
- Customizable email subject line
- User preview table on settings page
- Security: nonce verification, capability checks, input sanitization
- Clean, readable email template (no gradients, no gray text)

### Technical Details
- Procedural PHP code structure
- Feature-based file organization
- WordPress coding standards
- Proper use of WordPress hooks
- Settings API integration
- Comprehensive error handling with WP_Error

### Security Features
- Administrator-only access (manage_options capability)
- WordPress nonces on all forms
- Sanitized inputs, escaped outputs
- Email validation
- Secure direct access prevention