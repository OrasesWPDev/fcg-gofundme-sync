# Phase 1: Configuration - Research

**Researched:** 2026-01-23
**Domain:** WordPress settings integration with Classy API validation
**Confidence:** HIGH

## Summary

This phase adds template campaign ID configuration and fundraising goal field to the existing FCG GoFundMe Pro Sync plugin. Research confirms the existing codebase follows WordPress Settings API patterns and already has the infrastructure for API validation, AJAX interactions, and admin UI components.

**Key findings:**
- Existing `class-admin-ui.php` uses WordPress Settings API with `register_setting()` for options storage
- API client (`class-api-client.php`) already has `get_campaign()` method - can be used for validation
- Classy API endpoint `GET /campaigns/{id}` returns campaign details including `name` field
- Existing cron patterns use `wp_schedule_event()` for recurring tasks; single events use `wp_schedule_single_event()`
- Settings validation pattern: use `sanitize_callback` in `register_setting()` with `add_settings_error()` for user feedback

**Primary recommendation:** Follow existing patterns in `class-admin-ui.php` for settings registration and use API client's existing `get_campaign()` method for validation.

## Standard Stack

### Core (Already in Codebase)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Settings API | WP 5.8+ | Settings registration and rendering | Native WordPress approach, handles sanitization/validation |
| WordPress Options API | WP 5.8+ | Persistent storage | `get_option()`, `update_option()` - standard for plugin settings |
| WordPress WP-Cron | WP 5.8+ | Background task scheduling | Built-in job scheduler for background validation |
| Classy API v2.0 | 2.0 | Campaign validation | Already integrated in `FCG_GFM_API_Client` |

### Supporting (Already in Codebase)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| jQuery | WP bundled | AJAX interactions | Admin settings page interactivity |
| WordPress AJAX API | WP 5.8+ | Async validation requests | Real-time API checks without page reload |
| WordPress Transients API | WP 5.8+ | Temporary cache | OAuth tokens already use transients |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Options API | ACF fields | ACF adds complexity; Options API is simpler for plugin-level settings |
| Post meta | Options API | Post meta is for per-fund data (goal); Options API is for global settings (template ID) |
| WP-Cron | Action Scheduler | Action Scheduler better for reliability, but adds dependency; WP-Cron sufficient for non-critical background validation |

**Installation:**
No new dependencies required - all functionality exists in WordPress core and current codebase.

## Architecture Patterns

### Existing Settings Page Structure
```
class-admin-ui.php (FCG_GFM_Admin_UI)
├── register_settings()    # Lines 187-196: Uses register_setting()
├── render_settings_page() # Lines 201-284: Form table structure
└── Settings storage: Options API (get_option, update_option)
```

**Pattern observed:**
- Settings registered in `admin_init` hook (line 34)
- Each setting uses `register_setting('fcg_gfm_sync', 'option_name', $args)`
- Form uses `settings_fields('fcg_gfm_sync')` to handle nonces
- Settings page under Funds menu via `add_submenu_page()`

### Pattern 1: Settings Registration with Validation
**What:** Register a new option with sanitize callback for validation
**When to use:** Adding template campaign ID setting
**Example:**
```php
// Source: class-admin-ui.php lines 187-196 + WordPress Settings API best practices
public function register_settings(): void {
    register_setting('fcg_gfm_sync', 'fcg_gfm_template_campaign_id', [
        'type' => 'integer',
        'default' => 0,
        'sanitize_callback' => [$this, 'validate_template_campaign_id'],
    ]);
}

public function validate_template_campaign_id($input) {
    // Validate against Classy API
    $api = new FCG_GFM_API_Client();
    $result = $api->get_campaign($input);

    if (!$result['success']) {
        // Check if API unreachable vs invalid ID
        if (strpos($result['error'], 'connection') !== false) {
            // Schedule background re-validation
            wp_schedule_single_event(time() + 900, 'fcg_gfm_revalidate_template');
            add_settings_error(
                'fcg_gfm_template_campaign_id',
                'api_unreachable',
                'Template ID saved with warning: Could not verify with API. Re-validation scheduled.',
                'warning'
            );
            return intval($input); // Save with warning
        }

        // Invalid ID - block save
        add_settings_error(
            'fcg_gfm_template_campaign_id',
            'invalid_id',
            'Invalid campaign ID: ' . $result['error'],
            'error'
        );
        return get_option('fcg_gfm_template_campaign_id', 0); // Return old value
    }

    // Valid - store campaign name in separate option
    update_option('fcg_gfm_template_campaign_name', $result['data']['name'], false);

    return intval($input);
}
```

