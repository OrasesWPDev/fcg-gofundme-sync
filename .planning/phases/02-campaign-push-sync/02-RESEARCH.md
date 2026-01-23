# Phase 2: Campaign Push Sync - Research

**Researched:** 2026-01-23
**Domain:** Campaign duplication workflow and WordPress-to-Classy push synchronization
**Confidence:** MEDIUM

## Summary

This phase extends the existing designation sync infrastructure to support campaign creation and updates via Classy's template duplication workflow. The codebase already has partial campaign sync infrastructure (helper methods exist in `class-sync-handler.php`), but the current `create_campaign()` implementation uses POST /campaigns which returns 403. Phase 2 replaces this with the correct duplication-then-publish workflow.

**Key findings:**
- Campaign creation MUST use `POST /campaigns/{template_id}/actions/duplicate` (POST /campaigns returns 403)
- Newly duplicated campaigns start in "unpublished" status - must call `POST /campaigns/{id}/publish` to activate
- Campaign sync hooks already exist - they call `sync_campaign_to_gofundme()` which needs updating
- Template campaign ID exists in plugin settings (added in Phase 1)
- Meta keys `_gofundme_campaign_id` and `_gofundme_campaign_url` are defined but not yet storing data
- ACF field "Disable Campaign Sync" is mentioned in context but not yet implemented

**Primary recommendation:** Modify existing `create_campaign_in_gfm()` to use duplicate→update→publish workflow, add two new API methods (`duplicate_campaign()` and `publish_campaign()`), and implement the ACF checkbox for explicit opt-out.

## Standard Stack

### Core (Already in Codebase)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress HTTP API | WP 5.8+ | API requests | `wp_remote_request()` used throughout existing code |
| WordPress Post Meta API | WP 5.8+ | Store campaign IDs/URLs | Matches existing designation pattern (`_gofundme_*` keys) |
| Classy API 2.0 | 2.0 | Campaign duplication/publishing | Only public method for campaign creation |
| OAuth2 client_credentials | Existing | API authentication | Already implemented with token caching |

### Supporting (Already in Codebase)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ACF (Advanced Custom Fields) | 5.x+ | "Disable Campaign Sync" checkbox | Optional - graceful fallback if ACF not available |
| WordPress Transients API | WP 5.8+ | OAuth token caching | Already implemented in `FCG_GFM_API_Client` |
| WordPress Error Logging | WP 5.8+ | Sync operation logging | Existing pattern: `error_log('[FCG GoFundMe Sync] ...')` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Duplication workflow | POST /campaigns direct creation | Not possible - endpoint returns 403 Forbidden |
| ACF checkbox | Custom post meta field | ACF provides UI for free; custom field requires admin UI code |
| Post meta storage | CPT relationship | Post meta simpler, matches existing designation pattern |

**Installation:**
No new dependencies required - all functionality exists in WordPress core and current codebase. ACF is already in use for other plugin features.

## Architecture Patterns

### Existing Campaign Sync Structure
```
class-sync-handler.php (FCG_GFM_Sync_Handler)
├── on_save_fund()           # Line 86: Hooks save_post_funds
│   └── sync_campaign_to_gofundme()  # Line 134: Already called!
├── on_trash_fund()          # Line 142: Already deactivates campaign (line 165-171)
├── on_untrash_fund()        # Line 178: Already updates campaign (line 202-209)
├── on_delete_fund()         # Line 216: Already deactivates campaign (line 237-244)
└── on_status_change()       # Line 253: Already handles campaign status (line 284-297)

Helper methods (lines 335-468):
├── build_campaign_data()        # Line 342: Builds API payload from post
├── get_fund_goal()              # Line 370: Gets goal from ACF/meta
├── create_campaign_in_gfm()     # Line 392: NEEDS UPDATE - uses wrong endpoint
├── update_campaign_in_gfm()     # Line 417: Already correct
├── sync_campaign_to_gofundme()  # Line 435: Orchestrator (calls create or update)
├── get_campaign_id()            # Line 454: Gets from post meta
└── get_campaign_url()           # Line 465: Gets from post meta
```

**Key observation:** Infrastructure is 80% complete. Only need to:
1. Fix `create_campaign_in_gfm()` to use duplication
2. Add `duplicate_campaign()` and `publish_campaign()` API methods
3. Add "Disable Campaign Sync" field check

