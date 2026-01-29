# Phase 6: Master Campaign Integration - Research

**Researched:** 2026-01-29
**Domain:** Classy/GoFundMe Pro API - Campaign Designations
**Confidence:** MEDIUM (Luke confirmed adding, but unlinking method unverified)

## Summary

This research investigated how to add and remove designations from a campaign's "Default Active Group" (also called "Group Designations") in Classy. The core problem is that creating a designation via the Classy API places it at the organization level ("All Designations"), but campaigns have their own subset of "active" designations that appear in the donation form dropdown.

**Critical Finding - Adding:** Luke Dringoli (GoFundMe Principal Technical Partnerships Manager) confirmed via email on 2026-01-28 that `PUT /campaigns/{id}` with `{"designation_id": ...}` **DOES add** the designation to the campaign's active designation group. His email included a screenshot showing:
- API call: `PUT /campaigns/763276` with body `{"designation_id": "1896309"}`
- Response: `200 OK` with designation linked
- UI confirmation: "Test Designation" appeared in campaign's "Default Active Group" (1 designation)

**Critical Gap - Removing:** No verified API method exists for REMOVING a designation from a campaign's active group. WebSearch and documentation access attempts did not reveal a dedicated "unlink designation from campaign" endpoint. This leaves the "unpublish/trash" behavior from CONTEXT.md unimplemented.

**Primary recommendation:** Implement the confirmed `PUT /campaigns/{id}` with `{"designation_id": ...}` approach for ADDING designations after creation. For REMOVING, recommend one of three approaches (see Open Questions section).

**Source:** Email thread saved at `docs/classy-email-thread-2026-01-28.md`

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress HTTP API | WP 5.8+ | API requests | `wp_remote_request()` used in existing codebase |
| Classy API v2.0 | 2.0 | Classy integration | Only supported API version |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress Options API | Core | Store master campaign/component IDs | Settings storage |
| WordPress Transients API | Core | Token caching | Already implemented |

### Existing Methods (Keep)
| Method | Class | Purpose |
|--------|-------|---------|
| `update_campaign()` | `FCG_GFM_API_Client` | PUT /campaigns/{id} - confirmed for adding designation |
| `get_campaign()` | `FCG_GFM_API_Client` | GET /campaigns/{id} - verify campaign state |
| `create_designation()` | `FCG_GFM_API_Client` | POST /organizations/{id}/designations |
| `update_designation()` | `FCG_GFM_API_Client` | PUT /designations/{id} - manage is_active flag |

**Installation:** No new dependencies required.

## Architecture Patterns

### Recommended Project Structure
```
includes/
  class-api-client.php   # No new methods needed for Phase 6
  class-sync-handler.php # Add link_designation_to_campaign() method
  class-admin-ui.php     # Rename template → master, add component ID
```

### Pattern 1: Add to Campaign Group (Confirmed Working)
**What:** After designation creation succeeds, trigger a separate API call to add designation to campaign group
**When to use:** Every time a fund is published
**Example:**
```php
// In FCG_GFM_Sync_Handler::create_designation()
// After successful designation creation:

$designation_id = $result['data']['id'];
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');

// Call update_campaign with designation_id to add to active group
$this->api->update_campaign($master_campaign_id, [
    'designation_id' => $designation_id  // Confirmed by Luke's email
]);
```

### Pattern 2: Remove from Campaign Group (UNVERIFIED)
**What:** Remove a designation from campaign's active group without deleting designation
**When to use:** Fund unpublished/trashed (per CONTEXT.md requirements)
**Problem:** No confirmed API method exists

**Three possible approaches:**
1. **Use is_active flag only** - Don't remove from campaign, just deactivate designation
2. **Contact Classy support** - Ask for removal endpoint before implementation
3. **Accept manual curation** - UI-only operation, API doesn't support unlinking

See Open Questions section for detailed analysis.

### Pattern 3: Settings Storage
**What:** Store master campaign and component IDs in WordPress options
**When to use:** Phase 6 settings implementation
**Example:**
```php
// Settings
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id', '');
$master_component_id = get_option('fcg_gofundme_master_component_id', '');

// Setting update (admin-settings.php)
update_option('fcg_gofundme_master_campaign_id', sanitize_text_field($_POST['master_campaign_id']));
update_option('fcg_gofundme_master_component_id', sanitize_text_field($_POST['master_component_id']));
```

### Pattern 4: Idempotency Check (Defer to Testing)
**What:** Check if designation already linked before attempting to add
**When to use:** Prevent duplicate API calls on re-publish
**Note:** Defer implementation until testing confirms it's needed. The API may handle this gracefully.

