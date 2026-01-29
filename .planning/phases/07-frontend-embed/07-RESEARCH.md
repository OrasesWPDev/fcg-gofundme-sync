# Phase 7: Frontend Embed - Research

**Researched:** 2026-01-29
**Domain:** Classy donation form embedding, WordPress theme integration
**Confidence:** HIGH

## Summary

This phase replaces the legacy donation form on fund pages with Classy embedded donation forms. The existing Classy WP plugin already handles SDK loading via Organization ID. The key implementation is modifying `fund-form.php` to render a simple div element with the master campaign's component ID and campaign ID, while using the `?designation={id}` URL parameter to pre-select the correct fund.

The architecture is well-understood and verified:
- Master campaign ID and component ID are already stored in plugin settings (verified on staging)
- Designation IDs are already stored in post meta (`_gofundme_designation_id`)
- Classy WP plugin injects SDK via `<script src="https://giving.classy.org/embedded/api/sdk/js/{org_id}">`
- Embed format: `<div id="{component_id}" classy="{campaign_id}"></div>`

**Primary recommendation:** Modify `fund-form.php` to render the Classy embed div with designation pre-selection via URL parameter. The Classy SDK reads URL parameters and pre-selects the designation in the dropdown.

## Standard Stack

The implementation uses existing infrastructure - no new libraries needed.

### Core (Already Installed)
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| Classy WP Plugin | 1.0.0 | SDK injection, shortcode support | Installed, configured |
| FCG GoFundMe Sync | 2.3.0 | Stores master campaign/component IDs, designation sync | Installed, configured |
| community-foundation theme | - | Contains fund-form.php template | Active |

### Settings (Already Configured on Staging)
| Setting | Value | Purpose |
|---------|-------|---------|
| `classy_wp_org_id` | 105659 | SDK loading |
| `fcg_gofundme_master_campaign_id` | 764694 | Master campaign for all designations |
| `fcg_gofundme_master_component_id` | mKAgOmLtRHVGFGh_eaqM6 | Embed component ID |

## Architecture Patterns

### How Classy SDK Embed Works

1. Classy WP plugin adds SDK script to `<head>`:
   ```html
   <script async src="https://giving.classy.org/embedded/api/sdk/js/105659"></script>
   ```

2. SDK scans DOM for divs with `classy` attribute
3. SDK replaces matching divs with embedded donation forms

### Embed Code Format (Verified from Classy WP Plugin)

```html
<div id="{component_id}" classy="{campaign_id}"></div>
```

Example:
```html
<div id="mKAgOmLtRHVGFGh_eaqM6" classy="764694"></div>
```

### Designation Pre-Selection via URL Parameter

**How it works:**
- URL includes `?designation={id}` parameter
- SDK reads URL parameters and pre-selects the designation
- Donor sees their chosen fund already selected in the dropdown

**Implementation pattern:**
```php
// In fund-form.php
$designation_id = get_post_meta(get_the_ID(), '_gofundme_designation_id', true);
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
$master_component_id = get_option('fcg_gofundme_master_component_id');

// URL must include designation parameter for pre-selection
$current_url = add_query_arg('designation', $designation_id, get_permalink());
```

**Important:** The designation parameter must be in the page URL when the SDK loads. This works because:
1. Fund page loads with `?designation={id}` in URL
2. Classy SDK reads URL parameters automatically
3. Form renders with designation pre-selected

### Recommended Template Structure

```php
<?php
// fund-form.php - Classy embed with designation pre-selection
$designation_id = get_post_meta(get_the_ID(), '_gofundme_designation_id', true);
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
$master_component_id = get_option('fcg_gofundme_master_component_id');
?>

<h4>Make a Donation</h4>

<?php if ($designation_id && $master_campaign_id && $master_component_id): ?>
  <!-- Classy Embedded Donation Form -->
  <div id="<?php echo esc_attr($master_component_id); ?>"
       classy="<?php echo esc_attr($master_campaign_id); ?>"></div>

  <?php if (!isset($_GET['designation'])): ?>
  <!-- Redirect to include designation parameter for pre-selection -->
  <script>
    if (!window.location.search.includes('designation=')) {
      window.history.replaceState({}, '',
        window.location.href +
        (window.location.search ? '&' : '?') +
        'designation=<?php echo esc_js($designation_id); ?>'
      );
    }
  </script>
  <?php endif; ?>
<?php else: ?>
  <!-- Fallback when embed not configured -->
  <div class="donate-form-fallback">
    <p class="text-muted">
      Online donations for this fund are coming soon.
      Please <a href="/contact/">contact us</a> to make a donation.
    </p>
  </div>
<?php endif; ?>
```

