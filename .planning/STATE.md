# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-22)

**Core value:** When a fund is published in WordPress, the designation is automatically created in Classy and linked to the master campaign — no manual data entry required.

## Current Position

Phase: 5 (Code Cleanup)
Plan: 02 of 4
Status: **In progress**
Last activity: 2026-01-29 — Completed 05-02-PLAN.md (poller and admin UI cleanup)

Progress: [████░░░░░░] 33% (2 of 6 phases complete, Phase 5: 1 of 4 plans done)

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
| 05 - Code Cleanup | In progress (1 of 4 plans complete) |
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
| Silent degradation for empty campaign meta | 05-02 | poll_campaigns() silently returns when no funds have campaigns (expected post-pivot) |
| Campaign status storage removed | 05-02 | META_CAMPAIGN_STATUS orphaned after pivot |
| Admin UI shows designation-only info | 05-02 | Prevents confusion from stale campaign data |

### Pending Manual Work

- Create master campaign in Classy UI (before Phase 6)
- Enable Alternate Cron on WP Engine Production (before go-live)

### Blockers/Concerns

None. Architecture pivot resolved all blockers.

## Session Continuity

Last session: 2026-01-29
Stopped at: Completed 05-02-PLAN.md

**Next steps:**
1. Execute 05-01-PLAN.md (remove campaign duplication code)
2. Execute 05-03-PLAN.md (cleanup orphaned campaign meta)
3. Execute 05-04-PLAN.md (remove unused campaign endpoints)
4. Verify designation sync still works

Resume file: None
