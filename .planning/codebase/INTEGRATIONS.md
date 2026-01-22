# External Integrations

**Analysis Date:** 2026-01-22

## APIs & External Services

**Classy (GoFundMe Pro):**
- Service: GoFundMe Pro API (Classy platform)
- Documentation: https://docs.classy.org/
- What it's used for: Syncing WordPress "funds" custom post type with Classy designations and campaigns
  - SDK/Client: Custom wrapper class `FCG_GFM_API_Client` in `includes/class-api-client.php`
  - Auth: OAuth2 client credentials flow
  - Env vars: `GOFUNDME_CLIENT_ID`, `GOFUNDME_CLIENT_SECRET`, `GOFUNDME_ORG_ID`
  - Base URL: `https://api.classy.org/2.0`
  - Token URL: `https://api.classy.org/oauth2/auth`

**Classy API Endpoints:**

Designations (sync-primary):
- `POST /organizations/{org_id}/designations` - Create designation from fund
- `PUT /designations/{id}` - Update designation (name, description, is_active)
- `DELETE /designations/{id}` - Permanent delete
- `GET /designations/{id}` - Fetch single designation
- `GET /organizations/{org_id}/designations?page={page}&per_page={per_page}` - List all with pagination

Campaigns (parallel to designations):
- `POST /organizations/{org_id}/campaigns` - Create campaign from fund
- `PUT /campaigns/{id}` - Update campaign (name, goal, overview)
- `GET /campaigns/{id}` - Fetch single campaign
- `GET /organizations/{org_id}/campaigns?page={page}&per_page={per_page}` - List all with pagination
- `POST /campaigns/{id}/deactivate` - Deactivate campaign (preserve donation history)

## Data Storage

**Primary Database:**
- WordPress Post Meta (wp_postmeta table)
  - Posts with post_type='funds'
  - Stores designation/campaign IDs and sync metadata
  - Key relationships:
    - Post ID → `_gofundme_designation_id` (Classy designation ID)
    - Post ID → `_gofundme_campaign_id` (Classy campaign ID)
    - Post ID → `_gofundme_campaign_url` (Public campaign URL)
    - Post ID → `_gofundme_last_sync` (datetime of last sync)
    - Post ID → `_gofundme_poll_hash` (MD5 for change detection)
    - Post ID → `_gofundme_sync_source` ('wordpress' or 'gofundme')
    - Post ID → `_gofundme_sync_error` (error message if failed)
    - Post ID → `_gofundme_sync_attempts` (retry count)

**WordPress Options (wp_options table):**
  - `fcg_gfm_last_poll` - Last polling timestamp
  - `fcg_gfm_poll_enabled` - Boolean to enable/disable polling
  - `fcg_gfm_poll_interval` - Polling interval (default 900 sec)
  - `fcg_gfm_conflict_log` - Array of conflict records (last 100)

**Client:**
- WordPress native functions (`get_post_meta()`, `update_post_meta()`, `get_option()`, `update_option()`)
- No ORM or data abstraction layer

## File Storage

**Not used.** Plugin operates entirely in WordPress database and external API. No file uploads or storage.

## Caching

**WordPress Transients:**
- `gofundme_access_token` - OAuth2 access token
  - TTL: Classy API-provided `expires_in` minus 5 minutes for safety (minimum 60 seconds)
  - Set in: `FCG_GFM_API_Client::get_access_token()` line 142
  - Used in: `FCG_GFM_API_Client::request()` line 111

- `fcg_gfm_syncing_inbound` - Inbound sync flag (prevents save_post hook loops)
  - TTL: 30 seconds
  - Set in: `FCG_GFM_Sync_Poller::apply_designation_to_post()` line 911
  - Read in: `FCG_GFM_Sync_Handler::on_save_fund()` line 88

No page caching or object caching beyond these two transients.

## Authentication & Identity

**OAuth2 (Classy):**
- Flow: Client Credentials (no user authentication)
- Implementation: `FCG_GFM_API_Client::get_access_token()`
- Endpoint: `https://api.classy.org/oauth2/auth`
- Request body: `grant_type=client_credentials&client_id={id}&client_secret={secret}`
- Token response includes `access_token` and `expires_in` (seconds)
- Used in all API requests via Authorization header: `Bearer {token}`
- Cached in transient with automatic refresh

**Credentials Source:**
- Environment variables (WP Engine User Portal) - recommended
- PHP constants in wp-config.php - fallback
- Validation: `FCG_GFM_API_Client::is_configured()` checks all three required

## Monitoring & Observability

**Error Tracking:**
- PHP error log (file-based)
- Enabled by WordPress `WP_DEBUG` constant
- Plugin prefix: `[FCG GoFundMe Sync]`
- Logged via `error_log()` in:
  - `FCG_GFM_API_Client::log_error()` - API failures
  - `FCG_GFM_Sync_Handler::log_info()` / `log_error()` - Sync operations
  - `FCG_GFM_Sync_Poller::log()` - Polling events

**Logs Captured:**
- Token request failures
- API HTTP errors (4xx, 5xx)
- Designation/campaign CRUD failures
- Sync errors and retries
- Conflict detection and resolution
- Polling statistics

**Admin Dashboard:**
- "Sync Status" column on Funds list table (shows synced/pending/error states)
- Meta box on fund edit screen showing:
  - Designation ID (linked to Classy admin)
  - Last sync timestamp
  - Sync source (WordPress vs GoFundMe)
  - Error message if failed
  - "Sync Now" button
