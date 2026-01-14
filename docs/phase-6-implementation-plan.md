# Phase 6: Error Handling - Implementation Plan

**PRD Reference:** `/Users/chadmacbook/projects/fcg/docs/PRD.md` (Phase 6)
**Goal:** Graceful failure and recovery
**Version:** 1.5.0
**Branch:** `feature/phase-6-error-handling`
**Depends On:** Phase 3, Phase 4, Phase 5

---

## Substeps Overview

| Step | Description | Files |
|------|-------------|-------|
| 6.1 | Add error tracking post meta | `class-sync-poller.php` |
| 6.2 | Implement retry logic | `class-sync-poller.php` |
| 6.3 | Add retry WP-CLI command | `class-sync-poller.php` |
| 6.4 | Enhanced admin notifications | `class-admin-ui.php` |
| 6.5 | Deploy and test | N/A |

---

## Step 6.1: Error Tracking Post Meta

**New post meta keys:**

| Key | Purpose |
|-----|---------|
| `_gofundme_sync_error` | Last error message |
| `_gofundme_sync_attempts` | Failed attempt count |
| `_gofundme_sync_last_attempt` | Timestamp of last failed attempt |

```php
/**
 * Record a sync error for a post
 */
private function record_sync_error(int $post_id, string $error): void {
    update_post_meta($post_id, '_gofundme_sync_error', $error);

    $attempts = (int) get_post_meta($post_id, '_gofundme_sync_attempts', true);
    update_post_meta($post_id, '_gofundme_sync_attempts', $attempts + 1);
    update_post_meta($post_id, '_gofundme_sync_last_attempt', current_time('mysql'));

    $this->log("Sync error for post {$post_id}: {$error} (attempt " . ($attempts + 1) . ")");
}

/**
 * Clear sync error for a post (on successful sync)
 */
private function clear_sync_error(int $post_id): void {
    delete_post_meta($post_id, '_gofundme_sync_error');
    delete_post_meta($post_id, '_gofundme_sync_attempts');
    delete_post_meta($post_id, '_gofundme_sync_last_attempt');
}
```

---

## Step 6.2: Retry Logic

**Strategy:** Exponential backoff with maximum 3 attempts.

| Attempt | Delay |
|---------|-------|
| 1 | 5 minutes |
| 2 | 15 minutes |
| 3 | 45 minutes |

After 3 failed attempts, the sync is marked as failed and requires manual intervention.

```php
private const MAX_RETRY_ATTEMPTS = 3;

/**
 * Check if a post should be retried
 */
private function should_retry(int $post_id): bool {
    $attempts = (int) get_post_meta($post_id, '_gofundme_sync_attempts', true);

    if ($attempts >= self::MAX_RETRY_ATTEMPTS) {
        return false;
    }

    $last_attempt = get_post_meta($post_id, '_gofundme_sync_last_attempt', true);
    if (!$last_attempt) {
        return true;
    }

    $delay = $this->get_retry_delay($attempts);
    $next_retry_time = strtotime($last_attempt) + $delay;

    return time() >= $next_retry_time;
}

/**
 * Get retry delay based on attempt count (exponential backoff)
 */
private function get_retry_delay(int $attempts): int {
    // 5 min, 15 min, 45 min
    return (int) (5 * 60 * pow(3, $attempts));
}

/**
 * Attempt to sync a post with error handling
 */
private function sync_post_with_retry(int $post_id, array $designation): bool {
    try {
        $this->apply_designation_to_post($post_id, $designation);
        $this->clear_sync_error($post_id);
        return true;
    } catch (Exception $e) {
        $this->record_sync_error($post_id, $e->getMessage());
        return false;
    }
}
```

---

## Step 6.3: Update poll() to Handle Errors

Modify the `poll()` method to include error handling:

```php
public function poll(): void {
    $result = $this->api->get_all_designations();

    if (!$result['success']) {
        $this->log("Poll failed: {$result['error']}");
        return;
    }

    $designations = $result['data'];
    $stats = [
        'processed' => 0,
        'updated' => 0,
        'skipped' => 0,
        'orphaned' => 0,
        'errors' => 0,
        'retried' => 0,
    ];

    foreach ($designations as $designation) {
        $stats['processed']++;

        $post_id = $this->find_post_for_designation($designation);

        if (!$post_id) {
            $this->handle_orphan($designation);
            $stats['orphaned']++;
            continue;
        }

        // Check if this post has a pending error
        $has_error = get_post_meta($post_id, '_gofundme_sync_error', true);
        if ($has_error) {
            if (!$this->should_retry($post_id)) {
                $stats['skipped']++;
                continue;
            }
            $stats['retried']++;
        }

        if (!$this->has_designation_changed($post_id, $designation)) {
            $stats['skipped']++;
            continue;
        }

        if ($this->should_apply_gfm_changes($post_id, $designation)) {
            if ($this->sync_post_with_retry($post_id, $designation)) {
                $stats['updated']++;
            } else {
                $stats['errors']++;
            }
        } else {
            $stats['skipped']++;
        }
    }

    $this->log(sprintf(
        "Poll complete: %d processed, %d updated, %d skipped, %d orphaned, %d errors, %d retried",
        $stats['processed'],
        $stats['updated'],
        $stats['skipped'],
        $stats['orphaned'],
        $stats['errors'],
        $stats['retried']
    ));

    $this->set_last_poll_time();
}
```