### Pattern 1: Campaign Duplication Workflow
**What:** Create new campaign by duplicating template, updating fields, then publishing
**When to use:** Fund published without existing campaign ID
**Example:**
```php
// Source: .planning/research/STACK.md lines 15-59 + existing sync pattern
private function create_campaign_in_gfm(int $post_id, array $data): void {
    // Get template campaign ID from plugin settings
    $template_id = get_option('fcg_gfm_template_campaign_id', 0);

    if (!$template_id) {
        $this->log_error("Cannot create campaign for post {$post_id}: No template campaign configured");
        return;
    }

    // Step 1: Duplicate template with overrides
    $overrides = [
        'name' => $data['name'],
        'raw_goal' => (string) $data['goal'],
        'raw_currency_code' => 'USD',
        'started_at' => $data['started_at'],
        'external_reference_id' => $data['external_reference_id'],
    ];

    $duplicate_result = $this->api->duplicate_campaign($template_id, $overrides);

    if (!$duplicate_result['success']) {
        $this->log_error("Failed to duplicate campaign for post {$post_id}: {$duplicate_result['error']}");
        return;
    }

    $campaign_id = $duplicate_result['data']['id'];
    $campaign_url = $duplicate_result['data']['canonical_url'] ?? '';

    // Step 2: Update additional fields not available in duplicate overrides
    if (!empty($data['overview'])) {
        $update_result = $this->api->update_campaign($campaign_id, [
            'overview' => $data['overview'],
        ]);

        if (!$update_result['success']) {
            $this->log_error("Failed to update campaign {$campaign_id} overview: {$update_result['error']}");
            // Non-fatal - campaign still usable
        }
    }

    // Step 3: Publish campaign (starts in unpublished status)
    $publish_result = $this->api->publish_campaign($campaign_id);

    if (!$publish_result['success']) {
        $this->log_error("Failed to publish campaign {$campaign_id}: {$publish_result['error']}");
        // Store anyway - can be published manually
    }

    // Step 4: Store IDs in post meta
    update_post_meta($post_id, self::META_CAMPAIGN_ID, $campaign_id);
    update_post_meta($post_id, self::META_CAMPAIGN_URL, $campaign_url);
    update_post_meta($post_id, self::META_KEY_LAST_SYNC, current_time('mysql'));

    $this->log_info("Created campaign {$campaign_id} for post {$post_id}");
}
```

### Pattern 2: API Client Methods for Duplication
**What:** Add duplicate_campaign() and publish_campaign() to FCG_GFM_API_Client
**When to use:** Campaign creation flow
**Example:**
```php
// Source: .planning/research/STACK.md lines 15-96 + existing API client pattern
// Add to class-api-client.php

/**
 * Duplicate a campaign from a template
 *
 * @param int|string $source_campaign_id Template campaign ID
 * @param array $overrides Fields to override in duplicated campaign
 * @return array Response
 */
public function duplicate_campaign($source_campaign_id, array $overrides = []): array {
    $payload = [
        'overrides' => $overrides,
        'duplicates' => [], // Don't duplicate related objects (tickets, ecards)
    ];

    return $this->request('POST', "/campaigns/{$source_campaign_id}/actions/duplicate", $payload);
}

/**
 * Publish a campaign (make it active)
 *
 * @param int|string $campaign_id Campaign ID
 * @return array Response
 */
public function publish_campaign($campaign_id): array {
    return $this->request('POST', "/campaigns/{$campaign_id}/actions/publish", []);
}

/**
 * Unpublish a campaign (make it unpublished but not deactivated)
 *
 * @param int|string $campaign_id Campaign ID
 * @return array Response
 */
public function unpublish_campaign($campaign_id): array {
    return $this->request('POST', "/campaigns/{$campaign_id}/actions/unpublish", []);
}

/**
 * Reactivate a deactivated campaign (returns to unpublished status)
 *
 * @param int|string $campaign_id Campaign ID
 * @return array Response
 */
public function reactivate_campaign($campaign_id): array {
    return $this->request('POST', "/campaigns/{$campaign_id}/actions/reactivate", []);
}
```

