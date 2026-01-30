---
phase: 07-frontend-embed
plan: 02
subsystem: deployment
tags: [staging, verification, classy-sdk, modal-limitation]

# Dependency graph
requires:
  - phase: 07-01
    provides: Classy embed implementation in fund-form.php
provides:
  - Verified working Classy embed on staging
  - Documented modal incompatibility and workaround
  - Updated archive page with direct links
affects: [production-deployment]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Direct fund page links instead of modals"]

key-files:
  created: [docs/classy-technical-questions.md, docs/client-fund-page-changes.md]
  modified: [archive-funds.php, docs/theme-fund-form-embed.md]

key-decisions:
  - "Disable Bootstrap modals on archive page due to Classy SDK incompatibility"
  - "Change 'Learn More' to 'Give Now' with direct fund page links"
  - "Keep modal code commented (not deleted) for future reference"
  - "Schedule Classy meeting to discuss alternative solutions"

patterns-established:
  - "Classy SDK custom elements fail inside Bootstrap modals"
  - "Direct fund page links as workaround for modal limitation"

# Metrics
duration: ~2hr (including debugging)
completed: 2026-01-29
---

# Phase 07 Plan 02: Deploy and Verify Summary

**Staging deployment revealed Classy SDK incompatibility with Bootstrap modals. Implemented workaround using direct fund page links.**

## Performance

- **Duration:** ~2 hours (including debugging)
- **Started:** 2026-01-29
- **Completed:** 2026-01-29
- **Tasks:** 4 (3 auto + 1 checkpoint)
- **Files modified:** 3 (archive-funds.php, 2 docs files)

## Accomplishments

- Deployed fund-form.php to WP Engine staging
- Verified Classy embed loads correctly on single fund pages
- Discovered SDK incompatibility with Bootstrap modals
- Implemented workaround: disabled modals, direct fund page links
- Updated archive-funds.php with "Give Now" links
- Documented technical limitation and workaround
- Created client explanation document

## Task Commits

Work was done across multiple sessions, commits include:
- `d366973` - feat(07-01): implement Classy embed in fund-form.php
- `a12e6f9` - docs(07-01): complete frontend embed plan
- `206ac06` - docs(07-02): deploy fund-form.php to staging
- `df82605` - docs(07-02): identify test fund for embed verification
- `e321c50` - docs(07-02): update theme docs for modal fix

## Critical Discovery: Modal Incompatibility

**Issue Found:** Classy SDK cannot work inside Bootstrap modals.

**Technical Details:**
- Classy SDK uses custom HTML elements (`<cl-donation-form>`)
- Initial form renders correctly inside Bootstrap modal
- When user clicks "Donate", Classy tries to open its payment modal
- Browser throws: `Failed to construct 'HTMLElement': Illegal constructor`
- Payment flow breaks completely

**Root Cause:** Classy SDK architecture conflict with Bootstrap's modal event handling and DOM manipulation.

**Not Fixable:** This is a fundamental SDK limitation, not a JavaScript workaround opportunity.

## Workaround Implemented

**Archive Page (`archive-funds.php`):**
- Disabled "Give Now" modal triggers
- Changed fund title to direct link (no modal)
- Changed "Learn More" to "Give Now" direct link
- Commented out `get_template_part('fund-modal')` call
- Original code preserved with explanatory comments

**Single Fund Pages:**
- Classy embed works perfectly (no Bootstrap modal involved)
- URL parameter injection (`?designation={id}`) works correctly
- Pre-selection of designation in dropdown works

## Files Created/Modified

**Created:**
- `docs/classy-technical-questions.md` - Questions for Classy meeting
- `docs/client-fund-page-changes.md` - Client explanation of changes

**Modified:**
- `archive-funds.php` (theme file) - Modal disabled, direct links added
- `docs/theme-fund-form-embed.md` - Updated with modal fix documentation

## Verification Results

