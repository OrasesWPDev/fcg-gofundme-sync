# FCG GoFundMe Pro Sync

## What This Is

A WordPress plugin that synchronizes the "funds" custom post type with GoFundMe Pro (Classy) designations and campaigns via their API. When a client adds or updates a fund in WordPress, it automatically creates/updates both a designation and a campaign in Classy. The plugin also polls Classy for donation data and syncs it back to WordPress.

## Core Value

When a fund is published in WordPress, both the designation AND campaign are automatically created in Classy with correct settings — no manual data entry in Classy required.

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

New capabilities to build:

- [ ] Campaign creation via template duplication when fund published
- [ ] Campaign update when fund updated (name, goal, overview)
- [ ] Campaign deactivate/reactivate on trash/untrash
- [ ] Inbound sync: pull donation totals from Classy
- [ ] Inbound sync: pull campaign status from Classy
- [ ] Inbound sync: pull goal progress from Classy
- [ ] fundraising_goal ACF field on funds
- [ ] Template campaign ID plugin setting
- [ ] Bulk migration tool for existing funds without campaigns
- [ ] Campaign URL storage and display in admin

### Out of Scope

- Direct campaign creation via API — Classy doesn't support public endpoint
- Real-time webhooks — Classy doesn't offer webhook integration
- Multiple campaigns per fund — 1:1 relationship only
- Donation-level sync — Only totals, not individual transactions
- Campaign deletion — Only deactivation (preserves donation history)

## Context

**Technical Environment:**
- WordPress 5.8+ on WP Engine hosting
- PHP 7.4+ with WordPress HTTP API
- Classy API 2.0 with OAuth2 client credentials
- ACF for custom fields
- WP-CLI available for operations

**API Constraints:**
- `POST /organizations/{org_id}/campaigns` is NOT a public endpoint
- Must use `duplicateCampaign` endpoint to create campaigns from template
- `publishCampaign` endpoint to make campaigns live
- Awaiting confirmation from Classy contact on what fields can be updated post-duplication

**Existing Data:**
- ~758 funds in WordPress, all with designations
- None have campaigns yet — bulk migration needed

**Staging Environment:**
- SSH: `frederickc2stg@frederickc2stg.ssh.wpengine.net`
- Uses Classy sandbox API credentials

## Constraints

- **API**: Must use campaign duplication, not direct creation
- **Pre-requisite**: Template campaign must exist in Classy before plugin can create campaigns
- **ACF**: Requires ACF plugin for fundraising_goal field
- **WP Engine**: Environment variables for credentials (no hardcoded secrets)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Campaign duplication approach | Classy doesn't expose direct campaign creation publicly | — Pending (awaiting Classy confirmation on updatable fields) |
| 1:1 fund-to-campaign relationship | Simplest model, matches business need | — Pending |
| WordPress wins on conflicts | Existing pattern, client is source of truth | ✓ Good |
| Scheduled inbound sync (15 min) | Balances freshness with API rate limits | — Pending |
| Bulk migration tool | 758 existing funds need campaigns | — Pending |

---
*Last updated: 2026-01-22 after initialization*
