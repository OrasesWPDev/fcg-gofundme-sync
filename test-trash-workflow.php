<?php
/**
 * Test script for regression: Trash/Restore should still work correctly
 * Usage: wp eval-file test-trash-workflow.php
 */

$post_id = 13771;
$campaign_id = 763426;

echo "=== Regression Test: Trash -> Deactivated (single call) ===\n\n";

// Step 1: Verify fund is published
echo "Step 1: Ensure fund is published\n";
$post = get_post($post_id);
if ($post->post_status !== 'publish') {
    wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
    sleep(2);
}
echo "  Fund status: publish\n\n";

// Step 2: Trash the fund
echo "Step 2: Trashing fund {$post_id}\n";
wp_trash_post($post_id);
echo "  Fund trashed\n";
sleep(2); // Wait for sync to complete

// Step 3: Check campaign status after trash
echo "\nStep 3: Check campaign status after trash\n";
$api = new FCG_GFM_API_Client();
$result = $api->get_campaign($campaign_id);
if ($result['success']) {
    $status = $result['data']['status'];
    echo "  Campaign status: {$status}\n";
    if ($status === 'deactivated') {
        echo "  ✓ SUCCESS: Campaign is deactivated\n\n";
    } else {
        echo "  ✗ FAIL: Campaign status is {$status} (should be deactivated)\n\n";
    }
} else {
    echo "  Error: " . ($result['error'] ?? 'Unknown') . "\n\n";
}

echo "=== Regression Test: Restore -> Reactivate+Publish -> Active ===\n\n";

// Step 4: Restore from trash
echo "Step 4: Restoring fund {$post_id} from trash\n";
wp_untrash_post($post_id);
echo "  Fund restored\n";
sleep(3); // Wait for reactivate+publish sequence to complete

// Step 5: Check campaign status after restore
echo "\nStep 5: Check campaign status after restore\n";
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

echo "=== Regression Test Complete ===\n";
echo "\nNote: Check error log for 'deactivated' messages - should appear exactly ONCE during trash\n";
