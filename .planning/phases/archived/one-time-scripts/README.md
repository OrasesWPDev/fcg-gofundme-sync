# One-Time Scripts (Archived)

**Archived:** 2026-01-29
**Reason:** These were utility scripts used during Phase 5-6 development. Not part of the plugin.

## Scripts

| Script | Purpose | When Used |
|--------|---------|-----------|
| `cleanup-test-designations.php` | Removed 11 test designations from Classy | Phase 5 |
| `debug-api-raw.php` | Raw API debugging output | Phase 5-6 |
| `debug-api.php` | Formatted API debugging | Phase 5-6 |
| `link-pending-designations.php` | Linked 5 pending designations to master campaign | Phase 6 |
| `match-designations.php` | Matched WordPress funds to Classy designations | Phase 5 |

## Usage

These scripts were run manually via WP-CLI or SSH during development. They are NOT part of the plugin and should NOT be deployed.

Example (if ever needed again):
```bash
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp eval-file /path/to/script.php"
```

---
*Archived from /scripts on 2026-01-29*
