# Phase 5: Bulk Migration - Research

**Researched:** 2026-01-26
**Domain:** WP-CLI batch processing for WordPress migrations
**Confidence:** HIGH

## Summary

This research focused on WP-CLI best practices for migrating 758 existing funds to create GoFundMe Pro campaigns via batch processing. The core requirement is to leverage the existing `create_campaign_in_gfm()` method from `FCG_GFM_Sync_Handler` while implementing safe, resumable, and observable batch execution patterns.

WP-CLI provides robust primitives for batch operations including progress bars (`WP_CLI\Utils\make_progress_bar()`), memory-efficient process spawning (`WP_CLI::runcommand()` with `launch` option), and comprehensive logging methods. The established pattern for production migrations is: dry-run by default, process in small batches (50 recommended), use transients or offset-based tracking for resumability, and provide verbose progress output.

Key finding: The plugin already has a working `create_campaign_in_gfm()` method with race condition protection via 60-second transients. The migration command needs to wrap this in a WP-CLI structure with batching, progress reporting, and resume capability.

**Primary recommendation:** Implement a custom WP-CLI command class with dry-run defaulting to true, 50-fund batch size, WP_Query pagination for resumability, progress bar for user feedback, and comprehensive success/failure logging.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WP-CLI | 2.12.0 (current stable) | WordPress command-line interface | Only official WordPress CLI, built into all major hosts |
| WP_Query | WordPress Core | Post querying with pagination | Native WordPress API, memory-efficient with proper args |
| PHP sleep() | PHP core | Throttling between API calls | Standard PHP function for rate limiting |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WP_CLI\Utils\make_progress_bar() | WP-CLI core | Progress visualization | All long-running operations (>10 items) |
| WP_CLI::runcommand() | WP-CLI core | Spawn child processes for batches | When memory optimization needed (large datasets) |
| get_transient/set_transient | WordPress core | State persistence for resume | Track progress between command runs |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WP_Query pagination | Direct SQL with OFFSET | SQL more fragile, bypasses WordPress filters/caching |
| WP_CLI::runcommand() | WP_CLI::launch_self() | launch_self() doesn't persist env vars, harder to use |
| Transients for state | Custom DB table | Transients simpler, auto-expire, use existing infrastructure |

**Installation:**
No additional packages needed - all functionality is in WordPress core and WP-CLI.

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-sync-handler.php         # Existing - has create_campaign_in_gfm()
└── class-migration-command.php    # NEW - WP-CLI batch migration command
```

### Pattern 1: Command Registration via Plugin Init
**What:** Register WP-CLI commands in plugin initialization when WP-CLI is detected
**When to use:** Custom plugin commands that operate on plugin data
**Example:**
```php
// In main plugin file fcg-gofundme-sync.php
if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-migration-command.php';
    WP_CLI::add_command('fcg-gfm migrate-campaigns', 'FCG_GFM_Migration_Command');
}
```
**Source:** [WP-CLI Commands Cookbook](https://make.wordpress.org/cli/handbook/guides/commands-cookbook/)

### Pattern 2: Dry-Run Default with Explicit Opt-In
**What:** Commands default to dry-run mode requiring explicit `--live` flag for execution
**When to use:** Any destructive or API-writing operation
**Example:**
```php
// Source: WordPress VIP Documentation
$dry_run = true; // Default to safe mode
if (isset($assoc_args['live'])) {
    $dry_run = false;
    WP_CLI::warning('Running in LIVE mode - campaigns will be created in GoFundMe Pro');
}

