# Phase C3: Pull Sync (GoFundMe Pro → WordPress) - Implementation Plan

**PRD Reference:** `/Users/chadmacbook/projects/fcg/docs/PRD-campaigns.md` (Phase C3)
**Goal:** Sync campaign changes from GoFundMe Pro back to WordPress
**Version:** 2.2.0
**Branch:** `feature/phase-C3-campaign-pull-sync`
**Depends On:** Phase C2 (Campaign Push Sync)

---

## Overview

Add campaign pull sync to `FCG_GFM_Sync_Poller`. This extends the existing designation polling to also poll for campaign changes. When a campaign is modified in GoFundMe Pro, those changes sync back to the corresponding WordPress fund.

**Conflict Resolution:** WordPress wins (same as designations). If WordPress was modified after last sync, push WP version to GFM rather than overwriting local changes.

---

## Architecture Decision

**Option A:** Separate cron job for campaigns
**Option B:** Combined poll that fetches both designations and campaigns ✓

**Decision:** Option B - Combined poll. More efficient (single cron event) and maintains consistency between designation and campaign state.

---

## Substeps Overview

| Step | Description | Files |
|------|-------------|-------|
| C3.1 | Add campaign hash calculation method | `class-sync-poller.php` |
| C3.2 | Add `find_post_for_campaign()` method | `class-sync-poller.php` |
| C3.3 | Add `has_campaign_changed()` method | `class-sync-poller.php` |
| C3.4 | Add `should_apply_campaign_changes()` method | `class-sync-poller.php` |
| C3.5 | Add `apply_campaign_to_post()` method | `class-sync-poller.php` |
| C3.6 | Add `handle_campaign_conflict()` method | `class-sync-poller.php` |
| C3.7 | Add `poll_campaigns()` method | `class-sync-poller.php` |
| C3.8 | Integrate campaign polling into `poll()` | `class-sync-poller.php` |
| C3.9 | Add campaign meta key constants | `class-sync-poller.php` |
| C3.10 | Update plugin version to 2.2.0 | `fcg-gofundme-sync.php` |

---

## Step C3.1: Add Campaign Hash Calculation Method

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * Calculate hash for campaign change detection
 *
 * @param array $campaign Campaign data
 * @return string MD5 hash
 */
private function calculate_campaign_hash(array $campaign): string {
    $hashable = [
        'name' => $campaign['name'] ?? '',
        'overview' => $campaign['overview'] ?? '',
        'goal' => $campaign['goal'] ?? 0,
        'status' => $campaign['status'] ?? '',
    ];
    return md5(json_encode($hashable));
}
```

---

## Step C3.2: Add `find_post_for_campaign()` Method

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * Find WordPress post for a campaign
 *
 * @param array $campaign Campaign data
 * @return int|null Post ID or null
 */
private function find_post_for_campaign(array $campaign): ?int {
    $external_ref = $campaign['external_reference_id'] ?? null;

    // Priority 1: external_reference_id is the WP post ID
    if ($external_ref && is_numeric($external_ref)) {
        $post = get_post((int) $external_ref);
        if ($post && $post->post_type === 'funds') {
            return $post->ID;
        }
    }

    // Priority 2: Search by campaign ID in post meta
    $campaign_id = $campaign['id'];
    $posts = get_posts([
        'post_type' => 'funds',
        'meta_key' => '_gofundme_campaign_id',
        'meta_value' => $campaign_id,
        'posts_per_page' => 1,
        'post_status' => 'any',
    ]);

    return !empty($posts) ? $posts[0]->ID : null;
}
```

---

## Step C3.3: Add `has_campaign_changed()` Method

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * Check if campaign data has changed
 *
 * @param int $post_id Post ID
 * @param array $campaign Campaign data
 * @return bool
 */
private function has_campaign_changed(int $post_id, array $campaign): bool {
    $stored_hash = get_post_meta($post_id, '_gofundme_campaign_poll_hash', true);
    $current_hash = $this->calculate_campaign_hash($campaign);
    return $stored_hash !== $current_hash;
}
```

---

## Step C3.4: Add `should_apply_campaign_changes()` Method

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * Check if GFM campaign changes should be applied to WordPress
 *
 * @param int $post_id Post ID
 * @param array $campaign Campaign data
 * @return bool
 */
private function should_apply_campaign_changes(int $post_id, array $campaign): bool {
    $last_sync = get_post_meta($post_id, '_gofundme_last_sync', true);
    $post = get_post($post_id);

    if (!$last_sync) {
        return true; // Never synced, accept GFM data
    }

    // Check if WP was modified after last sync
    $wp_modified = strtotime($post->post_modified_gmt);
    $last_sync_time = strtotime($last_sync);

    if ($wp_modified > $last_sync_time) {
        // Conflict detected - WordPress wins
        $this->handle_campaign_conflict($post_id, $campaign);
        return false;
    }

    return true;
}
```

---

