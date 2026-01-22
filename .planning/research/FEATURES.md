# Features Research: Campaign Sync

**Domain:** WordPress plugin syncing campaigns with Classy API
**Researched:** 2026-01-22
**Confidence:** MEDIUM (WebSearch findings cross-referenced with existing codebase)

## Executive Summary

Classy API supports comprehensive campaign operations through duplication, status management, and metrics retrieval. The API uses a **template duplication pattern** (no direct POST creation endpoint publicly available) and provides separate endpoints for campaign data vs. donation metrics. Campaign sync for this WordPress plugin must work within these constraints while maintaining parity with the existing designation sync pattern.

**Key Finding:** Unlike designation creation (simple POST), campaign creation requires duplicating a template campaign and overriding specific fields. This adds complexity but is the only public path.

## Table Stakes

Features required for campaign sync to function properly.

| Feature | Why Table Stakes | Complexity | API Support | Notes |
|---------|------------------|------------|-------------|-------|
| **Create via duplication** | Only public method to create campaigns | Medium | `POST /campaigns/{id}/duplicate` | Requires template campaign ID in settings |
| **Update name** | Core WordPress→Classy sync | Low | `PUT /campaigns/{id}` | After duplication |
| **Update goal** | Core WordPress→Classy sync | Low | `PUT /campaigns/{id}` | After duplication |
| **Update overview** | Core WordPress→Classy sync | Low | `PUT /campaigns/{id}` | Maps from post_content |
| **Deactivate on trash** | Match designation behavior | Low | `POST /campaigns/{id}/deactivate` | Preserves donation history |
| **Status retrieval** | Know campaign state | Low | `GET /campaigns/{id}` | Returns status field |
| **Store campaign ID** | Link WP post to Classy campaign | Low | WordPress post meta | `_gofundme_campaign_id` |
| **Store campaign URL** | Display/link to live campaign | Low | WordPress post meta | `_gofundme_campaign_url` from `canonical_url` |

**MVP Dependencies:**
- Template campaign ID must exist in Classy before sync can work
- fundraising_goal field required (ACF or post meta)
- Designation must exist (campaigns link to designations)

## Nice to Have

Features that improve user experience but aren't blocking.

| Feature | Value Proposition | Complexity | API Support | When to Build |
|---------|-------------------|------------|-------------|---------------|
| **Publish after duplication** | Make campaign live immediately | Low | `POST /campaigns/{id}/publish` | Phase 1 (enables testing) |
| **Unpublish on draft** | Match WP publish state | Low | `POST /campaigns/{id}/unpublish` | Phase 1 (status parity) |
| **Reactivate on untrash** | Restore functionality | Medium | No direct endpoint | Phase 2 (update after deactivate) |
| **Get donation totals** | Show progress in WP admin | Medium | Campaign overview endpoint | Phase 2 (polling) |
| **Get donor count** | Show engagement metric | Low | Campaign overview endpoint | Phase 2 (polling) |
| **Get percent-to-goal** | Display progress | Low | Calculated from totals + goal | Phase 2 (polling) |
| **Bulk migration tool** | Create campaigns for 758 existing funds | Medium | Duplication API | Phase 3 (one-time operation) |
| **Campaign preview URL** | Quick access from admin | Low | Use canonical_url field | Phase 2 (UX polish) |

**Rationale for "Nice to Have":**
- Publish/unpublish: Non-blocking because campaigns can be activated manually in Classy if needed
- Donation metrics: Non-blocking because core sync works without them
- Bulk migration: One-time operation, can be deferred until core sync is proven

## Not Supported

Features blocked by API limitations or intentionally excluded.

| Feature | Why Not Supported | Workaround | Impact |
|---------|-------------------|------------|--------|
| **Direct campaign creation** | POST /campaigns endpoint not publicly exposed | Must duplicate from template | Medium: requires template setup |
| **Campaign deletion** | Destroys donation history | Use deactivate endpoint instead | Low: deactivate is sufficient |
| **Real-time sync** | No webhooks available | Poll every 15 minutes via WP-Cron | Low: acceptable for this use case |
| **Individual donation sync** | Out of scope (totals only) | N/A | Low: totals are sufficient |
| **Multiple campaigns per fund** | 1:1 relationship by design | N/A | Low: matches business model |
| **Campaign type change** | Type set on duplication, not updatable | Must delete and recreate | Low: rare operation |
| **Reactivate directly** | No reactivate endpoint exists | Update campaign after deactivation | Medium: requires workaround |

**Critical Limitation - Duplication Pattern:**
The lack of a public POST /campaigns endpoint means:
1. Template campaign must exist in Classy account before plugin works
2. Template must be configured with desired defaults (theme, design, etc.)
3. Overrides only work for specific fields (name, goal, designation_id, etc.)
4. Other settings (theme, ecards, permissions) only copied if specified in `duplicates` array

**Reactivation Workaround:**
After deactivation, campaigns return to "unpublished" state when "reactivated" — but no direct reactivate endpoint exists. Workaround: attempt PUT update which may trigger state change, or document manual process.

