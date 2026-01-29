# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

FCG GoFundMe Pro Sync is a WordPress plugin that automatically synchronizes WordPress "funds" custom post type with GoFundMe Pro (Classy) designations via their API.

## Architecture

```
fcg-gofundme-sync.php    # Main plugin file, initialization, admin notices
includes/
  class-api-client.php   # OAuth2 auth + Classy API wrapper (FCG_GFM_API_Client)
  class-sync-handler.php # WordPress hooks + sync logic (FCG_GFM_Sync_Handler)
```

**Key Classes:**
- `FCG_GFM_API_Client`: Handles OAuth2 token acquisition (cached via transients), wraps Classy API endpoints for designations (CRUD operations)
- `FCG_GFM_Sync_Handler`: Hooks into WordPress post lifecycle (`save_post_funds`, `wp_trash_post`, `untrash_post`, `before_delete_post`, `transition_post_status`) to sync funds

**API Details:**
- Base URL: `https://api.classy.org/2.0`
- Token URL: `https://api.classy.org/oauth2/auth`
- Documentation: `https://docs.classy.org/`
- Uses client_credentials OAuth2 flow
- Token cached in transient `gofundme_access_token`

## Configuration

**IMPORTANT:** Never store credentials in this file or the codebase.

Plugin reads credentials from environment variables (recommended) or wp-config.php constants:

| Environment Variable | Description |
|---------------------|-------------|
| `GOFUNDME_CLIENT_ID` | OAuth2 Client ID from GoFundMe Pro |
| `GOFUNDME_CLIENT_SECRET` | OAuth2 Client Secret from GoFundMe Pro |
| `GOFUNDME_ORG_ID` | Organization ID from GoFundMe Pro |

**WP Engine Setup (Recommended):**
1. Log into WP Engine User Portal
2. Navigate to your environment (Staging or Production)
3. Go to "Environment Variables" section
4. Add each variable with the appropriate values for that environment

**Priority:** Environment variables take precedence over wp-config.php constants

## Architecture (v2.3.0+)

**Single Master Campaign Model:**
- One master campaign in Classy contains all designations
- Each WordPress fund maps to one Classy designation
- Designations are linked to the master campaign via `PUT /campaigns/{id}` with `{"designation_id": "{id}"}`
- Frontend embeds use `?designation={id}` parameter to pre-select the correct fund

**Plugin flow:**
1. Fund published in WordPress
2. Plugin creates designation via Classy API
3. Plugin links designation to master campaign (Phase 6)
4. Frontend embed uses `?designation={id}` to show correct fund

**Removed in v2.3.0:**
- Per-fund campaign duplication
- Campaign publish/unpublish/deactivate/reactivate workflow
- Campaign status synchronization

## Requirements

- PHP 7.4+
- WordPress 5.8+
- ACF plugin (optional, for field group integration)

## Post Meta Keys

**Active (designation sync):**
- `_gofundme_designation_id`: Classy designation ID
- `_gofundme_last_sync`: Timestamp of last successful sync

**Inbound sync (donation totals):**
- `_gofundme_donation_total`: Total donations for fund
- `_gofundme_donor_count`: Number of donors
- `_gofundme_goal_progress`: Percentage of goal reached
- `_gofundme_last_inbound_sync`: Timestamp of last inbound sync

**Legacy (orphaned after v2.3.0):**
- `_gofundme_campaign_id`: Per-fund campaign ID (no longer used)
- `_gofundme_campaign_url`: Per-fund campaign URL (no longer used)
- `_gofundme_campaign_status`: Per-fund campaign status (no longer used)

Legacy meta can be cleaned up with WP-CLI if needed.

## Sync Behavior

| WordPress Action | API Action |
|-----------------|------------|
| Publish fund | Create designation, link to master campaign |
| Update fund | Update designation |
| Unpublish/Draft | Set designation `is_active = false` |
| Trash | Set designation `is_active = false` |
| Restore from trash | Set designation `is_active = true` |
| Permanent delete | Delete designation |

**Note:** Campaign duplication was removed in v2.3.0. Designations are now linked to a single master campaign configured in plugin settings.

## Debugging

Enable `WP_DEBUG` to see sync operations logged to PHP error log with prefix `[FCG GoFundMe Sync]`.

## Live Site SSH Access (WP Engine - Production)

- **SSH:** `frederickcount@frederickcount.ssh.wpengine.net`
- **Site Path:** `~/sites/frederickcount`
- **API:** Production GoFundMe Pro credentials (set in WP Engine env vars)

## Staging Environment (WP Engine)

- **SSH:** `frederickc2stg@frederickc2stg.ssh.wpengine.net`
- **Site Path:** `~/sites/frederickc2stg`
- **API:** Sandbox GoFundMe Pro credentials (set in WP Engine env vars)
- **Purpose:** Primary development and testing environment for current phase

## Deployment

**WP Engine Constraints:**
- **SCP is NOT supported** - WP Engine blocks SCP connections
- **rsync IS supported** - Use rsync for all deployments
- **SFTP available** - As fallback via FileZilla/Transmit or WP Engine File Manager

**Deploy to Staging (rsync):**
```bash
rsync -avz --exclude='.git' --exclude='.planning' --exclude='*.zip' \
  /Users/chadmacbook/projects/fcg/ \
  frederickc2stg@frederickc2stg.ssh.wpengine.net:~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync/
```

**Deploy to Production (rsync):**
```bash
rsync -avz --exclude='.git' --exclude='.planning' --exclude='*.zip' \
  /Users/chadmacbook/projects/fcg/ \
  frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/plugins/fcg-gofundme-sync/
```

**Post-Deployment Cleanup:**
Always remove any zip files from the project directory after deployment:
```bash
rm -f /Users/chadmacbook/projects/fcg/*.zip
```

## Local Development Environment

- **Path:** `/Users/chadmacbook/Local Sites/frederick-county-gives/app/public`
- **Platform:** Local by Flywheel
- **Status:** NOT USED for current development phase
- **Note:** All development and testing done on WP Engine Staging with Sandbox API

## Security Guidelines

**NEVER commit or store in this repository:**
- API credentials (client IDs, secrets, tokens)
- Organization IDs
- Any sensitive configuration values

**Credentials are managed via:**
- WP Engine User Portal → Environment Variables (per environment)
- Each environment (staging/production) has its own credentials

## Development Workflow

**IMPORTANT:** Follow this process for ALL code changes:

1. **Pull latest main** - Ensure local main branch is up to date with remote
2. **Create feature branch** - Branch off main for any new work
3. **Make changes** - Implement the requested functionality
4. **Update plugin version** - Bump version in main plugin file header if releasing
5. **Deploy to Staging** - Deploy plugin to WP Engine Staging via SSH for testing with sandbox API
6. **STOP and wait for user approval** - Do NOT push to repo until user confirms testing is complete
7. **Push to repo** - Only after explicit user approval

**Current Phase:** All development and testing on WP Engine Staging (no local environment)
