# Phase 4: Inbound Sync - Research

**Researched:** 2026-01-26
**Domain:** Classy API polling, WordPress scheduled tasks, post meta management
**Confidence:** HIGH

## Summary

This phase implements inbound synchronization from Classy (GoFundMe Pro) to WordPress, polling campaign data every 15 minutes. The core challenge is fetching donation totals and campaign status from Classy, calculating goal progress, and storing this data in WordPress post meta without triggering the outbound sync (which would cause infinite loops).

The existing codebase already has substantial infrastructure for this:
- `FCG_GFM_Sync_Poller` class with 15-minute cron setup
- `is_syncing_inbound()` static method for preventing outbound sync during inbound
- Pattern for setting/clearing transient sync flags
- API client methods for `get_campaign()` (but NOT `get_campaign_overview()` - must be added)

**Primary recommendation:** Extend the existing sync poller to poll campaigns (not just designations), add a new API client method for the campaign overview endpoint, and store donation data in new post meta keys.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Cron API | WP 5.8+ | Scheduling periodic tasks | Built-in, integrates with WP Engine Alternate Cron |
| WordPress Post Meta API | WP 5.8+ | Storing synced data | Native, no external dependencies |
| WordPress Transients API | WP 5.8+ | Temporary locks to prevent race conditions | Already used in codebase |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WP-CLI | Latest | Manual sync commands | Already implemented for `fcg-sync pull/push` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WP-Cron | Server cron + `wp-cron.php` | More reliable but WP Engine Alternate Cron already solves this |
| Post meta | Custom table | More performant for large datasets but unnecessary complexity |
| Transient locks | Database locks | More robust but overkill for this use case |

**Installation:**
No additional packages needed - all functionality available in WordPress core.

## Architecture Patterns

### Recommended Project Structure
```
includes/
  class-sync-poller.php  # Extend existing class with campaign polling
  class-api-client.php   # Add get_campaign_overview() method
```

### Pattern 1: Campaign Data Polling (Extend Existing Poller)
**What:** Add campaign polling alongside existing designation polling
**When to use:** Every 15-minute cron run
**Example:**
```php
// Source: Existing pattern in class-sync-poller.php poll() method
public function poll(): void {
    // Existing designation polling...

    // NEW: Poll campaign data for donation totals
    $this->poll_campaigns();
}

private function poll_campaigns(): void {
    $funds_with_campaigns = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => '_gofundme_campaign_id',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    foreach ($funds_with_campaigns as $post) {
        $campaign_id = get_post_meta($post->ID, '_gofundme_campaign_id', true);
        if ($campaign_id) {
            $this->sync_campaign_inbound($post->ID, $campaign_id);
        }
    }
}
```

### Pattern 2: Inbound Sync Flag (Already Implemented)
**What:** Transient flag to prevent outbound sync during inbound sync
**When to use:** Before/after any inbound data writes
**Example:**
```php
// Source: Existing pattern in class-sync-poller.php
private function set_syncing_flag(): void {
    set_transient(self::TRANSIENT_SYNCING, true, 30); // 30 second TTL
}

private function clear_syncing_flag(): void {
    delete_transient(self::TRANSIENT_SYNCING);
}

public static function is_syncing_inbound(): bool {
    return (bool) get_transient(self::TRANSIENT_SYNCING);
}

// In sync handler (already exists):
public function on_save_fund(int $post_id, WP_Post $post, bool $update): void {
    if (FCG_GFM_Sync_Poller::is_syncing_inbound()) {
        return; // Skip outbound sync during inbound
    }
    // ... rest of sync logic
}
```

### Pattern 3: Post Meta Storage for Donation Data
**What:** Store synced donation data in dedicated post meta keys
**When to use:** When writing inbound campaign data
**Example:**
```php
// New meta keys for Phase 4
private const META_DONATION_TOTAL = '_gofundme_donation_total';
private const META_DONOR_COUNT = '_gofundme_donor_count';
private const META_GOAL_PROGRESS = '_gofundme_goal_progress';
private const META_CAMPAIGN_STATUS = '_gofundme_campaign_status';
private const META_LAST_INBOUND_SYNC = '_gofundme_last_inbound_sync';

private function sync_campaign_inbound(int $post_id, string $campaign_id): void {
    $this->set_syncing_flag();

    try {
        // Fetch campaign data
        $campaign = $this->api->get_campaign($campaign_id);
        $overview = $this->api->get_campaign_overview($campaign_id);

        if (!$campaign['success'] || !$overview['success']) {
            return;
        }

        // Store campaign status
        update_post_meta($post_id, self::META_CAMPAIGN_STATUS,
            $campaign['data']['status'] ?? '');

        // Store donation totals (overview endpoint returns strings, cast to float)
        $total = floatval($overview['data']['total_gross_amount'] ?? 0);
        update_post_meta($post_id, self::META_DONATION_TOTAL, $total);

        // Store donor count
        update_post_meta($post_id, self::META_DONOR_COUNT,
            intval($overview['data']['donors_count'] ?? 0));

        // Calculate and store goal progress
        $goal = floatval($campaign['data']['goal'] ?? 0);
        $progress = ($goal > 0) ? round(($total / $goal) * 100, 1) : 0;
        update_post_meta($post_id, self::META_GOAL_PROGRESS, $progress);

        // Update sync timestamp
        update_post_meta($post_id, self::META_LAST_INBOUND_SYNC, current_time('mysql'));

    } finally {
        $this->clear_syncing_flag();
    }
}
```

