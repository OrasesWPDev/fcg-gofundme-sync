# Roadmap: FCG GoFundMe Pro Sync

## Overview

This roadmap extends the existing designation sync plugin to support bi-directional campaign synchronization with Classy. Phase 1 establishes configuration infrastructure, Phases 2-3 implement outbound push sync with status management, Phase 4 adds inbound donation polling, Phase 5 provides bulk migration for 758 existing funds, and Phase 6 enhances admin visibility. The journey uses campaign duplication (the only public creation method) and extends proven sync patterns from the existing designation implementation.

## Manual Work

Some phases require manual work that cannot be automated via API:

| Phase | Manual Task | Assistance Options | Status |
|-------|-------------|-------------------|--------|
| 1 | Create template campaign in Classy sandbox | Screenshots for guidance, or `/chrome` browser automation | Complete |
| 2 | Add ACF checkbox field for campaign sync opt-out | WordPress admin UI | Pending |
| 4 | Enable Alternate Cron on WP Engine | WP Engine dashboard toggle | Staging done |

Plans for these phases will include explicit user steps. For Classy UI tasks, the user can:
- Share screenshots and receive guidance on next steps
- Allow agent to assist via browser automation (`/chrome`)

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Configuration** - Add template campaign setting and fundraising goal field
- [x] **Phase 2: Campaign Push Sync** - Create and update campaigns via duplication when funds publish/update
- [x] **Phase 3: Campaign Status Management** - Sync campaign status with WordPress post status transitions
- [ ] **Phase 4: Inbound Sync** - Poll donation totals and campaign status from Classy
- [ ] **Phase 5: Bulk Migration** - WP-CLI tool to create campaigns for existing funds
- [ ] **Phase 6: Admin UI** - Display campaign data and sync controls in WordPress admin
- [ ] **Phase 7: Frontend Embed Integration** - Replace legacy donation form with Classy embed on fund pages

## Phase Details

### Phase 1: Configuration
**Goal**: Admin can configure campaign template and fundraising goals for fund creation
**Depends on**: Nothing (first phase)
**Requirements**: CONF-01, CONF-02
**Plans:** 3 plans

Plans:
- [x] 01-01-PLAN.md - Create template campaign in Classy sandbox (manual)
- [x] 01-02-PLAN.md - Add template campaign ID setting with API validation
- [x] 01-03-PLAN.md - Add fundraising goal field to fund meta box

**Success Criteria** (what must be TRUE):
  1. Admin can set template campaign ID in plugin settings
  2. Template campaign ID is validated against Classy API on save
  3. Fundraising goal field exists on fund edit screen
  4. Goal value is saved with fund post meta

**Manual Work Required:**
This phase includes manual Classy sandbox work that cannot be done via API:
- Create template campaign in Classy with desired defaults (branding, description, settings)
- Note the campaign ID for plugin configuration

*Plan must include step-by-step instructions for manual tasks. User can share screenshots for guidance, or agent can assist via browser automation (`/chrome`).*

### Phase 2: Campaign Push Sync
**Goal**: Published WordPress funds automatically create and update campaigns in Classy
**Depends on**: Phase 1 (requires template campaign ID)
**Requirements**: CAMP-01, CAMP-02, CAMP-03, CAMP-04, CAMP-05, CAMP-06
**Plans:** 4 plans

Plans:
- [x] 02-01-PLAN.md - Add campaign lifecycle API methods (duplicate, publish, unpublish, reactivate)
- [x] 02-02-PLAN.md - Rewrite campaign creation to use duplication workflow
- [x] 02-03-PLAN.md - Fix campaign restore workflow and add sync opt-out
- [x] 02-04-PLAN.md - E2E verification and ACF field setup (includes human step)

**Success Criteria** (what must be TRUE):
  1. When fund is published, campaign is created in Classy via template duplication
  2. When fund title or goal is updated, campaign name and goal update in Classy
  3. When fund is trashed, campaign is deactivated in Classy
  4. When fund is restored from trash, campaign is reactivated and published in Classy
  5. Campaign ID and URL are stored in fund post meta after sync

**Manual Work Required:**
- Add "Disable Campaign Sync" checkbox field to ACF field group (Plan 02-04 checkpoint)

