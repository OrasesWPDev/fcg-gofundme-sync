# Phase 5: Code Cleanup - Research

**Researched:** 2026-01-28
**Domain:** WordPress plugin code cleanup, dead code removal, testing strategies
**Confidence:** HIGH

## Summary

Phase 5 removes obsolete campaign duplication and status management code that became dead code after the architecture pivot to a single master campaign. The research identified all campaign-related methods, hooks, and post meta that must be removed while preserving critical designation sync functionality, OAuth2 infrastructure, and inbound sync polling.

**Key findings:**
- 15 campaign-related methods identified for removal across 3 files
- 4 WordPress hooks exclusively used for campaign sync must be removed
- 3 post meta keys for campaign data (`_gofundme_campaign_id`, `_gofundme_campaign_url`, `_gofundme_campaign_status`) are orphaned
- Designation sync logic is completely isolated from campaign code - clean separation boundary exists
- No existing test infrastructure - will need to create PHPUnit tests with mocked API
- Orphaned post meta should be cleaned up but won't break functionality if left

**Primary recommendation:** Remove all campaign methods and hooks in sync-handler and API client, preserve all campaign methods in API client that may be useful for future features, add PHPUnit tests for designation sync, clean up orphaned post meta with WP-CLI command.

## Standard Stack

### Core (Already in Use)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress | 5.8+ | Platform | Plugin environment |
| PHP | 7.4+ | Language | WordPress requirement |
| WP_Http | Core | API requests | Native WordPress HTTP API |
| WP_Cron | Core | Background tasks | Native WordPress scheduling |
| WP-CLI | Latest | Command line | WordPress standard for bulk operations |

### Testing Stack (New)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHPUnit | 9.x | Test framework | WordPress compatible version for PHP 7.4+ |
| WP_Mock | 1.x | WordPress mocking | Unit tests without WordPress bootstrap |
| BrainMonkey | 2.x | Function mocking | Alternative to WP_Mock, used by Yoast |

### Supporting Tools
| Tool | Version | Purpose | When to Use |
|------|---------|---------|-------------|
| WP-Optimize | Latest | Database cleanup | Remove orphaned post meta (alternative to custom WP-CLI) |
| Advanced Database Cleaner | Latest | Orphan detection | Find orphaned meta if custom approach fails |

**Installation (Testing):**
```bash
composer require --dev phpunit/phpunit:^9.0 10up/wp_mock brain/monkey
```

**Note:** Current plugin has NO test infrastructure. Testing is optional per user discretion, but recommended for verification.

## Architecture Patterns

### Recommended Cleanup Process
```
1. Audit Phase
   - Map all campaign code references
   - Verify designation code boundaries
   - Identify orphaned data

2. Removal Phase
   - Remove campaign methods from sync-handler
   - Remove campaign hooks
   - Preserve API client campaign methods (may be useful later)
   - Update constants/documentation

3. Verification Phase
   - PHPUnit tests for designation sync
   - Deploy to staging
   - Manual end-to-end test
   - Verify inbound polling still works

4. Cleanup Phase
   - Remove orphaned post meta (WP-CLI)
   - Update CLAUDE.md
   - Bump plugin version
```

### Pattern 1: WordPress Hook Removal
**What:** Remove hooks that are no longer called after dead code removal
**When to use:** When removing methods that are registered as WordPress action/filter callbacks

**Current hooks to remove:**
```php
// In FCG_GFM_Sync_Handler::__construct() - KEEP these (used by designations):
add_action('save_post_funds', [$this, 'on_save_fund'], 20, 3);  // KEEP
add_action('wp_trash_post', [$this, 'on_trash_fund']);          // KEEP
add_action('untrash_post', [$this, 'on_untrash_fund']);         // KEEP
add_action('before_delete_post', [$this, 'on_delete_fund']);    // KEEP
add_action('transition_post_status', [$this, 'on_status_change'], 10, 3); // KEEP

// No campaign-specific hooks to remove - campaign sync is called from designation hooks
```

