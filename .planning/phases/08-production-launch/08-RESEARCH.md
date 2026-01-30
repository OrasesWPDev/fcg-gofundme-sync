# Phase 8: Production Launch (MVP) - Research

**Researched:** 2026-01-29
**Domain:** WordPress Admin UI, Delete Sync Verification, Production Deployment
**Confidence:** HIGH

## Summary

Phase 8 completes the MVP by enhancing admin UI visibility, verifying delete sync behavior, and planning production deployment. After extensive codebase analysis, the majority of admin UI functionality already exists in `class-admin-ui.php`. The DELETE endpoint is already implemented and awaits verification on staging. Production deployment documentation is complete.

Key findings:
1. **Admin UI is 90% complete** - Designation ID, last sync, and Sync Now button already implemented
2. **DELETE endpoint is implemented** - Just needs staging verification
3. **Production deployment checklist exists** - Only minor gaps to address

**Primary recommendation:** This phase is largely verification and documentation work, not new feature development. Focus on testing the DELETE flow and ensuring production readiness.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Core | 5.8+ | Meta box API, admin hooks | Already in use, native to project |
| jQuery | WP bundled | AJAX interactions | Already loaded by WordPress admin |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| wp-ajax | Core | Async operations | Sync Now button (already implemented) |
| wp-nonce | Core | CSRF protection | Form and AJAX security (already implemented) |

### Existing Implementation (No New Dependencies)

The project already has a complete admin UI stack:
- `includes/class-admin-ui.php` - Admin interface class
- `assets/js/admin.js` - jQuery AJAX handlers
- `assets/css/admin.css` - Admin styling

**Installation:** No new packages needed.

## Architecture Patterns

### Existing Implementation Review

The codebase already implements all required admin UI patterns:

```
includes/
  class-admin-ui.php    # Complete admin UI implementation
assets/
  css/admin.css         # Admin styles
  js/admin.js           # AJAX handlers
```

### Pattern 1: WordPress Meta Box (Already Implemented)
**What:** Admin panel on post edit screen showing sync status
**Current implementation:** `render_sync_meta_box()` in class-admin-ui.php (lines 127-199)

Existing meta box displays:
- Designation ID (with clickable Classy admin link)
- Last Sync timestamp
- Last Sync Source
- Fundraising Goal (editable)
- Sync Error (if present)
- Sync Now button

**Gap Analysis:**
- [x] Designation ID displayed (ADMN-01) - **COMPLETE**
- [ ] Donation total displayed (ADMN-02) - **NEEDS IMPLEMENTATION**
- [x] Last sync timestamp (ADMN-03) - **COMPLETE**
- [x] Manual "Sync Now" button (ADMN-04) - **COMPLETE**

### Pattern 2: Delete Sync (Already Implemented)
**What:** Permanently deleting a fund removes designation from Classy
**Current implementation:**
- `on_delete_fund()` in class-sync-handler.php (lines 185-204)
- `delete_designation()` in class-api-client.php (lines 245-247)

```php
// Source: includes/class-sync-handler.php lines 185-204
public function on_delete_fund(int $post_id): void {
    $post = get_post($post_id);

    if (!$post || $post->post_type !== self::POST_TYPE) {
        return;
    }

    $designation_id = $this->get_designation_id($post_id);

    if (!$designation_id) {
        return;
    }

    // Permanently delete designation
    $result = $this->api->delete_designation($designation_id);

    if ($result['success']) {
        $this->log_info("Deleted designation {$designation_id} for deleted post {$post_id}");
    }
}
```

**Verification needed:** Test on staging that DELETE removes designation from both:
1. Campaign's Default Active Group
2. Organization's designations list entirely

### Pattern 3: Inbound Sync Data Display
**What:** Show donation totals from Classy inbound sync
**Current state:** Post meta exists but not displayed in meta box

Post meta keys (from class-sync-poller.php):
- `_gofundme_donation_total` - Total donations
- `_gofundme_donor_count` - Number of donors
- `_gofundme_goal_progress` - Percentage of goal
- `_gofundme_last_inbound_sync` - Timestamp