## Feature Dependencies

```
Template Campaign (in Classy)
    ↓
[Create Campaign via Duplication]
    ↓
    ├─→ [Set designation_id] (link to designation)
    ├─→ [Set name] (from post_title)
    ├─→ [Set goal] (from ACF/meta)
    └─→ [Set overview] (from post_content)
    ↓
[Publish Campaign] (make live)
    ↓
[Poll for Donation Metrics]
    ↓
    ├─→ gross_amount
    ├─→ donors_count
    └─→ transactions_count
```

**Designation Dependency:**
Campaigns MUST link to a designation via `designation_id`. This means:
- Designation sync must complete before campaign sync
- Or designation must exist from previous sync
- Existing codebase already handles designation sync ✓

**Sequencing:**
1. Publish fund in WordPress
2. Designation created (existing logic)
3. Campaign duplicated from template with designation_id
4. Campaign fields updated (name, goal, overview)
5. Campaign published (optional, can stay unpublished)
6. Poll for metrics every 15 minutes (separate process)

## Campaign Duplication - Override Fields

Fields that can be set when duplicating a campaign (from WebSearch findings):

| Field | Purpose | Source in WordPress |
|-------|---------|---------------------|
| `name` | Campaign title | post_title |
| `goal` | Fundraising goal | ACF fundraising_goal or _fundraising_goal meta |
| `designation_id` | Link to designation | _gofundme_designation_id meta |
| `type` | Campaign type (crowdfunding) | Hardcoded: "crowdfunding" |
| `started_at` | Campaign start date | post_date |
| `timezone_identifier` | Campaign timezone | Hardcoded: "America/New_York" |
| `status` | Initial status | Derived from post_status |

**Fields NOT overridable via duplication:**
- Theme/design (copied from template unless `duplicates: ["theme"]`)
- Tickets, ecards, permissions (copied if specified in `duplicates` array)
- Campaign overview endpoint data (read-only, populated by Classy)

**Fields updatable AFTER duplication:**
Based on WebSearch findings, these can be updated via PUT after campaign exists:
- `name` - ✓ confirmed
- `goal` - ✓ confirmed (but see note about raw_goal)
- `overview` - ✓ assumed (common field, needs verification)
- `started_at` - UNKNOWN (needs verification)

