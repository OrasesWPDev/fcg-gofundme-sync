<?php
/**
 * Match existing Classy designations to WordPress funds by External ID
 * Run with: wp eval-file /path/to/match-designations.php
 */

// Ensure plugin is loaded
if (!class_exists('FCG_GFM_API_Client')) {
    WP_CLI::error('FCG GoFundMe Sync plugin not loaded');
}

// Get API client
$api = new FCG_GFM_API_Client();

WP_CLI::log("Fetching all designations using get_all_designations()...");

// Use the built-in method that's proven to work
$result = $api->get_all_designations();

if (!$result['success']) {
    WP_CLI::error('API Error: ' . ($result['error'] ?? 'Unknown'));
}

$all_designations = $result['data'];
WP_CLI::log("Total designations fetched: " . count($all_designations));

// Build lookup by external_reference_id (this is the WP post ID)
$matched = 0;
$skipped = 0;
$not_found = 0;

foreach ($all_designations as $designation) {
    $designation_id = $designation['id'];
    // The field is external_reference_id, not external_id
    $external_id = $designation['external_reference_id'] ?? null;
    $name = $designation['name'] ?? 'Unknown';

    if (empty($external_id) || !is_numeric($external_id)) {
        $skipped++;
        continue;
    }

    // Check if WordPress post exists with this ID
    $post = get_post((int)$external_id);
    if (!$post || $post->post_type !== 'funds') {
        $not_found++;
        continue;
    }

    // Update post meta with designation ID
    update_post_meta($post->ID, '_gofundme_designation_id', $designation_id);
    update_post_meta($post->ID, '_gofundme_last_sync', current_time('mysql'));
    $matched++;

    if ($matched % 100 === 0) {
        WP_CLI::log("Matched {$matched} funds so far...");
    }
}

WP_CLI::success("Done! Matched: {$matched}, Skipped (no external_reference_id): {$skipped}, Post not found: {$not_found}");

// Count remaining unlinked funds
$unlinked = get_posts([
    'post_type' => 'funds',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => [
        'relation' => 'OR',
        [
            'key' => '_gofundme_designation_id',
            'compare' => 'NOT EXISTS'
        ],
        [
            'key' => '_gofundme_designation_id',
            'value' => '',
            'compare' => '='
        ]
    ],
    'fields' => 'ids'
]);

WP_CLI::log("Remaining unlinked published funds: " . count($unlinked));
if (count($unlinked) > 0 && count($unlinked) <= 30) {
    WP_CLI::log("Unlinked fund IDs: " . implode(', ', $unlinked));
}
