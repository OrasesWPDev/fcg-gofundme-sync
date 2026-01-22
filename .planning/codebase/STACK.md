# Technology Stack

**Analysis Date:** 2026-01-22

## Languages & Runtime

**Primary:**
- PHP 7.4+ (minimum requirement per plugin header)
- WordPress 5.8+ (minimum requirement per plugin header)

**Current Version:**
- Plugin Version: 2.1.0
- Tested up to WordPress: 6.4

## Framework & Architecture

**Core Framework:**
- WordPress Plugin Framework (custom class-based architecture)
- Object-oriented PHP with type hints (PHP 7.4+)

**Plugin Structure:**
- Main file: `fcg-gofundme-sync.php` (plugin initialization, admin notices, hooks)
- Core classes in `includes/`:
  - `class-api-client.php` - OAuth2 auth + Classy API wrapper
  - `class-sync-handler.php` - WordPress post lifecycle hooks
  - `class-sync-poller.php` - Inbound polling + WP-CLI commands
  - `class-admin-ui.php` - Admin interface and dashboard

## External Libraries & Dependencies

**None declared.** This plugin uses only:
- WordPress core APIs (wp_remote_post, wp_remote_request, transients, post meta, options, hooks)
- PHP standard library (json_encode/decode, mb_* string functions, error_log)
- Optional: ACF (Advanced Custom Fields) - read via get_field() if available

## HTTP Client

**Built-in:**
- Uses WordPress `wp_remote_post()` and `wp_remote_request()` functions (built on cURL)
- No external HTTP library dependency
- Timeout: 30 seconds per request

## Data Persistence

**WordPress Database:**
- Post meta keys:
  - `_gofundme_designation_id` - Classy designation ID
  - `_gofundme_campaign_id` - Classy campaign ID
  - `_gofundme_campaign_url` - Campaign URL
  - `_gofundme_last_sync` - MySQL datetime of last successful sync
  - `_gofundme_poll_hash` - MD5 hash for change detection
  - `_gofundme_sync_source` - 'wordpress' or 'gofundme' (which system made the change)
  - `_gofundme_sync_error` - Error message if sync failed
  - `_gofundme_sync_attempts` - Number of retry attempts
  - `_gofundme_sync_last_attempt` - Timestamp of last retry

- Options (WordPress wp_options table):
  - `fcg_gfm_last_poll` - MySQL datetime of last polling cycle
  - `fcg_gfm_poll_enabled` - Boolean to enable/disable auto-polling
  - `fcg_gfm_poll_interval` - Polling interval in seconds (default 900)
  - `fcg_gfm_conflict_log` - Array of recent sync conflicts (last 100)

**Transients (Cache):**
- `gofundme_access_token` - OAuth2 access token (expires per API response, min 60 sec, max configured expires_in minus 5 minutes for safety)
- `fcg_gfm_syncing_inbound` - Flag during inbound sync to prevent loops (30 sec TTL)

## Configuration

**Environment Variables (Recommended - WP Engine User Portal):**
All credentials support environment variable first, then PHP constants as fallback:

```
GOFUNDME_CLIENT_ID        # OAuth2 Client ID
GOFUNDME_CLIENT_SECRET    # OAuth2 Client Secret
GOFUNDME_ORG_ID           # Classy Organization ID
```

**Priority Resolution:**
1. Environment variables (checked via `getenv()`)
2. PHP constants (checked via `defined()` and `constant()`)
3. Fallback: Plugin initialization fails with admin notices if missing

**Deployment:**
- Set in WP Engine User Portal → Environment Variables section
- Different credentials per environment (Staging uses sandbox API, Production uses live API)
- See `class-api-client.php` lines 59-84 for credential loading logic

**Credential Checking:**
- `fcg_gofundme_has_credential()` function in main plugin file validates presence
- Missing credentials trigger admin notice warnings
- Plugin initialization defers if credentials unavailable

## Cron & Scheduling

**WP-Cron Integration:**
- Uses WordPress native WP-Cron (not system cron, requires site traffic to trigger)
- Custom interval: `fcg_gfm_15min` = 15 minutes (900 seconds)
- Hook: `fcg_gofundme_sync_poll`
- Registered on activation: `fcg_gfm_sync_activate()`
- Unregistered on deactivation: `fcg_gfm_sync_deactivate()`

**Polling Details:**
- Runs every 15 minutes via WP-Cron
- Fetches all designations from Classy API
- Detects changes via MD5 hash comparison
- Applies inbound changes with conflict detection (WordPress wins on conflict)
- Handles orphaned designations and retry logic

## APIs & Integrations

**Classy (GoFundMe Pro) API:**
- Base URL: `https://api.classy.org/2.0`
- Token endpoint: `https://api.classy.org/oauth2/auth`
- OAuth2 flow: `client_credentials`
- API wrapper: `FCG_GFM_API_Client` class
- Endpoints used:
  - POST `/organizations/{org_id}/designations` - Create
  - PUT `/designations/{id}` - Update
  - DELETE `/designations/{id}` - Delete
  - GET `/designations/{id}` - Fetch single
  - GET `/organizations/{org_id}/designations` - List with pagination
  - POST `/organizations/{org_id}/campaigns` - Create campaign
  - PUT `/campaigns/{id}` - Update campaign
  - GET `/campaigns/{id}` - Fetch campaign
  - GET `/organizations/{org_id}/campaigns` - List campaigns
  - POST `/campaigns/{id}/deactivate` - Deactivate campaign

## Command-Line Interface

**WP-CLI Commands:**
Registered via `class-sync-poller.php` if WP_CLI is defined:

```bash
wp fcg-sync pull [--dry-run]              # Pull designations from GFM
wp fcg-sync push [--dry-run] [--update]   # Push funds to GFM
wp fcg-sync status                        # Show sync status
wp fcg-sync conflicts [--limit=N]         # Show recent conflicts
wp fcg-sync retry [--force] [--clear]     # Retry failed syncs
```

## Logging

**Debug Mode:**
- Enabled by WordPress `WP_DEBUG` constant
- All logs go to PHP error log with prefix `[FCG GoFundMe Sync]`
- Logs sync operations, errors, retries, conflicts
- Implements via `log_info()` and `log_error()` methods in handler and poller classes

**Logging Locations:**
- `FCG_GFM_API_Client::log_error()` - API request failures
- `FCG_GFM_Sync_Handler::log_info()` / `log_error()` - Outbound sync events
- `FCG_GFM_Sync_Poller::log()` - Polling and inbound sync events

## Development Requirements

**Server:**
- PHP 7.4+
- WordPress 5.8+
- Optional: ACF plugin for field group management
- WP-Engine for deployment (SSH access for staging/production)

**Local Development:**
- Local by Flywheel (not actively used per CLAUDE.md)
- WP-Engine Staging with Sandbox API credentials (primary dev environment)

**Deployment:**
- WP-Engine User Portal for credential management
- SSH access to staging/production sites
- Version bump in plugin header before release

## Version Control

**Current:**
- Plugin Version: 2.1.0 (in fcg-gofundme-sync.php header and constant `FCG_GFM_SYNC_VERSION`)
- GitHub repository main branch

**Change Protocol:**
1. Pull latest main
2. Create feature branch
3. Implement and test on WP-Engine Staging
4. Await user approval before pushing to repo
5. Update version in plugin header if releasing

---

*Stack analysis: 2026-01-22*
