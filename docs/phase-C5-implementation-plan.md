# Phase C5: WP-CLI Commands for Campaigns - Implementation Plan

**PRD Reference:** `/Users/chadmacbook/projects/fcg/docs/PRD-campaigns.md` (Phase C5)
**Goal:** Add command-line tools for campaign management
**Version:** 2.4.0
**Branch:** `feature/phase-C5-campaign-wpcli`
**Depends On:** Phase C4 (Admin UI for Campaigns)

---

## Overview

Add WP-CLI commands for campaign management to `FCG_GFM_Sync_Poller`. This enables administrators to manage campaign sync via command line, useful for bulk operations and debugging.

**New Commands:**
- `wp fcg-sync campaigns` - List all funds with campaign status
- `wp fcg-sync campaign-create` - Create campaigns for funds that don't have one
- `wp fcg-sync campaign-pull` - Pull campaign updates from GoFundMe Pro

---

## Substeps Overview

| Step | Description | Files |
|------|-------------|-------|
| C5.1 | Add `cli_campaigns` command | `class-sync-poller.php` |
| C5.2 | Add `cli_campaign_create` command | `class-sync-poller.php` |
| C5.3 | Add `cli_campaign_pull` command | `class-sync-poller.php` |
| C5.4 | Register new CLI commands | `class-sync-poller.php` |
| C5.5 | Add helper method for campaign data building | `class-sync-poller.php` |
| C5.6 | Update plugin version to 2.4.0 | `fcg-gofundme-sync.php` |

---

## Step C5.1: Add `cli_campaigns` Command

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * List all funds with campaign status.
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : Output format. Options: table, csv, json. Default: table.
 *
 * [--status=<status>]
 * : Filter by sync status. Options: synced, pending, not-linked, all. Default: all.
 *
 * ## EXAMPLES
 *
 *     wp fcg-sync campaigns
 *     wp fcg-sync campaigns --format=csv
 *     wp fcg-sync campaigns --status=not-linked
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Associative arguments.
 */
