# FCG GoFundMe Pro Sync

A WordPress plugin that synchronizes the "funds" custom post type with GoFundMe Pro (Classy) designations via their API.

**Version:** 2.3.0
**Requires:** WordPress 5.8+ | PHP 7.4+
**License:** GPLv2 or later

## Architecture (v2.3.0)

Single master campaign model:
- ONE master campaign in Classy contains all designations
- Each WordPress fund maps to one Classy designation
- Designations are linked to master campaign via `PUT /campaigns/{id}` with `{"designation_id": "{id}"}`
- Frontend embeds use `?designation={id}` parameter to pre-select the correct fund

```
WordPress Fund → Designation created via API → Linked to Master Campaign
                                                      ↓
                             Frontend: <div classy="{master_id}"> + ?designation={id}
```

## Features

### Outbound Sync (WordPress → Classy)

**Designation Sync:**
- Creates designation when fund is published
- Links designation to master campaign automatically
- Updates designation when fund is modified
- Deactivates designation when fund is trashed/unpublished
- Reactivates designation when fund is restored
- Deletes designation on permanent delete

### Inbound Sync (Classy → WordPress)

Polls Classy every 15 minutes to fetch:
- Total donations and donor count
- Goal progress percentage

### Admin Features

- Sync status column in funds list
- Meta box showing designation ID and sync timestamps
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

**Plugin Settings:**
- Master Campaign ID - The Classy campaign containing all designations
- Master Component ID - The embed component ID for frontend integration

## Post Meta Keys

**Active:**

| Key | Description |
|-----|-------------|
| `_gofundme_designation_id` | Classy designation ID |
| `_gofundme_donation_total` | Total gross donations |
| `_gofundme_donor_count` | Number of donors |
| `_gofundme_goal_progress` | Percentage toward goal |
| `_gofundme_last_sync` | Last outbound sync timestamp |
| `_gofundme_last_inbound_sync` | Last inbound sync timestamp |

**Legacy (orphaned in v2.3.0):**

| Key | Description |
|-----|-------------|
| `_gofundme_campaign_id` | No longer used |
| `_gofundme_campaign_url` | No longer used |
| `_gofundme_campaign_status` | No longer used |

## File Structure

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
- Master campaign configured in Classy

## Debugging

Enable `WP_DEBUG` to see sync operations logged with prefix `[FCG GoFundMe Sync]`.

## Changelog

### 2.3.0
- **Architecture:** Single master campaign with all designations
- **Added:** Master Campaign ID and Component ID settings
- **Added:** Automatic designation linking to master campaign
- **Added:** Frontend embed support with `?designation={id}` pre-selection
- **Removed:** Per-fund campaign duplication
- **Removed:** Campaign publish/unpublish/deactivate/reactivate workflow
- **Simplified:** Sync handler focuses exclusively on designation sync

### 2.2.0
- Added inbound sync for donation totals
- Polling runs every 15 minutes via WP-Cron

### 2.1.0
- Fixed draft status handling
- Added campaign ID display in admin meta box

### 2.0.0
- Added campaign sync via template duplication (removed in 2.3.0)
- Added sync opt-out ACF field support

### 1.0.0
- Initial release
- Designation CRUD sync
- OAuth2 token caching
- Status transition handling

## License

GPLv2 or later - https://www.gnu.org/licenses/gpl-2.0.html