## Step C3.5: Add `apply_campaign_to_post()` Method

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * Apply campaign changes to WordPress post
 *
 * @param int $post_id Post ID
 * @param array $campaign Campaign data
 */
private function apply_campaign_to_post(int $post_id, array $campaign): void {
    $this->set_syncing_flag();

    try {
        $updates = [
            'ID' => $post_id,
        ];

        // Only update title if campaign name differs significantly
        // (campaigns might have the same name as the post already)
        $current_title = get_the_title($post_id);
        if ($campaign['name'] !== $current_title) {
            $updates['post_title'] = $campaign['name'];
        }

        // Update goal in post meta if present
        if (!empty($campaign['goal'])) {
            update_post_meta($post_id, '_fundraising_goal', $campaign['goal']);
        }

        // Update overview/description
        if (!empty($campaign['overview'])) {
            $updates['post_excerpt'] = $this->truncate_string($campaign['overview'], 500);
        }

        // Update post status based on campaign status
        if (isset($campaign['status'])) {
            $current_status = get_post_status($post_id);
            $is_active = in_array($campaign['status'], ['active', 'published'], true);

            if ($is_active && $current_status === 'draft') {
                $updates['post_status'] = 'publish';
            } elseif (!$is_active && $current_status === 'publish') {
                $updates['post_status'] = 'draft';
            }
        }

        if (count($updates) > 1) { // More than just ID
            wp_update_post($updates);
        }

        // Update campaign URL if present
        if (!empty($campaign['canonical_url'])) {
            update_post_meta($post_id, '_gofundme_campaign_url', $campaign['canonical_url']);
        }

        // Update meta
        update_post_meta($post_id, '_gofundme_campaign_poll_hash', $this->calculate_campaign_hash($campaign));
        update_post_meta($post_id, '_gofundme_sync_source', 'gofundme');
        update_post_meta($post_id, '_gofundme_last_sync', current_time('mysql'));

        $this->log("Applied GFM campaign changes to post {$post_id}");
    } finally {
        $this->clear_syncing_flag();
    }
}
```

---

## Step C3.6: Add `handle_campaign_conflict()` Method

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * Handle campaign conflict by pushing WP version to GFM (WordPress wins)
 *
 * @param int $post_id Post ID
 * @param array $campaign Campaign data
 */
private function handle_campaign_conflict(int $post_id, array $campaign): void {
    $this->log_conflict($post_id, $campaign, 'WP modified after last sync (campaign)');

    // Push WP version to GFM (WordPress wins)
    $post = get_post($post_id);

    // Build campaign data from WP post
    $data = [
        'name' => $this->truncate_string($post->post_title, 127),
        'external_reference_id' => (string) $post->ID,
    ];

    // Add goal if available
    $goal = get_post_meta($post_id, '_fundraising_goal', true);
    if (!empty($goal) && is_numeric($goal)) {
        $data['goal'] = (float) $goal;
    }

    if (!empty($post->post_excerpt)) {
        $data['overview'] = $post->post_excerpt;
    }

    $result = $this->api->update_campaign($campaign['id'], $data);

    if ($result['success']) {
        $this->log("Campaign conflict resolved: pushed WP version to GFM for post {$post_id}");
        update_post_meta($post_id, '_gofundme_last_sync', current_time('mysql'));
        update_post_meta($post_id, '_gofundme_sync_source', 'wordpress');

        // Recalculate hash based on what we pushed
        $new_hash = $this->calculate_campaign_hash([
            'name' => $data['name'],
            'overview' => $data['overview'] ?? '',
            'goal' => $data['goal'] ?? $campaign['goal'] ?? 0,
            'status' => $campaign['status'] ?? '',
        ]);
        update_post_meta($post_id, '_gofundme_campaign_poll_hash', $new_hash);
    } else {
        $this->log("Failed to push WP campaign version to GFM for post {$post_id}: {$result['error']}");
    }
}
```

---

## Step C3.7: Add `poll_campaigns()` Method

**File:** `includes/class-sync-poller.php`

**Add method:**

```php
/**
 * Poll GoFundMe Pro for campaign changes
 *
 * @return array Stats from the poll
 */
public function poll_campaigns(): array {
    $result = $this->api->get_all_campaigns();

    if (!$result['success']) {
        $this->log("Campaign poll failed: {$result['error']}");
        return ['error' => $result['error']];
    }

    $campaigns = $result['data'];
    $stats = [
        'processed' => 0,
        'updated' => 0,
        'skipped' => 0,
        'orphaned' => 0,
        'errors' => 0,
    ];

    foreach ($campaigns as $campaign) {
        $stats['processed']++;

        $post_id = $this->find_post_for_campaign($campaign);

        if (!$post_id) {
            // Orphaned campaign - no matching WP post
            $this->log("Orphan campaign found: {$campaign['id']} ({$campaign['name']}) has no WP post");
            $stats['orphaned']++;
            continue;
        }

        if (!$this->has_campaign_changed($post_id, $campaign)) {
            $stats['skipped']++;
            continue;
        }

        if ($this->should_apply_campaign_changes($post_id, $campaign)) {
            try {
                $this->apply_campaign_to_post($post_id, $campaign);
                $stats['updated']++;
            } catch (Exception $e) {
                $this->log("Error applying campaign to post {$post_id}: {$e->getMessage()}");
                $stats['errors']++;
            }
        } else {
            $stats['skipped']++;
        }
    }

    return $stats;
}
```

