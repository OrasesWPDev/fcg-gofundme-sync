# Architecture Pivot: Single Campaign with Designations

**Date:** 2026-01-28
**Triggered by:** Classy response from Luke Dringoli (GoFundMe)
**Status:** ✅ IMPLEMENTED (Phases 5-7 complete as of 2026-01-29)

---

## Summary

Classy confirmed a dramatically simpler architecture. Instead of creating 861 campaigns (one per fund), we use **ONE master campaign** with all designations loaded. The `?designation={id}` URL parameter pre-selects the fund on the embedded form.

---

## Classy's Answers (from email 2026-01-28)

### Question 1: Designation parameter with embedded forms

> **Q:** Does `?designation=[id]` work with the inline embedded form (`<div id="..." classy="...">`), or only with direct URL links?

> **A:** Yes, the designation pass-through parameter is compatible with inline forms. Just ensure the parameter is always included in the URL when linking to the page.

### Question 2: New fund workflow

> **Q:** When our plugin creates a new designation via API, how does it get added to the single campaign?

> **A:** Campaigns do NOT automatically inherit organization-level designations, but you CAN add a designation to a campaign programmatically using the `updateCampaign` endpoint:

```
PUT /campaigns/{campaign_id}
{"designation_id": "{designation_id}"}
```

Response confirms: `200 OK` with the designation linked.

---

## Old vs New Architecture

### OLD (Phases 1-4 built this):
```
WordPress Fund → Designation (API) → Campaign (API duplicate)
                                          ↓
                              861 Campaigns (one per fund)
```

### NEW (what we're pivoting to):
```
WordPress Fund → Designation (API) → Link to Master Campaign (API update)
                                          ↓
                              1 Master Campaign (all designations)
```

---

## Impact on Existing Phases

### Pre-Phase Work (KEEP ALL)
The existing designation sync was built BEFORE phases 1-4 and is **critical to keep**:
- Designation create on publish
- Designation update on save
- Designation deactivate on trash/unpublish
- Designation reactivate on untrash
- Designation delete on permanent delete
- OAuth2, token caching, retry logic, WP-CLI

### Phase 1: Configuration
| Item | Action |
|------|--------|
| Template Campaign ID setting | **RENAME** to "Master Campaign ID" |
| Fundraising goal field | **KEEP** - still useful for display |

### Phase 2: Campaign Push Sync
| Item | Action |
|------|--------|
| Campaign duplication on publish | **REMOVE** - not needed |
| Campaign update on fund update | **REMOVE** - not needed |
| `duplicate_campaign()` method | **REMOVE** |
| `publish_campaign()` method | **REMOVE** (or repurpose) |
| Campaign ID post meta storage | **REMOVE** - no longer storing per-fund |

### Phase 3: Campaign Status Management
| Item | Action |
|------|--------|
| Campaign unpublish on draft | **REMOVE** - master campaign stays active |
| Campaign deactivate on trash | **REMOVE** |
| Campaign reactivate on restore | **REMOVE** |

### Phase 4: Inbound Sync
| Item | Action |
|------|--------|
| Poll donation totals | **MODIFY** - poll by designation, not campaign |
| Poll campaign status | **REMOVE** - master campaign status doesn't change per fund |

### Phase 5: Bulk Migration
| Item | Action |
|------|--------|
| Create 758 campaigns via WP-CLI | **MOOT** - completely unnecessary |
| Entire phase | **REMOVE from roadmap** |

### Phase 6: Admin UI
| Item | Action |
|------|--------|
| Show campaign URL | **MODIFY** - show master campaign URL or designation info |
| Show donation totals | **KEEP** - still relevant |
| Sync status | **KEEP** - still relevant |

### Phase 7: Frontend Embed
| Item | Action |
|------|--------|
| ACF fields per fund (component_id, campaign_id) | **NOT NEEDED** |
| Embed code in fund-form.php | **SIMPLIFY** - single embed + `?designation={id}` |

---

## New Work Required

### 1. Link Designation to Master Campaign (NEW)

After creating a designation, call `updateCampaign` to link it:

```php
// In FCG_Sync_Handler::handle_fund_publish() or similar
// After successful designation creation:

$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
$designation_id = get_post_meta($post_id, '_gofundme_designation_id', true);

$this->api_client->update_campaign($master_campaign_id, [
    'designation_id' => $designation_id
]);
```

### 2. Master Campaign Setup (ONE-TIME MANUAL)

- Create single "master" campaign in Classy UI
- Configure branding, settings, payment processing
- Safelist the WordPress domain for embeds
- Store campaign ID in plugin settings

### 3. Frontend Embed Update

```php
// fund-form.php - simplified
$designation_id = get_post_meta($post_id, '_gofundme_designation_id', true);
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
$master_component_id = get_option('fcg_gofundme_master_component_id');

// URL includes designation param
$current_url = add_query_arg('designation', $designation_id, get_permalink());
?>
<div id="<?php echo esc_attr($master_component_id); ?>"
     classy="<?php echo esc_attr($master_campaign_id); ?>"></div>
```

### 4. Inbound Sync Modification

Change from polling campaigns to polling designations for donation data. Need to verify Classy API supports this - may need to poll campaign totals broken down by designation.

---

## Proposed New Roadmap

| Phase | Name | Description |
|-------|------|-------------|
| 1-4 | (Complete) | Keep designation sync, remove campaign sync code |
| 5 | **Code Cleanup** | Remove campaign duplication/status code from Phases 2-3 |
| 6 | **Master Campaign Link** | Add `updateCampaign` call after designation creation |
| 7 | **Settings Update** | Rename template → master campaign, add component ID |
| 8 | **Frontend Embed** | Simplified embed with `?designation={id}` parameter |
| 9 | **Admin UI** | Display designation/donation info (simplified) |
| 10 | **Inbound Sync Update** | Poll designation-level donation data |

---

## Questions to Verify

1. **Designation donation totals** - Can we get donation totals per designation via API? Or only at campaign level?
2. **Existing campaign code** - Any hooks/filters that might break if we remove campaign sync?
3. **Master campaign embed code** - Need component ID from Classy UI after setup

---

## Implementation Status

All architecture pivot work has been completed:

| Phase | Status | Notes |
|-------|--------|-------|
| Phase 5: Code Cleanup | ✅ Complete | ~430 lines removed |
| Phase 6: Master Campaign Link | ✅ Complete | Designation linking works |
| Phase 7: Settings Update | ✅ Complete | Renamed to "Master Campaign" |
| Phase 8: Frontend Embed | ✅ Complete | With modal workaround |
| Phase 9: Admin UI | Pending | Optional enhancement |

**Known Issue:** Classy SDK incompatible with Bootstrap modals. Workaround deployed (direct fund page links instead of modal popups).

---

*Document created: 2026-01-28*
*Implementation completed: 2026-01-29*
*Source: Email thread with Luke Dringoli, GoFundMe*
