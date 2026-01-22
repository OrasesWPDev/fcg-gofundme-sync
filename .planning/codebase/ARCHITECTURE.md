# Architecture

**Analysis Date:** 2026-01-22

## Pattern Overview

**Overall:** WordPress Plugin with Bi-Directional Sync

This is a WordPress plugin using the **WordPress Hook & Filter pattern** combined with a **Bidirectional Data Sync pattern**. The plugin maintains synchronization between WordPress posts (custom post type "funds") and external GoFundMe Pro (Classy) API designations/campaigns through a combination of:
- **Outbound sync**: WordPress post lifecycle hooks trigger API updates
- **Inbound sync**: Polling via WP-Cron detects changes from GoFundMe Pro and applies them to WordPress

**Key Characteristics:**
- Multi-class object-oriented design (4 main service classes)
- Transient-based OAuth2 token caching to reduce API calls
- Conflict detection and resolution (WordPress wins on conflicts)
- Retry logic with exponential backoff for failed syncs
- WP-Cron polling every 15 minutes for inbound changes
- WP-CLI command suite for manual operations
- Admin UI for monitoring and manual sync control

## Layers

**API Layer** (`FCG_GFM_API_Client`):
- Purpose: Encapsulate all HTTP communication with GoFundMe Pro API (Classy API 2.0)
- Location: `includes/class-api-client.php`
- Contains: OAuth2 token management, HTTP request wrapper, endpoint abstractions for designations and campaigns
- Depends on: WordPress HTTP API (`wp_remote_*` functions), WordPress transients for token caching
- Used by: Sync Handler and Sync Poller
- Key Methods: `request()`, `create_designation()`, `update_designation()`, `delete_designation()`, `get_designation()`, `get_all_designations()`, `create_campaign()`, `update_campaign()`, `get_campaign()`, `get_all_campaigns()`, `deactivate_campaign()`

**Outbound Sync Layer** (`FCG_GFM_Sync_Handler`):
- Purpose: Listen to WordPress post lifecycle events and push changes to GoFundMe Pro
- Location: `includes/class-sync-handler.php`
- Contains: Post save, trash, delete, status transition handlers; designation and campaign data building
- Depends on: API Client, WordPress post meta functions, ACF (optional for fields)
- Used by: WordPress hooks system
- Hooks Registered:
  - `save_post_funds` → `on_save_fund()` - Create/update when post saved
  - `wp_trash_post` → `on_trash_fund()` - Soft delete (deactivate)
  - `untrash_post` → `on_untrash_fund()` - Restore (reactivate)
  - `before_delete_post` → `on_delete_fund()` - Hard delete
  - `transition_post_status` → `on_status_change()` - Publish/draft changes

**Inbound Sync Layer** (`FCG_GFM_Sync_Poller`):
- Purpose: Poll GoFundMe Pro periodically for changes and apply them to WordPress
- Location: `includes/class-sync-poller.php`
- Contains: WP-Cron polling logic, conflict resolution, retry mechanism, WP-CLI commands
- Depends on: API Client, WordPress post functions, options/meta functions
- Used by: WP-Cron (15-minute interval), WP-CLI commands
- Key Methods: `poll()`, `cli_pull()`, `cli_push()`, `cli_status()`, `cli_conflicts()`, `cli_retry()`

**Admin UI Layer** (`FCG_GFM_Admin_UI`):
- Purpose: Provide WordPress admin interface for sync status visibility and manual operations
- Location: `includes/class-admin-ui.php`
- Contains: Admin column rendering, meta boxes, settings page, AJAX handlers
- Depends on: WordPress admin functions, admin hooks
- Used by: WordPress admin interface only
- Admin Features: List table column, post edit meta box, settings page, sync status notice, AJAX sync buttons

**Initialization Layer** (Main Plugin File):
- Purpose: Bootstrap plugin, register hooks, check credentials, manage activation/deactivation
- Location: `fcg-gofundme-sync.php`
- Contains: Credential checking, class loading, WP-Cron schedule management, admin notices
- Hooks: `plugins_loaded`, `register_activation_hook`, `register_deactivation_hook`

## Data Flow

**Outbound Sync Flow (WordPress → GoFundMe Pro):**

1. User saves/updates a fund post in WordPress admin or via API
2. `save_post_funds` hook fires → `FCG_GFM_Sync_Handler::on_save_fund()`
3. Sync Handler checks if sync flag is set (prevents recursion during inbound sync)
4. Builds designation data from post: name, description, is_active, external_reference_id (post ID)
5. Builds campaign data separately: name, type, goal, timezone, overview
6. If post already has `_gofundme_designation_id` meta → calls `update_designation()`
7. If post is published and no designation → calls `create_designation()`
8. On success: stores returned designation ID and campaign ID in post meta, updates `_gofundme_last_sync` timestamp
9. Sets `_gofundme_sync_source` to "wordpress"
10. On failure: stores error in `_gofundme_sync_error` meta for retry