### Anti-Patterns to Avoid
- **Assuming `designation_id` sets both default AND list:** Per Luke's screenshot, it adds to the active group (which is correct for our use case)
- **Polling campaigns for designation lists:** No evidence this returns the "active group" list reliably
- **Creating multiple campaigns:** Architecture pivot confirmed single master campaign approach

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| API authentication | Custom OAuth2 | Existing `get_access_token()` | Already battle-tested |
| Settings UI | Custom admin page | Extend existing admin-settings.php | Consistent UX |
| Error handling | Custom retry logic | Existing `request()` method | Already has error handling |
| Designation sync | New implementation | Existing sync-handler.php | Already handles create/update/delete |

**Key insight:** The codebase already has solid API infrastructure and designation sync. The gap is specifically in campaign-level designation group management.

## Code Examples

Verified patterns from existing codebase:

### Create Designation (Existing, Confirmed Working)
```php
// Source: Existing codebase - class-api-client.php
public function create_designation(array $data): array {
    return $this->request('POST', "/organizations/{$this->org_id}/designations", $data);
}

// Usage in sync-handler.php
$result = $this->api_client->create_designation([
    'name' => $fund_title,
    'external_reference_id' => strval($post_id),
    'is_active' => true,
]);
```

### Update Campaign to Add Designation (Confirmed by Luke)
```php
// Source: Existing codebase - class-api-client.php
public function update_campaign($campaign_id, array $data): array {
    return $this->request('PUT', "/campaigns/{$campaign_id}", $data);
}

// Add designation to campaign's active group
$this->api_client->update_campaign($master_campaign_id, [
    'designation_id' => $designation_id
]);
```

### Settings Rename (Template → Master)
```php
// Current option name
'fcg_gfm_template_campaign_id'

// New option name
'fcg_gofundme_master_campaign_id'

// Migration during admin UI initialization
$old_value = get_option('fcg_gfm_template_campaign_id');
if ($old_value && !get_option('fcg_gofundme_master_campaign_id')) {
    update_option('fcg_gofundme_master_campaign_id', $old_value);
    delete_option('fcg_gfm_template_campaign_id');
}
```

### Current Status Transition Pattern (Keep This)
```php
// Source: class-sync-handler.php::on_status_change()
// This pattern already handles is_active correctly
public function on_status_change(string $new_status, string $old_status, WP_Post $post): void {
    if ($post->post_type !== self::POST_TYPE) {
        return;
    }

    $designation_id = $this->get_designation_id($post->ID);

    if (!$designation_id) {
        return;
    }

    // Update is_active based on publish status
    $is_active = ($new_status === 'publish');

    $result = $this->api->update_designation($designation_id, [
        'is_active' => $is_active,
    ]);
}
```

## Common Pitfalls

### Pitfall 1: Confusing Organization Designations with Campaign Designation Groups
**What goes wrong:** Assuming creating a designation at org level adds it to all campaigns
**Why it happens:** Classy has TWO levels: org-level "Program Designations" and campaign-level "Group Designations" (active designations)
**How to avoid:** Understand the hierarchy:
1. Organization has "Program Designations" (created via API)
2. Each Campaign has "Group Designations" (subset of org designations)
3. Creating a designation via API only adds to level 1, NOT level 2
4. Must explicitly link designation to campaign via `PUT /campaigns/{id}`
**Warning signs:** New designations don't appear in donation form dropdown

### Pitfall 2: Misunderstanding Campaign Linking Behavior - RESOLVED
**UPDATE (2026-01-29):** Luke's email with screenshot confirms `PUT /campaigns/{id}` with `{"designation_id": X}` **DOES add** X to the campaign's "Default Active Group" - it appears in both the dropdown AND as the default.

**Original concern was:** Thinking this only sets the default, not the available list
**Resolution:** Luke's screenshot shows the API call adding "Test Designation" to the campaign's active group
**Action:** Use this endpoint after each designation creation

### Pitfall 3: Assuming Symmetric Add/Remove Operations
**What goes wrong:** Expecting that if `PUT /campaigns/{id}` with `designation_id` ADDS, then some similar call REMOVES
**Why it happens:** APIs often have asymmetric operations (easy to add, hard to remove)
**How to avoid:** Verify removal operations separately; don't assume they exist
**Warning signs:** Can't find documentation for unlinking operation
**Current status:** This is an OPEN QUESTION for Phase 6 (see below)

