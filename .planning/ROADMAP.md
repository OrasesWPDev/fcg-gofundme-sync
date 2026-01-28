# Roadmap: FCG GoFundMe Pro Sync

## Overview

This roadmap manages the WordPress plugin that synchronizes "funds" custom post type with GoFundMe Pro (Classy).

**Architecture (as of 2026-01-28):** Single master campaign with all designations. When a fund is published, our plugin creates a designation and links it to the master campaign. The frontend embed uses `?designation={id}` to pre-select the correct fund.

**Archived:** Phases 2, 3, and original 5 (per-fund campaign approach) were completed but made obsolete by the architecture pivot. See `.planning/phases/archived/ARCHIVE-README.md`.

## Phases

**Completed:**
- [x] **Phase 1: Configuration** - Template campaign setting and fundraising goal field
- [x] **Phase 4: Inbound Sync** - Poll donation totals from Classy

**New Work (Post-Pivot):**
- [ ] **Phase 5: Code Cleanup** - Remove obsolete campaign sync code
- [ ] **Phase 6: Master Campaign Integration** - Settings + link designations to master campaign
- [ ] **Phase 7: Frontend Embed** - Simplified embed with `?designation={id}` parameter
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

### Phase 5: Code Cleanup
**Goal**: Remove obsolete campaign duplication and status management code
**Status**: Planning complete
**Plans:** 4 plans in 3 waves

Plans:
- [ ] 05-01-PLAN.md — Remove campaign methods from sync-handler and API client
- [ ] 05-02-PLAN.md — Update sync-poller and admin-ui for post-pivot state
- [ ] 05-03-PLAN.md — Update CLAUDE.md and bump plugin version to 2.3.0
- [ ] 05-04-PLAN.md — Deploy to staging and verify designation sync

**What to remove:**
- `duplicate_campaign()`, `publish_campaign()`, `unpublish_campaign()`, `deactivate_campaign()`, `reactivate_campaign()` methods
- Campaign status hooks in sync handler
- Per-fund campaign ID/URL post meta logic

**What to keep:**
- All designation sync code
- OAuth2 and API client infrastructure
- Inbound sync polling infrastructure

**Success Criteria:**
1. Plugin still syncs designations correctly
2. No orphaned campaign code
3. Plugin activates without errors

---

### Phase 6: Master Campaign Integration
**Goal**: Configure master campaign settings and link designations to it
**Depends on**: Phase 5 (clean codebase)

**Combines:**
- Rename "Template Campaign ID" → "Master Campaign ID"
- Add "Master Component ID" setting (for embed code)
- After designation creation, call `PUT /campaigns/{id}` with `{"designation_id": "{id}"}`

**Success Criteria:**
1. Admin can configure master campaign ID and component ID
2. When fund is published, designation is linked to master campaign
3. New designations appear in master campaign's dropdown

**Manual work:** Create master campaign in Classy UI first

---

### Phase 7: Frontend Embed
**Goal**: Fund pages display Classy donation embed with correct designation pre-selected
**Depends on**: Phase 6 (settings available)

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
| 5. Code Cleanup | Planning complete | - |
| 6. Master Campaign Integration | Not started | - |
| 7. Frontend Embed | Not started | - |
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
| 6 | Create master campaign in Classy UI | Pending |
| 6 | Note campaign ID and component ID | Pending |
| 4 | Enable Alternate Cron on WP Engine Production | Pending |

---

*Last updated: 2026-01-28*