**Important:** All existing hooks are DUAL-PURPOSE (handle both designations and campaigns). After removing campaign code from within these hook callbacks, the hooks themselves remain needed for designation sync.

### Pattern 2: Post Meta Cleanup
**What:** Remove orphaned post meta from deleted code features
**When to use:** After removing code that writes to wp_postmeta

**Orphaned meta keys:**
- `_gofundme_campaign_id` - Campaign ID stored per fund
- `_gofundme_campaign_url` - Campaign URL stored per fund
- `_gofundme_campaign_status` - Campaign status from inbound sync

**WP-CLI cleanup command pattern:**
```php
/**
 * Clean up orphaned campaign meta
 *
 * ## EXAMPLES
 *
 *     wp fcg-cleanup campaign-meta
 *     wp fcg-cleanup campaign-meta --dry-run
 */
public function cli_cleanup_campaign_meta(array $args, array $assoc_args): void {
    $dry_run = isset($assoc_args['dry-run']);

    $meta_keys = [
        '_gofundme_campaign_id',
        '_gofundme_campaign_url',
        '_gofundme_campaign_status',
    ];

    $posts = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => -1,
        'post_status' => 'any',
    ]);

    $stats = ['posts' => 0, 'keys_removed' => 0];

    foreach ($posts as $post) {
        $removed_for_post = false;
        foreach ($meta_keys as $key) {
            if (get_post_meta($post->ID, $key, true)) {
                if (!$dry_run) {
                    delete_post_meta($post->ID, $key);
                }
                $stats['keys_removed']++;
                $removed_for_post = true;
            }
        }
        if ($removed_for_post) {
            $stats['posts']++;
        }
    }

    \WP_CLI::success(sprintf(
        '%s %d orphaned meta keys from %d posts',
        $dry_run ? 'Would remove' : 'Removed',
        $stats['keys_removed'],
        $stats['posts']
    ));
}
```

### Pattern 3: PHPUnit Test for Designation Sync
**What:** Unit test that verifies designation sync without WordPress bootstrap
**When to use:** After removing campaign code to verify designation functionality intact

**Example test structure:**
```php
use WP_Mock\Tools\TestCase;

class DesignationSyncTest extends TestCase {

    public function setUp(): void {
        \WP_Mock::setUp();
    }

    public function tearDown(): void {
        \WP_Mock::tearDown();
    }

    /**
     * Test designation creation on publish
     */
    public function test_designation_created_on_publish() {
        // Mock API client
        $api_mock = \Mockery::mock('FCG_GFM_API_Client');
        $api_mock->shouldReceive('create_designation')
            ->once()
            ->with(\Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'data' => ['id' => 12345]
            ]);

        // Mock WordPress functions
        \WP_Mock::userFunction('get_post_meta', [
            'return' => false // No existing designation
        ]);

        \WP_Mock::userFunction('update_post_meta', [
            'times' => 2 // designation_id + last_sync
        ]);

        // Test sync handler
        $handler = new FCG_GFM_Sync_Handler();
        $post = (object) [
            'ID' => 1,
            'post_type' => 'funds',
            'post_status' => 'publish',
            'post_title' => 'Test Fund',
        ];

        $handler->on_save_fund(1, $post, false);

        // Assertions handled by Mockery expectations
        $this->assertTrue(true);
    }
}
```

