# Phase C2: Push Sync (WordPress → GoFundMe Pro Campaigns) - Implementation Plan

**PRD Reference:** `/Users/chadmacbook/projects/fcg/docs/PRD-campaigns.md` (Phase C2)
**Goal:** Create/update campaigns when funds are published/modified
**Version:** 2.1.0
**Branch:** `feature/phase-C2-campaign-push-sync`
**Depends On:** Phase C1 (Campaign API methods)

---

## Overview

Add campaign push sync to `FCG_GFM_Sync_Handler`. When a WordPress fund is created/updated/deleted, the corresponding GoFundMe Pro campaign should be synchronized. This mirrors the existing designation sync pattern.

---

## Sync Behavior (from PRD)

| WordPress Action | Campaign Action |
|------------------|-----------------|
| Publish fund | Create campaign |
| Update fund | Update campaign |
| Unpublish/Draft | Deactivate campaign |
| Trash | Deactivate campaign |
| Restore from trash | Reactivate campaign (if possible) |
| Permanent delete | **Deactivate** (preserve donation history) |

**Note:** Campaigns are deactivated on delete, NOT deleted, to preserve donation history.

---

## Required Campaign Fields (from C1 research)

| Field | Source | Notes |
|-------|--------|-------|
| `name` | `post_title` | Max 127 chars |
| `type` | Constant | "crowdfunding" or as discovered in C1 |
| `goal` | ACF field or default | Fundraising goal amount |
| `started_at` | `post_date` or now | Campaign start date |
| `timezone_identifier` | Constant | "America/New_York" |
| `external_reference_id` | `post_id` | Links campaign to WP post |

---

## Substeps Overview

| Step | Description | Files |
|------|-------------|-------|
| C2.1 | Add `build_campaign_data()` method | `class-sync-handler.php` |
| C2.2 | Add `create_campaign()` private method | `class-sync-handler.php` |
| C2.3 | Add `update_campaign()` private method | `class-sync-handler.php` |
| C2.4 | Add `sync_campaign_to_gofundme()` orchestrator method | `class-sync-handler.php` |
| C2.5 | Hook campaign sync into `on_save_fund()` | `class-sync-handler.php` |
| C2.6 | Hook campaign deactivation into `on_trash_fund()` | `class-sync-handler.php` |
| C2.7 | Hook campaign restore into `on_untrash_fund()` | `class-sync-handler.php` |
| C2.8 | Hook campaign deactivation into `on_delete_fund()` | `class-sync-handler.php` |
| C2.9 | Add campaign status handling in `on_status_change()` | `class-sync-handler.php` |
| C2.10 | Add helper methods for campaign meta | `class-sync-handler.php` |
| C2.11 | Update plugin version to 2.1.0 | `fcg-gofundme-sync.php` |

---

## Step C2.1: Add `build_campaign_data()` Method

**File:** `includes/class-sync-handler.php`

**Add method (after `build_designation_data()`):**

```php
/**
 * Build campaign data from WordPress post
 *
 * @param WP_Post $post Post object
 * @return array Campaign data for API
 */
private function build_campaign_data(WP_Post $post): array {
    $data = [
        'name'                  => $this->truncate_string($post->post_title, 127),
        'type'                  => 'crowdfunding', // Default type
        'goal'                  => $this->get_fund_goal($post->ID),
        'started_at'            => $post->post_date,
        'timezone_identifier'   => 'America/New_York',
        'external_reference_id' => (string) $post->ID,
    ];

    // Add description/overview from content or excerpt
    if (!empty($post->post_content)) {
        $data['overview'] = $this->truncate_string(
            wp_strip_all_tags($post->post_content),
            2000
        );
    } elseif (!empty($post->post_excerpt)) {
        $data['overview'] = $post->post_excerpt;
    }

    return $data;
}

/**
 * Get fundraising goal for a fund
 *
 * @param int $post_id Post ID
 * @return float Goal amount (default 1000)
 */
private function get_fund_goal(int $post_id): float {
    // Try ACF field first
    if (function_exists('get_field')) {
        $gfm_settings = get_field(self::ACF_GROUP_KEY, $post_id);
        if (!empty($gfm_settings['fundraising_goal']) && is_numeric($gfm_settings['fundraising_goal'])) {
            return (float) $gfm_settings['fundraising_goal'];
        }
    }

    // Fall back to post meta
    $goal = get_post_meta($post_id, '_fundraising_goal', true);
    if (!empty($goal) && is_numeric($goal)) {
        return (float) $goal;
    }

    // Default goal
    return 1000.00;
}
```

---

## Step C2.2: Add `create_campaign()` Private Method

**File:** `includes/class-sync-handler.php`

**Add method:**