### Pattern 3: Disable Campaign Sync Check
**What:** Allow explicit opt-out via ACF checkbox
**When to use:** Before any campaign sync operation
**Example:**
```php
// Source: Phase 2 context + existing ACF pattern in build_designation_data()
private function should_sync_campaign(int $post_id): bool {
    // Check if ACF is available
    if (!function_exists('get_field')) {
        return true; // Default to sync if ACF not available
    }

    $gfm_settings = get_field('gofundme_settings', $post_id);

    // Check for explicit disable
    if (!empty($gfm_settings['disable_campaign_sync'])) {
        return false;
    }

    return true;
}

// Use in sync_campaign_to_gofundme()
private function sync_campaign_to_gofundme(int $post_id, WP_Post $post): void {
    // Check if campaign sync is disabled for this fund
    if (!$this->should_sync_campaign($post_id)) {
        return;
    }

    // Existing sync logic...
    $campaign_data = $this->build_campaign_data($post);
    // ...
}
```

### Pattern 4: Campaign Status Mapping
**What:** Map WordPress post status to Classy campaign actions
**When to use:** Status transitions and trash/untrash operations
**Reference:**
```
WordPress Status    →  Campaign Action      →  Classy Status
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
publish (new)       →  duplicate + publish  →  active
publish (existing)  →  update + publish     →  active
draft               →  unpublish            →  unpublished
trash               →  deactivate           →  deactivated
restore from trash  →  reactivate + publish →  active
permanent delete    →  deactivate           →  deactivated (preserve donations)
```

**Key rules:**
- New campaigns start "unpublished" after duplication - MUST publish
- Trash = deactivate (more permanent than unpublish)
- Restore = reactivate (returns to unpublished) + publish (make active)
- Never delete campaigns via API (preserves donation history)

### Anti-Patterns to Avoid
- **Using POST /campaigns for creation:** Returns 403 - must use duplication workflow
- **Forgetting to publish after duplication:** Campaign stays unpublished, not visible to donors
- **Deleting campaigns on permanent delete:** Destroys donation history - always deactivate instead
- **Syncing when explicitly disabled:** Check `disable_campaign_sync` field before any operation
- **Creating campaign without designation:** Campaigns are linked to designations - designation must exist first

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Campaign creation | Direct POST /campaigns | Duplicate-then-publish workflow | POST endpoint not public (returns 403) |
| Campaign deactivation | Custom status field | Existing `deactivate_campaign()` method | Already implemented (line 362 of class-api-client.php) |
| OAuth token management | Custom token refresh | Existing transient-based caching | Already handles expiration with 5-min buffer |
| Goal formatting | Custom number handling | Existing `get_fund_goal()` method | Already extracts from ACF or post meta (line 370) |
| Campaign data building | New builder function | Existing `build_campaign_data()` method | Already exists (line 342), just needs to be used |
| Meta key constants | Magic strings | Existing class constants | `META_CAMPAIGN_ID`, `META_CAMPAIGN_URL` already defined (lines 34, 37) |

**Key insight:** The infrastructure is 80% complete. This phase is more "connect the pieces" than "build new system." Don't rebuild what exists.

## Common Pitfalls

### Pitfall 1: POST /campaigns Returns 403
**What goes wrong:** Attempting to create campaigns via POST /organizations/{org_id}/campaigns fails with 403 Forbidden
**Why it happens:** This endpoint is not public in Classy API despite appearing in some documentation
**How to avoid:** ALWAYS use duplicate-then-publish workflow from template campaign
**Warning signs:** 403 errors in logs, campaigns not created despite valid credentials
**Confidence:** HIGH - Confirmed in project research (.planning/research/PITFALLS.md line 32)

### Pitfall 2: Forgetting to Publish After Duplication
**What goes wrong:** Campaigns created but not visible to donors
**Why it happens:** Duplicated campaigns start in "unpublished" status - publish step is required
**How to avoid:** Always call `publish_campaign()` after successful duplication
**Warning signs:** Campaign ID stored but campaign not appearing on public site
**Confidence:** MEDIUM - Documented in STACK.md, needs verification with sandbox

### Pitfall 3: Campaign URL Not Captured
**What goes wrong:** Campaign created but URL not stored, admin UI can't link to it
**Why it happens:** URL is in duplication response but not extracted to post meta
**How to avoid:** Extract `canonical_url` from duplicate response, store in `_gofundme_campaign_url` meta
**Warning signs:** Campaign meta box shows "No campaign URL" despite campaign ID existing
**Confidence:** HIGH - Meta key defined, pattern clear from designation sync

