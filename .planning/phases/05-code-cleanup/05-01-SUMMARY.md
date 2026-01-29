---
phase: 05-code-cleanup
plan: 01
subsystem: refactor
tags: [php, wordpress, cleanup, code-quality]

# Dependency graph
requires:
  - phase: 01-configuration
    provides: "API client and sync handler base classes"
  - phase: 04-inbound-sync
    provides: "Designation sync implementation"
provides:
  - "Clean sync handler with designation-only logic"
  - "API client without obsolete campaign lifecycle methods"
  - "~430 lines of dead code removed"
affects: [06-master-campaign-integration, 07-frontend-embed]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Designation-only sync pattern (no per-fund campaigns)"

key-files:
  created: []
  modified:
    - "includes/class-sync-handler.php"
    - "includes/class-api-client.php"

key-decisions:
  - "Removed all campaign duplication and status management code"
  - "Preserved campaign methods needed for Phase 6 (update_campaign for designation linking)"
  - "Preserved campaign methods used by inbound sync (get_campaign_overview)"

patterns-established:
  - "Sync handler focuses exclusively on designation CRUD"
  - "API client retains campaign methods only for master campaign integration and admin UI"

# Metrics
duration: 4min
completed: 2026-01-29
---

# Phase 05 Plan 01: Remove Obsolete Campaign Code Summary

**Removed ~430 lines of obsolete campaign sync code from sync handler and API client after architecture pivot to single master campaign approach**

## Performance

- **Duration:** 4 min
- **Started:** 2026-01-29T12:36:58Z
- **Completed:** 2026-01-29T12:40:55Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Removed all campaign duplication and lifecycle code from sync handler (9 methods, 2 constants)
- Removed campaign lifecycle methods from API client (5 methods)
- Preserved designation sync functionality completely intact
- Reduced code complexity and maintenance burden

## Task Commits

Each task was committed atomically:

1. **Task 1: Remove campaign methods from sync-handler.php** - `6257ea2` (refactor)
   - Removed META_CAMPAIGN_ID and META_CAMPAIGN_URL constants
   - Removed 9 campaign methods (sync_campaign_to_gofundme, create_campaign_in_gfm, update_campaign_in_gfm, build_campaign_data, get_fund_goal, get_campaign_id, get_campaign_url, should_sync_campaign, ensure_campaign_active)
   - Removed campaign sync calls from all 5 hook callbacks
   - 362 lines deleted

2. **Task 2: Remove campaign lifecycle methods from api-client.php** - `7384ce7` (refactor)
   - Removed 5 lifecycle methods (duplicate_campaign, publish_campaign, unpublish_campaign, reactivate_campaign, deactivate_campaign)
   - Preserved 5 useful methods (create_campaign, update_campaign, get_campaign, get_campaign_overview, get_all_campaigns)
   - 67 lines deleted

**Total:** 429 lines of dead code removed

## Files Created/Modified
- `includes/class-sync-handler.php` (397 lines, was 759) - Designation-only sync handler
- `includes/class-api-client.php` (379 lines, was 446) - API client with designation methods + useful campaign methods

## Decisions Made

**1. Preserved specific campaign methods for future phases**
- Rationale: update_campaign() needed for Phase 6 to link designations to master campaign via `PUT /campaigns/{id}` with `designation_id`
- get_campaign_overview() used by Phase 4 inbound sync for donation totals
- get_campaign() used by admin UI for validation

**2. Removed all campaign lifecycle methods**
- Rationale: duplicate_campaign, publish_campaign, unpublish_campaign, reactivate_campaign, deactivate_campaign were only used by deleted sync-handler campaign code
- These methods are obsolete under single master campaign architecture

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for Phase 6:** Master Campaign Integration
- Sync handler cleaned of campaign duplication logic
- API client retains update_campaign() for linking designations to master campaign
- Codebase simpler and easier to understand

**No blockers:** All verification passed
- PHP syntax check passed for both files
- No "campaign" references remain in sync-handler.php (except in comments/docs)
- Designation methods fully preserved
- File line counts meet requirements (397 and 379 lines)

---
*Phase: 05-code-cleanup*
*Completed: 2026-01-29*
