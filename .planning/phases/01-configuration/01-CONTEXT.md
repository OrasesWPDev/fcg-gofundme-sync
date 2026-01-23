# Phase 1: Configuration - Context

**Gathered:** 2026-01-23
**Status:** Ready for planning

<domain>
## Phase Boundary

Add template campaign setting and fundraising goal field. Admin can configure:
1. Template campaign ID (validated against Classy API)
2. Per-fund fundraising goal

This phase is infrastructure for Phase 2 (Campaign Push Sync). No campaigns are created in this phase — only the configuration that enables creation.

</domain>

<decisions>
## Implementation Decisions

### Settings Placement
- Add template campaign ID to **existing FCG GoFundMe Sync settings page** in `class-admin-ui.php`
- Include helper link: "Find campaign ID in Classy →"
- After valid ID saved, display campaign name: "Template: [Campaign Name]"
- Show status indicator: API connection status and template validation state

### Goal Field Integration
- Add fundraising goal field to **existing sync status meta box** on fund edit screen
- Currency-formatted input: $ prefix, comma formatting, validates as currency
- Goal is optional — campaigns can be created without a goal
- Goal is always editable (changes push to Classy on save)
- Store as integer in post meta: `_gofundme_fundraising_goal`

### Template Validation Behavior
- **Invalid ID**: Block save, show red error message
- **Valid ID**: Save and show campaign name for confirmation
- **API unreachable**: Save with warning, schedule background re-validation via WP-Cron
- **Background re-validation fails**: Show admin notice banner (dismissible) on admin pages

### Testing Approach (Hybrid)
- Claude verifies data correctness via WP-CLI/SSH (post meta, options)
- User verifies visual elements (field appears, validation messages display)
- Screenshots can be shared for guidance when issues arise

### Claude's Discretion
- Exact error message wording
- Admin notice styling and placement
- Settings page section ordering
- Background validation timing (within 15-minute cron window)

</decisions>

<specifics>
## Specific Ideas

- Status indicator should show both "API Connected" and "Template Valid" states
- Helper link should open Classy in new tab
- Currency input should accept both "5000" and "5,000" as valid input

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 01-configuration*
*Context gathered: 2026-01-23*
