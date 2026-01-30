# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-29)

**Core value:** When a fund is published in WordPress, the designation is automatically created in Classy and linked to the master campaign — no manual data entry required.

## Current Position

Phase: 8 (Production Launch MVP)
Plan: 3 of 3
Status: **Complete** ✅
Last activity: 2026-01-30 — Phase 8 verified (7/7 must-haves passed)

Progress: [█████████░] 85% (6 of 7 phases complete)

## Classy Call Summary (2026-01-29)

Met with Luke Dringoli and Jon Bierma from Classy. Key outcomes:
- ✅ Architecture validated
- ✅ DELETE endpoint confirmed (removes from campaign + designations)
- ✅ Modal workaround (direct links) confirmed appropriate
- 📋 Roadmap updated: Phase 8 = MVP (admin UI + production), Phase 9 = modal/theme enhancements

## Architecture Pivot Summary (2026-01-28)

Classy confirmed single master campaign approach:
- `?designation={id}` works with inline embeds
- `PUT /campaigns/{id}` with `{"designation_id": "{id}"}` links designation to campaign

**Result:** Old campaign sync code is now obsolete. New roadmap:
- ~~Phase 5: Code Cleanup~~ ✅ Complete
- ~~Phase 6: Master Campaign Integration~~ ✅ Complete
- ~~Phase 7: Frontend Embed~~ ✅ Complete (with modal workaround)
- ~~Phase 8: Production Launch (MVP)~~ ✅ Complete
- Phase 9: Modal & Theme Enhancements (future)

See: `.planning/ARCHITECTURE-PIVOT-2026-01-28.md` for full details

## Performance Metrics

| Phase | Status |
|-------|--------|
| 01 - Configuration | Complete |
| 04 - Inbound Sync | Complete |
| 05 - Code Cleanup | Complete (2026-01-29) |
| 06 - Master Campaign Integration | Complete (2026-01-29) |
| 07 - Frontend Embed | **Complete** (2026-01-29) ✅ |
| 08 - Production Launch (MVP) | **Complete** (2026-01-30) ✅ |
| 09 - Modal & Theme Enhancements | Future |

**Archived:** Phases 2, 3, original 5 — see `.planning/phases/archived/`

## Accumulated Context

### Key Decisions

| Decision | Phase | Context |
|----------|-------|---------|
| ARCHITECTURE PIVOT (2026-01-28) | - | Single master campaign instead of per-fund campaigns |
| Designation sync is critical | - | Keep all of it |
| Campaign duplication code is dead code | 05 | Removed in Phase 5 |
| `?designation={id}` URL parameter | - | Pre-selects fund in embed |
| Removed campaign sync methods from sync handler | 05-01 | 9 methods deleted (sync_campaign_to_gofundme, create_campaign_in_gfm, etc.) |
| Removed campaign lifecycle methods from API client | 05-01 | 5 methods deleted (duplicate, publish, unpublish, reactivate, deactivate) |
| Preserved campaign methods for Phase 6 | 05-01 | update_campaign() needed for designation linking, get_campaign_overview() for inbound sync |
| Version 2.3.0 uses minor bump (not major) | 05-03 | Architecture change but not breaking for existing designations |
| Legacy meta keys documented as orphaned | 05-03 | Can be cleaned up with WP-CLI if needed, not removed from database |
| **New designations must be added to default group** | 05-04 | API creates designation but doesn't add to campaign's active group — Phase 6 must fix |
| Renamed "template" to "master" campaign | 06-01 | More accurate terminology for single master campaign architecture |
| Graceful linking failure | 06-01 | Linking failure logged but doesn't fail overall sync - designation is created |
| Automatic migration for existing installations | 06-01 | Old template setting migrates to new master setting on admin page load |
| Master component ID stored separately | 06-01 | Used for frontend embed code, not API calls |
| Use history.replaceState() for URL parameter injection | 07-01 | Non-disruptive method that adds ?designation={id} without page reload |
| Document theme changes in plugin repository | 07-01 | Theme file outside plugin repo needs deployment tracking via docs/ |
| Graceful fallback for unconfigured funds | 07-01 | Show "coming soon" message when designation or settings missing |
| Disable modals due to Classy SDK incompatibility | 07-02 | SDK custom elements fail inside Bootstrap modals |
| Direct fund page links instead of modals | 07-02 | Workaround provides working donation path with one extra click |
| Keep modal code commented (not deleted) | 07-02 | Easy rollback if Classy fixes SDK in future |
| Display donation section only when data exists | 08-01 | Prevents empty UI section for funds without donations |
| Goal progress shown only with both goal and progress values | 08-01 | Avoids showing "0%" for funds without fundraising goals |
| DELETE removes designation entirely (not just deactivate) | 08-02 | Permanent delete returns 404 from Classy API |
| Default designation cannot be deleted | 08-02 | Must change default in Classy before deleting that designation |
| Sync status is binary (not time-based) | 08-03 | If designation ID exists and no error, show "Synced" regardless of last sync time |

