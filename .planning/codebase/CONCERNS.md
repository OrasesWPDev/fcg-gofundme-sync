# Codebase Concerns

**Analysis Date:** 2026-01-22

## Tech Debt

**Limited error handling and recovery mechanisms:**
- Files: `includes/class-sync-handler.php`, `includes/class-sync-poller.php`
- Issue: Campaign creation failures are logged but not retried automatically. When campaign creation fails (e.g., due to 403 permission error), the post meta `_gofundme_campaign_id` is never set, but subsequent syncs don't attempt to retry
- Impact: Funds with failed campaign creation will remain unlinked, requiring manual `wp fcg-sync retry --force` intervention
- Fix approach: Implement automatic retry queue with exponential backoff for campaign operations (already exists for designations via `_gofundme_sync_attempts` and `_gofundme_sync_error`, but not applied to campaigns)

**Duplication check missing for campaigns:**
- Files: `includes/class-sync-handler.php` (methods `create_campaign_in_gfm` and `sync_campaign_to_gofundme`)
- Issue: When syncing a fund that doesn't have a campaign ID, the code always calls `create_campaign_in_gfm()`. If this function is called twice in quick succession, two campaigns could be created for the same fund
- Impact: Multiple duplicate campaigns in GoFundMe Pro for a single WordPress fund
- Fix approach: Add a "is_syncing_campaign" transient flag (similar to `fcg_gfm_syncing_inbound`) to prevent concurrent campaign creation; validate campaign doesn't already exist in GFM before creating

**OAuth2 token refresh timing is narrow:**
- Files: `includes/class-api-client.php` (line 142)
- Issue: Access tokens are cached with a TTL of `expires_in - 300` seconds (5 minute safety margin). If API response doesn't include `expires_in`, defaults to 3600 seconds. If GoFundMe Pro token expires faster than expected, requests will fail
- Impact: Intermittent "401 Unauthorized" errors during sync operations, particularly if token expiration time varies
- Fix approach: Monitor failed API requests for 401 errors and force token refresh; consider shorter cache TTL (2 minutes instead of 5)

**No automated tests:**
- Files: All files
- Issue: No PHPUnit tests, no integration tests with mock API, no test fixtures for common scenarios
- Impact: Regressions introduced silently; campaign sync conflicts untested; error retry logic untested
- Fix approach: Add PHPUnit test suite with mock API client; add tests for sync conflict resolution; add tests for campaign creation/update flow

## Known Issues

**Campaign creation may fail with 403 Forbidden:**
- Symptoms: Campaign creation fails silently, API returns 403, no campaign ID is stored
- Files: `includes/class-sync-handler.php` (line 393-407)
- Trigger: Publish a fund when API credentials lack campaign creation permissions
- Workaround: Check GoFundMe Pro API credentials have `campaigns.create` permission; use `wp fcg-sync retry --force` after permissions are fixed

**Campaign deactivation endpoint vs. inactive status:**
- Symptoms: When a fund is unpublished, campaign is deactivated via `POST /campaigns/{id}/deactivate` but designation is set to `is_active=false`. No `is_active` field for campaigns
- Files: `includes/class-sync-handler.php` (lines 164-171, 237-244, 289-291)
- Trigger: Unpublish a fund or trash it
- Context: GoFundMe Pro campaigns don't have an `is_active` field; they must be deactivated via a dedicated endpoint. This asymmetry with designations could cause confusion during conflict resolution
- Workaround: Current approach works (deactivate campaigns, set designations inactive), but is inconsistent

**Conflict resolution may overwrite concurrent GFM changes:**
- Symptoms: If WordPress and GoFundMe Pro are edited simultaneously, WordPress version wins and overwrites GFM changes
- Files: `includes/class-sync-poller.php` (lines 883-902, 991-1026)
- Trigger: Edit fund in WordPress while also editing designation in GoFundMe Pro within 15-minute polling interval
- Context: Sync strategy is "WordPress wins on conflict" based on `post_modified_gmt` timestamp comparison. No three-way merge or user notification of lost changes
- Workaround: Establish workflow where either WordPress or GoFundMe Pro is the authoritative source, not both

