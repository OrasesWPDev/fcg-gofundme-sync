<?php
// Test script to check campaign status
$api = new FCG_GFM_API_Client();
$result = $api->get_campaign(763426);
if ($result['success']) {
    echo 'Campaign 763426 status: ' . $result['data']['status'] . PHP_EOL;
} else {
    echo 'Error: ' . ($result['error'] ?? 'Unknown') . PHP_EOL;
}