- Settings page at: Funds → Sync Settings
  - Shows last poll time
  - Auto-polling toggle
  - Polling interval selection
  - Recent conflicts table
  - Admin notice for posts with sync errors

## CI/CD & Deployment

**Hosting:**
- WP Engine (managed WordPress hosting)
- Staging environment: `frederickc2stg.ssh.wpengine.net`
- Production environment: `frederickcount.ssh.wpengine.net`

**Deployment Method:**
- SSH access to WP Engine sites
- Manual plugin updates via upload
- Environment variables set per site in WP Engine User Portal

**CI Pipeline:**
- None detected. Manual testing and deployment process.
- Development flow: local → WP-Engine Staging (with Sandbox API) → WP-Engine Production (with Live API)

**Version Management:**
- Manual version bumps in plugin file header (e.g., Version: 2.1.0)
- No automated release process

## Environment Configuration

**Required Environment Variables:**

| Variable | Purpose | Where Set |
|----------|---------|-----------|
| `GOFUNDME_CLIENT_ID` | OAuth2 client ID | WP Engine User Portal / wp-config.php |
| `GOFUNDME_CLIENT_SECRET` | OAuth2 client secret | WP Engine User Portal / wp-config.php |
| `GOFUNDME_ORG_ID` | Classy organization ID | WP Engine User Portal / wp-config.php |

**Optional Settings (via WordPress admin UI):**
- `fcg_gfm_poll_enabled` - Enable/disable auto-polling (default: true)
- `fcg_gfm_poll_interval` - Polling interval in seconds (default: 900)

**Secrets Location:**
- WP Engine User Portal → Environment Variables section (recommended)
- wp-config.php constants (fallback, less secure)
- Never commit to repository

**Sandbox vs Live:**
- Staging uses Sandbox API credentials (test designations/campaigns)
- Production uses Live API credentials (real donations)

## Webhooks & Callbacks

**Incoming:**
- Not used. Plugin uses polling model instead of webhooks.
- Polls Classy API every 15 minutes via WP-Cron

**Outgoing:**
- None. Plugin makes direct API calls only.
- No webhooks sent to external systems.

**WordPress Hooks Used (internal):**
- `plugins_loaded` - Initialize plugin
- `admin_plugins_loaded` - Load admin classes
- `save_post_funds` - Outbound sync on fund save (priority 20)
- `wp_trash_post` - Deactivate on trash
- `untrash_post` - Reactivate on restore
- `before_delete_post` - Delete designation on permanent delete
- `transition_post_status` - Handle publish/draft status changes
- `cron_schedules` - Register custom 15-minute interval
- `fcg_gofundme_sync_poll` - Polling cron hook
- `manage_funds_posts_columns` - Add admin column
- `manage_funds_posts_custom_column` - Render admin column
- `add_meta_boxes` - Add sync status meta box
- `admin_menu` - Add settings page
- `admin_init` - Register settings
- `admin_notices` - Show error notices
- `wp_ajax_fcg_gfm_sync_now` - Manual sync AJAX
- `admin_enqueue_scripts` - Load admin assets

## Data Flow Patterns

**Outbound Sync (WordPress → Classy):**
```
Fund saved/published in WordPress
  → save_post_funds hook fires
  → Sync_Handler::on_save_fund()
  → Build designation/campaign data from post
  → If designation ID exists: update_designation()
  → Else if published: create_designation()
  → Store returned ID in post meta
  → Update last_sync timestamp
```

**Inbound Sync (Classy → WordPress):**
```
WP-Cron triggers every 15 minutes
  → Sync_Poller::poll()
  → API: get_all_designations() (paginated)
  → For each designation:
    → Find matching WordPress post via external_reference_id
    → Check if designation changed (via hash)
    → If changed and no WP edits since last sync: apply to post
    → If WP edited since last sync: conflict detected (WordPress wins)
    → Record in conflict log
  → Update poll timestamp
```

**Conflict Resolution:**
- When inbound changes conflict with WordPress edits (post modified after last sync)
- Strategy: **WordPress wins** - push WordPress version back to Classy
- Logged in `fcg_gfm_conflict_log` option (last 100 conflicts)
- Prevents clobbering user edits in WordPress

## Data Mapping

**Fund (WordPress) → Designation (Classy):**
- post_title → designation.name (max 127 chars)
- post_excerpt (or content summary) → designation.description
- post_status ('publish') → designation.is_active (true/false)
- Post ID → designation.external_reference_id (string)
- [ACF field] → designation.goal (if present)

**Fund (WordPress) → Campaign (Classy):**
- post_title → campaign.name (max 127 chars)
- post_content → campaign.overview (max 2000 chars)
- [ACF field] → campaign.goal
- post_date → campaign.started_at
- 'crowdfunding' → campaign.type (fixed)
- 'America/New_York' → campaign.timezone_identifier (hardcoded)
- Post ID → campaign.external_reference_id (string)

## API Error Handling

**HTTP Errors:**
- Logged to PHP error log
- Returned to caller as `['success' => false, 'error' => '...', 'http_code' => code]`
- No automatic retries at API client level
- Retry logic handled by `Sync_Poller` at operation level

**Auth Failures:**
- Token request failure: logged and returns false
- Returns `['success' => false, 'error' => 'Failed to obtain access token']`

**Validation:**
- Empty responses checked
- HTTP 204 No Content handled as success for DELETE
- Non-JSON responses handled gracefully

## Rate Limiting

**Not explicitly handled.** Classy API rate limits not documented in codebase. Polling occurs every 15 minutes (safe interval). API requests made synchronously (no queuing).

---

*Integration audit: 2026-01-22*
