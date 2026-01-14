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

        // Register WP-CLI command
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('fcg-sync pull', [$this, 'cli_pull']);
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
        $stats = ['processed' => 0, 'updated' => 0, 'skipped' => 0, 'orphaned' => 0];

        foreach ($designations as $designation) {
            $stats['processed']++;

            $post_id = $this->find_post_for_designation($designation);

            if (!$post_id) {
                $this->handle_orphan($designation);
                $stats['orphaned']++;
                continue;
            }

            if (!$this->has_designation_changed($post_id, $designation)) {
                $stats['skipped']++;
                continue;
            }

            if ($this->should_apply_gfm_changes($post_id, $designation)) {
                $this->apply_designation_to_post($post_id, $designation);
                $stats['updated']++;
            } else {
                $stats['skipped']++;
            }
        }

        $this->log(sprintf(
            "Poll complete: %d processed, %d updated, %d skipped, %d orphaned",
            $stats['processed'],
            $stats['updated'],
            $stats['skipped'],
            $stats['orphaned']
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
            // WordPress wins - skip GFM changes
            $this->log("Conflict: Post {$post_id} modified after last sync, keeping WP version");
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
}
