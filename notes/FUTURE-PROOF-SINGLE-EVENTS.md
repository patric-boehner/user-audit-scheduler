# Future-Proof Solution: Self-Rescheduling Single Events

## The Recommendation

Instead of using recurring cron events with custom schedules, use **single events that reschedule themselves**. This completely eliminates the "unknown schedule" problem.

## Why This Is Better

### The Old Way (Recurring Events with Custom Schedules)
```php
// Schedule a RECURRING event
wp_schedule_event( $timestamp, 'uas_monthly', 'uas_send_scheduled_email' );

// Problems:
// - Requires custom recurrence definition
// - Custom schedule disappears on deactivation
// - Events become "unknown schedule"
// - Requires special cleanup code
```

### The New Way (Self-Rescheduling Single Events)
```php
// Schedule a SINGLE event
wp_schedule_single_event( $timestamp, 'uas_send_scheduled_email' );

// In the callback:
// - Send the email
// - Calculate next run time
// - Schedule the next single event

// Benefits:
// - No custom recurrences needed
// - No "unknown schedule" issues
// - Deactivation works normally
// - Standard wp_next_scheduled() works
```

## How It Works

### 1. Initial Scheduling
When user enables automation:
```php
function uas_schedule_audit_email( $frequency ) {
    // Clear any existing
    uas_clear_scheduled_audit_email();
    
    // Calculate next time
    $timestamp = uas_get_next_schedule_time( $frequency );
    
    // Schedule SINGLE event (not recurring)
    wp_schedule_single_event( $timestamp, 'uas_send_scheduled_email' );
}
```

### 2. The Callback Reschedules
When the event fires:
```php
function uas_send_scheduled_email_callback() {
    // Send the email
    $result = uas_send_audit_email();
    
    // Check if automation is still enabled
    $settings = get_option( 'uas_settings', array() );
    if ( ! empty( $settings['schedule_enabled'] ) ) {
        // Calculate NEXT run time
        $next_timestamp = uas_get_next_schedule_time( $settings['schedule_frequency'] );
        
        // Schedule the NEXT single event
        wp_schedule_single_event( $next_timestamp, 'uas_send_scheduled_email' );
    }
}
```

### 3. The Chain Continues
```
Event 1 fires → Sends email → Schedules Event 2
Event 2 fires → Sends email → Schedules Event 3
Event 3 fires → Sends email → Schedules Event 4
... and so on
```

## Benefits

### ✅ No Custom Recurrences Needed
- Don't need to define `uas_monthly` or `uas_quarterly`
- Don't need `cron_schedules` filter
- WordPress never sees an "unknown schedule"

### ✅ Deactivation Works Perfectly
```php
// Old way - complicated
function uas_deactivate() {
    uas_force_clear_cron_events(); // Special handling needed
}

// New way - simple
function uas_deactivate() {
    // Standard wp_next_scheduled() works because no custom schedule
    while ( $timestamp = wp_next_scheduled( 'uas_send_scheduled_email' ) ) {
        wp_unschedule_event( $timestamp, 'uas_send_scheduled_email' );
    }
}
```

### ✅ Self-Regulating
- If user disables automation, no new event gets scheduled
- Chain stops naturally
- No orphaned recurring events

### ✅ Easier to Debug
- WP Crontrol shows clear "Run Once" events
- No confusing recurrence intervals
- Can see exactly when next email will send

## Implementation Details

### What Changed

**`uas_schedule_audit_email()`**
- Changed from `wp_schedule_event()` to `wp_schedule_single_event()`
- No longer needs recurrence parameter

**`uas_send_scheduled_email_callback()`**
- Added rescheduling logic after sending
- Checks if automation still enabled
- Schedules next single event

**`uas_add_cron_schedules()`**
- Deprecated - no longer needed
- Returns schedules unchanged
- Kept for backwards compatibility

**Main plugin file**
- Removed `cron_schedules` filter hook
- No longer registers custom schedules

### Cleanup is Now Simple