### Phase 3: Campaign Status Management
**Goal**: Campaign publish/unpublish status stays synchronized with WordPress post status
**Depends on**: Phase 2 (requires campaign creation working)
**Requirements**: STAT-01, STAT-02, STAT-03
**Plans:** 1 plan

Plans:
- [x] 03-01-PLAN.md - Fix draft status to call unpublish_campaign() instead of deactivate

**Success Criteria** (what must be TRUE):
  1. When fund is set to draft, campaign is unpublished (not deactivated)
  2. When fund is republished from draft, campaign returns to active status
  3. Campaign status correctly maps: publish->active, draft->unpublished, trash->deactivated
  4. Two-step restore works: reactivate then publish

**Bonus (user feedback):**
  - Campaign ID now displayed in admin meta box (delivered early from Phase 6)

### Phase 4: Inbound Sync
**Goal**: Donation totals and campaign status automatically sync from Classy to WordPress
**Depends on**: Phase 2 (requires campaigns to exist)
**Requirements**: SYNC-01, SYNC-02, SYNC-03, SYNC-04
**Plans:** 1 plan

Plans:
- [ ] 04-01-PLAN.md - Add campaign overview API method and extend sync poller

**Success Criteria** (what must be TRUE):
  1. Donation totals are fetched from Classy every 15 minutes
  2. Campaign status (active/unpublished/deactivated) is fetched and reflected in post meta
  3. Goal progress percentage is calculated and stored
  4. Inbound sync updates post meta without triggering outbound sync
  5. Sync runs via server cron (not WP-Cron)

**Manual Work Required:**
- Enable "Alternate Cron" in WP Engine dashboard (replaces wp-cron with reliable server cron)
- Staging: Already configured (Alternate Cron enabled)
- Production: Will need same toggle enabled before go-live

### Phase 5: Bulk Migration
**Goal**: All 758 existing funds without campaigns get campaigns created via WP-CLI
**Depends on**: Phases 2-3 (requires sync operations validated)
**Requirements**: MIGR-01, MIGR-02, MIGR-03, MIGR-04, MIGR-05
**Success Criteria** (what must be TRUE):
  1. WP-CLI command creates campaigns for funds lacking campaign IDs
  2. Migration runs in 50-fund batches to avoid timeouts
  3. Migration can be resumed if interrupted (idempotent)
  4. Dry-run mode shows what would happen without making changes
  5. Migration logs success/failure for each fund
**Plans**: TBD

Plans:
- [ ] TBD (will be created during plan-phase)

### Phase 6: Admin UI
**Goal**: Campaign data and sync status are visible in WordPress admin interface
**Depends on**: Phases 2-4 (requires sync operations working)
**Requirements**: ADMN-01, ADMN-02, ADMN-03, ADMN-04
**Success Criteria** (what must be TRUE):
  1. Campaign URL displays as clickable link in fund edit meta box
  2. Current donation total displays in fund edit meta box
  3. Last sync timestamp displays in fund edit meta box
  4. Manual "Sync Now" button triggers immediate sync for individual fund
**Plans**: TBD

Plans:
- [ ] TBD (will be created during plan-phase)

### Phase 7: Frontend Embed Integration
**Goal**: Public fund pages use Classy's embedded donation form instead of legacy cart system
**Depends on**: Phase 2 (requires campaigns to exist with valid IDs)
**Requirements**: EMBD-01, EMBD-02, EMBD-03, EMBD-04
**Success Criteria** (what must be TRUE):
  1. Fund single template displays Classy donation embed instead of legacy form
  2. Embed uses the campaign ID stored in fund post meta
  3. Embed loads correctly for API-created campaigns
  4. Legacy js-add-to-cart form is removed or conditionally hidden
  5. Fallback behavior when fund has no campaign ID
**Plans**: TBD

Plans:
- [ ] TBD (will be created during plan-phase)

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Configuration | 3/3 | Complete | 2026-01-23 |
| 2. Campaign Push Sync | 4/4 | Complete | 2026-01-26 |
| 3. Campaign Status Management | 1/1 | Complete | 2026-01-26 |
| 4. Inbound Sync | 0/1 | Planned | - |
| 5. Bulk Migration | 0/0 | Not started | - |
| 6. Admin UI | 0/0 | Not started | - |
| 7. Frontend Embed Integration | 0/0 | Not started | - |
