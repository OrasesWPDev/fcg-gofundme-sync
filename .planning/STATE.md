# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-22)

**Core value:** When a fund is published in WordPress, the designation is automatically created in Classy and linked to the master campaign — no manual data entry required.

## Current Position

Phase: 5 (Code Cleanup)
Plan: 3 of 4
Status: **In progress**
Last activity: 2026-01-29 — Completed 05-03-PLAN.md (updated documentation and bumped version to 2.3.0)

Progress: [████░░░░░░] 33% (2 of 6 phases complete, Phase 5: 3 of 4 plans done)

## Architecture Pivot Summary (2026-01-28)

Classy confirmed single master campaign approach:
- `?designation={id}` works with inline embeds
- `PUT /campaigns/{id}` with `{"designation_id": "{id}"}` links designation to campaign

**Result:** Old campaign sync code is now obsolete. New roadmap:
- Phase 5: Code Cleanup
- Phase 6: Master Campaign Integration (settings + linking)
- Phase 7: Frontend Embed
- Phase 8: Admin UI (optional)

See: `.planning/ARCHITECTURE-PIVOT-2026-01-28.md` for full details

## Performance Metrics

| Phase | Status |
|-------|--------|
| 01 - Configuration | Complete |
| 04 - Inbound Sync | Complete |
| 05 - Code Cleanup | In progress (3 of 4 plans complete) |
| 06 - Master Campaign Integration | Not started |
| 07 - Frontend Embed | Not started |
| 08 - Admin UI | Not started |

**Archived:** Phases 2, 3, original 5 — see `.planning/phases/archived/`

## Accumulated Context

### Key Decisions

| Decision | Phase | Context |
|----------|-------|---------|
| ARCHITECTURE PIVOT (2026-01-28) | - | Single master campaign instead of per-fund campaigns |
| Designation sync is critical | - | Keep all of it |
| Campaign duplication code is dead code | 05 | Remove in Phase 5 |
| `?designation={id}` URL parameter | - | Pre-selects fund in embed |
| Removed campaign sync methods from sync handler | 05-01 | 9 methods deleted (sync_campaign_to_gofundme, create_campaign_in_gfm, etc.) |
| Removed campaign lifecycle methods from API client | 05-01 | 5 methods deleted (duplicate, publish, unpublish, reactivate, deactivate) |
| Preserved campaign methods for Phase 6 | 05-01 | update_campaign() needed for designation linking, get_campaign_overview() for inbound sync |
| Version 2.3.0 uses minor bump (not major) | 05-03 | Architecture change but not breaking for existing designations |
| Legacy meta keys documented as orphaned | 05-03 | Can be cleaned up with WP-CLI if needed, not removed from database |

### Pending Manual Work

- Create master campaign in Classy UI (before Phase 6)
- Enable Alternate Cron on WP Engine Production (before go-live)

### Blockers/Concerns

None. Architecture pivot resolved all blockers.

## Session Continuity

Last session: 2026-01-29
Stopped at: Completed 05-03-PLAN.md

**Next steps:**
1. Execute 05-04-PLAN.md (archive obsolete test files) - final cleanup plan
2. Verify designation sync still works after cleanup
3. Proceed to Phase 6 (Master Campaign Integration)

Resume file: None
