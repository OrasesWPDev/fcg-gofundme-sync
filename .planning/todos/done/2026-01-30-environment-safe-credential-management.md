---
created: 2026-01-30T12:00
title: Environment-safe credential management for staging/production syncs
area: config
files:
  - fcg-gofundme-sync.php
  - includes/class-api-client.php
  - includes/class-admin-ui.php
---

## Problem

When copying production database to staging (common WP Engine workflow for testing), credentials and IDs risk being overwritten or cross-contaminated:

**Environment-specific values at risk:**
1. **OAuth credentials** (wp-config.php or env vars):
   - `GOFUNDME_CLIENT_ID` — different for sandbox vs production
   - `GOFUNDME_CLIENT_SECRET` — different for sandbox vs production
   - `GOFUNDME_ORG_ID` — same org but different API environments

2. **Plugin settings** (stored in wp_options):
   - `gofundme_master_campaign_id` — Campaign 764694 (production) vs sandbox equivalent
   - `gofundme_master_component_id` — Component ID for embed code

**Current risk:** After a prod→staging copy, the staging site might:
- Use production API credentials against sandbox data
- Push/modify production Classy designations accidentally
- Display wrong campaign embed on frontend

**Need:** A safe, secure method to ensure each environment always uses its correct credentials regardless of database copies.

## Solution

TBD — brainstorm session needed. Potential approaches:

1. **Environment detection** — Auto-detect WP Engine environment and use appropriate constants
2. **Hostname-based switching** — Match hostname patterns to credential sets
3. **Environment variable priority** — Always prefer env vars over wp_options (current partial approach)
4. **Post-copy script** — WP-CLI script to reset environment-specific options after database copy
5. **Separate credential storage** — Keep credentials outside database entirely (env vars only)

Key considerations:
- WP Engine supports environment variables per environment
- wp-config.php is per-environment on WP Engine
- wp_options table is copied with database
- Need to protect both API creds AND campaign/component IDs
