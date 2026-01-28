# Phase 5: Code Cleanup - Context

**Gathered:** 2026-01-28
**Status:** Ready for planning

<domain>
## Phase Boundary

Remove obsolete campaign duplication and status management code that became dead code after the architecture pivot to single master campaign. Preserve all designation sync functionality, OAuth2 infrastructure, and inbound sync polling.

</domain>

<decisions>
## Implementation Decisions

### Removal Scope
- **Thorough sweep** — Find and remove ALL campaign-related dead code, not just the explicitly listed methods
- Database post meta: Claude's discretion — handle based on whether orphaned data causes issues
- API client methods: Claude's discretion — remove if clearly unused, keep if useful for debugging
- WordPress hooks: Remove campaign-specific hooks only, **document any hooks that may become unused** after removal

### Verification Approach
- **Both automated and manual testing**
- Automated: PHPUnit tests with mocked API to verify designation sync logic
- Manual: Deploy to staging, verify designation sync works end-to-end
- Manual test scope: Claude's discretion on which lifecycle actions to test (publish, update, trash, etc.)
- **Verify inbound sync polling** still runs and updates donation totals
- Activation check: Verify plugin activates without PHP fatal errors (no deep admin page check needed)

### Code Organization
- Structure changes: Claude's discretion — do what makes code cleaner without over-engineering
- **Update CLAUDE.md** to reflect new architecture and remove campaign references
- **Bump plugin version** to mark the architecture change (e.g., increment to next minor version)
- Code style: Claude's discretion — focus on removal, don't get sidetracked by style improvements

### Claude's Discretion
- Database migration for orphaned post meta
- API client campaign method retention
- Test scope for staging verification
- Light restructuring after cleanup
- PHPDoc updates

</decisions>

<specifics>
## Specific Ideas

- Document any hooks that become unused after campaign code removal — don't silently leave them
- Version bump signals the architecture pivot to anyone tracking plugin versions

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 05-code-cleanup*
*Context gathered: 2026-01-28*