if (!$dry_run) {
    $result = $this->sync_handler->create_campaign_in_gfm($post_id, $campaign_data);
    WP_CLI::log("Created campaign {$result['id']} for post {$post_id}");
} else {
    WP_CLI::log("[DRY RUN] Would create campaign for post {$post_id}: {$post->post_title}");
}
```
**Source:** [WordPress VIP - Best Practices for WP-CLI Commands](https://docs.wpvip.com/vip-cli/wp-cli-with-vip-cli/wp-cli-commands-on-vip/)

### Pattern 3: WP_Query Pagination for Resumability
**What:** Use `paged` parameter with WP_Query to process records in batches
**When to use:** Operations on large datasets that may be interrupted
**Example:**
```php
// Source: Igor Benić - WP CLI Batch Processing
public function migrate_campaigns($args, $assoc_args) {
    $batch_size = $assoc_args['batch-size'] ?? 50;
    $page = $assoc_args['page'] ?? 1;

    $query_args = [
        'post_type'      => 'funds',
        'posts_per_page' => $batch_size,
        'paged'          => $page,
        'meta_query'     => [
            [
                'key'     => '_gofundme_campaign_id',
                'compare' => 'NOT EXISTS',
            ],
        ],
        'no_found_rows'  => false, // Need total for progress bar
    ];

    $query = new WP_Query($query_args);

    // If interrupted, user can resume with --page=X
    if (!$query->have_posts()) {
        WP_CLI::success('No funds without campaigns found.');
        return;
    }

    // Process batch...
}
```
**Source:** [Igor Benić - WP CLI Batch Imports/Exports](https://www.ibenic.com/wp-cli-command-batch-imports-exports/)

### Pattern 4: Progress Bar for Long Operations
**What:** Visual progress indicator with time estimates
**When to use:** Any operation processing >10 items
**Example:**
```php
// Source: WP-CLI Official Documentation
$progress = \WP_CLI\Utils\make_progress_bar('Migrating campaigns', $query->found_posts);

while ($query->have_posts()) {
    $query->the_post();
    $post_id = get_the_ID();

    // Do migration work...

    $progress->tick();
}

$progress->finish();
```
**Source:** [WP-CLI make_progress_bar() Documentation](https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-utils-make-progress-bar/)

### Pattern 5: Success/Failure Tracking with Summary
**What:** Accumulate results during batch, report summary at end
**When to use:** Operations where individual failures shouldn't stop entire batch
**Example:**
```php
$successes = [];
$failures = [];

while ($query->have_posts()) {
    $query->the_post();
    $post_id = get_the_ID();

    try {
        // Attempt migration
        $result = $this->migrate_fund($post_id);
        if ($result['success']) {
            $successes[] = $post_id;
        } else {
            $failures[] = ['post_id' => $post_id, 'error' => $result['error']];
        }
    } catch (Exception $e) {
        $failures[] = ['post_id' => $post_id, 'error' => $e->getMessage()];
    }

    $progress->tick();
}

$progress->finish();

// Report summary
WP_CLI::success(sprintf('Migrated %d funds successfully.', count($successes)));
if (count($failures) > 0) {
    WP_CLI::warning(sprintf('%d funds failed:', count($failures)));
    foreach ($failures as $failure) {
        WP_CLI::log("  - Post {$failure['post_id']}: {$failure['error']}");
    }
}
```

### Pattern 6: Throttling Between API Calls
**What:** Add sleep delay between API operations to prevent rate limiting
**When to use:** External API calls where rate limits are unknown or conservative approach needed
**Example:**
```php
// Process in batches with throttling
$processed = 0;
while ($query->have_posts()) {
    $query->the_post();

    // Create campaign via API
    $result = $this->sync_handler->create_campaign_in_gfm(get_the_ID(), $data);

    $processed++;
    $progress->tick();

    // Sleep 1 second between requests (3600/hour max)
    // Adjust based on API rate limit testing
    if ($query->current_post + 1 < $query->post_count) {
        sleep(1); // 1 second between requests
    }
}
```
**Source:** [PHP sleep() Documentation](https://www.php.net/manual/en/function.sleep.php) and [Implementing API Throttling in PHP](https://dev.to/olutayo/implementing-api-throttling-in-my-php-project-35jm)

### Pattern 7: Command Synopsis with PHPDoc
**What:** Define command arguments via PHPDoc annotations for auto-validation
**When to use:** All WP-CLI commands
**Example:**
```php
/**
 * Migrate existing funds to GoFundMe Pro campaigns.
 *
 * Creates campaigns for all published funds that don't have a campaign_id.
 * Runs in dry-run mode by default - use --live to create actual campaigns.
 *
 * ## OPTIONS
 *
 * [--batch-size=<number>]
 * : Number of funds to process per batch.
 * ---
 * default: 50
 * ---
 *
 * [--page=<number>]
 * : Start at specific page (for resuming interrupted migration).
 * ---
 * default: 1
 * ---
 *
 * [--live]
 * : Actually create campaigns (default is dry-run).
 *
 * [--throttle=<seconds>]
 * : Seconds to sleep between API calls.
 * ---
 * default: 1
 * ---
 *
 * ## EXAMPLES
 *
 *     # Dry run to see what would be migrated
 *     wp fcg-gfm migrate-campaigns
 *
 *     # Actually create campaigns
 *     wp fcg-gfm migrate-campaigns --live
 *
 *     # Resume from page 5 after interruption
 *     wp fcg-gfm migrate-campaigns --live --page=5
 *
 * @when after_wp_load
 */
