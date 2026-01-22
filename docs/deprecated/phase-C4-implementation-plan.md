# Phase C4: Admin UI for Campaigns - Implementation Plan

**PRD Reference:** `/Users/chadmacbook/projects/fcg/docs/PRD-campaigns.md` (Phase C4)
**Goal:** Add visibility into campaign sync status in WordPress admin
**Version:** 2.3.0
**Branch:** `feature/phase-C4-campaign-admin-ui`
**Depends On:** Phase C3 (Campaign Pull Sync)

---

## Overview

Extend `FCG_GFM_Admin_UI` to show campaign information alongside existing designation status. Add a campaign column to the funds list, campaign info in the meta box, and campaign-specific settings.

---

## Substeps Overview

| Step | Description | Files |
|------|-------------|-------|
| C4.1 | Add "Campaign" column to funds list | `class-admin-ui.php` |
| C4.2 | Render campaign column with link | `class-admin-ui.php` |
| C4.3 | Add campaign info to sync meta box | `class-admin-ui.php` |
| C4.4 | Add "Create Campaign" button for funds without one | `class-admin-ui.php` |
| C4.5 | Add campaign sync status to settings page | `class-admin-ui.php` |
| C4.6 | Update admin CSS for campaign styles | `assets/css/admin.css` |
| C4.7 | Add AJAX handler for manual campaign sync | `class-admin-ui.php` |
| C4.8 | Update admin JS for campaign actions | `assets/js/admin.js` |
| C4.9 | Update plugin version to 2.3.0 | `fcg-gofundme-sync.php` |

---

## Step C4.1: Add "Campaign" Column to Funds List

**File:** `includes/class-admin-ui.php`

**Modify `add_sync_column()` to add campaign column:**

```php
/**
 * Add sync status columns to funds list table
 *
 * @param array $columns Existing columns
 * @return array Modified columns
 */
public function add_sync_column(array $columns): array {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['fcg_sync_status'] = 'Designation';
            $new_columns['fcg_campaign_status'] = 'Campaign';
        }
    }
    return $new_columns;
}
```

---

## Step C4.2: Render Campaign Column with Link

**File:** `includes/class-admin-ui.php`

**Add new method and update hook registration:**

```php
// In constructor, add:
add_action('manage_funds_posts_custom_column', [$this, 'render_campaign_column'], 10, 2);

/**
 * Render campaign status column content
 *
 * @param string $column Column name
 * @param int $post_id Post ID
 */
public function render_campaign_column(string $column, int $post_id): void {
    if ($column !== 'fcg_campaign_status') {
        return;
    }

    $campaign_id = get_post_meta($post_id, '_gofundme_campaign_id', true);
    $campaign_url = get_post_meta($post_id, '_gofundme_campaign_url', true);
    $last_sync = get_post_meta($post_id, '_gofundme_last_sync', true);

    if (!$campaign_id) {
        echo '<span class="fcg-sync-status fcg-sync-not-linked" title="No campaign linked">';
        echo '<a href="#" class="fcg-create-campaign" data-post-id="' . esc_attr($post_id) . '">Create</a>';
        echo '</span>';
        return;
    }

    // Build display with link
    $display_html = '';

    if ($campaign_url) {
        $display_html .= '<a href="' . esc_url($campaign_url) . '" target="_blank" class="fcg-campaign-link" title="View donation page">';
        $display_html .= '<span class="dashicons dashicons-external"></span>';
        $display_html .= '</a> ';
    }

    if ($last_sync) {
        $last_sync_time = strtotime($last_sync);
        $fifteen_min_ago = time() - (15 * 60);

        if ($last_sync_time > $fifteen_min_ago) {
            $display_html .= '<span class="fcg-sync-status fcg-sync-synced" title="Campaign synced: ' . esc_attr($last_sync) . '">Synced</span>';
        } else {
            $display_html .= '<span class="fcg-sync-status fcg-sync-pending" title="Campaign last synced: ' . esc_attr($last_sync) . '">Pending</span>';
        }
    } else {
        $display_html .= '<span class="fcg-sync-status fcg-sync-pending" title="Campaign never synced">Pending</span>';
    }

    echo $display_html;
}
```

---

## Step C4.3: Add Campaign Info to Sync Meta Box

**File:** `includes/class-admin-ui.php`

**Modify `render_sync_meta_box()` to include campaign info:**

