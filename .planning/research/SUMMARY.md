# Research Summary: Classy Campaign Sync

**Project:** FCG GoFundMe Pro Sync - Campaign Integration
**Domain:** WordPress Plugin with Classy (GoFundMe Pro) API Integration
**Researched:** 2026-01-22
**Confidence:** MEDIUM-HIGH

## Executive Summary

Adding campaign sync to the existing FCG GoFundMe Pro plugin requires extending the proven designation sync pattern with Classy's duplication-based campaign creation workflow. Unlike designations (which support direct POST creation), campaigns must be duplicated from a pre-configured template campaign and customized via field overrides. The existing plugin architecture—featuring OAuth2 authentication, WordPress HTTP client, and parallel sync handlers—supports this extension with minimal structural changes. The primary implementation path involves three integration points: API client methods for campaign duplication/publishing, sync handler hooks for outbound operations, and poller extensions for inbound donation metrics.

The most critical risk is bulk migration of 758 existing funds, which will timeout without proper batching and rate limit handling. The API lacks public rate limit documentation, requiring conservative throttling (10 requests/minute recommended). WordPress WP-Cron's unreliability on cached sites creates a secondary risk for inbound polling—production deployment must use server-level cron instead. Campaign status lifecycle complexity (active/unpublished/deactivated) requires careful mapping to WordPress post statuses to avoid counting campaigns against organizational limits incorrectly.

Research confidence is MEDIUM-HIGH overall: the duplication workflow is well-documented, existing codebase patterns are proven, but specific details (updatable fields post-duplication, exact rate limits, reactivation behavior) require validation with Classy contact and sandbox API testing before bulk migration.

## Key Findings

### Recommended Stack

The existing plugin stack is production-ready and requires no changes for campaign sync. Campaign operations use the same OAuth2 authentication, WordPress HTTP client (wp_remote_request), and transient-based token caching already proven with designation sync.

**Core technologies:**
- **Campaign Duplication API**: POST /campaigns/{id}/duplicate — Only public method for campaign creation (POST /campaigns returns 403)
- **OAuth2 client_credentials flow**: Already implemented with 5-minute early token expiration buffer — No changes needed
- **WordPress post meta storage**: Existing pattern extends cleanly to campaign IDs, URLs, and donation totals
- **WP-Cron polling (15-min interval)**: Works for inbound sync BUT must be replaced with server cron on production (WP-Cron fails on cached sites)

**Critical version requirements:**
- Template campaign must be Studio type (not legacy Classy Mode) — Legacy campaigns cannot be published via API
- Organization must have available campaign slots (active campaigns count toward limits)

### Expected Features

Campaign sync splits into four operational areas: outbound push (WordPress to Classy), inbound pull (Classy to WordPress), bulk migration (one-time 758 funds), and admin UI visibility.

**Must have (table stakes):**
- Create campaign via duplication on fund publish — Only public API path available
- Update campaign name/goal on fund save — Core sync functionality
- Deactivate campaign on trash/delete — Preserves donation history (never delete campaigns)
- Store campaign ID and URL in post meta — Links WordPress to Classy entities
- Template campaign ID setting — Required before any sync can work

**Should have (competitive):**
- Publish/unpublish campaign based on WordPress status — Status parity between systems
- Poll donation totals every 15 minutes — Shows fundraising progress in WordPress admin
- Bulk migration WP-CLI tool — Creates campaigns for 758 existing funds
- Campaign URL display in admin meta box — Quick access to live campaign

**Defer (v2+):**
- Real-time sync (no webhooks available from Classy) — 15-minute polling is acceptable lag
- Individual donation records (only totals needed) — Out of scope for this project
- Multiple campaigns per fund — 1:1 relationship by design

**Not supported by API:**
- Direct campaign creation (POST /campaigns returns 403) — Must use duplication workflow
- Campaign deletion (destroys donation history) — Use deactivate instead
- Direct reactivation endpoint — Must use update workaround after deactivation

### Architecture Approach

Campaign sync integrates as a parallel operation alongside designation sync within the existing three-layer architecture. Both entities sync independently during the same WordPress lifecycle events (save_post_funds, wp_trash_post, etc.), with campaign operations always following designation operations due to the designation_id dependency.

