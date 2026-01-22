# Architecture Research: Campaign Integration

**Researched:** 2026-01-22
**Confidence:** HIGH (based on existing codebase analysis and Classy API documentation)

## Executive Summary

Campaign sync integrates into the existing designation sync architecture as a **parallel operation** — both designation and campaign operations happen side-by-side during the same WordPress lifecycle events. The existing class structure supports this pattern with minimal changes: the API client already has campaign methods, the sync handler already has campaign helper methods, and the poller's structure can be extended to handle campaign data alongside designation data.

**Key architectural insight:** Campaigns are **linked entities**, not separate entities. A campaign MUST have a designation. WordPress post → designation → campaign is the ownership chain. This means campaign operations always follow designation operations.

## Integration Points

### 1. FCG_GFM_API_Client (HTTP Layer)

**Current state:** Already complete. Class has all needed campaign methods.

**Existing methods:**
- `create_campaign(array $data)` - POST /organizations/{org_id}/campaigns
- `update_campaign($campaign_id, array $data)` - PUT /campaigns/{campaign_id}
- `get_campaign($campaign_id)` - GET /campaigns/{campaign_id}
- `get_all_campaigns(int $per_page = 100)` - GET /organizations/{org_id}/campaigns (with pagination)
- `deactivate_campaign($campaign_id)` - POST /campaigns/{campaign_id}/deactivate

**Missing methods needed:**
- `duplicate_campaign($source_campaign_id, array $overrides)` - For template-based campaign creation
- `publish_campaign($campaign_id)` - To publish duplicated campaigns
- `reactivate_campaign($campaign_id)` - For untrash operation (if supported by API)

**Note:** The current `create_campaign()` method uses POST to `/organizations/{org_id}/campaigns`. Per PROJECT.md, this is NOT a public endpoint. We need to use the duplication approach instead.

**Integration boundary:**
- API Client remains a pure HTTP wrapper
- No business logic in this layer
- Returns standardized `['success' => bool, 'data' => mixed, 'error' => string]` format

### 2. FCG_GFM_Sync_Handler (Outbound Sync Layer)

**Current state:** Has campaign helper methods but incomplete integration into WordPress hooks.

**Existing helper methods:**
- `build_campaign_data(WP_Post $post)` - Builds campaign data from post
- `get_fund_goal(int $post_id)` - Extracts goal from ACF or post meta
- `create_campaign_in_gfm(int $post_id, array $data)` - Creates and stores campaign ID
- `update_campaign_in_gfm(int $post_id, $campaign_id, array $data)` - Updates campaign
- `sync_campaign_to_gofundme(int $post_id, WP_Post $post)` - Main orchestrator
- `get_campaign_id(int $post_id)` - Retrieves campaign ID from post meta
- `get_campaign_url(int $post_id)` - Retrieves campaign URL from post meta

**Current hook integration:**
- ✓ `on_save_fund()` - Already calls `sync_campaign_to_gofundme()` at line 134
- ✓ `on_trash_fund()` - Already deactivates campaign at lines 165-171
- ✓ `on_untrash_fund()` - Already updates/reactivates campaign at lines 202-209
- ✓ `on_delete_fund()` - Already deactivates campaign at lines 237-244
- ✓ `on_status_change()` - Already handles campaign activate/deactivate at lines 284-297

**What needs modification:**
- `create_campaign_in_gfm()` must be updated to use duplication endpoint instead of direct creation
- Add template campaign ID retrieval (from plugin settings)
- Add publish step after duplication

**Data flow pattern:**
```
WordPress Event → Designation Operation → Campaign Operation
     ↓                      ↓                       ↓
save_post_funds    create/update_designation  create/update_campaign
wp_trash_post      deactivate_designation     deactivate_campaign
untrash_post       activate_designation       reactivate_campaign
before_delete_post delete_designation         deactivate_campaign (preserve donations)
```

**Integration boundary:**
- Sync Handler orchestrates both designation and campaign operations
- Campaign operations ALWAYS follow designation operations (never before)
- Campaign operations share same error handling as designations
- Campaign sync uses same recursion prevention flag (`fcg_gfm_syncing_inbound`)

### 3. FCG_GFM_Sync_Poller (Inbound Sync Layer)

**Current state:** Only polls designations. Needs extension for campaign polling.

**Existing pattern (for designations):**
```php
poll() {
    get_all_designations() from API
    foreach designation:
        find_post_for_designation()
        has_designation_changed()
        should_apply_gfm_changes()
        apply_designation_to_post()
}
```