### Phase 5 Results (Staging Verification)

- **859 published funds** all have designation IDs
- **856 designations** in Classy's "Default Active Group" (manually added)
- **5 new designations** created via API are NOT in the group yet
- **11 test designations** cleaned up from Classy

### Pending Manual Work

- ~~Create master campaign in Classy UI~~ ✅ Done (Campaign 764694)
- Enable Alternate Cron on WP Engine Production (before go-live)

### Blockers/Concerns

~~**Phase 6 Critical Finding:** Creating a designation via API does NOT automatically add it to the campaign's default designation group. New designations won't appear in the donation embed until this is addressed.~~

**RESOLVED** (2026-01-29): Phase 6 implemented automatic designation linking via `update_campaign()` API. New designations are now linked to master campaign immediately after creation.

## Session Continuity

Last session: 2026-01-30
Stopped at: Phase 8 complete and verified

**Phase 8 Complete:**
- 08-01: Admin meta box donation totals display ✅
- 08-02: DELETE sync verification + deployment checklist ✅
- 08-03: Fixed sync status column to show "Synced" ✅
- Verification: 7/7 must-haves passed

**Key outcomes:**
1. Admin can see donation totals in fund edit screen
2. DELETE permanently removes designation from Classy
3. Sync status column correctly shows "Synced" for linked funds
4. Production deployment checklist documented

**Next steps:**
1. Deploy to production (user task - see docs/production-deployment-checklist.md)
2. Phase 9 (Future): Modal enhancements and theme refactor

**Staging verification URL:** `https://frederickc2stg.wpengine.com/wp-admin/post.php?post=13854&action=edit`

Resume file: None

## Phase 6 Verification Results

- **Test Fund:** "Phase 6 Test Fund - DELETE ME" (post ID 13854)
- **Designation ID:** 1896370
- **Classy Verification:** ✅ Designation appears in campaign 764694's Default Active Group
- **Active Group Count:** 862 designations (857 + test + 5 pending linked)
- **API Confirmation:** `update_campaign()` with `designation_id` adds to active group
- **5 Pending Designations:** Linked via script (13826, 13795, 13782, 13781, 13758)

## Known Issues

**Default Designation Overwrite (Phase 6):**
- `update_campaign()` API sets each new designation as the campaign default
- Last synced designation becomes the default (lock icon in Classy)
- **Workaround:** Manually reset default in Classy UI
- **Status:** Documented for future review; doesn't block Phase 8

**Classy SDK Modal Incompatibility (Phase 7):**
- Classy SDK custom elements (`<cl-donation-form>`) fail inside Bootstrap modals
- Error: `Failed to construct 'HTMLElement': Illegal constructor`
- Payment flow breaks when Classy tries to open its internal modal
- **Workaround:** Disabled archive page modals, use direct fund page links
- **Status:** ✅ Workaround validated by Classy (call 2026-01-29)
- **Future fix (Phase 9):** Use Classy "button link" version instead of Bootstrap modal
- **Clarification:** Designation ID persistence is only an issue with fund-modal.php (lacks post context); single fund page (fund-form.php) works correctly via URL injection

**Theme File Deployment (Phase 7):**
- Theme files (fund-form.php, archive-funds.php) are separate from plugin deployment
- **Staging:** ✅ Deployed and tested
- **Production:** Pending (deploy with plugin v2.3.0 go-live)
- See docs/theme-fund-form-embed.md for deployment instructions