public function cli_campaigns(array $args, array $assoc_args): void {
    $format = $assoc_args['format'] ?? 'table';
    $status_filter = $assoc_args['status'] ?? 'all';

    $posts = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => -1,
        'post_status' => 'any',
    ]);

    if (empty($posts)) {
        \WP_CLI::warning('No funds found');
        return;
    }

    $table = [];
    foreach ($posts as $post) {
        $campaign_id = get_post_meta($post->ID, '_gofundme_campaign_id', true);
        $campaign_url = get_post_meta($post->ID, '_gofundme_campaign_url', true);
        $designation_id = get_post_meta($post->ID, '_gofundme_designation_id', true);
        $last_sync = get_post_meta($post->ID, '_gofundme_last_sync', true);

        // Determine status
        $status = 'Not Linked';
        if ($campaign_id) {
            if ($last_sync) {
                $last_sync_time = strtotime($last_sync);
                $fifteen_min_ago = time() - (15 * 60);
                $status = ($last_sync_time > $fifteen_min_ago) ? 'Synced' : 'Pending';
            } else {
                $status = 'Pending';
            }
        }

        // Apply status filter
        if ($status_filter !== 'all') {
            $filter_map = [
                'synced' => 'Synced',
                'pending' => 'Pending',
                'not-linked' => 'Not Linked',
            ];
            if (isset($filter_map[$status_filter]) && $status !== $filter_map[$status_filter]) {
                continue;
            }
        }

        $table[] = [
            'ID' => $post->ID,
            'Title' => mb_substr($post->post_title, 0, 35),
            'Post Status' => $post->post_status,
            'Campaign ID' => $campaign_id ?: '-',
            'Campaign URL' => $campaign_url ? mb_substr($campaign_url, 0, 40) . '...' : '-',
            'Designation ID' => $designation_id ?: '-',
            'Sync Status' => $status,
            'Last Sync' => $last_sync ?: 'never',
        ];
    }

    if (empty($table)) {
        \WP_CLI::warning('No funds match the filter criteria');
        return;
    }

    \WP_CLI\Utils\format_items($format, $table, array_keys($table[0]));

    // Summary
    \WP_CLI::log('');
    \WP_CLI::log(sprintf('Total: %d funds displayed', count($table)));
}
```

---

## Step C5.2: Add `cli_campaign_create` Command

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * Create campaigns for funds that don't have one.
 *
 * ## OPTIONS
 *
 * [--dry-run]
 * : Show what would be created without making changes.
 *
 * [--limit=<number>]
 * : Maximum number of campaigns to create. Default: all.
 *
 * [--post-id=<id>]
 * : Create campaign for a specific post ID only.
 *
 * ## EXAMPLES
 *
 *     wp fcg-sync campaign-create
 *     wp fcg-sync campaign-create --dry-run
 *     wp fcg-sync campaign-create --limit=10
 *     wp fcg-sync campaign-create --post-id=123
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Associative arguments.
 */
public function cli_campaign_create(array $args, array $assoc_args): void {
    $dry_run = isset($assoc_args['dry-run']);
    $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : -1;
    $post_id = isset($assoc_args['post-id']) ? (int) $assoc_args['post-id'] : 0;

    if ($dry_run) {
        \WP_CLI::log('Dry run mode - no campaigns will be created');
    }

    // Build query for funds without campaigns
    $query_args = [
        'post_type' => 'funds',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => '_gofundme_campaign_id',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_gofundme_campaign_id',
                'value' => '',
                'compare' => '=',
            ],
        ],
    ];

    if ($post_id) {
        $query_args = [
            'post_type' => 'funds',
            'p' => $post_id,
            'post_status' => 'any',
        ];
    }

    $posts = get_posts($query_args);

    if (empty($posts)) {
        \WP_CLI::success('No funds need campaigns');
        return;
    }

    \WP_CLI::log(sprintf('Found %d fund(s) without campaigns', count($posts)));

    $stats = [
        'processed' => 0,
        'created' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    $progress = \WP_CLI\Utils\make_progress_bar('Creating campaigns', count($posts));

    foreach ($posts as $post) {
        $stats['processed']++;

        // Skip if already has campaign (for --post-id case)
        $existing = get_post_meta($post->ID, '_gofundme_campaign_id', true);
        if ($existing) {
            \WP_CLI::log(sprintf('  [SKIP] Post %d already has campaign %s', $post->ID, $existing));
            $stats['skipped']++;
            $progress->tick();
            continue;
        }

        // Build campaign data
        $data = $this->build_campaign_data_from_post($post);

        if ($dry_run) {
            \WP_CLI::log(sprintf(
                '  [WOULD CREATE] Post %d: %s (goal: $%s)',
                $post->ID,
                mb_substr($post->post_title, 0, 40),
                number_format($data['goal'], 2)
            ));
            $stats['created']++;
        } else {
            $result = $this->api->create_campaign($data);

            if ($result['success'] && !empty($result['data']['id'])) {
                $campaign_id = $result['data']['id'];
                $campaign_url = $result['data']['canonical_url'] ?? '';

                update_post_meta($post->ID, '_gofundme_campaign_id', $campaign_id);
                update_post_meta($post->ID, '_gofundme_campaign_url', $campaign_url);
                update_post_meta($post->ID, '_gofundme_last_sync', current_time('mysql'));
                update_post_meta($post->ID, '_gofundme_sync_source', 'wordpress');

                \WP_CLI::log(sprintf(
                    '  [CREATED] Post %d: Campaign %s',
                    $post->ID,
                    $campaign_id
                ));
                $stats['created']++;
            } else {
                \WP_CLI::warning(sprintf(
                    '  [ERROR] Post %d: %s - %s',
                    $post->ID,
                    $post->post_title,
                    $result['error'] ?? 'Unknown error'
                ));
                $stats['errors']++;
            }
        }

        $progress->tick();
    }

    $progress->finish();

    \WP_CLI::log('');
    \WP_CLI::log(sprintf(
        'Results: %d processed, %d created, %d skipped, %d errors',
        $stats['processed'],
        $stats['created'],
        $stats['skipped'],
        $stats['errors']
    ));

    if ($stats['errors'] === 0) {
        \WP_CLI::success('Campaign creation complete!');
    } elseif ($stats['created'] > 0) {
        \WP_CLI::warning('Campaign creation complete with some errors');
    } else {
        \WP_CLI::error('Campaign creation failed');
    }
}
```

---

## Step C5.3: Add `cli_campaign_pull` Command

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * Pull campaign updates from GoFundMe Pro.
 *
 * ## OPTIONS
 *
 * [--dry-run]
 * : Show what would be updated without making changes.
 *
 * ## EXAMPLES
 *
 *     wp fcg-sync campaign-pull
 *     wp fcg-sync campaign-pull --dry-run
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Associative arguments.
 */
