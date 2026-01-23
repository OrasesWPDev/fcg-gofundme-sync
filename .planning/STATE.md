# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-22)

**Core value:** When a fund is published in WordPress, both the designation AND campaign are automatically created in Classy with correct settings — no manual data entry required.
**Current focus:** Phase 2 - Campaign Push Sync

## Current Position

Phase: 2 of 6 (Campaign Push Sync)
Plan: 1 of 4 complete in current phase
Status: Executing phase 02
Last activity: 2026-01-23 — Plan 02-01 complete (campaign lifecycle API methods)

Progress: [██░░░░░░░░] 20%

## Performance Metrics

**Velocity:**
- Total plans completed: 1
- Average duration: N/A
- Total execution time: N/A

**By Phase:**

| Phase | Plans | Status |
|-------|-------|--------|
| 01 - Configuration | 2 | Complete |
| 02 - Campaign Push Sync | 1/4 | In Progress |

**Recent Completions:**
- 02-01: Campaign lifecycle API methods (e38e439)

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- Campaign duplication approach: Must use duplicateCampaign endpoint (POST /campaigns returns 403)
- 1:1 fund-to-campaign relationship: Simplest model, matches business need
- WordPress wins on conflicts: Existing pattern, client is source of truth

### Pending Todos

None yet.

### Blockers/Concerns

**Phase 2 concerns (from research):**
- Must validate which campaign fields can be updated post-duplication with Classy contact
- Template campaign must be Studio type (not legacy Classy Mode)

**Phase 4 concerns (from research):**
- WP-Cron unreliable on cached sites — must use server cron in production

**Phase 5 concerns (from research):**
- API rate limits unknown — must load test to determine safe throttling
- 758 funds will timeout without proper batching (50 per batch recommended)

## Session Continuity

Last session: 2026-01-23 (plan 02-01 execution)
Stopped at: Plan 02-01 complete, ready for plan 02-02 execution
Resume file: None
