# Production Launch Report — March 10, 2026

## Summary

The FCG GoFundMe Pro Sync system was successfully launched to production on March 10, 2026. All components were deployed, issues discovered during launch were resolved in real-time, and desktop + mobile donation flows were verified working.

**Components Deployed:**
- FCG GoFundMe Pro Sync plugin (v2.4.0)
- Classy WP plugin (v1.0.0)
- Theme changes: Classy embed on fund pages + mobile race condition fix
- 861 Classy designations created and linked to master campaign 764752

**Launch Duration:** ~3 hours (including debugging and monitoring)

---

## Steps Completed

### Step 0: WP Engine Backups
- Created backup points for both production and staging environments via WP Engine portal

### Step 1: wp-config.php Production Credentials
- Updated via SFTP (not SSH) — replaced placeholder credentials with real production Classy API values
- Hostname detection block confirmed working: staging hostname → sandbox creds, production → production creds

### Step 2: Deploy Classy WP Plugin
- Copied from staging to production via SFTP
- Plugin provides the Classy SDK script (`giving.classy.org/embedded/api/sdk/js/{org_id}`)

### Step 3: Deploy FCG GoFundMe Pro Sync Plugin
- Copied from staging to production via SFTP
- **Issue found:** SFTP copied entire project directory including dev files (`.env.credentials`, `CLAUDE.md`, `.claude/`, `docs/`, etc.)
- **Fix:** SSH cleanup removed all non-production files from the plugin directory

### Step 4: Deploy Theme Files

#### 4a: fund-form.php
- Deployed via rsync — replaces old Acceptiva form with Classy embed

#### 4b: functions.php Race Condition Fix
- Initially appended with tracking comments that don't exist on staging
- Also caused a syntax issue: `<?php` tag jammed onto end of previous line
- **Fix:** User cleaned up via SFTP by copying the clean version from staging

#### 4c: archive-funds.php
- Production still had old modal triggers (`data-toggle="modal"`)
- **Fix:** User replaced with staging version via SFTP — now uses direct permalink links

### Step 5: Activate Plugins
- Both plugins activated in WP Admin
- API Status confirmed connected (green)
- Plugin settings showing correct Campaign ID (764752) and Component ID

### Step 6: Create Designations via WP-CLI
- Dry run showed 861 funds to sync
- `wp fcg-sync push` executed — created 861 designations in Classy

### Step 7: Purge All Caches
- Cache purged via WP Engine portal

### Step 8: Alternate Cron
- `DISABLE_WP_CRON` was already set by WP Engine's cron toggle in the portal
- WP Engine server-side cron handles execution

### Step 9: Classy Dashboard Verification
- Recurring Nudge: OFF
- Abandon Cart Nudge: OFF

### Step 10: Verification Testing
- Desktop donation flow: Working
- Mobile donation flow: Working (after fixes below)
- Fund pages show correct designation in "I'd like to support"

### Step 11: Post-Launch Monitoring
- Set up automated monitoring loop (every 15 minutes for 2 hours)
- All checks returned green — no errors, poller running on schedule

---

## Issues Found and Fixed During Launch

### 1. Dev Files Deployed to Production via SFTP

**Problem:** SFTP copied the entire project directory without rsync excludes, pushing `.env.credentials`, `CLAUDE.md`, `.claude/`, `.idea/`, `docs/`, `theme-changes/`, etc. to the production plugin directory.

**Fix:** SSH cleanup removed all non-production files:
```bash
rm -f .env.credentials .DS_Store CLAUDE.md README.md .gitignore
rm -rf .claude/ .idea/ docs/ theme-changes/
```

**Prevention:** Always use rsync with exclude flags for deployments, not raw SFTP copy.

### 2. functions.php Tracking Comments and Syntax Issue

**Problem:** The local `functions-php-additions.php` had wrapper comments (deployment status, task tracking) that got appended to production. Also, the `<?php` opening tag got concatenated onto the end of the previous `endif;` line.

**Fix:** User copied the clean version from staging functions.php via SFTP.

