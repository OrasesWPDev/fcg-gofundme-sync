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
- [x] **Phase 7: Frontend Embed** - Classy embed on single fund pages, modal removal on archive

**Upcoming:**
- [ ] **Phase 8: Production Launch (MVP)** - Admin UI, delete sync, production deployment
- [ ] **Phase 9: Modal & Theme Enhancements** - Classy button links, fund-modal.php fix, theme refactor

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

### Phase 7: Frontend Embed (COMPLETE)
**Goal**: Fund pages display Classy donation embed with correct designation pre-selected
**Depends on**: Phase 6 (settings available)
**Status**: Complete (2026-01-29)
**Plans:** 2 plans

Plans:
- [x] 07-01-PLAN.md — Replace fund-form.php with Classy embed + designation pre-selection
- [x] 07-02-PLAN.md — Deploy to staging and verify

**Results:**
- Classy embed working on single fund pages
- URL parameter injection (`?designation={id}`) pre-selects correct fund
- **Modal limitation discovered:** Classy SDK incompatible with Bootstrap modals
- Archive page modals disabled; "Learn More" changed to "Give Now" direct links

**Theme Files Modified:**
- `fund-form.php` - Classy embed implementation
- `archive-funds.php` - Modal disabled, direct links to fund pages

**Key Finding:** Classy SDK uses custom elements that fail inside Bootstrap modals with `Failed to construct 'HTMLElement': Illegal constructor`. This is a fundamental SDK architecture limitation. Meeting scheduled with Classy (2026-01-29 4pm) to discuss alternatives.

**Documentation:**
- `docs/theme-fund-form-embed.md` - Deployment guide
- `docs/classy-technical-questions.md` - Questions for Classy meeting
- `docs/client-fund-page-changes.md` - Client explanation

**Success Criteria (Revised):**
1. ✅ Single fund page displays Classy donation embed
2. ✅ Correct fund is pre-selected in designation dropdown
3. ⚠️ Modal popup removed (SDK limitation) - direct links used instead

---

### Phase 8: Production Launch (MVP)
**Goal**: Complete admin UI, verify delete sync, plan production deployment
**Depends on**: Phase 7
**Status**: Not started

**Scope:**
- Admin meta box showing designation ID (clickable link to Classy)
- Donation totals display (from inbound sync)
- Last sync timestamp
- Manual "Sync Now" button
- Test DELETE endpoint on staging (verify designation removal from campaign + designations list)
- **Production deployment planning** (checklist, credential configuration guide)

**Production Deployment (planning only - execution by user):**
- See: `docs/production-deployment-checklist.md`
- Credentials configured via WP Engine environment variables (not stored in repo)
- Theme files deployed via rsync

**Exclusions (deferred to Phase 9):**
- Modal popup functionality (fund-modal.php)
- Archive page quick-donate buttons
- Theme PHP file consolidation

**Success Criteria:**
1. Admin can see designation info in fund edit screen
2. Delete a fund on staging → designation removed from Classy entirely
3. Production deployment checklist documented
4. Staging fully tested before production go-live

---

### Phase 9: Modal & Theme Enhancements (Post-MVP)
**Goal**: Restore modal functionality using Classy button links, consolidate theme files
**Depends on**: Phase 8 (production stable)
**Status**: Future

**Background (from Classy call 2026-01-29):**
Luke Dringoli recommended using Classy's "button link" version instead of Bootstrap modals. This opens the Classy donation modal directly without an intermediate modal. Jon Bierma noted designation ID persistence is only an issue with the modal (fund-modal.php) since it lacks post context.

**Scope:**
- Implement Classy button link for archive page "Give Now"
- Refactor fund-modal.php to pass designation ID correctly
- Update remaining templates (search.php, taxonomy-fund-category.php, template-flexible.php)
- Theme consolidation: merge fund-form.php and fund-modal.php logic into single-funds.php template
- Remove scattered PHP template files

**Files Affected:**
- `fund-modal.php` - Currently disabled, needs Classy button link implementation
- `archive-funds.php` - Re-enable modal triggers with new approach
- `search.php` (~line 203) - Modal removal/update
- `taxonomy-fund-category.php` (~line 44) - Modal removal/update
- `template-flexible.php` (~line 964) - Modal removal/update

**Classy Recommendations:**
- Use Classy button link: `<a class="classy-give-button" data-campaign="{id}" data-designation="{id}">Give Now</a>`
- Opens Classy modal directly, bypasses Bootstrap modal entirely
- Requires SDK initialization on page

---

## Progress

| Phase | Status | Completed |
|-------|--------|-----------|
| 1. Configuration | Complete | 2026-01-23 |
| 4. Inbound Sync | Complete | 2026-01-26 |
| 5. Code Cleanup | Complete | 2026-01-29 |
| 6. Master Campaign Integration | Complete | 2026-01-29 |
| 7. Frontend Embed | Complete | 2026-01-29 |
| 8. Production Launch (MVP) | Not started | - |
| 9. Modal & Theme Enhancements | Future | - |

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
| 7 | Meet with Classy re: modal compatibility | Done (2026-01-29) - workaround confirmed |
| 4 | Enable Alternate Cron on WP Engine Production | Pending (Phase 8) |
| 8 | Create master campaign in Production Classy account | Done (764752) |
| 8 | Generate Production API credentials | Done |
| 8 | Get Master Component ID from production campaign | Done (CngmDfcvOorpIS4KOTO4H) |
| 8 | Set WP Engine environment variables | Pending |
| 8 | Deploy plugin v2.3.0 to production | Pending |
| 8 | Deploy theme files to production (rsync) | Pending |
| 8 | Configure production plugin settings | Pending |
| 9 | Update remaining templates (search, taxonomy, flexible) | Future |

## Classy Developer Call Notes (2026-01-29)

**Attendees:** Chad Diaz, Luke Dringoli, Jon Bierma (GoFundMe/Classy)

**Key Confirmations:**
- Architecture validated (single master campaign + designations)
- DELETE endpoint removes designation from campaign list AND designations entirely
- Deactivate (PUT `is_active: false`) leaves designation in campaign list but inactive
- Direct fund page links workaround is appropriate for modal limitation

**Recommendations for Phase 9:**
- Use Classy "button link" for quick donate (bypasses Bootstrap modal)
- Theme consolidation to simplify designation ID handling

**Recording:** tldv meeting ID 697bcdc55095a70013422fd8

## Production Environment Reference

| Item | Value |
|------|-------|
| SSH | `frederickcount@frederickcount.ssh.wpengine.net` |
| Org ID | `104060` |
| Master Campaign ID | `764752` |
| Component ID | `CngmDfcvOorpIS4KOTO4H` |

**Full deployment checklist:** `docs/production-deployment-checklist.md`

**Credentials:**
- Local reference: `.env.credentials` (gitignored, contains both staging and production)
- Configured via WP Engine environment variables

---

*Last updated: 2026-01-29*