```php
/**
 * Render sync status meta box
 *
 * @param WP_Post $post Current post
 */
public function render_sync_meta_box(WP_Post $post): void {
    $designation_id = get_post_meta($post->ID, '_gofundme_designation_id', true);
    $campaign_id = get_post_meta($post->ID, '_gofundme_campaign_id', true);
    $campaign_url = get_post_meta($post->ID, '_gofundme_campaign_url', true);
    $last_sync = get_post_meta($post->ID, '_gofundme_last_sync', true);
    $sync_source = get_post_meta($post->ID, '_gofundme_sync_source', true);
    $sync_error = get_post_meta($post->ID, '_gofundme_sync_error', true);

    wp_nonce_field('fcg_gfm_sync_now', 'fcg_gfm_sync_nonce');
    ?>
    <div class="fcg-sync-meta-box">
        <h4 style="margin: 0 0 10px;">Designation</h4>
        <p>
            <strong>ID:</strong>
            <?php if ($designation_id): ?>
                <a href="https://www.classy.org/admin/designations/<?php echo esc_attr($designation_id); ?>" target="_blank">
                    <?php echo esc_html($designation_id); ?> <span class="dashicons dashicons-external"></span>
                </a>
            <?php else: ?>
                <em>Not linked</em>
            <?php endif; ?>
        </p>

        <hr style="margin: 15px 0;">

        <h4 style="margin: 0 0 10px;">Campaign</h4>
        <p>
            <strong>ID:</strong>
            <?php if ($campaign_id): ?>
                <?php echo esc_html($campaign_id); ?>
            <?php else: ?>
                <em>Not linked</em>
            <?php endif; ?>
        </p>

        <?php if ($campaign_url): ?>
        <p>
            <strong>Donation Page:</strong><br>
            <a href="<?php echo esc_url($campaign_url); ?>" target="_blank" class="button button-small">
                <span class="dashicons dashicons-external" style="line-height: 1.4;"></span> View Campaign
            </a>
        </p>
        <?php endif; ?>

        <hr style="margin: 15px 0;">

        <h4 style="margin: 0 0 10px;">Sync Status</h4>
        <p>
            <strong>Last Sync:</strong><br>
            <?php echo $last_sync ? esc_html($last_sync) : '<em>Never</em>'; ?>
        </p>

        <p>
            <strong>Last Source:</strong><br>
            <?php echo $sync_source ? esc_html(ucfirst($sync_source)) : '<em>Unknown</em>'; ?>
        </p>

        <?php if ($sync_error): ?>
        <p class="fcg-sync-error-message">
            <strong>Error:</strong><br>
            <?php echo esc_html($sync_error); ?>
        </p>
        <?php endif; ?>

        <hr style="margin: 15px 0;">

        <p>
            <?php if ($designation_id || $campaign_id): ?>
            <button type="button" class="button fcg-sync-now-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                <span class="dashicons dashicons-update" style="line-height: 1.4;"></span> Sync Now
            </button>
            <?php else: ?>
            <button type="button" class="button button-primary fcg-create-all-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                <span class="dashicons dashicons-plus-alt" style="line-height: 1.4;"></span> Create Designation & Campaign
            </button>
            <?php endif; ?>
            <span class="spinner"></span>
        </p>
    </div>
    <?php
}
```

---

## Step C4.4: Add "Create Campaign" Button Handler

**File:** `includes/class-admin-ui.php`

**Add AJAX handler for creating campaign:**

```php
// In constructor, add:
add_action('wp_ajax_fcg_gfm_create_campaign', [$this, 'ajax_create_campaign']);

/**
 * Handle AJAX create campaign request
 */
public function ajax_create_campaign(): void {
    check_ajax_referer('fcg_gfm_sync_now', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'funds') {
        wp_send_json_error('Invalid post');
    }

    // Check if already has campaign
    $existing_campaign = get_post_meta($post_id, '_gofundme_campaign_id', true);
    if ($existing_campaign) {
        wp_send_json_error('Campaign already exists');
    }

    // Trigger sync by updating the post (sync handler will create campaign)
    wp_update_post([
        'ID' => $post_id,
        'post_modified' => current_time('mysql'),
        'post_modified_gmt' => current_time('mysql', true),
    ]);

    // Check if campaign was created
    $campaign_id = get_post_meta($post_id, '_gofundme_campaign_id', true);
    $campaign_url = get_post_meta($post_id, '_gofundme_campaign_url', true);

    if ($campaign_id) {
        wp_send_json_success([
            'message' => 'Campaign created successfully',
            'campaign_id' => $campaign_id,
            'campaign_url' => $campaign_url,
        ]);
    } else {
        wp_send_json_error('Failed to create campaign - check error logs');
    }
}
```

---

## Step C4.5: Add Campaign Sync Status to Settings Page

**File:** `includes/class-admin-ui.php`

**Modify `render_settings_page()` to include campaign stats:**

