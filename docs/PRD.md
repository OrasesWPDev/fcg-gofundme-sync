# PRD: FCG GoFundMe Pro Sync - Bidirectional Sync Implementation

## Document Status
**Status:** FINAL DRAFT - Ready for Review
**Last Updated:** 2025-01-14

---

## Executive Summary

Implement bidirectional synchronization between WordPress "funds" custom post type and GoFundMe Pro (Classy) designations. Current plugin supports unidirectional sync (WordPress → GoFundMe). This PRD adds GoFundMe → WordPress sync via polling (webhooks not available for designations).

---

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Sync method (GFM→WP) | **Polling** | No designation webhook events in Classy |
| Polling frequency | **Every 15 minutes** | Balance of freshness vs API usage |
| Conflict resolution | **WordPress wins** | WP is source of truth |
| Deletion handling | **Trash WP post** | Preserve data, don't permanently delete |
| Donation webhooks | **Defer to later phase** | Focus on core designation sync first |
| Test environment | **WP Engine Staging** | No local development this phase |

---

## Current State

### Existing Plugin Architecture
- **API Client** (includes/class-api-client.php): OAuth2 auth, CRUD for designations
- **Sync Handler** (includes/class-sync-handler.php): WP → GFM sync on post lifecycle
- **Post Meta**: _gofundme_designation_id, _gofundme_last_sync

### Current Sync (Unidirectional - Working)
| WordPress Action | GoFundMe Result |
|------------------|-----------------|
| Publish fund | Create designation |
| Update fund | Update designation |
| Draft/Unpublish | is_active = false |
| Trash | is_active = false |
| Restore | is_active = true |
| Delete | Delete designation |

---

## Webhook Analysis (Classy API)

### Available Events (Beta)
- recurring_donation_plan.created/updated
- supporter.created/updated
- transaction.created/updated

### NOT Available
- designation.created
- designation.updated
- designation.deleted

**Conclusion:** Must use polling for designation sync from GoFundMe → WordPress.

---

## Implementation Phases

### Phase 1: Validation of Existing Sync
**Goal:** Verify current WordPress → GoFundMe sync works correctly on staging

**Environment:** WP Engine Staging (frederickc2stg) with Sandbox API

**Tasks:**
1. Pull latest main branch
2. Deploy plugin to WP Engine Staging via SSH
3. Verify sandbox credentials configured in WP Engine environment variables
4. Test all sync scenarios:
   - Create fund → designation created
   - Update fund → designation updated
   - Draft fund → is_active = false
   - Trash fund → is_active = false
   - Restore fund → is_active = true
   - Delete fund → designation deleted
5. Verify post meta storage
6. Check debug logs for errors

**Success Criteria:**
- All WP changes sync to GoFundMe Sandbox
- Designation IDs stored correctly
- No errors in debug log

---

### Phase 2: Polling Infrastructure
**Goal:** Fetch designation changes from GoFundMe

**New Files:**
- includes/class-sync-poller.php

**Tasks:**
1. Add API method: get_all_designations() with pagination
2. Create FCG_GFM_Sync_Poller class
3. Implement WP-Cron job (every 15 minutes)
4. Add WP-CLI command: wp fcg-sync pull
5. Store last poll timestamp in options table
6. Deploy to Staging and test

**API Endpoint:**
GET /organizations/{org_id}/designations

**Cron Schedule:**
- Hook: fcg_gofundme_sync_poll
- Interval: 15 minutes
- Callback: FCG_GFM_Sync_Poller::poll()

---

### Phase 3: Incoming Sync Logic
**Goal:** Apply GoFundMe changes to WordPress

**Tasks:**
1. Match designations to WP posts via external_reference_id (= post ID)
2. Handle orphaned designations (exist in GFM but not WP)
3. Detect changes: compare name, description, is_active, goal
4. Apply changes to WordPress posts
5. Implement sync loop prevention (flag to skip outbound sync during inbound)