### Anti-Patterns to Avoid
- **Using wp_update_post() for meta-only updates:** This triggers `save_post` hooks and causes infinite loops. Always use `update_post_meta()` directly.
- **Polling without syncing flag:** ALWAYS set the syncing flag before writing data, clear it after. Forgetting this causes infinite sync loops.
- **Trusting API response types:** The Classy overview endpoint returns amounts as strings (e.g., "8850.00"). Always cast to float/int before storing.
- **Modifying post_status based on campaign status:** The requirements specify "campaign status is polled and reflected in WordPress" but this means **post meta**, not changing the WordPress post status. WordPress is the source of truth for post status.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Preventing sync loops | Custom flag system | Existing `is_syncing_inbound()` transient pattern | Already implemented and tested in codebase |
| Scheduled polling | Custom timer logic | WordPress cron + WP Engine Alternate Cron | Platform-supported, already configured |
| Rate limiting API calls | Manual throttling | Batch processing with delays | Simple batches with 50-100ms delay between calls |

**Key insight:** The existing sync poller already has 80% of the infrastructure needed. Extend it rather than creating parallel systems.

## Common Pitfalls

### Pitfall 1: Infinite Sync Loop
**What goes wrong:** Inbound sync writes data, triggers `save_post`, which triggers outbound sync, which updates Classy, which gets polled again...
**Why it happens:** Forgetting to set the syncing flag, or using `wp_update_post()` instead of `update_post_meta()`
**How to avoid:**
1. Always use `update_post_meta()` for data storage (does NOT trigger `save_post`)
2. Always wrap inbound sync in `set_syncing_flag()`/`clear_syncing_flag()`
3. Verify `on_save_fund()` checks `is_syncing_inbound()` (already implemented)
**Warning signs:** Repeated API calls in logs, rapidly incrementing `_gofundme_last_sync` timestamps