**Campaign sync depends on designation existing:**
- Symptoms: If designation creation fails, campaign may still be created, creating orphaned campaigns
- Files: `includes/class-sync-handler.php` (lines 118-135)
- Trigger: Publish fund; designation creation fails due to API error; campaign creation succeeds
- Context: No explicit dependency or transaction; both operations run independently
- Impact: Inconsistent state where WordPress post has campaign ID but no designation ID
- Fix approach: Make campaign creation conditional on successful designation creation, or add validation to check both exist

## Security Considerations

**OAuth2 tokens stored in WordPress transients:**
- Risk: Transients are stored in WordPress options table (database) with plaintext token value
- Files: `includes/class-api-client.php` (lines 111-114, 142)
- Current mitigation:
  - Transient cached with 5-minute safety margin (expires before API token)
  - Tokens never logged (checked via WP_DEBUG)
  - Database access restricted to WordPress admin users
- Recommendations:
  - Consider storing transients with shorter TTL (2 minutes vs. 5)
  - Add database-level encryption if available (e.g., WP Engine Secrets)
  - Audit transient cleanup on plugin deactivation (already done in line 144)

**Credentials loaded from environment variables or wp-config.php:**
- Risk: If `GOFUNDME_CLIENT_SECRET` is exposed, attacker can authenticate to Classy API
- Files: `fcg-gofundme-sync.php` (lines 59-68), `includes/class-api-client.php` (lines 71-84)
- Current mitigation:
  - Environment variables take priority (WP Engine User Portal is encrypted)
  - wp-config.php constants are server-side only, not included in version control
  - Credentials never logged in code
  - No credentials in error messages
- Recommendations:
  - Ensure WP Engine environment variables are set (not wp-config.php constants)
  - Monitor for credential exposure in error logs
  - Rotate credentials if plugin code is committed to public repo

**ACF field values loaded without sanitization:**
- Risk: If ACF field contains malicious data, it could be sent to GoFundMe Pro API
- Files: `includes/class-sync-handler.php` (lines 324-331, 371-384)
- Current mitigation:
  - Data is truncated to max field lengths before sending (line 308, 344)
  - `wp_strip_all_tags()` used on post content (lines 318-319)
  - Field values validated as numeric for goal fields (lines 328, 373)
- Recommendations:
  - Consider additional validation: goals must be positive numbers; descriptions sanitized for special characters

**Admin UI AJAX actions:**
- Risk: `ajax_sync_now()` triggers post updates via `wp_update_post()` which could be exploited
- Files: `includes/class-admin-ui.php` (lines 349-378)
- Current mitigation:
  - Nonce verification (line 350)
  - Capability check `edit_posts` (line 352)
  - Post type validation (line 361)
- Recommendations: Current implementation is secure; nonce is properly validated

## Performance Bottlenecks

**15-minute polling interval for large fund counts:**
- Problem: Every 15 minutes, plugin fetches ALL designations from GoFundMe Pro and compares against ALL published funds
- Files: `includes/class-sync-poller.php` (lines 100-166)
- Current behavior:
  - `get_all_designations()` paginates with `per_page=100` (lines 265-290)
  - If organization has 1000+ designations, this makes 10+ API requests
  - For each designation, WordPress post is queried (`find_post_for_designation()`, line 121)
  - Hash comparison done for each designation (line 139)
- Cause: No incremental sync; no webhook support mentioned
- Improvement path:
  - Add support for GoFundMe Pro webhooks to push changes (vs. polling)
  - Implement delta sync: track `updated_at` timestamp and only fetch designations modified since last poll
  - Cache designation list locally with TTL to avoid N+1 post queries

**WP-Cron reliability:**
- Problem: Polling depends on WordPress WP-Cron, which only fires when site receives visitor traffic
- Files: `fcg-gofundme-sync.php` (lines 127-129)
- Current behavior: 15-minute schedule registered but not guaranteed to execute
- Impact: If site has low traffic, polling may lag significantly (could be hours between syncs)
- Improvement path: Recommend setting up real cron job (`DISABLE_WP_CRON=true` in wp-config.php and real cron via SSH)

