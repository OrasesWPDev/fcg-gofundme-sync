# Pitfalls Research: Campaign Sync for Classy API

**Domain:** WordPress + Classy (GoFundMe Pro) API Integration
**Researched:** 2026-01-22
**Overall Confidence:** MEDIUM (verified patterns from general best practices + Classy docs, specific campaign sync scenarios are LOW confidence)

## Executive Summary

Adding campaign sync to an existing designation-only WordPress/Classy integration introduces critical pitfalls in five areas:

1. **API Endpoint Permissions** - POST /campaigns returns 403, must use duplicateCampaign
2. **Bulk Migration at Scale** - 758 funds will timeout without batching and proper PHP config
3. **Race Conditions** - WordPress transient cache and concurrent WP-Cron requests create data inconsistency
4. **OAuth Token Concurrency** - Multiple simultaneous requests can invalidate/overwrite tokens
5. **Data Drift & Sync Conflicts** - Inbound polling + outbound push creates eventual consistency challenges

**Most Critical Risk:** Bulk migration of 758 funds without batching will exceed PHP max_execution_time and create partial sync states that are difficult to recover from.

---

## Critical Pitfalls

### Pitfall 1: POST /campaigns Endpoint is Not Public (403 Forbidden)

**What goes wrong:**
Attempting to create campaigns via `POST /organizations/{org_id}/campaigns` returns **403 Forbidden** even with valid OAuth token. This is a known Classy API restriction - direct campaign creation is not available to public API consumers.

**Why it happens:**
Classy's campaign creation endpoint requires organization-level admin permissions that are not granted through standard OAuth2 client_credentials flow. The API documentation shows the endpoint exists, but it's restricted to internal/privileged access only.

