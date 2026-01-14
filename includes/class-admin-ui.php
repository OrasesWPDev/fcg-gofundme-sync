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

        wp_nonce_field('fcg_gfm_sync_now', 'fcg_gfm_sync_nonce');
        ?>
        <div class="fcg-sync-meta-box">
            <p>
                <strong>Designation ID:</strong><br>
                <?php if ($designation_id): ?>
                    <a href="https://www.classy.org/admin/designations/<?php echo esc_attr($designation_id); ?>" target="_blank">
                        <?php echo esc_html($designation_id); ?> <span class="dashicons dashicons-external"></span>
                    </a>
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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('fcg_gfm_sync'); ?>

                <table class="form-table">
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
     * Show admin notices for sync errors
     */
    public function show_sync_notices(): void {
        $screen = get_current_screen();

        // Only show on funds screens
        if (!$screen || $screen->post_type !== 'funds') {
            return;
        }

        // Count posts with sync errors
        $error_posts = get_posts([
            'post_type' => 'funds',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_gofundme_sync_error',
                    'compare' => 'EXISTS',
                ],
            ],
            'fields' => 'ids',
        ]);

        $error_count = count($error_posts);

        if ($error_count > 0) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>GoFundMe Pro Sync:</strong>
                    <?php echo esc_html($error_count); ?> fund(s) have sync errors.
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=funds&page=fcg-gfm-sync-settings')); ?>">View Settings</a>
                </p>
            </div>
            <?php
        }
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
}