**Gap:** Meta box does not display these values. ADMN-02 requires adding this display.

### Anti-Patterns to Avoid
- **Overcomplicating admin UI:** Existing implementation is clean and functional, don't refactor
- **Adding new AJAX endpoints:** Existing `fcg_gfm_sync_now` action handles both single and bulk sync
- **Breaking working code:** Admin UI works well, only add donation totals display

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Meta box framework | Custom meta box class | WordPress `add_meta_box()` | Already implemented correctly |
| AJAX handlers | Custom REST endpoints | WordPress `wp_ajax_*` actions | Already implemented correctly |
| Admin styles | Custom CSS framework | WordPress admin classes | Already follows WP conventions |
| Nonce verification | Custom token system | `wp_nonce_field()` / `check_ajax_referer()` | Standard WP security |

**Key insight:** The existing implementation follows WordPress best practices. The only gap is displaying donation totals in the meta box.

## Common Pitfalls

### Pitfall 1: Rushing DELETE Verification
**What goes wrong:** Assuming DELETE works without testing, then finding issues in production
**Why it happens:** API endpoint exists, so it's assumed to be correct
**How to avoid:** Create test fund on staging, permanently delete, verify in Classy
**Warning signs:** Haven't tested DELETE flow end-to-end on staging

**Verification steps:**
1. Create test fund on staging
2. Verify designation appears in Classy
3. Trash the fund (should set `is_active: false`, NOT delete)
4. Verify designation still exists but inactive
5. Permanently delete the fund (Empty Trash)
6. Verify designation is GONE from Classy (both campaign group and org designations)

### Pitfall 2: Inconsistent Environment Variables
**What goes wrong:** Production deployment fails because credentials misconfigured
**Why it happens:** Environment variables set incorrectly or not at all
**How to avoid:** Document all required variables, verify before deployment
**Warning signs:** API errors immediately after production deployment

**Required environment variables:**
```
GOFUNDME_CLIENT_ID
GOFUNDME_CLIENT_SECRET
GOFUNDME_ORG_ID
```

### Pitfall 3: Missing Plugin Settings Configuration
**What goes wrong:** Designations created but not linked to campaign
**Why it happens:** Master Campaign ID not configured after plugin activation
**How to avoid:** Include settings configuration in deployment checklist
**Warning signs:** Donations not attributed to correct fund

**Required plugin settings:**
- Master Campaign ID: `764752` (production)
- Master Component ID: `CngmDfcvOorpIS4KOTO4H` (production)

### Pitfall 4: Forgetting Theme Files
**What goes wrong:** Plugin deployed but donation embed doesn't appear
**Why it happens:** Theme files (fund-form.php, archive-funds.php) not deployed
**How to avoid:** Deployment checklist includes both plugin AND theme files
**Warning signs:** Fund pages show old Acceptiva form

## Code Examples

Verified patterns from official sources:

### Displaying Donation Totals in Meta Box (New Code Needed)

The only new code needed for Phase 8 is adding donation totals to the meta box:

```php
// Add to render_sync_meta_box() in class-admin-ui.php
// After the "Last Source" paragraph, add:

// Get inbound sync data
$donation_total = get_post_meta($post->ID, '_gofundme_donation_total', true);
$donor_count = get_post_meta($post->ID, '_gofundme_donor_count', true);
$goal_progress = get_post_meta($post->ID, '_gofundme_goal_progress', true);
$last_inbound_sync = get_post_meta($post->ID, '_gofundme_last_inbound_sync', true);

// Display donation totals
if ($donation_total || $donor_count) {
    ?>
    <hr style="margin: 12px 0;">
    <p>
        <strong>Donation Total:</strong><br>
        <?php echo $donation_total ? '$' . number_format((float) $donation_total, 2) : '<em>$0.00</em>'; ?>
    </p>
    <p>
        <strong>Donor Count:</strong><br>
        <?php echo $donor_count ? intval($donor_count) : '<em>0</em>'; ?>
    </p>
    <?php if ($fundraising_goal && $goal_progress): ?>
    <p>
        <strong>Goal Progress:</strong><br>
        <?php echo number_format((float) $goal_progress, 1); ?>%
    </p>
    <?php endif; ?>
    <p>
        <strong>Last Inbound Sync:</strong><br>
        <?php echo $last_inbound_sync ? esc_html($last_inbound_sync) : '<em>Never</em>'; ?>
    </p>
    <?php
}
```

