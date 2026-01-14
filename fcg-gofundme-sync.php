<?php
/**
 * Plugin Name: FCG GoFundMe Pro Sync
 * Plugin URI: https://orases.com
 * Description: Syncs WordPress funds with GoFundMe Pro designations via API. Creates, updates, and deletes designations automatically when funds are modified.
 * Version: 1.1.0
 * Author: Orases
 * Author URI: https://orases.com
 * License: GPL v2 or later
 * Text Domain: fcg-gofundme-sync
 * 
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * 
 * @package FCG_GoFundMe_Sync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('FCG_GFM_SYNC_VERSION', '1.1.0');
define('FCG_GFM_SYNC_PATH', plugin_dir_path(__FILE__));
define('FCG_GFM_SYNC_URL', plugin_dir_url(__FILE__));

// Load the API client
require_once FCG_GFM_SYNC_PATH . 'includes/class-api-client.php';

// Load the sync handler
require_once FCG_GFM_SYNC_PATH . 'includes/class-sync-handler.php';

// Load the sync poller
require_once FCG_GFM_SYNC_PATH . 'includes/class-sync-poller.php';

/**
 * Check if a credential is available via env var or constant
 *
 * @param string $name Credential name
 * @return bool
 */
function fcg_gfm_has_credential(string $name): bool {
    $env_value = getenv($name);
    if ($env_value !== false && $env_value !== '') {
        return true;
    }
    return defined($name) && constant($name) !== '';
}

/**
 * Initialize the plugin
 */
function fcg_gfm_sync_init() {
    // Check for required credentials (env vars or constants)
    if (!fcg_gfm_has_credential('GOFUNDME_CLIENT_ID') || !fcg_gfm_has_credential('GOFUNDME_CLIENT_SECRET')) {
        add_action('admin_notices', 'fcg_gfm_sync_missing_credentials_notice');
        return;
    }

    // Check for required organization ID
    if (!fcg_gfm_has_credential('GOFUNDME_ORG_ID')) {
        add_action('admin_notices', 'fcg_gfm_sync_missing_org_notice');
        return;
    }

    // Initialize the sync handler
    new FCG_GFM_Sync_Handler();

    // Initialize the sync poller
    new FCG_GFM_Sync_Poller();
}
add_action('plugins_loaded', 'fcg_gfm_sync_init');

/**
 * Admin notice for missing API credentials
 */
function fcg_gfm_sync_missing_credentials_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="notice notice-warning">
        <p>
            <strong>FCG GoFundMe Pro Sync:</strong>
            API credentials not configured. Set environment variables in WP Engine User Portal:
        </p>
        <ul style="margin: 10px 0 10px 20px; list-style: disc;">
            <li><code>GOFUNDME_CLIENT_ID</code></li>
            <li><code>GOFUNDME_CLIENT_SECRET</code></li>
            <li><code>GOFUNDME_ORG_ID</code></li>
        </ul>
    </div>
    <?php
}

/**
 * Admin notice for missing organization ID
 */
function fcg_gfm_sync_missing_org_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="notice notice-warning">
        <p>
            <strong>FCG GoFundMe Pro Sync:</strong>
            Organization ID not configured. Set <code>GOFUNDME_ORG_ID</code> environment variable in WP Engine User Portal.
        </p>
    </div>
    <?php
}

/**
 * Plugin activation
 */
function fcg_gfm_sync_activate() {
    // Schedule polling cron if not already scheduled
    if (!wp_next_scheduled('fcg_gofundme_sync_poll')) {
        wp_schedule_event(time(), 'fcg_gfm_15min', 'fcg_gofundme_sync_poll');
    }
}
register_activation_hook(__FILE__, 'fcg_gfm_sync_activate');

/**
 * Plugin deactivation
 */
function fcg_gfm_sync_deactivate() {
    // Unschedule polling cron
    $timestamp = wp_next_scheduled('fcg_gofundme_sync_poll');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'fcg_gofundme_sync_poll');
    }

    // Clean up transients
    delete_transient('gofundme_access_token');
}
register_deactivation_hook(__FILE__, 'fcg_gfm_sync_deactivate');
