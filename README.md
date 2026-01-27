# FCG GoFundMe Pro Sync

A WordPress plugin that synchronizes the "funds" custom post type with GoFundMe Pro (Classy) designations and campaigns via their API.

**Version:** 2.2.0
**Requires:** WordPress 5.8+ | PHP 7.4+
**License:** GPLv2 or later

## Current Status

| Phase | Description | Status |
|-------|-------------|--------|
| 1. Configuration | Template campaign settings | Complete |
| 2. Campaign Push Sync | Create/update campaigns on publish | Complete |
| 3. Campaign Status Management | Draft/trash status sync | Complete |
| 4. Inbound Sync | Poll donation data from Classy | Complete |
| 5. Bulk Migration | WP-CLI tool for existing funds | **Blocked** |
| 6. Admin UI | Enhanced admin display | Not Started |
| 7. Frontend Embed | Classy donation embed | Not Started |

### Phase 5 Blocker

Classy's public API endpoints (`duplicateCampaign`, `publishCampaign`) do not properly support Studio campaign types. API-created campaigns appear "Published" in the dashboard but the Design and Settings tabs show errors. Awaiting response from Classy support for a path forward.

## Features

### Outbound Sync (WordPress → Classy)

**Designation Sync:**
- Creates designation when fund is published
- Updates designation when fund is modified
- Deactivates designation when fund is trashed/unpublished
- Reactivates designation when fund is restored
- Deletes designation on permanent delete

**Campaign Sync:**
- Creates campaign via template duplication when fund is published
- Updates campaign name, goal, and overview on fund update
- Unpublishes campaign when fund is set to draft
- Deactivates campaign when fund is trashed
- Reactivates and publishes campaign when fund is restored

### Inbound Sync (Classy → WordPress)

Polls Classy every 15 minutes to fetch:
- Total donations and donor count
- Campaign status (active/unpublished/deactivated)
- Goal progress percentage

### Admin Features

- Sync status column in funds list
- Meta box showing designation ID, campaign ID, and sync timestamps
- Manual sync trigger for individual funds
- WP-CLI commands for operations

### WP-CLI Commands

```bash
wp fcg-sync pull [--fund_id=<id>]     # Pull data from Classy
wp fcg-sync push [--fund_id=<id>]     # Push data to Classy
wp fcg-sync status [--fund_id=<id>]   # Show sync status
wp fcg-sync conflicts                  # List sync conflicts
wp fcg-sync retry                      # Retry failed syncs
```

## Configuration

The plugin reads credentials from environment variables (recommended) or `wp-config.php` constants:

| Variable | Description |
|----------|-------------|
| `GOFUNDME_CLIENT_ID` | OAuth2 Client ID |
| `GOFUNDME_CLIENT_SECRET` | OAuth2 Client Secret |
| `GOFUNDME_ORG_ID` | Organization ID |

**WP Engine Setup:** Add variables in User Portal → Environment Variables

## Post Meta Keys

| Key | Description |
|-----|-------------|
| `_gofundme_designation_id` | Classy designation ID |
| `_gofundme_campaign_id` | Classy campaign ID |
| `_gofundme_campaign_url` | Campaign public URL |
| `_gofundme_donation_total` | Total gross donations |
| `_gofundme_donor_count` | Number of donors |
| `_gofundme_goal_progress` | Percentage toward goal |
| `_gofundme_campaign_status` | active/unpublished/deactivated |
| `_gofundme_last_sync` | Last outbound sync timestamp |
| `_gofundme_last_inbound_sync` | Last inbound sync timestamp |

## Architecture

```
fcg-gofundme-sync.php         # Main plugin file
includes/
  class-api-client.php        # OAuth2 + Classy API wrapper
  class-sync-handler.php      # WordPress hooks + outbound sync
  class-sync-poller.php       # WP-Cron inbound sync
  class-admin-ui.php          # Admin columns, meta box, settings
assets/
  js/admin.js                 # Admin UI interactions
```

## Requirements

- WordPress 5.8+
- PHP 7.4+
- ACF plugin (for fundraising_goal field)
- WP Engine hosting (for environment variables)
- Template campaign configured in Classy

## Debugging

Enable `WP_DEBUG` to see sync operations logged with prefix `[FCG GoFundMe Sync]`.

## Changelog

### 2.2.0
- Added inbound sync for donation totals and campaign status
- Polling runs every 15 minutes via WP-Cron

### 2.1.0
- Fixed draft status to call unpublish (not deactivate)
- Added campaign ID display in admin meta box
- Fixed deactivated campaign restore sequence

### 2.0.0
- Added campaign sync via template duplication
- Campaign create/update/unpublish/deactivate/reactivate
- Added sync opt-out ACF field support
- Added template campaign validation

### 1.0.0
- Initial release
- Designation CRUD sync
- OAuth2 token caching
- Status transition handling

## License

GPLv2 or later - https://www.gnu.org/licenses/gpl-2.0.html
