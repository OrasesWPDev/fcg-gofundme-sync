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
| C1 | Campaign API Methods | ✅ Complete (`008aef9`) - 5 CRUD methods, meta constants, v2.0.0 |

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

---

## Agent Architecture

Phase execution uses a hierarchical agent pattern with **hook-enforced tool restrictions**.

### Agent Hierarchy

```
Main Agent
    ↓
    Creates .claude/orchestrator-mode marker
    ↓
Task(general-purpose) with orchestrator instructions
    ↓                   (Edit/Write/Bash BLOCKED by hook)
    ├── Task(dev-agent)      →  Full tools (implements code)
    ├── Task(testing-agent)  →  Read-only (reviews code)
    └── Task(deploy-agent)   →  Bash/SSH (commits/deploys)
    ↓
Main Agent deletes marker
```

### How Enforcement Works

**PreToolUse Hook:** `.claude/hooks/enforce-orchestrator-restrictions.py`

When `.claude/orchestrator-mode` marker file exists:
- Edit, Write, Bash tools are **blocked** with error message
- Orchestrator is forced to delegate via Task tool

### Agent Definitions

Located in `.claude/agents/`:

| Agent | Intended Tools | Responsibility |
|-------|----------------|----------------|
| `orchestrator` | Read, Glob, Grep, Task | Coordinate phases, delegate work |
| `dev-agent` | Read, Write, Edit, Bash | Implement code changes |
| `testing-agent` | Read, Bash | Code review, syntax checks |
| `deploy-agent` | Read, Bash | Git commits, rsync, SSH |

### How Main Agent Invokes Orchestrator

```bash
# 1. Create marker to activate restrictions
touch .claude/orchestrator-mode

# 2. Spawn orchestrator (uses general-purpose but hook blocks tools)
Task(general-purpose) with:
  "Execute Phase X from docs/phase-X-implementation-plan.md
   Follow the orchestrator workflow in .claude/agents/orchestrator.md"

# 3. Wait for completion

# 4. Delete marker
rm .claude/orchestrator-mode
```

### Why Separation Matters

**The same agent should NOT write and test code.**

- dev-agent: Implements features (may have blind spots)
- testing-agent: Reviews objectively (fresh perspective)
- deploy-agent: Commits only after approval

This prevents bias and ensures quality.
