<?php
/**
 * Test script for campaign duplication API
 * Run with: wp eval-file test-campaign-api.php
 */

// Ensure API client is available
if (!class_exists('FCG_GFM_API_Client')) {
    echo "ERROR: FCG_GFM_API_Client class not found. Plugin may not be active.\n";
    exit(1);
}

$api = new FCG_GFM_API_Client();
$template_id = get_option('fcg_gfm_template_campaign_id');

echo "=== Campaign Duplication Test ===\n";
echo "Template ID from option: " . ($template_id ?: 'NOT SET') . "\n";

if (empty($template_id)) {
    echo "ERROR: No template campaign ID configured.\n";
    exit(1);
}

echo "API configured: " . ($api->is_configured() ? 'YES' : 'NO') . "\n";

// Test API connectivity first
echo "\n--- Testing API connectivity ---\n";
$campaign_result = $api->get_campaign($template_id);
if ($campaign_result['success']) {
    echo "Template campaign exists: " . $campaign_result['data']['name'] . "\n";
    echo "Template status: " . $campaign_result['data']['status'] . "\n";
} else {
    echo "ERROR fetching template: " . $campaign_result['error'] . "\n";
    exit(1);
}

// Now test duplication
echo "\n--- Testing duplicate_campaign ---\n";
$test_name = "API_Test_" . time();
$overrides = [
    'name' => $test_name,
    'raw_goal' => '1000',
    'raw_currency_code' => 'USD',
    'external_reference_id' => 'test_' . time()
];

echo "Calling duplicate_campaign with:\n";
print_r($overrides);

$result = $api->duplicate_campaign($template_id, $overrides);

echo "\n--- Result ---\n";
print_r($result);

if ($result['success'] && !empty($result['data']['id'])) {
    $new_id = $result['data']['id'];
    echo "\nSUCCESS: Campaign created with ID: $new_id\n";
    echo "URL: " . ($result['data']['canonical_url'] ?? 'N/A') . "\n";
    echo "Status: " . ($result['data']['status'] ?? 'N/A') . "\n";

    // Clean up - deactivate test campaign
    echo "\n--- Cleaning up (deactivating test campaign) ---\n";
    $deactivate = $api->deactivate_campaign($new_id);
    echo $deactivate['success'] ? "Test campaign deactivated.\n" : "Failed to deactivate: " . $deactivate['error'] . "\n";
} else {
    echo "\nFAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
}