**Major components:**
1. **FCG_GFM_API_Client** (HTTP layer) — Add duplicate_campaign() and publish_campaign() methods; existing update/get/deactivate methods already present
2. **FCG_GFM_Sync_Handler** (Outbound sync) — Extend on_save_fund() to call sync_campaign_to_gofundme() after designation sync; modify create_campaign_in_gfm() to use duplication instead of POST
3. **FCG_GFM_Sync_Poller** (Inbound sync) — Add campaign polling loop after designation polling; fetch donation totals and update post meta (not post content)
4. **FCG_GFM_Admin_UI** (Admin display) — Add campaign URL, donation total, and sync status to meta box; provide manual "Create Campaign" button

**Key architectural patterns:**
- **Parallel operations**: Designation and campaign sync independently with separate error handling
- **Hash-based change detection**: MD5 comparison prevents unnecessary updates (extend existing pattern)
- **Recursion prevention**: Transient flag prevents inbound sync from triggering outbound sync (reuse fcg_gfm_syncing_inbound)
- **WordPress wins conflict resolution**: When both sides change, WordPress version takes precedence (extend existing should_apply_gfm_changes())

### Critical Pitfalls

1. **POST /campaigns returns 403 Forbidden** — Direct campaign creation is not a public endpoint despite API documentation showing it exists. Must use duplicate_campaign() workflow from day one. Impact: Blocks Phase C2 if not addressed.

2. **Bulk migration timeout without batching (758 funds)** — Processing 758 funds at ~3 seconds each = 38 minutes, exceeding PHP max_execution_time (typically 30-300s). Requires WP-CLI batching (50 funds per batch), resume-able logic (query for funds without campaign_id), and dry-run mode. Impact: Phase C4 will fail without proper batching.

3. **WordPress transient race conditions on OAuth token** — Multiple concurrent requests can trigger simultaneous token refresh, causing 401 errors and wasted API calls. Requires MySQL GET_LOCK() before token refresh and double-check pattern after lock acquisition. Impact: High-concurrency operations (bulk migration, concurrent polling) will hit intermittent failures.

4. **WP-Cron unreliability on cached sites** — WP-Cron only fires on page loads, not on timers. High-traffic cached sites (WP Engine with page caching) may never trigger WP-Cron, breaking inbound polling entirely. Requires disabling WP-Cron (define('DISABLE_WP_CRON', true)) and using server cron (*/15 * * * * wp cron event run --due-now). Impact: Phase C5 inbound sync will not work reliably without server cron.

5. **Campaign status lifecycle complexity** — Classy campaigns have three states (active, unpublished, deactivated) with specific rules: reactivate returns to unpublished (not active), unpublished campaigns still count toward org limits. Incorrect mapping causes limit issues and restore failures. Requires two-step restore: reactivate → publish. Impact: Phase C3 status management will have confusing behavior without proper state machine.

## Implications for Roadmap

Based on research, campaign sync naturally divides into five phases with clear dependencies and risk profiles.

### Phase C2: Campaign Push Sync (Outbound)
**Rationale:** Must come first—establishes WordPress-to-Classy data flow before any other operations. Template duplication is the only public creation method, making this the foundational phase.

**Delivers:** Published WordPress fund automatically creates and publishes campaign in Classy; updated fund syncs name/goal changes; campaign ID and URL stored in post meta.

**Addresses:** Table stakes features (create via duplication, update name/goal, store IDs), avoids POST /campaigns 403 pitfall by using duplication workflow from start.

**Uses:** Existing OAuth2, wp_remote_request, parallel sync pattern; adds duplicate_campaign() and publish_campaign() API methods.

**Avoids:** Pitfall #1 (must use duplication, not POST), Pitfall #5 (implements correct status mapping from start).

### Phase C3: Campaign Status Management
**Rationale:** Extends push sync with status transitions (publish/unpublish/deactivate/reactivate). Separated from Phase C2 because status lifecycle requires testing to validate state machine correctness.

**Delivers:** WordPress draft/trash/restore actions properly map to Classy campaign states; two-step restore (reactivate → publish) implemented.

