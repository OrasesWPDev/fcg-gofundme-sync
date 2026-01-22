# Testing Patterns

**Analysis Date:** 2026-01-22

## Current State

**Automated Testing:** Not implemented
- No PHPUnit tests
- No test frameworks configured
- No CI pipeline tests
- No local testing infrastructure

**Manual Testing Approach:**
- All testing performed on WP Engine Staging environment
- Sandbox GoFundMe Pro API credentials used
- Browser-based and WP-CLI command testing
- Deployment: SSH to staging, WordPress admin UI verification, WP-CLI command execution

**Code Quality Checks:** None automated
- No linters configured
- No static analysis
- No type checking

## Test Strategy

**Deployment Pipeline:**
1. Develop code locally on `main` branch
2. Deploy plugin to WP Engine Staging environment via SSH
3. Test against Sandbox API credentials in WP Engine environment variables
4. Verify changes in Classy dashboard (GoFundMe Pro admin)
5. Wait for user approval before pushing to production

**Manual Test Scenarios:**

**Outbound Sync (WordPress → GoFundMe Pro):**
- Publish new fund → Creates designation in Classy
- Update published fund → Updates designation
- Change fund to draft → Sets `is_active = false` in Classy
- Trash fund → Deactivates designation
- Restore fund from trash → Reactivates designation
- Delete fund → Deletes designation (campaign deactivated)
- Verify designation fields: name, description, is_active, external_reference_id

**Inbound Sync (GoFundMe Pro → WordPress):**
- Poll retrieves all designations via `wp fcg-sync pull`
- Changes detected via hash comparison
- Conflict detection: WP modified after last sync timestamp
- Conflict resolution: WordPress version wins (pushed to GFM)
- Verify post meta updated: title, excerpt, status

**Campaign Sync (parallel to designations):**
- Create fund → Creates campaign in Classy
- Update fund → Updates campaign
- Change status → Updates campaign status
- Verify campaign fields: name, type, goal, started_at, overview, external_reference_id

**Error Handling:**
- API authentication failures → Admin notice
- Missing credentials → Admin notice
- Network errors → Logged, retried with exponential backoff
- HTTP errors → Error recorded, retryable
- Max retries exceeded (3) → Flagged in admin UI and CLI

**Post Meta Tracking:**
- Check `_gofundme_designation_id` set after create
- Check `_gofundme_last_sync` timestamp updated
- Check `_gofundme_sync_source` set (`'wordpress'` or `'gofundme'`)
- Check `_gofundme_sync_error` present on failures
- Check `_gofundme_sync_attempts` incremented on retries

## WP-CLI Commands

**Testing infrastructure via command-line:**

```bash
# Pull designations from GoFundMe Pro (inbound)
wp fcg-sync pull
wp fcg-sync pull --dry-run              # Preview what would sync

# Push funds to GoFundMe Pro (outbound)
wp fcg-sync push
wp fcg-sync push --dry-run              # Preview without changes
wp fcg-sync push --update               # Also update existing designations
wp fcg-sync push --limit=100            # Process only 100 funds
wp fcg-sync push --post-id=13707        # Sync only specific post

# Show sync status for all funds
wp fcg-sync status
# Output: Table with ID, Title, Post Status, Designation ID, Sync Status, Last Sync, Source

# Show recent sync conflicts
wp fcg-sync conflicts
wp fcg-sync conflicts --limit=20        # Show last 20 (default 10)

# Retry failed syncs
wp fcg-sync retry                        # Retry all eligible
wp fcg-sync retry --force               # Retry even if max attempts exceeded
wp fcg-sync retry --clear               # Clear all error flags without retrying
```

**Output Examples:**

Status command output:
```
+---------+------------------+-----------+----------------------+--------------+-----------+-----------+
| ID      | Title            | Post Status| Designation          | Sync Status  | Last Sync | Source    |
+---------+------------------+-----------+----------------------+--------------+-----------+-----------+
| 13707   | Emergency Fund   | publish   | des_abc123def456      | Synced       | 2026-01-22| wordpress |
| 13708   | Medical Relief   | draft     | -                     | Not Linked   | never     | -         |
| 13709   | Disaster Aid     | publish   | des_xyz789abc123      | Pending      | 2026-01-20| gofundme  |
+---------+------------------+-----------+----------------------+--------------+-----------+-----------+
```

Conflicts command output:
```
+---------------------+---------+------------------+------------------+-----------------------------+
| Timestamp           | Post ID | WP Title         | GFM Title        | Reason                      |
+---------------------+---------+------------------+------------------+-----------------------------+
| 2026-01-22 14:32:00 | 13709   | Disaster Aid 2.0 | Disaster Aid     | WP modified after last sync |
+---------------------+---------+------------------+------------------+-----------------------------+
```

## Test File Organization

**Location:** Not applicable (no automated test files)

**Future Test Structure (if added):**
- Unit tests: `tests/unit/`
- Integration tests: `tests/integration/`
- Fixtures: `tests/fixtures/`

## Test Structure

**Current Verification Methods:**

**Admin UI Checks:**
- Sync Status column visible in funds list
- Color-coded status badges (green=Synced, yellow=Pending, red=Error, gray=Not Linked)
- Sync meta box on fund edit screen showing:
  - Designation ID (linked to Classy admin)
  - Last Sync timestamp
  - Last Sync source
  - Error message (if any)
  - "Sync Now" button for manual trigger
- Settings page showing last poll timestamp
- Conflict log showing recent sync conflicts

**WP-CLI Verification:**
- Commands execute without errors
- Dry-run mode shows what would happen
- Stats output indicates results (processed, created, updated, skipped, errors)
- Progress bars for long operations
- Proper exit codes and messages