### Pitfall 2: Campaign Overview Endpoint Missing
**What goes wrong:** Trying to get donation totals from `GET /campaigns/{id}` (doesn't include totals)
**Why it happens:** The main campaign endpoint returns goal but NOT raised amount
**How to avoid:** Use separate endpoint: `GET /campaigns/{id}/overview`
**Warning signs:** `donation_total` always 0 or null, missing `total_gross_amount` field

### Pitfall 3: WP-Cron Not Running on Cached Sites
**What goes wrong:** Scheduled tasks don't execute because WP-Cron depends on page visits
**Why it happens:** WP Engine has aggressive caching; no page visits = no cron execution
**How to avoid:** Use WP Engine Alternate Cron (already enabled on staging)
**Warning signs:** `_gofundme_last_inbound_sync` timestamp hours/days old

### Pitfall 4: Type Coercion Errors
**What goes wrong:** Comparing string "8850.00" to number, or storing "0.00" instead of 0
**Why it happens:** Classy API returns currency amounts as strings
**How to avoid:** Always cast: `floatval($overview['data']['total_gross_amount'])`
**Warning signs:** Progress calculations off, totals not matching Classy dashboard

### Pitfall 5: Modifying Post Status from Inbound Sync
**What goes wrong:** Changing WordPress post status based on campaign status causes user confusion
**Why it happens:** Misinterpreting "campaign status reflected in WordPress" as "change post status"
**How to avoid:** Store campaign status in **post meta only** (`_gofundme_campaign_status`). Never modify `post_status`. WordPress is source of truth for post status.
**Warning signs:** Posts mysteriously changing to draft when campaign is unpublished in Classy

## Code Examples

Verified patterns from official sources and existing codebase:

### API Client: Get Campaign Overview
```php
// Source: Classy API documentation (developers.gofundme.com/pro/docs/)
// Add to class-api-client.php

/**
 * Get campaign overview with donation totals
 *
 * Returns aggregated donation data including total raised, donor count, etc.
 * Amounts are returned as strings (e.g., "8850.00") - cast to float as needed.
 *
 * @param int|string $campaign_id Campaign ID
 * @return array Response with overview data
 */
public function get_campaign_overview($campaign_id): array {
    return $this->request('GET', "/campaigns/{$campaign_id}/overview");
}
```

**Response fields from overview endpoint:**
```php
// Source: Verified via Classy API documentation
[
    'start_time_utc' => null,
    'end_time_utc' => null,
    'gross_amount' => '8850.00',       // Total gross donations (string)
    'fees_amount' => '66.50',          // Platform fees (string)
    'net_amount' => '8783.50',         // After fees (string)
    'transactions_count' => 39,        // Number of transactions (int)
    'donors_count' => 39,              // Number of unique donors (int)
    'registrations_amount' => '0.0000', // Event registrations (string)
    'donations_amount' => '8850.0000', // Donations only (string)
    'total_gross_amount' => '8850.00', // Total gross (string) - USE THIS
    'donation_net_amount' => '8783.50' // Net donations (string)
]
```

### Calculating Goal Progress
```php
/**
 * Calculate goal progress percentage
 *
 * @param float $raised Amount raised
 * @param float $goal Goal amount
 * @return float Progress percentage (0-100+, can exceed 100)
 */
private function calculate_progress(float $raised, float $goal): float {
    if ($goal <= 0) {
        return 0.0;
    }
    // Allow >100% if goal exceeded
    return round(($raised / $goal) * 100, 1);
}
```

### Cron Schedule Verification
```php
// Source: Existing pattern in fcg-gofundme-sync.php
// The cron is already scheduled at plugin activation

// To verify cron is scheduled (WP-CLI):
// wp cron event list | grep fcg_gofundme_sync_poll

// To manually trigger for testing (WP-CLI):
// wp cron event run fcg_gofundme_sync_poll
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Direct WP-Cron | WP Engine Alternate Cron | Already configured | More reliable on cached sites |
| Poll designations only | Poll campaigns + designations | Phase 4 (new) | Gets donation data |
| Classy API v1 | Classy API v2.0 | Pre-existing | Uses OAuth2, current endpoints |

**Deprecated/outdated:**
- Classy branding: Now "GoFundMe Pro" (May 2025), but API remains at `api.classy.org`
- docs.classy.org: Redirects to `developers.gofundme.com/pro/docs/`

## Campaign Status Values

Campaign status values returned by `GET /campaigns/{id}`:

| Status | Meaning | WordPress Behavior |
|--------|---------|-------------------|
| `active` | Campaign is live, accepting donations | Store in meta, no action |
| `unpublished` | Campaign hidden from public | Store in meta, no action |
| `deactivated` | Campaign permanently closed | Store in meta, no action |

**Important:** WordPress post status remains controlled by WordPress (source of truth). Campaign status is informational only, stored in `_gofundme_campaign_status` meta.

## Post Meta Keys (New for Phase 4)

| Meta Key | Type | Description |
|----------|------|-------------|
| `_gofundme_donation_total` | float | Total gross amount raised (from overview) |
| `_gofundme_donor_count` | int | Number of unique donors |
| `_gofundme_goal_progress` | float | Percentage progress (0-100+) |
| `_gofundme_campaign_status` | string | Campaign status (active/unpublished/deactivated) |
| `_gofundme_last_inbound_sync` | string | MySQL datetime of last inbound sync |

**Existing keys (do not modify):**
- `_gofundme_campaign_id` - Campaign ID (set by outbound sync)
- `_gofundme_campaign_url` - Campaign URL (set by outbound sync)
- `_gofundme_last_sync` - Last outbound sync timestamp
- `_gofundme_sync_source` - Whether last sync was 'wordpress' or 'gofundme'

## WP Engine Cron Configuration

| Setting | Value | Notes |
|---------|-------|-------|
| Alternate Cron | Enabled (staging) | Checks for due crons every minute |
| `DISABLE_WP_CRON` | `true` (automatic) | Set by Alternate Cron feature |
| Interval | 15 minutes | `fcg_gfm_15min` custom schedule |
| Hook | `fcg_gofundme_sync_poll` | Already registered in plugin |

**Production deployment note:** Verify Alternate Cron is enabled in WP Engine User Portal for production environment.

## Open Questions

Things that couldn't be fully resolved:

1. **Classy API rate limits**
   - What we know: No explicit rate limits documented
   - What's unclear: Whether polling 100+ campaigns every 15 minutes will hit limits
   - Recommendation: Add 50ms delay between API calls, monitor for 429 errors

2. **Campaign overview caching**
   - What we know: Overview endpoint returns current totals
   - What's unclear: How frequently Classy updates these aggregates
   - Recommendation: Accept 15-minute polling may show slightly delayed data

3. **Error handling for missing campaigns**
   - What we know: Campaign could be deleted in Classy but ID still in WordPress
   - What's unclear: Best UX for orphaned campaign references
   - Recommendation: Log 404 errors, don't delete meta (preserves audit trail)

## Sources

### Primary (HIGH confidence)
- Existing codebase: `class-sync-poller.php`, `class-sync-handler.php`, `class-api-client.php`
- Classy API overview endpoint: Verified via [Factor1 blog](https://factor1studios.com/harnessing-power-classy-api/)
- WP Engine cron: [WP Engine Support - Event Scheduling](https://wpengine.com/support/wp-cron-wordpress-scheduling/)

### Secondary (MEDIUM confidence)
- WordPress Cron API: [Plugin Handbook](https://developer.wordpress.org/plugins/cron/)
- WordPress Post Meta: [Developer Reference](https://developer.wordpress.org/reference/functions/update_post_meta/)

### Tertiary (LOW confidence)
- Classy API rate limits: Not officially documented, based on community experience

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using existing WordPress APIs and patterns
- Architecture: HIGH - Extending existing well-tested sync poller
- Pitfalls: HIGH - Based on existing codebase patterns and common WordPress issues

**Research date:** 2026-01-26
**Valid until:** 2026-02-26 (30 days - stable domain)
