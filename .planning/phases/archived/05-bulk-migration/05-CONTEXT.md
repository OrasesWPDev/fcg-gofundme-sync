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
**Status:** Awaiting Classy response to clarifying questions (2026-01-27)

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

### Classy's Offer (2026-01-27)

Luke Dringoli offered to:
1. Duplicate template campaign 758+ times using internal Studio endpoints
2. Apply updates from spreadsheet with "exact call body" for each campaign
3. Publish campaigns on our behalf

**Critical revelation:** Even Classy-created Studio campaigns cannot be updated via public `updateCampaign` API. Plugin becomes one-way (inbound only) for campaign data.

### Two-Tier Approach Identified

Analysis of WordPress fund data revealed two categories:

**Rich funds (345 / 40%)** — Have featured image + excerpt
- Need full Embedded Form with Content Panel

**Simple funds (516 / 60%)** — Have excerpt only, no image
- Can use Inline Donation Grid only

### Questions Sent to Luke (2026-01-27)

Email sent with clarifying questions before creating data export:

1. Can campaigns work with partial Content Panel data (text but no image)?
2. For funds WITH images, can we provide WordPress image URLs for Classy to upload?
3. Should all campaigns get Content Panel data for consistency?
4. Do Partner Logos carry over from template automatically?
5. Should campaigns be linked to their corresponding Designation IDs?
6. What's the workflow for NEW funds after bulk migration?
7. Any timeline for public Studio API endpoints?

**Email draft saved:** `docs/email-draft-luke-migration.md`

### Data Available for Export

- WordPress Post ID — 100%
- Fund Title — 100%
- Excerpt/Description — ~100%
- Fundraising Goal — varies
- Featured Image URL — 40% (345 funds)
- Designation ID — 100% (if needed for linkage)

### What We Need Back from Classy

Mapping file format:
```
WordPress Post ID, Campaign ID
1611, [new campaign ID]
1938, [new campaign ID]
```

</blocker>

<deferred>
## Deferred Ideas

None — discussion focused on resolving the API limitation blocker.

</deferred>

---

*Phase: 05-bulk-migration*
*Context gathered: 2026-01-26*
*Status: Blocked pending Classy support response*
