# Phase 6: Master Campaign Integration - Research

**Researched:** 2026-01-29
**Domain:** Classy/GoFundMe Pro API - Campaign Designations
**Confidence:** MEDIUM (API documentation gaps exist)

## Summary

This research investigated how to add newly created designations to a campaign's "Default Active Group" (also called "Group Designations") in Classy. The core problem is that creating a designation via the Classy API places it at the organization level ("All Designations"), but campaigns have their own subset of "active" designations that appear in the donation form dropdown.

**Critical Finding:** The Classy API documentation does not appear to expose a public endpoint for managing campaign-level designation groups. The `PUT /campaigns/{id}` endpoint with `{"designation_id": ...}` sets the campaign's DEFAULT designation (used when donor doesn't select one), NOT the list of available designations in the dropdown.

**Primary recommendation:** Contact Classy/GoFundMe Pro support to confirm the correct API endpoint for adding designations to a campaign's active designation group, OR implement a workaround using manual curation in the Classy UI for new designations.

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
| `update_campaign()` | `FCG_GFM_API_Client` | PUT /campaigns/{id} - may be used for default designation |
| `get_campaign()` | `FCG_GFM_API_Client` | GET /campaigns/{id} - verify campaign state |
| `create_designation()` | `FCG_GFM_API_Client` | POST /organizations/{id}/designations |

**Installation:** No new dependencies required.

## Architecture Patterns

### Recommended Project Structure
```
includes/
  class-api-client.php   # Add campaign_designations methods (if API exists)
  class-sync-handler.php # Add post-designation-creation hook
```

### Pattern 1: Post-Creation Hook
**What:** After designation creation succeeds, trigger a separate API call to add designation to campaign group
**When to use:** If Classy API provides an endpoint
**Example:**
```php
// In FCG_GFM_Sync_Handler::sync_designation_to_gofundme()
// After successful designation creation:

$designation_id = $result['data']['id'];
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');

// Option A: If dedicated endpoint exists
$this->api_client->add_designation_to_campaign_group($master_campaign_id, $designation_id);

// Option B: If PUT /campaigns/{id} with designation array works
$this->api_client->update_campaign($master_campaign_id, [
    'designation_ids' => [$designation_id], // Hypothetical - needs verification
]);

// Option C: Set as default designation (confirmed behavior)
$this->api_client->update_campaign($master_campaign_id, [
    'designation_id' => $designation_id  // Sets DEFAULT only
]);
```

### Pattern 2: Settings Storage
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

### Anti-Patterns to Avoid
- **Assuming `designation_id` in PUT /campaigns adds to dropdown:** The `designation_id` parameter sets the DEFAULT designation, not the available list
- **Polling campaigns for designation lists:** No evidence this returns the "active group" list
- **Creating multiple campaigns:** Architecture pivot confirmed single master campaign approach

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| API authentication | Custom OAuth2 | Existing `get_access_token()` | Already battle-tested |
| Settings UI | Custom admin page | Extend existing admin-settings.php | Consistent UX |
| Error handling | Custom retry logic | Existing `request()` method | Already has error handling |

**Key insight:** The codebase already has solid API infrastructure. The gap is specifically in campaign-level designation group management.

## Common Pitfalls

### Pitfall 1: Confusing Organization Designations with Campaign Designation Groups
**What goes wrong:** Assuming creating a designation at org level adds it to all campaigns
**Why it happens:** Classy has TWO levels: org-level "Program Designations" and campaign-level "Group Designations" (active designations)
**How to avoid:** Understand the hierarchy:
1. Organization has "Program Designations" (created via API)
2. Each Campaign has "Group Designations" (subset of org designations)
3. Creating a designation via API only adds to level 1, NOT level 2
**Warning signs:** New designations don't appear in donation form dropdown