---

## Step 6.4: Add WP-CLI Retry Command

```php
// Register in constructor
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('fcg-sync pull', [$this, 'cli_pull']);
    \WP_CLI::add_command('fcg-sync status', [$this, 'cli_status']);
    \WP_CLI::add_command('fcg-sync conflicts', [$this, 'cli_conflicts']);
    \WP_CLI::add_command('fcg-sync retry', [$this, 'cli_retry']);
}

/**
 * Retry failed syncs.
 *
 * ## OPTIONS
 *
 * [--force]
 * : Retry even if max attempts exceeded.
 *
 * [--clear]
 * : Clear all error states without retrying.
 *
 * ## EXAMPLES
 *
 *     wp fcg-sync retry
 *     wp fcg-sync retry --force
 *     wp fcg-sync retry --clear
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Associative arguments.
 */
public function cli_retry(array $args, array $assoc_args): void {
    $force = isset($assoc_args['force']);
    $clear = isset($assoc_args['clear']);

    // Find posts with sync errors
    $posts = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_query' => [
            [
                'key' => '_gofundme_sync_error',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    if (empty($posts)) {
        \WP_CLI::success('No failed syncs to retry');
        return;
    }

    \WP_CLI::log(sprintf('Found %d post(s) with sync errors', count($posts)));

    if ($clear) {
        foreach ($posts as $post) {
            $this->clear_sync_error($post->ID);
            \WP_CLI::log("Cleared error for post {$post->ID}");
        }
        \WP_CLI::success('All errors cleared');
        return;
    }

    // Get all designations for matching
    $result = $this->api->get_all_designations();
    if (!$result['success']) {
        \WP_CLI::error("Failed to fetch designations: {$result['error']}");
        return;
    }

    $designations_by_id = [];
    foreach ($result['data'] as $designation) {
        $designations_by_id[$designation['id']] = $designation;
    }

    $stats = ['retried' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0];

    foreach ($posts as $post) {
        $designation_id = get_post_meta($post->ID, '_gofundme_designation_id', true);
        $attempts = (int) get_post_meta($post->ID, '_gofundme_sync_attempts', true);
        $error = get_post_meta($post->ID, '_gofundme_sync_error', true);

        \WP_CLI::log(sprintf(
            'Post %d: %s (attempts: %d, error: %s)',
            $post->ID,
            $post->post_title,
            $attempts,
            $error
        ));

        if (!$force && $attempts >= self::MAX_RETRY_ATTEMPTS) {
            \WP_CLI::warning("  Skipped: max retries exceeded (use --force to override)");
            $stats['skipped']++;
            continue;
        }

        if (!isset($designations_by_id[$designation_id])) {
            \WP_CLI::warning("  Skipped: designation {$designation_id} not found in GFM");
            $stats['skipped']++;
            continue;
        }

        $designation = $designations_by_id[$designation_id];
        $stats['retried']++;

        if ($this->sync_post_with_retry($post->ID, $designation)) {
            \WP_CLI::log("  Success: sync completed");
            $stats['success']++;
        } else {
            \WP_CLI::warning("  Failed: sync error");
            $stats['failed']++;
        }
    }

    \WP_CLI::log('');
    \WP_CLI::log(sprintf(
        'Results: %d retried, %d success, %d failed, %d skipped',
        $stats['retried'],
        $stats['success'],
        $stats['failed'],
        $stats['skipped']
    ));

    if ($stats['failed'] === 0 && $stats['skipped'] === 0) {
        \WP_CLI::success('All retries successful!');
    } elseif ($stats['success'] > 0) {
        \WP_CLI::success('Some retries successful');
    } else {
        \WP_CLI::warning('No retries successful');
    }
}
```

---

## Step 6.5: Enhanced Admin Notifications

**File:** `includes/class-admin-ui.php`

Update `show_sync_notices()` to be more informative:

