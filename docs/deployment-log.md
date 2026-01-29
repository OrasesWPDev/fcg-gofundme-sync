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
