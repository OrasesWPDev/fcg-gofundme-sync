# Theme Files Deployment Guide - Classy Embed Implementation

**Phase:** 07 (Frontend Embed)
**Date:** 2026-01-29
**Status:** Complete on Staging, Ready for Production

---

## Quick Reference - Files to Deploy

| File | Action | Priority |
|------|--------|----------|
| `fund-form.php` | **REPLACE** | Required |
| `archive-funds.php` | **REPLACE** | Required |
| `search.php` | Update (same pattern) | Recommended |
| `taxonomy-fund-category.php` | Update (same pattern) | Recommended |
| `template-flexible.php` | Update (same pattern) | Recommended |

**Source Location (Local):**
```
/Users/chadmacbook/Local Sites/frederick-county-gives/app/public/wp-content/themes/community-foundation/
```

**Destination (WP Engine):**
```
wp-content/themes/community-foundation/
```

---

## SFTP Deployment Instructions

### Connection Details

**Staging:**
- Host: `frederickc2stg.sftp.wpengine.com`
- Port: `2222`
- Username: `frederickc2stg-{username}` (check WP Engine portal)
- Path: `/wp-content/themes/community-foundation/`

**Production:**
- Host: `frederickcount.sftp.wpengine.com`
- Port: `2222`
- Username: `frederickcount-{username}` (check WP Engine portal)
- Path: `/wp-content/themes/community-foundation/`

### Files to Upload

**1. fund-form.php** (REQUIRED)
- Contains: Classy embedded donation form
- Used on: Single fund pages
- Replaces: Legacy Acceptiva donation form

**2. archive-funds.php** (REQUIRED)
- Contains: Fund listing with "Give Now" links
- Used on: `/funds/` archive page
- Changes: Disabled modals, "Learn More" → "Give Now"

---

## What Changed and Why

### Problem Discovered
The Classy embedded donation SDK has a fundamental incompatibility with Bootstrap modals. When the Classy form is rendered inside a Bootstrap modal:
- Initial form displays correctly
- Clicking "Donate" triggers Classy's payment modal
- Payment modal fails with: `Failed to construct 'HTMLElement': Illegal constructor`
- Donation cannot be completed

### Solution Implemented
**Single Fund Pages:** Classy embed works perfectly (no Bootstrap modal involved)

**Archive Pages:** Removed modal functionality entirely
- Fund titles link directly to fund page
- "Learn More" changed to "Give Now"
- Original modal code commented out (not deleted) with notes
- Users are taken to single fund page where Classy works

---

## File Details

### 1. fund-form.php

**Purpose:** Renders the Classy donation form on single fund pages

**Key Features:**
- Reads designation ID from post meta (`_gofundme_designation_id`)
- Reads master campaign/component IDs from plugin settings
- Injects `?designation={id}` URL parameter via JavaScript
- Classy SDK reads URL and pre-selects the correct fund
- Fallback message for funds without designation

**Code Structure:**
```php
<?php
$designation_id = get_post_meta(get_the_ID(), '_gofundme_designation_id', true);
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
$master_component_id = get_option('fcg_gofundme_master_component_id');
?>

<!-- JavaScript sets ?designation={id} in URL -->
<!-- Then Classy embed div renders -->
<div id="{component_id}" classy="{campaign_id}"></div>
```

### 2. archive-funds.php

**Purpose:** Displays fund listings on `/funds/` page

**Changes Made:**

| Line | Original | New |
|------|----------|-----|
| ~70 | Title opens modal | Title links to fund page |
| ~77 | "Learn More" link | "Give Now" link |
| ~80 | "Give Now" modal button | **HIDDEN** (commented with notes) |
| ~85 | `get_template_part('fund-modal')` | **HIDDEN** (commented with notes) |

**Original Code (now commented):**
```php
<!-- Was: Modal trigger -->
<a class="btn-link" data-toggle="modal" data-target="#fund-<?php the_ID() ?>">Give Now</a>

<!-- Was: Modal template -->
<?php get_template_part('fund-modal') ?>
```

