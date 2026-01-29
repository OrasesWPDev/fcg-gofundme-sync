# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-22)

**Core value:** When a fund is published in WordPress, the designation is automatically created in Classy and linked to the master campaign — no manual data entry required.

## Current Position

Phase: 7 (Frontend Embed)
Plan: 1 of 1
Status: **Phase complete**
Last activity: 2026-01-29 — Completed 07-01-PLAN.md

Progress: [████████░░] 83% (5 of 6 phases complete)

## Architecture Pivot Summary (2026-01-28)

Classy confirmed single master campaign approach:
- `?designation={id}` works with inline embeds
- `PUT /campaigns/{id}` with `{"designation_id": "{id}"}` links designation to campaign

**Result:** Old campaign sync code is now obsolete. New roadmap:
- ~~Phase 5: Code Cleanup~~ ✅ Complete
- Phase 6: Master Campaign Integration (settings + linking)
- Phase 7: Frontend Embed
- Phase 8: Admin UI (optional)

See: `.planning/ARCHITECTURE-PIVOT-2026-01-28.md` for full details

## Performance Metrics

| Phase | Status |
|-------|--------|
| 01 - Configuration | Complete |
| 04 - Inbound Sync | Complete |
| 05 - Code Cleanup | Complete (2026-01-29) |
| 06 - Master Campaign Integration | Complete (2026-01-29) |
| 07 - Frontend Embed | **Complete** (2026-01-29) ✅ |
| 08 - Admin UI | Not started |

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

Last session: 2026-01-29
Stopped at: Completed Phase 7 (Frontend Embed)

**Next steps:**
1. Deploy fund-form.php theme file to staging
2. Configure plugin settings (master campaign ID and component ID)
3. Test Classy embed with designation pre-selection
4. Plan Phase 8 (Admin UI) if needed

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

**Theme File Deployment (Phase 7):**
- fund-form.php is theme file, separate from plugin deployment
- Must be deployed manually to staging/production
- See docs/theme-fund-form-embed.md for deployment instructions
- **Status:** Documented; awaiting deployment to staging for testing
