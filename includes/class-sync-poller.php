<?php
/**
 * GoFundMe Pro Sync Poller
 *
 * Handles polling GoFundMe Pro for designation changes.
 *
 * @package FCG_GoFundMe_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class FCG_GFM_Sync_Poller {

    /**
     * Option key for last poll timestamp
     */
    private const OPTION_LAST_POLL = 'fcg_gfm_last_poll';

    /**
     * Cron hook name
     */
    private const CRON_HOOK = 'fcg_gofundme_sync_poll';

    /**
     * Custom cron interval name
     */
    private const CRON_INTERVAL = 'fcg_gfm_15min';

    /**
     * Transient key for inbound sync flag
     */
    private const TRANSIENT_SYNCING = 'fcg_gfm_syncing_inbound';

    /**
     * Maximum retry attempts before giving up
     */
    private const MAX_RETRY_ATTEMPTS = 3;

    /**
     * API Client instance
     *
     * @var FCG_GFM_API_Client
     */
    private FCG_GFM_API_Client $api;

    /**
     * Orphaned designations found during poll
     *
     * @var array
     */
    private array $orphaned_designations = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new FCG_GFM_API_Client();

        if (!$this->api->is_configured()) {
            return;
        }

        // Register cron callback
        add_action(self::CRON_HOOK, [$this, 'poll']);

        // Register custom cron interval
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Register WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('fcg-sync pull', [$this, 'cli_pull']);
            \WP_CLI::add_command('fcg-sync push', [$this, 'cli_push']);
            \WP_CLI::add_command('fcg-sync status', [$this, 'cli_status']);
            \WP_CLI::add_command('fcg-sync conflicts', [$this, 'cli_conflicts']);
            \WP_CLI::add_command('fcg-sync retry', [$this, 'cli_retry']);
        }
    }

    /**
     * Add custom cron interval (15 minutes)
     *
     * @param array $schedules Existing cron schedules
     * @return array Modified schedules
     */
    public function add_cron_interval(array $schedules): array {
        $schedules[self::CRON_INTERVAL] = [
            'interval' => 15 * 60, // 900 seconds
            'display'  => __('Every 15 Minutes (FCG GoFundMe Sync)')
        ];
        return $schedules;
    }

    /**
     * Poll GoFundMe Pro for designation changes
     *
     * Called by WP-Cron every 15 minutes.
     */
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

    /**
     * WP-CLI command to pull designations
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Show what would be synced without making changes.
     *
     * ## EXAMPLES
     *
     *     wp fcg-sync pull
     *     wp fcg-sync pull --dry-run
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function cli_pull(array $args, array $assoc_args): void {
        $dry_run = isset($assoc_args['dry-run']);

        if ($dry_run) {
            \WP_CLI::log('Dry run mode - no changes will be made');
        }

        \WP_CLI::log('Fetching designations from GoFundMe Pro...');

        $result = $this->api->get_all_designations();

        if (!$result['success']) {
            \WP_CLI::error("API Error: {$result['error']}");
            return;
        }

        $designations = $result['data'];
        \WP_CLI::success("Fetched {$result['total']} designations");

        $stats = ['processed' => 0, 'updated' => 0, 'skipped' => 0, 'orphaned' => 0];

        foreach ($designations as $designation) {
            $stats['processed']++;

            $post_id = $this->find_post_for_designation($designation);

            if (!$post_id) {
                \WP_CLI::log(sprintf(
                    "  [ORPHAN] %s (ID: %d) - no matching WP post",
                    $designation['name'],
                    $designation['id']
                ));
                $stats['orphaned']++;
                continue;
            }

            $post = get_post($post_id);
            $changed = $this->has_designation_changed($post_id, $designation);
            $should_apply = $changed ? $this->should_apply_gfm_changes($post_id, $designation) : false;

            if (!$changed) {
                \WP_CLI::log(sprintf(
                    "  [SKIP] %s (Post %d) - no changes",
                    $designation['name'],
                    $post_id
                ));
                $stats['skipped']++;
                continue;
            }

            if (!$should_apply) {
                \WP_CLI::log(sprintf(
                    "  [CONFLICT] %s (Post %d) - WP modified after last sync, keeping WP version",
                    $designation['name'],
                    $post_id
                ));
                $stats['skipped']++;
                continue;
            }

            if ($dry_run) {
                \WP_CLI::log(sprintf(
                    "  [WOULD UPDATE] %s (Post %d)",
                    $designation['name'],
                    $post_id
                ));
                $stats['updated']++;
            } else {
                $this->apply_designation_to_post($post_id, $designation);
                \WP_CLI::log(sprintf(
                    "  [UPDATED] %s (Post %d)",
                    $designation['name'],
                    $post_id
                ));
                $stats['updated']++;
            }
        }

        \WP_CLI::log('');
        \WP_CLI::log(sprintf(
            "Results: %d processed, %d updated, %d skipped, %d orphaned",
            $stats['processed'],
            $stats['updated'],
            $stats['skipped'],
            $stats['orphaned']
        ));

        if (!$dry_run) {
            $this->set_last_poll_time();
            \WP_CLI::success('Poll timestamp updated');
        }
    }

    /**
     * Push WordPress funds to GoFundMe Pro.
     *
     * Creates designations in GoFundMe Pro for funds that don't have one yet.
     * Also updates existing designations if --update is passed.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Show what would be synced without making changes.
     *
     * [--update]
     * : Also update existing designations (not just create new ones).
     *
     * [--limit=<number>]
     * : Maximum number of funds to process. Default: all.
     *
     * [--post-id=<id>]
     * : Sync only a specific post ID.
     *
     * ## EXAMPLES
     *
     *     wp fcg-sync push
     *     wp fcg-sync push --dry-run
     *     wp fcg-sync push --update
     *     wp fcg-sync push --limit=100
     *     wp fcg-sync push --post-id=13707
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function cli_push(array $args, array $assoc_args): void {
        $dry_run = isset($assoc_args['dry-run']);
        $update = isset($assoc_args['update']);
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : -1;
        $post_id = isset($assoc_args['post-id']) ? (int) $assoc_args['post-id'] : 0;

        if ($dry_run) {
            \WP_CLI::log('Dry run mode - no changes will be made');
        }

        // Build query
        $query_args = [
            'post_type' => 'funds',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
        ];

        if ($post_id) {
            $query_args['p'] = $post_id;
            $query_args['post_status'] = 'any'; // Allow any status for specific post
        }

        $posts = get_posts($query_args);

        if (empty($posts)) {
            \WP_CLI::warning('No funds found matching criteria');
            return;
        }

        \WP_CLI::log(sprintf('Found %d fund(s) to process', count($posts)));

        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $progress = \WP_CLI\Utils\make_progress_bar('Processing funds', count($posts));

        foreach ($posts as $post) {
            $stats['processed']++;
            $designation_id = get_post_meta($post->ID, '_gofundme_designation_id', true);

            // Build designation data from WP post
            $data = $this->build_designation_data_from_post($post);

            if ($designation_id) {
                // Already has a designation
                if (!$update) {
                    $stats['skipped']++;
                    $progress->tick();
                    continue;
                }

                // Update existing
                if ($dry_run) {
                    \WP_CLI::log(sprintf(
                        "  [WOULD UPDATE] Post %d: %s (Designation %s)",
                        $post->ID,
                        mb_substr($post->post_title, 0, 40),
                        $designation_id
                    ));
                    $stats['updated']++;
                } else {
                    $result = $this->api->update_designation($designation_id, $data);
                    if ($result['success']) {
                        update_post_meta($post->ID, '_gofundme_last_sync', current_time('mysql'));
                        update_post_meta($post->ID, '_gofundme_sync_source', 'wordpress');
                        $stats['updated']++;
                    } else {
                        \WP_CLI::warning(sprintf(
                            "  [ERROR] Post %d: %s - %s",
                            $post->ID,
                            $post->post_title,
                            $result['error'] ?? 'Unknown error'
                        ));
                        $stats['errors']++;
                    }
                }
            } else {
                // No designation - create new
                if ($dry_run) {
                    \WP_CLI::log(sprintf(
                        "  [WOULD CREATE] Post %d: %s",
                        $post->ID,
                        mb_substr($post->post_title, 0, 40)
                    ));
                    $stats['created']++;
                } else {
                    $result = $this->api->create_designation($data);
                    if ($result['success'] && !empty($result['data']['id'])) {
                        $new_id = $result['data']['id'];
                        update_post_meta($post->ID, '_gofundme_designation_id', $new_id);
                        update_post_meta($post->ID, '_gofundme_last_sync', current_time('mysql'));
                        update_post_meta($post->ID, '_gofundme_sync_source', 'wordpress');
                        $stats['created']++;
                    } else {
                        \WP_CLI::warning(sprintf(
                            "  [ERROR] Post %d: %s - %s",
                            $post->ID,
                            $post->post_title,
                            $result['error'] ?? 'Unknown error'
                        ));
                        $stats['errors']++;
                    }
                }
            }

            $progress->tick();
        }

        $progress->finish();

        \WP_CLI::log('');
        \WP_CLI::log(sprintf(
            "Results: %d processed, %d created, %d updated, %d skipped, %d errors",
            $stats['processed'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['errors']
        ));

        if ($stats['errors'] === 0) {
            \WP_CLI::success('Push complete!');
        } elseif ($stats['created'] > 0 || $stats['updated'] > 0) {
            \WP_CLI::warning('Push complete with some errors');
        } else {
            \WP_CLI::error('Push failed - all operations had errors');
        }
    }

    /**
     * Build designation data from a WordPress post.
     *
     * @param WP_Post $post The post object.
     * @return array Designation data for API.
     */
    private function build_designation_data_from_post(\WP_Post $post): array {
        $data = [
            'name' => $this->truncate_string($post->post_title, 127),
            'is_active' => ($post->post_status === 'publish'),
            'external_reference_id' => (string) $post->ID,
        ];

        // Add description from excerpt or content
        if (!empty($post->post_excerpt)) {
            $data['description'] = $post->post_excerpt;
        } elseif (!empty($post->post_content)) {
            $data['description'] = $this->truncate_string(
                wp_strip_all_tags($post->post_content),
                500
            );
        }

        return $data;
    }

    /**
     * Show sync status for all funds.
     *
     * ## EXAMPLES
     *
     *     wp fcg-sync status
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function cli_status(array $args, array $assoc_args): void {
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
            $designation_id = get_post_meta($post->ID, '_gofundme_designation_id', true);
            $last_sync = get_post_meta($post->ID, '_gofundme_last_sync', true);
            $sync_source = get_post_meta($post->ID, '_gofundme_sync_source', true);
            $sync_error = get_post_meta($post->ID, '_gofundme_sync_error', true);

            $status = 'Not Linked';
            if ($designation_id) {
                if ($sync_error) {
                    $status = 'Error';
                } elseif ($last_sync) {
                    $last_sync_time = strtotime($last_sync);
                    $fifteen_min_ago = time() - (15 * 60);
                    $status = ($last_sync_time > $fifteen_min_ago) ? 'Synced' : 'Pending';
                } else {
                    $status = 'Pending';
                }
            }

            $table[] = [
                'ID' => $post->ID,
                'Title' => mb_substr($post->post_title, 0, 30),
                'Post Status' => $post->post_status,
                'Designation' => $designation_id ?: '-',
                'Sync Status' => $status,
                'Last Sync' => $last_sync ?: 'never',
                'Source' => $sync_source ?: '-',
            ];
        }

        \WP_CLI\Utils\format_items('table', $table, array_keys($table[0]));
    }

    /**
     * Show recent sync conflicts.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Number of conflicts to show. Default 10.
     *
     * ## EXAMPLES
     *
     *     wp fcg-sync conflicts
     *     wp fcg-sync conflicts --limit=20
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function cli_conflicts(array $args, array $assoc_args): void {
        $conflicts = get_option('fcg_gfm_conflict_log', []);

        if (empty($conflicts)) {
            \WP_CLI::success('No conflicts recorded');
            return;
        }

        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 10;
        $conflicts = array_slice(array_reverse($conflicts), 0, $limit);

        $table = [];
        foreach ($conflicts as $conflict) {
            $table[] = [
                'Timestamp' => $conflict['timestamp'],
                'Post ID' => $conflict['post_id'],
                'WP Title' => mb_substr($conflict['wp_title'], 0, 25),
                'GFM Title' => mb_substr($conflict['gfm_title'], 0, 25),
                'Reason' => $conflict['reason'],
            ];
        }

        \WP_CLI\Utils\format_items('table', $table, array_keys($table[0]));
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

    /**
     * Get the timestamp of the last successful poll
     *
     * @return string|null MySQL datetime or null if never polled
     */
    public function get_last_poll_time(): ?string {
        return get_option(self::OPTION_LAST_POLL, null);
    }

    /**
     * Store the current time as the last poll timestamp
     */
    public function set_last_poll_time(): void {
        update_option(self::OPTION_LAST_POLL, current_time('mysql'), false);
    }

    /**
     * Set the syncing inbound flag
     */
    private function set_syncing_flag(): void {
        set_transient(self::TRANSIENT_SYNCING, true, 30); // 30 second TTL
    }

    /**
     * Clear the syncing inbound flag
     */
    private function clear_syncing_flag(): void {
        delete_transient(self::TRANSIENT_SYNCING);
    }

    /**
     * Check if inbound sync is in progress
     *
     * @return bool
     */
    public static function is_syncing_inbound(): bool {
        return (bool) get_transient(self::TRANSIENT_SYNCING);
    }

    /**
     * Log message with plugin prefix
     *
     * @param string $message Message to log
     */
    private function log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FCG GoFundMe Sync] ' . $message);
        }
    }

    /**
     * Record a sync error for a post
     *
     * @param int $post_id Post ID
     * @param string $error Error message
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
     *
     * @param int $post_id Post ID
     */
    private function clear_sync_error(int $post_id): void {
        delete_post_meta($post_id, '_gofundme_sync_error');
        delete_post_meta($post_id, '_gofundme_sync_attempts');
        delete_post_meta($post_id, '_gofundme_sync_last_attempt');
    }

    /**
     * Check if a post should be retried
     *
     * @param int $post_id Post ID
     * @return bool
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
     *
     * @param int $attempts Number of failed attempts
     * @return int Delay in seconds
     */
    private function get_retry_delay(int $attempts): int {
        // 5 min, 15 min, 45 min
        return (int) (5 * 60 * pow(3, $attempts));
    }

    /**
     * Attempt to sync a post with error handling
     *
     * @param int $post_id Post ID
     * @param array $designation Designation data
     * @return bool Success or failure
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

    /**
     * Calculate hash for designation change detection
     *
     * @param array $designation Designation data
     * @return string MD5 hash
     */
    private function calculate_designation_hash(array $designation): string {
        $hashable = [
            'name' => $designation['name'] ?? '',
            'description' => $designation['description'] ?? '',
            'is_active' => $designation['is_active'] ?? false,
            'goal' => $designation['goal'] ?? 0,
        ];
        return md5(json_encode($hashable));
    }

    /**
     * Find WordPress post for a designation
     *
     * @param array $designation Designation data
     * @return int|null Post ID or null
     */
    private function find_post_for_designation(array $designation): ?int {
        $external_ref = $designation['external_reference_id'] ?? null;

        // Priority 1: external_reference_id is the WP post ID
        if ($external_ref && is_numeric($external_ref)) {
            $post = get_post((int) $external_ref);
            if ($post && $post->post_type === 'funds') {
                return $post->ID;
            }
        }

        // Priority 2: Search by designation ID in post meta
        $designation_id = $designation['id'];
        $posts = get_posts([
            'post_type' => 'funds',
            'meta_key' => '_gofundme_designation_id',
            'meta_value' => $designation_id,
            'posts_per_page' => 1,
            'post_status' => 'any',
        ]);

        return !empty($posts) ? $posts[0]->ID : null;
    }

    /**
     * Check if designation data has changed
     *
     * @param int $post_id Post ID
     * @param array $designation Designation data
     * @return bool
     */
    private function has_designation_changed(int $post_id, array $designation): bool {
        $stored_hash = get_post_meta($post_id, '_gofundme_poll_hash', true);
        $current_hash = $this->calculate_designation_hash($designation);
        return $stored_hash !== $current_hash;
    }

    /**
     * Check if GFM changes should be applied to WordPress
     *
     * @param int $post_id Post ID
     * @param array $designation Designation data
     * @return bool
     */
    private function should_apply_gfm_changes(int $post_id, array $designation): bool {
        $last_sync = get_post_meta($post_id, '_gofundme_last_sync', true);
        $post = get_post($post_id);

        if (!$last_sync) {
            return true; // Never synced, accept GFM data
        }

        // Check if WP was modified after last sync
        $wp_modified = strtotime($post->post_modified_gmt);
        $last_sync_time = strtotime($last_sync);

        if ($wp_modified > $last_sync_time) {
            // Conflict detected - WordPress wins
            $this->handle_conflict($post_id, $designation);
            return false;
        }

        return true;
    }

    /**
     * Apply designation changes to WordPress post
     *
     * @param int $post_id Post ID
     * @param array $designation Designation data
     */
    private function apply_designation_to_post(int $post_id, array $designation): void {
        $this->set_syncing_flag();

        try {
            $updates = [
                'ID' => $post_id,
                'post_title' => $designation['name'],
            ];

            // Update post status based on is_active
            if (isset($designation['is_active'])) {
                $current_status = get_post_status($post_id);
                if ($designation['is_active'] && $current_status === 'draft') {
                    $updates['post_status'] = 'publish';
                } elseif (!$designation['is_active'] && $current_status === 'publish') {
                    $updates['post_status'] = 'draft';
                }
            }

            // Update description if present
            if (!empty($designation['description'])) {
                $updates['post_excerpt'] = $designation['description'];
            }

            wp_update_post($updates);

            // Update meta
            update_post_meta($post_id, '_gofundme_poll_hash', $this->calculate_designation_hash($designation));
            update_post_meta($post_id, '_gofundme_sync_source', 'gofundme');
            update_post_meta($post_id, '_gofundme_last_sync', current_time('mysql'));

            $this->log("Applied GFM changes to post {$post_id}");
        } finally {
            $this->clear_syncing_flag();
        }
    }

    /**
     * Handle orphaned designation (no matching WP post)
     *
     * @param array $designation Designation data
     */
    private function handle_orphan(array $designation): void {
        $this->orphaned_designations[] = [
            'id' => $designation['id'],
            'name' => $designation['name'],
        ];
        $this->log("Orphan found: designation {$designation['id']} ({$designation['name']}) has no WP post");
    }

    /**
     * Log a sync conflict
     *
     * @param int $post_id Post ID
     * @param array $designation Designation data
     * @param string $reason Conflict reason
     */
    private function log_conflict(int $post_id, array $designation, string $reason): void {
        $conflicts = get_option('fcg_gfm_conflict_log', []);

        $conflicts[] = [
            'timestamp' => current_time('mysql'),
            'post_id' => $post_id,
            'designation_id' => $designation['id'],
            'reason' => $reason,
            'wp_title' => get_the_title($post_id),
            'gfm_title' => $designation['name'],
        ];

        // Keep last 100 conflicts
        $conflicts = array_slice($conflicts, -100);

        update_option('fcg_gfm_conflict_log', $conflicts, false);
    }

    /**
     * Handle conflict by pushing WP version to GFM (WordPress wins)
     *
     * @param int $post_id Post ID
     * @param array $designation Designation data
     */
    private function handle_conflict(int $post_id, array $designation): void {
        $this->log_conflict($post_id, $designation, 'WP modified after last sync');

        // Push WP version to GFM (WordPress wins)
        $post = get_post($post_id);

        // Build designation data from WP post
        $data = [
            'name' => $this->truncate_string($post->post_title, 127),
            'is_active' => ($post->post_status === 'publish'),
            'external_reference_id' => (string) $post->ID,
        ];

        if (!empty($post->post_excerpt)) {
            $data['description'] = $post->post_excerpt;
        }

        $result = $this->api->update_designation($designation['id'], $data);

        if ($result['success']) {
            $this->log("Conflict resolved: pushed WP version to GFM for post {$post_id}");
            update_post_meta($post_id, '_gofundme_last_sync', current_time('mysql'));
            update_post_meta($post_id, '_gofundme_sync_source', 'wordpress');

            // Recalculate hash based on what we pushed
            $new_hash = $this->calculate_designation_hash([
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'is_active' => $data['is_active'],
                'goal' => $designation['goal'] ?? 0,
            ]);
            update_post_meta($post_id, '_gofundme_poll_hash', $new_hash);
        } else {
            $this->log("Failed to push WP version to GFM for post {$post_id}: {$result['error']}");
        }
    }

    /**
     * Truncate a string to a maximum length
     *
     * @param string $string String to truncate
     * @param int $max_length Maximum length
     * @return string Truncated string
     */
    private function truncate_string(string $string, int $max_length): string {
        if (mb_strlen($string) <= $max_length) {
            return $string;
        }
        return mb_substr($string, 0, $max_length - 3) . '...';
    }
}
