# Phase 7: Frontend Embed Integration - Context

## Discovery Session (2026-01-27)

This document captures findings from hands-on exploration of the Classy WP plugin and embedded form setup.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLASSY (GoFundMe Pro)                     │
├─────────────────────────────────────────────────────────────────┤
│  Organization (ID: 105659)                                       │
│      │                                                           │
│      ├── Designations (861 synced from WordPress)               │
│      │       └── Ada B. Poole Scholarship Fund (designation)    │
│      │                                                           │
│      └── Campaigns (Embedded Form type)                         │
│              └── Embed_Form_Test (ID: 764041)                   │
│                      └── Linked to: Ada B. Poole designation    │
│                      └── Component ID: S9nYPwV-n0eBvabmj6qJk    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ SDK loads via Org ID
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        WORDPRESS                                 │
├─────────────────────────────────────────────────────────────────┤
│  Classy WP Plugin                                                │
│      └── Settings: Org ID = 105659                              │
│      └── Auto-injects SDK: <script src="...sdk/js/105659">      │
│                                                                  │
│  Theme: community-foundation                                     │
│      ├── fund-form.php (donation form template part)            │
│      │       └── Used by: single-funds.php (fund page)          │
│      │       └── Used by: fund-modal.php (lightbox popup)       │
│      │                                                           │
│      └── Embed div: <div id="{component}" classy="{campaign}">  │
│                                                                  │
│  Funds CPT (ACF Fields - TO BE ADDED)                           │
│      └── classy_campaign_id: "764041"                           │
│      └── classy_component_id: "S9nYPwV-n0eBvabmj6qJk"          │
└─────────────────────────────────────────────────────────────────┘
```

## Key Discoveries

### 1. Classy WP Plugin Handles SDK Loading

The official Classy WP plugin (GoFundMe Pro | Nonprofit Donation Forms) automatically:
- Adds the SDK script to `<head>` based on Organization ID
- Provides a `[classy campaign="X" component="Y"]` shortcode
- No manual snippet installation needed when plugin is active

**Plugin Settings Location:** WordPress Admin → Settings → Classy Donation Form

### 2. Theme Template Locations

| File | Location | Purpose |
|------|----------|---------|
| `fund-form.php` | `wp-content/themes/community-foundation/` | Donation form template part |
| `single-funds.php` | Same directory | Fund single page (includes fund-form) |
| `fund-modal.php` | Same directory | Lightbox popup (includes fund-form) |

**Key insight:** Both the fund page AND the modal popup use the same `fund-form.php` template part. Updating ONE file updates BOTH locations.

### 3. Embed Code Format

```html
<!-- Component ID = unique identifier for this embed instance -->
<!-- Campaign ID = the Classy campaign ID -->
<div id="S9nYPwV-n0eBvabmj6qJk" classy="764041"></div>
```

The SDK (loaded by Classy WP plugin) finds these divs and renders the donation form inside them.

### 4. Campaign Dependency

**Critical realization:** You cannot embed a donation form without first creating an Embedded Form Campaign in Classy.

The dependency chain:
```
1. Designation exists in Classy (already synced via FCG plugin)
       ↓
2. Embedded Form Campaign created in Classy (MANUAL - links to designation)
       ↓
3. Campaign ID + Component ID obtained from Classy Install settings
       ↓
4. IDs entered into WordPress ACF fields on the fund
       ↓
