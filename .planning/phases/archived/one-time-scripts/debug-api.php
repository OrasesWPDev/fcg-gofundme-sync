<?php
/**
 * Debug Classy API to find designations
 * Run with: wp eval-file /path/to/debug-api.php
 */

if (!class_exists('FCG_GFM_API_Client')) {
    WP_CLI::error('FCG GoFundMe Sync plugin not loaded');
}

$api = new FCG_GFM_API_Client();
$org_id = defined('GOFUNDME_ORG_ID') ? GOFUNDME_ORG_ID : getenv('GOFUNDME_ORG_ID');

WP_CLI::log("Org ID: {$org_id}");

// Test 1: Campaign designations endpoint
WP_CLI::log("\n=== Testing campaigns/764694/designations ===");
$r1 = $api->request("campaigns/764694/designations", 'GET', ['per_page' => 5]);
if (is_wp_error($r1)) {
    WP_CLI::log("Error: " . $r1->get_error_message());
} else {
    WP_CLI::log("Data count: " . count($r1['data'] ?? []));
    if (!empty($r1['data'][0])) {
        WP_CLI::log("First designation: " . json_encode($r1['data'][0]));
    }
    WP_CLI::log("Full response keys: " . implode(', ', array_keys($r1)));
}

// Test 2: Organization designations endpoint
WP_CLI::log("\n=== Testing organizations/{$org_id}/designations ===");
$r2 = $api->request("organizations/{$org_id}/designations", 'GET', ['per_page' => 5]);
if (is_wp_error($r2)) {
    WP_CLI::log("Error: " . $r2->get_error_message());
} else {
    WP_CLI::log("Data count: " . count($r2['data'] ?? []));
    WP_CLI::log("Full response keys: " . implode(', ', array_keys($r2)));
}

// Test 3: Get campaign info
WP_CLI::log("\n=== Testing campaigns/764694 info ===");
$r3 = $api->request("campaigns/764694", 'GET');
if (is_wp_error($r3)) {
    WP_CLI::log("Error: " . $r3->get_error_message());
} else {
    WP_CLI::log("Campaign name: " . ($r3['name'] ?? '?'));
    WP_CLI::log("Org ID: " . ($r3['organization_id'] ?? '?'));
    WP_CLI::log("Status: " . ($r3['status'] ?? '?'));
}

// Test 4: Try organizations/ORG_ID/program-designations (alternative endpoint)
WP_CLI::log("\n=== Testing organizations/{$org_id}/program-designations ===");
$r4 = $api->request("organizations/{$org_id}/program-designations", 'GET', ['per_page' => 5]);
if (is_wp_error($r4)) {
    WP_CLI::log("Error: " . $r4->get_error_message());
} else {
    WP_CLI::log("Data count: " . count($r4['data'] ?? []));
    if (!empty($r4['data'][0])) {
        WP_CLI::log("First designation: " . json_encode($r4['data'][0]));
    }
}