**Single Fund Page:**
- [x] Classy donation form loads
- [x] URL shows `?designation={id}` parameter
- [x] Correct fund pre-selected in dropdown
- [x] No JavaScript console errors
- [x] Payment flow works (test donation successful)

**Archive Page:**
- [x] Fund titles link directly to fund pages
- [x] "Give Now" links to fund pages
- [x] No modal popups appear
- [x] User flow redirects to working donation page

**Fallback:**
- [x] Funds without designation show "coming soon" message

## Decisions Made

**1. Disable modals rather than remove**
- **Rationale:** Keep original code for potential future fix
- **Implementation:** Comments with clear explanations
- **Benefit:** Easy rollback if Classy fixes SDK

**2. Direct fund page links**
- **Rationale:** Users can still donate, just requires page navigation
- **UX Impact:** One extra click, but donation flow works
- **Benefit:** 100% functional donation path

**3. Schedule Classy meeting**
- **Rationale:** Explore if Classy has alternative embed options
- **Scheduled:** 2026-01-29 4pm
- **Questions prepared:** See docs/classy-technical-questions.md

## Success Criteria Assessment

| Criterion | Status | Notes |
|-----------|--------|-------|
| Single fund page displays Classy embed | PASS | Works perfectly |
| Modal popup displays Classy embed | FAIL | SDK incompatibility |
| Correct designation pre-selected | PASS | URL parameter works |
| Fallback for funds without designation | PASS | Message displays |

**Revised success (after workaround):**
| Criterion | Status | Notes |
|-----------|--------|-------|
| Single fund page displays Classy embed | PASS | Works perfectly |
| Archive page provides donation path | PASS | Direct links work |
| Correct designation pre-selected | PASS | URL parameter works |
| Fallback for funds without designation | PASS | Message displays |

## Remaining Work

**Other templates need same modal removal:**
- `search.php` (~line 203)
- `taxonomy-fund-category.php` (~line 44)
- `template-flexible.php` (~line 964)

These are documented in `docs/theme-fund-form-embed.md` for future deployment.

## Issues Logged

**Known Issue: Modal Incompatibility**
- **Severity:** Medium (workaround exists)
- **Status:** Documented, workaround deployed
- **Next Steps:** ~~Discuss with Classy for potential SDK fix~~ Discussed (see below)

## Classy Developer Call (2026-01-29)

**Attendees:** Chad Diaz, Luke Dringoli (Classy), Jon Bierma (Classy)

**Key Confirmations:**
- ✅ Architecture (single master campaign + designations) validated as correct approach
- ✅ Direct fund page links workaround is appropriate solution for modal issue
- ✅ DELETE endpoint removes designation from campaign list AND designations entirely
- ✅ Deactivate (PUT with `is_active: false`) leaves designation in campaign list

**Classy Feedback on Designation ID Persistence:**
Jon emphasized that designation ID must persist through all donation entry points. This concern is **specific to the modal** (fund-modal.php) which lacks post context when triggered from archive pages.

**Single fund page (fund-form.php):** No persistence issue — JavaScript injects `?designation={id}` on page load with full post context available.

**Luke's Recommendations for Future:**
1. Use Classy "button link" version for quick donate (opens Classy modal directly, skips Bootstrap)
2. Theme refactor to consolidate PHP templates into custom post type
3. These are deferred to Phase 8.2 (post-MVP)

**Call Recording:** Available in tldv (meeting ID: 697bcdc55095a70013422fd8)

## Next Phase Readiness

**Ready for Phase 8 (Admin UI):**
- Frontend embed complete and working
- Workaround documented and deployed
- No blockers for admin UI phase

**Production deployment prerequisites:**
1. Deploy plugin v2.3.0
2. Configure plugin settings (master campaign ID, component ID)
3. Deploy theme files (fund-form.php, archive-funds.php)
4. Test on production before going live

---
*Phase: 07-frontend-embed*
*Plan: 02*
*Completed: 2026-01-29*
