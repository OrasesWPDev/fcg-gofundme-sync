<?php
/**
 * Debug Classy API raw responses
 * Run with: wp eval-file /path/to/debug-api-raw.php
 */

if (!class_exists('FCG_GFM_API_Client')) {
    WP_CLI::error('FCG GoFundMe Sync plugin not loaded');
}

$api = new FCG_GFM_API_Client();
$org_id = defined('GOFUNDME_ORG_ID') ? GOFUNDME_ORG_ID : getenv('GOFUNDME_ORG_ID');

WP_CLI::log("Org ID: {$org_id}");

// Test campaign info - check RAW response
WP_CLI::log("\n=== RAW response for campaigns/764694 ===");
$r1 = $api->request("campaigns/764694", 'GET');
WP_CLI::log("Response type: " . gettype($r1));
if (is_wp_error($r1)) {
    WP_CLI::log("WP Error: " . $r1->get_error_message());
} elseif (is_array($r1)) {
    WP_CLI::log("Keys: " . implode(', ', array_keys($r1)));
    // Check if it's the wrapper or actual data
    if (isset($r1['response'])) {
        WP_CLI::log("Wrapper response (first 500 chars): " . substr(print_r($r1['response'], true), 0, 500));
    }
    if (isset($r1['name'])) {
        WP_CLI::log("Direct data - name: " . $r1['name']);
    }
    if (isset($r1['data'])) {
        WP_CLI::log("Has data key, count: " . (is_array($r1['data']) ? count($r1['data']) : 'not array'));
    }
    // Print first 1000 chars of full response
    WP_CLI::log("Full response (first 1000): " . substr(json_encode($r1), 0, 1000));
}

// Test program-designations
WP_CLI::log("\n=== RAW response for organizations/{$org_id}/program-designations ===");
$r2 = $api->request("organizations/{$org_id}/program-designations", 'GET', ['per_page' => 2]);
WP_CLI::log("Response type: " . gettype($r2));
if (is_wp_error($r2)) {
    WP_CLI::log("WP Error: " . $r2->get_error_message());
} elseif (is_array($r2)) {
    WP_CLI::log("Keys: " . implode(', ', array_keys($r2)));
    WP_CLI::log("Full response (first 1000): " . substr(json_encode($r2), 0, 1000));
}
