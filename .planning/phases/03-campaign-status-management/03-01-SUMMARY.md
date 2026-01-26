# Plan 03-01 Summary: Fix draft status to call unpublish_campaign()

## Completion

**Status:** Complete
**Date:** 2026-01-26
**Duration:** ~20 minutes

## What Was Built

Fixed the bug where setting a fund to draft incorrectly deactivated the campaign instead of unpublishing it.

### Changes Made

1. **class-sync-handler.php** - Modified `on_status_change()` to:
   - Call `unpublish_campaign()` when fund status changes to draft
   - Removed catch-all else clause that caused double deactivation on trash
   - Trash/delete now handled exclusively by their dedicated hooks

2. **fcg-gofundme-sync.php** - Version bump to 2.1.4

3. **CLAUDE.md** - Added Deployment section documenting:
   - WP Engine rsync deployment method (SCP not supported)
   - Commands for staging and production deployment
   - Post-deployment cleanup instructions

## Commits

| Hash | Message | Files |
|------|---------|-------|
| 57c903d | fix(03-01): correct draft status to unpublish (not deactivate) campaign | includes/class-sync-handler.php, fcg-gofundme-sync.php |
| f99e6cb | docs(03-01): document rsync deployment method for WP Engine | CLAUDE.md |

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

Gap closure plan 03-02 created to add Campaign ID to admin meta box (user feedback).