### Pattern 2: Post Meta Field in Meta Box
**What:** Add field to existing meta box for per-fund data
**When to use:** Adding fundraising goal to fund edit screen
**Example:**
```php
// Source: class-admin-ui.php lines 105-168 (existing meta box)
public function render_sync_meta_box(WP_Post $post): void {
    $goal = get_post_meta($post->ID, '_gofundme_fundraising_goal', true);
    ?>
    <p>
        <label for="fcg-fundraising-goal"><strong>Fundraising Goal:</strong></label><br>
        <input type="text"
               id="fcg-fundraising-goal"
               name="fcg_fundraising_goal"
               value="<?php echo esc_attr($goal ? number_format($goal) : ''); ?>"
               placeholder="e.g., 5,000"
               pattern="[\d,]+"
               class="regular-text">
        <p class="description">Optional. Enter goal amount (e.g., 5000 or 5,000)</p>
    </p>
    <?php
}

// Save handler (new hook in constructor)
add_action('save_post_funds', [$this, 'save_fundraising_goal'], 10, 2);

public function save_fundraising_goal(int $post_id, WP_Post $post): void {
    if (isset($_POST['fcg_fundraising_goal'])) {
        $goal = sanitize_text_field($_POST['fcg_fundraising_goal']);
        // Remove commas, validate numeric
        $goal = str_replace(',', '', $goal);
        if (is_numeric($goal) && $goal > 0) {
            update_post_meta($post_id, '_gofundme_fundraising_goal', intval($goal));
        } else {
            delete_post_meta($post_id, '_gofundme_fundraising_goal');
        }
    }
}
```

### Pattern 3: Background Validation with WP-Cron
**What:** Schedule single event for re-validation when API is unreachable
**When to use:** API timeout during template ID validation
**Example:**
```php
// Source: fcg-gofundme-sync.php lines 125-129 + WP-Cron best practices
public function __construct() {
    // Register hook for background validation
    add_action('fcg_gfm_revalidate_template', [$this, 'revalidate_template_campaign']);
}

public function revalidate_template_campaign(): void {
    $template_id = get_option('fcg_gfm_template_campaign_id', 0);
    if (!$template_id) {
        return;
    }

    $api = new FCG_GFM_API_Client();
    $result = $api->get_campaign($template_id);

    if (!$result['success']) {
        // Store failed validation status
        update_option('fcg_gfm_template_validation_failed', true, false);
    } else {
        // Success - update campaign name
        update_option('fcg_gfm_template_campaign_name', $result['data']['name'], false);
        delete_option('fcg_gfm_template_validation_failed');
    }
}

// Show admin notice if background validation failed
public function show_template_validation_notice(): void {
    if (get_option('fcg_gfm_template_validation_failed')) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>GoFundMe Pro Sync:</strong> Template campaign ID validation failed. ';
        echo '<a href="' . admin_url('edit.php?post_type=funds&page=fcg-gfm-sync-settings') . '">Check settings</a></p>';
        echo '</div>';
    }
}
```

### Pattern 4: API Connection Status Indicator
**What:** Display real-time status of API and template validation
**When to use:** Settings page header section
**Example:**
```php
// Source: Existing meta box pattern + admin notices
public function render_settings_page(): void {
    $api = new FCG_GFM_API_Client();
    $api_configured = $api->is_configured();

    $template_id = get_option('fcg_gfm_template_campaign_id', 0);
    $template_name = get_option('fcg_gfm_template_campaign_name', '');
    $validation_failed = get_option('fcg_gfm_template_validation_failed', false);

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="notice notice-<?php echo $api_configured ? 'success' : 'warning'; ?>">
            <p>
                <strong>API Status:</strong>
                <?php echo $api_configured ? 'Connected' : 'Not Configured'; ?>
                <?php if ($template_id && $template_name && !$validation_failed): ?>
                    | <strong>Template:</strong> <?php echo esc_html($template_name); ?>
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <?php elseif ($template_id && $validation_failed): ?>
                    | <strong>Template:</strong> Validation failed
                    <span class="dashicons dashicons-warning" style="color: #f56e28;"></span>
                <?php endif; ?>
            </p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields('fcg_gfm_sync'); ?>
            <!-- Settings fields here -->
        </form>
    </div>
    <?php
}
```

