=== FCG GoFundMe Pro Sync ===
Contributors: orases
Tags: gofundme, classy, donations, sync, api, campaigns
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically syncs WordPress funds with GoFundMe Pro (Classy) designations and campaigns via API.

== Description ==

This plugin provides automatic bi-directional synchronization between the WordPress "funds"
custom post type and GoFundMe Pro (Classy) designations and campaigns.

**Outbound Sync (WordPress to Classy):**

* Creates a designation and campaign when a fund is published
* Updates the designation and campaign when a fund is modified
* Unpublishes campaign when fund is set to draft
* Deactivates designation/campaign when fund is trashed
* Reactivates and publishes when fund is restored
* Permanently deletes designation when fund is permanently deleted

**Inbound Sync (Classy to WordPress):**

* Polls donation totals every 15 minutes
* Fetches campaign status (active/unpublished/deactivated)
* Calculates and stores goal progress percentage
* Updates post meta without triggering outbound sync

**Admin Features:**

* Sync status column in funds list
* Meta box showing IDs and sync timestamps
* Manual sync trigger for individual funds
* WP-CLI commands for operations

== Installation ==

1. Upload the `fcg-gofundme-sync` folder to `/wp-content/plugins/`
2. Set environment variables (see Configuration below)
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure template campaign ID in Settings > GoFundMe Pro Sync

== Configuration ==

Set these environment variables (WP Engine) or wp-config.php constants:

`
GOFUNDME_CLIENT_ID     - OAuth2 Client ID from Classy
GOFUNDME_CLIENT_SECRET - OAuth2 Client Secret from Classy
GOFUNDME_ORG_ID        - Your organization ID
`

Get API credentials from your Classy admin panel.

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
- Creates campaign via template duplication
- Publishes campaign
- Stores IDs and URLs in post meta

**On Fund Update:**
- Updates designation with new title/description
- Updates campaign name, goal, and overview

**On Fund Draft:**
- Unpublishes campaign (remains editable in Classy)
- Designation unchanged

**On Fund Trash:**
- Deactivates designation (is_active = false)
- Deactivates campaign

**On Fund Restore:**
- Reactivates designation
- Reactivates and publishes campaign

**On Fund Permanent Delete:**
- Permanently deletes designation
- Campaign deactivated (preserved for donation history)

== Logging ==

When `WP_DEBUG` is enabled, the plugin logs sync operations with prefix `[FCG GoFundMe Sync]`.

== Changelog ==

= 2.3.0 =
* Architecture: Removed per-fund campaign duplication in favor of single master campaign
* Removed: Campaign publish/unpublish/deactivate/reactivate workflow
* Removed: Per-fund campaign sync (META_CAMPAIGN_ID, META_CAMPAIGN_URL)
* Simplified: Sync handler now focuses exclusively on designation sync
* Note: Legacy campaign meta remains in database but is no longer used

= 2.2.0 =
* Added inbound sync for donation totals
* Added campaign status polling
* Added goal progress calculation
* Inbound sync runs every 15 minutes

= 2.1.0 =
* Fixed draft status to unpublish (not deactivate) campaign
* Added campaign ID display in admin meta box
* Fixed deactivated campaign restore sequence

= 2.0.0 =
* Added campaign sync via template duplication
* Campaign create/update/unpublish/deactivate/reactivate
* Added sync opt-out ACF field support
* Added template campaign validation
* Added campaign URL storage

= 1.0.0 =
* Initial release
* Designation CRUD sync
* OAuth2 token caching with transients
* ACF field group integration
* Status transition handling

== Upgrade Notice ==

= 2.2.0 =
Adds inbound sync - donation totals and campaign status now poll every 15 minutes.

= 2.0.0 =
Major update adding campaign sync. Requires template campaign configured in Classy.