public function migrate_campaigns($args, $assoc_args) {
    // Implementation...
}
```
**Source:** [WP-CLI Commands Cookbook](https://make.wordpress.org/cli/handbook/guides/commands-cookbook/)

### Anti-Patterns to Avoid

- **Using offset instead of paged**: offset breaks WP_Query pagination calculations and doesn't work with found_posts
- **No dry-run mode**: Always default to safe mode for destructive operations
- **Silent failures**: Log every failure with context (post ID, error message)
- **No progress feedback**: Users assume hung process without progress bar
- **Unlimited queries**: Always paginate to prevent memory exhaustion
- **Using WP_CLI::error() in loops**: Stops entire batch on first failure; use WP_CLI::warning() and continue

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Progress visualization | Custom counter output | `WP_CLI\Utils\make_progress_bar()` | Handles time estimates, terminal width, disabled when piped |
| Command argument parsing | Manual $_SERVER parsing | PHPDoc synopsis + WP_CLI::add_command() | Auto-validates, generates help docs, handles defaults |
| Memory cleanup in loops | Manual cache_flush() calls | `WP_CLI::runcommand()` with `launch=true` | Spawns fresh process per batch, prevents accumulation |
| Pagination offset math | Manual offset = page * per_page | WP_Query with `paged` parameter | WordPress handles offset calculation and found_posts adjustment |
| Dry-run output formatting | Custom echo/printf statements | `WP_CLI::log()`, `WP_CLI::success()`, `WP_CLI::warning()` | Respects --quiet flag, consistent formatting, colored output |

**Key insight:** WP-CLI has evolved over 10+ years to handle edge cases (terminal width, piping, signal handling, memory limits). Using built-in utilities ensures compatibility across environments and avoids reinventing solutions to problems you haven't discovered yet.

## Common Pitfalls

### Pitfall 1: Using offset Parameter Breaks Pagination
**What goes wrong:** Developer sets `'offset' => 50` in WP_Query args and pagination shows incorrect total pages
**Why it happens:** offset parameter overrides WordPress's automatic offset calculation for paged queries, causing found_posts to be wrong
**How to avoid:** Use `'paged' => $page` instead of offset. WordPress calculates offset internally as `(paged - 1) * posts_per_page`
**Warning signs:**
- `$query->max_num_pages` always shows 1 even with many results
- Progress bar shows wrong total count
- Second page returns same results as first page

**Source:** [WordPress Codex - Custom Queries using Offset and Pagination](https://codex.wordpress.org/Making_Custom_Queries_using_Offset_and_Pagination)

### Pitfall 2: Memory Exhaustion on Large Datasets
**What goes wrong:** Migration starts fine but crashes after 200-300 items with "Allowed memory size exhausted"
**Why it happens:** WordPress object cache accumulates in memory. Each processed post adds cache entries that never get freed in long-running CLI processes
**How to avoid:**
- Set `'no_found_rows' => true` when you don't need pagination (disables expensive SQL_CALC_FOUND_ROWS)
- Call `wp_cache_flush()` every 50-100 items
- OR use `WP_CLI::runcommand()` with `'launch' => true` to spawn fresh processes per batch (reduces peak memory from 228MB to 130MB per WordPress VIP testing)
**Warning signs:**
- Memory usage grows linearly with each processed item
- Script slows down progressively
- Fatal error after processing 1/3 of total records

**Sources:**
- [WordPress VIP - Optimize Queries at Scale](https://docs.wpvip.com/databases/optimize-queries/optimize-core-queries-at-scale/)
- [Igor Benić - WP CLI Batch Processing](https://www.ibenic.com/wp-cli-command-batch-imports-exports/)

### Pitfall 3: API Rate Limiting Without Detection
**What goes wrong:** First 50 campaigns create fine, then API returns 429 or silent failures
**Why it happens:** Unknown API rate limits hit mid-migration. Classy API documentation doesn't publish specific rate limits
**How to avoid:**
- Add configurable `--throttle=<seconds>` argument (default: 1 second = 3600/hour max)
- Log API response times to detect slowdowns
- Implement exponential backoff on HTTP 429 responses
- Test load on sandbox environment first
**Warning signs:**
- Increasing API response times (200ms → 2s → 10s)
- HTTP 429 "Too Many Requests" errors
- API calls succeeding but responses show validation errors

**Recommendation:** Start conservative (2 second throttle), test 100-fund batch on staging, measure API response times, then optimize for production.

### Pitfall 4: Race Conditions on Concurrent Runs
**What goes wrong:** Two terminal windows run migration simultaneously, both create campaigns for same fund
**Why it happens:** No locking mechanism prevents concurrent execution
**How to avoid:**
- Check for transient lock at command start: `get_transient('fcg_gfm_migration_lock')`
- Set transient with reasonable TTL: `set_transient('fcg_gfm_migration_lock', time(), 7200)` (2 hours)
- Delete on completion or provide `--force` flag to override stale locks
- Note: Existing `create_campaign_in_gfm()` already has per-post 60s locks
**Warning signs:**
- Duplicate campaign_ids in database
- API errors about duplicate external_reference_id

### Pitfall 5: Opaque Failures Without Context
**What goes wrong:** User sees "Failed to create campaign" but no information about which fund or why
**Why it happens:** Logging errors without contextual data (post ID, post title, API response)
**How to avoid:**
```php
if (!$result['success']) {
    $error_msg = sprintf(
        'Post %d (%s): %s',
        $post_id,
        get_the_title($post_id),
        $result['error'] ?? 'Unknown error'
    );
    WP_CLI::warning($error_msg);
    $failures[] = ['post_id' => $post_id, 'title' => get_the_title(), 'error' => $result['error']];
}
```
**Warning signs:**
- Error logs with no post ID
- Unable to reproduce failures
- "It failed somewhere in the middle"

### Pitfall 6: Not Respecting ACF disable_campaign_sync Field
**What goes wrong:** Migration creates campaigns for funds explicitly marked as sync-disabled
**Why it happens:** Query doesn't filter out funds with `disable_campaign_sync = true`
**How to avoid:** Use existing `should_sync_campaign()` method from sync handler or replicate its logic:
```php
$query_args = [
    'post_type'      => 'funds',
    'posts_per_page' => $batch_size,
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'     => '_gofundme_campaign_id',
            'compare' => 'NOT EXISTS',
        ],
        [
            'relation' => 'OR',
            [
                'key'     => 'gofundme_settings_disable_campaign_sync',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => 'gofundme_settings_disable_campaign_sync',
                'value'   => '1',
                'compare' => '!=',
            ],
        ],
    ],
];
```
**Warning signs:**
- Campaign created for fund admin marked as "do not sync"
- More campaigns created than expected

## Code Examples

Verified patterns from official sources:

### WP_Query with Resume-Friendly Pagination
```php
// Source: WP_Query Official Documentation + WordPress VIP Best Practices
// URL: https://developer.wordpress.org/reference/classes/wp_query/