```php
/**
 * Show admin notices for sync errors
 */
public function show_sync_notices(): void {
    $screen = get_current_screen();

    if (!$screen || $screen->post_type !== 'funds') {
        return;
    }

    // Count posts with sync errors
    $error_posts = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_query' => [
            [
                'key' => '_gofundme_sync_error',
                'compare' => 'EXISTS',
            ],
        ],
        'fields' => 'ids',
    ]);

    $error_count = count($error_posts);

    if ($error_count === 0) {
        return;
    }

    // Count posts that have exceeded max retries
    $max_retries_exceeded = 0;
    foreach ($error_posts as $post_id) {
        $attempts = (int) get_post_meta($post_id, '_gofundme_sync_attempts', true);
        if ($attempts >= 3) {
            $max_retries_exceeded++;
        }
    }

    $class = $max_retries_exceeded > 0 ? 'notice-error' : 'notice-warning';
    $message = sprintf(
        '<strong>GoFundMe Pro Sync:</strong> %d fund(s) have sync errors.',
        $error_count
    );

    if ($max_retries_exceeded > 0) {
        $message .= sprintf(
            ' <strong>%d require manual intervention</strong> (max retries exceeded).',
            $max_retries_exceeded
        );
    }

    $message .= sprintf(
        ' <a href="%s">View Settings</a> | <code>wp fcg-sync retry</code>',
        admin_url('edit.php?post_type=funds&page=fcg-gfm-sync-settings')
    );

    printf('<div class="notice %s"><p>%s</p></div>', $class, $message);
}
```

---

## Step 6.6: Uninstall Cleanup

**File:** `uninstall.php`

Add cleanup for error-related meta:

```php
// Clean up error tracking meta (optional - uncomment to enable)
/*
global $wpdb;

$wpdb->delete($wpdb->postmeta, ['meta_key' => '_gofundme_sync_error'], ['%s']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_gofundme_sync_attempts'], ['%s']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_gofundme_sync_last_attempt'], ['%s']);
*/
```

---

## Verification Tests

| Test | Command/Action | Expected Result |
|------|----------------|-----------------|
| 6.5.1 | Simulate API failure | Error recorded in post meta |
| 6.5.2 | Check error meta | `_gofundme_sync_error` contains message |
| 6.5.3 | Check attempts meta | `_gofundme_sync_attempts` increments |
| 6.5.4 | `wp fcg-sync retry` | Failed syncs retried |
| 6.5.5 | `wp fcg-sync retry --force` | Max retries bypassed |
| 6.5.6 | `wp fcg-sync retry --clear` | All errors cleared |
| 6.5.7 | View funds list with errors | Admin notice shows error count |
| 6.5.8 | Exceed max retries | Notice shows "require manual intervention" |

### Test Commands

```bash
# Test 6.5.2: Check error meta
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp post meta get <POST_ID> _gofundme_sync_error"

# Test 6.5.3: Check attempts meta
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp post meta get <POST_ID> _gofundme_sync_attempts"

# Test 6.5.4: Retry failed syncs
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp fcg-sync retry"

# Test 6.5.5: Force retry
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp fcg-sync retry --force"

# Test 6.5.6: Clear errors
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp fcg-sync retry --clear"
```

---

## Files Modified Summary

| File | Action | Changes |
|------|--------|---------|
| `includes/class-sync-poller.php` | Modify | Error tracking, retry logic, CLI command |
| `includes/class-admin-ui.php` | Modify | Enhanced admin notices |
| `uninstall.php` | Modify | Cleanup for error meta |

---

## Execution Tracking

| Step | Agent | Status | Notes |
|------|-------|--------|-------|
| 6.1 | Dev Agent | PENDING | |
| 6.2 | Dev Agent | PENDING | |
| 6.3 | Dev Agent | PENDING | |
| 6.4 | Dev Agent | PENDING | |
| Code Review | Testing Agent | PENDING | |
| Commit | Git Agent | PENDING | |
| Deploy | Main Agent | PENDING | |
| Tests 6.5.1-6.5.8 | Main Agent | PENDING | |

**Commit SHA:** (pending)
**Commit Message:** (pending)

---

## Summary: All WP-CLI Commands After Phase 6

| Command | Description | Added In |
|---------|-------------|----------|
| `wp fcg-sync pull` | Pull designations from GFM | Phase 2 |
| `wp fcg-sync pull --dry-run` | Pull without applying changes | Phase 2 |
| `wp fcg-sync status` | Show sync status for all funds | Phase 4 |
| `wp fcg-sync conflicts` | Show recent sync conflicts | Phase 4 |
| `wp fcg-sync retry` | Retry failed syncs | Phase 6 |
| `wp fcg-sync retry --force` | Retry even if max attempts exceeded | Phase 6 |
| `wp fcg-sync retry --clear` | Clear all error states | Phase 6 |
