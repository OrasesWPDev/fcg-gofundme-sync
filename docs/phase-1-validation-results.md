# Phase 1: Validation Results

**Date:** 2026-01-14
**Environment:** WP Engine Staging (frederickc2stg) with Classy Sandbox API
**Tester:** Claude Code + Chad Diaz

---

## Test Results

| Test | Action | Expected Result | Actual Result | Status |
|------|--------|-----------------|---------------|--------|
| 4.1 | Create fund (publish) | Designation created | Designation ID 1894667 created with external_reference_id=13759 | PASS |
| 4.2 | Update fund title | Designation updated | Name updated to "Test Fund- Updated for 4.2" | PASS |
| 4.3 | Change fund to draft | is_active = false | is_active changed to false | PASS |
| 4.4 | Trash fund | is_active = false | is_active remains false | PASS |
| 4.5 | Restore from trash + Publish | is_active = true | is_active changed to true after publish | PASS |
| 4.6 | Permanently delete | Designation deleted | HTTP 404 - designation no longer exists | PASS |

**Overall Result: ALL TESTS PASS**

---

## Findings

### 1. Restore from Trash Behavior

**Observation:** When restoring a fund from trash, the `is_active` status depends on the post's restored status, not the restore action itself.

**Technical Details:**
- WordPress restores posts to their pre-trash status (e.g., draft)
- Two hooks fire: `on_untrash_fund()` sets `is_active = true`, then `on_status_change()` sets `is_active` based on publish status
- Final result: If restored to draft, `is_active = false`; if restored to publish, `is_active = true`

**Conclusion:** This is correct behavior - a draft fund should not be active in GoFundMe Pro.

### 2. API Integration Working

- OAuth2 token acquisition: Working
- Token caching via transients: Working
- All CRUD operations: Working
- Error handling: Not tested (no errors occurred)

### 3. Post Meta Storage

- `_gofundme_designation_id`: Stored correctly on designation creation
- `_gofundme_last_sync`: Updated on each sync operation
- `external_reference_id`: WordPress post ID stored in Classy designation

---

## Environment Configuration

### Staging Credentials Setup

Credentials configured via mu-plugin at:
```
wp-content/mu-plugins/fcg-gofundme-credentials.php
```

**Note:** This file contains sensitive credentials and must NOT be committed to version control.

### Required Constants

| Constant | Purpose |
|----------|---------|
| GOFUNDME_CLIENT_ID | OAuth2 Client ID |
| GOFUNDME_CLIENT_SECRET | OAuth2 Client Secret |
| GOFUNDME_ORG_ID | Classy Organization ID |

---

## Code Changes Validated

The following local changes were deployed and tested:

1. **fcg-gofundme-sync.php**
   - Added `fcg_gfm_has_credential()` helper function
   - Updated credential checks to support environment variables
   - Updated admin notices to reference WP Engine User Portal

2. **includes/class-api-client.php**
   - Added `get_credential()` private method
   - Priority: Environment variables > PHP constants

---

## Next Steps

- [x] Phase 1: Validation - COMPLETE
- [ ] Commit validated changes to repository
- [ ] Phase 2: Polling Infrastructure
- [ ] Phase 3: Incoming Sync Logic
- [ ] Phase 4: Conflict Detection
- [ ] Phase 5: Admin UI
- [ ] Phase 6: Error Handling
