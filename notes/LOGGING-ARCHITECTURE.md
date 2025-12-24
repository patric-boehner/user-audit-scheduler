# User Audit Scheduler - Logging Architecture

## Overview

All audit logging decisions flow through a single function: `uas_should_log_event()`.

This is the **only** place that decides what gets written to the audit log. Nothing gets logged unless this function explicitly returns `true`.

## Why This Architecture?

### The Problem It Solves

Without a central decision point, logging decisions get scattered:

```php
// BAD: Decision logic scattered across multiple functions
function uas_log_user_created( $user_id ) {
    if ( uas_has_elevated_role( $user ) ) {  // Decision here
        uas_insert_log(...);
    }
}

function uas_log_role_change( $user_id ) {
    if ( uas_crosses_boundary( $old, $new ) ) {  // Different decision here
        uas_insert_log(...);
    }
}
```

This creates risk:
- Accidental logging of non-security events
- Inconsistent application of rules
- Difficult debugging ("why was this logged?")
- Silent drift as features are added

### The Solution

**One function makes all decisions:**

```php
// GOOD: All decisions flow through central point
function uas_log_user_created( $user_id ) {
    if ( ! uas_should_log_event( 'user_created', $user ) ) {
        return;  // Central gate says no
    }
    uas_insert_log(...);
}

function uas_log_role_change( $user_id ) {
    if ( ! uas_should_log_event( 'role_changed', $user, $context ) ) {
        return;  // Same gate, different event
    }
    uas_insert_log(...);
}
```

## How It Works

### Basic Decision Flow

1. **Is this a recognized event type?**
   - `user_created`, `user_deleted`, `role_changed`, `profile_updated`
   - Unknown events are rejected

2. **Normalize role arrays defensively**
   - Ensures roles are always arrays, even if filters or plugins interfere
   - Protects against edge cases and malformed data

3. **Does this involve an audited role?**
   - Checks user's current roles against configured audited roles
   - For role changes, checks if transition crosses the security boundary
   - For deletions, uses explicitly passed old_roles for clarity and safety

4. **Allow filters to override**
   - Site-specific requirements can modify the decision

### Default Behavior

By default, the plugin logs:

✅ **LOGGED:**
- User created with elevated role (admin, editor, etc.)
- Role changes crossing security boundary (subscriber → editor)
- Profile updates for elevated roles (email, display name)
- Deletions of elevated roles

❌ **NOT LOGGED:**
- Subscriber registrations (unless subscriber is marked as audited)
- Subscriber profile changes
- Subscriber deletions  
- Role changes between non-audited roles (subscriber → contributor)

## Customizing Logging Behavior

### Example 1: Log All Subscriber Activity

Some sites (e.g., compliance-focused) may want to log ALL subscriber activity:

```php
add_filter( 'uas_should_log_event', function( $should_log, $event_type, $user, $context ) {
    // Override: log everything
    return true;
}, 10, 4 );
```

### Example 2: Never Log Profile Updates

Some sites may want to skip profile update logging:

```php
add_filter( 'uas_should_log_event', function( $should_log, $event_type, $user, $context ) {
    if ( $event_type === 'profile_updated' ) {
        return false;  // Never log profile updates
    }
    return $should_log;  // Use default for everything else
}, 10, 4 );
```

### Example 3: Log Only Specific Roles

Log only administrators and editors, regardless of settings:

```php
add_filter( 'uas_should_log_event', function( $should_log, $event_type, $user, $context ) {
    $current_roles = isset( $user->roles ) ? $user->roles : array();
    
    $important_roles = array( 'administrator', 'editor' );
    
    foreach ( $current_roles as $role ) {
        if ( in_array( $role, $important_roles, true ) ) {
            return true;  // Log it
        }
    }
    
    return false;  // Skip everything else
}, 10, 4 );
```

### Example 4: Log Based on Email Domain

Log all changes for users with company email addresses:

```php
add_filter( 'uas_should_log_event', function( $should_log, $event_type, $user, $context ) {
    $email = isset( $user->user_email ) ? $user->user_email : '';
    
    if ( strpos( $email, '@company.com' ) !== false ) {
        return true;  // Log company employees
    }
    
    return $should_log;  // Use default for others
}, 10, 4 );
```

## Filter Hook Reference

