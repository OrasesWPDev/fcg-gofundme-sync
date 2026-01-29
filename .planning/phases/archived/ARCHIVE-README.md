# Archived Phases: Campaign Sync (Per-Fund Approach)

**Archived:** 2026-01-28
**Reason:** Architecture pivot to single master campaign approach

---

## Why These Phases Were Archived

On 2026-01-28, Classy (Luke Dringoli, Jon Bierma) confirmed a dramatically simpler architecture:

| Original Approach | New Approach |
|-------------------|--------------|
| 861 campaigns (one per fund) | ONE master campaign with all designations |
| Campaign duplication on fund publish | Link designation to master campaign via API |
| Per-fund campaign status management | Master campaign stays active always |
| Bulk migration tool for 758 funds | Not needed |

The work in these archived phases was **completed and working**, but is now obsolete due to this architectural change.

---

## Archived Phases

### Phase 2: Campaign Push Sync
**Status:** Complete (but now obsolete)
**What it did:**
- Created campaigns via template duplication when funds published
- Updated campaign name/goal when funds updated
- Stored campaign ID and URL in post meta

**Files:**
- `02-campaign-push-sync/02-01-PLAN.md` - Campaign lifecycle API methods
- `02-campaign-push-sync/02-02-PLAN.md` - Campaign creation via duplication
- `02-campaign-push-sync/02-03-PLAN.md` - Restore workflow & sync opt-out
- `02-campaign-push-sync/02-04-PLAN.md` - E2E verification
- `02-campaign-push-sync/02-RESEARCH.md` - API research
- `02-campaign-push-sync/*-SUMMARY.md` - Execution summaries

### Phase 3: Campaign Status Management
**Status:** Complete (but now obsolete)
**What it did:**
- Synced campaign publish/unpublish status with WordPress post status
- Handled draft → unpublish, trash → deactivate, restore → reactivate+publish

**Files:**
- `03-campaign-status-management/03-01-PLAN.md` - Status management implementation
- `03-campaign-status-management/03-01-SUMMARY.md` - Execution summary

### Phase 5: Bulk Migration
**Status:** Blocked, then made obsolete
**What it would have done:**
- WP-CLI command to create campaigns for 758 existing funds
- Batch processing with resume capability

**Files:**
- `05-bulk-migration/05-01-PLAN.md` - Migration command design
- `05-bulk-migration/05-CONTEXT.md` - Context and research
- `05-bulk-migration/05-RESEARCH.md` - API research

**Note:** This phase was blocked by Classy API limitations with Studio campaigns before being made obsolete by the pivot.

---

## Code to Remove

The following code in the plugin implements the archived functionality and should be removed in Phase 5 (Code Cleanup):

### In `class-api-client.php`:
- `duplicate_campaign()` method
- `publish_campaign()` method
- `unpublish_campaign()` method
- `deactivate_campaign()` method
- `reactivate_campaign()` method
- `update_campaign()` method (MAY be repurposed for master campaign linking)

### In `class-sync-handler.php`:
- Campaign creation logic in `handle_fund_publish()`
- Campaign status management in `handle_post_status_transition()`
- Campaign-related hooks for draft/trash/restore

### Post meta keys to evaluate:
- `_gofundme_campaign_id` - No longer needed per-fund
- `_gofundme_campaign_url` - No longer needed per-fund
- `_gofundme_campaign_status` - May need modification

---

## What We're Keeping

### Designation Sync (Pre-Phase 1 work)
All designation sync functionality remains critical:
- Designation create on publish
- Designation update on save
- Designation deactivate on trash/unpublish
- Designation reactivate on untrash
- Designation delete on permanent delete

### Phase 1: Configuration
- Template Campaign ID setting → Rename to "Master Campaign ID"
- Fundraising goal field → Keep for display purposes

### Phase 4: Inbound Sync
- Donation total polling → May need modification for designation-level data
- Sync infrastructure → Keep

---

## Reference Documents

- `.planning/ARCHITECTURE-PIVOT-2026-01-28.md` - Full pivot analysis
- Email thread with Luke Dringoli (2026-01-28) - Classy's recommendation

---

*This archive preserves the context of work that was completed but superseded by architectural changes. The code worked correctly; it's simply no longer the right approach.*
