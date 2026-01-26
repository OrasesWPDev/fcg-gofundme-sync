<?php
/**
 * Test script for STAT-01: Draft should unpublish campaign (not deactivate)
 * Usage: wp eval-file test-draft-workflow.php
 */

$post_id = 13771;
$campaign_id = 763426;

echo "=== STAT-01 Test: Publish -> Draft -> Unpublished ===\n\n";

// Step 1: Check initial status
echo "Step 1: Check initial campaign status\n";
$api = new FCG_GFM_API_Client();
$result = $api->get_campaign($campaign_id);
if ($result['success']) {
    echo "  Initial status: " . $result['data']['status'] . "\n\n";
} else {
    echo "  Error: " . ($result['error'] ?? 'Unknown') . "\n\n";
    exit(1);
}

// Step 2: Set fund to Draft
echo "Step 2: Setting fund {$post_id} to Draft status\n";
wp_update_post([
    'ID' => $post_id,
    'post_status' => 'draft'
]);
echo "  Fund status changed to draft\n";
sleep(2); // Wait for sync to complete

// Step 3: Check campaign status after draft
echo "\nStep 3: Check campaign status after draft\n";
$result = $api->get_campaign($campaign_id);
if ($result['success']) {
    $status = $result['data']['status'];
    echo "  Campaign status: {$status}\n";
    if ($status === 'unpublished') {
        echo "  ✓ SUCCESS: Campaign is unpublished (not deactivated)\n\n";
    } else if ($status === 'deactivated') {
        echo "  ✗ FAIL: Campaign is deactivated (should be unpublished)\n\n";
    } else {
        echo "  ✗ FAIL: Unexpected status: {$status}\n\n";
    }
} else {
    echo "  Error: " . ($result['error'] ?? 'Unknown') . "\n\n";
}

echo "=== STAT-02 Test: Draft -> Publish -> Active ===\n\n";

// Step 4: Republish fund
echo "Step 4: Setting fund {$post_id} to Published status\n";
wp_update_post([
    'ID' => $post_id,
    'post_status' => 'publish'
]);
echo "  Fund status changed to publish\n";
sleep(2); // Wait for sync to complete

// Step 5: Check campaign status after republish
echo "\nStep 5: Check campaign status after republish\n";
$result = $api->get_campaign($campaign_id);
if ($result['success']) {
    $status = $result['data']['status'];
    echo "  Campaign status: {$status}\n";
    if ($status === 'active') {
        echo "  ✓ SUCCESS: Campaign is active\n\n";
    } else {
        echo "  ✗ FAIL: Campaign status is {$status} (should be active)\n\n";
    }
} else {
    echo "  Error: " . ($result['error'] ?? 'Unknown') . "\n\n";
}

echo "=== Test Complete ===\n";