**Addresses:** Nice-to-have features (publish/unpublish based on WP status, reactivate on untrash).

**Avoids:** Pitfall #5 (campaign status lifecycle) by implementing correct state transitions and testing in sandbox.

**Research flag:** MAYBE needs deeper research—can be validated quickly with sandbox API testing of state transitions.

### Phase C4: Bulk Migration Tool
**Rationale:** Cannot happen until Phases C2-C3 complete and validate sync patterns. High-risk operation requiring special infrastructure (batching, throttling, resume logic).

**Delivers:** WP-CLI command creates campaigns for 758 existing funds in batches with progress tracking, error handling, and resume capability.

**Addresses:** Nice-to-have feature (bulk migration tool).

**Avoids:** Pitfall #2 (timeout) with 50-fund batching, Pitfall #3 (token race) with pre-warmed token and locking, unknown rate limits with conservative 10 requests/minute throttling.

**Research flag:** YES needs deeper research—must load test with 100 funds to measure timing, test rate limit behavior by gradually increasing frequency until 429 response.

### Phase C5: Inbound Donation Sync (Pull)
**Rationale:** Independent from push sync—can happen in parallel or after. Separated because it introduces new complexity (polling, conflict detection, staleness) and has different failure modes.

**Delivers:** Every 15 minutes, poll Classy for campaign donation totals and update WordPress post meta; display in admin UI with "last synced" timestamp.

**Addresses:** Nice-to-have features (donation totals, donor count, percent-to-goal).

**Avoids:** Pitfall #4 (WP-Cron unreliability) by documenting server cron requirement, idempotency issues by extending existing hash comparison pattern.

**Uses:** Existing poller architecture (find_post_for_*, has_*_changed(), calculate_*_hash()), extends with campaign-specific methods.

**Research flag:** NO deeper research needed—standard polling patterns apply, extend existing designation polling structure.

### Phase C6: Admin UI Enhancement
**Rationale:** Last phase—purely cosmetic, depends on all sync operations working. Can be deferred if timeline is tight.

**Delivers:** Meta box shows campaign URL (clickable), donation total with progress bar, last sync timestamp, manual "Sync Now" button; list table column shows both designation and campaign sync status.

**Addresses:** Nice-to-have features (campaign URL display, progress visualization).

**Avoids:** No specific pitfalls, but sets client expectations about 15-minute polling lag.

**Research flag:** NO deeper research needed—standard WordPress admin UI patterns.

### Phase Ordering Rationale

- **C2 before C3**: Must establish basic push sync before adding status complexity
- **C2-C3 before C4**: Bulk migration requires validated sync patterns to avoid creating 758 broken campaigns
- **C5 independent of C2-C4**: Inbound polling doesn't depend on outbound push working (can develop in parallel)
- **C6 last**: Admin UI is pure presentation layer, requires all operations working to display meaningful data
- **Critical path**: C2 → C3 → C4 (cannot bulk migrate until push sync proven)
- **Parallel path**: C5 can develop alongside C2-C3

### Research Flags

**Phases likely needing deeper research during planning:**
- **Phase C2 (Campaign Push Sync)**: Confirm with Classy contact which campaign fields can be updated post-duplication; test duplication with template to verify inherited fields vs overridable fields
- **Phase C4 (Bulk Migration)**: Load test 100 funds to measure timing and identify bottlenecks; test API rate limits by gradually increasing request frequency until 429 response; document actual limits for throttling configuration

**Phases with standard patterns (skip research-phase):**
- **Phase C3 (Campaign Status Management)**: Status workflow documented in Classy docs, just needs sandbox testing to validate state machine
- **Phase C5 (Inbound Donation Sync)**: Standard polling pattern, extends existing designation poller architecture
- **Phase C6 (Admin UI Enhancement)**: WordPress admin UI best practices apply, no domain-specific research needed

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Existing OAuth2 and HTTP client proven with designations; duplication workflow well-documented |
| Features | MEDIUM-HIGH | Table stakes clear, but some updatable fields unknown without Classy contact confirmation |
| Architecture | HIGH | Extends existing three-layer pattern; parallel operation approach validated with designations |
| Pitfalls | MEDIUM | Critical pitfalls verified (WP-Cron, bulk timeout, 403 on POST); rate limits unknown, require testing |

