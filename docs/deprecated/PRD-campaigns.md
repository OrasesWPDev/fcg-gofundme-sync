# PRD: Campaign Sync Feature

**Status:** APPROVED
**Approach:** Hybrid (Add campaigns to existing designations plugin)
**Target Version:** 2.0.0
**Created:** 2026-01-14

---

## Executive Summary

Add bidirectional Campaign sync to the existing FCG GoFundMe Sync plugin. WordPress funds will sync to both **Designations** (fund allocation) AND **Campaigns** (donation pages).

**Result:** Each WordPress fund can have:
- A GoFundMe Pro campaign (donation page with URL)
- A GoFundMe Pro designation (fund allocation category)

---

## API Research Summary

### Campaign API Endpoints

| Operation | Endpoint |
|-----------|----------|
| Create | `POST /organizations/{org_id}/campaigns` |
| Get | `GET /campaigns/{id}` |
| Update | `PUT /campaigns/{id}` |
| List All | `GET /organizations/{org_id}/campaigns` |
| Deactivate | `POST /campaigns/{id}/deactivate` |
| Duplicate | `POST /campaigns/{id}/duplicate` |

### Campaign Required Fields (Estimated)

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Campaign name |
| `type` | string | "crowdfunding", "donation", etc. |
| `goal` | number | Fundraising goal |
| `started_at` | datetime | Start date |
| `timezone_identifier` | string | e.g., "America/New_York" |

### Field Mapping: WordPress Fund → Campaign

| WordPress | GoFundMe Pro Campaign |
|-----------|----------------------|
| `post_title` | `name` |
| `post_content` | `overview` or `description` |
| `fund_goal` (ACF) | `goal` |
| Post ID | `external_reference_id` |
| `post_status` | `status` (active/inactive) |

---

## Reusable Components from Phases 1-6

| Component | Reusable? | Notes |
|-----------|-----------|-------|
| OAuth2 auth | Yes | Same token works for campaigns |
| `request()` method | Yes | Generic API wrapper |
| Sync loop prevention | Yes | Same transient pattern |
| Error tracking meta | Yes | Same pattern, different meta keys |
| Admin UI patterns | Yes | Add campaign column/status |
| WP-CLI structure | Yes | Add campaign commands |
| Orchestrator/agents | Yes | Same workflow |

---

## Implementation Phases

**Order:** Fix designations first (C0), then add campaigns (C1-C5)

---

### Phase C0: Fix Existing Designation Sync (FIRST)

**Goal:** Debug and fix why designations aren't syncing

**Files:**
- `includes/class-sync-handler.php`
- `includes/class-api-client.php`

**Tasks:**
1. Test API credentials with manual designation creation
2. Add debug logging to `sync_to_gofundme()` method
3. Verify `save_post_funds` hook is firing
4. Check if `create_designation()` is being called
5. Fix any issues found
6. Verify designations appear in GoFundMe Pro Settings

**Version:** 1.6.0

**Success Criteria:**
- Publishing a fund creates a designation in GoFundMe Pro
- Designation visible in Settings → Program Designations

---

### Phase C1: Campaign API Integration

**Goal:** Add campaign CRUD methods to API client + research campaign types

**Files:**
- `includes/class-api-client.php`

**Tasks:**
1. Add `create_campaign(array $data): array`
2. Add `update_campaign($id, array $data): array`
3. Add `get_campaign($id): array`
4. Add `get_all_campaigns(): array` (with pagination)
5. Add `deactivate_campaign($id): array`

**New Post Meta:**
- `_gofundme_campaign_id` - Campaign ID
- `_gofundme_campaign_url` - Campaign donation page URL

**Version:** 2.0.0

---

### Phase C2: Push Sync (WordPress → GoFundMe Pro Campaigns)

**Goal:** Create/update campaigns when funds are published

**Files:**
- `includes/class-sync-handler.php`

**Tasks:**
1. Add `sync_campaign()` method
2. Hook into existing `save_post_funds` action
3. Map WordPress fields to campaign fields
4. Store campaign ID and URL in post meta
5. Handle unpublish → deactivate campaign
6. Handle trash → deactivate campaign
7. Handle delete → deactivate (preserve data)

**Sync Behavior:**

| WordPress Action | Campaign Action |
|------------------|-----------------|
| Publish fund | Create campaign |
| Update fund | Update campaign |
| Unpublish | Deactivate campaign |
| Trash | Deactivate campaign |
| Restore | Reactivate campaign |
| Delete | **Deactivate** (preserve donation history) |