### Anti-Patterns to Avoid
- **Validating on every page load:** Only validate on settings save - don't call API on page render
- **Blocking saves on transient API issues:** Network timeouts should warn, not block - schedule background check instead
- **Using ACF for plugin settings:** ACF is for content editors; plugin settings should use WordPress Settings API
- **Storing goal in ACF field:** Post meta (`_gofundme_fundraising_goal`) is simpler and doesn't require ACF dependency

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Settings validation UI | Custom error display | `add_settings_error()` + `settings_errors()` | WordPress handles display, styling, and dismissal automatically |
| Option sanitization | Manual input cleaning | `sanitize_callback` in `register_setting()` | Built-in, tested, follows WP conventions |
| Background task retry | Custom queue system | `wp_schedule_single_event()` | Native WP-Cron, no additional tables/complexity |
| API response caching | Custom transient wrapper | Use existing token transient pattern | Already implemented in `FCG_GFM_API_Client` |
| Currency formatting | Custom number formatter | `number_format()` + JavaScript fallback | Standard PHP, widely supported |
| Admin notices | Custom notification system | `admin_notices` hook with standard classes | WordPress styling, automatic dismiss button |

**Key insight:** WordPress Settings API handles the entire validation/sanitization/error display flow. Don't bypass it with custom AJAX validation - use the built-in callback system.

## Common Pitfalls

### Pitfall 1: API Validation Blocking Settings Save
**What goes wrong:** Settings page becomes unusable if API is down
**Why it happens:** Synchronous API call in validation callback with no timeout handling
**How to avoid:** Distinguish between "invalid ID" (block) vs "API unreachable" (warn + schedule background check)
**Warning signs:** Users report "settings won't save" during brief API outages

### Pitfall 2: Missing Nonce on Meta Box Save
**What goes wrong:** Goal field doesn't save, or saves incorrectly
**Why it happens:** WordPress meta box rendering includes nonce, but custom fields need to respect it
**How to avoid:** Use `wp_nonce_field()` and verify with `wp_verify_nonce()` or leverage existing nonce from line 127
**Warning signs:** Field value reverts after save, security warnings in debug log

### Pitfall 3: Background Validation Never Runs
**What goes wrong:** WP-Cron event scheduled but never executes
**Why it happens:** WP-Cron requires site traffic; staging environments may have low traffic
**How to avoid:** Test with `wp cron event run fcg_gfm_revalidate_template` or trigger manually
**Warning signs:** Validation status option never updates after initial save

### Pitfall 4: Number Formatting Confusion
**What goes wrong:** Goal "5,000" saved as "5" or validation fails
**Why it happens:** JavaScript formats display, but backend expects integer
**How to avoid:** Strip commas before intval(), accept both "5000" and "5,000" as input
**Warning signs:** Users report goal amounts incorrect by factor of 1000

### Pitfall 5: Existing API Method Not Used
**What goes wrong:** Duplicate code for fetching campaign details
**Why it happens:** Researcher doesn't check `class-api-client.php` for existing methods
**How to avoid:** Review shows `get_campaign($campaign_id)` already exists (line 319)
**Warning signs:** Code review finds duplicate API request logic

### Pitfall 6: Template Name Not Cached
**What goes wrong:** Settings page makes API call on every load to show template name
**Why it happens:** Not storing campaign name in options after successful validation
**How to avoid:** Store name in `fcg_gfm_template_campaign_name` option during validation
**Warning signs:** Slow settings page load, API rate limit errors

## Code Examples

### Campaign Fetch from Existing API Client
```php
// Source: class-api-client.php lines 319-321
$api = new FCG_GFM_API_Client();
$result = $api->get_campaign($campaign_id);

if ($result['success']) {
    $campaign_name = $result['data']['name']; // Confirmed field from Classy API
    $campaign_goal = $result['data']['goal'] ?? null;
}
```

### Post Meta Retrieval Pattern (Existing)
```php
// Source: class-admin-ui.php line 122
$designation_id = get_post_meta($post->ID, '_gofundme_designation_id', true);
$last_sync = get_post_meta($post->ID, '_gofundme_last_sync', true);

// New goal field follows same pattern:
$goal = get_post_meta($post->ID, '_gofundme_fundraising_goal', true);
```

### Settings Fields Rendering (Existing Pattern)
```php
// Source: class-admin-ui.php lines 217-236
<table class="form-table">
    <tr>
        <th scope="row">Setting Label</th>
        <td>
            <input type="text"
                   name="fcg_gfm_template_campaign_id"
                   value="<?php echo esc_attr(get_option('fcg_gfm_template_campaign_id', '')); ?>"
                   class="regular-text">
            <p class="description">
                Helper text here.
                <a href="https://www.classy.org/admin/campaigns" target="_blank">
                    Find campaign ID in Classy <span class="dashicons dashicons-external"></span>
                </a>
            </p>
        </td>
    </tr>
</table>
```

