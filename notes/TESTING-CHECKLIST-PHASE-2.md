# Phase 2 Testing Checklist

## Pre-Testing Setup

- [ ] Fresh WordPress installation (5.8+) OR existing test site
- [ ] PHP 7.4 or higher
- [ ] Plugin uploaded to `/wp-content/plugins/user-audit-scheduler/`
- [ ] Plugin activated successfully
- [ ] At least one test user with elevated permissions (editor, administrator)

## Basic Functionality Tests (Phase 1 Features)

- [ ] Last Login column appears in Users list
- [ ] Last login updates when logging in
- [ ] Can access Users → User Audits settings page
- [ ] Can save email recipients
- [ ] Can save custom email subject
- [ ] "Send Test Email" sends successfully
- [ ] "Download CSV" exports successfully
- [ ] User preview table displays correctly

## Phase 2 Automated Scheduling Tests

### Initial Schedule Setup

- [ ] Navigate to Users → User Audits
- [ ] Verify "Automated Schedule" section is visible
- [ ] Check "Enable Automated Emails" checkbox
- [ ] Select "Weekly" frequency
- [ ] Click "Save Settings"
- [ ] Verify green success message appears
- [ ] Verify "Next email scheduled for" displays with date/time
- [ ] Verify scheduled time is next Monday at 9am

### Frequency Changes

**Test Monthly:**
- [ ] Change frequency to "Monthly"
- [ ] Click "Save Settings"
- [ ] Verify next scheduled time updates to 1st of next month at 9am

**Test Quarterly:**
- [ ] Change frequency to "Quarterly"
- [ ] Click "Save Settings"
- [ ] Verify next scheduled time updates to next quarter start at 9am
  - If in Jan-Mar: Should show Apr 1
  - If in Apr-Jun: Should show Jul 1
  - If in Jul-Sep: Should show Oct 1
  - If in Oct-Dec: Should show Jan 1 next year

**Test Back to Weekly:**
- [ ] Change frequency back to "Weekly"
- [ ] Click "Save Settings"
- [ ] Verify schedule updates correctly

### Enable/Disable Tests

- [ ] Uncheck "Enable Automated Emails"
- [ ] Click "Save Settings"
- [ ] Verify status message changes to "Automated emails are currently disabled"
- [ ] Re-enable automation
- [ ] Verify schedule is recreated

### Persistence Tests

- [ ] Enable automated emails with Monthly frequency
- [ ] Note the next scheduled time
- [ ] Navigate away from the settings page
- [ ] Return to Users → User Audits
- [ ] Verify schedule settings are still saved
- [ ] Verify "enabled" checkbox is still checked
- [ ] Verify frequency dropdown shows "Monthly"
- [ ] Verify next send time still displays correctly

### Manual Email Independence

- [ ] With automation enabled, click "Send Test Email Now"
- [ ] Verify test email sends successfully
- [ ] Verify scheduled automation is still intact (not affected)

### Plugin Deactivation

- [ ] Enable automated emails
- [ ] Note that schedule is active
- [ ] Deactivate the plugin
- [ ] Check WordPress cron (using plugin like WP Crontrol)
- [ ] Verify `uas_send_scheduled_email` event is removed
- [ ] Reactivate plugin
- [ ] Verify settings are preserved
- [ ] Verify automation is disabled (must be manually re-enabled)

### Edge Case Tests

**Same Day Scheduling:**
- [ ] On a Monday before 9am, enable weekly automation
- [ ] Verify schedules for same Monday at 9am
- [ ] On a Monday after 9am, enable weekly automation
- [ ] Verify schedules for next Monday

**End of Month:**
- [ ] On the 1st of month before 9am, enable monthly automation
- [ ] Verify schedules for same day at 9am
- [ ] On the 1st after 9am, enable monthly automation
- [ ] Verify schedules for 1st of next month

## WordPress Cron Verification

Using a plugin like WP Crontrol:

- [ ] Install WP Crontrol (or similar cron management plugin)
- [ ] Navigate to Tools → Cron Events
- [ ] With automation enabled, verify `uas_send_scheduled_email` event exists
- [ ] Verify event shows correct next run time
- [ ] Verify event shows correct recurrence interval
- [ ] For weekly: Shows "weekly" recurrence
- [ ] For monthly: Shows "monthly" recurrence
- [ ] For quarterly: Shows "Once Every Three Months" recurrence

## Actual Email Delivery Test

**Option 1: Wait for scheduled time (recommended for real testing)**
- [ ] Set weekly automation for next Monday
- [ ] Wait until Monday 9am
- [ ] Verify email is received
- [ ] Verify next event is scheduled for following Monday

**Option 2: Force cron run (for faster testing)**
- [ ] Set weekly automation
- [ ] Using WP Crontrol, manually run `uas_send_scheduled_email` event
- [ ] Verify email is received
- [ ] Check error log for success message

## Error Handling Tests

**No Recipients:**
- [ ] Remove all email recipients
- [ ] Enable automation
- [ ] Force cron run (using WP Crontrol)
- [ ] Check error_log for "no recipients" error
- [ ] Verify graceful failure (no fatal errors)

**Invalid Frequency:**
- [ ] Manually edit database to set invalid frequency value
- [ ] Save settings page
- [ ] Verify defaults to "monthly"
- [ ] Verify no errors occur

## Browser/UI Tests

- [ ] Test in Chrome
- [ ] Test in Firefox
- [ ] Test in Safari
- [ ] Verify checkbox works correctly
- [ ] Verify dropdown works correctly
- [ ] Verify status messages are readable
- [ ] Verify dates/times display in correct timezone

## Documentation Verification

- [ ] README accurately describes Phase 2 features
- [ ] CHANGELOG includes Phase 2 release
- [ ] Code comments are clear and helpful
- [ ] Function documentation is complete

## Success Criteria

All checkboxes above should be checked for Phase 2 to be considered complete and ready for production.

## Common Issues & Solutions

**Emails not sending:**
- Check WordPress cron is working (test with other plugins)
- Verify wp_mail() is configured correctly on server
- Check spam folder
- Review error_log for messages

**Wrong send times:**
- Verify WordPress timezone setting (Settings → General)
- Check server timezone matches WordPress timezone
- Test with different timezones

**Schedule not persisting:**
- Verify settings are being saved to database
- Check for JavaScript errors in browser console
- Verify nonces are working correctly
