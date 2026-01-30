# Environment-Safe Configuration

## Overview

To prevent credential cross-contamination when copying databases between environments (e.g., production to staging), all Classy API credentials should be configured in wp-config.php using hostname detection.

**Problem solved:** When you copy a production database to staging on WP Engine:
- wp_options values (campaign ID, component ID) get overwritten
- Risk of staging using production API credentials or vice versa

**Solution:** Both sets of credentials live in wp-config.php. Hostname detection picks the correct set at runtime.

## Setup

Add this block to wp-config.php on **BOTH** staging and production:

```php
/**
 * FCG GoFundMe Sync - Environment-Specific Configuration
 *
 * Hostname detection ensures correct credentials are used even after
 * database copies between environments.
 */
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'frederickc2stg') !== false) {
    // STAGING (Sandbox API)
    define('GOFUNDME_CLIENT_ID', 'your_sandbox_client_id');
    define('GOFUNDME_CLIENT_SECRET', 'your_sandbox_secret');
    define('GOFUNDME_ORG_ID', 'your_org_id');
    define('GOFUNDME_MASTER_CAMPAIGN_ID', 'staging_campaign_id');
    define('GOFUNDME_MASTER_COMPONENT_ID', 'staging_component_id');
} else {
    // PRODUCTION
    define('GOFUNDME_CLIENT_ID', 'your_prod_client_id');
    define('GOFUNDME_CLIENT_SECRET', 'your_prod_secret');
    define('GOFUNDME_ORG_ID', 'your_org_id');
    define('GOFUNDME_MASTER_CAMPAIGN_ID', '764694');
    define('GOFUNDME_MASTER_COMPONENT_ID', 'your_prod_component_id');
}
```

**Important:** Replace placeholder values with actual credentials. Never commit credentials to the repository.

## Constants Reference

| Constant | Description | Example |
|----------|-------------|---------|
| `GOFUNDME_CLIENT_ID` | OAuth2 Client ID from Classy | `abc123...` |
| `GOFUNDME_CLIENT_SECRET` | OAuth2 Client Secret from Classy | `xyz789...` |
| `GOFUNDME_ORG_ID` | Organization ID from Classy | `12345` |
| `GOFUNDME_MASTER_CAMPAIGN_ID` | Master campaign containing all designations | `764694` |
| `GOFUNDME_MASTER_COMPONENT_ID` | Component ID for frontend embeds | `mKAgOmLtRHVGFGh_eaqM6` |

## How It Works

1. **Hostname detection:** `$_SERVER['HTTP_HOST']` is checked at runtime
2. **Staging identified by:** `frederickc2stg` in hostname
3. **Production:** All other hostnames (default case)
4. **Priority:** Constants take precedence over wp_options values
5. **Database copies:** Won't affect credentials (they're in file, not DB)

## Plugin Behavior

When constants are defined:

1. **Admin UI:** Shows read-only configuration section
   - Campaign ID and Component ID displayed as code blocks
   - Info message: "Configured in wp-config.php. Contact developer to modify."

2. **Settings saving:** Skipped for constant-defined values
   - Polling settings still saved to wp_options (interval, enabled)
   - Master campaign/component IDs not saved (constants used directly)

3. **Sync operations:** Use constant values directly
   - No database lookup required
   - Faster, more reliable

## Verification

After setup, verify by visiting:
**Funds > Sync Settings** in WordPress admin

You should see:
- "Configuration (from wp-config.php)" header
- Read-only display of Campaign ID and Component ID
- Info message confirming wp-config.php source

## Backwards Compatibility

If constants are NOT defined:
- Plugin falls back to wp_options values
- Admin UI shows editable fields
- Existing installations continue working

## Troubleshooting

**Constants not being read?**
- Ensure wp-config.php block is BEFORE `require_once ABSPATH . 'wp-settings.php';`
- Check for PHP syntax errors in wp-config.php
- Verify hostname detection matches your environment

**Wrong environment detected?**
- Check `$_SERVER['HTTP_HOST']` value
- Adjust hostname pattern in if condition
- WP-CLI commands may not have `HTTP_HOST` set (will use production/default)

**Database copy still has issues?**
- Transients may cache old tokens - clear transients after DB copy
- Run: `wp transient delete gofundme_access_token`

## WP Engine Specific Notes

- Traditional WP Engine hosting does NOT have environment variables UI
- Only WP Engine Atlas/Headless has env vars
- Use wp-config.php constants as described above
- Access wp-config.php via SSH or WP Engine File Manager

## Related Documentation

- [Production Deployment Checklist](./production-deployment-checklist.md)
- [Theme Fund Form Embed](./theme-fund-form-embed.md)