$batch_size = $assoc_args['batch-size'] ?? 50;
$page = $assoc_args['page'] ?? 1;

$query_args = [
    'post_type'      => 'funds',
    'post_status'    => 'publish',
    'posts_per_page' => $batch_size,
    'paged'          => $page,
    'meta_query'     => [
        [
            'key'     => '_gofundme_campaign_id',
            'compare' => 'NOT EXISTS', // Only funds without campaigns
        ],
    ],
    'no_found_rows'  => false, // Need total for progress bar (costs performance but needed)
    'orderby'        => 'ID',
    'order'          => 'ASC',
];

$query = new WP_Query($query_args);

WP_CLI::log(sprintf(
    'Found %d funds without campaigns (showing batch %d, page %d/%d)',
    $query->found_posts,
    $batch_size,
    $page,
    $query->max_num_pages
));
```

### Progress Bar with Error-Tolerant Loop
```php
// Source: WP-CLI make_progress_bar() Documentation
// URL: https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-utils-make-progress-bar/

$progress = \WP_CLI\Utils\make_progress_bar('Creating campaigns', $query->post_count);

$successes = [];
$failures = [];

while ($query->have_posts()) {
    $query->the_post();
    $post_id = get_the_ID();

    try {
        // Reuse existing create_campaign_in_gfm method
        $campaign_data = $this->sync_handler->build_campaign_data(get_post($post_id));
        $this->sync_handler->create_campaign_in_gfm($post_id, $campaign_data);

        // Verify campaign was created
        $campaign_id = get_post_meta($post_id, '_gofundme_campaign_id', true);
        if ($campaign_id) {
            $successes[] = $post_id;
        } else {
            $failures[] = ['post_id' => $post_id, 'error' => 'No campaign_id set after creation'];
        }

    } catch (Exception $e) {
        $failures[] = [
            'post_id' => $post_id,
            'title'   => get_the_title(),
            'error'   => $e->getMessage(),
        ];
    }

    $progress->tick();

    // Throttle to prevent API rate limiting
    if ($query->current_post + 1 < $query->post_count) {
        sleep($assoc_args['throttle'] ?? 1);
    }
}

