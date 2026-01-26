# Phase 3: Campaign Status Management - Research

**Researched:** 2026-01-26
**Domain:** Campaign status lifecycle and WordPress-to-Classy status synchronization
**Confidence:** HIGH

## Summary

Phase 3 addresses campaign status synchronization between WordPress post status and Classy campaign status. The key finding is a **BUG in the current implementation**: when a fund is set to draft, the code calls `deactivate_campaign()` (line 317 in class-sync-handler.php) instead of `unpublish_campaign()`. The `unpublish_campaign()` method exists in the API client (line 406) but is NEVER CALLED anywhere in the codebase.

**Key findings:**
- **BUG IDENTIFIED:** Draft status incorrectly triggers `deactivate_campaign()` instead of `unpublish_campaign()`
- Three distinct Classy campaign statuses: `active`, `unpublished`, `deactivated`
- `unpublish_campaign()` exists in API client but is unused - needs to be wired into status change logic
- `ensure_campaign_active()` already handles the publish flow correctly (reactivate->publish for deactivated campaigns)
- Idempotent API calls: calling publish on active campaign or unpublish on unpublished campaign should be safe (non-destructive)

**Primary recommendation:** Fix the `on_status_change()` method to call `unpublish_campaign()` instead of `deactivate_campaign()` when post status changes to draft. This is a minimal change - the infrastructure is 95% complete.

## Standard Stack

### Core (Already in Codebase)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| FCG_GFM_API_Client | Current | Campaign status operations | Already has all needed methods |
| FCG_GFM_Sync_Handler | Current | WordPress hook integration | Already hooks into transition_post_status |
| WordPress Post Status API | WP 5.8+ | Status transition detection | Uses transition_post_status hook |

### Supporting (Already in Codebase)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress Transients API | WP 5.8+ | Lock mechanism for status changes | Race condition prevention |
| WordPress Error Logging | WP 5.8+ | Debug output | `[FCG GoFundMe Sync]` prefix pattern |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| transition_post_status hook | save_post_funds only | transition_post_status captures status changes before save_post fires |
| unpublish for draft | deactivate for draft | Unpublish preserves ability to republish; deactivate is more permanent |

**Installation:**
No new dependencies required. All methods already exist in the codebase.

## Architecture Patterns

### Current Status Change Implementation (WITH BUG)
```php
// class-sync-handler.php on_status_change() method (line 273-323)
public function on_status_change(string $new_status, string $old_status, WP_Post $post): void {
    // ... validation checks ...

    $is_active = ($new_status === 'publish');

    // Update campaign based on publish status
    $campaign_id = $this->get_campaign_id($post->ID);
    if ($campaign_id && $this->should_sync_campaign($post->ID)) {
        if ($is_active) {
            // This works correctly via ensure_campaign_active()
            $this->ensure_campaign_active($campaign_id, $post->ID);
        } else {
            // BUG: This calls deactivate instead of unpublish
            $campaign_result = $this->api->deactivate_campaign($campaign_id); // LINE 317
        }
    }
}
```

### Pattern 1: Correct Status Mapping
**What:** Map WordPress post status to appropriate Classy campaign action
**When to use:** Any post status transition that affects campaign visibility
**Correct Implementation:**
```php
// Source: Requirements STAT-01, STAT-02, STAT-03
public function on_status_change(string $new_status, string $old_status, WP_Post $post): void {
    // ... existing validation checks ...

    $campaign_id = $this->get_campaign_id($post->ID);
    if (!$campaign_id || !$this->should_sync_campaign($post->ID)) {
        return;
    }

    if ($new_status === 'publish') {
        // STAT-02: republish -> campaign published (active)
        if ($this->ensure_campaign_active($campaign_id, $post->ID)) {
            $campaign_data = $this->build_campaign_data($post);
            $this->api->update_campaign($campaign_id, $campaign_data);
        }
    } elseif ($new_status === 'draft') {
        // STAT-01: unpublish -> campaign unpublished (NOT deactivated!)
        $this->api->unpublish_campaign($campaign_id);
    }
    // Note: trash->deactivated is handled by on_trash_fund() (correct)
}
```

### Pattern 2: Status Mapping Table (Requirement STAT-03)
**What:** Complete mapping of WordPress to Classy status
**When to use:** Understanding which API method to call

| WordPress Status | Classy API Method | Classy Status | Notes |
|------------------|-------------------|---------------|-------|
| `publish` (new) | `duplicate + publish` | `active` | Campaign creation |
| `publish` (existing) | `ensure_campaign_active` + `update` | `active` | Handles deactivated->unpublished->active |
| `draft` | `unpublish_campaign()` | `unpublished` | **BUG FIX NEEDED** |
| `trash` | `deactivate_campaign()` | `deactivated` | Already correct in on_trash_fund() |
| `restore from trash` | `reactivate + publish` | `active` | Already correct in on_untrash_fund() |
| `permanent delete` | `deactivate_campaign()` | `deactivated` | Already correct in on_delete_fund() |