public function cli_campaign_pull(array $args, array $assoc_args): void {
    $dry_run = isset($assoc_args['dry-run']);

    if ($dry_run) {
        \WP_CLI::log('Dry run mode - no changes will be made');
    }

    \WP_CLI::log('Fetching campaigns from GoFundMe Pro...');

    $result = $this->api->get_all_campaigns();

    if (!$result['success']) {
        \WP_CLI::error("API Error: {$result['error']}");
        return;
    }

    $campaigns = $result['data'];
    \WP_CLI::success(sprintf('Fetched %d campaigns', count($campaigns)));

    $stats = ['processed' => 0, 'updated' => 0, 'skipped' => 0, 'orphaned' => 0, 'conflicts' => 0];

    foreach ($campaigns as $campaign) {
        $stats['processed']++;

        $post_id = $this->find_post_for_campaign($campaign);

        if (!$post_id) {
            \WP_CLI::log(sprintf(
                '  [ORPHAN] %s (ID: %s) - no matching WP post',
                $campaign['name'],
                $campaign['id']
            ));
            $stats['orphaned']++;
            continue;
        }

        $post = get_post($post_id);
        $changed = $this->has_campaign_changed($post_id, $campaign);
        $should_apply = $changed ? $this->should_apply_campaign_changes($post_id, $campaign) : false;

        if (!$changed) {
            \WP_CLI::log(sprintf(
                '  [SKIP] %s (Post %d) - no changes',
                $campaign['name'],
                $post_id
            ));
            $stats['skipped']++;
            continue;
        }

        if (!$should_apply) {
            \WP_CLI::log(sprintf(
                '  [CONFLICT] %s (Post %d) - WP modified after last sync, keeping WP version',
                $campaign['name'],
                $post_id
            ));
            $stats['conflicts']++;
            $stats['skipped']++;
            continue;
        }

        if ($dry_run) {
            \WP_CLI::log(sprintf(
                '  [WOULD UPDATE] %s (Post %d)',
                $campaign['name'],
                $post_id
            ));
            $stats['updated']++;
        } else {
            try {
                $this->apply_campaign_to_post($post_id, $campaign);
                \WP_CLI::log(sprintf(
                    '  [UPDATED] %s (Post %d)',
                    $campaign['name'],
                    $post_id
                ));
                $stats['updated']++;
            } catch (Exception $e) {
                \WP_CLI::warning(sprintf(
                    '  [ERROR] %s (Post %d) - %s',
                    $campaign['name'],
                    $post_id,
                    $e->getMessage()
                ));
            }
        }
    }

    \WP_CLI::log('');
    \WP_CLI::log(sprintf(
        'Results: %d processed, %d updated, %d skipped, %d orphaned, %d conflicts',
        $stats['processed'],
        $stats['updated'],
        $stats['skipped'],
        $stats['orphaned'],
        $stats['conflicts']
    ));

    if (!$dry_run) {
        $this->set_last_poll_time();
        \WP_CLI::success('Campaign pull complete');
    }
}
```

---

## Step C5.4: Register New CLI Commands

**File:** `includes/class-sync-poller.php`

**Modify constructor to add new commands:**

```php
// In constructor, add after existing WP-CLI command registrations:

if (defined('WP_CLI') && WP_CLI) {
    // Existing commands
    \WP_CLI::add_command('fcg-sync pull', [$this, 'cli_pull']);
    \WP_CLI::add_command('fcg-sync push', [$this, 'cli_push']);
    \WP_CLI::add_command('fcg-sync status', [$this, 'cli_status']);
    \WP_CLI::add_command('fcg-sync conflicts', [$this, 'cli_conflicts']);
    \WP_CLI::add_command('fcg-sync retry', [$this, 'cli_retry']);

    // New campaign commands
    \WP_CLI::add_command('fcg-sync campaigns', [$this, 'cli_campaigns']);
    \WP_CLI::add_command('fcg-sync campaign-create', [$this, 'cli_campaign_create']);
    \WP_CLI::add_command('fcg-sync campaign-pull', [$this, 'cli_campaign_pull']);
}
```

---

## Step C5.5: Add Helper Method for Campaign Data Building

**File:** `includes/class-sync-poller.php`

**Add method (if not already added in C3):**

```php
/**
 * Build campaign data from a WordPress post.
 *
 * @param WP_Post $post The post object.
 * @return array Campaign data for API.
 */
private function build_campaign_data_from_post(\WP_Post $post): array {
    $data = [
        'name' => $this->truncate_string($post->post_title, 127),
        'type' => 'crowdfunding',
        'goal' => $this->get_fund_goal($post->ID),
        'started_at' => $post->post_date,
        'timezone_identifier' => 'America/New_York',
        'external_reference_id' => (string) $post->ID,
    ];

    // Add overview from content or excerpt
    if (!empty($post->post_content)) {
        $data['overview'] = $this->truncate_string(
            wp_strip_all_tags($post->post_content),
            2000
        );
    } elseif (!empty($post->post_excerpt)) {
        $data['overview'] = $post->post_excerpt;
    }

    return $data;
}

/**
 * Get fundraising goal for a fund.
 *
 * @param int $post_id Post ID.
 * @return float Goal amount.
 */
