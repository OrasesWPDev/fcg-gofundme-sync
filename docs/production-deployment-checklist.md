# Production Deployment Checklist

## Overview

This document outlines the steps to deploy the FCG GoFundMe Pro Sync plugin to the production environment. **Credentials are not stored in this file** — they must be configured in WP Engine environment variables.

## Production Environment

| Item | Value |
|------|-------|
| SSH | `frederickcount@frederickcount.ssh.wpengine.net` |
| Site Path | `~/sites/frederickcount` |
| Plugin Path | `wp-content/plugins/fcg-gofundme-sync/` |
| Theme Path | `wp-content/themes/developer/` |

## Classy Production Account

| Item | Value |
|------|-------|
| Organization ID | `104060` |
| Master Campaign ID | `764752` |
| Master Component ID | `CngmDfcvOorpIS4KOTO4H` |

**Embed Code Reference:**
```html
<!-- Production -->
<div id="CngmDfcvOorpIS4KOTO4H" classy="764752" />

<!-- Staging -->
<div id="mKAgOmLtRHVGFGh_eaqM6" classy="764694" />
```

## Credentials Configuration

### Local Credentials Reference

All credentials are stored locally (gitignored) at:
```
.env.credentials
```

This file contains values for both staging and production, plus ready-to-copy wp-config.php blocks.

### Option 1: wp-config.php (Recommended for WP Engine)

SSH into production and edit wp-config.php. Add below the `# WP Engine Settings` section:

```php
// FCG GoFundMe Pro Sync - Classy Production Credentials
define('GOFUNDME_CLIENT_ID', 'REDACTED_CLIENT_ID');
define('GOFUNDME_CLIENT_SECRET', 'REDACTED_CLIENT_SECRET');
define('GOFUNDME_ORG_ID', '104060');
```

### Option 2: WP Engine Environment Variables

1. Log into WP Engine User Portal
2. Navigate to **frederickcount** environment
3. Go to **Environment Variables** section
4. Add the following:

| Variable | Value |
|----------|-------|
| `GOFUNDME_CLIENT_ID` | `REDACTED_CLIENT_ID` |
| `GOFUNDME_CLIENT_SECRET` | `REDACTED_CLIENT_SECRET` |
| `GOFUNDME_ORG_ID` | `104060` |

**Note:** Environment variables take precedence over wp-config.php constants.

**OAuth2 Flow:** Plugin uses `client_credentials` grant type (server-to-server). No redirect URL is required.

## Pre-Deployment Checklist

### Staging Verification (before production)
- [x] All 860+ funds have designation IDs synced
- [x] Test donation completes with correct designation
- [x] DELETE test: deleting a fund removes designation from Classy
  - Verified: 2026-01-30 via Phase 8 testing
  - Behavior: Trash = deactivate (is_active: false), Permanent delete = remove entirely (404)
  - Note: Default designation cannot be deleted (change default first)
- [x] Inbound sync polling works (donation totals update)
- [x] Admin UI shows designation info correctly
  - Includes donation totals from inbound sync (verified 2026-01-30)

### Production Preparation
- [ ] Master campaign created in production Classy account
- [ ] Master campaign configured (styling, settings)
- [ ] Component ID noted from embed code
- [ ] API credentials generated in production Classy
- [ ] Environment variables set in WP Engine

## Deployment Steps

### Step 1: Deploy Plugin

```bash
rsync -avz --exclude='.git' --exclude='.planning' --exclude='*.zip' \
  /Users/chadmacbook/projects/fcg/ \
  frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/plugins/fcg-gofundme-sync/
```

### Step 2: Deploy Theme Files

Theme files that need deployment:

| File | Purpose |
|------|---------|
| `fund-form.php` | Classy embed on single fund pages |
| `archive-funds.php` | Direct links (modal disabled) |

**Option A: rsync (if theme is in local repo)**
```bash
rsync -avz fund-form.php archive-funds.php \
  frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/themes/developer/
```

**Option B: SFTP via WP Engine File Manager**
1. Log into WP Engine User Portal
2. Navigate to frederickcount → SFTP users
3. Connect via SFTP client or use File Manager
4. Upload files to `wp-content/themes/developer/`

### Step 3: Configure Plugin Settings

1. Log into WordPress admin: `https://www.frederickcountygives.org/wp-admin/`
2. Navigate to **Settings → GoFundMe Pro Sync**
3. Configure:
   - Master Campaign ID: `764752`
   - Master Component ID: *(from Step 1)*
4. Verify "API Status" shows connected

### Step 4: Initial Sync

1. In plugin settings, click **Sync All Funds Now**
2. Monitor sync status
3. Verify designations appear in Classy dashboard
4. Check that designations are in the master campaign's Default Active Group

### Step 5: Enable Cron

WP Engine requires Alternate Cron for reliable scheduling:

1. SSH into production
2. Check wp-config.php for: `define('ALTERNATE_WP_CRON', true);`
3. If not present, add via WP Engine User Portal or contact support

### Step 6: Verification

- [ ] Visit a fund page, verify Classy embed loads
- [ ] Verify correct designation is pre-selected
- [ ] Complete a test donation (use sandbox card if available)
- [ ] Check Classy dashboard for donation with correct designation
- [ ] Verify inbound sync updates donation totals
- [ ] Verify admin UI shows donation data after inbound sync runs
  - Edit any fund, check "GoFundMe Pro Sync" meta box
  - Should show: Donation Total, Donor Count, Goal Progress (if applicable)

## Post-Deployment

### Monitoring
- Check PHP error logs for `[FCG GoFundMe Sync]` entries
- Monitor Classy dashboard for sync issues
- Verify cron is running (check last sync timestamps)

### Rollback Plan
If issues occur:
1. Deactivate plugin in WordPress admin
2. Previous donation provider (Accativa) forms will need manual restoration
3. Contact Classy support if designation sync issues

## Admin UI Features (Phase 8)

The fund edit screen meta box ("GoFundMe Pro Sync") displays:
- Designation ID (with link to Classy admin)
- Last Sync timestamp
- Sync source
- Fundraising Goal (editable)
- **Donation Total** (from inbound sync, formatted as currency)
- **Donor Count** (from inbound sync)
- **Goal Progress** (percentage, when goal set)
- **Last Inbound Sync** timestamp
- Sync Now button

## Theme Files Reference

Files modified for Classy integration:

| File | Change | Status |
|------|--------|--------|
| `fund-form.php` | Classy inline embed | Deploy |
| `archive-funds.php` | Modal disabled, direct links | Deploy |
| `search.php` | Modal removal needed | Phase 9 |
| `taxonomy-fund-category.php` | Modal removal needed | Phase 9 |
| `template-flexible.php` | Modal removal needed | Phase 9 |

---

*Last updated: 2026-01-29*
*Phase: 8 (Production Launch MVP)*