**Sync Logic:**
```
For each designation from GoFundMe:
  1. Find WP post by external_reference_id OR _gofundme_designation_id
  2. If not found: log as orphan, skip (or optionally create)
  3. If found: compare fields
  4. If different AND GFM updated_at > WP _gofundme_last_sync:
     - WordPress wins, so only update if no WP changes since last sync
     - If WP was also modified, keep WP version (log conflict)
  5. If is_active changed to false: move WP post to draft
  6. If designation deleted: move WP post to trash
```

**New Post Meta:**
- _gofundme_sync_source: 'wordpress' or 'gofundme' (tracks origin of last change)
- _gofundme_poll_hash: hash of last polled data (for change detection)

---

### Phase 4: Conflict Detection
**Goal:** Handle simultaneous edits gracefully

**Strategy: WordPress Wins**
- If WP post modified since last sync AND GFM designation also modified:
  - Keep WordPress version
  - Push WP changes to GFM (overwrite)
  - Log the conflict for admin review

**Sync Loop Prevention:**
- Set transient flag during inbound sync
- Outbound sync checks flag and skips if set
- Flag auto-expires after 30 seconds

---

### Phase 5: Admin UI
**Goal:** Visibility into sync status

**Tasks:**
1. Add "Sync Status" column to funds list table
   - Synced (green) - last sync within 15 min, no errors
   - Pending (yellow) - changes waiting to sync
   - Error (red) - last sync failed
   - Orphaned (gray) - no GFM designation linked
2. Add meta box on fund edit screen:
   - Last sync timestamp
   - Designation ID (linked to GFM)
   - Manual "Sync Now" button
3. Add admin notice for sync errors
4. Add Settings page:
   - Enable/disable auto-polling
   - Polling interval (15min, 30min, 1hr)
   - Manual "Sync All" button

---

### Phase 6: Error Handling
**Goal:** Graceful failure and recovery

**Tasks:**
1. Log all sync operations to custom log
2. Track failed syncs in post meta
3. Implement retry logic (max 3 attempts with exponential backoff)
4. Admin notification for persistent failures
5. WP-CLI command to retry failed syncs: wp fcg-sync retry

---

## Technical Specifications

### New Files
```
includes/
  class-sync-poller.php      # Polling logic, cron job
  class-admin-ui.php         # Admin columns, meta boxes, settings
```

### New Post Meta Keys
| Key | Purpose |
|-----|---------|
| _gofundme_sync_source | Last sync direction (wp/gfm) |
| _gofundme_poll_hash | Hash for change detection |
| _gofundme_sync_error | Last error message |
| _gofundme_sync_attempts | Failed retry count |

### New Options
| Key | Purpose |
|-----|---------|
| fcg_gfm_last_poll | Timestamp of last poll |
| fcg_gfm_poll_enabled | Enable/disable auto-polling |
| fcg_gfm_poll_interval | Interval in seconds |

### WP-CLI Commands
```
wp fcg-sync pull        # Manual poll from GoFundMe
wp fcg-sync push        # Push all WP funds to GoFundMe
wp fcg-sync status      # Show sync status for all funds
wp fcg-sync retry       # Retry failed syncs
```

---

## Development Workflow

1. Pull latest main
2. Create feature branch (e.g., feature/phase-2-polling)
3. Implement changes
4. Bump version in plugin header
5. Deploy to WP Engine Staging via SSH
6. Test with Sandbox API
7. **STOP** - Wait for user testing approval
8. Push to repo after approval

---

## Environments

| Environment | SSH | API | Purpose |
|-------------|-----|-----|---------|
| Staging | frederickc2stg.ssh.wpengine.net | Sandbox | All testing this phase |
| Production | frederickcount.ssh.wpengine.net | Production | After full approval |

**Note:** Local development environment not used this phase.

---

## Risk Assessment

| Risk | Mitigation |
|------|------------|
| API rate limits | 15-min polling interval, exponential backoff |
| Webhook beta instability | Using polling instead |
| Sync loops | Transient flag during sync operations |
| Data loss on conflict | WordPress wins, log conflicts for review |
| Cron reliability | WP-CLI fallback for manual sync |

---

## Future Enhancements (Out of Scope)

- Webhook support for donations (transaction.* events)
- Real-time donation totals on fund pages
- Supporter/donor data sync
- Multi-site support

---

## Approval

- [ ] User approves PRD
- [ ] Begin Phase 1 implementation