---

## Step C3.8: Integrate Campaign Polling into `poll()`

**File:** `includes/class-sync-poller.php`

**Modify existing `poll()` method - add after designation polling:**

```php
// After the existing designation polling loop, add:

// Poll campaigns
$this->log("Starting campaign poll...");
$campaign_stats = $this->poll_campaigns();

if (!isset($campaign_stats['error'])) {
    $this->log(sprintf(
        "Campaign poll complete: %d processed, %d updated, %d skipped, %d orphaned, %d errors",
        $campaign_stats['processed'],
        $campaign_stats['updated'],
        $campaign_stats['skipped'],
        $campaign_stats['orphaned'],
        $campaign_stats['errors']
    ));
}
```

---

## Step C3.9: Add Campaign Meta Key Constants

**File:** `includes/class-sync-poller.php`

**Add constants at class level (if not already referencing from sync-handler):**

```php
/**
 * Campaign meta keys
 */
private const META_CAMPAIGN_ID = '_gofundme_campaign_id';
private const META_CAMPAIGN_URL = '_gofundme_campaign_url';
private const META_CAMPAIGN_POLL_HASH = '_gofundme_campaign_poll_hash';
```

---

## Step C3.10: Update Plugin Version

**File:** `fcg-gofundme-sync.php`

**Update:**
1. Header comment: `* Version: 2.2.0`
2. Version constant: `define('FCG_GFM_SYNC_VERSION', '2.2.0');`

---

## Verification Tests

| Test | Command/Action | Expected Result |
|------|----------------|-----------------|
| C3.T1 | PHP syntax check | `php -l` passes for all modified files |
| C3.T2 | Run poll manually | Campaigns fetched and processed |
| C3.T3 | Modify campaign in GFM | Changes sync to WordPress |
| C3.T4 | Modify fund in WP then campaign in GFM | WP wins, pushes to GFM |
| C3.T5 | Plugin version | Shows 2.2.0 in plugins list |

### Test Commands

```bash
# T1: PHP Syntax
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync && php -l includes/class-sync-poller.php && php -l fcg-gofundme-sync.php"

# T2: Run poll and check campaigns
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp eval '\$api = new FCG_GFM_API_Client(); \$r = \$api->get_all_campaigns(); echo \"Campaigns: \" . (\$r[\"total\"] ?? 0);'"

# T3: Trigger poll
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp eval '\$p = new FCG_GFM_Sync_Poller(); \$p->poll();'"

# T5: Plugin version
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp plugin list --name=fcg-gofundme-sync --format=csv"
```

---

## Files Modified Summary

| File | Action | Changes |
|------|--------|---------|
| `includes/class-sync-poller.php` | Modified | Add campaign polling methods (~150 lines) |
| `fcg-gofundme-sync.php` | Modified | Version bump 2.1.0 → 2.2.0 |

---

## Execution Tracking

| Step | Agent | Status | Notes |
|------|-------|--------|-------|
| C3.1 | dev-agent | pending | calculate_campaign_hash() |
| C3.2 | dev-agent | pending | find_post_for_campaign() |
| C3.3 | dev-agent | pending | has_campaign_changed() |
| C3.4 | dev-agent | pending | should_apply_campaign_changes() |
| C3.5 | dev-agent | pending | apply_campaign_to_post() |
| C3.6 | dev-agent | pending | handle_campaign_conflict() |
| C3.7 | dev-agent | pending | poll_campaigns() |
| C3.8 | dev-agent | pending | Integrate into poll() |
| C3.9 | dev-agent | pending | Meta key constants |
| C3.10 | dev-agent | pending | Version bump |
| - | testing-agent | pending | Code review |
| - | deploy-agent | pending | Deploy to staging, run tests |

---

## Success Criteria

After this phase:
1. Cron poll fetches both designations AND campaigns
2. Campaign changes in GFM sync back to WordPress
3. Conflict resolution works (WordPress wins)
4. Campaign URL and goal stay updated
5. Designation pull sync still works
6. Plugin version is 2.2.0

---

## Notes for Dev Agent

1. **Reuse patterns:** Follow exact same patterns as designation polling
2. **Conflict log:** Reuse existing conflict logging with campaign indicator
3. **Syncing flag:** Use same transient to prevent loops
4. **Order:** Poll designations first, then campaigns (in same cron run)
5. **Goal sync:** Campaign goal updates `_fundraising_goal` post meta
