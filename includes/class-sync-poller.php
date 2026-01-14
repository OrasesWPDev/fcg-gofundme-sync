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
     * API Client instance
     *
     * @var FCG_GFM_API_Client
     */
    private FCG_GFM_API_Client $api;

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
     * Phase 2 scope: fetch and log only (no WordPress changes).
     */
    public function poll(): void {
        $result = $this->api->get_all_designations();

        if (!$result['success']) {
            $this->log("Poll failed: {$result['error']}");
            return;
        }

        $count = $result['total'];
        $this->log("Poll complete: fetched {$count} designations");

        $this->set_last_poll_time();
    }

    /**
     * WP-CLI command to pull designations
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

        // Display results (Phase 2 scope - just fetch and display)
        foreach ($designations as $designation) {
            \WP_CLI::log(sprintf(
                "  [%d] %s (active: %s)",
                $designation['id'],
                $designation['name'],
                $designation['is_active'] ? 'yes' : 'no'
            ));
        }

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
     * Log message with plugin prefix
     *
     * @param string $message Message to log
     */
    private function log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FCG GoFundMe Sync] ' . $message);
        }
    }
}
