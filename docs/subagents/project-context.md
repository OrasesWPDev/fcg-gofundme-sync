# FCG GoFundMe Pro Sync - Project Context

**For Subagents:** Read this file to understand the project before implementing tasks.

---

## Overview

**Plugin Name:** FCG GoFundMe Pro Sync
**Purpose:** Bidirectional sync between WordPress "funds" custom post type and GoFundMe Pro (Classy) **designations AND campaigns** via API.

**Core Value:** When a fund is published in WordPress, both the designation AND campaign are automatically created in Classy with correct settings.

---

## Current Roadmap

**Campaign sync planning and progress is tracked in `.planning/`:**

| File | Purpose |
|------|---------|
| `.planning/PROJECT.md` | Project context and decisions |
| `.planning/REQUIREMENTS.md` | 24 v1 requirements with REQ-IDs |
| `.planning/ROADMAP.md` | 6-phase campaign sync roadmap |
| `.planning/STATE.md` | Current progress and position |
| `.planning/research/` | API research and technical findings |

**Use `/gsd:progress` to check current state and next steps.**

---

## Architecture

```
fcg-gofundme-sync.php           # Main plugin file, initialization, admin notices
includes/
  class-api-client.php          # OAuth2 auth + Classy API wrapper (designations + campaigns)
  class-sync-handler.php        # WP->GFM sync (outbound on post save/delete)
  class-sync-poller.php         # GFM->WP sync (inbound polling every 15 min)
  class-admin-ui.php            # Admin UI for sync status display
uninstall.php                   # Cleanup on plugin deletion
.planning/                      # GSD planning infrastructure (campaign sync roadmap)
docs/
  phase-X-implementation-plan.md  # Historical designation sync plans (COMPLETED)
  subagents/                      # Agent reference documentation
```

---

## Key Classes

### `FCG_GFM_API_Client`
- OAuth2 client_credentials flow with Classy API
- Token cached in transient `gofundme_access_token` (1 hour TTL)
- **Designation methods:** `create_designation()`, `update_designation()`, `delete_designation()`, `get_all_designations()`
- **Campaign methods:** `duplicate_campaign()`, `publish_campaign()`, `unpublish_campaign()`, `deactivate_campaign()`, `reactivate_campaign()`, `get_campaign()`, `update_campaign()`

### `FCG_GFM_Sync_Handler`
- Hooks into WordPress post lifecycle
- Outbound sync: WP changes -> GFM API (both designations and campaigns)
- Checks `FCG_GFM_Sync_Poller::is_syncing_inbound()` to prevent loops

### `FCG_GFM_Sync_Poller`
- WP-Cron job every 15 minutes
- Inbound sync: GFM API -> WP posts
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

**Critical API Note:** POST /campaigns returns 403. Campaign creation MUST use `duplicateCampaign` endpoint from a template campaign.

---

## Post Meta Keys

### Designation Sync (COMPLETED)

| Key | Purpose | Set By |
|-----|---------|--------|
| `_gofundme_designation_id` | Links WP post to GFM designation | Outbound sync |
| `_gofundme_last_sync` | Timestamp of last sync | Both directions |
| `_gofundme_poll_hash` | MD5 hash for change detection | Inbound sync |
| `_gofundme_sync_source` | Last change origin (`wordpress` or `gofundme`) | Both directions |
| `_gofundme_sync_error` | Last error message | Error handling |
| `_gofundme_sync_attempts` | Failed attempt count | Error handling |

### Campaign Sync (IN PROGRESS)

| Key | Purpose | Set By |
|-----|---------|--------|
| `_gofundme_campaign_id` | Links WP post to GFM campaign | Outbound sync |
| `_gofundme_campaign_url` | Public campaign donation page URL | Outbound sync |
| `_gofundme_donation_total` | Total donations from Classy | Inbound sync |
| `_gofundme_campaign_status` | Campaign status (active/unpublished/deactivated) | Inbound sync |
| `_gofundme_goal_progress` | Percentage toward fundraising goal | Inbound sync |

---

## Sync Behavior

### Designation Sync (COMPLETED)

| WordPress Action | GFM API Action |
|-----------------|----------------|
| Publish fund | Create designation |
| Update fund | Update designation |
| Unpublish/Draft | Set `is_active = false` |
| Trash | Set `is_active = false` |
| Restore + Publish | Set `is_active = true` |
| Permanent delete | Delete designation |

### Campaign Sync (IN PROGRESS - See .planning/ROADMAP.md)

| WordPress Action | GFM API Action |
|-----------------|----------------|
| Publish fund | Duplicate + publish campaign from template |
| Update fund | Update campaign name/goal |
| Unpublish/Draft | Unpublish campaign |
| Trash | Deactivate campaign |
| Restore from trash | Reactivate + publish campaign |

**Conflict Resolution:** WordPress wins. If WP modified after last sync, GFM changes are skipped.

---

## Phase History

### Designation Sync (COMPLETED)

These phases implemented bidirectional designation sync and are complete:

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Validation | COMPLETE (`c55c8c3`) |
| 2 | Polling Infrastructure | COMPLETE (`fbbeb78`) |
| 3 | Incoming Sync Logic | COMPLETE (`a4e5183`) |
| 4 | Conflict Detection | COMPLETE (`3fcf0de`) |
| 5 | Admin UI | COMPLETE (`b020027`) |
| 6 | Error Handling | COMPLETE (`5b293ca`) |
| C0 | Fix Designations | COMPLETE (`341dc63`) |
| C1 | Campaign API Methods | COMPLETE (`008aef9`) |

**Implementation plans:** `docs/phase-1-6-implementation-plan.md` (historical reference)

### Campaign Sync (IN PROGRESS)

Campaign sync is tracked in `.planning/ROADMAP.md` with 6 phases:

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Configuration | Not started |
| 2 | Campaign Push Sync | Not started |
| 3 | Campaign Status Management | Not started |
| 4 | Inbound Sync | Not started |
| 5 | Bulk Migration | Not started |
| 6 | Admin UI | Not started |

**Use `/gsd:progress` to check current state.**

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
    |
    Creates .claude/orchestrator-mode marker
    |
Task(orchestrator)
    |                   (Edit/Write/Bash BLOCKED by hook)
    +-- Task(dev-agent)      ->  Full tools (implements code)
    +-- Task(testing-agent)  ->  Read-only (reviews code)
    +-- Task(deploy-agent)   ->  Bash/SSH (commits/deploys)
    |
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

### Why Separation Matters

**The same agent should NOT write and test code.**

- dev-agent: Implements features (may have blind spots)
- testing-agent: Reviews objectively (fresh perspective)
- deploy-agent: Commits only after approval

This prevents bias and ensures quality.

---

*Last updated: 2026-01-22 after GSD initialization*
