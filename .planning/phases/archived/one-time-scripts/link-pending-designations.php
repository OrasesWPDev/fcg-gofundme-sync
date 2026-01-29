<?php
/**
 * Link pending designations to master campaign
 *
 * Run via WP-CLI:
 * wp eval-file wp-content/plugins/fcg-gofundme-sync/scripts/link-pending-designations.php
 *
 * This script finds designations that exist but aren't linked to the master campaign
 * and links them using the update_campaign() API method.
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI\n";
    exit(1);
}

// Get the master campaign ID
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
if (empty($master_campaign_id)) {
    echo "ERROR: Master campaign ID not configured. Go to Funds > Sync Settings first.\n";
    exit(1);
}

echo "Master Campaign ID: {$master_campaign_id}\n\n";

// Get the API client
if (!class_exists('FCG_GFM_API_Client')) {
    require_once dirname(__DIR__) . '/includes/class-api-client.php';
}

$api = new FCG_GFM_API_Client();

// Find all published funds with designation IDs
$args = [
    'post_type' => 'funds',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => '_gofundme_designation_id',
            'compare' => 'EXISTS',
        ],
    ],
];

$funds = get_posts($args);
echo "Found " . count($funds) . " published funds with designation IDs\n\n";

// The 5 specific post IDs that need linking (from the screenshot)
$pending_post_ids = [13826, 13795, 13782, 13781, 13758];

echo "Linking " . count($pending_post_ids) . " pending designations to master campaign...\n\n";

$success_count = 0;
$error_count = 0;

foreach ($pending_post_ids as $post_id) {
    $designation_id = get_post_meta($post_id, '_gofundme_designation_id', true);
    $fund_title = get_the_title($post_id);

    if (empty($designation_id)) {
        echo "SKIP: Post {$post_id} has no designation ID\n";
        continue;
    }

    echo "Linking: {$fund_title} (Post {$post_id}, Designation {$designation_id})... ";

    $result = $api->update_campaign($master_campaign_id, [
        'designation_id' => $designation_id,
    ]);

    if ($result['success']) {
        echo "SUCCESS\n";
        $success_count++;
    } else {
        $error = $result['error'] ?? 'Unknown error';
        echo "FAILED: {$error}\n";
        $error_count++;
    }
}

echo "\n";
echo "========================================\n";
echo "Linked: {$success_count}\n";
echo "Errors: {$error_count}\n";
echo "========================================\n";

if ($success_count > 0) {
    echo "\nRefresh the Classy campaign page to see the updated designation count.\n";
}