**Consequences:**
- Development blocked if attempting direct campaign creation
- Wasted time debugging OAuth scopes/permissions (won't fix the issue)
- Must pivot to `duplicateCampaign` endpoint workflow

**Prevention:**
```php
// WRONG - Will return 403
$api->request('POST', "/organizations/{$org_id}/campaigns", $campaign_data);

// CORRECT - Use duplicateCampaign endpoint
$api->request('POST', "/campaigns/{$template_id}/actions/duplicate", [
    'overrides' => [
        'name' => $fund_name,
        'goal' => $fund_goal,
        // ... other overrideable fields
    ]
]);
```

**Detection:**
- HTTP 403 responses when attempting campaign creation
- Error message: "You do not have permission to perform this action" or similar
- Successful designation creation but failed campaign creation

**Phase Impact:** Phase C2 (Campaign Push Sync) - MUST use duplicateCampaign from the start

**Confidence:** HIGH (confirmed in project context, verified by Classy API documentation pattern)

**Sources:**
- [Classy API](https://developers.classy.org/api-docs/v2/index.html) - duplicateCampaign endpoint documented
- Project context confirms POST /campaigns returns 403

---

### Pitfall 2: Bulk Migration Timeout (758 Funds) Without Batching

**What goes wrong:**
Running bulk migration for 758 existing funds in a single execution will exceed PHP's `max_execution_time` (typically 30-300 seconds) and `memory_limit`, causing partial completion and inconsistent sync state.

**Why it happens:**
Each fund requires:
1. OAuth token acquisition (if not cached) - ~500ms
2. Template campaign duplication API call - ~1-2 seconds
3. Campaign field overrides API call - ~1-2 seconds
4. WordPress post meta updates - ~100ms
5. Database writes - ~50ms

**Total per fund:** ~2-4 seconds
**758 funds × 3 seconds avg = 2,274 seconds (38 minutes)**

Default PHP `max_execution_time` is 30-120 seconds on most hosts. WP Engine may allow up to 300 seconds (5 minutes), which still only handles ~100 funds.

**Consequences:**
- Script timeout mid-execution
- Partial migration: some funds have campaigns, others don't
- Orphaned campaigns in Classy with no WordPress association
- Difficult to resume - which funds were processed?
- Admin panic when bulk operation "fails silently"

**Prevention:**

**Strategy 1: WP-CLI Batching (RECOMMENDED)**
```php
// wp fcg-sync migrate-campaigns --batch-size=50 --batch=1
// Process funds 1-50, then exit cleanly

function cli_migrate_campaigns($args, $assoc_args) {
    $batch_size = $assoc_args['batch-size'] ?? 50;
    $batch = $assoc_args['batch'] ?? 1;
    $offset = ($batch - 1) * $batch_size;

    $funds = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => $batch_size,
        'offset' => $offset,
        'meta_query' => [
            [
                'key' => '_gofundme_campaign_id',
                'compare' => 'NOT EXISTS'
            ]
        ]
    ]);

    foreach ($funds as $fund) {
        $this->create_campaign_for_fund($fund->ID);
        WP_CLI::log("Created campaign for fund {$fund->ID}");
    }

    WP_CLI::success("Batch {$batch} complete: {$batch_size} funds processed");
}
```

**Strategy 2: Async Background Processing**
- Use Action Scheduler plugin (WooCommerce) to queue campaign creation
- Process 5-10 funds per minute in background
- Graceful resume on failure

**Strategy 3: Increase PHP Limits (TEMPORARY FIX)**
```php
// In migration script
set_time_limit(0); // Unlimited execution time (dangerous on web requests)
ini_set('memory_limit', '512M'); // Increase memory

// Better: Configure in php.ini or WP Engine settings
// max_execution_time = 600 (10 minutes)
// memory_limit = 512M
```

**Detection:**
- "Fatal Error: Maximum Execution Time Exceeded"
- WordPress white screen during bulk operation
- Migration stops after N funds (where N × 3 seconds ≈ max_execution_time)
- Inconsistent `_gofundme_campaign_id` meta across funds

**Phase Impact:**
- Phase C4 (Bulk Migration) - PRIMARY CONCERN
- Must use batching from day one
- Plan for resume/retry logic

**Confidence:** HIGH (WordPress bulk operation timeouts are well-documented, math is straightforward)

**Sources:**
- [How to Fix WordPress max_execution_time Fatal Error](https://kinsta.com/blog/wordpress-max-execution-time/)
- [Bulk edit posts very slow performance](https://wordpress.org/support/topic/bulk-edit-posts-very-slow-performance/)

---

### Pitfall 3: WordPress Transient Race Conditions on OAuth Token Cache

**What goes wrong:**
When multiple concurrent requests trigger OAuth token acquisition simultaneously (e.g., during bulk operations or high-traffic polling), WordPress transients create a race condition where:
1. Request A checks transient, finds no token
2. Request B checks transient, finds no token
3. Request A requests new token, caches it
4. Request B requests new token, overwrites A's token
5. Both requests may use different tokens or A's token gets invalidated

This leads to intermittent 401 Unauthorized errors and wasted API calls.

**Why it happens:**
WordPress transients are not atomic. The `get_transient()` → `set_transient()` operation is not locked, so concurrent processes can read stale data. The existing code uses this pattern:

```php
// class-api-client.php line 111-114 (VULNERABLE)
$cached_token = get_transient(self::TOKEN_TRANSIENT);
if ($cached_token) {
    return $cached_token;
}
// ... request new token
set_transient(self::TOKEN_TRANSIENT, $access_token, $expires_in - 300);
```

**Consequences:**
- Intermittent 401 errors during bulk operations
- Duplicate OAuth token requests (hitting API rate limits)
- Token invalidation mid-operation
- Unpredictable failures that are hard to reproduce
- Higher API usage costs

**Prevention:**

**Strategy 1: Use WordPress Locking (RECOMMENDED)**
```php
private function get_access_token() {
    $cached_token = get_transient(self::TOKEN_TRANSIENT);
    if ($cached_token) {
        return $cached_token;
    }

    // Acquire lock before requesting new token
    global $wpdb;
    $lock_name = 'gofundme_token_refresh';
    $lock_acquired = $wpdb->get_var($wpdb->prepare(
        "SELECT GET_LOCK(%s, 10)", // Wait up to 10 seconds for lock
        $lock_name
    ));

    if (!$lock_acquired) {
        // Another process is refreshing, wait and check again
        sleep(2);
        return get_transient(self::TOKEN_TRANSIENT) ?: false;
    }

    try {
        // Double-check transient after acquiring lock (another process may have set it)
        $cached_token = get_transient(self::TOKEN_TRANSIENT);
        if ($cached_token) {
            return $cached_token;
        }

        // Request new token
        $response = wp_remote_post(self::TOKEN_URL, [...]);
        // ... process response
        set_transient(self::TOKEN_TRANSIENT, $access_token, $expires_in - 300);

        return $access_token;
    } finally {
        // Always release lock
        $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
    }
}
```

**Strategy 2: Add Token Refresh Buffer**
```php
// Refresh token 10 minutes early instead of 5 minutes
// Reduces window where multiple processes see "expired" token
set_transient(self::TOKEN_TRANSIENT, $access_token, max(60, $expires_in - 600));
```

**Strategy 3: Pre-warm Token Before Bulk Operations**
```php
// In WP-CLI bulk migration script
function cli_migrate_campaigns() {
    // Force token refresh before starting
    delete_transient('gofundme_access_token');
    $this->api->request('GET', "/organizations/{$org_id}/campaigns?per_page=1");

    // Now token is cached, bulk operation won't trigger concurrent refreshes
    // ... proceed with migration
}
```

**Detection:**
- Intermittent 401 errors during high-concurrency operations
- Multiple OAuth token requests in logs within seconds of each other
- "Invalid token" errors that resolve themselves
- Errors during bulk operations that don't occur for single operations

**Phase Impact:**
- Phase C4 (Bulk Migration) - HIGH RISK due to concurrent requests
- Phase C5 (Inbound Donation Sync) - MEDIUM RISK if polling triggers concurrent token refresh

**Confidence:** HIGH (WordPress transient race conditions are well-documented)

**Sources:**
- [Finding and solving a race condition in WordPress](https://www.altis-dxp.com/finding-and-solving-a-race-condition-in-wordpress/)
- [Protect against Race Condition](https://patchstack.com/academy/wordpress/securing-code/race-condition/)
- [OAuth 2.0 Refresh Token Best Practices](https://stateful.com/blog/oauth-refresh-token-best-practices)

---

### Pitfall 4: WP-Cron Unreliability for Scheduled Inbound Sync

**What goes wrong:**
WordPress WP-Cron is **not a true cron system**. It only fires when someone visits the website. For inbound donation sync polling every 15 minutes:
- Low-traffic sites: polling may not run for hours/days
- High-traffic sites with page caching: WP-Cron may not fire at all (static HTML served, no PHP execution)
- Missed schedule errors: WP-Cron events can be skipped if previous execution is still running

**Why it happens:**
WP-Cron triggers on page load, not on a timer. The existing code uses:

```php
// class-sync-poller.php line 88-92
$schedules[self::CRON_INTERVAL] = [
    'interval' => 15 * 60, // 900 seconds
    'display'  => __('Every 15 Minutes (FCG GoFundMe Sync)')
];
```

This only runs every 15 minutes **IF** someone visits the site during that window.

**Consequences:**
- Donation totals out of sync for hours
- Campaign status changes not reflected in WordPress
- Admin frustration: "Why aren't donations showing up?"
- Unpredictable sync timing
- Inbound sync effectively broken on cached/low-traffic sites

**Prevention:**

**Strategy 1: Disable WP-Cron, Use Server Cron (RECOMMENDED for WP Engine)**
```bash
# 1. Add to wp-config.php
define('DISABLE_WP_CRON', true);

# 2. Set up server cron (WP Engine SSH)
# Edit crontab: crontab -e
*/15 * * * * cd /home/wpe-user/sites/site-name && wp cron event run --due-now > /dev/null 2>&1
```

**Strategy 2: Use External Cron Service**
- EasyCron.com, cron-job.org, UptimeRobot
- Hit `https://yoursite.com/wp-cron.php` every 15 minutes
- More reliable than WP-Cron, doesn't require server access

**Strategy 3: Add Manual Trigger for Testing**
```php
// WP-CLI command to force inbound sync
WP_CLI::add_command('fcg-sync pull-now', function() {
    $poller = new FCG_GFM_Sync_Poller();
    $poller->poll();
    WP_CLI::success('Inbound sync completed');
});
```

**Detection:**
- `wp cron event list` shows scheduled events but "Next Run" never updates
- Admin reports donations not appearing
- `fcg_gfm_last_poll` option timestamp is stale (hours/days old)
- WP-Cron events shown as "missed" in WP Crontrol plugin

**Phase Impact:**
- Phase C5 (Inbound Donation Sync) - CRITICAL
- Must address before relying on polling for live data
- Document for client during deployment

**Confidence:** HIGH (WP-Cron unreliability is extremely well-documented)

**Sources:**
- [Event Scheduling and wp-cron - WP Engine Support](https://wpengine.com/support/wp-cron-wordpress-scheduling/)
- [The Correct Way to Configure WordPress Cron](https://spinupwp.com/doc/understanding-wp-cron/)
- [Cron events that have missed their schedule](https://wp-crontrol.com/help/missed-cron-events/)

---

### Pitfall 5: Campaign Status Lifecycle Mismatch (active vs unpublished vs deactivated)

**What goes wrong:**
Classy campaigns have three distinct statuses with different meanings:
- **active** - Published and counting towards organization limits
- **unpublished** - Created but not public
- **deactivated** - Fully deactivated, can be reactivated to **unpublished** (not active)

WordPress funds have post_status: publish/draft/trash. Mapping these states incorrectly causes:
- Campaigns counting against org limits when they shouldn't
- Trashed funds showing as "unpublished" in Classy instead of "deactivated"
- Cannot reactivate a campaign that was never deactivated

**Why it happens:**
Assuming "inactive" and "unpublished" are the same thing. The Classy API docs show:
- `POST /campaigns/{id}/publish` → status becomes **active**
- `POST /campaigns/{id}/unpublish` → status becomes **unpublished**
- `POST /campaigns/{id}/deactivate` → status becomes **deactivated**
- `POST /campaigns/{id}/reactivate` → status becomes **unpublished** (not active!)

**Consequences:**
- Organization hits campaign limit unexpectedly (unpublished campaigns still count)
- Cannot properly restore trashed funds (reactivate → unpublished, not active)
- Client confusion: "Why is this inactive campaign counting against my limit?"
- Data inconsistency between WordPress and Classy

**Prevention:**

**Correct Mapping:**
| WordPress Status | Classy Action | Classy Status | Notes |
|------------------|---------------|---------------|-------|
| draft → publish | duplicateCampaign + publish | active | Create AND publish |
| publish → draft | unpublish | unpublished | Still counts against limit |
| publish → trash | deactivate | deactivated | Does NOT count against limit |
| trash → publish | reactivate + publish | unpublished → active | Two-step process |

**Correct Implementation:**
```php
function sync_campaign_to_gofundme($post_id, $post) {
    $campaign_id = $this->get_campaign_id($post_id);

    if (!$campaign_id) {
        // No campaign exists
        if ($post->post_status === 'publish') {
            // Create AND publish
            $result = $this->api->request('POST', "/campaigns/{$template_id}/actions/duplicate", [...]);
            $new_campaign_id = $result['data']['id'];
            $this->api->request('POST', "/campaigns/{$new_campaign_id}/publish", []);
        }
        return;
    }

    // Campaign exists, handle status changes
    if ($post->post_status === 'publish') {
        // WordPress is published
        $campaign_data = $this->api->get_campaign($campaign_id);
        if ($campaign_data['data']['status'] === 'deactivated') {
            // Reactivate first (goes to unpublished)
            $this->api->request('POST', "/campaigns/{$campaign_id}/reactivate", []);
        }
        if ($campaign_data['data']['status'] !== 'active') {
            // Now publish
            $this->api->request('POST', "/campaigns/{$campaign_id}/publish", []);
        }
    } elseif ($post->post_status === 'draft') {
        // Unpublish (keeps campaign, just not public)
        $this->api->request('POST', "/campaigns/{$campaign_id}/unpublish", []);
    }
    // Note: trash is handled by on_trash_fund() which calls deactivate
}
```

**Detection:**
- Campaigns with status "unpublished" when expected "deactivated"
- Organization hitting campaign limits unexpectedly
- Restored funds not appearing as active in Classy
- API errors when trying to unpublish a deactivated campaign

**Phase Impact:**
- Phase C2 (Campaign Push Sync) - MEDIUM RISK
- Phase C3 (Campaign Status Management) - HIGH RISK
- Must understand lifecycle from the start

**Confidence:** MEDIUM (Classy API docs verified, but specific behavior during state transitions not fully tested)

**Sources:**
- [Campaign Status Definitions - Classy support center](https://support.classy.org/s/article/campaign-status-definitions)
- [Classy API](https://developers.classy.org/api-docs/v2/index.html) - publish/unpublish/deactivate/reactivate endpoints

---

## Common Mistakes

### Mistake 1: Assuming duplicateCampaign Copies All Fields

**What goes wrong:**
The `duplicateCampaign` endpoint does NOT copy all related objects:
- Tickets: NOT duplicated by default
- Ecards: NOT duplicated by default
- Permissions: NOT duplicated by default
- Fundraising pages: NOT duplicated by default

Only core campaign settings are duplicated. The `overrides` parameter can overwrite specific fields, but you can't override fields that weren't in the source campaign.

**Prevention:**
- Read Classy API docs carefully for what IS duplicated
- Test with template campaign to verify which fields come through
- Document which fields are inherited vs which must be set via overrides
- **Phase C2 priority:** Confirm with Classy contact what fields can be updated post-duplication

**Detection:**
- Missing features in duplicated campaigns
- Tickets/ecards not appearing
- Permissions not set correctly

**Confidence:** HIGH (documented in Classy API)

**Sources:**
- [Classy API](https://developers.classy.org/api-docs/v2/index.html) - duplicateCampaign notes

---

### Mistake 2: Not Implementing Idempotency for Webhook/Polling Data

**What goes wrong:**
During inbound sync polling (Phase C5), if the same donation data is pulled multiple times (due to cron overlap, retries, manual triggers), without idempotency checks you may:
- Duplicate donation totals in WordPress
- Overwrite newer data with older data
- Create data inconsistency

**Why it happens:**
The existing code has conflict detection (`has_designation_changed()`), but adding campaign/donation sync needs similar checks. Polling APIs don't guarantee "exactly once" delivery - you might fetch the same data multiple times.

**Prevention:**

**Strategy 1: Track Last Updated Timestamp**
```php
function sync_campaign_data($post_id, $campaign_data) {
    $last_synced = get_post_meta($post_id, '_gofundme_campaign_last_updated', true);
    $remote_updated = strtotime($campaign_data['updated_at']);

    if ($last_synced && $remote_updated <= $last_synced) {
        // Remote data is not newer, skip
        return;
    }

    // Proceed with sync
    update_post_meta($post_id, 'donation_total', $campaign_data['total_gross_amount']);
    update_post_meta($post_id, '_gofundme_campaign_last_updated', $remote_updated);
}
```

**Strategy 2: Use WordPress "WordPress Wins" Pattern**
```php
// Already implemented for designations in class-sync-poller.php
function should_apply_gfm_changes($post_id, $remote_data) {
    $wp_modified = get_post_modified_time('U', false, $post_id);
    $gfm_modified = strtotime($remote_data['updated_at']);

    // If WordPress was modified more recently, skip Classy update
    return $gfm_modified > $wp_modified;
}
```

**Detection:**
- Donation totals increasing on every poll despite no new donations
- Campaign data reverting to old values
- Logs showing repeated updates with same data

**Phase Impact:**
- Phase C5 (Inbound Donation Sync) - MEDIUM RISK
- Build on existing conflict detection pattern

**Confidence:** HIGH (existing codebase has this pattern, extend it)

**Sources:**
- [How to handle duplicate events in your code](https://postmarkapp.com/blog/why-idempotency-is-important)
- [Webhook Idempotency - Cashfree Docs](https://www.cashfree.com/docs/payments/online/webhooks/webhook-indempotency)

---

### Mistake 3: Not Validating Required Campaign Fields Before Duplication

**What goes wrong:**
Attempting to duplicate a campaign with invalid or missing override data causes API errors. Classy may require certain fields (name, goal, etc.) and have validation rules (goal > 0, name length, etc.).

**Why it happens:**
Not validating WordPress data before sending to API. The designation sync code doesn't show validation, but campaigns may have stricter requirements.

**Prevention:**
```php
function build_campaign_data($post) {
    $name = get_the_title($post->ID);
    $goal = get_field('fundraising_goal', $post->ID); // ACF field

    // Validate before sending to API
    $errors = [];

    if (empty($name) || strlen($name) < 3) {
        $errors[] = 'Campaign name must be at least 3 characters';
    }

    if (empty($goal) || $goal <= 0) {
        $errors[] = 'Fundraising goal must be greater than 0';
    }

    if (!empty($errors)) {
        $this->log_error("Cannot sync campaign for post {$post->ID}: " . implode(', ', $errors));
        update_post_meta($post->ID, '_gofundme_sync_error', implode(', ', $errors));
        return false;
    }

    return [
        'overrides' => [
            'name' => $name,
            'goal' => $goal,
            // ... other fields
        ]
    ];
}
```

**Detection:**
- API errors: "Validation failed: name is required"
- Campaigns not created despite publish action
- Post meta `_gofundme_sync_error` populated

**Phase Impact:**
- Phase C2 (Campaign Push Sync) - LOW RISK (easy to fix)
- Phase C4 (Bulk Migration) - MEDIUM RISK (silent failures during bulk)

**Confidence:** MEDIUM (general API best practice, specific Classy validation rules unknown)

**Sources:**
- [Classy for Salesforce API Request Error Messages](https://support.classy.org/s/article/classy-for-salesforce-api-request-error-messages)

---

### Mistake 4: Ignoring Campaign Currency and Goal Normalization

**What goes wrong:**
Classy campaigns have four currency/goal attributes:
- `goal` - Amount in organization-level currency
- `currency_code` - Organization currency (e.g., USD)
- `raw_goal` - Amount in campaign-specific currency
- `raw_currency_code` - Campaign-specific currency

If you only set `goal` without understanding currency normalization, Classy may:
- Normalize the goal using current exchange rate
- Display wrong goal amount on campaign page
- Cause confusion when `goal !== raw_goal`

**Why it happens:**
Not reading Classy's currency documentation. The API handles multi-currency, but you need to set both raw and normalized values.

**Prevention:**
```php
function build_campaign_data($post) {
    $goal = get_field('fundraising_goal', $post->ID); // Assumed USD

    return [
        'overrides' => [
            'name' => get_the_title($post->ID),
            'raw_goal' => $goal, // What displays on campaign page
            'raw_currency_code' => 'USD', // Currency for display
            // Classy will auto-calculate 'goal' and 'currency_code' from org settings
        ]
    ];
}
```

**Detection:**
- Campaign goal amounts don't match WordPress values
- Currency symbols incorrect on campaign pages
- Classy support inquiries about "wrong goal amounts"

**Phase Impact:**
- Phase C2 (Campaign Push Sync) - LOW RISK (cosmetic, easy to fix)
- Document for client if using multi-currency

**Confidence:** MEDIUM (Classy docs mention this, but specific behavior unknown)

**Sources:**
- [Classy API](https://developers.classy.org/api-docs/v2/index.html) - Passport/currency documentation

---

## Rate Limiting Issues

### Issue 1: Classy API Rate Limits (Unknown, Assume Conservative)

**Problem:**
Classy API documentation does NOT explicitly state rate limits. Absence of documentation means:
- Limits exist but are not published
- Hitting limits results in 429 Too Many Requests
- No guidance on retry-after headers or backoff strategy

**Assumptions for Safe Operation:**
- **Per-token limit:** ~100-300 requests/minute (industry standard for fundraising APIs)
- **Burst limit:** ~10 concurrent requests
- **Daily limit:** Unknown (likely 10K-50K requests)

**Prevention:**

**Strategy 1: Rate Limit Tracking**
```php
class FCG_GFM_API_Client {
    private $request_timestamps = [];
    private const MAX_REQUESTS_PER_MINUTE = 100;

    public function request($method, $endpoint, $data = null) {
        // Remove timestamps older than 1 minute
        $this->request_timestamps = array_filter($this->request_timestamps, function($ts) {
            return $ts > (time() - 60);
        });

        // Check if at limit
        if (count($this->request_timestamps) >= self::MAX_REQUESTS_PER_MINUTE) {
            // Wait until oldest request is >60s old
            $oldest = min($this->request_timestamps);
            $wait_seconds = 60 - (time() - $oldest) + 1;
            sleep($wait_seconds);
        }

        // Track this request
        $this->request_timestamps[] = time();

        // Proceed with API call
        // ... existing code
    }
}
```

**Strategy 2: Handle 429 Responses**
```php
// In request() method, after getting response
if ($code === 429) {
    $retry_after = wp_remote_retrieve_header($response, 'Retry-After') ?: 60;
    $this->log_error("Rate limited. Retrying after {$retry_after} seconds.");

    // Exponential backoff with jitter
    $backoff = min(300, $retry_after * (2 ** $attempt) + rand(0, 10));
    sleep($backoff);

    // Retry
    return $this->request($method, $endpoint, $data);
}
```

**Strategy 3: Bulk Operation Throttling**
```php
// In bulk migration
function cli_migrate_campaigns() {
    $funds = get_posts([...]);

    foreach ($funds as $index => $fund) {
        $this->create_campaign_for_fund($fund->ID);

        // Throttle: 10 requests/minute = 1 request every 6 seconds
        if (($index + 1) % 10 === 0) {
            WP_CLI::log("Throttling: waiting 60 seconds...");
            sleep(60);
        }
    }
}
```

**Detection:**
- HTTP 429 responses in logs
- API errors: "Rate limit exceeded"
- Failed campaigns during bulk migration
- Exponential backoff retry attempts

**Phase Impact:**
- Phase C4 (Bulk Migration) - HIGH RISK (758 funds = 1,516+ API calls)
- Must throttle bulk operations to avoid hitting unknown limits

**Confidence:** LOW (Classy API docs don't specify limits, extrapolating from industry norms)

**Sources:**
- General API rate limiting best practices (no Classy-specific documentation found)
- [API Rate Limiting Documentation Template](https://rivereditor.com/blogs/write-rate-limiting-explanation-api)

---

### Issue 2: OAuth Token Request Limits

**Problem:**
The existing code caches OAuth tokens with 5-minute early expiration (line 142 in class-api-client.php). However, if token cache is cleared frequently (manual, plugin conflicts, cache flush), repeated token requests may hit OAuth endpoint rate limits.

**Prevention:**
- Extend early expiration buffer from 5 to 10 minutes: `$expires_in - 600`
- Add retry logic for token acquisition failures
- Monitor token refresh frequency in logs
- Add transient lock (see Pitfall 3) to prevent concurrent refresh

**Detection:**
- OAuth token endpoint returning 429
- Repeated token acquisition failures
- High volume of token requests in logs

**Phase Impact:**
- All phases - LOW RISK (unlikely with proper caching)

**Confidence:** MEDIUM (OAuth rate limiting is common, but Classy's specific limits unknown)

---

## Bulk Migration Risks (758 Existing Funds)

### Risk 1: Partial Migration State Recovery

**Problem:**
If bulk migration fails mid-execution (timeout, API error, server restart), you end up with:
- 400 funds with campaigns
- 358 funds without campaigns
- No easy way to identify which funds need retry

**Prevention:**

**Strategy 1: Resume-able Migration (RECOMMENDED)**
```php
function cli_migrate_campaigns($args, $assoc_args) {
    // Query funds WITHOUT campaign_id meta
    $funds = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => $assoc_args['batch-size'] ?? 50,
        'meta_query' => [
            [
                'key' => '_gofundme_campaign_id',
                'compare' => 'NOT EXISTS'
            ]
        ]
    ]);

    // Only process funds that haven't been migrated yet
    // Can re-run command safely, it picks up where it left off
}
```

**Strategy 2: Migration Log**
```php
function create_campaign_for_fund($post_id) {
    $log_file = WP_CONTENT_DIR . '/fcg-migration-log.csv';
    $log = fopen($log_file, 'a');

    try {
        $result = $this->duplicate_campaign_from_template($post_id);
        fputcsv($log, [date('Y-m-d H:i:s'), $post_id, 'success', $result['campaign_id']]);
    } catch (Exception $e) {
        fputcsv($log, [date('Y-m-d H:i:s'), $post_id, 'error', $e->getMessage()]);
    }

    fclose($log);
}
```

**Strategy 3: Dry-Run Mode**
```php
// wp fcg-sync migrate-campaigns --dry-run
if ($assoc_args['dry-run'] ?? false) {
    WP_CLI::log("DRY RUN: Would create campaign for fund {$fund->ID}");
    // Don't actually make API call
} else {
    $this->create_campaign_for_fund($fund->ID);
}
```

**Confidence:** HIGH (standard bulk operation best practice)

---

### Risk 2: Template Campaign Misconfiguration

**Problem:**
If the template campaign ID is wrong, deleted, or misconfigured:
- All 758 duplication attempts will fail
- Waste time/API calls before discovering issue
- May create campaigns with wrong settings

**Prevention:**

**Strategy 1: Pre-flight Validation**
```php
function cli_migrate_campaigns() {
    $template_id = get_option('fcg_gfm_template_campaign_id');

    if (empty($template_id)) {
        WP_CLI::error('Template campaign ID not set. Use: wp option update fcg_gfm_template_campaign_id 12345');
        return;
    }

    // Verify template exists
    $result = $this->api->get_campaign($template_id);
    if (!$result['success']) {
        WP_CLI::error("Template campaign {$template_id} not found or inaccessible: {$result['error']}");
        return;
    }

    WP_CLI::confirm("Template campaign: {$result['data']['name']}. Proceed with migration?");

    // Continue with migration...
}
```

**Confidence:** HIGH (straightforward validation)

---

### Risk 3: Duplicate Campaign Creation on Retry

**Problem:**
If migration is re-run after partial failure, might create duplicate campaigns for funds that already have one.

**Prevention:**
Always check for existing campaign ID before creating (see Resume-able Migration above).

```php
function create_campaign_for_fund($post_id) {
    // Check if already has campaign
    $existing_campaign_id = get_post_meta($post_id, '_gofundme_campaign_id', true);
    if ($existing_campaign_id) {
        WP_CLI::log("Fund {$post_id} already has campaign {$existing_campaign_id}, skipping");
        return;
    }

    // Proceed with creation
}
```

**Confidence:** HIGH (defensive programming)

---

## Data Drift and Eventual Consistency

### Issue 1: Two-Way Sync Conflicts (WordPress + Classy Both Modified)

**Problem:**
Existing code implements "WordPress wins" for designation conflicts. When adding campaign sync:
- WordPress: Admin updates fund title at 10:00am
- Classy: Someone manually updates campaign name in Classy at 10:05am
- Inbound poll at 10:15am: Which name wins?

Current pattern: WordPress wins (see `should_apply_gfm_changes()` in class-sync-poller.php).

**Prevention:**
Continue "WordPress wins" pattern, but add visibility:

```php
function should_apply_gfm_changes($post_id, $campaign_data) {
    $wp_modified = get_post_modified_time('U', false, $post_id);
    $gfm_modified = strtotime($campaign_data['updated_at']);

    if ($wp_modified > $gfm_modified) {
        // WordPress is newer, skip Classy changes
        return false;
    }

    if ($gfm_modified > $wp_modified) {
        // Classy is newer, but log it (possible manual edit)
        $this->log_info("Classy campaign {$campaign_data['id']} was modified in Classy after WordPress. WordPress wins, discarding Classy changes.");
        return false; // Still WordPress wins
    }

    return true;
}
```

**Alternative:** Add admin notice when conflicts detected:
```php
if ($gfm_modified > $wp_modified) {
    update_post_meta($post_id, '_gofundme_conflict_detected', [
        'wp_modified' => $wp_modified,
        'gfm_modified' => $gfm_modified,
        'gfm_data' => $campaign_data
    ]);

    // Admin UI shows "Conflict detected" warning in meta box
}
```

**Confidence:** HIGH (extend existing pattern)

---

### Issue 2: Donation Total Staleness (15-Minute Lag)

**Problem:**
Inbound polling every 15 minutes means donation totals can be up to 15 minutes stale. If client expects real-time updates, this is unacceptable.

**Reality Check:**
Classy does NOT offer webhooks. Polling is the only option. 15-minute polling is reasonable for:
- Admin dashboard views
- Internal reporting
- Non-critical displays

NOT reasonable for:
- Public-facing "live" thermometers
- Real-time donor notifications
- High-frequency donation events

**Prevention:**
Set client expectations during deployment:
- Document 15-minute lag in admin UI
- Add "Last synced: X minutes ago" timestamp to displays
- Offer manual "Sync Now" button for immediate refresh

```php
// Admin meta box
<p>Donation total: $<?php echo number_format($total, 2); ?></p>
<p><small>Last synced: <?php echo human_time_diff($last_sync); ?> ago</small></p>
<button type="button" class="button" onclick="fcgSyncNow(<?php echo $post_id; ?>)">Sync Now</button>
```

**Confidence:** HIGH (API limitation, not implementation issue)

---

## Summary Table: Pitfalls by Phase

| Phase | Critical Pitfall | Risk Level | Mitigation Priority |
|-------|------------------|------------|---------------------|
| C2: Campaign Push Sync | POST /campaigns returns 403 | HIGH | IMMEDIATE - Use duplicateCampaign |
| C2: Campaign Push Sync | Campaign status lifecycle mismatch | MEDIUM | Phase planning |
| C3: Campaign Status Mgmt | Campaign status lifecycle (active/unpublished/deactivated) | HIGH | Research & testing |
| C4: Bulk Migration | Timeout without batching (758 funds) | CRITICAL | IMMEDIATE - WP-CLI batching required |
| C4: Bulk Migration | OAuth token race conditions | HIGH | Add locking before bulk ops |
| C4: Bulk Migration | API rate limiting (unknown limits) | MEDIUM | Add throttling |
| C5: Inbound Donation Sync | WP-Cron unreliability | CRITICAL | Disable WP-Cron, use server cron |
| C5: Inbound Donation Sync | Idempotency (duplicate data) | MEDIUM | Extend existing pattern |
| C5: Inbound Donation Sync | Data staleness (15-min lag) | LOW | Set client expectations |
| All phases | OAuth token transient race condition | MEDIUM | Add locking to get_access_token() |

---

## Recommended Research Flags for Phases

### Phase C2: Campaign Push Sync
- **FLAG:** Confirm with Classy contact which campaign fields can be updated post-duplication
- **FLAG:** Test duplicateCampaign with template to verify inherited fields
- **Likely needs deeper research:** YES (API behavior not fully documented)

### Phase C3: Campaign Status Management
- **FLAG:** Test campaign lifecycle transitions in sandbox (active → unpublished → deactivated → reactivated → published)
- **Likely needs deeper research:** MAYBE (can test in sandbox quickly)

### Phase C4: Bulk Migration
- **FLAG:** Load test bulk operation with 100 funds to measure timing and identify bottlenecks
- **FLAG:** Test API rate limit behavior (gradually increase request frequency until 429)
- **Likely needs deeper research:** YES (unknown rate limits)

### Phase C5: Inbound Donation Sync
- **FLAG:** Verify Classy API response structure for campaign donation totals (field names, nesting, currency handling)
- **FLAG:** Test inbound sync with stale vs fresh data to verify timestamp handling
- **Likely needs deeper research:** NO (standard patterns apply)

---

## Confidence Assessment by Domain

| Domain | Confidence | Rationale |
|--------|------------|-----------|
| WordPress bulk operations | HIGH | Well-documented timeouts, transient race conditions verified |
| WP-Cron unreliability | HIGH | Extensively documented WordPress limitation |
| OAuth token handling | MEDIUM | General best practices apply, Classy-specific behavior unverified |
| Classy API rate limits | LOW | No official documentation found, extrapolating from industry norms |
| Campaign status lifecycle | MEDIUM | Classy docs verified, but state transition edge cases untested |
| duplicateCampaign behavior | MEDIUM | Documented what's NOT copied, but field override limits unknown |

---

## Sources

### WordPress-Specific
- [How to Fix WordPress max_execution_time Fatal Error - Kinsta](https://kinsta.com/blog/wordpress-max-execution-time/)
- [Bulk edit posts very slow performance - WordPress.org](https://wordpress.org/support/topic/bulk-edit-posts-very-slow-performance/)
- [Finding and solving a race condition in WordPress - Altis](https://www.altis-dxp.com/finding-and-solving-a-race-condition-in-wordpress/)
- [Protect against Race Condition - Patchstack](https://patchstack.com/academy/wordpress/securing-code/race-condition/)
- [Event Scheduling and wp-cron - WP Engine Support](https://wpengine.com/support/wp-cron-wordpress-scheduling/)
- [The Correct Way to Configure WordPress Cron - SpinupWP](https://spinupwp.com/doc/understanding-wp-cron/)
- [Cron events that have missed their schedule - WP Crontrol](https://wp-crontrol.com/help/missed-cron-events/)

### Classy API
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)
- [GoFundMe Pro API Documentation](https://docs.classy.org/)
- [Campaign Status Definitions - Classy support center](https://support.classy.org/s/article/campaign-status-definitions)
- [Classy for Salesforce API Request Error Messages](https://support.classy.org/s/article/classy-for-salesforce-api-request-error-messages)

### General API Integration
- [OAuth 2.0 Refresh Token Best Practices - Stateful](https://stateful.com/blog/oauth-refresh-token-best-practices)
- [How to handle duplicate events in your code - Postmark](https://postmarkapp.com/blog/why-idempotency-is-important)
- [Webhook Idempotency - Cashfree Docs](https://www.cashfree.com/docs/payments/online/webhooks/webhook-indempotency)
- [How can you ensure eventual consistency in your API integrations? - LinkedIn](https://www.linkedin.com/advice/0/how-can-you-ensure-eventual-consistency-your-api-n9pif)
- [Data Consistency in Sharded APIs - DreamFactory](https://blog.dreamfactory.com/data-consistency-in-sharded-apis-key-integration-patterns)

---

**END OF PITFALLS RESEARCH**

*This research identifies critical mistakes and prevention strategies for campaign sync. Roadmap creation should prioritize addressing high-risk pitfalls (bulk migration batching, WP-Cron replacement, OAuth locking) in phase planning.*