### Pitfall 4: Creating Campaign Without Designation
**What goes wrong:** Campaign orphaned in Classy without designation link
**Why it happens:** Campaigns are linked entities - they need a designation parent
**How to avoid:** Campaign sync happens AFTER designation sync in `on_save_fund()` (line 134 follows line 125-130)
**Warning signs:** Campaigns created but not associated with designations in Classy
**Confidence:** HIGH - Architecture documented in ARCHITECTURE.md line 70

### Pitfall 5: Race Condition on Rapid Saves
**What goes wrong:** Multiple campaigns created for single fund
**Why it happens:** No lock on campaign creation, concurrent requests trigger duplicate duplication calls
**How to avoid:** Check for existing campaign_id before duplication, use transient lock similar to `fcg_gfm_syncing_inbound`
**Warning signs:** Multiple campaign IDs in Classy with same external_reference_id
**Confidence:** MEDIUM - Identified in CONCERNS.md line 16, needs prevention logic
**Prevention code:**
```php
// Before duplicate_campaign() call
$lock_key = "fcg_gfm_creating_campaign_{$post_id}";
if (get_transient($lock_key)) {
    return; // Creation already in progress
}
set_transient($lock_key, true, 60); // 60-second lock

// After campaign created
delete_transient($lock_key);
```

### Pitfall 6: Sync Loop from Status Changes
**What goes wrong:** Campaign update triggers status change triggers sync triggers update (infinite loop)
**Why it happens:** Status transitions fire hooks that trigger sync
**How to avoid:** Use existing `FCG_GFM_Sync_Poller::is_syncing_inbound()` check (already in `on_save_fund()` line 88-90)
**Warning signs:** PHP max execution time errors, thousands of API calls in short time
**Confidence:** HIGH - Protection already exists for designation sync

## Code Examples

### Campaign Duplication Request/Response
```php
// Source: .planning/research/STACK.md lines 23-58
// Request
POST /campaigns/762968/actions/duplicate
{
  "overrides": {
    "name": "My Fund Campaign",
    "raw_goal": "5000.000",
    "raw_currency_code": "USD",
    "started_at": "2026-01-23T00:00:00Z",
    "external_reference_id": "12345"
  },
  "duplicates": []
}

// Response
{
  "id": 987654,
  "name": "My Fund Campaign",
  "status": "unpublished",
  "raw_goal": "5000.000",
  "canonical_url": "https://www.classy.org/campaign/my-fund-campaign/c987654"
}
```

### Existing Hook Integration Point
```php
// Source: class-sync-handler.php line 86-135
public function on_save_fund(int $post_id, WP_Post $post, bool $update): void {
    // Skip checks (autosave, revision, etc.) - lines 88-115

    // Build designation data - line 118
    $designation_data = $this->build_designation_data($post);

    // Get existing designation ID - line 121
    $designation_id = $this->get_designation_id($post_id);

    if ($designation_id) {
        // Update existing designation - line 125
        $this->update_designation($post_id, $designation_id, $designation_data);
    } else {
        // Create new designation if published - line 128
        if ($post->post_status === 'publish') {
            $this->create_designation($post_id, $designation_data);
        }
    }

    // Sync campaign (parallel to designation) - line 134
    $this->sync_campaign_to_gofundme($post_id, $post);
}
```