### DELETE API Call (Already Implemented)

```php
// Source: includes/class-api-client.php lines 245-247
public function delete_designation($designation_id): array {
    return $this->request('DELETE', "/designations/{$designation_id}");
}
```

HTTP 204 response handled correctly in `request()` method (lines 204-210).

### Production rsync Deployment

```bash
# Plugin deployment
rsync -avz --exclude='.git' --exclude='.planning' --exclude='*.zip' \
  /Users/chadmacbook/projects/fcg/ \
  frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/plugins/fcg-gofundme-sync/

# Theme files deployment
rsync -avz fund-form.php archive-funds.php \
  frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/themes/developer/
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Per-fund campaigns | Single master campaign | 2026-01-28 | Architecture pivot complete |
| Modal donation popups | Direct fund page links | 2026-01-29 | Classy SDK incompatibility workaround |
| Custom admin UI | WordPress native meta boxes | Project inception | Standard WP approach |

**Deprecated/outdated:**
- Campaign creation/duplication code: Removed in Phase 5
- Modal popup functionality: Disabled in Phase 7, planned for Phase 9 Classy button link

## Open Questions

Things that couldn't be fully resolved:

1. **Inbound sync for master campaign architecture**
   - What we know: Legacy per-fund campaign inbound sync exists but is inactive
   - What's unclear: Whether `poll_campaigns()` should be updated for master campaign
   - Recommendation: Document as Phase 9 enhancement, not MVP blocker

2. **Default designation overwrite on linking**
   - What we know: `update_campaign()` API sets each new designation as campaign default
   - What's unclear: Long-term impact on user experience
   - Recommendation: Monitor, document manual reset workaround (per 06-02-SUMMARY.md)

## Sources

### Primary (HIGH confidence)
- `/Users/chadmacbook/projects/fcg/includes/class-admin-ui.php` - Current admin UI implementation
- `/Users/chadmacbook/projects/fcg/includes/class-sync-handler.php` - DELETE sync implementation
- `/Users/chadmacbook/projects/fcg/includes/class-api-client.php` - API methods
- `/Users/chadmacbook/projects/fcg/docs/production-deployment-checklist.md` - Deployment docs

### Secondary (MEDIUM confidence)
- [WordPress Meta Box Documentation](https://developer.wordpress.org/plugins/metadata/custom-meta-boxes/)
- [WP Engine Development Best Practices](https://wpengine.com/support/development-workflow-best-practices/)

### Tertiary (LOW confidence)
- General WordPress admin UI patterns from WebSearch (verified against codebase)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No new dependencies, all patterns in codebase
- Architecture: HIGH - Direct codebase analysis
- Pitfalls: HIGH - Based on project history and prior phase learnings

**Research date:** 2026-01-29
**Valid until:** Stable - Admin UI patterns don't change frequently

## Implementation Summary

### What Already Exists (No Changes Needed)
1. Meta box with designation ID, clickable link to Classy admin
2. Last sync timestamp display
3. Manual "Sync Now" button with AJAX
4. Sync status column in funds list table
5. Settings page for master campaign configuration
6. DELETE endpoint implementation
7. Production deployment checklist documentation

### What Needs Implementation (ADMN-02 Only)
1. Add donation totals display to meta box (donation total, donor count, goal progress)
2. Add last inbound sync timestamp to meta box

### What Needs Verification
1. DELETE endpoint removes designation from Classy entirely (not just deactivates)
2. Production credentials work correctly
3. Theme files deploy without issues

### What Needs User Action (Not Claude)
1. Set WP Engine environment variables for production
2. Execute production deployment commands
3. Configure plugin settings in production WordPress admin
4. Verify production Classy dashboard

---

**Phase 8 is primarily a verification and minor enhancement phase, not a major development effort.**