**Extension needed (for campaigns):**
```php
poll() {
    // Existing designation polling (unchanged)
    get_all_designations()
    foreach designation: ...

    // NEW: Campaign polling
    get_all_campaigns()
    foreach campaign:
        find_post_for_campaign()
        has_campaign_changed()
        apply_campaign_data_to_post() // donation totals, goal progress
}
```

**New methods needed:**
- `find_post_for_campaign(array $campaign)` - Match campaign to post via external_reference_id or meta lookup
- `has_campaign_changed(int $post_id, array $campaign)` - Hash comparison for campaign data
- `calculate_campaign_hash(array $campaign)` - MD5 hash of relevant campaign fields
- `apply_campaign_data_to_post(int $post_id, array $campaign)` - Update post meta with donation totals, progress

**Fields to sync inbound from campaigns:**
- `total_gross_amount` → `_gofundme_total_raised` post meta
- `goal` → `_fundraising_goal` post meta (if changed in Classy)
- `status` → tracking meta (for admin display)
- `canonical_url` → `_gofundme_campaign_url` post meta (if not set)

**Integration boundary:**
- Designation polling and campaign polling are separate loops (not nested)
- Campaign polling happens AFTER designation polling completes
- Campaign data updates post meta only, does NOT update post title/content (designation handles that)
- Campaign polling uses same conflict resolution as designations (WordPress wins)

### 4. FCG_GFM_Admin_UI (Admin Interface Layer)

**Current state:** Shows designation sync status only.

**Extension needed:**
- Display campaign URL in meta box (if campaign exists)
- Display donation total from `_gofundme_total_raised` meta
- Display goal progress percentage
- Add "Create Campaign" button for posts with designation but no campaign
- Update sync status column to show both designation and campaign status

**Integration boundary:**
- Admin UI reads post meta, does NOT call API directly
- Manual sync button triggers same handlers as automated sync
- AJAX actions remain in Admin UI layer

## Data Flow

### Outbound Flow: WordPress → Classy

**Scenario: User publishes new fund**

```
1. User clicks "Publish" on fund post
   ↓
2. save_post_funds hook fires
   ↓
3. FCG_GFM_Sync_Handler::on_save_fund()
   ├─ Check: Is inbound sync running? (skip if yes)
   ├─ Check: Is autosave/revision? (skip if yes)
   ├─ Check: Post status = 'auto-draft'? (skip if yes)
   ↓
4. Build designation data from post
   ↓
5. Check: Does post have _gofundme_designation_id?
   ├─ YES → update_designation()
   └─ NO + published → create_designation()
   ↓
6. Store designation ID in post meta: _gofundme_designation_id
   ↓
7. FCG_GFM_Sync_Handler::sync_campaign_to_gofundme()
   ├─ Build campaign data from post
   ├─ Check: Does post have _gofundme_campaign_id?
   ├─ YES → update_campaign_in_gfm()
   └─ NO + published → create_campaign_in_gfm()
   ↓
8. create_campaign_in_gfm() flow:
   ├─ Get template_campaign_id from plugin settings
   ├─ Call duplicate_campaign(template_id, overrides)
   ├─ Call publish_campaign(new_campaign_id)
   ├─ Store campaign ID: _gofundme_campaign_id
   ├─ Store campaign URL: _gofundme_campaign_url
   └─ Update last sync: _gofundme_last_sync
```

**Scenario: User updates published fund**

```
1. User clicks "Update" on fund post
   ↓
2. save_post_funds hook fires
   ↓
3. FCG_GFM_Sync_Handler::on_save_fund()
   ↓
4. Build designation data from post
   ↓
5. Check: Post has _gofundme_designation_id? YES
   ↓
6. update_designation(designation_id, data)
   ↓
7. FCG_GFM_Sync_Handler::sync_campaign_to_gofundme()
   ↓
8. Check: Post has _gofundme_campaign_id? YES
   ↓
9. update_campaign_in_gfm(post_id, campaign_id, data)
   ↓
10. Update last sync: _gofundme_last_sync
```

**Scenario: User trashes fund**

```
1. User clicks "Move to Trash"
   ↓
2. wp_trash_post hook fires
   ↓
3. FCG_GFM_Sync_Handler::on_trash_fund()
   ↓
4. update_designation(designation_id, {is_active: false})
   ↓
5. deactivate_campaign(campaign_id)
```

### Inbound Flow: Classy → WordPress

**Scenario: WP-Cron polling (every 15 minutes)**

