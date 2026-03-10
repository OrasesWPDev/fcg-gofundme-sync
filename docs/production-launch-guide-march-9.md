# Production Launch Guide — March 9, 2026

## Overview

Step-by-step guide for deploying the FCG GoFundMe Pro Sync system to production. Follow each step in order. Estimated time: 45-60 minutes.

**What's being deployed:**
- FCG GoFundMe Pro Sync plugin (v2.4.0) — syncs WordPress funds with Classy designations
- Classy WP plugin (v1.0.0) — loads the Classy SDK script that renders donation embeds
- Theme file changes — Classy embed on fund pages + mobile race condition fix

---

## Production Environment

| Item | Value |
|------|-------|
| SSH | `frederickcount@frederickcount.ssh.wpengine.net` |
| Site Path | `~/sites/frederickcount` |
| Plugin Path | `wp-content/plugins/fcg-gofundme-sync/` |
| Theme Path | `wp-content/themes/community-foundation/` |
| WP Admin | `https://frederickcountygives.org/wp-admin/` |

## Classy Production Account

| Item | Value |
|------|-------|
| Organization ID | `104060` |
| Master Campaign ID | `764752` |
| Master Component ID | `CngmDfcvOorpIS4KOTO4H` |

---

## Pre-Launch: Already Completed

These items are done. Verify, don't redo.

- [x] Master campaign created in production Classy (764752)
- [x] Production API credentials generated
- [x] Master Component ID noted (CngmDfcvOorpIS4KOTO4H)
- [x] wp-config.php hostname detection block added (staging creds filled in; **production creds still placeholders**)
- [x] Classy nudges disabled on production campaign 764752:
  - Recurring Nudge: OFF (Design tab > Recurring Nudge)
  - Abandon Cart Nudge: OFF (Settings > Donations > Donation options)
- [x] Staging fully tested (all 4 mobile issues verified)

---

## Launch Steps

### Step 0: Create WP Engine Backup

Before touching anything, create a restore point.

1. Log into WP Engine portal
2. Navigate to **frederickcount** environment
3. Go to **Backup points** > **Create backup**
4. Wait for backup to complete before proceeding

---

### Step 1: Update wp-config.php Production Credentials

The hostname detection block already exists in production wp-config.php, but the production credentials are still placeholders.

**SSH into production:**
```bash
ssh frederickcount@frederickcount.ssh.wpengine.net
```

**Edit wp-config.php** (find the existing block and replace placeholders):
```bash
vi ~/sites/frederickcount/wp-config.php
```

Find this section:
```php
} else {
    // PRODUCTION (Classy Production API)
    define('GOFUNDME_CLIENT_ID', 'YOUR_PRODUCTION_CLIENT_ID');
    define('GOFUNDME_CLIENT_SECRET', 'YOUR_PRODUCTION_CLIENT_SECRET');
```

Replace `YOUR_PRODUCTION_CLIENT_ID` and `YOUR_PRODUCTION_CLIENT_SECRET` with the actual production Classy API credentials from your `.env.credentials` file.

**Do NOT change** the other production values — they're already correct:
- `GOFUNDME_ORG_ID`: `104060`
- `GOFUNDME_MASTER_CAMPAIGN_ID`: `764752`
- `GOFUNDME_MASTER_COMPONENT_ID`: `CngmDfcvOorpIS4KOTO4H`

**Verify** the block is BEFORE `require_once ABSPATH . 'wp-settings.php';` in the file.

**Exit SSH** after saving.

---

### Step 2: Deploy classy-wp Plugin

The Classy WP plugin loads the SDK script (`giving.classy.org/embedded/api/sdk/js/{org_id}`) on pages with donation embeds. Without it, the donation form won't render.

This plugin exists on staging but NOT on production. Copy it from staging:

```bash
# From your local machine:
# First, download from staging
rsync -avz frederickc2stg@frederickc2stg.ssh.wpengine.net:~/sites/frederickc2stg/wp-content/plugins/classy-wp/ \
  /tmp/classy-wp/

# Then upload to production
rsync -avz /tmp/classy-wp/ \
  frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/plugins/classy-wp/
```

**Do NOT activate yet** — wait until Step 5.

---

### Step 3: Deploy FCG GoFundMe Pro Sync Plugin