```php
// Add after the existing "Sync Status" section:

<h2>Campaign Status</h2>
<?php
$campaign_stats = $this->get_campaign_stats();
?>
<table class="widefat" style="max-width: 400px;">
    <tbody>
        <tr>
            <td><strong>Total Funds</strong></td>
            <td><?php echo esc_html($campaign_stats['total']); ?></td>
        </tr>
        <tr>
            <td><strong>With Campaigns</strong></td>
            <td><?php echo esc_html($campaign_stats['with_campaigns']); ?></td>
        </tr>
        <tr>
            <td><strong>Without Campaigns</strong></td>
            <td><?php echo esc_html($campaign_stats['without_campaigns']); ?></td>
        </tr>
        <tr>
            <td><strong>With Designations</strong></td>
            <td><?php echo esc_html($campaign_stats['with_designations']); ?></td>
        </tr>
    </tbody>
</table>

// Add helper method:

/**
 * Get campaign statistics
 *
 * @return array Stats
 */
private function get_campaign_stats(): array {
    $posts = get_posts([
        'post_type' => 'funds',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids',
    ]);

    $stats = [
        'total' => count($posts),
        'with_campaigns' => 0,
        'without_campaigns' => 0,
        'with_designations' => 0,
    ];

    foreach ($posts as $post_id) {
        $campaign_id = get_post_meta($post_id, '_gofundme_campaign_id', true);
        $designation_id = get_post_meta($post_id, '_gofundme_designation_id', true);

        if ($campaign_id) {
            $stats['with_campaigns']++;
        } else {
            $stats['without_campaigns']++;
        }

        if ($designation_id) {
            $stats['with_designations']++;
        }
    }

    return $stats;
}
```

---

## Step C4.6: Update Admin CSS for Campaign Styles

**File:** `assets/css/admin.css`

**Add campaign-specific styles:**

```css
/* Campaign column link */
.fcg-campaign-link {
    color: #0073aa;
    text-decoration: none;
}

.fcg-campaign-link:hover {
    color: #00a0d2;
}

.fcg-campaign-link .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    vertical-align: middle;
}

/* Create campaign link in column */
.fcg-create-campaign {
    color: #0073aa;
    text-decoration: none;
    font-size: 12px;
}

.fcg-create-campaign:hover {
    color: #00a0d2;
    text-decoration: underline;
}

/* Meta box sections */
.fcg-sync-meta-box h4 {
    color: #23282d;
    font-size: 13px;
}

.fcg-sync-meta-box hr {
    border: 0;
    border-top: 1px solid #ddd;
}

/* Campaign stats table */
.fcg-campaign-stats td {
    padding: 8px 12px;
}

/* Create all button */
.fcg-create-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
```

---

## Step C4.7: Add AJAX Handler for Manual Campaign Sync

**File:** `includes/class-admin-ui.php`

**Update existing `ajax_sync_now()` to handle campaign sync:**

```php
/**
 * Handle AJAX sync now request
 */
public function ajax_sync_now(): void {
    check_ajax_referer('fcg_gfm_sync_now', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

    if ($post_id) {
        // Sync single post by triggering an update
        $post = get_post($post_id);
        if ($post && $post->post_type === 'funds') {
            // Update the post to trigger outbound sync (both designation and campaign)
            wp_update_post([
                'ID' => $post_id,
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', true),
            ]);

            // Get updated meta
            $designation_id = get_post_meta($post_id, '_gofundme_designation_id', true);
            $campaign_id = get_post_meta($post_id, '_gofundme_campaign_id', true);
            $campaign_url = get_post_meta($post_id, '_gofundme_campaign_url', true);

            wp_send_json_success([
                'message' => 'Sync triggered for post ' . $post_id,
                'designation_id' => $designation_id,
                'campaign_id' => $campaign_id,
                'campaign_url' => $campaign_url,
            ]);
        } else {
            wp_send_json_error('Invalid post');
        }
    } else {
        // Sync all via poller
        $poller = new FCG_GFM_Sync_Poller();
        $poller->poll();
        wp_send_json_success(['message' => 'Full sync completed']);
    }
}
```

---

## Step C4.8: Update Admin JS for Campaign Actions

**File:** `assets/js/admin.js`

**Add campaign-specific handlers:**