```
1. WP-Cron fires: fcg_gofundme_sync_poll
   ↓
2. FCG_GFM_Sync_Poller::poll()
   ↓
3. DESIGNATION POLLING (existing):
   ├─ get_all_designations() from API
   ├─ foreach designation:
   │   ├─ find_post_for_designation()
   │   ├─ has_designation_changed() (hash comparison)
   │   ├─ should_apply_gfm_changes() (conflict detection)
   │   └─ apply_designation_to_post() (update title, status, excerpt)
   ↓
4. CAMPAIGN POLLING (new):
   ├─ get_all_campaigns() from API
   ├─ foreach campaign:
   │   ├─ find_post_for_campaign() (via external_reference_id or meta)
   │   ├─ Skip if no matching post
   │   ├─ has_campaign_changed() (hash comparison)
   │   ├─ Skip if unchanged
   │   └─ apply_campaign_data_to_post():
   │       ├─ Update _gofundme_total_raised meta
   │       ├─ Update _fundraising_goal meta (if goal changed in Classy)
   │       ├─ Update _gofundme_campaign_status meta
   │       └─ Update _gofundme_campaign_poll_hash meta
   ↓
5. set_last_poll_time()
```

**Key differences from designation polling:**
- Designation polling can UPDATE WordPress post (title, content, status)
- Campaign polling ONLY updates post meta (donation data, not content)
- Designation uses conflict resolution (WordPress wins on title changes)
- Campaign has no conflicts (only pulls donation totals, never pushed from WP)

## Post Meta Schema

### Current Designation Meta (existing)
- `_gofundme_designation_id` - Classy designation ID (string)
- `_gofundme_last_sync` - ISO datetime of last sync (string)
- `_gofundme_poll_hash` - MD5 hash of designation data (string)
- `_gofundme_sync_source` - "wordpress" or "gofundme" (string)
- `_gofundme_sync_error` - Error message if sync failed (string)
- `_gofundme_sync_attempts` - Failed sync attempt count (int)
- `_gofundme_sync_last_attempt` - Timestamp of last failed sync (string)

### Campaign Meta (existing but incomplete)
- `_gofundme_campaign_id` - Classy campaign ID (string) - EXISTS
- `_gofundme_campaign_url` - Public campaign URL (string) - EXISTS

### Campaign Meta (new)
- `_gofundme_campaign_poll_hash` - MD5 hash of campaign data for change detection (string)
- `_gofundme_total_raised` - Total donations received (float)
- `_fundraising_goal` - Fundraising goal (float) - May already exist from ACF
- `_gofundme_campaign_status` - Campaign status in Classy (string: "active", "deactivated", "unpublished")

### Plugin Options (new)
- `fcg_gfm_template_campaign_id` - Template campaign ID for duplication (string)

## Implementation Order

**Recommended sequence based on dependencies:**

### Phase 1: Campaign Push Sync (Outbound)
**Goal:** WordPress fund operations create/update/deactivate campaigns in Classy

1. **Add API methods** (FCG_GFM_API_Client):
   - `duplicate_campaign($source_id, $overrides)` - POST /campaigns/{id}/duplicate
   - `publish_campaign($campaign_id)` - POST /campaigns/{id}/publish
   - Test with manual WP-CLI script before integration

2. **Add plugin setting** (new settings class or existing admin):
   - Template campaign ID field in admin settings
   - Validation: Check if template campaign exists via API
   - Store in option: `fcg_gfm_template_campaign_id`

3. **Modify sync handler** (FCG_GFM_Sync_Handler):
   - Update `create_campaign_in_gfm()` to use duplication instead of direct creation
   - Add publish step after duplication
   - Add error handling for missing template ID
   - Test: Publish new fund → verify campaign created and published in Classy

4. **Bulk migration tool** (WP-CLI command):
   - New command: `wp fcg-sync create-campaigns`
   - Find all funds with designation but no campaign
   - For each: duplicate template, publish, link to post
   - Dry-run mode, progress bar, error reporting
   - Test on staging with ~758 existing funds

**Validation:** User publishes fund → designation created → campaign created via duplication → campaign published → IDs stored in post meta

### Phase 2: Campaign Pull Sync (Inbound)
**Goal:** Classy donation totals sync to WordPress post meta

1. **Add poll methods** (FCG_GFM_Sync_Poller):
   - `find_post_for_campaign(array $campaign)` - Match campaign to post
   - `has_campaign_changed(int $post_id, array $campaign)` - Hash comparison
   - `calculate_campaign_hash(array $campaign)` - MD5 of relevant fields
   - `apply_campaign_data_to_post(int $post_id, array $campaign)` - Update meta