5. fund-form.php renders the embed div dynamically
```

### 5. Campaign Creation Workflow (Classy UI)

Tested path to create an embedded form campaign:

1. **Classy Dashboard** → Campaigns → Create Campaign
2. **Campaign Type:** Direct giving
3. **Format:** Embedded form
4. **Designation:** Select from synced designations (e.g., Ada B. Poole)
5. **Design tab:** Configure form appearance (amounts, frequencies, etc.)
6. **Settings → Install:**
   - Add safelisted domain: `frederickc2stg.wpenginepowered.com`
   - Copy "Inline donation grid" embed code
7. **Publish** the campaign

### 6. Successful Test

- Created embedded form campaign (ID: 764041, Component: S9nYPwV-n0eBvabmj6qJk)
- Safelisted staging domain
- Updated fund-form.php with embed div
- **Result:** Classy donation form renders on both fund page AND modal popup

## Implications for Phase 7

### What Changed from Original Plan

| Original Assumption | Reality |
|---------------------|---------|
| Plugin would auto-create campaigns | Campaigns must be created manually in Classy UI |
| Campaign ID from API sync sufficient | Need BOTH Campaign ID AND Component ID |
| Embed via shortcode | Direct div element works (shortcode optional) |
| One-time migration | Ongoing workflow for new funds |

### Revised Workflow for Clients

**For each fund:**
1. Create fund in WordPress (triggers designation sync)
2. Create Embedded Form Campaign in Classy UI
   - Select the fund's designation
   - Configure form settings
   - Publish
3. Copy Campaign ID and Component ID from Classy → Settings → Install
4. Enter IDs into ACF fields on the WordPress fund
5. Fund page now shows Classy donation form

### Alternative: Single Form with Designation Dropdown

Instead of 861 campaigns, could create ONE embedded form with a designation dropdown. Donors would:
1. Select which fund to support from dropdown
2. Enter donation amount
3. Donation routes to correct designation

**Pros:** Much simpler, one campaign for all funds
**Cons:** Less dedicated feel, no fund-specific customization

## Files Modified During Testing

| File | Change | Status |
|------|--------|--------|
| `fund-form.php` | Replaced legacy form with Classy embed div | TEMPORARY TEST - needs revert |

## Test Campaign Details

```
Campaign Name: Embed_Form_Test
Campaign ID: 764041
Component ID: S9nYPwV-n0eBvabmj6qJk
Linked Designation: Ada B. Poole Scholarship Fund
Safelisted Domain: frederickc2stg.wpenginepowered.com
Status: Published
```

## Open Questions for Luke/Classy

1. Can Classy bulk-create embedded form campaigns (like the campaign bulk creation request)?
2. Is the Component ID available via API, or only through the UI?
3. Is there a way to auto-generate embed campaigns when designations are created?

---

## Classy Response (2026-01-28) - ARCHITECTURE PIVOT

### Email from Luke Dringoli & Jon Bierma

Classy is recommending a **fundamentally different approach** than per-fund campaigns:

**Luke's key statement:**
> "What we are suggesting is to not create new campaigns at all, but instead use a single campaign with all designations loaded in. You should be able to use our public Designation API endpoint to create your designations on the account-level. Then, you'll just need to add them to the campaign using our UI (you check 'check all')."

**Jon's key statement:**
> "I would strongly recommend this, as the level of complexity and maintenance is simplified with one campaign to maintain instead of ~800. The designations are available in the donation flow using a drop down... The default program designation selected can be set campaign-wide, but can also be set by appending the passthrough parameter `&designation=` with the designation's ID number to the button's link or the page's URL."

### Proposed New Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  SINGLE Embedded Form Campaign                               │
│      └── Contains ALL ~861 designations                      │
│      └── Donor sees dropdown: "I'd like to support"          │
│      └── Pre-select via URL param: &designation={id}         │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ Same embed code everywhere
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  WordPress fund-form.php                                     │
│      └── Single campaign ID + component ID (config)          │
│      └── Dynamic designation param from post meta            │
│      └── Already have: _gofundme_designation_id              │
└─────────────────────────────────────────────────────────────┘
```

### Comparison: Old Plan vs New Approach

| Aspect | Old Plan (07-01) | New Approach |
|--------|------------------|--------------|
| Campaigns | 861 (one per fund) | 1 (for all funds) |
| ACF fields needed | campaign_id + component_id per fund | None (already have designation_id) |
| Embed code | Different per fund | Same everywhere |
| Pre-selection | Automatic (dedicated campaign) | Via `&designation=` param |
| Maintenance | 861 campaigns to manage | 1 campaign to manage |
| New fund workflow | Create campaign in Classy UI | ??? (question pending) |

### Follow-up Questions Sent (2026-01-28)

1. **Designation parameter with embeds:** Does `&designation=` work with inline embedded forms (`<div id="..." classy="...">`), or only with URL links to Classy-hosted pages?

2. **New fund automation:** When our plugin creates a new designation via API, how does it get added to the single campaign?
   - Automatic (campaign inherits all org-level designations)?
   - API endpoint available to add designation to campaign?
   - Manual UI work required each time?

### Status

**ON HOLD** - Current 07-01-PLAN.md may need complete revision depending on Classy's answers. If the new approach works, implementation becomes much simpler.

---

## Demo Live on Staging (2026-01-28)

Updated `fund-form.php` on staging with simple Classy embed for Ada B. Poole:

```php
<?php if (get_the_ID() == 1616): ?>
<div id="S9nYPwV-n0eBvabmj6qJk" classy="764041"></div>
<?php else: ?>
<!-- legacy form -->
<?php endif; ?>
```

**Demo URL:** https://frederickc2stg.wpenginepowered.com/funds/ada-b-poole-scholarship-fund/

**Files to update with final solution:**
- `fund-form.php` - Primary template (handles both fund page AND modal via `get_template_part()`)
- Verify `fund-modal.php` also uses `fund-form.php` template part (should work automatically)

**Note:** The simple `<div id="..." classy="..."></div>` format is all that's needed. The Classy WP plugin's SDK (loaded via Org ID 105659) finds these divs and renders the donation form.