```php
/**
 * Create a new campaign in GoFundMe Pro
 *
 * @param int $post_id WordPress post ID
 * @param array $data Campaign data
 */
private function create_campaign_in_gfm(int $post_id, array $data): void {
    $result = $this->api->create_campaign($data);

    if ($result['success'] && !empty($result['data']['id'])) {
        $campaign_id = $result['data']['id'];
        $campaign_url = $result['data']['canonical_url'] ?? '';

        // Store campaign meta
        update_post_meta($post_id, self::META_CAMPAIGN_ID, $campaign_id);
        update_post_meta($post_id, self::META_CAMPAIGN_URL, $campaign_url);
        update_post_meta($post_id, self::META_KEY_LAST_SYNC, current_time('mysql'));

        $this->log_info("Created campaign {$campaign_id} for post {$post_id}");
    } else {
        $error = $result['error'] ?? 'Unknown error';
        $this->log_error("Failed to create campaign for post {$post_id}: {$error}");
    }
}
```

---

## Step C2.3: Add `update_campaign()` Private Method

**File:** `includes/class-sync-handler.php`

**Add method:**

```php
/**
 * Update an existing campaign in GoFundMe Pro
 *
 * @param int $post_id WordPress post ID
 * @param string|int $campaign_id GoFundMe Pro campaign ID
 * @param array $data Campaign data
 */
private function update_campaign_in_gfm(int $post_id, $campaign_id, array $data): void {
    $result = $this->api->update_campaign($campaign_id, $data);

    if ($result['success']) {
        update_post_meta($post_id, self::META_KEY_LAST_SYNC, current_time('mysql'));
        $this->log_info("Updated campaign {$campaign_id} for post {$post_id}");
    } else {
        $error = $result['error'] ?? 'Unknown error';
        $this->log_error("Failed to update campaign {$campaign_id} for post {$post_id}: {$error}");
    }
}
```

---

## Step C2.4: Add `sync_campaign_to_gofundme()` Orchestrator Method

**File:** `includes/class-sync-handler.php`

**Add method:**

```php
/**
 * Sync fund to GoFundMe Pro as a campaign
 *
 * @param int $post_id Post ID
 * @param WP_Post $post Post object
 */
private function sync_campaign_to_gofundme(int $post_id, WP_Post $post): void {
    // Build campaign data from post
    $campaign_data = $this->build_campaign_data($post);

    // Get existing campaign ID
    $campaign_id = $this->get_campaign_id($post_id);

    if ($campaign_id) {
        // Update existing campaign
        $this->update_campaign_in_gfm($post_id, $campaign_id, $campaign_data);
    } else {
        // Only create new campaign if post is published
        if ($post->post_status === 'publish') {
            $this->create_campaign_in_gfm($post_id, $campaign_data);
        }
    }
}
```

---

## Step C2.5: Hook Campaign Sync into `on_save_fund()`

**File:** `includes/class-sync-handler.php`

**Modify `on_save_fund()` - add campaign sync after designation sync:**

```php
// After the existing designation sync logic, add:

// Sync campaign (parallel to designation)
$this->sync_campaign_to_gofundme($post_id, $post);
```

---

## Step C2.6: Hook Campaign Deactivation into `on_trash_fund()`

**File:** `includes/class-sync-handler.php`

**Modify `on_trash_fund()` - add campaign deactivation:**

```php
// After the existing designation deactivation, add:

// Deactivate campaign
$campaign_id = $this->get_campaign_id($post_id);
if ($campaign_id) {
    $result = $this->api->deactivate_campaign($campaign_id);
    if ($result['success']) {
        $this->log_info("Deactivated campaign {$campaign_id} for trashed post {$post_id}");
    }
}
```

---

## Step C2.7: Hook Campaign Restore into `on_untrash_fund()`

**File:** `includes/class-sync-handler.php`

**Modify `on_untrash_fund()` - attempt to reactivate campaign:**

```php
// After the existing designation reactivation, add:

// Note: Classy API may not support reactivating campaigns
// We'll update the campaign to trigger any available reactivation
$campaign_id = $this->get_campaign_id($post_id);
if ($campaign_id) {
    $campaign_data = $this->build_campaign_data($post);
    $result = $this->api->update_campaign($campaign_id, $campaign_data);
    if ($result['success']) {
        $this->log_info("Updated campaign {$campaign_id} for restored post {$post_id}");
    }
}
```

---

## Step C2.8: Hook Campaign Deactivation into `on_delete_fund()`

**File:** `includes/class-sync-handler.php`

**Modify `on_delete_fund()` - deactivate campaign (not delete):**

```php
// After the existing designation deletion, add:

// Deactivate campaign (preserve donation history - do NOT delete)
$campaign_id = $this->get_campaign_id($post_id);
if ($campaign_id) {
    $result = $this->api->deactivate_campaign($campaign_id);
    if ($result['success']) {
        $this->log_info("Deactivated campaign {$campaign_id} for deleted post {$post_id}");
    }
}
```

---

## Step C2.9: Add Campaign Status Handling in `on_status_change()`

**File:** `includes/class-sync-handler.php`

**Modify `on_status_change()` - handle campaign status:**

