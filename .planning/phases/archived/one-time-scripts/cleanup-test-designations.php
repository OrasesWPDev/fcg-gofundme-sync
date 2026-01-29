<?php
/**
 * Clean up orphaned test designations from Classy
 * Run with: wp eval-file /path/to/cleanup-test-designations.php
 *
 * Use --dry-run to preview without deleting
 */

if (!class_exists('FCG_GFM_API_Client')) {
    WP_CLI::error('FCG GoFundMe Sync plugin not loaded');
}

// Check for dry run mode - set to true for preview, false to delete
$dry_run = false; // Running actual deletion

if ($dry_run) {
    WP_CLI::log("DRY RUN MODE - No deletions will be made\n");
}

$api = new FCG_GFM_API_Client();

// Fetch all designations
WP_CLI::log("Fetching all designations...");
$result = $api->get_all_designations();

if (!$result['success']) {
    WP_CLI::error('API Error: ' . ($result['error'] ?? 'Unknown'));
}

$all_designations = $result['data'];
WP_CLI::log("Total designations: " . count($all_designations));

// Find test/orphan designations (no numeric external_reference_id or test patterns)
$test_patterns = [
    '/^DEBUG Test/',
    '/^E2E_Test/',
    '/^Test Designation/',
    '/^Theme_Test/',
    '/^Updated_E2E/',
    // Keeping "General Fund Project" - user requested to keep it
];

$orphans_to_delete = [];

foreach ($all_designations as $designation) {
    $name = $designation['name'] ?? '';
    $external_id = $designation['external_reference_id'] ?? null;

    // Check if it matches test patterns
    $is_test = false;
    foreach ($test_patterns as $pattern) {
        if (preg_match($pattern, $name)) {
            $is_test = true;
            break;
        }
    }

    // Also check if external_id is non-numeric (like "debug-test-...")
    if (!$is_test && $external_id && !is_numeric($external_id)) {
        $is_test = true;
    }

    if ($is_test) {
        $orphans_to_delete[] = $designation;
    }
}

if (empty($orphans_to_delete)) {
    WP_CLI::success("No test designations found to delete!");
    exit;
}

WP_CLI::log("\nFound " . count($orphans_to_delete) . " test designations to delete:\n");

foreach ($orphans_to_delete as $designation) {
    WP_CLI::log(sprintf(
        "  ID: %d | Name: %s | External: %s",
        $designation['id'],
        $designation['name'],
        $designation['external_reference_id'] ?? 'none'
    ));
}

if ($dry_run) {
    WP_CLI::log("\nDry run complete. Run without --dry-run to delete these designations.");
    exit;
}

WP_CLI::log("\nDeleting...\n");

$deleted = 0;
$errors = 0;

foreach ($orphans_to_delete as $designation) {
    $result = $api->delete_designation($designation['id']);

    if ($result['success']) {
        WP_CLI::log("  [DELETED] {$designation['id']} - {$designation['name']}");
        $deleted++;
    } else {
        WP_CLI::warning("  [ERROR] {$designation['id']} - " . ($result['error'] ?? 'Unknown'));
        $errors++;
    }

    // Small delay to avoid rate limiting
    usleep(100000); // 100ms
}

WP_CLI::log("\nResults: {$deleted} deleted, {$errors} errors");

if ($errors === 0) {
    WP_CLI::success("Cleanup complete!");
} else {
    WP_CLI::warning("Cleanup complete with some errors");
}