2. **Extend poll() method**:
   - Add campaign polling loop after designation polling
   - Store donation totals in `_gofundme_total_raised` meta
   - Store campaign status in `_gofundme_campaign_status` meta
   - Update hash for change detection

3. **WP-CLI command for testing**:
   - `wp fcg-sync pull-campaigns` - Manual campaign data pull
   - Dry-run mode to preview changes
   - Test on staging before cron automation

**Validation:** Change donation total in Classy → wait 15 min or run manual pull → verify `_gofundme_total_raised` updated in post meta

### Phase 3: Admin UI Enhancement
**Goal:** Display campaign data and status in WordPress admin

1. **Extend meta box** (FCG_GFM_Admin_UI):
   - Display campaign URL (if exists)
   - Display donation total and goal progress
   - Display last sync time
   - Add "Create Campaign" button (if designation exists but no campaign)

2. **Extend list table column**:
   - Show campaign status alongside designation status
   - Visual indicator: both synced, designation only, neither

3. **AJAX handler for manual campaign creation**:
   - Button triggers campaign creation for single post
   - Useful for posts that failed bulk migration

**Validation:** Admin can see campaign data, create campaigns manually, view sync status

### Phase 4: ACF Field Integration (Optional)
**Goal:** Fundraising goal field in WordPress admin

1. **Create ACF field** (or programmatic field registration):
   - Field: `fundraising_goal` (number)
   - Location: Post type = funds
   - Default: 1000

2. **Update build_campaign_data()** (already implemented):
   - Read from ACF field (already done at line 372-376)
   - Fallback to post meta `_fundraising_goal`
   - Fallback to default 1000

**Validation:** Set goal in ACF → publish fund → verify goal sent to Classy campaign

## Component Boundaries

### Clear Separation of Concerns

**FCG_GFM_API_Client:**
- ONLY handles HTTP requests to Classy API
- NO business logic about when to call endpoints
- NO WordPress-specific code (posts, meta, hooks)
- Returns standardized response format

**FCG_GFM_Sync_Handler:**
- ONLY handles outbound sync (WordPress → Classy)
- Orchestrates designation + campaign operations
- Builds data from WordPress posts
- Stores API response data in post meta
- Hooked to WordPress post lifecycle events

**FCG_GFM_Sync_Poller:**
- ONLY handles inbound sync (Classy → WordPress)
- Fetches data from API
- Compares with WordPress state
- Applies changes to WordPress (posts and meta)
- Triggered by WP-Cron and WP-CLI

**FCG_GFM_Admin_UI:**
- ONLY handles admin interface display
- Reads post meta and displays status
- Provides manual sync buttons (AJAX to trigger handlers)
- NO direct API calls
- Admin-only code (not loaded on frontend)

### Data Ownership

**WordPress is source of truth for:**
- Fund title, content, excerpt
- Fundraising goal
- Publish status

**Classy is source of truth for:**
- Donation totals
- Campaign status (active/deactivated)
- Campaign URL

**Sync direction:**
- Outbound (WP → Classy): Content, goal, status
- Inbound (Classy → WP): Donation totals, campaign status

## Architecture Patterns

### 1. Parallel Operations Pattern

Designation and campaign operations happen in parallel, not nested:

```php
// GOOD: Parallel operations
function on_save_fund($post_id, $post, $update) {
    $designation_data = build_designation_data($post);
    sync_designation($post_id, $designation_data);

    $campaign_data = build_campaign_data($post);
    sync_campaign($post_id, $campaign_data);
}

// BAD: Nested/sequential with tight coupling
function on_save_fund($post_id, $post, $update) {
    $result = sync_designation($post_id);
    if ($result['success']) {
        sync_campaign($post_id, $result['designation_id']);
    }
}
```

**Rationale:** Campaigns and designations are separate entities in Classy. If campaign sync fails, designation should still succeed. Parallel operations allow independent error handling.

### 2. Hash-Based Change Detection

Use MD5 hash comparison to avoid unnecessary updates:

```php
// Calculate hash of relevant fields
function calculate_designation_hash($designation) {
    $hashable = [
        'name' => $designation['name'],
        'description' => $designation['description'],
        'is_active' => $designation['is_active'],
        'goal' => $designation['goal'],
    ];
    return md5(json_encode($hashable));
}

// Compare before applying changes
if (get_post_meta($post_id, '_gofundme_poll_hash', true) !== calculate_designation_hash($designation)) {
    apply_changes($post_id, $designation);
}
```

**Rationale:** Reduces unnecessary wp_update_post() calls, prevents post modified timestamp churn, improves performance.