**Conflict log grows unbounded:**
- Problem: Conflict log stored in WordPress options as array, no size limit
- Files: `includes/class-sync-poller.php` (lines 979-982)
- Current behavior: Keeps last 100 conflicts (line 980), but all previous conflicts still in memory
- Impact: On sites with frequent conflicts, option value could grow large
- Fix approach: Already implemented (slice to last 100), but could be more aggressive (e.g., last 50, or purge after 7 days)

## Fragile Areas

**Sync conflict resolution depends on timestamp precision:**
- Files: `includes/class-sync-poller.php` (lines 883-902)
- Why fragile: Conflict detection compares `post_modified_gmt` with `_gofundme_last_sync`. If WordPress clocks are out of sync with server, false positives occur
- Safe modification: Always compare with UTC timestamps; use server time for both; add logging to track timestamp comparisons
- Test coverage gaps: No tests for conflict detection logic; no tests for timezone handling

**Campaign sync intertwined with designation sync:**
- Files: `includes/class-sync-handler.php` (lines 118-135)
- Why fragile: Both designation and campaign synced in same `on_save_fund()` call. If campaign creation fails partway through, post meta is inconsistent
- Safe modification: Add try-catch blocks; validate all required meta is present before considering sync "complete"
- Test coverage gaps: No tests for campaign sync failure scenarios; no tests for partial sync recovery

**ACF field group integration incomplete:**
- Files: `includes/class-sync-handler.php` (lines 44, 325-330, 487-489, 533-535)
- Why fragile: Code references ACF field group `gofundme_settings` but doesn't verify it exists. If ACF plugin not active, functions called on empty data
- Impact: Silent failures; designation data not synced if ACF not installed
- Safe modification: Check `function_exists('get_field')` before using (already done, lines 324, 371, 532) but doesn't validate field group exists
- Test coverage gaps: No tests for ACF/non-ACF scenarios

**External reference ID matching logic:**
- Files: `includes/class-sync-poller.php` (lines 839-861)
- Why fragile: Two-tier lookup (external_reference_id first, then meta_key search) assumes post IDs are stable. If posts are migrated or duplicated, references become stale
- Safe modification: Add logging to show which matching method succeeded; add validation that matched post has correct post_type
- Test coverage gaps: No tests for orphaned designation scenarios

## Scaling Limits

**API rate limiting not handled:**
- Current capacity: No explicit rate limit handling
- Limit: GoFundMe Pro API likely has per-minute request limits (typical: 600/min)
- Files: `includes/class-api-client.php` (lines 155-216)
- Current behavior: No throttling, no backoff, no rate limit header inspection
- Cause: No tracking of API request count
- Scaling path: Implement request queue with throttling; inspect `X-RateLimit-*` headers in responses; implement exponential backoff on 429 (Too Many Requests)

**Database query N+1 problem:**
- Current capacity: Works well for <1000 funds
- Limit: Each poll iteration calls `find_post_for_designation()` which makes a separate query per designation
- Files: `includes/class-sync-poller.php` (line 121)
- Current behavior: If 1000 designations, makes ~1000 post meta queries
- Cause: No bulk lookup; no caching
- Scaling path: Batch load all WordPress posts with designation IDs into memory; use local lookup instead of repeated queries

**Credential updates require plugin deactivation:**
- Current capacity: Works for infrequent credential rotation
- Limit: OAuth2 token cached in transient; if credentials change, old token still cached for up to 5 minutes
- Files: `includes/class-api-client.php` (lines 142)
- Current behavior: Need to manually delete `gofundme_access_token` transient or wait for expiry
- Scaling path: Add admin page to force credential refresh and clear token cache; consider shorter token TTL

## Dependencies at Risk

**WordPress transient API reliability:**
- Risk: Transients not guaranteed to persist (may be garbage collected)
- Impact: OAuth2 token may be lost, requiring re-auth on every request
- Current mitigation: WordPress typically persists transients if database is stable
- Migration plan: If reliability issues arise, switch to encrypted options or file-based cache

