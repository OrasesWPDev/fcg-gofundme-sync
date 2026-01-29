# Plan 03-01 Summary: Fix draft status to call unpublish_campaign()

## Completion

**Status:** Complete
**Date:** 2026-01-26
**Final Version:** 2.1.6

## What Was Built

Fixed the bug where setting a fund to draft incorrectly deactivated the campaign instead of unpublishing it. Also added Campaign ID display to admin meta box (user feedback) and corrected Classy admin URLs.

### Changes Made

1. **class-sync-handler.php** - Modified `on_status_change()` to:
   - Call `unpublish_campaign()` when fund status changes to draft
   - Removed catch-all else clause that caused double deactivation on trash
   - Trash/delete now handled exclusively by their dedicated hooks

2. **class-admin-ui.php** - Enhanced meta box:
   - Added Campaign ID display (above Designation ID)
   - Fixed admin URLs to use correct Classy format: `/admin/{org_id}/campaigns/{id}` and `/admin/{org_id}/settings/designations/{id}`
   - Gets org_id from GOFUNDME_ORG_ID env var/constant

3. **fcg-gofundme-sync.php** - Version bumps: 2.1.4 → 2.1.5 → 2.1.6

4. **CLAUDE.md** - Added Deployment section documenting:
   - WP Engine rsync deployment method (SCP not supported)
   - Commands for staging and production deployment
   - Post-deployment cleanup instructions

## Commits

| Hash | Message | Files |
|------|---------|-------|
| 57c903d | fix(03-01): correct draft status to unpublish (not deactivate) campaign | includes/class-sync-handler.php, fcg-gofundme-sync.php |
| f99e6cb | docs(03-01): document rsync deployment method for WP Engine | CLAUDE.md |
| bf8e161 | feat(03): add Campaign ID to admin meta box | includes/class-admin-ui.php, fcg-gofundme-sync.php |
| fb75425 | fix(03): correct Classy admin URLs in meta box | includes/class-admin-ui.php, fcg-gofundme-sync.php |

## Verification

### E2E Tests Passed

| Test | Action | Expected | Result |
|------|--------|----------|--------|
| STAT-01 | draft → unpublished | unpublished | PASS |
| STAT-02 | publish → active | active | PASS |
| STAT-03 | full cycle | active→unpublished→active | PASS |
| Trash regression | trash → deactivated | deactivated | PASS |

### Human Verification

- User confirmed via Classy dashboard screenshots
- Draft status shows "Unpublished" (yellow badge)
- Republish shows "Published/Active" (green badge)

## Technical Details

**Why unpublish vs deactivate:**
- Unpublished campaigns: single `publish` call to restore (1 API call)
- Deactivated campaigns: `reactivate` + `publish` to restore (2 API calls)
- Draft is temporary; trash is more permanent - status handling reflects this

**Why else clause was removed:**
- `on_trash_fund()` hook already handles trash → deactivate
- `on_delete_fund()` hook already handles permanent deletion
- Keeping else caused duplicate API calls (on_status_change fires before trash hook)

## Deviations

None - plan executed as specified.

## Issues Encountered

- SSH escaping complexities when running wp-cli commands remotely (resolved by using rsync + wp eval-file)
- Error logs not available on staging (WP_DEBUG may be off) - verified via API responses instead

## Next Steps

Phase 3 complete. Ready for Phase 4 (Inbound Sync).
