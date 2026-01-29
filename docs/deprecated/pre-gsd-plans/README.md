# Pre-GSD Planning Documents (Archived)

**Archived:** 2026-01-29
**Reason:** These documents predate the GSD workflow adoption and the architecture pivot (2026-01-28).

## What Changed

These plans described the original approach:
- Per-fund campaign duplication (one campaign per WordPress fund)
- Campaign status management (publish/unpublish/deactivate)
- Bulk migration of 758+ funds to individual campaigns

The architecture pivot (2026-01-28) moved to a **single master campaign** model:
- ONE master campaign contains all designations
- `?designation={id}` URL parameter pre-selects fund in embed
- No per-fund campaigns needed

## Current Planning

All active planning is now in:
- `.planning/ROADMAP.md` - Current phase structure
- `.planning/phases/` - Phase-specific plans and summaries
- `.planning/PROJECT.md` - Requirements and constraints

## Files in This Directory

| File | Original Purpose |
|------|------------------|
| PRD.md | Original product requirements (bidirectional sync) |
| phase-1-validation-results.md | Initial plugin validation |
| phase-2-implementation-plan.md | Polling infrastructure (implemented differently) |
| phase-3-implementation-plan.md | Campaign sync (obsolete) |
| phase-4-implementation-plan.md | Inbound sync (implemented via GSD) |
| phase-5-implementation-plan.md | Bulk migration (no longer needed) |
| phase-6-implementation-plan.md | Admin UI (pending as Phase 8) |

---
*Archived from docs/ on 2026-01-29*