```php
// After the existing designation status update, add:

// Update campaign based on publish status
$campaign_id = $this->get_campaign_id($post->ID);
if ($campaign_id) {
    if ($is_active) {
        // Reactivate: update campaign data
        $campaign_data = $this->build_campaign_data($post);
        $result = $this->api->update_campaign($campaign_id, $campaign_data);
    } else {
        // Deactivate campaign
        $result = $this->api->deactivate_campaign($campaign_id);
    }

    if ($result['success']) {
        $status_text = $is_active ? 'activated' : 'deactivated';
        $this->log_info("Status change: {$status_text} campaign {$campaign_id} for post {$post->ID}");
    }
}
```

---

## Step C2.10: Add Helper Methods for Campaign Meta

**File:** `includes/class-sync-handler.php`

**Add methods:**

```php
/**
 * Get campaign ID from post meta
 *
 * @param int $post_id Post ID
 * @return string|null Campaign ID or null
 */
private function get_campaign_id(int $post_id): ?string {
    $campaign_id = get_post_meta($post_id, self::META_CAMPAIGN_ID, true);
    return !empty($campaign_id) ? (string) $campaign_id : null;
}

/**
 * Get campaign URL from post meta
 *
 * @param int $post_id Post ID
 * @return string|null Campaign URL or null
 */
private function get_campaign_url(int $post_id): ?string {
    $campaign_url = get_post_meta($post_id, self::META_CAMPAIGN_URL, true);
    return !empty($campaign_url) ? $campaign_url : null;
}
```

---

## Step C2.11: Update Plugin Version

**File:** `fcg-gofundme-sync.php`

**Update:**
1. Header comment: `* Version: 2.1.0`
2. Version constant: `define('FCG_GFM_SYNC_VERSION', '2.1.0');`

---

## Verification Tests

| Test | Command/Action | Expected Result |
|------|----------------|-----------------|
| C2.T1 | PHP syntax check | `php -l` passes for all modified files |
| C2.T2 | Publish new fund | Campaign created in GFM, meta stored |
| C2.T3 | Update published fund | Campaign updated in GFM |
| C2.T4 | Unpublish fund (draft) | Campaign deactivated |
| C2.T5 | Trash fund | Campaign deactivated |
| C2.T6 | Restore from trash | Campaign updated (reactivated if supported) |
| C2.T7 | Permanent delete | Campaign deactivated (NOT deleted) |
| C2.T8 | Plugin version | Shows 2.1.0 in plugins list |

### Test Commands

```bash
# T1: PHP Syntax
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync && php -l includes/class-sync-handler.php && php -l fcg-gofundme-sync.php"

# T2: Create test fund and verify campaign
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp post create --post_type=funds --post_title='Test Campaign Fund' --post_status=publish --porcelain"
# Then verify campaign was created:
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp post meta get <POST_ID> _gofundme_campaign_id"

# T5: Plugin version
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp plugin list --name=fcg-gofundme-sync --format=csv"
```

---

## Files Modified Summary

| File | Action | Changes |
|------|--------|---------|
| `includes/class-sync-handler.php` | Modified | Add campaign sync methods (~100 lines) |
| `fcg-gofundme-sync.php` | Modified | Version bump 2.0.0 → 2.1.0 |

---

## Execution Tracking

| Step | Agent | Status | Notes |
|------|-------|--------|-------|
| C2.1 | dev-agent | pending | build_campaign_data() |
| C2.2 | dev-agent | pending | create_campaign_in_gfm() |
| C2.3 | dev-agent | pending | update_campaign_in_gfm() |
| C2.4 | dev-agent | pending | sync_campaign_to_gofundme() |
| C2.5 | dev-agent | pending | Hook into on_save_fund() |
| C2.6 | dev-agent | pending | Hook into on_trash_fund() |
| C2.7 | dev-agent | pending | Hook into on_untrash_fund() |
| C2.8 | dev-agent | pending | Hook into on_delete_fund() |
| C2.9 | dev-agent | pending | Hook into on_status_change() |
| C2.10 | dev-agent | pending | Helper methods |
| C2.11 | dev-agent | pending | Version bump |
| - | testing-agent | pending | Code review |
| - | deploy-agent | pending | Deploy to staging, run tests |

---

## Success Criteria

After this phase:
1. Publishing a fund creates a campaign in GoFundMe Pro
2. Campaign ID and URL stored in post meta
3. Updating a fund updates the campaign
4. Unpublishing/trashing deactivates the campaign
5. Permanent delete deactivates (does not delete) the campaign
6. Designation sync continues to work in parallel
7. Plugin version is 2.1.0

---

## Notes for Dev Agent

1. **Keep designation sync intact:** Campaign sync runs in parallel, not replacing designation sync
2. **Deactivate, don't delete:** Campaigns preserve donation history
3. **Error handling:** Follow existing patterns in designation sync
4. **Meta keys:** Use the constants defined in C1 (META_CAMPAIGN_ID, META_CAMPAIGN_URL)
5. **Testing:** Test both new funds and existing funds with designations