**Source:** Based on [WP_Mock testing framework](https://github.com/10up/wp_mock) and [WordPress plugin testing guide](https://developer.wordpress.org/news/2025/12/how-to-add-automated-unit-tests-to-your-wordpress-plugin/)

### Anti-Patterns to Avoid
- **Don't remove API client methods prematurely:** Campaign methods like `get_campaign_overview()` are used by inbound sync for donation totals. Keep all API client methods.
- **Don't remove hooks without verification:** All hooks are dual-purpose. Only remove hook CALLBACKS, not the hook registrations.
- **Don't delete orphaned meta without backup:** Always run with `--dry-run` first to verify what will be deleted.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Orphaned meta detection | Custom SQL queries | WP-CLI `wp post meta list` or WP-Optimize plugin | WordPress core handles meta relationships correctly |
| Database cleanup | Direct SQL DELETE | `delete_post_meta()` WP function | Triggers hooks, maintains cache consistency |
| Test mocking | Custom mock classes | WP_Mock or BrainMonkey | Standard tools, community support, maintained |
| Backup before cleanup | Custom backup script | WP Engine backup or UpdraftPlus | Automated, tested, restorable |

**Key insight:** WordPress post meta cleanup looks simple but has cache invalidation, hook triggering, and multisite considerations. Use WordPress APIs or established tools.

## Common Pitfalls

### Pitfall 1: Removing API Client Campaign Methods
**What goes wrong:** Admin UI and inbound sync poller use `get_campaign()` and `get_campaign_overview()` for donation totals and status.
**Why it happens:** Grepping for "campaign" finds API methods, assumption is "all campaign code is dead."
**How to avoid:**
- Analyze CALLERS of each method before removal
- Keep ALL API client methods (they're thin wrappers, low maintenance cost)
- Only remove campaign methods from sync-handler (the orchestration logic)
**Warning signs:**
- Admin UI shows "Unknown" for donation totals
- Inbound sync fails with "Call to undefined method"
- WP-CLI `fcg-sync pull` errors

### Pitfall 2: Breaking Inbound Sync Campaign Polling
**What goes wrong:** `poll_campaigns()` method in sync-poller fetches donation data using campaign IDs. If you remove campaign meta cleanup, you break polling for existing funds.
**Why it happens:** Orphaned campaign IDs in post meta cause polling logic to attempt API calls that return 404.
**How to avoid:**
- Run post meta cleanup BEFORE deploying code changes
- Add null checks in `poll_campaigns()` for missing campaign IDs
- Update `poll_campaigns()` to skip funds without campaign IDs gracefully
**Warning signs:**
- WP error log shows "Campaign {id} not found (404)"
- Donation totals stop updating after cleanup

### Pitfall 3: Dual-Purpose Hook Confusion
**What goes wrong:** Developer removes WordPress hook registration thinking "this is for campaigns" but it also handles designations.
**Why it happens:** All hooks in sync-handler are dual-purpose - they call BOTH designation AND campaign sync methods.
**How to avoid:**
- Read hook callback method completely before removing hook
- Only remove campaign method CALLS from within callbacks
- Never remove `add_action()` registrations for these hooks:
  - `save_post_funds`
  - `wp_trash_post`
  - `untrash_post`
  - `before_delete_post`
  - `transition_post_status`
**Warning signs:**
- Designations stop syncing after code cleanup
- "Designation sync stopped working" reports from staging

### Pitfall 4: Test False Positives with Mocked WordPress
**What goes wrong:** PHPUnit test passes but real WordPress environment fails because mock doesn't match actual behavior.
**Why it happens:** WP_Mock/BrainMonkey require explicit expectation setup - if you don't mock a function WordPress calls, test silently ignores it.
**How to avoid:**
- Always do BOTH automated (PHPUnit) and manual (staging) testing
- Mock every WordPress function the code calls (use `WP_Mock::userFunction()` liberally)
- Check WP error logs in staging for unexpected function calls
**Warning signs:**
- Tests green but staging deployment breaks
- "Call to undefined function" errors in staging

### Pitfall 5: ACF Field Conflicts with Orphaned Meta
**What goes wrong:** ACF (Advanced Custom Fields) may use `_gofundme_campaign_id` meta key, cleanup deletes it, ACF breaks.
**Why it happens:** Meta key naming collision - both plugin and ACF could use same key if manually configured.
**How to avoid:**
- Audit ACF field groups for `gofundme_` prefixed fields before cleanup
- Check ACF field storage locations (post_meta vs options)
- Run cleanup with `--dry-run` first to see what would be deleted
**Warning signs:**
- ACF fields show empty values after cleanup
- Admin UI fundraising goal field stops working

## Code Examples

Verified patterns from codebase audit:

### Campaign Code to Remove (Sync Handler)
```php
// Source: includes/class-sync-handler.php lines 134, 579-595
// REMOVE: sync_campaign_to_gofundme() method and its call

// Line 134 - REMOVE this call:
$this->sync_campaign_to_gofundme($post_id, $post);

// Lines 579-595 - REMOVE entire method:
private function sync_campaign_to_gofundme(int $post_id, WP_Post $post): void {
    if (!$this->should_sync_campaign($post_id)) {
        return;
    }
    $campaign_data = $this->build_campaign_data($post);
    $campaign_id = $this->get_campaign_id($post_id);
    if ($campaign_id) {
        $this->update_campaign_in_gfm($post_id, $campaign_id, $campaign_data);
    } else {
        if ($post->post_status === 'publish') {
            $this->create_campaign_in_gfm($post_id, $campaign_data);
        }
    }
}

// ALSO REMOVE supporting methods:
// - create_campaign_in_gfm() (lines 479-552)
// - update_campaign_in_gfm() (lines 561-571)
// - build_campaign_data() (lines 426-446)
// - get_campaign_id() (lines 603-606)
// - get_campaign_url() (lines 614-617)
// - should_sync_campaign() (lines 625-639)
// - ensure_campaign_active() (lines 342-382)
```

### Campaign Code in Hook Callbacks to Remove
```php
// Source: includes/class-sync-handler.php

// In on_trash_fund() lines 165-171 - REMOVE:
$campaign_id = $this->get_campaign_id($post_id);
if ($campaign_id) {
    $result = $this->api->deactivate_campaign($campaign_id);
    if ($result['success']) {
        $this->log_info("Deactivated campaign {$campaign_id} for trashed post {$post_id}");
    }
}

// In on_untrash_fund() lines 202-228 - REMOVE:
$campaign_id = $this->get_campaign_id($post_id);
if ($campaign_id && $this->should_sync_campaign($post_id)) {
    $reactivate_result = $this->api->reactivate_campaign($campaign_id);
    // ... rest of campaign reactivation logic
}

// In on_delete_fund() lines 257-263 - REMOVE:
$campaign_id = $this->get_campaign_id($post_id);
if ($campaign_id) {
    $result = $this->api->deactivate_campaign($campaign_id);
    // ...
}

// In on_status_change() lines 303-329 - REMOVE:
$campaign_id = $this->get_campaign_id($post->ID);
if ($campaign_id && $this->should_sync_campaign($post->ID)) {
    // ... all campaign status sync logic
}
```

### Constants to Remove
```php
// Source: includes/class-sync-handler.php lines 34-39

// REMOVE these constants:
private const META_CAMPAIGN_ID = '_gofundme_campaign_id';
private const META_CAMPAIGN_URL = '_gofundme_campaign_url';

// KEEP these (used by designations):
private const META_KEY_DESIGNATION_ID = '_gofundme_designation_id';
private const META_KEY_LAST_SYNC = '_gofundme_last_sync';
```

### API Client Methods - KEEP
```php
// Source: includes/class-api-client.php
// DO NOT REMOVE these - used by inbound sync and admin UI:

public function create_campaign(array $data): array          // May be useful for master campaign setup
public function update_campaign($campaign_id, array $data): array  // USED by architecture pivot
public function get_campaign($campaign_id): array            // USED by admin UI validation
public function get_campaign_overview($campaign_id): array   // USED by inbound sync polling
public function get_all_campaigns(int $per_page = 100): array  // Useful for debugging
public function deactivate_campaign($campaign_id): array     // Can remove (no longer used)
public function duplicate_campaign($source_campaign_id, array $overrides = []): array  // Can remove
public function publish_campaign($campaign_id): array        // Can remove
public function unpublish_campaign($campaign_id): array      // Can remove
public function reactivate_campaign($campaign_id): array     // Can remove
```

**Decision:** KEEP all campaign methods in API client. They're thin wrappers (5-10 lines each), low maintenance burden, may be useful for debugging or future master campaign features. Only remove methods used by sync orchestration (duplicate, publish, unpublish, reactivate, deactivate).

### Inbound Sync Campaign Polling - UPDATE
```php
// Source: includes/class-sync-poller.php lines 202-251
// This code MUST remain - it fetches donation totals per campaign

private function poll_campaigns(): void {
    // Find all funds with campaign IDs
    $funds_with_campaigns = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => -1,
        'post_status' => ['publish', 'draft', 'pending'],
        'meta_query' => [
            [
                'key' => '_gofundme_campaign_id',  // This meta will be orphaned
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    // ... rest of method uses get_campaign() and get_campaign_overview()
}

// MODIFY: Add graceful handling for missing campaign IDs after cleanup:
private function poll_campaigns(): void {
    $funds_with_campaigns = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => -1,
        'post_status' => ['publish', 'draft', 'pending'],
        'meta_query' => [
            [
                'key' => '_gofundme_campaign_id',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    if (empty($funds_with_campaigns)) {
        $this->log("Campaign poll: No funds with campaigns found");
        return;  // Graceful exit after cleanup removes all campaign IDs
    }

    // ... continue with polling
}
```

**Important:** After architecture pivot, this entire method becomes obsolete (Phase 6 will poll master campaign by designation). For Phase 5, just add null check to prevent errors.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Per-fund campaigns | Single master campaign with designations | 2026-01-28 (architecture pivot) | Phases 2-3 code became dead code |
| Manual testing only | PHPUnit + WP_Mock for plugin testing | WordPress Developer Blog Dec 2025 | Automated regression detection |
| Direct SQL for meta cleanup | WP-CLI commands or plugins | WordPress 5.x+ best practices | Cache consistency, hook triggering |
| Global `delete_post_meta()` | Scoped to post type with dry-run | 2024+ database safety patterns | Prevents accidental deletion |

**Deprecated/outdated:**
- `duplicate_campaign()` workflow: Old approach duplicated template campaign per fund - replaced by linking designations to single master campaign
- Campaign status management: Unpublish/deactivate/reactivate campaign per fund - replaced by designation `is_active` flag only
- Per-fund campaign URLs: Each fund had unique campaign URL - replaced by master campaign URL + `?designation={id}` parameter

## Open Questions

### Question 1: Should orphaned campaign meta be cleaned immediately or deferred?

**What we know:**
- Orphaned meta doesn't break functionality (just unused data)
- ~861 funds × 3 meta keys = ~2,583 orphaned records
- WP database size impact is minimal (<10KB typically)
- Post meta cleanup is reversible only via backup

**What's unclear:**
- Will future features need historical campaign ID mapping?
- Should cleanup happen before or after Phase 6 (master campaign linking)?

**Recommendation:**
- Create WP-CLI cleanup command in Phase 5
- Document but DON'T execute cleanup until after Phase 6 complete
- User can run manually if database bloat becomes issue
- Provides rollback capability if architecture pivot needs reversal

### Question 2: PHPUnit test coverage scope

**What we know:**
- No existing test infrastructure
- Designation sync has 5 lifecycle actions (publish, update, trash, restore, delete)
- Manual testing on staging is REQUIRED per CONTEXT.md

**What's unclear:**
- Is PHPUnit test setup worth time investment for one-time cleanup?
- Which lifecycle actions are most critical to test automatically?

**Recommendation:**
- Create minimal PHPUnit setup (1-2 hours)
- Test ONLY critical path: designation create on publish
- All other lifecycle actions tested manually on staging
- PHPUnit infrastructure available for future development

### Question 3: API client campaign method retention strategy

**What we know:**
- 10 campaign methods exist in API client
- 5 methods used by dead code (duplicate, publish, unpublish, reactivate, deactivate)
- 3 methods used by active code (get_campaign, get_campaign_overview, update_campaign)
- 2 methods potentially useful (create_campaign, get_all_campaigns)

**What's unclear:**
- Will master campaign setup (Phase 6) need create_campaign()?
- Are unused methods a maintenance burden or useful debugging tools?

**Recommendation:**
- REMOVE: duplicate_campaign, publish_campaign, unpublish_campaign, reactivate_campaign, deactivate_campaign (clear dead code)
- KEEP: create_campaign, update_campaign, get_campaign, get_campaign_overview, get_all_campaigns (minimal burden, potential utility)
- Document which methods are "for debugging only" vs "actively used"

## Sources

### Primary (HIGH confidence)
- Codebase audit: `/Users/chadmacbook/projects/fcg/includes/` (all 4 PHP files read and analyzed)
- Architecture pivot document: `.planning/ARCHITECTURE-PIVOT-2026-01-28.md` (defines dead code scope)
- Phase context: `.planning/phases/05-code-cleanup/05-CONTEXT.md` (user decisions)

### Secondary (MEDIUM confidence)
- [WordPress Developer Blog: PHPUnit Testing Guide](https://developer.wordpress.org/news/2025/12/how-to-add-automated-unit-tests-to-your-wordpress-plugin/) - PHPUnit setup for WordPress plugins (Dec 2025)
- [WP_Mock GitHub](https://github.com/10up/wp_mock) - Official API mocking framework maintained by 10up
- [BrainMonkey GitHub](https://github.com/Brain-WP/BrainMonkey) - Alternative mocking framework used by Yoast
- [Delicious Brains: Unit Testing Ajax and API Requests](https://deliciousbrains.com/unit-testing-ajax-api-requests-wordpress-plugins/) - Mocking HTTP requests pattern

### Tertiary (LOW confidence - community practices)
- [SigmaPlugin: WordPress Orphan Post Meta Cleanup](https://sigmaplugin.com/blog/what-are-wordpress-orphan-posts-meta-and-how-to-clean-them/) - Orphaned meta definition and risks
- [InMotion Hosting: Clean Old Metadata](https://www.inmotionhosting.com/support/edu/wordpress/cleaning-up-old-post-meta-data-in-wordpress/) - Manual cleanup approaches
- [Human Made: Orphan Command (WP-CLI)](https://github.com/humanmade/orphan-command) - WP-CLI tool for orphan detection
- [Servebolt: WordPress Cleanup Best Practices](https://servebolt.com/articles/wordpress-cleanup-checklist/) - General cleanup guidelines

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All tools are in active use or WordPress core
- Architecture: HIGH - Codebase fully audited, all campaign code locations mapped
- Pitfalls: HIGH - Based on direct code analysis and WordPress plugin development patterns
- Testing approach: MEDIUM - PHPUnit patterns verified but no WordPress-specific testing currently exists in codebase

**Research date:** 2026-01-28
**Valid until:** 60 days (stable WordPress APIs, cleanup patterns don't change rapidly)

**Code location summary:**
- Campaign code to remove: `includes/class-sync-handler.php` (9 methods, ~400 lines)
- Campaign hooks: All hooks in sync-handler constructor are dual-purpose - keep hooks, remove campaign calls from callbacks
- Orphaned meta: 3 keys across ~861 posts = ~2,583 records
- API methods: Keep all campaign methods in `includes/class-api-client.php`, remove only dead campaign lifecycle methods
- Inbound sync: `includes/class-sync-poller.php` poll_campaigns() method must be updated for graceful degradation
