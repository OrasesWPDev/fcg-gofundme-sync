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