### 3. Recursion Prevention with Transient Flag

Prevent inbound sync from triggering outbound sync:

```php
// Inbound sync sets flag
function apply_designation_to_post($post_id, $designation) {
    set_transient('fcg_gfm_syncing_inbound', true, 30); // 30 sec TTL
    wp_update_post(['ID' => $post_id, 'post_title' => $designation['name']]);
    delete_transient('fcg_gfm_syncing_inbound');
}

// Outbound sync checks flag
function on_save_fund($post_id) {
    if (get_transient('fcg_gfm_syncing_inbound')) {
        return; // Skip outbound sync
    }
    // ... proceed with sync
}
```

**Rationale:** Without this flag, inbound sync would trigger outbound sync, creating an infinite loop.

### 4. Conflict Resolution: WordPress Wins

When both WordPress and Classy have changes, WordPress version is pushed:

```php
function should_apply_gfm_changes($post_id, $designation) {
    $last_sync = get_post_meta($post_id, '_gofundme_last_sync', true);
    $post_modified = get_post($post_id)->post_modified_gmt;

    if (strtotime($post_modified) > strtotime($last_sync)) {
        // Conflict: WordPress was modified after last sync
        push_wp_version_to_gfm($post_id, $designation);
        return false; // Don't apply GFM changes
    }

    return true; // Safe to apply GFM changes
}
```

**Rationale:** WordPress is the content management system. Editors work in WordPress. If there's a conflict, the version the editor saved in WordPress takes precedence.

## Potential Issues and Mitigations

### Issue 1: Campaign Creation Requires Template

**Problem:** Cannot create campaigns directly. Must duplicate from template.

**Mitigation:**
- Add admin setting for template campaign ID
- Validate template exists before attempting sync
- Show admin notice if template not configured
- Document template creation process

### Issue 2: 758 Existing Funds Need Campaigns

**Problem:** Bulk migration could hit API rate limits or timeout.

**Mitigation:**
- WP-CLI command with batch processing
- Dry-run mode to preview
- Progress bar and logging
- Option to limit batch size (`--limit=100`)
- Retry mechanism for failures
- Run during off-peak hours

### Issue 3: Campaign and Designation State Mismatch

**Problem:** Designation is active but campaign is deactivated (or vice versa).

**Mitigation:**
- Always sync both entities together
- Add admin UI warning if states don't match
- WP-CLI `status` command shows both states
- Manual sync button to force re-sync

### Issue 4: Unknown Campaign Update Limitations

**Problem:** PROJECT.md notes "Awaiting confirmation from Classy contact on what fields can be updated post-duplication"

**Mitigation:**
- Start with minimal update fields (name, goal, overview)
- Test on staging with sandbox API
- Add logging for API errors
- If field updates fail, document in code comments
- May need to fall back to deactivate + recreate for major changes

## API Endpoint Reference

Based on existing code and Classy API documentation:

### Campaigns

| Endpoint | Method | Purpose | Status |
|----------|--------|---------|--------|
| `/organizations/{org_id}/campaigns` | POST | Create campaign (NOT PUBLIC) | NOT USED |
| `/campaigns/{id}/duplicate` | POST | Duplicate campaign from template | NEEDED |
| `/campaigns/{id}/publish` | POST | Publish duplicated campaign | NEEDED |
| `/campaigns/{id}` | PUT | Update campaign | EXISTS |
| `/campaigns/{id}` | GET | Get campaign details | EXISTS |
| `/campaigns/{id}/deactivate` | POST | Deactivate campaign | EXISTS |
| `/organizations/{org_id}/campaigns` | GET | List all campaigns (paginated) | EXISTS |

### Designations (existing)

| Endpoint | Method | Purpose | Status |
|----------|--------|---------|--------|
| `/organizations/{org_id}/designations` | POST | Create designation | EXISTS |
| `/designations/{id}` | PUT | Update designation | EXISTS |
| `/designations/{id}` | DELETE | Delete designation | EXISTS |
| `/designations/{id}` | GET | Get designation | EXISTS |
| `/organizations/{org_id}/designations` | GET | List all designations (paginated) | EXISTS |

## Sources

Research based on:
- Existing codebase analysis (includes/class-api-client.php, includes/class-sync-handler.php, includes/class-sync-poller.php)
- .planning/codebase/ARCHITECTURE.md
- .planning/PROJECT.md
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)
- [GoFundMe Pro API Documentation](https://docs.classy.org/)
- WebSearch: Classy API campaign duplication, designations relationship (2026-01-22)

---

*Architecture research complete. Ready for roadmap creation.*
