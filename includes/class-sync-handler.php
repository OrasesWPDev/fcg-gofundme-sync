<?php
/**
 * Sync Handler
 * 
 * Hooks into WordPress post actions to sync funds with GoFundMe Pro designations.
 * 
 * @package FCG_GoFundMe_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class FCG_GFM_Sync_Handler {
    
    /**
     * Post type to sync
     */
    private const POST_TYPE = 'funds';
    
    /**
     * Post meta key for designation ID
     */
    private const META_KEY_DESIGNATION_ID = '_gofundme_designation_id';
    
    /**
     * Post meta key for last sync timestamp
     */
    private const META_KEY_LAST_SYNC = '_gofundme_last_sync';

    /**
     * Meta key for GoFundMe Pro Campaign ID
     */
    private const META_CAMPAIGN_ID = '_gofundme_campaign_id';

    /**
     * Meta key for GoFundMe Pro Campaign URL
     */
    private const META_CAMPAIGN_URL = '_gofundme_campaign_url';

    /**
     * ACF field group key
     */
    private const ACF_GROUP_KEY = 'gofundme_settings';
    
    /**
     * API Client instance
     * 
     * @var FCG_GFM_API_Client
     */
    private $api;
    
    /**
     * Constructor - register WordPress hooks
     */
    public function __construct() {
        $this->api = new FCG_GFM_API_Client();
        
        if (!$this->api->is_configured()) {
            return;
        }
        
        // Hook into post save (fires on create and update)
        add_action('save_post_' . self::POST_TYPE, [$this, 'on_save_fund'], 20, 3);
        
        // Hook into post trash
        add_action('wp_trash_post', [$this, 'on_trash_fund']);
        
        // Hook into post restore from trash
        add_action('untrash_post', [$this, 'on_untrash_fund']);
        
        // Hook into permanent delete
        add_action('before_delete_post', [$this, 'on_delete_fund']);
        
        // Hook into post status transitions (catch draft/publish changes)
        add_action('transition_post_status', [$this, 'on_status_change'], 10, 3);
    }
    
    /**
     * Handle fund save (create or update)
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function on_save_fund(int $post_id, WP_Post $post, bool $update): void {
        // Skip outbound sync during inbound sync (prevent loop)
        if (FCG_GFM_Sync_Poller::is_syncing_inbound()) {
            return;
        }

        // Skip autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Skip if not our post type (extra safety)
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }
        
        // Skip auto-drafts
        if ($post->post_status === 'auto-draft') {
            return;
        }
        
        // Skip REST API requests during bulk operations (use bulk script instead)
        if (defined('REST_REQUEST') && REST_REQUEST && $this->is_bulk_operation()) {
            return;
        }
        
        // Build designation data from post
        $designation_data = $this->build_designation_data($post);
        
        // Get existing designation ID
        $designation_id = $this->get_designation_id($post_id);
        
        if ($designation_id) {
            // Update existing designation
            $this->update_designation($post_id, $designation_id, $designation_data);
        } else {
            // Only create new designation if post is published
            if ($post->post_status === 'publish') {
                $this->create_designation($post_id, $designation_data);
            }
        }

        // Sync campaign (parallel to designation)
        $this->sync_campaign_to_gofundme($post_id, $post);
    }
    
    /**
     * Handle fund trash (soft delete)
     * 
     * @param int $post_id Post ID
     */
    public function on_trash_fund(int $post_id): void {
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return;
        }
        
        $designation_id = $this->get_designation_id($post_id);
        
        if (!$designation_id) {
            return;
        }
        
        // Soft delete: set is_active = false
        $result = $this->api->update_designation($designation_id, [
            'is_active' => false,
        ]);

        if ($result['success']) {
            $this->log_info("Deactivated designation {$designation_id} for trashed post {$post_id}");
        }

        // Deactivate campaign
        $campaign_id = $this->get_campaign_id($post_id);
        if ($campaign_id) {
            $result = $this->api->deactivate_campaign($campaign_id);
            if ($result['success']) {
                $this->log_info("Deactivated campaign {$campaign_id} for trashed post {$post_id}");
            }
        }
    }
    
    /**
     * Handle fund restore from trash
     * 
     * @param int $post_id Post ID
     */
    public function on_untrash_fund(int $post_id): void {
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return;
        }
        
        $designation_id = $this->get_designation_id($post_id);
        
        if (!$designation_id) {
            return;
        }
        
        // Reactivate designation
        $result = $this->api->update_designation($designation_id, [
            'is_active' => true,
        ]);

        if ($result['success']) {
            $this->log_info("Reactivated designation {$designation_id} for restored post {$post_id}");
        }

        // Reactivate and publish campaign
        $campaign_id = $this->get_campaign_id($post_id);
        if ($campaign_id && $this->should_sync_campaign($post_id)) {
            // Step 1: Reactivate (returns campaign to unpublished status)
            $reactivate_result = $this->api->reactivate_campaign($campaign_id);
            if (!$reactivate_result['success']) {
                $this->log_error("Failed to reactivate campaign {$campaign_id}: " . ($reactivate_result['error'] ?? 'Unknown error'));
                return;
            }
            $this->log_info("Reactivated campaign {$campaign_id} for restored post {$post_id}");

            // Step 2: Publish (makes campaign active again)
            $publish_result = $this->api->publish_campaign($campaign_id);
            if (!$publish_result['success']) {
                $this->log_error("Failed to publish campaign {$campaign_id} after reactivation: " . ($publish_result['error'] ?? 'Unknown error'));
                // Campaign is reactivated but unpublished - not ideal but recoverable
            } else {
                $this->log_info("Published campaign {$campaign_id} for restored post {$post_id}");
            }

            // Step 3: Update campaign data (name, goal, overview may have changed)
            $campaign_data = $this->build_campaign_data($post);
            $update_result = $this->api->update_campaign($campaign_id, $campaign_data);
            if ($update_result['success']) {
                update_post_meta($post_id, self::META_KEY_LAST_SYNC, current_time('mysql'));
                $this->log_info("Updated campaign {$campaign_id} data for restored post {$post_id}");
            }
        }
    }

    /**
     * Handle fund permanent delete
     * 
     * @param int $post_id Post ID
     */
    public function on_delete_fund(int $post_id): void {
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return;
        }
        
        $designation_id = $this->get_designation_id($post_id);
        
        if (!$designation_id) {
            return;
        }
        
        // Permanently delete designation
        $result = $this->api->delete_designation($designation_id);

        if ($result['success']) {
            $this->log_info("Deleted designation {$designation_id} for deleted post {$post_id}");
        }

        // Deactivate campaign (preserve donation history - do NOT delete)
        $campaign_id = $this->get_campaign_id($post_id);
        if ($campaign_id) {
            $result = $this->api->deactivate_campaign($campaign_id);
            if ($result['success']) {
                $this->log_info("Deactivated campaign {$campaign_id} for deleted post {$post_id}");
            }
        }
    }
    
    /**
     * Handle post status transitions
     * 
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     */
    public function on_status_change(string $new_status, string $old_status, WP_Post $post): void {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }
        
        // Skip if status hasn't actually changed
        if ($new_status === $old_status) {
            return;
        }
        
        $designation_id = $this->get_designation_id($post->ID);
        
        // If no designation exists and going to publish, create will happen in on_save_fund
        if (!$designation_id) {
            return;
        }
        
        // Update is_active based on publish status
        $is_active = ($new_status === 'publish');

        $result = $this->api->update_designation($designation_id, [
            'is_active' => $is_active,
        ]);

        if ($result['success']) {
            $status_text = $is_active ? 'activated' : 'deactivated';
            $this->log_info("Status change: {$status_text} designation {$designation_id} for post {$post->ID} ({$old_status} → {$new_status})");
        }

        // Update campaign based on publish status
        $campaign_id = $this->get_campaign_id($post->ID);
        if ($campaign_id) {
            if ($is_active) {
                $campaign_data = $this->build_campaign_data($post);
                $campaign_result = $this->api->update_campaign($campaign_id, $campaign_data);
            } else {
                $campaign_result = $this->api->deactivate_campaign($campaign_id);
            }

            if ($campaign_result['success']) {
                $status_text = $is_active ? 'activated' : 'deactivated';
                $this->log_info("Status change: {$status_text} campaign {$campaign_id} for post {$post->ID}");
            }
        }
    }
    
    /**
     * Build designation data from WordPress post
     * 
     * @param WP_Post $post Post object
     * @return array Designation data
     */
    private function build_designation_data(WP_Post $post): array {
        $data = [
            'name'                  => $this->truncate_string($post->post_title, 127),
            'is_active'             => ($post->post_status === 'publish'),
            'external_reference_id' => (string) $post->ID,
        ];
        
        // Description: prefer excerpt, fall back to truncated content
        if (!empty($post->post_excerpt)) {
            $data['description'] = $post->post_excerpt;
        } elseif (!empty($post->post_content)) {
            $data['description'] = $this->truncate_string(
                wp_strip_all_tags($post->post_content),
                500
            );
        }
        
        // Get ACF fields if available
        if (function_exists('get_field')) {
            $gfm_settings = get_field(self::ACF_GROUP_KEY, $post->ID);
            
            // Goal from ACF (if we add a fundraising goal field later)
            if (!empty($gfm_settings['fundraising_goal']) && is_numeric($gfm_settings['fundraising_goal'])) {
                $data['goal'] = (float) $gfm_settings['fundraising_goal'];
            }
        }
        
        return $data;
    }

    /**
     * Build campaign data from WordPress post
     *
     * @param WP_Post $post Post object
     * @return array Campaign data for API
     */
    private function build_campaign_data(WP_Post $post): array {
        $data = [
            'name'                  => $this->truncate_string($post->post_title, 127),
            'type'                  => 'crowdfunding',
            'goal'                  => $this->get_fund_goal($post->ID),
            'started_at'            => $post->post_date,
            'timezone_identifier'   => 'America/New_York',
            'external_reference_id' => (string) $post->ID,
        ];

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
     * Get fundraising goal for a fund
     *
     * @param int $post_id Post ID
     * @return float Goal amount (default 1000)
     */
    private function get_fund_goal(int $post_id): float {
        if (function_exists('get_field')) {
            $gfm_settings = get_field(self::ACF_GROUP_KEY, $post_id);
            if (!empty($gfm_settings['fundraising_goal']) && is_numeric($gfm_settings['fundraising_goal'])) {
                return (float) $gfm_settings['fundraising_goal'];
            }
        }

        $goal = get_post_meta($post_id, '_fundraising_goal', true);
        if (!empty($goal) && is_numeric($goal)) {
            return (float) $goal;
        }

        return 1000.00;
    }

    /**
     * Create a new campaign in GoFundMe Pro via template duplication
     *
     * Uses the duplicate-then-publish workflow because POST /campaigns returns 403.
     * Template campaign ID is configured in Phase 1 plugin settings.
     *
     * @param int $post_id WordPress post ID
     * @param array $data Campaign data (name, goal, started_at, overview, external_reference_id)
     */
    private function create_campaign_in_gfm(int $post_id, array $data): void {
        // 1. Check prerequisites - get template campaign ID from settings
        $template_id = get_option('fcg_gfm_template_campaign_id');
        if (empty($template_id)) {
            $this->log_error("Cannot create campaign for post {$post_id}: No template campaign ID configured (fcg_gfm_template_campaign_id)");
            return;
        }

        // 2. Race condition protection - check for existing lock
        $lock_key = "fcg_gfm_creating_campaign_{$post_id}";
        if (get_transient($lock_key)) {
            $this->log_info("Campaign creation already in progress for post {$post_id}, skipping duplicate request");
            return;
        }

        // Set transient lock for 60 seconds
        set_transient($lock_key, true, 60);

        // 3. Build overrides array for duplication
        $overrides = [
            'name'                  => $data['name'],
            'raw_goal'              => isset($data['goal']) ? (string) $data['goal'] : '1000',
            'raw_currency_code'     => 'USD',
            'external_reference_id' => $data['external_reference_id'] ?? (string) $post_id,
        ];

        // Add started_at in ISO 8601 format if provided
        if (!empty($data['started_at'])) {
            $overrides['started_at'] = date('c', strtotime($data['started_at']));
        }

        // 4. Duplicate template campaign
        $result = $this->api->duplicate_campaign($template_id, $overrides);

        if (!$result['success'] || empty($result['data']['id'])) {
            $error = $result['error'] ?? 'Unknown error';
            $this->log_error("Failed to duplicate campaign template for post {$post_id}: {$error}");
            delete_transient($lock_key);
            return;
        }

        $campaign_id = $result['data']['id'];
        $campaign_url = $result['data']['canonical_url'] ?? '';

        $this->log_info("Duplicated template campaign to new campaign {$campaign_id} for post {$post_id}");

        // 5. Update overview if present (not available in duplication overrides)
        if (!empty($data['overview'])) {
            $update_result = $this->api->update_campaign($campaign_id, ['overview' => $data['overview']]);
            if (!$update_result['success']) {
                $this->log_info("Warning: Could not update overview for campaign {$campaign_id}: " . ($update_result['error'] ?? 'Unknown'));
                // Continue - overview update is non-fatal
            }
        }

        // 6. Publish campaign to make it active
        $publish_result = $this->api->publish_campaign($campaign_id);
        if (!$publish_result['success']) {
            $this->log_info("Warning: Could not publish campaign {$campaign_id}: " . ($publish_result['error'] ?? 'Unknown'));
            // Continue - campaign can be published manually later
        } else {
            $this->log_info("Published campaign {$campaign_id} for post {$post_id}");
        }

        // 7. Store campaign data in post meta
        update_post_meta($post_id, self::META_CAMPAIGN_ID, $campaign_id);
        update_post_meta($post_id, self::META_CAMPAIGN_URL, $campaign_url);
        update_post_meta($post_id, self::META_KEY_LAST_SYNC, current_time('mysql'));

        // 8. Cleanup - delete transient lock
        delete_transient($lock_key);

        $this->log_info("Created campaign {$campaign_id} for post {$post_id} via template duplication");
    }

    /**
     * Update an existing campaign in GoFundMe Pro
     *
     * @param int $post_id WordPress post ID
     * @param string|int $campaign_id GoFundMe Pro campaign ID
     * @param array $data Campaign data
     */
    private function update_campaign_in_gfm(int $post_id, $campaign_id, array $data): void {
        $result = $this->api->update_campaign($campaign_id, $data);

        if ($result['success']) {
            update_post_meta($post_id, self::META_KEY_LAST_SYNC, current_time('mysql'));
            $this->log_info("Updated campaign {$campaign_id} for post {$post_id}");
        } else {
            $error = $result['error'] ?? 'Unknown error';
            $this->log_error("Failed to update campaign {$campaign_id} for post {$post_id}: {$error}");
        }
    }

    /**
     * Sync fund to GoFundMe Pro as a campaign
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    private function sync_campaign_to_gofundme(int $post_id, WP_Post $post): void {
        // Check if campaign sync is disabled for this fund
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

    /**
     * Get campaign ID from post meta
     *
     * @param int $post_id Post ID
     * @return string|null Campaign ID or null
     */
    private function get_campaign_id(int $post_id): ?string {
        $campaign_id = get_post_meta($post_id, self::META_CAMPAIGN_ID, true);
        return !empty($campaign_id) ? (string) $campaign_id : null;
    }

    /**
     * Get campaign URL from post meta
     *
     * @param int $post_id Post ID
     * @return string|null Campaign URL or null
     */
    private function get_campaign_url(int $post_id): ?string {
        $campaign_url = get_post_meta($post_id, self::META_CAMPAIGN_URL, true);
        return !empty($campaign_url) ? $campaign_url : null;
    }

    /**
     * Check if campaign sync is enabled for this fund
     *
     * @param int $post_id Post ID
     * @return bool True if campaign should be synced
     */
    private function should_sync_campaign(int $post_id): bool {
        // Check if ACF is available
        if (!function_exists('get_field')) {
            return true; // Default to sync if ACF not available
        }

        $gfm_settings = get_field(self::ACF_GROUP_KEY, $post_id);

        // Check for explicit disable
        if (!empty($gfm_settings['disable_campaign_sync'])) {
            return false;
        }

        return true;
    }

    /**
     * Create a new designation
     *
     * @param int $post_id WordPress post ID
     * @param array $data Designation data
     */
    private function create_designation(int $post_id, array $data): void {
        $result = $this->api->create_designation($data);
        
        if ($result['success'] && !empty($result['data']['id'])) {
            $designation_id = $result['data']['id'];
            
            // Store designation ID in post meta
            update_post_meta($post_id, self::META_KEY_DESIGNATION_ID, $designation_id);
            update_post_meta($post_id, self::META_KEY_LAST_SYNC, current_time('mysql'));
            
            // Also update ACF field if it exists
            if (function_exists('update_field')) {
                update_field(self::ACF_GROUP_KEY . '_gofundme_designation_id', $designation_id, $post_id);
            }
            
            $this->log_info("Created designation {$designation_id} for post {$post_id}");
        } else {
            $error = $result['error'] ?? 'Unknown error';
            $this->log_error("Failed to create designation for post {$post_id}: {$error}");
        }
    }
    
    /**
     * Update an existing designation
     * 
     * @param int $post_id WordPress post ID
     * @param string|int $designation_id GoFundMe Pro designation ID
     * @param array $data Designation data
     */
    private function update_designation(int $post_id, $designation_id, array $data): void {
        $result = $this->api->update_designation($designation_id, $data);
        
        if ($result['success']) {
            update_post_meta($post_id, self::META_KEY_LAST_SYNC, current_time('mysql'));
            $this->log_info("Updated designation {$designation_id} for post {$post_id}");
        } else {
            $error = $result['error'] ?? 'Unknown error';
            $this->log_error("Failed to update designation {$designation_id} for post {$post_id}: {$error}");
        }
    }
    
    /**
     * Get designation ID from post meta or ACF field
     * 
     * @param int $post_id Post ID
     * @return string|null Designation ID or null
     */
    private function get_designation_id(int $post_id): ?string {
        // First check post meta (set by bulk script or this plugin)
        $designation_id = get_post_meta($post_id, self::META_KEY_DESIGNATION_ID, true);
        
        if (!empty($designation_id)) {
            return (string) $designation_id;
        }
        
        // Fallback to ACF field (if manually entered)
        if (function_exists('get_field')) {
            $gfm_settings = get_field(self::ACF_GROUP_KEY, $post_id);
            if (!empty($gfm_settings['gofundme_designation_id'])) {
                return (string) $gfm_settings['gofundme_designation_id'];
            }
        }
        
        return null;
    }
    
    /**
     * Check if this is a bulk operation
     * 
     * @return bool
     */
    private function is_bulk_operation(): bool {
        // Check for common bulk operation indicators
        return (
            defined('DOING_BULK_IMPORT') ||
            (isset($_REQUEST['action']) && $_REQUEST['action'] === 'bulk_import')
        );
    }
    
    /**
     * Truncate string to max length
     * 
     * @param string $string Input string
     * @param int $max_length Maximum length
     * @return string Truncated string
     */
    private function truncate_string(string $string, int $max_length): string {
        if (mb_strlen($string) <= $max_length) {
            return $string;
        }
        return mb_substr($string, 0, $max_length - 3) . '...';
    }
    
    /**
     * Log info message
     * 
     * @param string $message Message
     */
    private function log_info(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FCG GoFundMe Sync] ' . $message);
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message Message
     */
    private function log_error(string $message): void {
        error_log('[FCG GoFundMe Sync] ERROR: ' . $message);
    }
}