```php
function uas_deactivate() {
    // No special handling needed!
    // Standard cleanup works because events are single, not recurring
    uas_force_clear_cron_events(); // Still safe to use
    
    // Update settings
    $settings = get_option( 'uas_settings', array() );
    if ( isset( $settings['schedule_enabled'] ) ) {
        $settings['schedule_enabled'] = false;
        update_option( 'uas_settings', $settings );
    }
}
```

## Edge Cases Handled

### User Disables During Scheduled Period
```
1. Event is scheduled for next Monday
2. User disables automation on Friday
3. Settings updated: schedule_enabled = false
4. Monday arrives, event fires
5. Callback checks settings
6. schedule_enabled = false → doesn't reschedule
7. Chain stops ✓
```

### User Changes Frequency
```
1. Scheduled for monthly (Jan 1)
2. User changes to weekly
3. uas_schedule_audit_email() called
4. Clears existing event (Jan 1)
5. Schedules new event (next Monday)
6. Old schedule gone ✓
```

### Plugin Deactivated Mid-Cycle
```
1. Event scheduled for Jan 1
2. Plugin deactivated Dec 15
3. Deactivation clears event
4. No orphaned events ✓
5. No "unknown schedule" ✓
```

## Comparison

### Old Approach
```php
// Schedule
wp_schedule_event( $time, 'uas_monthly', 'hook' );

// Problems:
❌ Custom recurrence needed
❌ "Unknown schedule" on deactivation
❌ Special cleanup required
❌ More complex

// Cleanup
uas_force_clear_cron_events(); // Must use raw array
```

### New Approach
```php
// Schedule
wp_schedule_single_event( $time, 'hook' );

// Benefits:
✅ No custom recurrence
✅ No "unknown schedule" ever
✅ Standard cleanup works
✅ Simpler code

// Cleanup
wp_next_scheduled() works normally
```

## Testing

### Test 1: Initial Setup
1. Enable automation with monthly
2. Check WP Crontrol
3. Should see: "Run Once" event for 1st of next month ✓

### Test 2: Event Fires
1. Wait for event to fire (or trigger manually)
2. Check error log
3. Should see: "Next email scheduled for..." ✓
4. Check WP Crontrol
5. Should see: New "Run Once" event scheduled ✓

### Test 3: Disable Automation
1. Disable automation checkbox
2. Save settings
3. Wait for scheduled event to fire
4. Check WP Crontrol after
5. Should see: No new event scheduled ✓

### Test 4: Deactivate Plugin
1. Enable automation
2. Note scheduled event
3. Deactivate plugin
4. Check WP Crontrol
5. Should see: Event removed ✓
6. No "Unknown schedule" errors ✓

## Files Modified

### `/user-audit-scheduler.php`
- Removed `cron_schedules` filter hook
- Simplified initialization

### `/includes/scheduler/functions.php`
- Changed `uas_schedule_audit_email()` to use `wp_schedule_single_event()`
- Updated `uas_send_scheduled_email_callback()` to reschedule after sending
- Deprecated `uas_add_cron_schedules()` (no longer needed)

## Migration Notes

### For Existing Installations
The change is backwards compatible:
1. Old recurring events will be cleared on next save
2. New single event will be created
3. No data loss
4. No manual intervention needed

### For New Installations
Just works - no special setup needed.

## Why This Recommendation is Brilliant

1. **Eliminates the root cause** - No custom schedules = No unknown schedule problem
2. **Simpler code** - Less complexity, easier to maintain
3. **Standard WordPress pattern** - How many WordPress plugins handle one-time scheduling
4. **Self-documenting** - WP Crontrol clearly shows "Run Once" events
5. **Future-proof** - Will work forever, no WordPress version concerns

## Credit

This approach eliminates the entire category of problems we were solving with `uas_force_clear_cron_events()`. While we still keep that function for safety, it's no longer critical because events are never "unknown schedule."

Thank you for this excellent recommendation! 🎯
