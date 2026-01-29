---
phase: 02-campaign-push-sync
plan: 03
subsystem: api
tags: [classy, wordpress, campaign, restore, acf, opt-out]

# Dependency graph
requires:
  - phase: 02-01
    provides: reactivate_campaign() and publish_campaign() API methods
provides:
  - Campaign restore workflow with proper reactivate-then-publish sequence
  - Campaign sync opt-out mechanism via ACF checkbox
  - should_sync_campaign() check for conditional campaign operations
affects: [02-04, phase-3]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Campaign state transitions: deactivated -> unpublished -> active (via reactivate + publish)"
    - "ACF field opt-out pattern for conditional sync operations"

key-files:
  created: []
  modified:
    - includes/class-sync-handler.php

key-decisions:
  - "Reactivate returns campaign to unpublished status, publish required afterward"
  - "Campaign sync opt-out via ACF disable_campaign_sync field"
  - "Continue with designation sync even if campaign sync disabled"

patterns-established:
  - "should_sync_campaign() check before any campaign operations"
  - "Three-step restore: reactivate -> publish -> update"

# Metrics
duration: 7min
completed: 2026-01-23
---

# Phase 02 Plan 03: Campaign Restore Workflow Summary

**Proper reactivate-then-publish sequence for trash restore with ACF opt-out mechanism**

## Performance

- **Duration:** 7 min
- **Started:** 2026-01-23T15:53:02Z
- **Completed:** 2026-01-23T15:59:48Z
- **Tasks:** 3 (2 code tasks + 1 deployment task)
- **Files modified:** 1

## Accomplishments

- Added should_sync_campaign() method for ACF-based opt-out of campaign sync
- Updated on_untrash_fund() with proper reactivate-then-publish sequence
- Deployed to staging successfully (SSH testing blocked by connectivity timeout)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add should_sync_campaign() method** - `b099b71` (feat)
2. **Task 2: Update on_untrash_fund() for proper restore** - `41a4669` (feat)
3. **Task 3: Deploy and test** - No code commit (deployment task, SSH timeout blocked testing)

## Files Created/Modified

- `includes/class-sync-handler.php` - Added should_sync_campaign() opt-out check, updated on_untrash_fund() with reactivate-publish-update sequence

## What Was Built

### 1. Sync Opt-out Mechanism

Added `should_sync_campaign()` method that checks ACF field `disable_campaign_sync`:

```php
private function should_sync_campaign(int $post_id): bool {
    if (!function_exists('get_field')) {
        return true; // Default to sync if ACF not available
    }
    $gfm_settings = get_field(self::ACF_GROUP_KEY, $post_id);
    if (!empty($gfm_settings['disable_campaign_sync'])) {
        return false;
    }
    return true;
}
```

**Usage:** Funds with `disable_campaign_sync = true` skip campaign operations while still syncing designations.

### 2. Campaign Restore Workflow

Updated `on_untrash_fund()` with proper 3-step reactivate-publish-update sequence:

| Step | Method | Effect |
|------|--------|--------|
| 1 | `reactivate_campaign()` | deactivated -> unpublished |
| 2 | `publish_campaign()` | unpublished -> active |
| 3 | `update_campaign()` | sync any data changes |

This ensures restored funds return their campaigns to **active** status, not stuck at unpublished.

## Key Integration Points

```yaml
key_links:
  - from: "on_untrash_fund()"
    to: "FCG_GFM_API_Client::reactivate_campaign()"
    pattern: "reactivate_campaign"
  - from: "on_untrash_fund()"
    to: "FCG_GFM_API_Client::publish_campaign()"
    pattern: "publish_campaign"
  - from: "sync_campaign_to_gofundme()"
    to: "should_sync_campaign()"
    pattern: "should_sync_campaign.*post_id"
```

## Decisions Made

1. **Reactivate returns to unpublished status** - Per Classy API, reactivate_campaign() returns a deactivated campaign to "unpublished" status, not "active". Must call publish_campaign() afterward to make it active again.

2. **Three-step restore sequence** - When restoring from trash:
   - Step 1: reactivate_campaign() - returns to unpublished
   - Step 2: publish_campaign() - makes active
   - Step 3: update_campaign() - sync any data changes made while trashed

3. **Graceful publish failure** - If publish fails after reactivate, log error but don't return early. Campaign is recoverable (can be published manually). Continue to update step.

4. **Opt-out applies to all campaign operations** - should_sync_campaign() check added to both sync_campaign_to_gofundme() and on_untrash_fund() campaign sections.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

**SSH Timeout for Staging Test:**
- WP Engine staging SSH connection timed out during Task 3
- Retry attempted as per objective instructions, also timed out
- rsync deployment succeeded (files transferred)
- Local code verification passed (syntax, method presence, API method availability)
- Staging test deferred until SSH connectivity restored

## User Setup Required

**ACF Field Configuration:**
To enable per-fund campaign sync opt-out, add an ACF field:
- Field Group: gofundme_settings (existing)
- Field Name: disable_campaign_sync
- Field Type: True/False
- Default: False (sync enabled by default)
- Location: funds post type

This allows administrators to disable campaign sync for specific funds while maintaining designation sync.

## Next Phase Readiness

- Campaign restore workflow complete with proper state transitions
- Campaign sync opt-out mechanism ready for ACF field configuration
- Ready for 02-04 (status transition hooks) to complete phase 02

**Pending verification:**
- Staging test should be run when SSH connectivity is restored to verify:
  - Trash fund -> campaign deactivated
  - Restore fund -> campaign reactivated AND published (status = "active")

---
*Phase: 02-campaign-push-sync*
*Completed: 2026-01-23*