**Goal Field Complexity:**
Campaigns have `goal` (normalized to org currency) and `raw_goal` (in campaign's currency). If `raw_currency_code` differs from org `currency_code`, must use `raw_goal` not `goal`. For this plugin: assume single currency (USD), use `goal` field.

## Campaign Metrics - Overview Endpoint

Donation data lives in separate endpoint from campaign data.

**Endpoint Pattern:** `/campaigns/{id}/overview` (inferred from WebSearch)
**Authentication:** OAuth2 Bearer token (same as other endpoints)

**Available Metrics:**

| Metric | Field Name | Type | Use Case |
|--------|------------|------|----------|
| Gross amount | `gross_amount` | Decimal | Total raised before fees |
| Net amount | `net_amount` | Decimal | Total after fees |
| Fees | `fees_amount` | Decimal | Platform fees |
| Donor count | `donors_count` | Integer | Unique donors |
| Transaction count | `transactions_count` | Integer | Total transactions |
| Donation amount | `donations_amount` | Decimal | One-time donations |
| Registration amount | `registrations_amount` | Decimal | Event registrations |

**What to Sync to WordPress:**
- `gross_amount` → Display as "Total Raised"
- `donors_count` → Display as "Number of Donors"
- Calculate percent-to-goal: `(gross_amount / goal) * 100`

**Polling Frequency:**
Existing codebase uses 15-minute WP-Cron interval. Donation metrics should use same schedule to avoid rate limits.

## Campaign Status Workflow

Understanding status transitions for proper sync behavior.

| WordPress Action | Campaign Status Transition | API Endpoint |
|------------------|---------------------------|--------------|
| Publish fund | duplicate → publish | `POST /campaigns/{id}/duplicate` + `POST /campaigns/{id}/publish` |
| Unpublish fund | active → unpublished | `POST /campaigns/{id}/unpublish` |
| Trash fund | active/unpublished → deactivated | `POST /campaigns/{id}/deactivate` |
| Untrash fund | deactivated → unpublished | No direct endpoint (workaround: PUT update) |
| Delete fund | deactivated → (no delete) | Deactivate only, no deletion |

**Status Values (from API):**
- `draft` - Initial state after duplication
- `unpublished` - Not live but can be edited
- `active` - Published and live
- `deactivated` - Soft deleted, preserves data

**Reactivation Pattern:**
When campaign is deactivated and fund is untrashed:
1. Attempt `PUT /campaigns/{id}` with updated data
2. This MAY trigger state change back to unpublished
3. Then call `POST /campaigns/{id}/publish` to make active again
4. **UNCERTAIN** - needs testing with Classy sandbox API

## Complexity Assessment

| Operation | Complexity | Reason |
|-----------|------------|--------|
| Create via duplicate | Medium | Requires template setup, multi-step process |
| Update name/goal | Low | Standard PUT endpoint |
| Deactivate | Low | Single POST endpoint |
| Reactivate | Medium | No direct endpoint, requires workaround |
| Publish/unpublish | Low | Single POST endpoints |
| Poll metrics | Medium | Separate endpoint, requires parsing |
| Bulk migration | Medium | Must iterate 758 funds, handle failures |

**Highest Risk Operations:**
1. **Reactivation after deactivation** - No clear API path (needs verification)
2. **Bulk migration** - Large dataset, must be idempotent and resumable
3. **Template campaign dependency** - Plugin breaks if template deleted/changed

## WordPress Plugin Pattern

How campaign sync fits into existing plugin architecture.

**Existing Pattern (Designation Sync):**
```
save_post_funds hook
    ↓
build_designation_data()
    ↓
create_designation() OR update_designation()
    ↓
store designation_id in post meta
```

**New Pattern (Campaign Sync):**
```
save_post_funds hook
    ↓
build_campaign_data()
    ↓
Has campaign_id?
    NO  → duplicate_campaign() → publish_campaign() → store campaign_id
    YES → update_campaign()
```

**Parallel Sync:**
Campaigns and designations sync independently:
- Both triggered by same WordPress hooks
- Campaigns depend on designation_id existing
- If designation sync fails, campaign sync should wait/retry

**Unidirectional vs. Bidirectional:**
- **Outbound (WP → Classy):** Create, update, status changes
- **Inbound (Classy → WP):** Donation metrics only
- **Conflict resolution:** WordPress wins (existing pattern)

## Open Questions for Verification

Items flagged as LOW confidence needing validation with Classy API testing.

1. **Reactivate endpoint:** Does PUT /campaigns/{id} allow state change from deactivated to unpublished? Or is deactivation permanent?

2. **Overview endpoint path:** Is it `/campaigns/{id}/overview` or different structure? WebSearch results implied this but no official confirmation.

3. **Updatable fields after duplication:** Can `overview` field be updated via PUT? What about `started_at`?

4. **Template campaign detection:** How to verify template campaign ID is valid before attempting duplication?

5. **Duplication failure modes:** What errors occur if designation_id is invalid or missing?

6. **Rate limits:** What are API rate limits for duplication and update operations?

7. **Bulk operation best practices:** Should bulk migration use batch endpoints or throttle individual requests?

## Sources

**HIGH Confidence (Existing Codebase):**
- `/Users/chadmacbook/projects/fcg/includes/class-api-client.php` - API client implementation with campaign methods
- `/Users/chadmacbook/projects/fcg/includes/class-sync-handler.php` - Sync handler with campaign sync logic already started
- `/Users/chadmacbook/projects/fcg/.planning/PROJECT.md` - Project context and constraints

**MEDIUM Confidence (WebSearch + Official Docs):**
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html) - Official API reference (redirects to GoFundMe Pro)
- [Classy Support: Duplicate Campaign](https://support.classy.org/s/article/how-to-duplicate-a-campaign) - Duplication feature documentation
- [Factor 1 Studios: Classy API Guide](https://factor1studios.com/harnessing-power-classy-api/) - Campaign overview endpoint details
- [Medium: Classy API Donation Data](https://medium.com/factor1/harnessing-the-power-of-the-classy-api-to-drive-online-donations-df3e6de72e4d) - Metrics endpoint structure

**LOW Confidence (WebSearch Only - Needs Verification):**
- Campaign status workflow (publish/unpublish/deactivate/reactivate) - inferred from multiple sources but not tested
- Campaign overview endpoint exact path - implied but not confirmed
- Reactivation behavior - contradictory information, needs API testing
- Updatable fields after duplication - partial information only

## Recommendations for Roadmap

Based on this research, suggested phase structure:

**Phase 1: Core Campaign Sync (Table Stakes)**
- Duplicate campaign from template on fund publish
- Update campaign on fund save
- Deactivate campaign on fund trash/delete
- Store campaign ID and URL in post meta
- Add template campaign ID setting to admin

**Phase 2: Status Management & Polish**
- Publish/unpublish campaign based on WordPress status
- Handle untrash (reactivate workaround testing)
- Campaign URL display in admin UI
- Error handling and admin notices

**Phase 3: Inbound Metrics Sync**
- Poll campaign overview endpoint for donation totals
- Store metrics in post meta or transients
- Display progress in admin UI (meta box or column)

**Phase 4: Bulk Migration**
- WP-CLI command to create campaigns for existing 758 funds
- Progress tracking and resume capability
- Dry-run mode for safety

**Deeper Research Needed:**
- Phase 2: Reactivation workflow (no clear API path documented)
- Phase 3: Campaign overview endpoint exact structure
- Phase 4: Bulk operation rate limits and best practices
