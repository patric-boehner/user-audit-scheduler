# User Audit Scheduler - Phase 1 MVP

A WordPress plugin for streamlining periodic user audits by automating report generation.

## What This Plugin Does

This plugin helps WordPress administrators keep track of users with elevated permissions. It provides:

- **Last Login Tracking**: Automatically tracks when users log in
- **Manual Email Reports**: Send audit reports on demand to configured recipients
- **CSV Export**: Download user audit data as a spreadsheet
- **Clean Interface**: Simple settings page under Users menu

## Phase 1 Features

This is the MVP (Minimum Viable Product) release with core functionality:

- ✅ Basic user list generation (excludes subscribers)
- ✅ Manual email sending with HTML table
- ✅ CSV export with all user data
- ✅ Last login tracking via user meta
- ✅ Last Login column in WordPress Users list (sortable!)

## Installation

1. Upload the `user-audit-scheduler` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Users → User Audits to configure settings

## Usage

### Configure Email Recipients

1. Navigate to Users → User Audits
2. Enter email addresses (one per line) where reports should be sent
3. Customize the email subject if desired
4. Click "Save Settings"

### Send a Test Email

Click the "Send Test Email Now" button to immediately send an audit report to your configured recipients. This helps verify your email settings are working correctly.

### Export to CSV

Click the "Download Current Audit (CSV)" button to download a spreadsheet with all user audit data. This is useful for offline review or record-keeping.

### What Gets Included

The audit includes all users EXCEPT basic subscribers. It shows:

- Username
- Display Name
- Email Address
- User Role(s)
- Last Login Time
- Link to Edit User

### Last Login Column in Users List

The plugin automatically adds a "Last Login" column to the WordPress Users list (Users → All Users) as the last column:

- Shows "Never" for users who haven't logged in since plugin activation
- Displays relative time like "2 hours ago" for logins within the last 24 hours
- Shows formatted date like "Dec 11, 2024 3:45 PM" for older logins
- Hover over any time to see the full date and time
- Click the column header to sort users by last login time
- Helps quickly identify inactive accounts

## Technical Details

### Database Storage

- **Last Login**: Stored in user meta as `_user_last_login` (Unix timestamp)
- **Settings**: Stored in `wp_options` as `uas_settings` (serialized array)

### Security

- All admin pages require `manage_options` capability (Administrator only)
- All form submissions use WordPress nonces
- All input is sanitized before storage
- All output is escaped before display
- Email addresses are validated before use

### Hooks Used

- `wp_login` - Tracks last login time
- `admin_menu` - Adds settings page
- `admin_init` - Registers settings and handles actions

### Code Structure

```
user-audit-scheduler/
├── user-audit-scheduler.php     (Main plugin file)
└── includes/
    ├── core/
    │   └── functions.php         (User data retrieval)
    ├── tracking/
    │   └── functions.php         (Last login tracking)
    ├── export/
    │   └── functions.php         (CSV generation)
    ├── email/
    │   └── functions.php         (Email sending)
    └── admin/
        ├── menu.php              (Menu setup)
        └── settings.php          (Settings page)
        └── user-list.php         (User columns)
```

### Function Naming Convention

All functions use the `uas_` prefix to prevent conflicts:
- `uas_get_audit_users()` - Get user data
- `uas_send_audit_email()` - Send email
- `uas_export_csv()` - Export to CSV
- etc.

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Administrator account to access settings

## Future Phases

**Phase 2**: Scheduled automation
- Automated cron-based email delivery
- Weekly, monthly, quarterly schedules

**Phase 3**: Change logging
- Track user role changes
- Log user deletions
- Audit trail with history

**Phase 4**: Polish
- Enhanced email templates
- Additional filtering options
- Detailed documentation

## Support

For issues or questions, please contact the plugin author.

## License

GPL v2 or later