### Pitfall 4: Assuming API Feature Parity with UI
**What goes wrong:** Expecting all Classy UI features to be available via API
**Why it happens:** Fundraising platforms often have UI-only features
**How to avoid:** Verify each required operation has an API endpoint BEFORE planning implementation
**Warning signs:** Cannot find endpoint in API documentation
**Current example:** UI can manage "Group designations" but API method for removal is unconfirmed

## Open Questions

Things that couldn't be fully resolved:

### 1. How to REMOVE Designation from Campaign Group (CRITICAL)
**What we know:**
- UI has "Group designations" for managing which designations appear in dropdown
- `PUT /campaigns/{id}` with `{"designation_id": X}` ADDS X to active group (confirmed by Luke)
- 856 of 861 designations are in the "Default Active Group" (manually added)
- CONTEXT.md requires: "On unpublish/draft: Remove designation from campaign group"

**What's unclear:**
- Is there an API endpoint to REMOVE a designation from a campaign's group?
- Possible endpoints (all UNVERIFIED):
  - `DELETE /campaigns/{id}/designations/{designation_id}` - Remove from campaign
  - `PUT /campaigns/{id}` with empty/null `designation_id` - Clear current designation
  - No API method - UI-only operation

**Three implementation approaches:**

**Option A: Use is_active flag only (RECOMMENDED for Phase 6)**
- Don't remove from campaign group, just set `designation_id.is_active = false`
- Classy may automatically hide inactive designations from dropdowns
- Simplest implementation, already working in current codebase
- Aligns with existing on_status_change() pattern
- **Tradeoff:** Designation stays "linked" to campaign even when unpublished

**Option B: Contact Classy support before implementation**
- Email Luke Dringoli or Jon Bierma with specific question
- Ask: "How do I REMOVE a designation from a campaign's active group via API?"
- Wait for confirmation before implementing Phase 6
- **Tradeoff:** Delays Phase 6 until response received

**Option C: Accept UI-only curation**
- API adds designations automatically (Phase 6 implements this)
- Removal is manual in Classy UI if needed
- Most designations stay linked to campaign permanently
- **Tradeoff:** Can't fully automate the unpublish workflow