### Pattern 3: Two-Step Restore Workflow
**What:** Restoring a trashed fund requires reactivate THEN publish
**When to use:** Fund restored from trash
**Implementation (Already Correct):**
```php
// Source: class-sync-handler.php on_untrash_fund() lines 201-228
// Step 1: Reactivate (returns campaign to unpublished status)
$reactivate_result = $this->api->reactivate_campaign($campaign_id);

// Step 2: Publish (makes campaign active again)
$publish_result = $this->api->publish_campaign($campaign_id);

// Step 3: Update campaign data
$this->api->update_campaign($campaign_id, $campaign_data);
```

### Anti-Patterns to Avoid
- **Using deactivate for draft:** Deactivate is for trash/permanent delete, unpublish is for draft
- **Skipping publish after reactivate:** Reactivate only returns to unpublished, not active
- **Assuming idempotent failures:** Calling unpublish on unpublished may return success or no-op, verify behavior

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Campaign unpublish | Custom status update | `$this->api->unpublish_campaign()` | Method already exists (line 406) |
| Deactivated->active | Direct publish | `$this->ensure_campaign_active()` | Handles reactivate->publish sequence |
| Status transition detection | Custom save_post logic | `transition_post_status` hook | Already hooked at line 76 |
| Campaign status check | Inline API call | `$this->api->get_campaign()` | Returns current status for branching |

**Key insight:** The infrastructure is complete. This phase requires WIRING changes, not new methods.

## Common Pitfalls

### Pitfall 1: Using Deactivate Instead of Unpublish for Draft (CURRENT BUG)
**What goes wrong:** Fund set to draft causes campaign deactivation instead of unpublish
**Why it happens:** Line 317 calls `deactivate_campaign()` for all non-publish statuses
**How to avoid:** Check for `draft` status specifically and call `unpublish_campaign()`
**Warning signs:** Cannot easily republish campaign; requires reactivate+publish instead of just publish
**Confidence:** HIGH - Verified in current codebase

### Pitfall 2: Unpublish Called on Already Unpublished Campaign
**What goes wrong:** API may return error or unexpected state
**Why it happens:** Repeated status changes or sync retries
**How to avoid:** Either check status first OR rely on API idempotency (test to verify)
**Warning signs:** API errors logged for unpublish calls
**Confidence:** MEDIUM - API behavior for idempotent calls not verified

### Pitfall 3: Missing Status Transitions
**What goes wrong:** Some WordPress status changes not captured
**Why it happens:** Only checking for `publish` vs `!publish`
**How to avoid:** Explicitly handle known statuses: `publish`, `draft`, let other hooks handle `trash`
**Warning signs:** Campaign status doesn't match WordPress status
**Confidence:** HIGH - Status mapping is explicit in requirements

### Pitfall 4: Republish After Unpublish Fails
**What goes wrong:** Campaign was unpublished, fund republished, but campaign stays unpublished
**Why it happens:** `ensure_campaign_active()` may not handle unpublished->active correctly
**How to avoid:** Verify `ensure_campaign_active()` handles all three states: active (no-op), unpublished (publish), deactivated (reactivate+publish)
**Warning signs:** Republished funds show campaign as unpublished in Classy
**Confidence:** MEDIUM - Current code only checks for `deactivated` status explicitly

**Verification needed:** Check if `ensure_campaign_active()` handles unpublished campaigns:
```php
// Current code (lines 350-375)
if ($status === 'active') {
    return true; // Already active
}

// If deactivated, need to reactivate first
if ($status === 'deactivated') {
    $reactivate = $this->api->reactivate_campaign($campaign_id);
    // ...
}

// Now publish (works for unpublished or just-reactivated)
$publish = $this->api->publish_campaign($campaign_id);
```
**Analysis:** The code DOES handle unpublished correctly - it falls through to the publish call. But this should be verified in testing.

## Code Examples

### Fix for on_status_change() (Minimal Change)
```php
// Source: Requirements STAT-01, STAT-03
// Change from:
} else {
    $campaign_result = $this->api->deactivate_campaign($campaign_id);
    if ($campaign_result['success']) {
        $this->log_info("Status change: deactivated campaign {$campaign_id} for post {$post->ID}");
    }
}

// To:
} elseif ($new_status === 'draft') {
    // STAT-01: unpublish campaign when fund is set to draft
    $campaign_result = $this->api->unpublish_campaign($campaign_id);
    if ($campaign_result['success']) {
        $this->log_info("Status change: unpublished campaign {$campaign_id} for post {$post->ID}");
    }
}
// Note: trash/delete handled by separate hooks (on_trash_fund, on_delete_fund)
```