```bash
rsync -avz \
  --exclude='.git' \
  --exclude='.planning' \
  --exclude='*.zip' \
  --exclude='.env*' \
  --exclude='.claude' \
  --exclude='.idea' \
  --exclude='.DS_Store' \
  --exclude='CLAUDE.md' \
  --exclude='README.md' \
  --exclude='readme.txt' \
  --exclude='client-vidoes' \
  --exclude='theme-changes' \
  --exclude='docs' \
  /Users/chadmacbook/projects/fcg/ \
  frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/plugins/fcg-gofundme-sync/
```

**Do NOT activate yet** — wait until Step 5.

---

### Step 4: Deploy Theme Files

Three theme changes are needed. The theme directory is `community-foundation` (NOT `developer`).

**Note:** Steps 4a through 6 should be done in quick succession. Step 4a replaces the live Acceptiva donation form — fund pages will show "coming soon" until Step 6 creates the designations.

#### 4a: Deploy fund-form.php

This replaces the old Acceptiva donation form with the Classy embed.

```bash
rsync -avz /Users/chadmacbook/projects/fcg/theme-changes/fund-form.php \
  frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/themes/community-foundation/fund-form.php
```

#### 4b: Append race condition fix to functions.php

This adds the `fcg_set_fund_designation_early` hook that fixes the mobile "General Fund" bug.

**IMPORTANT: Check FIRST that it's not already there:**
```bash
ssh frederickcount@frederickcount.ssh.wpengine.net \
  "grep -n 'fcg_set_fund_designation_early' ~/sites/frederickcount/wp-content/themes/community-foundation/functions.php"
```

- **If grep returns a line number:** Hook is already present. SKIP this step. Do NOT append again (causes PHP fatal error from duplicate function).
- **If grep returns nothing:** Safe to append:

```bash
ssh frederickcount@frederickcount.ssh.wpengine.net \
  "cat >> ~/sites/frederickcount/wp-content/themes/community-foundation/functions.php" \
  < /Users/chadmacbook/projects/fcg/theme-changes/functions-php-additions.php
```

**Verify exactly ONE occurrence:**
```bash
ssh frederickcount@frederickcount.ssh.wpengine.net \
  "grep -c 'fcg_set_fund_designation_early' ~/sites/frederickcount/wp-content/themes/community-foundation/functions.php"
```
Should return `1`.

#### 4c: Verify archive-funds.php

The archive page should have "Give Now" direct links instead of modal triggers. Verify:

```bash
ssh frederickcount@frederickcount.ssh.wpengine.net \
  "grep -c 'data-toggle=\"modal\"' ~/sites/frederickcount/wp-content/themes/community-foundation/archive-funds.php"
```

- If `0`: Good — modals already disabled, direct links in place.
- If non-zero: The archive page still has modal triggers. These will break with the Classy SDK. Deploy the updated archive-funds.php from staging.

---

### Step 5: Activate Plugins

Log into WP Admin: `https://frederickcountygives.org/wp-admin/`

1. Go to **Plugins**
2. Activate **Classy WP** (loads the SDK script)
3. Activate **FCG GoFundMe Pro Sync** (the sync plugin)

**After activation, verify plugin settings are reading from constants:**
1. Go to **Settings > GoFundMe Pro Sync**
2. You should see a read-only "Configuration (from wp-config.php)" section showing:
   - Master Campaign ID: `764752`
   - Master Component ID: `CngmDfcvOorpIS4KOTO4H`
3. Verify "API Status" shows connected (green)

If API Status shows an error, the production credentials in wp-config.php (Step 1) are incorrect.

---

### Step 6: Create Designations via WP-CLI

This creates a Classy designation for every published fund and links it to the master campaign. This is what makes the Classy donation embeds work on each fund page.

**SSH into production:**
```bash
ssh frederickcount@frederickcount.ssh.wpengine.net
cd ~/sites/frederickcount
```

**Preview first (dry run):**
```bash
wp fcg-sync push --dry-run
```
Should show ~860 `[WOULD CREATE]` lines — one per published fund.

**Create all designations:**
```bash
wp fcg-sync push
```
This takes several minutes for 860+ funds. Monitor progress output.

**After push completes, verify in Classy:**
1. Log into production Classy: `manage.classy.org`
2. Navigate to Campaign 764752
3. Check Program Designations — should show 860+ designations
4. Verify designations are in the Default Active Group
5. Check which designation is set as "default" — the last fund synced becomes the default. Reset if needed.

---

### Step 7: Purge All Caches

WP Engine has aggressive page caching. Without purging, visitors may see old cached versions of fund pages.

**Option A (WP Admin):**
WP Admin > WP Engine > General Settings > **Purge All Caches**

**Option B (SSH):**
```bash
ssh frederickcount@frederickcount.ssh.wpengine.net \
  "cd ~/sites/frederickcount && wp cache flush"
```

