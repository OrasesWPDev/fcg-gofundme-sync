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
- Uses client_credentials OAuth2 flow
- Token cached in transient `gofundme_access_token`

## Configuration

Plugin requires these constants in wp-config.php:
```php
define('GOFUNDME_CLIENT_ID', 'your_client_id');
define('GOFUNDME_CLIENT_SECRET', 'your_client_secret');
define('GOFUNDME_ORG_ID', 'your_org_id');
```

## Requirements

- PHP 7.4+
- WordPress 5.8+
- ACF plugin (optional, for field group integration)

## Post Meta Keys

- `_gofundme_designation_id`: Stores the Classy designation ID
- `_gofundme_last_sync`: Timestamp of last successful sync

## Sync Behavior

| WordPress Action | API Action |
|-----------------|------------|
| Publish fund | Create designation |
| Update fund | Update designation |
| Unpublish/Draft | Set `is_active = false` |
| Trash | Set `is_active = false` |
| Restore from trash | Set `is_active = true` |
| Permanent delete | Delete designation |

## Debugging

Enable `WP_DEBUG` to see sync operations logged to PHP error log with prefix `[FCG GoFundMe Sync]`.