private function get_fund_goal(int $post_id): float {
    // Try ACF field first
    if (function_exists('get_field')) {
        $gfm_settings = get_field('gofundme_settings', $post_id);
        if (!empty($gfm_settings['fundraising_goal']) && is_numeric($gfm_settings['fundraising_goal'])) {
            return (float) $gfm_settings['fundraising_goal'];
        }
    }

    // Fall back to post meta
    $goal = get_post_meta($post_id, '_fundraising_goal', true);
    if (!empty($goal) && is_numeric($goal)) {
        return (float) $goal;
    }

    // Default goal
    return 1000.00;
}
```

---

## Step C5.6: Update Plugin Version

**File:** `fcg-gofundme-sync.php`

**Update:**
1. Header comment: `* Version: 2.4.0`
2. Version constant: `define('FCG_GFM_SYNC_VERSION', '2.4.0');`

---

## Verification Tests

| Test | Command/Action | Expected Result |
|------|----------------|-----------------|
| C5.T1 | PHP syntax check | `php -l` passes for all modified files |
| C5.T2 | `wp fcg-sync campaigns` | Lists all funds with campaign status |
| C5.T3 | `wp fcg-sync campaigns --status=not-linked` | Shows only funds without campaigns |
| C5.T4 | `wp fcg-sync campaign-create --dry-run` | Shows what would be created |
| C5.T5 | `wp fcg-sync campaign-create --limit=1` | Creates one campaign |
| C5.T6 | `wp fcg-sync campaign-pull` | Pulls campaigns from GFM |
| C5.T7 | `wp fcg-sync campaign-pull --dry-run` | Shows what would be updated |
| C5.T8 | Plugin version | Shows 2.4.0 in plugins list |

### Test Commands

```bash
# T1: PHP Syntax
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync && php -l includes/class-sync-poller.php && php -l fcg-gofundme-sync.php"

# T2: List campaigns
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp fcg-sync campaigns"

# T3: Filter not linked
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp fcg-sync campaigns --status=not-linked"

# T4: Dry run create
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp fcg-sync campaign-create --dry-run --limit=5"

# T6: Pull campaigns
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp fcg-sync campaign-pull"

# T8: Plugin version
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp plugin list --name=fcg-gofundme-sync --format=csv"
```

---

## Files Modified Summary

| File | Action | Changes |
|------|--------|---------|
| `includes/class-sync-poller.php` | Modified | Add 3 CLI commands + helpers (~250 lines) |
| `fcg-gofundme-sync.php` | Modified | Version bump 2.3.0 → 2.4.0 |

---

## Execution Tracking

| Step | Agent | Status | Notes |
|------|-------|--------|-------|
| C5.1 | dev-agent | pending | cli_campaigns command |
| C5.2 | dev-agent | pending | cli_campaign_create command |
| C5.3 | dev-agent | pending | cli_campaign_pull command |
| C5.4 | dev-agent | pending | Register CLI commands |
| C5.5 | dev-agent | pending | Helper methods |
| C5.6 | dev-agent | pending | Version bump |
| - | testing-agent | pending | Code review |
| - | deploy-agent | pending | Deploy to staging, run tests |

---

## Command Reference Summary

After this phase, full WP-CLI command set:

### Designation Commands (existing)
```bash
wp fcg-sync pull        # Pull designation updates from GFM
wp fcg-sync push        # Push funds to GFM as designations
wp fcg-sync status      # Show sync status for all funds
wp fcg-sync conflicts   # Show recent sync conflicts
wp fcg-sync retry       # Retry failed syncs
```

### Campaign Commands (new)
```bash
wp fcg-sync campaigns        # List all funds with campaign status
wp fcg-sync campaign-create  # Create campaigns for funds
wp fcg-sync campaign-pull    # Pull campaign updates from GFM
```

---

## Success Criteria

After this phase:
1. `wp fcg-sync campaigns` shows all funds with campaign status
2. `wp fcg-sync campaign-create` creates campaigns for funds without one
3. `wp fcg-sync campaign-pull` pulls campaign updates from GFM
4. All commands support `--dry-run` where applicable
5. Progress bars and formatted output work correctly
6. Plugin version is 2.4.0

---

## Notes for Dev Agent

1. **Follow existing patterns:** Match style of existing CLI commands exactly
2. **Progress bars:** Use `\WP_CLI\Utils\make_progress_bar()` for bulk operations
3. **Dry run:** Always support `--dry-run` for destructive/creating operations
4. **Error handling:** Use `\WP_CLI::warning()` for errors, `\WP_CLI::error()` for fatal
5. **Output format:** Support `--format=table|csv|json` where useful
6. **Reuse methods:** Use existing helper methods from C3 where possible