**Inbound Sync Flow (GoFundMe Pro → WordPress):**

1. WP-Cron fires `fcg_gofundme_sync_poll` hook every 15 minutes
2. `FCG_GFM_Sync_Poller::poll()` calls `get_all_designations()` from API
3. For each designation returned:
   - Find matching WordPress post via `external_reference_id` (post ID) or post meta lookup
   - Calculate hash of designation data (name, description, is_active, goal)
   - Compare to stored hash in `_gofundme_poll_hash` post meta
   - If no change → skip
   - If changed → check `_gofundme_last_sync` vs post modified time
   - **Conflict Detection:** If post was modified after last sync, WordPress wins (push WP version back to GFM)
   - Otherwise, apply GFM changes to post: title, excerpt (description), post_status
4. Updates `_gofundme_poll_hash`, `_gofundme_sync_source` ("gofundme"), `_gofundme_last_sync`
5. Syncing flag prevents outbound sync from triggering during inbound updates

**Campaign Sync (Parallel to Designation):**

1. Campaign creation happens when fund is published and sent to GFM
2. Campaign ID and URL stored in post meta: `_gofundme_campaign_id`, `_gofundme_campaign_url`
3. Campaign updates happen in parallel with designation updates
4. Campaign deactivation on trash/delete (preserves donation history)
5. Campaign reactivation on untrash

**State Management:**

- **Post Meta Keys (WordPress source of truth for sync state):**
  - `_gofundme_designation_id`: Unique identifier linking to GoFundMe Pro
  - `_gofundme_campaign_id`: Campaign ID in GoFundMe Pro
  - `_gofundme_campaign_url`: Public campaign URL
  - `_gofundme_last_sync`: ISO datetime of most recent successful sync
  - `_gofundme_poll_hash`: MD5 hash for change detection
  - `_gofundme_sync_source`: "wordpress" or "gofundme" - which system performed last change
  - `_gofundme_sync_error`: Error message if sync failed
  - `_gofundme_sync_attempts`: Number of failed sync attempts
  - `_gofundme_sync_last_attempt`: Timestamp of last failed attempt

- **WordPress Options (Plugin-wide state):**
  - `fcg_gfm_last_poll`: Timestamp of last successful poll
  - `fcg_gfm_poll_enabled`: Boolean, enable/disable polling
  - `fcg_gfm_poll_interval`: Seconds between polls (default 900)
  - `fcg_gfm_conflict_log`: Array of recent conflicts (last 100)

- **Transients (Temporary cache):**
  - `gofundme_access_token`: OAuth2 token (expires early for safety)
  - `fcg_gfm_syncing_inbound`: Flag (30 second TTL) to prevent recursive syncs

## Key Abstractions

**FCG_GFM_API_Client:**
- Purpose: Encapsulate all external API communication
- Location: `includes/class-api-client.php`
- Pattern: Service/Client class with private credential management
- Credential loading priority: Environment variables → PHP constants
- Methods operate at API level (not business logic level)
- Response format standardized: `['success' => bool, 'data' => mixed, 'error' => string]`

**FCG_GFM_Sync_Handler:**
- Purpose: Business logic for outbound sync (WordPress → GoFundMe Pro)
- Location: `includes/class-sync-handler.php`
- Pattern: Hook handler class using WordPress actions
- Methods map to WordPress lifecycle events
- Builds data structures by extracting from `WP_Post` object
- ACF integration (optional): reads/writes field groups if ACF present

**FCG_GFM_Sync_Poller:**
- Purpose: Polling, inbound sync, retry logic, and CLI commands
- Location: `includes/class-sync-poller.php`
- Pattern: Service class with static helper methods and both hooks & CLI callbacks
- Change detection: MD5 hash comparison
- Conflict resolution: Timestamp-based (WP modified time > last sync time)
- Retry logic: Exponential backoff (5min, 15min, 45min), max 3 attempts
- CLI Integration: Provides `wp fcg-sync` command suite

**FCG_GFM_Admin_UI:**
- Purpose: Present sync status in WordPress admin
- Location: `includes/class-admin-ui.php`
- Pattern: Admin-only service class using filters and admin-specific hooks
- Renders: Column in funds list table, meta box on edit screen, settings page, notices
- AJAX: Handles `wp_ajax_fcg_gfm_sync_now` for manual sync trigger