**New Code:**
```php
<!-- Direct link to fund page -->
<a class="more-link" href="<?php the_permalink() ?>">Give Now</a>

<!-- Modal code commented out with explanation -->
```

---

## Other Files That May Need Updates

These files have the same modal pattern and should be updated for consistency:

### search.php (Line ~203)
```php
// Find and comment out:
<a class="btn-link" rel="bookmark" data-toggle="modal" data-target="#fund-<?php the_ID() ?>">Give Now</a>
```

### taxonomy-fund-category.php (Line ~44)
```php
// Find and comment out:
<a class="btn-link mt-auto" data-toggle="modal" data-target="#fund-<?php the_ID() ?>">Give Now</a>
```

### template-flexible.php (Line ~964)
```php
// Find and comment out:
<a class="btn-link mt-auto" data-toggle="modal" data-target="#fund-<?php the_ID() ?>">Give Now</a>
```

---

## Prerequisites for Production

Before deploying theme files to production:

1. **Plugin Deployed:** FCG GoFundMe Sync plugin v2.3.0+ must be active
2. **Plugin Settings Configured:**
   - Master Campaign ID: `764694` (or production campaign ID)
   - Master Component ID: `mKAgOmLtRHVGFGh_eaqM6` (or production component ID)
3. **Classy WP Plugin:** Must be installed and configured with correct Org ID
4. **Funds Synced:** Funds must have `_gofundme_designation_id` in post meta

---

## Testing Checklist

### After Deployment to Production

**Single Fund Page Test:**
- [ ] Visit any fund page (e.g., `/funds/example-fund/`)
- [ ] Classy donation form appears
- [ ] URL shows `?designation={number}`
- [ ] Select amount and click "Donate"
- [ ] Payment modal opens (NOT stuck/broken)
- [ ] Can complete donation flow

**Archive Page Test:**
- [ ] Visit `/funds/`
- [ ] Click any fund title → goes to fund page (no modal)
- [ ] "Give Now" link → goes to fund page
- [ ] No Bootstrap modals appear

**Fallback Test:**
- [ ] Visit a fund without designation ID
- [ ] Shows "Online donations coming soon" message

---

## Rollback Instructions

If issues occur, restore original files from WP Engine backup or:

**archive-funds.php rollback:**
1. Uncomment the modal button (`<a class="btn-link"...>Give Now</a>`)
2. Uncomment `get_template_part('fund-modal')`
3. Change "Give Now" back to "Learn More" in the `more-link`
4. Restore modal trigger on fund title

**fund-form.php rollback:**
- Restore from backup (original Acceptiva form code)

---

## Version History

**v2.3.0 (2026-01-29):**
- Initial Classy embed implementation
- Replaced Acceptiva donation form
- Added URL parameter injection for designation pre-selection

**v2.3.0-modal-fix (2026-01-29):**
- Discovered Classy SDK incompatibility with Bootstrap modals
- Removed modal functionality from archive pages
- Changed "Learn More" to "Give Now" for direct fund page links
- Documented original code for future reference

---

## Technical Notes

### Why Modals Don't Work

The Classy SDK uses custom HTML elements (`<cl-donation-form>`) and its own modal system. When nested inside a Bootstrap modal:

1. Classy SDK initializes and renders the amount selection form (works)
2. User clicks "Donate"
3. Classy tries to open its payment modal
4. Browser throws: `Failed to construct 'HTMLElement': Illegal constructor`
5. Payment flow breaks

This is a fundamental SDK architecture issue, not something we can fix with JavaScript workarounds.

### URL Parameter Behavior

The `?designation={id}` parameter tells Classy which fund to pre-select:
- Set via `history.replaceState()` (no page reload)
- Classy SDK reads on initialization
- Parameter persists in URL for bookmarking/sharing

---

## Support

For issues with this implementation:
1. Check browser console for JavaScript errors
2. Verify plugin settings are configured
3. Confirm Classy WP plugin is active
4. Check fund has designation ID in post meta

---

*Last Updated: 2026-01-29*