```javascript
jQuery(document).ready(function($) {
    // Existing sync now handler
    $('.fcg-sync-now-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var $spinner = $btn.siblings('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(fcgGfmAdmin.ajaxUrl, {
            action: 'fcg_gfm_sync_now',
            nonce: fcgGfmAdmin.nonce,
            post_id: postId
        }, function(response) {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);

            if (response.success) {
                alert('Sync complete!');
                location.reload();
            } else {
                alert('Sync failed: ' + response.data);
            }
        }).fail(function() {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
            alert('Request failed');
        });
    });

    // Create campaign handler
    $('.fcg-create-campaign').on('click', function(e) {
        e.preventDefault();
        var $link = $(this);
        var postId = $link.data('post-id');

        if (!confirm('Create a GoFundMe Pro campaign for this fund?')) {
            return;
        }

        $link.text('Creating...');

        $.post(fcgGfmAdmin.ajaxUrl, {
            action: 'fcg_gfm_create_campaign',
            nonce: fcgGfmAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                alert('Campaign created!');
                location.reload();
            } else {
                alert('Failed: ' + response.data);
                $link.text('Create');
            }
        }).fail(function() {
            alert('Request failed');
            $link.text('Create');
        });
    });

    // Create all (designation + campaign) handler
    $('.fcg-create-all-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var $spinner = $btn.siblings('.spinner');

        if (!confirm('Create both designation and campaign for this fund?')) {
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(fcgGfmAdmin.ajaxUrl, {
            action: 'fcg_gfm_sync_now',
            nonce: fcgGfmAdmin.nonce,
            post_id: postId
        }, function(response) {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);

            if (response.success) {
                alert('Designation and campaign created!');
                location.reload();
            } else {
                alert('Failed: ' + response.data);
            }
        }).fail(function() {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
            alert('Request failed');
        });
    });

    // Sync all button
    $('#fcg-sync-all').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $spinner = $btn.siblings('.spinner');

        if (!confirm('Run full sync for all funds? This may take a moment.')) {
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(fcgGfmAdmin.ajaxUrl, {
            action: 'fcg_gfm_sync_now',
            nonce: fcgGfmAdmin.nonce,
            post_id: 0
        }, function(response) {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);

            if (response.success) {
                alert('Full sync complete!');
                location.reload();
            } else {
                alert('Sync failed: ' + response.data);
            }
        }).fail(function() {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
            alert('Request failed');
        });
    });
});
```

---

## Step C4.9: Update Plugin Version

**File:** `fcg-gofundme-sync.php`

**Update:**
1. Header comment: `* Version: 2.3.0`
2. Version constant: `define('FCG_GFM_SYNC_VERSION', '2.3.0');`

---

## Verification Tests

| Test | Command/Action | Expected Result |
|------|----------------|-----------------|
| C4.T1 | PHP syntax check | `php -l` passes for all modified files |
| C4.T2 | View funds list | Campaign column visible with status |
| C4.T3 | Click campaign link | Opens donation page in new tab |
| C4.T4 | Click "Create" for fund without campaign | Campaign created |
| C4.T5 | View fund edit screen | Meta box shows campaign info |
| C4.T6 | View settings page | Campaign stats displayed |
| C4.T7 | Click "Sync Now" | Both designation and campaign sync |
| C4.T8 | Plugin version | Shows 2.3.0 in plugins list |

### Test Commands

```bash
# T1: PHP Syntax
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync && php -l includes/class-admin-ui.php && php -l fcg-gofundme-sync.php"

# T8: Plugin version
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp plugin list --name=fcg-gofundme-sync --format=csv"
```

---

## Files Modified Summary

| File | Action | Changes |
|------|--------|---------|
| `includes/class-admin-ui.php` | Modified | Campaign column, meta box updates, AJAX handlers (~150 lines) |
| `assets/css/admin.css` | Modified | Campaign styles (~30 lines) |
| `assets/js/admin.js` | Modified | Campaign action handlers (~50 lines) |
| `fcg-gofundme-sync.php` | Modified | Version bump 2.2.0 → 2.3.0 |

---

## Execution Tracking

| Step | Agent | Status | Notes |
|------|-------|--------|-------|
| C4.1 | dev-agent | pending | Add campaign column |
| C4.2 | dev-agent | pending | Render campaign column |
| C4.3 | dev-agent | pending | Update meta box |
| C4.4 | dev-agent | pending | Create campaign AJAX |
| C4.5 | dev-agent | pending | Settings page stats |
| C4.6 | dev-agent | pending | CSS updates |
| C4.7 | dev-agent | pending | Update sync AJAX |
| C4.8 | dev-agent | pending | JS handlers |
| C4.9 | dev-agent | pending | Version bump |
| - | testing-agent | pending | Code review |
| - | deploy-agent | pending | Deploy to staging, run tests |

---

## Success Criteria

After this phase:
1. Funds list shows both Designation and Campaign columns
2. Campaign column shows link to donation page
3. Meta box displays campaign ID and URL
4. "Create" link works for funds without campaigns
5. Settings page shows campaign statistics
6. Sync buttons work for both designation and campaign
7. Plugin version is 2.3.0

---

## Notes for Dev Agent

1. **Column order:** Designation first, then Campaign (after title)
2. **External links:** Open in new tab with dashicons-external
3. **Button states:** Disable during AJAX, show spinner
4. **Error handling:** Show user-friendly error messages
5. **Reload:** Refresh page after successful actions to show updated state