**GoFundMe Pro API stability:**
- Risk: API rate limits, timeouts, or endpoint changes could break sync
- Impact: Designations/campaigns fail to sync; designations become stale
- Current mitigation: Error logging, retry logic (for designations), manual intervention via WP-CLI
- Migration plan: Subscribe to Classy API status page; implement circuit breaker pattern; add fallback to cached data

**PHP 7.4 minimum (strict requirements):**
- Risk: If hosting upgraded to PHP 8.x, code with weak type hints may fail
- Impact: Plugin breaks silently or with fatal errors
- Current state: Code uses typed properties (PHP 7.4+) but mixed type handling
- Migration plan: Test on PHP 8.0+ before deployment; use strict types and proper type declarations

## Missing Critical Features

**No webhook support for real-time sync:**
- Problem: Polling every 15 minutes means changes in GoFundMe Pro have 15-minute latency
- Impact: Designations updated in GoFundMe Pro won't appear in WordPress for up to 15 minutes
- Blocks: Bi-directional real-time sync; user-facing "sync now" button won't reflect GFM changes until next poll
- Priority: Medium (current polling works, but not ideal for high-change environments)

**No bulk export/import for initial data migration:**
- Problem: Large organizations need to sync thousands of funds at once
- Impact: Must use WP-CLI `fcg-sync push` command, no UI for bulk operations
- Blocks: Streamlined onboarding for new clients with large fund counts
- Priority: Medium (WP-CLI exists, but UI-driven approach would be better)

**No rate limiting / request queuing:**
- Problem: Rapid edits to many posts could overwhelm GoFundMe Pro API
- Impact: Sync operations fail with 429 (Too Many Requests) or timeout
- Blocks: Safe operation in high-traffic environments
- Priority: High (would be triggered by bulk import or rapid publishing)

**No designation/campaign linkage UI:**
- Problem: Users can't easily see which WordPress funds link to which GoFundMe Pro resources
- Impact: Users must use WP-CLI `fcg-sync status` to debug mismatches
- Blocks: Admin-friendly conflict resolution
- Priority: Low (admin column shows status, meta box shows designation ID)

## Test Coverage Gaps

**Campaign creation failure scenarios:**
- What's not tested: Campaign creation with 403 Forbidden, network timeout, API validation errors
- Files: `includes/class-sync-handler.php` (lines 392-408)
- Risk: Failures silently logged without retrying; no way to know why campaign wasn't created
- Priority: High (actively used in v2.0+)

**Sync conflict resolution:**
- What's not tested: Timestamp comparison edge cases, UTC vs. local time, rapid edits
- Files: `includes/class-sync-poller.php` (lines 883-902)
- Risk: Conflicts resolved incorrectly; user changes overwritten without notice
- Priority: High (affects data integrity)

**Campaign sync duplication:**
- What's not tested: Concurrent campaign creation, rapid post saves, transient race conditions
- Files: `includes/class-sync-handler.php` (lines 392-408, 435-446)
- Risk: Duplicate campaigns created in GoFundMe Pro
- Priority: Medium (duplication endpoint exists but untested)

**API credential handling:**
- What's not tested: Missing credentials, invalid tokens, expired transients
- Files: `includes/class-api-client.php` (lines 59-84)
- Risk: Silent failures or error messages that don't help users debug
- Priority: Medium (currently relies on admin notices)

**WP-CLI commands:**
- What's not tested: WP-CLI `fcg-sync` commands with various flags and scenarios
- Files: `includes/class-sync-poller.php` (lines 184-685)
- Risk: Commands fail unexpectedly; dry-run doesn't accurately reflect what would happen
- Priority: Medium (used for bulk operations)

**Orphaned designation handling:**
- What's not tested: Designations in GoFundMe Pro with no matching WordPress post; cleanup logic
- Files: `includes/class-sync-poller.php` (lines 952-958)
- Risk: Orphaned designations accumulate; no automated cleanup
- Priority: Low (logged but not actionable)

---

*Concerns audit: 2026-01-22*