---

### Step 8: Enable Alternate Cron

WP Engine requires Alternate Cron for reliable scheduled tasks (inbound donation sync).

```bash
ssh frederickcount@frederickcount.ssh.wpengine.net \
  "grep 'ALTERNATE_WP_CRON' ~/sites/frederickcount/wp-config.php"
```

- If found: Already configured.
- If not found: Add to wp-config.php (before `wp-settings.php` require):

```php
define('ALTERNATE_WP_CRON', true);
```

---

### Step 9: Verify Classy Dashboard Settings

Confirm the nudge settings you disabled earlier are still OFF on the production campaign.

1. Log into `manage.classy.org`
2. Navigate to Campaign 764752
3. **Design tab > Recurring Nudge:** Should be OFF
4. **Settings tab > Donations > Donation options > Abandon cart nudge:** Should be OFF

If either was reset (e.g., from a Classy update), turn them off again.

---

### Step 10: Verification Testing

#### Fund Page Test
- [ ] Visit any fund page (e.g., `https://frederickcountygives.org/funds/{any-fund}/`)
- [ ] Classy donation form appears (not the old Acceptiva form, not "coming soon")
- [ ] URL shows `?designation={number}` in the address bar
- [ ] "I'd like to support" shows the correct fund name (NOT "General Fund Project")

#### Donation Flow Test
- [ ] Select a donation amount
- [ ] Wait 15-20 seconds — no "Make a difference!" overlay should appear
- [ ] Click "Donate" — payment flow opens
- [ ] No "Become a monthly supporter!" interstitial appears
- [ ] Can scroll page normally throughout

#### Archive Page Test
- [ ] Visit `https://frederickcountygives.org/funds/`
- [ ] Fund titles link directly to fund pages (no modals)
- [ ] "Give Now" links go to fund pages

#### Admin UI Test
- [ ] Edit any fund in WP Admin
- [ ] "GoFundMe Pro Sync" meta box shows:
  - Designation ID (clickable link to Classy)
  - Last Sync timestamp

#### Mobile Test (on phone)
- [ ] Open a fund page on iOS Safari
- [ ] Correct fund name shown in "I'd like to support"
- [ ] No popups or overlays appear
- [ ] Donation flow works without scroll-lock

---

### Step 11: Post-Launch Monitoring

**First hour:**
- Check PHP error logs: `ssh frederickcount@frederickcount.ssh.wpengine.net "tail -50 ~/sites/frederickcount/wp-content/debug.log 2>/dev/null"`
- Verify inbound sync runs (check last sync timestamps in admin)
- Monitor Classy dashboard for any sync errors

**First day:**
- Verify cron is running (check last poll timestamp in plugin settings)
- Check a few fund pages across different browsers
- Confirm no client reports of issues

---

## Rollback Plan

If critical issues occur after launch:

**Quick fix (deactivate plugins):**
1. Go to WP Admin > Plugins
2. Deactivate FCG GoFundMe Pro Sync
3. Deactivate Classy WP
4. Fund pages will show "Online donations coming soon" fallback message
5. Old Acceptiva forms are NOT automatically restored (they were replaced in fund-form.php)

**Full rollback (restore old donation forms):**
1. Deactivate both plugins (above)
2. Restore from WP Engine backup created in Step 0
3. WP Engine portal > frederickcount > Backup points > Restore the backup from before launch

---

## File Reference

| File | Source | Destination on Production | Action |
|------|--------|---------------------------|--------|
| Plugin (all files) | `/Users/chadmacbook/projects/fcg/` | `wp-content/plugins/fcg-gofundme-sync/` | rsync |
| classy-wp plugin | Copy from staging | `wp-content/plugins/classy-wp/` | rsync |
| fund-form.php | `theme-changes/fund-form.php` | `wp-content/themes/community-foundation/fund-form.php` | rsync (replace) |
| functions.php additions | `theme-changes/functions-php-additions.php` | `wp-content/themes/community-foundation/functions.php` | Append (once only) |
| wp-config.php | Edit in place on server | `~/sites/frederickcount/wp-config.php` | Replace credential placeholders |

## Classy Dashboard Settings Reference

| Setting | Location | Required State |
|---------|----------|---------------|
| Recurring Nudge | Design tab > Recurring Nudge | OFF |
| Abandon Cart Nudge | Settings > Donations > Donation options | OFF |

---

*Created: 2026-02-26*
*Updated: 2026-03-09 (pressure-tested and corrected)*
*Target launch: 2026-03-09*