### Pitfall 2: Misunderstanding `designation_id` on Campaigns
**What goes wrong:** Using `PUT /campaigns/{id}` with `{"designation_id": X}` expecting it to add X to the dropdown
**Why it happens:** Luke's email suggested this endpoint for "linking" designations
**How to avoid:**
- This sets the DEFAULT designation (fallback when donor doesn't choose)
- It does NOT add the designation to the campaign's available list
**Warning signs:** Designation becomes default but doesn't appear in dropdown

### Pitfall 3: Legacy vs Studio Campaign Types
**What goes wrong:** Different campaign types have different designation group features
**Why it happens:** Classy has "Legacy" campaigns and "Campaign Studio" campaigns with different UIs
**How to avoid:** Verify master campaign type; Studio campaigns have "Group designations" while Legacy has "All Designations"
**Warning signs:** UI terminology mismatch

### Pitfall 4: Assuming API Feature Parity with UI
**What goes wrong:** Expecting all Classy UI features to be available via API
**Why it happens:** Fundraising platforms often have UI-only features
**How to avoid:** Verify each required operation has an API endpoint BEFORE planning implementation
**Warning signs:** Cannot find endpoint in API documentation

## Code Examples

Verified patterns from official sources:

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

### Update Campaign (Existing, Sets Default Designation)
```php
// Source: Existing codebase - class-api-client.php
public function update_campaign($campaign_id, array $data): array {
    return $this->request('PUT', "/campaigns/{$campaign_id}", $data);
}

// Sets DEFAULT designation (NOT the dropdown list)
$this->api_client->update_campaign($master_campaign_id, [
    'designation_id' => $designation_id
]);
```

### Settings Rename (Template -> Master)
```php
// Current option name
'fcg_gofundme_template_campaign_id'

// New option name
'fcg_gofundme_master_campaign_id'

// Migration during activation
$old_value = get_option('fcg_gofundme_template_campaign_id');
if ($old_value && !get_option('fcg_gofundme_master_campaign_id')) {
    update_option('fcg_gofundme_master_campaign_id', $old_value);
    delete_option('fcg_gofundme_template_campaign_id');
}
```

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

## Open Questions

Things that couldn't be fully resolved:

### 1. Campaign Designation Group API Endpoint (CRITICAL)
**What we know:**
- UI has "Group designations" for managing which designations appear in dropdown
- Creating a designation via API puts it in "All designations" NOT the active group
- 856 of 861 designations are in the "Default Active Group" (manually added)
- 5 new designations created via API are NOT in the group

**What's unclear:**
- Is there an API endpoint to add a designation to a campaign's group?
- Possible endpoints (all UNVERIFIED):
  - `POST /campaigns/{id}/designations` - Add to campaign
  - `PUT /campaigns/{id}` with `designation_ids` array - Bulk set
  - `POST /campaigns/{id}/designation_groups/{group_id}/designations` - Add to specific group

**Recommendation:**
1. Contact Classy/GoFundMe Pro support to get definitive API documentation
2. Ask specifically: "How do I add a designation to a campaign's active designation group via API?"
3. Alternative: Accept manual curation in UI for new designations (not ideal for automation)

### 2. What Happens When `designation_id` is Set on Campaign?
**What we know:**
- `PUT /campaigns/{id}` with `{"designation_id": X}` is documented
- Luke's email said this "links" the designation to the campaign

**What's unclear:**
- Does this add to the dropdown list, or just set the default?
- Testing in Phase 5 suggests it does NOT add to dropdown
- Need explicit verification from Classy

**Recommendation:** Test explicitly on staging with a new designation

### 3. Master Campaign Configuration
**What we know:**
- Master Campaign ID: 764694
- Master Component ID: mKAgOmLtRHVGFGh_eaqM6
- Campaign already created in Classy UI

**What's unclear:**
- Is the master campaign a "Studio" or "Legacy" campaign type?
- Does the campaign type affect API behavior for designation groups?

**Recommendation:** Verify campaign type in Classy UI before implementation

## Classy API Endpoints Reference

### Confirmed Endpoints (HIGH Confidence)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/campaigns/{id}` | Retrieve campaign details |
| PUT | `/campaigns/{id}` | Update campaign (sets default designation) |
| GET | `/campaigns/{id}/overview` | Get donation totals |
| POST | `/organizations/{id}/designations` | Create designation |
| PUT | `/designations/{id}` | Update designation |
| DELETE | `/designations/{id}` | Delete designation |
| GET | `/organizations/{id}/designations` | List all org designations |

### Hypothetical Endpoints (LOW Confidence - UNVERIFIED)
| Method | Endpoint | Purpose | Status |
|--------|----------|---------|--------|
| POST | `/campaigns/{id}/designations` | Add designation to campaign | Not confirmed |
| GET | `/campaigns/{id}/designations` | List campaign's active designations | Not confirmed |
| POST | `/campaigns/{id}/designation_groups` | Create designation group | Not confirmed |

## Sources

### Primary (HIGH confidence)
- Existing codebase: `includes/class-api-client.php` - Verified working methods
- Phase 5 testing results: Designation creation confirmed, group addition gap identified

### Secondary (MEDIUM confidence)
- [GoFundMe Pro Help Center - Program Designations](https://prosupport.gofundme.com/hc/en-us/articles/37288737202843-Program-designations) - UI documentation confirms group designation concept
- [Factor 1 Studios - Classy API Article](https://factor1studios.com/harnessing-power-classy-api/) - Third-party implementation patterns
- [Classy API Marketing Page](https://www.classy.org/classy-api/) - General API overview

### Tertiary (LOW confidence)
- WebSearch results for Classy API endpoints - Could not access actual API spec
- Classy developers documentation redirects - New GoFundMe Pro domain, specs not directly fetchable

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using existing proven infrastructure
- Settings implementation: HIGH - Standard WordPress patterns
- Campaign designation groups: LOW - No verified API endpoint found

**Research date:** 2026-01-29
**Valid until:** Until Classy support confirms API endpoint (recommend follow-up within 7 days)

## Recommendations for Phase 6 Planning

### Option A: Contact Classy Support (Recommended)
1. Pause Phase 6 implementation planning
2. Contact Classy/GoFundMe Pro support with specific question:
   - "We're using the Classy API to create designations via `POST /organizations/{id}/designations`. The designations appear in 'All designations' but not in a campaign's active designation group. What API endpoint adds a designation to a campaign's active designation group?"
3. Resume planning once API endpoint is confirmed

### Option B: Test Luke's Suggestion
1. Create test designation via API
2. Call `PUT /campaigns/764694` with `{"designation_id": {test_id}}`
3. Check Classy UI - does it appear in campaign's "Group designations"?
4. If yes, Luke's approach works and we can proceed
5. If no, fall back to Option A

### Option C: Accept Manual Step
1. Implement automated designation creation
2. Document that new designations require manual addition to campaign group in Classy UI
3. Create admin notice in WordPress when designation created: "New designation created. Add to campaign group in Classy UI: [link]"
4. Not ideal for automation but unblocks the phase

### Settings Implementation (Safe to Plan)
Regardless of designation group question, these can be planned now:
1. Rename "Template Campaign ID" to "Master Campaign ID"
2. Add "Master Component ID" setting
3. Migrate existing option value
4. Update settings page UI