### Build Campaign Data (Existing)
```php
// Source: class-sync-handler.php lines 342-362
private function build_campaign_data(WP_Post $post): array {
    $data = [
        'name'                  => $this->truncate_string($post->post_title, 127),
        'type'                  => 'crowdfunding',
        'goal'                  => $this->get_fund_goal($post->ID),
        'started_at'            => $post->post_date,
        'timezone_identifier'   => 'America/New_York',
        'external_reference_id' => (string) $post->ID,
    ];

    if (!empty($post->post_content)) {
        $data['overview'] = $this->truncate_string(
            wp_strip_all_tags($post->post_content),
            2000
        );
    } elseif (!empty($post->post_excerpt)) {
        $data['overview'] = $post->post_excerpt;
    }

    return $data;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| POST /campaigns for creation | Duplicate-then-publish workflow | API v2 public endpoints | Required for all new integrations |
| Single publish status | Three-state lifecycle (unpublished/active/deactivated) | Classy platform evolution | More granular control, better UX |
| Manual campaign creation | Template-based duplication | Campaign templating feature | Consistent branding, faster setup |
| Delete campaigns | Deactivate campaigns | Financial compliance | Preserves donation history |

**Deprecated/outdated:**
- Direct campaign creation via POST /campaigns: Not supported for public API credentials
- Legacy campaigns (created_with = classyapp): Cannot be published via API
- Using `goal` field directly: Must use `raw_goal` with currency conversion

## Open Questions

1. **Template campaign type verification**
   - What we know: Context says template 762968 is "Embedded form type" (confirmed as correct)
   - What's unclear: Are there restrictions on what campaign types can be duplicated?
   - Recommendation: Proceed with Embedded form type; add validation to check template exists before duplication
   - Confidence: HIGH - Phase 1 already validated template exists

2. **Fields updatable after duplication**
   - What we know: `name`, `raw_goal`, `overview` are confirmed updatable
   - What's unclear: Complete list of fields that can be updated post-duplication vs. must be in overrides
   - Recommendation: Put core fields in overrides, use update for optional fields like overview
   - Confidence: MEDIUM - Pattern works but some trial-and-error may be needed
   - Source: .planning/research/STACK.md lines 205-225

3. **Campaign publish response format**
   - What we know: Publish endpoint exists, takes empty body
   - What's unclear: Does response include updated campaign object or just success confirmation?
   - Recommendation: Don't rely on response data; fetch campaign separately if needed
   - Confidence: MEDIUM - Standard REST pattern suggests minimal response

4. **ACF field group for disable checkbox**
   - What we know: ACF field group key is `gofundme_settings` (line 44 of class-sync-handler.php)
   - What's unclear: Should checkbox be added via code or ACF admin UI?
   - Recommendation: Add via ACF admin UI (simpler, no deployment complexity)
   - Confidence: HIGH - Other fields in same group added via UI

5. **Reactivate-then-publish timing**
   - What we know: Reactivate returns campaign to "unpublished", then must publish separately
   - What's unclear: Is there a required delay between reactivate and publish?
   - Recommendation: Call immediately in sequence; API will handle timing
   - Confidence: MEDIUM - Sequential API calls should work but untested

## Sources

### Primary (HIGH confidence)
- **Existing codebase:** `class-api-client.php`, `class-sync-handler.php` - Campaign infrastructure 80% complete
- **Project research documents:** `.planning/research/STACK.md` (campaign endpoints), `.planning/research/ARCHITECTURE.md` (integration patterns), `.planning/research/PITFALLS.md` (POST /campaigns 403 issue)
- **Phase 1 outputs:** Template campaign 762968 exists and validated, fundraising goal field implemented

### Secondary (MEDIUM confidence)
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html) - Official endpoint reference (redirects to GoFundMe Pro)
- [Campaign Status Definitions](https://support.classy.org/s/article/campaign-status-definitions) - Status lifecycle documentation
- [Duplicate a Campaign](https://support.classy.org/s/article/how-to-duplicate-a-campaign) - UI-based duplication feature
- [Convertr: Campaign Duplication API](https://www.convertr.io/resources/product/campaign-duplication-api) - Third-party integration example

### Tertiary (LOW confidence)
- [GoFundMe Pro Developers](https://developers.classy.org/) - General API overview (lacks specific endpoint details)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components exist in codebase or core WordPress
- Architecture patterns: HIGH - Existing code structure clear, extension points identified
- API endpoints: MEDIUM - Documented in research but not directly verified with API docs
- Pitfalls: MEDIUM-HIGH - Most based on project research and common integration issues

**Research date:** 2026-01-23
**Valid until:** 2026-02-23 (30 days - Classy API is stable, WordPress patterns don't change)

**Key verification performed:**
- ✅ Confirmed existing campaign sync hooks in `class-sync-handler.php`
- ✅ Confirmed meta key constants defined (lines 34, 37)
- ✅ Confirmed `build_campaign_data()` method exists (line 342)
- ✅ Confirmed POST /campaigns 403 issue documented in project research
- ✅ Confirmed template campaign ID setting exists from Phase 1
- ✅ Confirmed duplication workflow endpoint pattern from research docs
- ⚠️ NOT verified: Actual API responses (needs sandbox testing in planning)
