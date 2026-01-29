<?php
/**
 * GoFundMe Pro Sync Admin UI
 *
 * Provides admin interface for sync status visibility.
 *
 * @package FCG_GoFundMe_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class FCG_GFM_Admin_UI {

    /**
     * Constructor - register WordPress hooks
     */
    public function __construct() {
        // Only load in admin
        if (!is_admin()) {
            return;
        }

        // List table column
        add_filter('manage_funds_posts_columns', [$this, 'add_sync_column']);
        add_action('manage_funds_posts_custom_column', [$this, 'render_sync_column'], 10, 2);

        // Meta box
        add_action('add_meta_boxes', [$this, 'add_sync_meta_box']);

        // Settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        // Admin notices
        add_action('admin_notices', [$this, 'show_sync_notices']);

        // AJAX handler for manual sync
        add_action('wp_ajax_fcg_gfm_sync_now', [$this, 'ajax_sync_now']);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Save fundraising goal
        add_action('save_post_funds', [$this, 'save_fundraising_goal'], 10, 2);
    }

    /**
     * Add sync status column to funds list table
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_sync_column(array $columns): array {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['fcg_sync_status'] = 'Sync Status';
            }
        }
        return $new_columns;
    }

    /**
     * Render sync status column content
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public function render_sync_column(string $column, int $post_id): void {
        if ($column !== 'fcg_sync_status') {
            return;
        }

        $designation_id = get_post_meta($post_id, '_gofundme_designation_id', true);
        $last_sync = get_post_meta($post_id, '_gofundme_last_sync', true);
        $sync_error = get_post_meta($post_id, '_gofundme_sync_error', true);

        if (!$designation_id) {
            echo '<span class="fcg-sync-status fcg-sync-not-linked" title="No GoFundMe designation linked">Not Linked</span>';
            return;
        }

        if ($sync_error) {
            echo '<span class="fcg-sync-status fcg-sync-error" title="' . esc_attr($sync_error) . '">Error</span>';
            return;
        }

        if ($last_sync) {
            $last_sync_time = strtotime($last_sync);
            $fifteen_min_ago = time() - (15 * 60);

            if ($last_sync_time > $fifteen_min_ago) {
                echo '<span class="fcg-sync-status fcg-sync-synced" title="Last synced: ' . esc_attr($last_sync) . '">Synced</span>';
            } else {
                echo '<span class="fcg-sync-status fcg-sync-pending" title="Last synced: ' . esc_attr($last_sync) . '">Pending</span>';
            }
        } else {
            echo '<span class="fcg-sync-status fcg-sync-pending" title="Never synced">Pending</span>';
        }
    }

    /**
     * Add sync status meta box to fund edit screen
     */
    public function add_sync_meta_box(): void {
        add_meta_box(
            'fcg_gfm_sync_status',
            'GoFundMe Pro Sync',
            [$this, 'render_sync_meta_box'],
            'funds',
            'side',
            'default'
        );
    }

    /**
     * Render sync status meta box
     *
     * @param WP_Post $post Current post
     */
    public function render_sync_meta_box(WP_Post $post): void {
        $designation_id = get_post_meta($post->ID, '_gofundme_designation_id', true);
        $last_sync = get_post_meta($post->ID, '_gofundme_last_sync', true);
        $sync_source = get_post_meta($post->ID, '_gofundme_sync_source', true);
        $sync_error = get_post_meta($post->ID, '_gofundme_sync_error', true);
        $fundraising_goal = get_post_meta($post->ID, '_gofundme_fundraising_goal', true);

        // Get org ID for admin URLs
        $org_id = getenv('GOFUNDME_ORG_ID');
        if (!$org_id && defined('GOFUNDME_ORG_ID')) {
            $org_id = GOFUNDME_ORG_ID;
        }

        wp_nonce_field('fcg_gfm_sync_now', 'fcg_gfm_sync_nonce');
        ?>
        <div class="fcg-sync-meta-box">
            <p>
                <strong>Designation ID:</strong><br>
                <?php if ($designation_id && $org_id): ?>
                    <a href="https://www.classy.org/admin/<?php echo esc_attr($org_id); ?>/settings/designations/<?php echo esc_attr($designation_id); ?>" target="_blank">
                        <?php echo esc_html($designation_id); ?> <span class="dashicons dashicons-external"></span>
                    </a>
                <?php elseif ($designation_id): ?>
                    <?php echo esc_html($designation_id); ?> <em>(org ID not configured)</em>
                <?php else: ?>
                    <em>Not linked</em>
                <?php endif; ?>
            </p>

            <p>
                <strong>Last Sync:</strong><br>
                <?php echo $last_sync ? esc_html($last_sync) : '<em>Never</em>'; ?>
            </p>

            <p>
                <strong>Last Source:</strong><br>
                <?php echo $sync_source ? esc_html(ucfirst($sync_source)) : '<em>Unknown</em>'; ?>
            </p>

            <p>
                <label for="fcg-fundraising-goal"><strong>Fundraising Goal:</strong></label><br>
                <span style="display: inline-flex; align-items: center;">
                    <span style="margin-right: 4px;">$</span>
                    <input type="text"
                           id="fcg-fundraising-goal"
                           name="fcg_fundraising_goal"
                           value="<?php echo esc_attr($fundraising_goal ? number_format((int) $fundraising_goal) : ''); ?>"
                           placeholder="e.g., 5,000"
                           class="regular-text"
                           style="width: 120px;"
                           inputmode="numeric">
                </span>
            </p>
            <p class="description" style="margin-top: 0;">Optional. Goal amount for this fund's campaign.</p>

            <?php if ($sync_error): ?>
            <p class="fcg-sync-error-message">
                <strong>Error:</strong><br>
                <?php echo esc_html($sync_error); ?>
            </p>
            <?php endif; ?>

            <?php if ($designation_id): ?>
            <p>
                <button type="button" class="button fcg-sync-now-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <span class="dashicons dashicons-update"></span> Sync Now
                </button>
                <span class="spinner"></span>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add settings page under Funds menu
     */
    public function add_settings_page(): void {
        add_submenu_page(
            'edit.php?post_type=funds',
            'GoFundMe Pro Sync Settings',
            'Sync Settings',
            'manage_options',
            'fcg-gfm-sync-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        register_setting('fcg_gfm_sync', 'fcg_gfm_template_campaign_id', [
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => [$this, 'validate_template_campaign_id'],
        ]);
        register_setting('fcg_gfm_sync', 'fcg_gfm_poll_enabled', [
            'type' => 'boolean',
            'default' => true,
        ]);
        register_setting('fcg_gfm_sync', 'fcg_gfm_poll_interval', [
            'type' => 'integer',
            'default' => 900, // 15 minutes
        ]);
    }

    /**
     * Validate template campaign ID against Classy API
     *
     * @param mixed $input Input value
     * @return int Validated campaign ID or previous value on error
     */
    public function validate_template_campaign_id($input): int {
        $input = intval($input);

        // Allow clearing the setting
        if ($input === 0) {
            delete_option('fcg_gfm_template_campaign_name');
            delete_option('fcg_gfm_template_validation_failed');
            delete_option('fcg_gfm_template_validation_pending');
            return 0;
        }

        $api = new FCG_GFM_API_Client();

        // Check if API is configured first
        if (!$api->is_configured()) {
            add_settings_error(
                'fcg_gfm_template_campaign_id',
                'api_not_configured',
                'Cannot validate template: API credentials not configured.',
                'error'
            );
            return get_option('fcg_gfm_template_campaign_id', 0);
        }

        $result = $api->get_campaign($input);

        if (!$result['success']) {
            // Check if network/connection issue vs invalid ID
            $error_msg = $result['error'] ?? 'Unknown error';
            $is_connection_error = (
                stripos($error_msg, 'connection') !== false ||
                stripos($error_msg, 'timeout') !== false ||
                stripos($error_msg, 'resolve') !== false ||
                ($result['http_code'] ?? 0) >= 500
            );

            if ($is_connection_error) {
                // Schedule background re-validation
                if (!wp_next_scheduled('fcg_gfm_revalidate_template')) {
                    wp_schedule_single_event(time() + 900, 'fcg_gfm_revalidate_template');
                }
                update_option('fcg_gfm_template_validation_pending', true, false);
                add_settings_error(
                    'fcg_gfm_template_campaign_id',
                    'api_unreachable',
                    'Template ID saved. Could not verify with API (connection issue). Re-validation scheduled.',
                    'warning'
                );
                return $input;
            }

            // Invalid ID - block save
            add_settings_error(
                'fcg_gfm_template_campaign_id',
                'invalid_id',
                'Invalid campaign ID: ' . esc_html($error_msg),
                'error'
            );
            return get_option('fcg_gfm_template_campaign_id', 0);
        }

        // Valid - store campaign name
        $campaign_name = $result['data']['name'] ?? 'Unknown';
        update_option('fcg_gfm_template_campaign_name', $campaign_name, false);
        delete_option('fcg_gfm_template_validation_failed');
        delete_option('fcg_gfm_template_validation_pending');

        add_settings_error(
            'fcg_gfm_template_campaign_id',
            'valid_id',
            'Template campaign validated: ' . esc_html($campaign_name),
            'success'
        );

        return $input;
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $poll_enabled = get_option('fcg_gfm_poll_enabled', true);
        $poll_interval = get_option('fcg_gfm_poll_interval', 900);
        $last_poll = get_option('fcg_gfm_last_poll');
        $conflicts = get_option('fcg_gfm_conflict_log', []);

        // Template campaign settings
        $api = new FCG_GFM_API_Client();
        $api_configured = $api->is_configured();
        $template_id = get_option('fcg_gfm_template_campaign_id', 0);
        $template_name = get_option('fcg_gfm_template_campaign_name', '');
        $validation_failed = get_option('fcg_gfm_template_validation_failed', false);
        $validation_pending = get_option('fcg_gfm_template_validation_pending', false);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="notice notice-<?php echo $api_configured ? 'info' : 'warning'; ?>" style="margin: 15px 0;">
                <p>
                    <strong>API Status:</strong>
                    <?php if ($api_configured): ?>
                        <span style="color: #46b450;">Connected</span>
                    <?php else: ?>
                        <span style="color: #dc3232;">Not Configured</span>
                    <?php endif; ?>

                    <?php if ($template_id && $template_name && !$validation_failed && !$validation_pending): ?>
                        &nbsp;|&nbsp; <strong>Template:</strong> <?php echo esc_html($template_name); ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                    <?php elseif ($template_id && $validation_pending): ?>
                        &nbsp;|&nbsp; <strong>Template:</strong> Validation pending
                        <span class="dashicons dashicons-clock" style="color: #f0b849;"></span>
                    <?php elseif ($template_id && $validation_failed): ?>
                        &nbsp;|&nbsp; <strong>Template:</strong> Validation failed
                        <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                    <?php endif; ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('fcg_gfm_sync'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Template Campaign ID</th>
                        <td>
                            <input type="number"
                                   name="fcg_gfm_template_campaign_id"
                                   value="<?php echo esc_attr($template_id); ?>"
                                   class="regular-text"
                                   min="0"
                                   step="1">
                            <?php if ($template_name && !$validation_failed): ?>
                                <p class="description" style="color: #46b450;">
                                    <span class="dashicons dashicons-yes"></span>
                                    Template: <?php echo esc_html($template_name); ?>
                                </p>
                            <?php endif; ?>
                            <p class="description">
                                Enter the campaign ID to use as template for fund campaigns.
                                <a href="https://www.classy.org/admin/campaigns" target="_blank">
                                    Find campaign ID in Classy <span class="dashicons dashicons-external"></span>
                                </a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto-Polling</th>
                        <td>
                            <label>
                                <input type="checkbox" name="fcg_gfm_poll_enabled" value="1" <?php checked($poll_enabled); ?>>
                                Enable automatic polling from GoFundMe Pro
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Polling Interval</th>
                        <td>
                            <select name="fcg_gfm_poll_interval">
                                <option value="900" <?php selected($poll_interval, 900); ?>>Every 15 minutes</option>
                                <option value="1800" <?php selected($poll_interval, 1800); ?>>Every 30 minutes</option>
                                <option value="3600" <?php selected($poll_interval, 3600); ?>>Every hour</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2>Sync Status</h2>
            <p><strong>Last Poll:</strong> <?php echo $last_poll ? esc_html($last_poll) : 'Never'; ?></p>
            <p>
                <button type="button" class="button button-primary" id="fcg-sync-all">
                    <span class="dashicons dashicons-update"></span> Sync All Now
                </button>
                <span class="spinner"></span>
            </p>

            <?php if (!empty($conflicts)): ?>
            <h2>Recent Conflicts (Last 10)</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Post</th>
                        <th>WP Title</th>
                        <th>GFM Title</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice(array_reverse($conflicts), 0, 10) as $conflict): ?>
                    <tr>
                        <td><?php echo esc_html($conflict['timestamp']); ?></td>
                        <td>
                            <a href="<?php echo esc_url(get_edit_post_link($conflict['post_id'])); ?>">
                                #<?php echo esc_html($conflict['post_id']); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html(mb_substr($conflict['wp_title'], 0, 30)); ?></td>
                        <td><?php echo esc_html(mb_substr($conflict['gfm_title'], 0, 30)); ?></td>
                        <td><?php echo esc_html($conflict['reason']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Background re-validation of template campaign ID
     * Called via WP-Cron when API was unreachable during initial validation
     */
    public static function revalidate_template_campaign(): void {
        $template_id = get_option('fcg_gfm_template_campaign_id', 0);
        if (!$template_id) {
            delete_option('fcg_gfm_template_validation_failed');
            delete_option('fcg_gfm_template_validation_pending');
            return;
        }

        $api = new FCG_GFM_API_Client();
        if (!$api->is_configured()) {
            update_option('fcg_gfm_template_validation_failed', true, false);
            delete_option('fcg_gfm_template_validation_pending');
            return;
        }

        $result = $api->get_campaign($template_id);

        if (!$result['success']) {
            update_option('fcg_gfm_template_validation_failed', true, false);
            delete_option('fcg_gfm_template_validation_pending');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FCG GoFundMe Sync] Background template validation failed: ' . ($result['error'] ?? 'Unknown error'));
            }
        } else {
            // Success
            update_option('fcg_gfm_template_campaign_name', $result['data']['name'] ?? 'Unknown', false);
            delete_option('fcg_gfm_template_validation_failed');
            delete_option('fcg_gfm_template_validation_pending');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FCG GoFundMe Sync] Background template validation succeeded: ' . ($result['data']['name'] ?? 'Unknown'));
            }
        }
    }

    /**
     * Show admin notices for sync errors
     */
    public function show_sync_notices(): void {
        // Check for template validation failure (show on all admin pages)
        if (get_option('fcg_gfm_template_validation_failed')) {
            printf(
                '<div class="notice notice-error is-dismissible"><p><strong>GoFundMe Pro Sync:</strong> Template campaign ID validation failed. <a href="%s">Check settings</a></p></div>',
                esc_url(admin_url('edit.php?post_type=funds&page=fcg-gfm-sync-settings'))
            );
        }

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

        printf('<div class="notice %s"><p>%s</p></div>', esc_attr($class), $message);
    }

    /**
     * Handle AJAX sync now request
     */
    public function ajax_sync_now(): void {
        check_ajax_referer('fcg_gfm_sync_now', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($post_id) {
            // Sync single post by triggering an update
            $post = get_post($post_id);
            if ($post && $post->post_type === 'funds') {
                // Update the post to trigger outbound sync
                wp_update_post([
                    'ID' => $post_id,
                    'post_modified' => current_time('mysql'),
                    'post_modified_gmt' => current_time('mysql', true),
                ]);
                wp_send_json_success(['message' => 'Sync triggered for post ' . $post_id]);
            } else {
                wp_send_json_error('Invalid post');
            }
        } else {
            // Sync all via poller
            $poller = new FCG_GFM_Sync_Poller();
            $poller->poll();
            wp_send_json_success(['message' => 'Full sync completed']);
        }
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts(string $hook): void {
        $screen = get_current_screen();

        if (!$screen || $screen->post_type !== 'funds') {
            return;
        }

        wp_enqueue_style(
            'fcg-gfm-admin',
            FCG_GFM_SYNC_URL . 'assets/css/admin.css',
            [],
            FCG_GFM_SYNC_VERSION
        );

        wp_enqueue_script(
            'fcg-gfm-admin',
            FCG_GFM_SYNC_URL . 'assets/js/admin.js',
            ['jquery'],
            FCG_GFM_SYNC_VERSION,
            true
        );

        wp_localize_script('fcg-gfm-admin', 'fcgGfmAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fcg_gfm_sync_now'),
        ]);
    }

    /**
     * Save fundraising goal from meta box
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function save_fundraising_goal(int $post_id, WP_Post $post): void {
        // Check nonce (uses same nonce as sync meta box)
        if (!isset($_POST['fcg_gfm_sync_nonce']) ||
            !wp_verify_nonce($_POST['fcg_gfm_sync_nonce'], 'fcg_gfm_sync_now')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Process fundraising goal
        if (isset($_POST['fcg_fundraising_goal'])) {
            $goal = sanitize_text_field($_POST['fcg_fundraising_goal']);
            // Remove commas and non-numeric characters (except decimal point)
            $goal = preg_replace('/[^0-9.]/', '', $goal);

            if (is_numeric($goal) && floatval($goal) > 0) {
                // Store as integer (cents would require different handling)
                update_post_meta($post_id, '_gofundme_fundraising_goal', intval(floatval($goal)));
            } else {
                // Empty or invalid - remove the meta
                delete_post_meta($post_id, '_gofundme_fundraising_goal');
            }
        }
    }
}