**Overall confidence:** MEDIUM-HIGH

Research provides strong foundation for roadmap creation. Core patterns are proven, major risks identified with mitigations. Two gaps require validation before bulk migration: exact API rate limits and complete list of updatable campaign fields.

### Gaps to Address

**Gap 1: API rate limits unknown**
- **Issue**: Classy API documentation does not specify rate limits
- **Impact**: Bulk migration of 758 funds could hit limits unexpectedly
- **Mitigation**: Start with conservative 10 requests/minute throttling; gradually increase during Phase C4 testing until 429 response observed; document actual limits
- **Resolution**: Phase C4 planning includes load testing with increasing request frequency

**Gap 2: Complete list of updatable campaign fields**
- **Issue**: Research confirms name and goal are updatable, but overview/description/started_at status unclear
- **Impact**: May need workaround if certain fields cannot be updated (deactivate + recreate)
- **Mitigation**: Test in sandbox with template campaign; contact Classy support for official field list
- **Resolution**: Phase C2 planning includes field update validation before bulk operations

**Gap 3: Reactivate endpoint behavior**
- **Issue**: No direct reactivate endpoint documented; unclear if PUT update triggers state change from deactivated
- **Impact**: Untrash operation may require manual intervention or workaround
- **Mitigation**: Test deactivate → update → publish workflow in sandbox; document if reactivation requires two-step process
- **Resolution**: Phase C3 planning includes state transition testing with sandbox API

**Gap 4: Template campaign type detection**
- **Issue**: Research shows Studio campaigns work via API, legacy Classy Mode campaigns cannot be published via API; no field documented to detect type
- **Impact**: Wrong template type causes silent failures during publish
- **Mitigation**: Add validation in admin UI when setting template ID; test with both types if possible; document requirement
- **Resolution**: Phase C2 planning includes template validation logic

## Sources

### Primary (HIGH confidence)
- **Existing codebase** (`/Users/chadmacbook/projects/fcg/includes/`) — API client patterns, sync handler hooks, poller structure all analyzed
- **Project context** (`.planning/PROJECT.md`) — Confirms POST /campaigns returns 403, must use duplication
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html) — Official endpoint reference
- [GoFundMe Pro API Documentation](https://docs.classy.org/) — OAuth2 flow, campaign duplication workflow
- [Campaign Status Definitions - Classy support center](https://support.classy.org/s/article/campaign-status-definitions) — Status lifecycle (active/unpublished/deactivated)

### Secondary (MEDIUM confidence)
- [Automate Campaign Creation via API | Convertr](https://www.convertr.io/resources/product/campaign-duplication-api) — Duplication endpoint structure and overrides parameter
- [Factor 1 Studios: Classy API Guide](https://factor1studios.com/harnessing-power-classy-api/) — Campaign overview endpoint for donation totals
- [Release notes 2024 – GoFundMe Pro Help Center](https://prosupport.gofundme.com/hc/en-us/articles/37288721467931-Release-notes-2024) — Email settings inheritance fix, Studio campaign type introduction
- [How to Fix WordPress max_execution_time Fatal Error - Kinsta](https://kinsta.com/blog/wordpress-max-execution-time/) — PHP timeout mitigation strategies
- [Event Scheduling and wp-cron - WP Engine Support](https://wpengine.com/support/wp-cron-wordpress-scheduling/) — WP-Cron unreliability on cached hosts
- [Finding and solving a race condition in WordPress - Altis](https://www.altis-dxp.com/finding-and-solving-a-race-condition-in-wordpress/) — Transient race condition patterns and MySQL locking solution

### Tertiary (LOW confidence - needs validation)
- API rate limits: No official documentation found; extrapolated from industry standards (100 requests/minute typical for fundraising APIs)
- Campaign overview endpoint path: Implied to be `/campaigns/{id}/overview` from third-party sources, not confirmed in official docs
- Reactivation behavior: Contradictory information about whether deactivated campaigns can be reactivated via PUT update vs requiring manual intervention

---
*Research completed: 2026-01-22*
*Ready for roadmap: yes*
