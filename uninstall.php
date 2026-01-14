<?php
/**
 * Uninstall FCG GoFundMe Pro Sync
 * 
 * This file runs when the plugin is deleted from WordPress.
 * It cleans up plugin data while preserving designation IDs.
 * 
 * @package FCG_GoFundMe_Sync
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up transients
delete_transient('gofundme_access_token');

// Clean up polling options
delete_option('fcg_gfm_last_poll');

// Clean up conflict log
delete_option('fcg_gfm_conflict_log');

/**
 * Note: We intentionally do NOT delete the post meta:
 * - _gofundme_designation_id
 * - _gofundme_last_sync
 * - _gofundme_poll_hash
 * - _gofundme_sync_source
 *
 * This preserves the mapping between WordPress posts and GoFundMe Pro
 * designations in case the plugin is reinstalled or the data is needed
 * for manual reconciliation.
 *
 * If you need to completely remove all plugin data, uncomment the code below:
 */

/*
global $wpdb;

// Delete all designation ID meta
$wpdb->delete(
    $wpdb->postmeta,
    ['meta_key' => '_gofundme_designation_id'],
    ['%s']
);

// Delete all last sync meta
$wpdb->delete(
    $wpdb->postmeta,
    ['meta_key' => '_gofundme_last_sync'],
    ['%s']
);

// Delete all poll hash meta
$wpdb->delete(
    $wpdb->postmeta,
    ['meta_key' => '_gofundme_poll_hash'],
    ['%s']
);

// Delete all sync source meta
$wpdb->delete(
    $wpdb->postmeta,
    ['meta_key' => '_gofundme_sync_source'],
    ['%s']
);
*/