### Anti-Patterns to Avoid

- **Don't use the Classy shortcode:** Direct div is simpler and more reliable for dynamic content
- **Don't hardcode campaign/component IDs:** Use settings stored in database
- **Don't rely on ACF fields for embed config:** Use plugin settings instead (old plan was per-fund ACF fields)

## Theme Files to Modify

| File | Location | Current State | Action |
|------|----------|---------------|--------|
| `fund-form.php` | Theme: community-foundation | Legacy Acceptiva cart form | Replace with Classy embed |
| `fund-modal.php` | Theme: community-foundation | Includes fund-form via `get_template_part()` | No change needed |
| `single-funds.php` | Theme: community-foundation | Includes fund-form via `get_template_part()` | No change needed |

**Key insight:** Both the fund single page AND the modal popup use `get_template_part('fund-form')`. Updating ONE file updates BOTH locations.

### Current fund-form.php (Legacy Code)

```php
<h4>Make a Donation</h4>

<form class="donate-form">
  <input type="hidden" class="js-product-name" value="<?php esc_attr(the_title()) ?>">
  <div class="input-group mr-lg-3">
    <div class="input-group-prepend text-secondary">$</div>
    <input type="text" class="js-product-amount form-control" placeholder="0.00">
  </div>

  <button class="js-add-to-cart btn btn-primary" disabled>Review Your Donation</button>
</form>

<h6 class="js-cart-title mt-4 mb-2" style="display: none;">Saved Donations</h6>

<?php get_template_part('fund-cart') ?>
```

### Current fund-cart.php (Legacy - Will Be Removed)

```php
<div class="js-cart-container" style="display: none;">
  <div class="table-responsive">
    <table class="table table-sm mb-0 js-cart">
      <tbody></tbody>
      <tfoot class="bg-light">
        <tr>
          <th>Total</th>
          <td class="text-right font-weight-bold">
            <span class="d-flex justify-content-end align-items-center">
              <span class="js-cart-total text-large"></span>
              <a class="js-empty-cart ml-3 text-decoration-none" style="cursor: pointer;">
                <span class="icon icon-delete text-small"></span>
              </a>
            </span>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
```

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Donation form rendering | Custom form HTML | Classy embed div | SDK handles all payment processing |
| URL parameter reading | Custom JS parser | SDK's built-in handling | SDK already reads `?designation=` |
| SDK loading | Manual script injection | Classy WP plugin | Plugin handles script injection |
| Campaign config storage | New database tables | Existing plugin settings | Already stored in `fcg_gofundme_*` options |

## Common Pitfalls

### Pitfall 1: Designation Parameter Not in URL

**What goes wrong:** Embed loads but wrong designation is pre-selected
**Why it happens:** SDK reads URL parameters at load time - if `?designation=` isn't in URL, default is used
**How to avoid:** Either use JavaScript to add parameter to URL via `history.replaceState()`, or link to fund pages with parameter already included
**Warning signs:** Donors reporting wrong fund selected

### Pitfall 2: Modal Context Post ID

**What goes wrong:** `get_the_ID()` returns wrong post ID in modal context
**Why it happens:** Modal may be rendered outside the post loop
**How to avoid:** Verify `fund-modal.php` sets up post context correctly (it does use `the_ID()` in the modal wrapper)
**Warning signs:** Wrong designation ID in modal embeds

### Pitfall 3: Fallback When Designation Missing

**What goes wrong:** Published fund has no designation ID, shows broken embed or error
**Why it happens:** Fund was published before sync ran, or sync failed
**How to avoid:** Check for all three required values: designation_id, master_campaign_id, master_component_id
**Warning signs:** Empty or broken donation forms on some fund pages

### Pitfall 4: Legacy Cart JavaScript Conflicts

**What goes wrong:** Old cart.js still tries to intercept form submissions
**Why it happens:** Legacy JS event handlers still attached
**How to avoid:** Either remove legacy form elements entirely, or ensure they're not rendered
**Warning signs:** JavaScript errors in console, unexpected form behavior

### Pitfall 5: Safelisted Domain