## Entry Points

**Plugin Activation** (`plugins_loaded` hook):
- Location: `fcg-gofundme-sync.php`
- Triggers: On each WordPress page load after all plugins loaded
- Responsibilities:
  1. Check credentials (CLIENT_ID, SECRET, ORG_ID)
  2. If missing: Show admin notice and bail
  3. If present: Instantiate `FCG_GFM_Sync_Handler`, `FCG_GFM_Sync_Poller`, `FCG_GFM_Admin_UI` (if admin)

**Post Lifecycle** (Multiple hooks):
- Handlers in `FCG_GFM_Sync_Handler`
- Triggers: User saves/publishes/trashes/deletes a fund post
- Responsibility: Build data and call API methods

**Polling** (WP-Cron):
- Hook: `fcg_gofundme_sync_poll`
- Interval: Every 15 minutes (custom schedule `fcg_gfm_15min`)
- Handler: `FCG_GFM_Sync_Poller::poll()`
- Responsibility: Fetch designations from GoFundMe Pro and apply changes

**WP-CLI Commands:**
- Handler: `FCG_GFM_Sync_Poller` methods
- Commands: `wp fcg-sync pull`, `wp fcg-sync push`, `wp fcg-sync status`, `wp fcg-sync conflicts`, `wp fcg-sync retry`
- Responsibility: Allow manual control of sync operations with dry-run support

**AJAX Sync** (Admin):
- Action: `wp_ajax_fcg_gfm_sync_now`
- Handler: `FCG_GFM_Admin_UI::ajax_sync_now()`
- Responsibility: Trigger immediate sync for single post or all posts

## Error Handling

**Strategy:** Graceful degradation with error logging and retry mechanism

**Patterns:**

1. **API Errors:**
   - Caught in `FCG_GFM_API_Client::request()`
   - Return standardized error response: `['success' => false, 'error' => string]`
   - Logged to WordPress error log (if WP_DEBUG enabled)
   - Caller must check response['success'] before using response['data']

2. **Sync Errors:**
   - Caught in `FCG_GFM_Sync_Poller::sync_post_with_retry()` try/catch block
   - Stored in post meta `_gofundme_sync_error`
   - Tracked attempts in `_gofundme_sync_attempts`
   - Exponential backoff retry delay: 5min, 15min, 45min
   - Max 3 attempts, then manual intervention required
   - User can force retry with `wp fcg-sync retry --force`

3. **Credential Errors:**
   - Checked at plugin init time in `fcg_gfm_sync_init()`
   - Missing credentials show admin notice
   - Plugin classes check `is_configured()` before attempting operations

4. **Recursion Prevention:**
   - During inbound sync, a transient flag `fcg_gfm_syncing_inbound` is set
   - Outbound sync handler checks this flag and bails if syncing inbound
   - Prevents loop: inbound change → applies to WP post → triggers outbound sync → changes back → loop

## Cross-Cutting Concerns

**Logging:**
- All operations logged to WordPress error log (if WP_DEBUG enabled)
- Prefix: `[FCG GoFundMe Sync]`
- Methods: `log_info()`, `log_error()` in both handler and client
- Logged: API requests, sync operations, conflicts, retries, errors

**Validation:**
- Post type check: Handler only processes 'funds' posts
- Post status checks: Logic differs for 'publish', 'draft', 'trash', 'auto-draft'
- Autosave skip: Handler ignores DOING_AUTOSAVE
- Revision skip: Handler ignores post revisions
- Bulk operation detection: Handler can skip bulk imports
- Nonce validation: Admin AJAX includes wp_nonce_field/check_ajax_referer
- Permission checks: Admin functions check current_user_can()

**Authentication (OAuth2):**
- Client credentials flow with GoFundMe Pro OAuth2 endpoint
- Token URL: `https://api.classy.org/oauth2/auth`
- Token cached in transient with 5-minute early expiration buffer
- New token fetched when transient expires

**Conflict Resolution:**
- Triggered when: Inbound change detected AND WordPress post modified after last sync
- Strategy: **WordPress wins** - push post data back to GoFundMe Pro
- Logged: Conflict recorded to `fcg_gfm_conflict_log` option (kept for last 100)
- Viewable: `wp fcg-sync conflicts` CLI command or admin settings page

**Data Synchronization:**
- Outbound: Post title → Designation name, Post excerpt/content → Description
- Inbound: Designation name → Post title, Description → Post excerpt
- Campaign: Handles separately but linked to designation via post
- Active state: Post published ↔ designation is_active=true
- External reference: Post ID always stored as external_reference_id in GoFundMe Pro

---

*Architecture analysis: 2026-01-22*
