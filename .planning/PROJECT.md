# FCG GoFundMe Pro Sync

## What This Is

A WordPress plugin that synchronizes the "funds" custom post type with GoFundMe Pro (Classy) designations via their API. When a client adds or updates a fund in WordPress, it automatically creates/updates a designation in Classy and links it to a single master campaign. The plugin also polls Classy for donation data and syncs it back to WordPress.

## Core Value

When a fund is published in WordPress, the designation is automatically created in Classy and linked to the master campaign — no manual data entry required.

## Requirements

### Validated

These capabilities already work in the current codebase:

- ✓ Designation sync: create on publish — existing
- ✓ Designation sync: update on save — existing
- ✓ Designation sync: deactivate on trash/unpublish — existing
- ✓ Designation sync: delete on permanent delete — existing
- ✓ Designation sync: reactivate on untrash — existing
- ✓ OAuth2 authentication with Classy API — existing
- ✓ Token caching via WordPress transients — existing
- ✓ Post meta storage for sync state — existing
- ✓ WP-Cron polling infrastructure (15 min) — existing
- ✓ Conflict detection (WordPress wins) — existing
- ✓ Retry mechanism with exponential backoff — existing
- ✓ WP-CLI commands (pull/push/status/conflicts/retry) — existing
- ✓ Admin UI with sync status column and meta box — existing

### Active

New capabilities to build (post-architecture pivot):

- [x] Inbound sync: pull donation totals from Classy *(Phase 4 complete)*
- [x] Inbound sync: pull campaign status from Classy *(Phase 4 complete)*
- [x] Inbound sync: pull goal progress from Classy *(Phase 4 complete)*
- [x] fundraising_goal ACF field on funds *(Phase 1 complete)*
- [x] Template campaign ID plugin setting *(Phase 1 complete)*
- [x] Code cleanup: remove obsolete campaign sync code *(Phase 5 complete)*
- [x] Master campaign settings: rename setting, add component ID *(Phase 6 complete)*
- [x] Link designations to master campaign via API *(Phase 6 complete)*
- [x] Frontend embed with `?designation={id}` parameter *(Phase 7 complete, modal workaround)*
- [ ] Admin UI: designation ID, donation totals display *(Phase 8, optional)*

### Out of Scope

- Real-time webhooks — Classy doesn't offer webhook integration
- Donation-level sync — Only totals, not individual transactions
- Multiple designations per fund — 1:1 relationship only
- Per-fund campaigns — Architecture pivot moved to single master campaign

## Context

**Technical Environment:**
- WordPress 5.8+ on WP Engine hosting
- PHP 7.4+ with WordPress HTTP API
- Classy API 2.0 with OAuth2 client credentials
- ACF for custom fields
- WP-CLI available for operations

**Architecture (as of 2026-01-28):**
- Single master campaign with all designations
- `?designation={id}` URL parameter pre-selects fund in embed
- `PUT /campaigns/{id}` with `{"designation_id": "{id}"}` links designation to campaign
- Frontend embeds master campaign with designation parameter

**Existing Data:**
- ~861 funds in WordPress, all with designations
- Master campaign to be created manually in Classy UI

**Staging Environment:**
- SSH: `frederickc2stg@frederickc2stg.ssh.wpengine.net`
- Uses Classy sandbox API credentials

## Constraints

- **Pre-requisite**: Master campaign must exist in Classy before Phase 6
- **ACF**: Requires ACF plugin for fundraising_goal field
- **WP Engine**: Environment variables for credentials (no hardcoded secrets)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Single master campaign | Classy confirmed `?designation={id}` works with embeds | ✓ Architecture pivot 2026-01-28 |
| WordPress wins on conflicts | Existing pattern, client is source of truth | ✓ Good |
| Scheduled inbound sync (15 min) | Balances freshness with API rate limits | ✓ Phase 4 complete |
| Code cleanup before new features | Remove obsolete campaign code for clean codebase | ✓ Phase 5 queued |

---
*Last updated: 2026-01-29 — Phases 5-7 complete*