## Error Testing

**Manual Error Scenarios:**

**API Authentication Failures:**
1. Set invalid `GOFUNDME_CLIENT_ID` in WP Engine env vars
2. Expected: Admin notice "API credentials not configured"
3. Verify: `error_log` shows "[FCG GoFundMe Sync] Token request failed: Unauthorized"

**Missing Credentials:**
1. Remove `GOFUNDME_ORG_ID` from WP Engine env vars
2. Expected: Admin notice "Organization ID not configured"
3. Verify: Plugin doesn't attempt sync

**Network Timeout:**
1. Simulate via API blocking (if possible in staging)
2. Expected: Sync marked as error
3. Verify: `_gofundme_sync_error` meta set
4. Verify: Retry scheduled with exponential backoff

**Conflict Scenario:**
1. Create fund in WordPress, publish
2. Sync creates designation in GoFundMe Pro
3. Manually edit designation in Classy admin (change name)
4. Run `wp fcg-sync pull`
5. Expected: Conflict detected, WP version wins
6. Verify: Conflict logged to `fcg_gfm_conflict_log`
7. Verify: WP version pushed back to GFM

**Retry Mechanism:**
1. Create fund with API temporarily failing
2. Verify: Attempt 1 fails, recorded in `_gofundme_sync_attempts`
3. Wait delay: 5 minutes (first retry)
4. Verify: Attempt 2 happens at 5 min mark
5. On failure: Attempt 3 at 15 min mark
6. On failure: Attempt 4 at 45 min mark
7. On failure: Marked as max retries exceeded
8. Run: `wp fcg-sync retry --force`
9. Verify: Retry succeeds and meta cleared

## Mocking

**Not applicable:** No automated tests implemented

**Future Mocking Strategy (if tests added):**
- Mock `wp_remote_post()` for API token requests
- Mock `wp_remote_request()` for API data endpoints
- Mock WordPress functions: `get_post()`, `update_post_meta()`, `add_action()`
- Mock WP-CLI commands for testing CLI output

## Fixtures and Factories

**Not applicable:** No automated tests implemented

**Manual Test Data:**
- Test funds created in WordPress (various statuses: draft, publish, trash)
- Test designations created in GoFundMe Pro Sandbox
- Test data persisted in staging environment for ongoing testing
- Can be cleared and recreated for fresh test runs

## Coverage

**Requirements:** Not enforced

**Current State:** 0% automated coverage

**Estimated Manual Coverage:**
- API client: 80% (all endpoints tested manually)
- Sync handler: 75% (all hooks tested, edge cases vary)
- Sync poller: 70% (polling tested, conflict scenarios sometimes tested)
- Admin UI: 90% (all UI components visible and functional)

**Gaps:**
- Concurrent sync scenarios (multiple polls happening)
- Large-scale operations (1000+ funds)
- Complex conflict cascades (multiple conflicts with dependencies)
- Race conditions between inbound and outbound sync
- Memory usage under load

## Test Types

**Unit Tests:** Not implemented

**Integration Tests:** Manual
- Test WordPress → GoFundMe Pro integration
- Test GoFundMe Pro → WordPress integration
- Test conflict resolution
- Test error retry mechanisms
- Environment: WP Engine Staging with Sandbox API

**E2E Tests:** Not implemented
- Would test full workflows end-to-end
- Would verify UI state changes

## Common Patterns

**Deployment Testing Pattern:**
```
1. Code change in local main branch
   ↓
2. Deploy to staging via SSH
   cd ~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync
   git pull origin main (or manual file update)
   ↓
3. Clear transient cache
   wp transient delete gofundme_access_token
   ↓
4. Test via WP admin or WP-CLI
   wp fcg-sync status              # Check current state
   wp fcg-sync pull --dry-run      # Preview changes
   wp fcg-sync pull                # Execute pull
   ↓
5. Verify in Classy admin dashboard
   Navigate to Designations/Campaigns
   Confirm changes match expectations
   ↓
6. If successful, push to production after approval
```

**Admin Verification Pattern:**
```
1. Log into WordPress staging admin
2. Navigate to Funds post type
3. Verify Sync Status column shows appropriate badges
4. Click on a fund to open edit screen
5. Check GoFundMe Pro Sync meta box:
   - Designation ID present and linked
   - Last Sync timestamp recent
   - No error messages
   - "Sync Now" button present
6. Click "Sync Now" to trigger manual sync
7. Check meta box updates
```

**CLI Verification Pattern:**
```
wp fcg-sync status
# Inspect output:
# - Check count of Not Linked vs Synced funds
# - Identify any with Error status
# - Verify Last Sync times reasonable

wp fcg-sync conflicts
# If conflicts exist, review:
# - Timestamp of conflict
# - Which fund (Post ID)
# - What differed (WP Title vs GFM Title)
# - Reason (always "WP modified after last sync")

wp fcg-sync push --dry-run --limit=10
# Verify that funds without designations would be created
# Verify that funds with designations would be skipped
# Run actual push if output looks correct
```

## Debugging

**Enable debug logging:**
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**View logs:**
```bash
# SSH to staging
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net

# Tail error log
tail -f ~/logs/error.log | grep "FCG GoFundMe Sync"

# Search for specific errors
grep "designation" ~/logs/error.log
```

**Check transient cache:**
```bash
wp transient get gofundme_access_token
wp transient delete gofundme_access_token   # Clear if expired
```

**Inspect post meta:**
```bash
wp post meta get 13707 _gofundme_designation_id
wp post meta list 13707 --prefix=_gofundme
```

---

*Testing analysis: 2026-01-22*
