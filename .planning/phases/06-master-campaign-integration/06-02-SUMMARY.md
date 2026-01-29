---
phase: 06-master-campaign-integration
plan: 02
subsystem: deployment
tags: [staging, verification, classy-api]

# Dependency graph
requires:
  - phase: 06-01
    provides: Master campaign settings and designation linking code
provides:
  - Verified working integration on staging
  - Test fund designation appears in master campaign's active group
  - End-to-end sync flow confirmed
affects: [07-frontend-embed]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Human verification checkpoint for integration testing"]

key-files:
  created: []
  modified: []

key-decisions:
  - "SSH timeout during initial deployment - resolved by retry"
  - "Verified designation appears in campaign's Default Active Group (not just org level)"
  - "857 designations now in active group (was 856 before test)"

patterns-established:
  - "update_campaign() with designation_id adds to TOP of active group list"

# Metrics
duration: 20min
completed: 2026-01-29
---

# Phase 6 Plan 2: Deploy and Verify Summary

**Staging deployment and end-to-end verification of master campaign integration**

## Performance

- **Duration:** 20 minutes (including SSH timeout and retry)
- **Started:** 2026-01-29T16:48:00Z
- **Completed:** 2026-01-29T17:08:00Z
- **Tasks:** 4 (3 auto + 1 checkpoint)
- **Files modified:** 0 (deployment only)

## Accomplishments

- Plugin deployed to WP Engine staging via rsync
- Settings page confirmed showing Master Campaign ID and Master Component ID
- Test fund created: "Phase 6 Test Fund - DELETE ME" (post ID 13854)
- Designation ID 1896370 created and linked to master campaign
- **CRITICAL VERIFICATION:** Designation appears in campaign 764694's "Default Active Group"

## Task Completion

1. **Task 1: Deploy plugin to WP Engine staging** - ✅ Complete
   - Initial SSH timeout, resolved by retry
   - Files deployed successfully via rsync

2. **Task 2: Configure master campaign settings** - ✅ Complete
   - Settings already migrated from old template setting
   - Master Campaign ID: 764694 (validated)
   - Master Component ID: mKAgOmLtRHVGFGh_eaqM6

3. **Task 3: Create test fund and verify** - ✅ Complete
   - Test fund: "Phase 6 Test Fund - DELETE ME" (ID 13854)
   - Designation ID: 1896370
   - Sync status: Synced at 2026-01-29 12:00:12

4. **Task 4: Human verification checkpoint** - ✅ APPROVED
   - User verified designation appears in Classy campaign's Default Active Group
   - 857 designations now in group (was 856 before test)
   - Integration working end-to-end

## Verification Evidence

**WordPress Admin:**
- Settings page shows Master Campaign ID: 764694 with green checkmark
- Settings page shows Master Component ID: mKAgOmLtRHVGFGh_eaqM6
- Test fund meta box shows Designation ID: 1896370

**Classy Admin:**
- Program Designations: 1896370 "Phase 6 Test Fund - DELETE ME" (Enabled)
- Campaign 764694 → Group designations → Default Active Group
- "Phase 6 Test Fund - DELETE ME" appears at TOP of list (External ID: 13854)

## Issues Encountered

**SSH Timeout:**
- Initial rsync deployment failed due to SSH connection timeout
- Resolved after ~5 minute wait and retry
- Likely temporary network/WP Engine issue

## Critical Finding Confirmed

Luke Dringoli's email confirmation was accurate:
- `PUT /campaigns/{id}` with `{"designation_id": ...}` adds designation to campaign's active group
- New designations appear at the TOP of the Default Active Group list
- Designations are immediately available in donation form dropdown

## Known Issue: Default Designation Overwrite

**Issue:** The `update_campaign()` API with `{"designation_id": ...}` has a side effect — it sets the specified designation as the campaign's **default** (lock icon).

**Impact:** Every new fund published becomes the campaign's default designation, overwriting the previous default.

**Workaround:** Manually reset the default in Classy UI (Edit Campaign → Group designations → pencil icon → change default)

**Future Fix Options:**
1. Store a "general fund" designation ID in settings; after linking, call API again to reset default
2. Research if alternative API endpoint exists that adds without setting default
3. Accept behavior since Phase 7 uses `?designation={id}` to pre-select specific funds anyway

**Status:** Documented for future review. Core functionality (adding to group) works correctly.

## Cleanup Notes

Test fund "Phase 6 Test Fund - DELETE ME" can be:
- Trashed in WordPress (will set designation is_active = false)
- Optionally deleted from Classy if desired
- Left as-is for reference

## Next Phase Readiness

**Ready for Phase 7 (Frontend Embed):**
- Master campaign ID configured: 764694
- Master component ID configured: mKAgOmLtRHVGFGh_eaqM6
- Designations automatically linked to campaign
- Can now implement embed with `?designation={id}` pre-selection

**No blockers for Phase 7.**

---
*Phase: 06-master-campaign-integration*
*Completed: 2026-01-29*