**Version:** 2.1.0

---

### Phase C3: Pull Sync (GoFundMe Pro → WordPress)

**Goal:** Sync campaign changes back to WordPress

**Files:**
- `includes/class-sync-poller.php`

**Tasks:**
1. Add `poll_campaigns()` method
2. Match campaigns to funds via `external_reference_id`
3. Detect changes (name, goal, status)
4. Apply changes to WordPress
5. Handle conflict (WordPress wins, same as designations)
6. Add to existing cron job or separate schedule

**Version:** 2.2.0

---

### Phase C4: Admin UI for Campaigns

**Goal:** Visibility into campaign sync status

**Files:**
- `includes/class-admin-ui.php`
- `assets/css/admin.css`

**Tasks:**
1. Add "Campaign" column to funds list (with link to GFM page)
2. Add campaign info to sync meta box
3. Add "Create Campaign" / "View Campaign" buttons
4. Add campaign sync status to settings page
5. Display campaign URL on fund edit screen

**Version:** 2.3.0

---

### Phase C5: WP-CLI Commands

**Goal:** Command-line tools for campaign management

**Files:**
- `includes/class-sync-poller.php`

**New Commands:**
```
wp fcg-sync campaigns          # List all funds with campaign status
wp fcg-sync campaign-create    # Create campaigns for all funds
wp fcg-sync campaign-pull      # Pull campaign updates from GFM
```

**Version:** 2.4.0

---

## Orchestrator Execution Pattern

Each phase follows this workflow:

```
1. Main Agent spawns Orchestrator (background)
2. Orchestrator reads phase plan
3. Orchestrator spawns Dev Agents (parallel where possible)
4. Dev Agents implement code changes
5. Orchestrator spawns Testing Agent for review
6. Orchestrator commits, deploys to staging
7. Orchestrator runs verification tests
8. Orchestrator reports back to Main Agent
9. Main Agent waits for user approval
10. Main Agent pushes to GitHub
```

---

## File Structure After Campaigns

```
fcg-gofundme-sync/
├── fcg-gofundme-sync.php           # Main plugin file
├── includes/
│   ├── class-api-client.php        # + campaign methods
│   ├── class-sync-handler.php      # + campaign sync
│   ├── class-sync-poller.php       # + campaign polling
│   └── class-admin-ui.php          # + campaign UI
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── docs/
    ├── PRD.md                       # Original (designations)
    ├── PRD-campaigns.md             # Campaign sync PRD
    ├── phase-C0-implementation-plan.md
    ├── phase-C1-implementation-plan.md
    ├── phase-C2-implementation-plan.md
    ├── phase-C3-implementation-plan.md
    ├── phase-C4-implementation-plan.md
    └── phase-C5-implementation-plan.md
```

---

## Success Criteria

After all phases complete:

1. WordPress funds create campaigns in GoFundMe Pro
2. Campaign URLs accessible and functional
3. Campaign changes sync back to WordPress
4. Admin UI shows campaign status and links
5. WP-CLI commands work for campaign management
6. Designation sync also works (fixed)
7. Both campaign and designation IDs stored per fund

---

## Verification Tests

| Test | Command/Action |
|------|----------------|
| Create campaign | Publish fund → campaign appears in GFM |
| Campaign URL works | Visit campaign URL → donation page loads |
| Update syncs | Change fund title → campaign name updates |
| Pull sync works | Change campaign in GFM → WordPress updates |
| Status column shows | View funds list → campaign column visible |
| CLI works | `wp fcg-sync campaigns` → shows campaign status |

---

## Decisions Made

| Question | Decision |
|----------|----------|
| Campaign type | Research in Phase C1 |
| Deletion behavior | **Deactivate** (preserve data) |
| Phase order | Fix designations first (C0) |

---

## Estimated Effort

| Phase | Effort | Version |
|-------|--------|---------|
| **C0: Fix Designations** | Small-Medium | 1.6.0 |
| C1: Campaign API + Research | Small | 2.0.0 |
| C2: Push Sync | Medium | 2.1.0 |
| C3: Pull Sync | Medium | 2.2.0 |
| C4: Admin UI | Medium | 2.3.0 |
| C5: WP-CLI | Small | 2.4.0 |

**Total:** 6 phases, executed via orchestrator one at a time