### `uas_should_log_event`

**Description:** Filters whether an event should be logged to the audit log.

**Parameters:**
- `$should_log` (bool): Whether to log the event (default decision)
- `$event_type` (string): Type of event (`user_created`, `user_deleted`, `role_changed`, `profile_updated`)
- `$user` (WP_User): User object representing current/final state
- `$context` (array): Additional context data
  - `old_roles` (array): Previous roles (for role changes)

**Return:** (bool) True to log the event, false to skip

### `uas_log_retention_days`

**Description:** Filters the number of days to retain audit logs before automatic deletion.

**Parameters:**
- `$retention_days` (int): Number of days to retain logs (default: 365)

**Return:** (int) Number of days to retain logs, or 0 to never delete

**Examples:**

```php
// Never delete logs (compliance requirement)
add_filter( 'uas_log_retention_days', '__return_zero' );

// 2 years for healthcare compliance
add_filter( 'uas_log_retention_days', function() { return 730; } );

// 90 days (minimal retention)
add_filter( 'uas_log_retention_days', function() { return 90; } );

// 30 days (very short retention)
add_filter( 'uas_log_retention_days', function() { return 30; } );
```

## Log Retention & Cleanup

The plugin automatically deletes logs older than **1 year (365 days)** by default. This prevents unbounded database growth while maintaining sufficient history for security audits and compliance.

### How It Works

- **Cleanup runs daily** at 3am via WordPress cron
- **Default retention:** 365 days (1 year)
- **Customizable** via `uas_log_retention_days` filter hook
- **Transparent:** Cleanup operations are logged to error_log
- **Optional:** Set to 0 to never delete logs

### Why 1 Year?

One year retention:
- ✅ Covers annual security audits
- ✅ Sufficient for investigating recent incidents
- ✅ Meets most compliance requirements (SOC2, ISO27001)
- ✅ Prevents unbounded database growth
- ✅ Balances forensic value with practical storage

Some organizations need longer retention (e.g., healthcare = 6-7 years). Use the filter hook to customize.

### Compliance Considerations

If your organization requires longer retention:

```php
// Example: 7 years for HIPAA compliance
add_filter( 'uas_log_retention_days', function() { 
    return 365 * 7; // 2,555 days
} );
```

If you need permanent logs:

```php
// Never delete logs
add_filter( 'uas_log_retention_days', '__return_zero' );
```

**Important:** If you need historical logs before changing retention periods, export them to CSV first from the Audit Logs page.

## Debugging Logging Decisions

### How to Find Out Why Something Was (or Wasn't) Logged

1. **Check the central function**: Look at `uas_should_log_event()` in `includes/logging/functions.php`
2. **Check your filters**: Look for any `add_filter( 'uas_should_log_event' )` calls
3. **Check the settings**: Go to Users → User Audit Settings → Report Options

### Adding Debug Logging

To see what the decision function is doing:

```php
add_filter( 'uas_should_log_event', function( $should_log, $event_type, $user, $context ) {
    error_log( sprintf(
        'Logging decision: %s for %s (user: %s, result: %s)',
        $event_type,
        $user->user_login,
        print_r( $user->roles, true ),
        $should_log ? 'LOG' : 'SKIP'
    ) );
    
    return $should_log;
}, 10, 4 );
```

Check your error log to see what decisions are being made.

## Best Practices

### DO:
✅ Use the filter hook for site-specific requirements  
✅ Return early with explicit true/false values  
✅ Document why you're overriding the default  
✅ Test your filter on a staging site first  

### DON'T:
❌ Modify the core `uas_should_log_event()` function directly  
❌ Add logging calls that bypass the central decision point  
❌ Create overly complex filter logic  
❌ Log sensitive data in filter debugging  

## Architecture Benefits

This centralized approach provides:

1. **Single source of truth** - One place to look when debugging
2. **Fail-safe design** - Nothing logs unless explicitly approved
3. **Future-proof** - New features must pass through the gate
4. **Clear intent** - Decision logic is explicit and documented
5. **Flexible** - Easy to customize via filters without modifying core code

## Questions?

If you're unsure about:
- Why something was logged
- Why something wasn't logged  
- How to customize logging behavior

Start by reading `uas_should_log_event()` in `includes/logging/functions.php`. It's the single source of truth for all logging decisions.
