=== FCG GoFundMe Pro Sync ===
Contributors: orases
Tags: gofundme, classy, donations, sync, api, designations
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically syncs WordPress funds with GoFundMe Pro (Classy) designations via API.

== Description ==

This plugin provides automatic synchronization between the WordPress "funds"
custom post type and GoFundMe Pro (Classy) designations using a single master campaign.

**Architecture (v2.3.0):**

All designations are linked to ONE master campaign configured in plugin settings.
The frontend embed uses `?designation={id}` to pre-select the correct fund.

**Outbound Sync (WordPress to Classy):**

* Creates a designation when a fund is published
* Links designation to the master campaign
* Updates the designation when a fund is modified
* Deactivates designation when fund is trashed or set to draft
* Reactivates designation when fund is restored
* Permanently deletes designation when fund is permanently deleted

**Inbound Sync (Classy to WordPress):**

* Polls donation totals every 15 minutes
* Calculates and stores goal progress percentage
* Updates post meta without triggering outbound sync

**Admin Features:**

* Sync status column in funds list
* Meta box showing designation ID and sync timestamps
* Manual sync trigger for individual funds
* WP-CLI commands for operations

== Installation ==

1. Upload the `fcg-gofundme-sync` folder to `/wp-content/plugins/`
2. Set environment variables (see Configuration below)
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Create a master campaign in Classy UI
5. Configure Master Campaign ID and Component ID in Settings > GoFundMe Pro Sync

== Configuration ==

Set these environment variables (WP Engine) or wp-config.php constants:

`
GOFUNDME_CLIENT_ID     - OAuth2 Client ID from Classy
GOFUNDME_CLIENT_SECRET - OAuth2 Client Secret from Classy
GOFUNDME_ORG_ID        - Your organization ID
`

Get API credentials from your Classy admin panel.

**Plugin Settings:**

* Master Campaign ID - The Classy campaign that contains all designations
* Master Component ID - The embed component ID for frontend integration

== WP-CLI Commands ==

`
wp fcg-sync pull [--fund_id=<id>]     # Pull data from Classy
wp fcg-sync push [--fund_id=<id>]     # Push data to Classy
wp fcg-sync status [--fund_id=<id>]   # Show sync status
wp fcg-sync conflicts                  # List sync conflicts
wp fcg-sync retry                      # Retry failed syncs
`

== Sync Behavior ==

**On Fund Publish:**
- Creates designation in Classy
- Links designation to master campaign via API
- Stores designation ID in post meta

**On Fund Update:**
- Updates designation with new title/description

**On Fund Draft/Trash:**
- Deactivates designation (is_active = false)

**On Fund Restore:**
- Reactivates designation (is_active = true)

**On Fund Permanent Delete:**
- Permanently deletes designation from Classy

== Logging ==

When `WP_DEBUG` is enabled, the plugin logs sync operations with prefix `[FCG GoFundMe Sync]`.

== Changelog ==

= 2.3.0 =
* Architecture: Single master campaign with all designations
* Added: Master Campaign ID and Component ID settings
* Added: Automatic designation linking to master campaign
* Added: Frontend embed support with designation pre-selection
* Removed: Per-fund campaign duplication
* Removed: Campaign publish/unpublish/deactivate/reactivate workflow
* Simplified: Sync handler now focuses exclusively on designation sync
* Note: Legacy campaign meta remains in database but is no longer used

= 2.2.0 =
* Added inbound sync for donation totals
* Added goal progress calculation
* Inbound sync runs every 15 minutes

= 2.1.0 =
* Fixed draft status handling
* Added campaign ID display in admin meta box

= 2.0.0 =
* Added campaign sync via template duplication (removed in 2.3.0)
* Added sync opt-out ACF field support

= 1.0.0 =
* Initial release
* Designation CRUD sync
* OAuth2 token caching with transients
* ACF field group integration
* Status transition handling

== Upgrade Notice ==

= 2.3.0 =
Major architecture change: Single master campaign replaces per-fund campaigns. Configure Master Campaign ID in settings after upgrade.

= 2.2.0 =
Adds inbound sync - donation totals now poll every 15 minutes.