**Prevention:** Keep `functions-php-additions.php` clean — no tracking comments, no `<?php` tag (it's appended to an existing PHP file).

### 3. Classy Default Active Group — Only 1 Designation

**Problem:** After running `wp fcg-sync push` for 861 funds, only the last designation appeared in the Classy campaign's Default Active Group. The Classy API `PUT /campaigns/{id}` with `designation_id` **replaces** the group rather than appending to it.

**Fix:** User manually added all 861 designations to the Default Active Group in the Classy dashboard.

**Prevention:** This is a one-time bulk issue. Individual fund syncs going forward create a designation and link it, which works correctly for single additions. A future enhancement could use the Classy batch API to append to the group.

### 4. Wrong Organization ID in Classy WP Plugin Settings

**Problem:** The Classy Donation Form settings in WP Admin had `764752` (the campaign ID) instead of `104060` (the org ID). This caused the SDK script to load from the wrong URL.

**Fix:** User corrected the Organization ID to `104060` in WP Admin > Settings > Classy Donation Form.

### 5. Missing wp_options Values for Theme Template

**Problem:** `fund-form.php` reads `fcg_gofundme_master_campaign_id` and `fcg_gofundme_master_component_id` from `get_option()`, but these were never set in the database. The plugin reads from wp-config.php constants but doesn't write them to wp_options. Fund pages showed "coming soon" fallback instead of the donation form.

**Fix:** Set values via WP-CLI:
```bash
wp option update fcg_gofundme_master_campaign_id 764752
wp option update fcg_gofundme_master_component_id CngmDfcvOorpIS4KOTO4H
```

**Prevention:** Update `fund-form.php` to check wp-config.php constants first before falling back to `get_option()`. (Open follow-up item.)

### 6. Duplicate Classy SDK Script — Mobile JS Conflict

**Problem:** The "Insert Headers and Footers" plugin (`ihaf_insert_header` wp_option) contained a hardcoded Classy SDK `<script>` tag, duplicating the one loaded by the Classy WP plugin. This caused the mobile Donate button to do nothing on first page load — only worked after a manual refresh.

**Root Cause:** Two SDK instances competed for DOM control. Desktop tolerated it; mobile Safari did not.

**Fix:** Cleared the duplicate script:
```bash
wp option update ihaf_insert_header ""
```

**Prevention:** Classy SDK should only be loaded by one source (the Classy WP plugin). Remove any hardcoded SDK scripts from Insert Headers and Footers or theme files.

### 7. Missing Poller Cron Event

**Problem:** The `fcg_gofundme_sync_poll` WP-Cron event was not registered on production. The plugin only schedules it in `register_activation_hook()`, which doesn't fire when files are deployed via rsync/SFTP — it only runs when activated through WP Admin's plugin management.

**Impact:** The inbound sync poller (pulls donation totals from Classy every 15 minutes) was not running automatically. The only poll that occurred was from the manual `wp fcg-sync push` command.

**Fix:** Registered the cron event via WP-CLI:
```bash
wp cron event schedule fcg_gofundme_sync_poll now fcg_gfm_15min
```

**Verified:** Poller confirmed firing every 15 minutes — `fcg_gfm_last_poll` timestamp advancing on schedule.

**Prevention:** Add a runtime fallback in the plugin's `init` hook that checks `if (!wp_next_scheduled('fcg_gofundme_sync_poll'))` and schedules it. This prevents the issue on future file-based deployments. (Open follow-up item.)

---

## Open Follow-Up Items

| Item | Priority | Description |
|------|----------|-------------|
| `fund-form.php` constants check | Medium | Update template to check wp-config.php constants before `get_option()` fallback |
| Plugin cron self-registration | High | Add `init` hook fallback to auto-schedule cron if missing |
| Staging cron registration | Low | Run `wp cron event schedule fcg_gofundme_sync_poll now fcg_gfm_15min` on staging (SSH was blocked; or deactivate/reactivate plugin) |
| Staging duplicate SDK cleanup | Low | Clear `ihaf_insert_header` on staging — same duplicate script issue exists there |
| Theme PHP warnings | Low | Fix `Undefined array key "QUERY_STRING"` on functions.php:812 and `DISALLOW_FILE_EDIT already defined` on line 74 (pre-existing, not from our changes) |

---

## Monitoring Results

| Check | Time | Error Log | Sync Timestamp | Cron Status |
|-------|------|-----------|----------------|-------------|
| Baseline | ~10:08 | No debug.log | 07:18:55 | Not registered |
| Check 2 | ~10:23 | No debug.log | 07:18:55 | Not registered |
| Check 3 | ~10:38 | No debug.log | 07:18:55 | Not registered |
| Cron fix applied | ~10:39 | — | — | `fcg_gofundme_sync_poll` scheduled |
| Check 4 | ~10:50 | No debug.log | 09:39:25 | Every 15 min, next in 10 min |
| Check 5 | ~11:05 | No debug.log | 09:54:32 | Every 15 min, next in 6 min |
| Check 6 | ~11:20 | No debug.log | 10:09:30 | Every 15 min, next in 6 min |

All checks green. Poller firing on schedule after cron fix.

---

## Environment Reference

| Environment | SSH Host | Site Path |
|-------------|----------|-----------|
| Production | `frederickcount@frederickcount.ssh.wpengine.net` | `~/sites/frederickcount` |
| Staging | `frederickc2stg@frederickc2stg.ssh.wpengine.net` | `~/sites/frederickc2stg` |

| Classy Config | Production | Staging (Sandbox) |
|---------------|------------|-------------------|
| Organization ID | 104060 | 105659 |
| Master Campaign ID | 764752 | 764694 |
| Master Component ID | CngmDfcvOorpIS4KOTO4H | mKAgOmLtRHVGFGh_eaqM6 |

---

## Post-Launch Bug Fix — March 17, 2026

### Bug Report

Client reported a $250 donation from Daniel Traugh (March 14, 2026 at 12:10 PM EDT, Confirmation #174509301) was recorded against "General Fund Project" (the default designation) instead of the intended fund "Dan Richmond Music and Hope Scholarship." The donation was a memorial ("In memory of Dan Richmond"), which is how the client identified where it belonged. This was the only misrouted donation in the first week of production.

### Investigation

1. **Verified the fund page works correctly** — Fund ID 13656 has designation `1898361`, the race condition fix is in place, and curling the fund page confirms `?designation=1898361` is set in the URL via the `wp_head` priority 1 hook.

2. **Checked the site search** — Searching for "dan richmond" on the production site revealed the root cause: the search results page (`search.php`) still used the old modal pattern (`data-toggle="modal" data-target="#fund-13656"`). Clicking "Give Now" from search results opened a modal with the Classy embed but **without** the `?designation=` URL parameter, causing the donation to default to "General Fund Project."

3. **Audited all theme templates** — Found three templates still using modal triggers for fund donation:
   - `search.php` — fund title and "Give Now" both opened modals
   - `taxonomy-fund-category.php` — "Give Now" opened modal
   - `template-flexible.php` — "Give Now" opened modal

   `archive-funds.php` was the only template fixed during the March 10 launch.

### Root Cause

The `fcg_set_fund_designation_early()` race condition fix runs on `is_singular('funds')` — it only fires on individual fund pages. Any path that renders the Classy donation form outside of a singular fund page (modals on search, taxonomy, or flexible template pages) bypasses the designation parameter, causing donations to default to "General Fund Project."

The donor likely searched for the fund on the site, clicked "Give Now" from search results, and completed the donation through the modal — never visiting the actual fund page where the designation would have been set.

### Fix Applied

Replaced modal triggers with direct permalink links in all three templates, matching the pattern used in the `archive-funds.php` fix from launch:

| Template | Change |
|----------|--------|
| `search.php` | Fund title: modal → permalink. "Give Now": modal → permalink. Removed "Learn More" (redundant). Removed `fund-modal` include. |
| `taxonomy-fund-category.php` | "Give Now": modal → permalink. Removed `fund-modal` include. |
| `template-flexible.php` | "Give Now": modal → permalink. Removed `fund-modal` include. |

All "Give Now" buttons now route donors to the individual fund page, where the `?designation=` parameter is set correctly before the Classy embed loads.

### Deployment

- Deployed to staging: 2026-03-17
- Tested on staging: search results confirmed showing direct permalink links
- Deployed to production: 2026-03-17
- Verified on production: `curl` confirmed no modal triggers in search results, "Give Now" links to fund permalink

### Verification

```
# Before fix (production search results):
<a data-toggle="modal" data-target="#fund-13656">Give Now</a>

# After fix:
<a class="btn-link" rel="bookmark" href="https://www.frederickcountygives.org/funds/dan-richmond-music-and-hope-scholarship/">Give Now</a>
```

---

*Launch completed: 2026-03-10*
*Report created: 2026-03-10*
*Updated: 2026-03-17 (post-launch bug fix — modal triggers in search/taxonomy/flexible templates)*
