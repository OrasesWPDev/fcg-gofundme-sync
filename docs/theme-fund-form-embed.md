# Theme File: fund-form.php - Classy Embed Implementation

**Phase:** 07-01 (Frontend Embed)
**Date:** 2026-01-29
**Status:** Implemented in Local Sites

## Overview

Replaced legacy Acceptiva donation form with Classy embedded donation form in the Community Foundation theme's `fund-form.php` template file.

## File Location

**Theme:** `community-foundation`
**File:** `fund-form.php`
**Path (Local):** `/Users/chadmacbook/Local Sites/frederick-county-gives/app/public/wp-content/themes/community-foundation/fund-form.php`
**Path (WP Engine):** `~/sites/{site}/wp-content/themes/community-foundation/fund-form.php`

## Changes Made

### Removed (Legacy Acceptiva Form)
- `<form class="donate-form">` element
- Hidden input: `js-product-name`
- Amount input: `js-product-amount`
- Add to cart button: `js-add-to-cart`
- Cart title heading: `js-cart-title`
- Template part: `get_template_part('fund-cart')`

### Added (Classy Embed)
- Classy embed div with `classy="{campaign_id}"` attribute
- Dynamic designation pre-selection via URL parameter injection
- Graceful fallback for unconfigured funds
- PHP doc block with version reference (2.3.0)

## Implementation Details

### Required Plugin Settings
The template reads three values from the plugin:
1. `_gofundme_designation_id` (post meta) - Fund-specific designation ID
2. `fcg_gofundme_master_campaign_id` (option) - Master campaign ID
3. `fcg_gofundme_master_component_id` (option) - Master component ID for embed

### Classy Embed Format
```html
<div id="{component_id}" classy="{campaign_id}"></div>
```

### Designation Pre-selection

Different approaches for single pages vs archive pages (modals):

**Single Fund Pages:**
- JavaScript uses `history.replaceState()` to inject `?designation={id}` parameter
- Non-disruptive (no page reload)
- Updates URL immediately on page load
- Classy SDK reads parameter and pre-selects fund in dropdown

**Archive Pages (Modals):**
- `data-designation-id` attribute stores each fund's designation ID on the embed div
- JavaScript event listeners handle Bootstrap modal `show.bs.modal` and `hidden.bs.modal`
- On modal show: Sets `?designation={id}` in URL from data attribute
- On modal hide: Removes designation parameter from URL
- Stale designation parameters are cleaned on archive page load
- Handler registers only once per page (prevents duplicate listeners)

### Fallback Message
When any required value is missing:
```html
<div class="donate-form-fallback">
  <p class="text-muted">
    Online donations for this fund are coming soon.
    Please <a href="/contact/">contact us</a> to make a donation.
  </p>
</div>
```

## Security

All output properly escaped:
- `esc_attr()` for HTML attributes (campaign ID, component ID)
- `esc_js()` for JavaScript strings (designation ID)

## Deployment

### Prerequisites
1. Plugin settings must be configured:
   - Master Campaign ID
   - Master Component ID
2. Classy SDK must be loaded on page (handled by Classy WordPress plugin)

### Deployment Steps

**To Staging:**
```bash
# From theme repository or manual SFTP
scp fund-form.php frederickc2stg@frederickc2stg.ssh.wpengine.net:~/sites/frederickc2stg/wp-content/themes/community-foundation/
```

**To Production:**
```bash
# From theme repository or manual SFTP
scp fund-form.php frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/themes/community-foundation/
```

**Note:** This file is part of the theme, not the plugin. Deploy separately from plugin updates.

## Testing Checklist

After deployment:

### Single Fund Page Tests

1. Visit a fund page that has a designation ID
   - [ ] Classy embed div appears
   - [ ] URL includes `?designation={id}` parameter
   - [ ] Fund pre-selected in designation dropdown
   - [ ] Donation form functions correctly

2. Visit a fund page without designation ID
   - [ ] Fallback message appears: "Online donations for this fund are coming soon"
   - [ ] "Contact us" link works

### Archive Page / Modal Tests

3. Visit funds archive page (`/funds/`)
   - [ ] URL does NOT have stale designation parameter
   - [ ] Open modal for Fund A - verify correct fund shown
   - [ ] Close modal - URL should be clean (no designation param)
   - [ ] Open modal for Fund B - verify Fund B (not Fund A) is shown

4. Navigation test (critical)
   - [ ] Visit a single fund page (URL gets designation param)
   - [ ] Navigate back to archive page
   - [ ] URL should be clean (designation param removed)
   - [ ] Open modal for any fund - should work correctly

### General Tests

5. Check browser console for errors
   - [ ] No JavaScript errors
   - [ ] No missing SDK errors
   - [ ] No 404 errors

6. Test donation flow
   - [ ] Select amount
   - [ ] Complete donation form
   - [ ] Verify donation processes correctly

## Integration Points

### With Plugin
- Reads `fcg_gofundme_master_campaign_id` option
- Reads `fcg_gofundme_master_component_id` option
- Reads `_gofundme_designation_id` post meta

### With Classy SDK
- Classy SDK loaded by Classy WordPress plugin
- SDK processes `classy="{id}"` attribute
- SDK reads `?designation={id}` URL parameter

## Known Limitations

1. **Default Designation Behavior:** Each synced fund briefly becomes the campaign's default designation (lock icon in Classy). Manually reset in Classy UI if needed.

2. **Theme Dependency:** This file must be deployed to the theme directory, separate from the plugin deployment process.

3. **SDK Dependency:** Requires Classy WordPress plugin active and configured on the site.

## Related Files

**Plugin Files:**
- `includes/class-sync-handler.php` - Syncs designation data to post meta
- `fcg-gofundme-sync.php` - Registers settings for master campaign/component IDs

**Theme Files:**
- `fund-form.php` - This file
- (Legacy) `fund-cart.php` - No longer used, can be removed

## Version History

**v2.3.0 (2026-01-29):**
- Initial implementation of Classy embed
- Replaced legacy Acceptiva form
- Added designation pre-selection
- Added graceful fallback

**v2.3.0 (2026-01-29, Update 1):**
- Fixed modal behavior on archive pages
- Added `is_singular('funds')` detection for page type
- Added `data-designation-id` attribute to embed div
- Archive pages: Dynamic URL parameter management on modal open/close
- Archive pages: Clean stale designation parameters on page load
- Fixed issue where visiting single fund then returning to archive broke modal opening
