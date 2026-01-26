# Phase 5: Bulk Migration - Context

**Gathered:** 2026-01-26
**Status:** BLOCKED — Awaiting Classy response

<domain>
## Phase Boundary

Create campaigns for 758 existing WordPress funds that don't have Classy campaigns via WP-CLI batch migration tool. Manual creation through the Classy UI is impractical at this scale.

</domain>

<decisions>
## Implementation Decisions

### WP-CLI Command Design
- Dry-run mode by default (requires `--live` flag to execute)
- Batch size of 50 funds per run
- Resumable via `--page=N` parameter
- Throttling between API calls (configurable via `--throttle`)
- Progress bar with success/failure summary

### Reuse Existing Code
- Leverage existing `create_campaign_in_gfm()` method from sync handler
- Change method visibility from private to public (one-line change)
- Existing 60-second transient lock prevents race conditions

### Claude's Discretion
- Exact progress bar implementation
- Error message formatting
- Memory cleanup strategy (wp_cache_flush interval)

</decisions>

<specifics>
## Specific Ideas

Plan 05-01-PLAN.md fully specifies the implementation:
- FCG_GFM_Migration_Command class in `includes/class-migration-command.php`
- PHPDoc synopsis for command documentation
- WP_Query pagination with meta_query for funds without campaign_id
- Respect `disable_campaign_sync` ACF field

</specifics>

<blocker>
## BLOCKER: Studio Campaign API Limitation

**Discovered:** 2026-01-26
**Severity:** Complete blocker for Phase 5 execution

### The Problem

Classy's public API `duplicateCampaign` and `publishCampaign` endpoints do not properly support **Studio campaign types**.

API-created campaigns:
- Appear "Published" in Classy dashboard with correct type ("Studio Donation - Embedded")
- **Design tab:** "Oops! Something went wrong. There was an error loading your campaign."
- **Settings tab:** "We can't seem to find that page."

The campaigns exist as records but the Studio design data is not properly initialized.

### Confirmed By

Classy Support (Luke Dringoli, Jon Bierma) via email 2026-01-26:
> "Our duplicateCampaign and publishCampaign endpoints actually don't support the Studio campaign type... we have a separate set of internal API endpoints for Studio Campaigns to perform tasks such as duplicating and publishing. At this point, they are not available in our public API, but we are considering options to change that in the future."

### Template Campaign

- **FCG Template Source** (762968) — Studio Donation - Embedded
- All test campaigns created via API duplication are broken

### Options Presented to Classy

1. **Classy runs bulk duplications** using internal Studio endpoints (offered by support)
2. **Provide fund list** for Classy to batch-create campaigns
3. **Alternative campaign type** that works with public API
4. **Wait for public API** to support Studio campaigns (timeline unknown)

### Email Sent

2026-01-26 to Luke Dringoli & Jon Bierma at GoFundMe Pro requesting recommendation for fastest path to get 758 funds synced with working campaigns.

</blocker>

<deferred>
## Deferred Ideas

None — discussion focused on resolving the API limitation blocker.

</deferred>

---

*Phase: 05-bulk-migration*
*Context gathered: 2026-01-26*
*Status: Blocked pending Classy support response*
