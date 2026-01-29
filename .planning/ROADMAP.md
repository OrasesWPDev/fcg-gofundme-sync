# Roadmap: FCG GoFundMe Pro Sync

## Overview

This roadmap manages the WordPress plugin that synchronizes "funds" custom post type with GoFundMe Pro (Classy).

**Architecture (as of 2026-01-28):** Single master campaign with all designations. When a fund is published, our plugin creates a designation and links it to the master campaign. The frontend embed uses `?designation={id}` to pre-select the correct fund.

**Archived:** Phases 2, 3, and original 5 (per-fund campaign approach) were completed but made obsolete by the architecture pivot. See `.planning/phases/archived/ARCHIVE-README.md`.

## Phases

**Completed:**
- [x] **Phase 1: Configuration** - Template campaign setting and fundraising goal field
- [x] **Phase 4: Inbound Sync** - Poll donation totals from Classy
- [x] **Phase 5: Code Cleanup** - Remove obsolete campaign sync code
- [x] **Phase 6: Master Campaign Integration** - Settings + link designations to default group

**In Progress:**
- [ ] **Phase 7: Frontend Embed** - Simplified embed with `?designation={id}` parameter

**Upcoming:**
- [ ] **Phase 8: Admin UI** - Display designation and donation info (optional)

## Phase Details

### Phase 1: Configuration (COMPLETE)
**Status**: Complete (2026-01-23)

Plans:
- [x] 01-01-PLAN.md - Create template campaign in Classy sandbox
- [x] 01-02-PLAN.md - Add template campaign ID setting
- [x] 01-03-PLAN.md - Add fundraising goal field

---

### Phase 4: Inbound Sync (COMPLETE)
**Status**: Complete (2026-01-26)

Plans:
- [x] 04-01-PLAN.md - Campaign overview API method and sync poller

---

### Phase 5: Code Cleanup (COMPLETE)
**Goal**: Remove obsolete campaign duplication and status management code
**Status**: Complete (2026-01-29)
**Plans:** 4 plans in 3 waves

Plans:
- [x] 05-01-PLAN.md — Remove campaign methods from sync-handler and API client
- [x] 05-02-PLAN.md — Update sync-poller and admin-ui for post-pivot state
- [x] 05-03-PLAN.md — Update CLAUDE.md and bump plugin version to 2.3.0
- [x] 05-04-PLAN.md — Deploy to staging and verify designation sync

**Results:**
- ~430 lines of campaign code removed
- Plugin v2.3.0 deployed to staging
- 859 published funds all have designation IDs
- 11 test designations cleaned up from Classy

**Key Finding:** Creating a designation via API does NOT add it to the campaign's "Default Active Group". Phase 6 addresses this.

---

### Phase 6: Master Campaign Integration (COMPLETE)
**Goal**: Configure master campaign settings and link new designations to master campaign's active group
**Depends on**: Phase 5 (clean codebase)
**Status**: Complete (2026-01-29)
**Plans:** 2 plans in 2 waves

Plans:
- [x] 06-01-PLAN.md — Settings update (rename template to master, add component ID) + sync handler linking
- [x] 06-02-PLAN.md — Deploy to staging and verify designation appears in master campaign

**Results:**
- Settings renamed from "Template" to "Master Campaign"
- Master Component ID setting added for frontend embeds
- Automatic designation linking via `update_campaign()` API
- 862 designations now in Default Active Group
- 5 pending designations linked via one-time script

**Known Issue:** `update_campaign()` API sets each new designation as campaign default. Workaround: manually reset in Classy UI. See 06-02-SUMMARY.md for details.

**Manual work:** ~~Create master campaign in Classy UI~~ Done (Campaign 764694, Component mKAgOmLtRHVGFGh_eaqM6)

---

### Phase 7: Frontend Embed
**Goal**: Fund pages display Classy donation embed with correct designation pre-selected
**Depends on**: Phase 6 (settings available)
**Status**: Planned (2026-01-29)
**Plans:** 2 plans in 2 waves

Plans:
- [ ] 07-01-PLAN.md — Replace fund-form.php with Classy embed + designation pre-selection
- [ ] 07-02-PLAN.md — Deploy to staging and verify on single fund page AND modal popup

**Implementation:**
```php
// fund-form.php
$designation_id = get_post_meta($post_id, '_gofundme_designation_id', true);
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
$master_component_id = get_option('fcg_gofundme_master_component_id');
?>
<div id="<?php echo esc_attr($master_component_id); ?>"
     classy="<?php echo esc_attr($master_campaign_id); ?>"></div>
```

URL includes `?designation={id}` to pre-select the fund.

**Success Criteria:**
1. Fund page displays Classy donation embed
2. Correct fund is pre-selected in designation dropdown
3. Modal popup uses same embed

**Prior work:** See `.planning/phases/archived/07-frontend-embed-original/` for context docs

---

### Phase 8: Admin UI (Optional)
**Goal**: Display designation and donation info in WordPress admin
**Depends on**: Phases 6-7

**Scope:**
- Designation ID in fund edit meta box
- Donation totals (from inbound sync)
- Last sync timestamp
- Manual "Sync Now" button

---

## Progress

| Phase | Status | Completed |
|-------|--------|-----------|
| 1. Configuration | Complete | 2026-01-23 |
| 4. Inbound Sync | Complete | 2026-01-26 |
| 5. Code Cleanup | Complete | 2026-01-29 |
| 6. Master Campaign Integration | Complete | 2026-01-29 |
| 7. Frontend Embed | **Planned** | - |
| 8. Admin UI | Not started | - |

---

## Architecture Reference

```
WordPress Fund
    ↓ (on publish)
Designation created via API
    ↓ (Phase 6)
Designation linked to Master Campaign
    ↓ (Phase 7)
Frontend: <div classy="{master_id}"> + ?designation={id}
    ↓
Classy embed renders with fund pre-selected
```

---

## Manual Work Required

| Phase | Task | Status |
|-------|------|--------|
| 6 | Create master campaign in Classy UI | Done (764694) |
| 6 | Note campaign ID and component ID | Done (mKAgOmLtRHVGFGh_eaqM6) |
| 6 | Research Classy API for adding to group | Done (Luke confirmed approach) |
| 4 | Enable Alternate Cron on WP Engine Production | Pending |

---

*Last updated: 2026-01-29*