$progress->finish();
```

### Dry-Run Pattern with Live Mode Opt-In
```php
// Source: WordPress VIP - Best Practices for WP-CLI Commands
// URL: https://docs.wpvip.com/vip-cli/wp-cli-with-vip-cli/wp-cli-commands-on-vip/

$dry_run = !isset($assoc_args['live']);

if ($dry_run) {
    WP_CLI::warning('DRY RUN mode - no campaigns will be created. Use --live to execute.');
}

while ($query->have_posts()) {
    $query->the_post();
    $post_id = get_the_ID();
    $post_title = get_the_title();

    if ($dry_run) {
        WP_CLI::log(sprintf(
            '[DRY RUN] Would create campaign for: %d - %s',
            $post_id,
            $post_title
        ));
        $progress->tick();
        continue;
    }

    // Live mode: actually create campaign
    // ... implementation ...
}
```

### Complete Command Class Structure
```php
// Source: WP-CLI Commands Cookbook
// URL: https://make.wordpress.org/cli/handbook/guides/commands-cookbook/

class FCG_GFM_Migration_Command {

    private $sync_handler;

    public function __construct() {
        $this->sync_handler = new FCG_GFM_Sync_Handler();
    }

    /**
     * Migrate existing funds to GoFundMe Pro campaigns.
     *
     * ## OPTIONS
     *
     * [--batch-size=<number>]
     * : Number of funds to process per batch.
     * ---
     * default: 50
     * ---
     *
     * [--page=<number>]
     * : Page number to start at (for resuming).
     * ---
     * default: 1
     * ---
     *
     * [--live]
     * : Execute migration (default is dry-run).
     *
     * [--throttle=<seconds>]
     * : Seconds to sleep between API calls.
     * ---
     * default: 1
     * ---
     *
     * ## EXAMPLES
     *
     *     wp fcg-gfm migrate-campaigns --live
     *
     * @when after_wp_load
     */
    public function migrate_campaigns($args, $assoc_args) {
        // Implementation using patterns above...
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| WP_CLI::launch_self() | WP_CLI::runcommand() | WP-CLI 2.0+ (2018) | runcommand() preserves environment, easier API, better error handling |
| Manual progress output | WP_CLI\Utils\make_progress_bar() | WP-CLI 1.0+ | Automatic terminal handling, time estimates, pipe detection |
| Offset-based pagination | paged parameter with WP_Query | WordPress 3.0+ best practice | Correct found_posts calculation, automatic offset math |
| Always calculate SQL_CALC_FOUND_ROWS | no_found_rows => true when not needed | WordPress 4.0+ performance | 20-40% faster queries on large tables when pagination not needed |

**Deprecated/outdated:**
- `WP_CLI::launch_self()`: Still works but `WP_CLI::runcommand()` is preferred (better env handling, easier syntax)
- Manual offset calculation: Use `paged` parameter, let WordPress calculate offset
- Direct wp_posts queries: Use WP_Query for proper filtering, caching, and plugin compatibility

## Open Questions

Things that couldn't be fully resolved:

1. **Classy API Rate Limits**
   - What we know: Documentation doesn't publish specific rate limits
   - What's unclear: Actual requests/minute or requests/hour allowed
   - Recommendation: Start with conservative 1-second throttle (3600/hour), test 100-fund batch on staging, monitor API response times and errors, then optimize. Add `--throttle` flag for easy adjustment without code changes.

2. **Optimal Batch Size**
   - What we know: 50 recommended in context, memory exhaustion possible on large batches
   - What's unclear: Whether 758 total funds could be done in single batch on WP Engine
   - Recommendation: Default to 50 per batch (proven safe), make configurable via `--batch-size` flag. WP Engine staging has 256MB PHP memory limit which should handle 50 without issue. If testing shows stability, could increase to 100.

3. **Template Campaign ID Discovery**
   - What we know: `get_option('fcg_gfm_template_campaign_id')` required for duplication
   - What's unclear: Should migration command validate this exists before starting?
   - Recommendation: Check template ID at command start, fail fast with clear error if not configured. Better than discovering mid-migration.

4. **Handling Partial Campaign Creation**
   - What we know: `create_campaign_in_gfm()` is multi-step (duplicate → update overview → publish → store meta)
   - What's unclear: What if campaign duplicates successfully but publish fails? Post won't have campaign_id but campaign exists in API.
   - Recommendation: Query exists with meta_query NOT EXISTS on campaign_id, which is idempotent. If duplicate fails mid-flight, post won't have meta, will retry next run. Existing 60s transient lock prevents double-creation. Log warnings for any campaign_id found but status != published.

5. **Funds Modified During Migration**
   - What we know: Migration may take 15-30 minutes for 758 funds (with 1s throttle)
   - What's unclear: Should migration handle case where admin edits fund while migration running?
   - Recommendation: Document migration as "off-hours" operation. Existing sync handler will update campaign on next save. No special handling needed - WordPress post locking doesn't apply to CLI operations.

## Sources

### Primary (HIGH confidence)
- [WP-CLI Official Commands Cookbook](https://make.wordpress.org/cli/handbook/guides/commands-cookbook/) - Command structure, synopsis, registration
- [WP-CLI make_progress_bar() API](https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-utils-make-progress-bar/) - Progress visualization
- [WP-CLI runcommand() API](https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-runcommand/) - Process spawning, memory optimization
- [WordPress VIP - Best Practices for WP-CLI Commands](https://docs.wpvip.com/vip-cli/wp-cli-with-vip-cli/wp-cli-commands-on-vip/) - Dry-run pattern, batching strategies
- [WordPress VIP - Commands at Scale](https://docs.wpvip.com/vip-cli/wp-cli-with-vip-cli/cli-commands-at-scale/) - Memory management, production considerations
- [WordPress Codex - WP_Query Offset and Pagination](https://codex.wordpress.org/Making_Custom_Queries_using_Offset_and_Pagination) - Pagination pitfalls

### Secondary (MEDIUM confidence)
- [Igor Benić - WP CLI Batch Processing](https://www.ibenic.com/wp-cli-command-batch-imports-exports/) - Batch processing patterns, step-based approach
- [WordPress VIP - Optimize Queries at Scale](https://docs.wpvip.com/databases/optimize-queries/optimize-core-queries-at-scale/) - no_found_rows performance impact
- [PHP sleep() Manual](https://www.php.net/manual/en/function.sleep.php) - Throttling implementation

### Tertiary (LOW confidence - marked for validation)
- Generic WP-CLI tutorials (WebDevStudios, etc.) - Reiterate official docs but less authoritative
- Stack Overflow discussions - Community patterns but need verification

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All recommendations from official WP-CLI and WordPress documentation
- Architecture: HIGH - Patterns verified in WordPress VIP production documentation and official handbooks
- Pitfalls: HIGH - Documented in WordPress Codex, WordPress VIP, and WP-CLI official troubleshooting guides
- Throttling: MEDIUM - PHP sleep() standard but Classy API rate limits unknown (requires testing)
- Batch size: MEDIUM - 50 is recommended practice but optimal size for this specific case requires staging testing

**Research date:** 2026-01-26
**Valid until:** Approximately 90 days (2026-04-26) - WP-CLI is mature/stable, patterns unlikely to change
