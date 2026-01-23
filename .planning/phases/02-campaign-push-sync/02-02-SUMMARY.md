---
phase: 02-campaign-push-sync
plan: 02
subsystem: api
tags: [classy-api, campaign-duplication, oauth2, wordpress-hooks]

# Dependency graph
requires:
  - phase: 02-01
    provides: Campaign lifecycle API methods (duplicate, publish, unpublish, reactivate)
provides:
  - Campaign creation via template duplication workflow
  - Race condition protection for campaign creation
  - Campaign ID and URL storage in post meta
affects: [02-03, 02-04, phase-3]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Transient-based locking for race condition prevention
    - Template duplication workflow (duplicate -> update -> publish)

key-files:
  created: []
  modified:
    - includes/class-sync-handler.php

key-decisions:
  - "Use raw_goal (string) instead of goal for duplication overrides per Classy API requirement"
  - "Update overview in separate API call since not available in duplication overrides"
  - "60-second transient lock duration balances race protection vs recovery time"

patterns-established:
  - "Template duplication workflow: duplicate() -> update() -> publish()"
  - "Transient locking pattern for API operations: check -> lock -> operate -> unlock"

# Metrics
duration: 4min
completed: 2026-01-23
---

# Phase 02 Plan 02: Campaign Creation Duplication Workflow Summary

**Rewrote campaign creation to use template duplication instead of POST /campaigns (403 forbidden)**

## Performance

- **Duration:** 4 min
- **Started:** 2026-01-23T15:52:49Z
- **Completed:** 2026-01-23T15:57:01Z
- **Tasks:** 2 (Task 1 complete, Task 2 blocked by SSH timeout)
- **Files modified:** 1

## Accomplishments

- Rewrote `create_campaign_in_gfm()` to use duplicate-then-publish workflow
- Template campaign ID sourced from `fcg_gfm_template_campaign_id` option
- Added transient lock (60s) to prevent race conditions during campaign creation
- Campaign ID and URL stored in fund post meta after creation

## Task Commits

1. **Task 1: Rewrite create_campaign_in_gfm()** - `b099b71` (feat)
   - Note: Commit labeled as 02-03 but contains 02-02 work combined with future 02-03 work
2. **Task 2: Deploy and test on staging** - Not committed (SSH timeout, local verification only)

**Plan metadata:** Pending

## Files Modified

- `includes/class-sync-handler.php` - Rewrote create_campaign_in_gfm() with duplication workflow

## Technical Implementation

The `create_campaign_in_gfm()` method now follows this workflow:

1. **Check prerequisites:** Get template ID from `fcg_gfm_template_campaign_id` option
2. **Race condition lock:** Set transient `fcg_gfm_creating_campaign_{$post_id}` for 60s
3. **Build overrides:** name, raw_goal, raw_currency_code, started_at, external_reference_id
4. **Duplicate template:** Call `$this->api->duplicate_campaign($template_id, $overrides)`
5. **Update overview:** Call `$this->api->update_campaign($campaign_id, ['overview' => ...])`
6. **Publish campaign:** Call `$this->api->publish_campaign($campaign_id)`
7. **Store meta:** Update `_gofundme_campaign_id` and `_gofundme_campaign_url`
8. **Cleanup:** Delete transient lock

## Decisions Made

1. **Use raw_goal as string:** Classy API expects `raw_goal` (string) for duplication overrides, not `goal` (number)
2. **Separate overview update:** Overview cannot be set during duplication, requires separate PUT call
3. **Non-fatal publish failures:** If publish fails, campaign is still created (can be published manually later)
4. **60-second lock duration:** Balances race protection with allowing retry if something fails

## Deviations from Plan

### Prior Execution Merge

**[Rule 3 - Blocking] Task 1 code was committed combined with 02-03 work**
- **Found during:** Task 1 verification
- **Issue:** Commit `b099b71` was labeled "02-03" but contains both 02-02 and 02-03 changes
- **Resolution:** Code changes for Task 1 are complete and verified; commit attribution is incorrect but functional
- **Impact:** None on functionality; minor impact on git history clarity

---

**Total deviations:** 1 (commit labeling issue from prior execution)
**Impact on plan:** Task 1 changes are in place and verified; no scope creep

## Issues Encountered

### SSH Timeout to WP Engine Staging

- **Issue:** SSH connection to `frederickc2stg.ssh.wpengine.net` timed out twice
- **Impact:** Could not execute Task 2 staging test
- **Workaround:** Local verification performed (PHP syntax check, pattern verification)
- **Resolution:** Staging test deferred; code is syntactically correct and follows expected patterns

## User Setup Required

None - template campaign ID was configured in Phase 1.

## Next Phase Readiness

### Ready
- Campaign creation via duplication workflow is implemented
- All required API methods are in place
- Code passes local verification

### Pending
- Staging test should be run when SSH connectivity is restored
- Verify campaign creation works end-to-end with actual API calls

### Blockers
- None blocking further development
- SSH timeout is environment-specific, not code-related

---
*Phase: 02-campaign-push-sync*
*Completed: 2026-01-23*