### WP-Cron Single Event Scheduling
```php
// Source: WordPress WP-Cron API + research findings
// Schedule for 15 minutes from now (900 seconds)
if (!wp_next_scheduled('fcg_gfm_revalidate_template')) {
    wp_schedule_single_event(time() + 900, 'fcg_gfm_revalidate_template');
}

// Handler registered in constructor
add_action('fcg_gfm_revalidate_template', [$this, 'revalidate_template_campaign']);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| ACF fields for all settings | Settings API for plugin-level, post meta for per-fund | Industry standard | Cleaner separation, no ACF dependency for core features |
| Manual form handling | `settings_fields()` + `register_setting()` | WordPress 4.7+ | Automatic nonces, sanitization hooks, error handling |
| Transient caching only for tokens | Options API for persistent settings | N/A | Template ID persists across sessions, survives cache clears |
| Inline validation | `sanitize_callback` with `add_settings_error()` | WordPress Settings API evolution | User sees errors on same page, old value preserved on failure |

**Deprecated/outdated:**
- Manual option sanitization: Use `sanitize_callback` parameter in `register_setting()`
- Custom settings page form action: Use `options.php` with `settings_fields()` for automatic handling
- JavaScript-only validation: Always validate server-side in sanitize callback

## Open Questions

1. **Campaign duplication endpoint timing**
   - What we know: Prior decisions show "Must use duplicateCampaign endpoint" but no endpoint details found in research
   - What's unclear: Exact endpoint URL and parameters for campaign duplication (Phase 2 concern)
   - Recommendation: Out of scope for Phase 1 - configuration only stores template ID, duplication happens in Phase 2

2. **Fundraising goal field placement**
   - What we know: Context shows "add to existing sync status meta box"
   - What's unclear: Exact position within meta box (before/after designation ID)
   - Recommendation: Add after "Last Source" field, before error message (if any) - logical flow

3. **Background validation failure persistence**
   - What we know: Admin notice should be dismissible
   - What's unclear: Should dismissal be per-user or global? Should notice re-appear on next login?
   - Recommendation: Dismissible per session (standard `is-dismissible` class) - non-persistent is acceptable for non-critical validation

## Sources

### Primary (HIGH confidence)
- **Existing codebase analysis:** `class-admin-ui.php`, `class-api-client.php`, `class-sync-handler.php`, `fcg-gofundme-sync.php`
- **WordPress Settings API documentation:** [WordPress Developer Reference - register_setting()](https://developer.wordpress.org/reference/functions/register_setting/)
- **Classy API v2.0:** GET /campaigns/{id} endpoint confirmed to return `name` field ([Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html))
- **WordPress WP-Cron:** [wp_schedule_single_event() documentation](https://developer.wordpress.org/reference/functions/wp_schedule_single_event/)

### Secondary (MEDIUM confidence)
- **Settings validation patterns:** [WordPress Settings API Tutorial](https://code.tutsplus.com/tutorials/the-wordpress-settings-api-part-7-validation-sanitisation-and-input--wp-25289)
- **Admin notices:** [WordPress Admin Notices Guide](https://digwp.com/2016/05/wordpress-admin-notices/)
- **Classy API ecosystem:** [Factor 1 Studios Classy API overview](https://factor1studios.com/harnessing-power-classy-api/)

### Tertiary (LOW confidence)
- None - all findings verified against codebase or official documentation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components already in codebase or WordPress core
- Architecture: HIGH - Existing patterns documented, verified in code
- Pitfalls: MEDIUM - Based on common WordPress development issues + API integration experience

**Research date:** 2026-01-23
**Valid until:** 2026-02-23 (30 days - WordPress and Classy API are stable)

**Key verification performed:**
- ✅ Confirmed `get_campaign()` method exists in `class-api-client.php` line 319
- ✅ Confirmed Settings API pattern in `class-admin-ui.php` lines 187-196
- ✅ Confirmed meta box rendering pattern in `class-admin-ui.php` lines 105-168
- ✅ Confirmed WP-Cron usage in `fcg-gofundme-sync.php` line 127
- ✅ Confirmed post meta key naming pattern: `_gofundme_*` prefix used throughout
