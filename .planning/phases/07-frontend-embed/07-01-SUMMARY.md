---
phase: 07-frontend-embed
plan: 01
subsystem: ui
tags: [classy, embed, wordpress, theme, javascript]

# Dependency graph
requires:
  - phase: 06-master-campaign-integration
    provides: Master campaign ID and component ID settings
provides:
  - Theme template with Classy embed div
  - Designation pre-selection via URL parameter
  - Graceful fallback for unconfigured funds
affects: [07-02-testing, deployment]

# Tech tracking
tech-stack:
  added: []
  patterns: [Classy SDK embed div format, URL parameter injection via history.replaceState]

key-files:
  created: [docs/theme-fund-form-embed.md]
  modified: [wp-content/themes/community-foundation/fund-form.php]

key-decisions:
  - "Use history.replaceState() for URL parameter injection (non-disruptive, no page reload)"
  - "Implement graceful fallback message when configuration incomplete"
  - "Document theme file change in plugin repo for deployment tracking"

patterns-established:
  - "Classy embed format: <div id='{component_id}' classy='{campaign_id}'></div>"
  - "Designation pre-selection: ?designation={id} URL parameter read by Classy SDK"

# Metrics
duration: 3min
completed: 2026-01-29
---

# Phase 07 Plan 01: Frontend Embed Summary

**Fund pages now render Classy donation embed with automatic designation pre-selection via URL parameter injection**

## Performance

- **Duration:** 3 min
- **Started:** 2026-01-29T17:46:45Z
- **Completed:** 2026-01-29T17:49:41Z
- **Tasks:** 1
- **Files modified:** 2 (1 theme file, 1 documentation file)

## Accomplishments
- Replaced legacy Acceptiva donation form with Classy embedded donation form
- Implemented designation pre-selection via JavaScript URL parameter injection
- Added graceful fallback for funds without designation or configuration
- Documented theme file changes for deployment tracking

## Task Commits

Each task was committed atomically:

1. **Task 1: Replace fund-form.php with Classy embed** - `d366973` (feat)

**Plan metadata:** (pending)

## Files Created/Modified

**Created:**
- `docs/theme-fund-form-embed.md` - Complete documentation of theme file change, deployment instructions, testing checklist

**Modified:**
- `wp-content/themes/community-foundation/fund-form.php` - Theme template file (modified in Local Sites installation)

## Decisions Made

**1. Use history.replaceState() for URL parameter injection**
- **Rationale:** Non-disruptive method that adds ?designation={id} without page reload
- **Execution:** JavaScript checks if parameter exists before adding
- **Timing:** Runs before Classy SDK processes the embed div

**2. Document theme changes in plugin repository**
- **Rationale:** Theme file is outside plugin repo, needs deployment tracking
- **Implementation:** Created docs/theme-fund-form-embed.md with full deployment instructions
- **Benefit:** Clear record of theme changes tied to plugin version 2.3.0

**3. Implement graceful fallback message**
- **Rationale:** Handle funds that don't have designation or missing plugin configuration
- **Message:** "Online donations for this fund are coming soon. Please contact us to make a donation."
- **User experience:** Informative, actionable, not broken-looking

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - implementation straightforward.

## User Setup Required

**Theme file deployment needed.** The fund-form.php file must be deployed to:

**Staging:**
```bash
scp fund-form.php frederickc2stg@frederickc2stg.ssh.wpengine.net:~/sites/frederickc2stg/wp-content/themes/community-foundation/
```

**Production:**
```bash
scp fund-form.php frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/themes/community-foundation/
```

**Prerequisites:**
1. Plugin settings must be configured (master campaign ID and component ID)
2. Classy WordPress plugin must be active
3. Classy SDK must be loaded on fund pages

See `docs/theme-fund-form-embed.md` for complete deployment and testing instructions.

## Implementation Details

### Template Structure

The new fund-form.php template:

1. **Retrieves configuration:**
   - `$designation_id` from post meta `_gofundme_designation_id`
   - `$master_campaign_id` from option `fcg_gofundme_master_campaign_id`
   - `$master_component_id` from option `fcg_gofundme_master_component_id`

2. **Conditional rendering:**
   - If all three values present: Render Classy embed div
   - If any value missing: Show fallback message

3. **Classy embed div:**
   ```html
   <div id="{component_id}" classy="{campaign_id}"></div>
   ```

4. **Designation pre-selection:**
   ```javascript
   if (!window.location.search.includes('designation=')) {
     window.history.replaceState({}, '',
       window.location.href +
       (window.location.search ? '&' : '?') +
       'designation=' + designation_id
     );
   }
   ```

5. **Security:**
   - `esc_attr()` for HTML attributes
   - `esc_js()` for JavaScript strings

### Removed Legacy Code

All Acceptiva donation form code removed:
- `<form class="donate-form">` element
- Hidden input: `js-product-name`
- Amount input: `js-product-amount`
- Add to cart button: `js-add-to-cart`
- Cart title: `js-cart-title`
- Template part: `get_template_part('fund-cart')`

## Integration Points

**With Plugin:**
- Reads master campaign ID from plugin settings
- Reads master component ID from plugin settings
- Reads designation ID from post meta (set by sync handler)

**With Classy SDK:**
- Classy WordPress plugin loads SDK on page
- SDK processes `classy="{id}"` attribute on div
- SDK reads `?designation={id}` URL parameter for pre-selection

## Testing Strategy

Manual testing required after deployment:

1. **Test with configured fund:**
   - Visit fund page with designation ID
   - Verify embed div appears
   - Verify designation parameter in URL
   - Verify fund pre-selected in dropdown
   - Complete test donation

2. **Test with unconfigured fund:**
   - Visit fund page without designation ID
   - Verify fallback message appears
   - Verify contact link works

3. **Browser console:**
   - Check for JavaScript errors
   - Verify no SDK loading errors

## Known Limitations

1. **Theme deployment separate from plugin:** This file must be deployed to the theme directory, not the plugin directory.

2. **Default designation behavior:** Each synced fund briefly becomes the campaign's default designation in Classy. Manually reset in Classy UI if needed.

3. **SDK dependency:** Requires Classy WordPress plugin active and SDK loaded on page.

## Next Phase Readiness

**Ready for testing phase:**
- Embed implementation complete
- Documentation created
- Deployment instructions provided

**Blockers:**
- None

**Concerns:**
- Theme file deployment is manual (not part of plugin deployment)
- Requires Classy WordPress plugin configuration on live site
- Testing should verify Classy SDK loads correctly

**Recommended next steps:**
1. Deploy theme file to staging
2. Configure plugin settings (master campaign ID and component ID)
3. Verify Classy WordPress plugin active and configured
4. Test with sample fund page
5. Validate designation pre-selection works
6. Test fallback message on unconfigured fund

---
*Phase: 07-frontend-embed*
*Completed: 2026-01-29*