**What goes wrong:** Embed shows error or doesn't load
**Why it happens:** Domain not safelisted in Classy campaign settings
**How to avoid:** Ensure production domain is safelisted before go-live (staging already safelisted)
**Warning signs:** Console errors about cross-origin, blank embed

## Code Examples

### Embed Div (Verified from Classy WP Plugin Source)

```php
// From class-classy-wp-public.php line 175-176
return '<div id="' . esc_attr($code_params['component']) . '" classy="' . esc_attr($code_params['campaign']) . '"></div>';
```

### SDK Injection (Verified from Classy WP Plugin Source)

```php
// From class-classy-wp-public.php add_embedded_header()
echo '<script async src="https://giving.classy.org/embedded/api/sdk/js/' . esc_attr($org_id) . '"></script>';
```

### Getting Plugin Settings

```php
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
$master_component_id = get_option('fcg_gofundme_master_component_id');
```

### Getting Designation ID from Post

```php
$designation_id = get_post_meta(get_the_ID(), '_gofundme_designation_id', true);
```

## Edge Cases

### Draft/Trashed Funds

- Designation may exist but be deactivated (`is_active = false`)
- Embed would still work but designation may not appear in dropdown
- **Recommendation:** Check post status before rendering embed, show fallback for non-published

### Funds Without Designation

- New funds or sync-failed funds may not have `_gofundme_designation_id`
- **Recommendation:** Show graceful fallback message with contact link

### Multiple Embeds on Page (Archive Pages)

- Archive pages show multiple funds
- Each modal needs its own embed with correct designation
- **Verification needed:** Test modal behavior on archive pages

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Per-fund campaigns | Single master campaign | 2026-01-28 | Much simpler, one campaign for 862 designations |
| Per-fund ACF fields for embed | Plugin settings for embed config | 2026-01-28 | No per-fund config needed |
| Legacy Acceptiva cart | Classy embedded forms | This phase | Modern payment processing |

**Deprecated/outdated:**
- Acceptiva payment gateway - replaced by Classy/GoFundMe Pro
- cart.js shopping cart functionality - replaced by Classy's built-in cart
- fund-cart.php template - no longer needed

## Open Questions

### 1. URL Parameter Injection Method

**What we know:** SDK reads `?designation=` from URL at load time
**What's unclear:** Best method to ensure parameter is in URL
**Options:**
  - JavaScript `history.replaceState()` to add parameter without page reload
  - Server-side redirect to add parameter (performance impact)
  - Assume links to fund pages include parameter
**Recommendation:** Use JavaScript `history.replaceState()` - most seamless

### 2. Archive Page Modal Behavior

**What we know:** fund-modal.php includes fund-form.php via `get_template_part()`
**What's unclear:** Whether modal context preserves correct post ID for each fund
**Recommendation:** Test on staging before finalizing implementation

### 3. Legacy Cart Cleanup Scope

**What we know:** cart.js handles add-to-cart, checkout to Acceptiva
**What's unclear:** Whether cart.js should be completely removed or just ignored
**Recommendation:** Phase 7 focuses on fund-form.php replacement only; cart.js cleanup can be separate task

## Sources

### Primary (HIGH confidence)
- Classy WP Plugin source code - class-classy-wp-public.php (read from staging server)
- FCG GoFundMe Sync plugin source - class-admin-ui.php, class-sync-handler.php
- WordPress staging environment - verified settings via WP-CLI
- Archived research - .planning/phases/archived/07-frontend-embed-original/08-CONTEXT.md

### Secondary (MEDIUM confidence)
- [Classy Embedded Donation Forms Documentation](https://support.classy.org/s/article/embedded-giving)
- [Pass-through Parameters Guide](https://support.classy.org/s/article/a-guide-to-pass-through-parameters)
- [Classy Embedded Form Settings](https://support.classy.org/s/article/more-embedded-form-customizations)

### Tertiary (LOW confidence)
- WebSearch results on URL parameter configuration - need to verify exact syntax with testing

## Metadata

**Confidence breakdown:**
- Embed div format: HIGH - verified from Classy WP plugin source code
- SDK injection: HIGH - verified from Classy WP plugin source code
- Settings storage: HIGH - verified via WP-CLI on staging
- Designation pre-selection: MEDIUM - Classy confirmed in email, but exact behavior needs testing
- Modal context behavior: MEDIUM - code review suggests it works, needs testing

**Research date:** 2026-01-29
**Valid until:** 2026-02-28 (stable - core infrastructure is established)
