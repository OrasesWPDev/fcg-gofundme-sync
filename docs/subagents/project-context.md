# FCG GoFundMe Pro Sync - Project Context

**For Subagents:** Read this file to understand the project before implementing tasks.

---

## Overview

**Plugin Name:** FCG GoFundMe Pro Sync
**Purpose:** Bidirectional sync between WordPress "funds" custom post type and GoFundMe Pro (Classy) designations via API.

---

## Architecture

```
fcg-gofundme-sync.php           # Main plugin file, initialization, admin notices
includes/
  class-api-client.php          # OAuth2 auth + Classy API wrapper
  class-sync-handler.php        # WP→GFM sync (outbound on post save/delete)
  class-sync-poller.php         # GFM→WP sync (inbound polling every 15 min)
  class-admin-ui.php            # Admin UI (Phase 5 - planned)
uninstall.php                   # Cleanup on plugin deletion
docs/
  phase-X-implementation-plan.md  # Detailed implementation plans per phase
```

---

## Key Classes

### `FCG_GFM_API_Client`
- OAuth2 client_credentials flow with Classy API
- Token cached in transient `gofundme_access_token` (1 hour TTL)
- Methods: `create_designation()`, `update_designation()`, `delete_designation()`, `get_all_designations()`

### `FCG_GFM_Sync_Handler`
- Hooks into WordPress post lifecycle
- Outbound sync: WP changes → GFM API
- Checks `FCG_GFM_Sync_Poller::is_syncing_inbound()` to prevent loops

### `FCG_GFM_Sync_Poller`
- WP-Cron job every 15 minutes
- Inbound sync: GFM API → WP posts
- WP-CLI commands: `wp fcg-sync pull`, `wp fcg-sync push`, `wp fcg-sync status`, `wp fcg-sync conflicts`, `wp fcg-sync retry`

---

## API Details

| Property | Value |
|----------|-------|
| Base URL | `https://api.classy.org/2.0` |
| Token URL | `https://api.classy.org/oauth2/auth` |
| Auth Flow | OAuth2 client_credentials |
| Docs | `https://docs.classy.org/` |

**Credentials** (from environment variables):
- `GOFUNDME_CLIENT_ID`
- `GOFUNDME_CLIENT_SECRET`
- `GOFUNDME_ORG_ID`

---

## Post Meta Keys

| Key | Purpose | Set By |
|-----|---------|--------|
| `_gofundme_designation_id` | Links WP post to GFM designation | Outbound sync |
| `_gofundme_last_sync` | Timestamp of last sync | Both directions |
| `_gofundme_poll_hash` | MD5 hash for change detection | Inbound sync |
| `_gofundme_sync_source` | Last change origin (`wordpress` or `gofundme`) | Both directions |
| `_gofundme_sync_error` | Last error message (Phase 6) | Error handling |
| `_gofundme_sync_attempts` | Failed attempt count (Phase 6) | Error handling |

---

## Sync Behavior

| WordPress Action | GFM API Action |
|-----------------|----------------|
| Publish fund | Create designation |
| Update fund | Update designation |
| Unpublish/Draft | Set `is_active = false` |
| Trash | Set `is_active = false` |
| Restore + Publish | Set `is_active = true` |
| Permanent delete | Delete designation |

**Conflict Resolution:** WordPress wins. If WP modified after last sync, GFM changes are skipped.

---

## Phase Status

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Validation | ✅ Complete (`c55c8c3`) |
| 2 | Polling Infrastructure | ✅ Complete (`fbbeb78`) |
| 3 | Incoming Sync Logic | ✅ Complete (`a4e5183`) |
| 4 | Conflict Detection | ✅ Complete (`3fcf0de`) |
| 5 | Admin UI | ✅ Complete (`b020027`) |
| 6 | Error Handling | ✅ Complete (`5b293ca`) |
| C0 | Fix Designations | ✅ Complete (`341dc63`) - Added push command, synced 855 funds |

---

## Environments

**Staging (development/testing):**
- SSH: `frederickc2stg@frederickc2stg.ssh.wpengine.net`
- Path: `~/sites/frederickc2stg`
- API: Classy Sandbox credentials

**Production:**
- SSH: `frederickcount@frederickcount.ssh.wpengine.net`
- Path: `~/sites/frederickcount`
- API: Production Classy credentials