**Recommendation:** Implement Option A for Phase 6. The is_active flag already works and may be sufficient. Test whether inactive designations appear in campaign dropdown. If they do appear (and shouldn't), implement Option B for Phase 6.1.

### 2. Master Campaign Configuration
**What we know:**
- Master Campaign ID: 764694
- Master Component ID: mKAgOmLtRHVGFGh_eaqM6
- Campaign already created in Classy UI

**What's unclear:**
- Is the master campaign a "Studio" or "Legacy" campaign type?
- Does the campaign type affect API behavior for designation groups?

**Recommendation:** Document campaign type during Phase 6 testing (06-02-PLAN.md)

### 3. Idempotency Behavior
**What we know:**
- CONTEXT.md requires: "Check if designation is already in campaign group before adding"
- This prevents duplicate add attempts

**What's unclear:**
- Does `PUT /campaigns/{id}` with `designation_id` fail if already added?
- Does it succeed silently (idempotent)?
- Do we need to GET the campaign first to check?

**Recommendation:** Defer idempotency check until testing. If API is already idempotent (likely), no additional check needed. Log the response during testing to confirm.

### 4. Error Handling for Linking Failures
**What we know:**
- CONTEXT.md requires: "Retry 2-3 times, log failures, show admin notice if linking fails"
- CONTEXT.md requires: "Do NOT block fund publish if linking fails"

**What's unclear:**
- Does the existing `request()` method in API client already retry?
- Should retries be in sync-handler or API client?

**Recommendation:** Review existing error handling in class-api-client.php. If `request()` already handles transient failures, use it as-is. Add retry logic only if testing reveals consistent failures.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Per-fund campaigns | Single master campaign | 2026-01-28 | Architectural simplification |
| Classy branding | GoFundMe Pro branding | May 2025 | URLs redirect, API same |
| developers.classy.org | developers.gofundme.com | 2025 | Documentation location |

**Deprecated/outdated:**
- Per-fund campaign duplication: Removed in Phase 5
- Campaign publish/unpublish workflow: Removed in Phase 5
- `_gofundme_campaign_id` post meta: Orphaned, can be cleaned up

## Classy API Endpoints Reference

### Confirmed Endpoints (HIGH Confidence)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/campaigns/{id}` | Retrieve campaign details |
| PUT | `/campaigns/{id}` | Update campaign (confirmed: adds designation to active group) |
| GET | `/campaigns/{id}/overview` | Get donation totals |
| POST | `/organizations/{id}/designations` | Create designation |
| PUT | `/designations/{id}` | Update designation (including is_active) |
| DELETE | `/designations/{id}` | Delete designation permanently |
| GET | `/organizations/{id}/designations` | List all org designations |

### Hypothetical Endpoints (LOW Confidence - UNVERIFIED)
| Method | Endpoint | Purpose | Status |
|--------|----------|---------|--------|
| DELETE | `/campaigns/{id}/designations/{designation_id}` | Remove designation from campaign | Not found in docs |
| POST | `/campaigns/{id}/designations` | Add designation to campaign | Not found in docs |
| GET | `/campaigns/{id}/designations` | List campaign's active designations | Not found in docs |
| POST | `/campaigns/{id}/designation_groups` | Create designation group | Not found in docs |

## Sources

### Primary (HIGH confidence)
- **Luke Dringoli Email (2026-01-28)** - GoFundMe Principal Technical Partnerships Manager confirmed `PUT /campaigns/{id}` with `{"designation_id": ...}` adds to active group. Screenshot evidence included. Saved at: `docs/classy-email-thread-2026-01-28.md`
- **Existing codebase** - `includes/class-api-client.php`, `includes/class-sync-handler.php`, `includes/class-admin-ui.php` - Verified working methods and patterns
- **Phase 5 testing results** - Designation creation confirmed, group addition gap identified (now resolved)
- **Phase 6 CONTEXT.md** - User decisions and requirements for this phase

### Secondary (MEDIUM confidence)
- [GoFundMe Pro Help Center - Program Designations](https://prosupport.gofundme.com/hc/en-us/articles/37288737202843-Program-designations) - UI documentation confirms group designation concept
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html) - General API reference (redirects to GoFundMe domain, 404 encountered)
- WebSearch results (2026-01-29) - Confirmed designation management concepts but did not reveal removal endpoint

### Tertiary (LOW confidence)
- WebSearch results for unlinking designations - Could not find specific removal method
- Classy developers documentation - Redirects and access issues prevented direct verification

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using existing proven infrastructure
- Settings implementation: HIGH - Standard WordPress patterns, clear from existing code
- Adding designation to campaign: HIGH - Confirmed by Luke Dringoli with screenshot evidence
- Removing designation from campaign: LOW - No verified method found
- Overall phase confidence: MEDIUM - Core feature confirmed but removal gap exists

**Research date:** 2026-01-29
**Updated:** 2026-01-29 with Luke's email confirmation and removal method investigation

## Recommendations for Phase 6 Planning

### For 06-01-PLAN.md (Settings + Add to Campaign)

**Confirmed approach for ADDING designations:**
```php
// In sync-handler.php after successful create_designation()
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
$this->api_client->update_campaign($master_campaign_id, [
    'designation_id' => $designation_id
]);
```

This adds the designation to the campaign's "Default Active Group" per Luke's screenshot evidence.

**Settings implementation:**
1. Rename "Template Campaign ID" to "Master Campaign ID"
2. Add "Master Component ID" setting for embed code (Phase 7)
3. Migrate existing option value if present (`fcg_gfm_template_campaign_id` → `fcg_gofundme_master_campaign_id`)
4. Update settings page UI with clear descriptions
5. Keep existing validation logic

**Error handling:**
- Don't block designation creation if campaign linking fails
- Log linking failures for debugging
- Linking can be retried or done manually in Classy UI
- Consider admin notice for persistent failures (defer to testing)

### For 06-02-PLAN.md (Staging Verification)

**Testing checklist:**
1. Create test fund in WordPress staging
2. Publish fund → verify designation created in Classy
3. Check Classy UI: does designation appear in master campaign's "Default Active Group"?
4. Check Classy donation embed: does designation appear in dropdown?
5. Unpublish fund → verify designation is_active = false
6. Check Classy embed: does inactive designation still appear in dropdown? (CRITICAL TEST)

**If inactive designations appear in dropdown:**
- Implement Option B (contact Classy support for removal method)
- May require Phase 6.1 plan

**If inactive designations DON'T appear:**
- Current implementation is sufficient
- No removal API needed

### Out of Scope for Phase 6

These items from CONTEXT.md should be deferred:
- **Component ID setting** - Add to settings page but not needed until Phase 7 (Frontend Embed)
- **Bulk sync of 5 pending designations** - Can be done manually or in Phase 8
- **Idempotency checks** - Defer until testing shows it's necessary
- **Retry logic** - Existing API client may handle this already
- **Admin notices** - Defer until testing shows persistent failures
