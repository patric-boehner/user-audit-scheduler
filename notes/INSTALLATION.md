# Installation Guide

## Quick Start

1. **Upload the plugin**
   - Upload the entire `user-audit-scheduler` folder to your WordPress installation's `/wp-content/plugins/` directory
   - Or zip the folder and upload through WordPress admin (Plugins → Add New → Upload Plugin)

2. **Activate**
   - Go to Plugins in your WordPress admin
   - Find "User Audit Scheduler"
   - Click "Activate"

3. **Configure**
   - Go to Users → User Audits
   - Add your email address(es) in the Email Recipients field
   - Click "Save Settings"

4. **Test**
   - Click "Send Test Email Now" to verify everything works
   - Check your inbox for the audit report

## What Happens on Activation

When you activate the plugin:

- Default settings are created (uses your site's admin email as the default recipient)
- The plugin starts tracking login times for all users going forward
- A new menu item "User Audits" appears under Users

## First Use Tips

### Testing Email Delivery

Before relying on the plugin for audits, test email delivery:

1. Configure at least one recipient email
2. Save settings
3. Click "Send Test Email Now"
4. Check your inbox (and spam folder)

If the email doesn't arrive:

- Check your WordPress email configuration
- Verify the email addresses are valid
- Consider installing an SMTP plugin if your server has email delivery issues

### Understanding Last Login Dates

The plugin starts tracking logins from the moment it's activated. Users who haven't logged in since activation will show "Never" for their last login time. This is normal and will update as users log in.

### Reviewing Users

The settings page shows a preview of all users that will be included in reports. Review this list to understand what the audit emails will contain. Note that basic subscribers (users with no elevated permissions) are excluded from reports.

## Troubleshooting

### Email not sending
- Check that wp_mail() works on your server
- Verify recipient email addresses are valid
- Check your server's email logs
- Consider using an SMTP plugin

### Users showing "Never" for last login
- This is normal for users who haven't logged in since plugin activation
- The plugin only tracks logins going forward
- Previous login history is not available

### Settings not saving
- Verify you're logged in as an Administrator
- Check for JavaScript errors in browser console
- Ensure your hosting allows settings updates

## Next Steps

After installation:

1. Send yourself a test email to verify formatting
2. Review the user list to ensure it matches expectations
3. Export a CSV to see the data format
4. Share the plugin with other site administrators if needed

## Upgrading to Future Phases

When Phase 2 (automation) is released:

- Your existing settings will be preserved
- New scheduling options will be added
- You can continue using manual sending if preferred

## Uninstalling

If you need to remove the plugin:

1. Deactivate it first (Plugins → Deactivate)
2. Delete it (Plugins → Delete)

When deleted, the plugin will:
- Remove all stored settings
- Delete last login tracking data
- Leave no trace in your database

Your actual user accounts are never affected by the plugin.
