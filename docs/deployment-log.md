# Deployment Log

## 2026-01-29 - Phase 07 Plan 02: Classy Embed to Staging

**Deployment:** fund-form.php theme file to WP Engine staging

**Method:** rsync

**Command:**
```bash
rsync -avz "/Users/chadmacbook/Local Sites/frederick-county-gives/app/public/wp-content/themes/community-foundation/fund-form.php" \
  frederickc2stg@frederickc2stg.ssh.wpengine.net:~/sites/frederickc2stg/wp-content/themes/community-foundation/fund-form.php
```

**Status:** ✅ Deployed successfully

**Verification:** SSH confirmed file contains Classy embed code with:
- Designation pre-selection via history.replaceState()
- Master campaign ID and component ID from plugin settings
- Graceful fallback for unconfigured funds

**Environment:** Staging (frederickc2stg.wpengine.com)

**Next Steps:** Human verification on staging environment required before production deployment.

---

## Test Fund Identified for Verification

**Fund Details:**
- Post ID: 13854
- Title: "Phase 6 Test Fund - DELETE ME"
- Slug: phase-6-test-fund-delete-me
- Designation ID: 1896370
- Single Page URL: https://frederickc2stg.wpengine.com/funds/phase-6-test-fund-delete-me/

**Additional Test Funds Available:**
- Charles Harris Family Charitable Fund (ID: 13795, Designation: via WP-CLI)
- Clark E. and Evelyn P. Shaff Scholarship Fund (ID: 13782, Designation: via WP-CLI)
- Frederick Motor Company Loves Frederick Fund (ID: 13781, Designation: via WP-CLI)

**Staging Settings (Phase 6):**
- Master Campaign ID: 764694
- Master Component ID: mKAgOmLtRHVGFGh_eaqM6
- Classy Org ID: 105659

**Ready for human verification testing.**