### Existing unpublish_campaign Method (Already Implemented)
```php
// Source: class-api-client.php lines 397-408
/**
 * Unpublish a campaign
 *
 * Returns a campaign to unpublished (draft) status without deactivating.
 * Use this for temporarily hiding a campaign that may be republished.
 *
 * @param int|string $campaign_id Campaign ID
 * @return array Response
 */
public function unpublish_campaign($campaign_id): array {
    return $this->request('POST', "/campaigns/{$campaign_id}/unpublish", []);
}
```

### Test Scenarios to Verify
```php
// Test 1: STAT-01 - Fund publish -> draft
// Expected: campaign status changes from active to unpublished
// API call: POST /campaigns/{id}/unpublish

// Test 2: STAT-02 - Fund draft -> publish
// Expected: campaign status changes from unpublished to active
// API call: POST /campaigns/{id}/publish (via ensure_campaign_active)

// Test 3: STAT-03 - Full cycle
// publish (active) -> draft (unpublished) -> publish (active)
// trash (deactivated) -> restore (active via reactivate+publish)
```

## State of the Art

| Old Approach (Current Bug) | Correct Approach | Impact |
|----------------------------|------------------|--------|
| `deactivate_campaign()` for draft | `unpublish_campaign()` for draft | Easier republish (just publish vs reactivate+publish) |
| Binary is_active check | Three-way status handling | Proper status mapping per requirements |

**Key difference between unpublished and deactivated:**
- **Unpublished:** Campaign exists, not visible to public, can be published with single API call
- **Deactivated:** Campaign is "closed", requires reactivate (returns to unpublished) THEN publish to make active again

## Open Questions

1. **Idempotent API calls**
   - What we know: Methods exist for all status transitions
   - What's unclear: Does calling unpublish on already-unpublished campaign return success or error?
   - Recommendation: Test in sandbox; if error, add status check before call
   - Confidence: MEDIUM - Most REST APIs are idempotent for status changes

2. **Other WordPress statuses (pending, private, future)**
   - What we know: Requirements only specify publish, draft, trash
   - What's unclear: Should pending/private/future map to unpublished?
   - Recommendation: Treat non-publish, non-trash as "draft" (unpublish campaign)
   - Confidence: MEDIUM - Reasonable default, may need client input

3. **Scheduled posts (future status)**
   - What we know: WordPress "future" status means scheduled post
   - What's unclear: Should campaign be unpublished until publish date?
   - Recommendation: Yes, treat as draft (unpublished) until WordPress auto-publishes
   - Confidence: LOW - Edge case, needs testing

## Sources

### Primary (HIGH confidence)
- **Existing codebase:** `class-api-client.php` (lines 393-421) - All four status methods implemented
- **Existing codebase:** `class-sync-handler.php` (lines 273-323) - BUG location identified
- **Project research:** `.planning/research/PITFALLS.md` (lines 349-437) - Campaign status lifecycle documented
- **Project research:** `.planning/research/STACK.md` (lines 302-332) - Status workflow diagram

### Secondary (MEDIUM confidence)
- [Campaign Status Definitions - Classy support center](https://support.classy.org/s/article/campaign-status-definitions) (redirects to prosupport.gofundme.com)
- [Publish your campaign - GoFundMe Pro Help Center](https://prosupport.gofundme.com/hc/en-us/articles/37288720532891-Publish-your-campaign)
- [Classy API Documentation](https://developers.gofundme.com/pro/docs/) - Status endpoint patterns

### Tertiary (LOW confidence)
- WebSearch results confirming publish/unpublish/deactivate as distinct operations
- classy-node GitHub repository - confirms publish(), unpublish(), deactivate() methods exist

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All methods already exist in codebase
- Architecture patterns: HIGH - Minimal change to existing structure
- Pitfalls: HIGH - Bug clearly identified in code
- API behavior: MEDIUM - Idempotency not verified

**Research date:** 2026-01-26
**Valid until:** 2026-02-26 (30 days - Classy API is stable)

**Key verification performed:**
- Confirmed `unpublish_campaign()` exists in API client (line 406)
- Confirmed `unpublish_campaign()` is NEVER called in sync handler
- Confirmed line 317 incorrectly calls `deactivate_campaign()` for non-publish status
- Confirmed `ensure_campaign_active()` handles unpublished->active transition (falls through to publish)
- Confirmed trash handling already uses `deactivate_campaign()` correctly (on_trash_fund line 167)

**Verification needed in planning/testing:**
- API response when calling unpublish on already-unpublished campaign
- API response when calling publish on already-active campaign
- Handling of pending, private, future WordPress statuses
