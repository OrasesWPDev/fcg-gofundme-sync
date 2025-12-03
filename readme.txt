=== FCG GoFundMe Pro Sync ===
Contributors: orases
Tags: gofundme, donations, sync, api
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically syncs WordPress funds with GoFundMe Pro designations via API.

== Description ==

This plugin provides automatic synchronization between the WordPress "funds" 
custom post type and GoFundMe Pro designations.

**What it does:**

* Creates a GoFundMe Pro designation when a fund is published
* Updates the designation when a fund is modified
* Deactivates the designation when a fund is trashed (soft delete)
* Reactivates the designation when a fund is restored
* Permanently deletes the designation when a fund is permanently deleted

**What it doesn't do:**

* No admin UI for creating campaigns directly
* No settings page (credentials go in wp-config.php)
* No bulk operations (use the bulk script for initial migration)

== Installation ==

1. Upload the `fcg-gofundme-sync` folder to `/wp-content/plugins/`
2. Add credentials to `wp-config.php` (see Configuration below)
3. Activate the plugin through the 'Plugins' menu in WordPress

== Configuration ==

Add these constants to your `wp-config.php` file:

`
// GoFundMe Pro API Credentials
define('GOFUNDME_CLIENT_ID', 'your_client_id_here');
define('GOFUNDME_CLIENT_SECRET', 'your_client_secret_here');
define('GOFUNDME_ORG_ID', 'your_org_id');
`

Get your API credentials from:
https://www.classy.org/admin/YOUR_ORG_ID/apps/classyapi

== ACF Integration ==

This plugin works with the "GoFundMe Pro Settings" ACF field group.
It reads the designation ID from either:

1. Post meta: `_gofundme_designation_id` (set by bulk script or this plugin)
2. ACF field: `gofundme_settings_gofundme_designation_id` (fallback)

== Sync Behavior ==

**On Fund Publish:**
- Creates a new designation in GoFundMe Pro
- Stores the returned designation ID in post meta
- Records sync timestamp

**On Fund Update:**
- Updates the existing designation with new title/description
- Records sync timestamp

**On Fund Unpublish (Draft):**
- Sets designation `is_active = false`
- Designation remains in GoFundMe Pro but is hidden

**On Fund Trash:**
- Sets designation `is_active = false`
- Preserves designation for potential restore

**On Fund Restore:**
- Sets designation `is_active = true`
- Donations are re-enabled

**On Fund Permanent Delete:**
- Permanently deletes the designation from GoFundMe Pro

== Logging ==

When `WP_DEBUG` is enabled, the plugin logs sync operations to the PHP error log:

`
[FCG GoFundMe Sync] Created designation 12345 for post 678
[FCG GoFundMe Sync] Updated designation 12345 for post 678
[FCG GoFundMe Sync] ERROR: Failed to create designation for post 678: ...
`

== Changelog ==

= 1.0.0 =
* Initial release
* Create, update, delete designation sync
* OAuth2 token caching with transients
* ACF field group integration
* Status transition handling

== Upgrade Notice ==

= 1.0.0 =
Initial release.
