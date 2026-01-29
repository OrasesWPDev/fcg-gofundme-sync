# Phase 6: Master Campaign Integration - Context

**Gathered:** 2026-01-29
**Status:** Ready for planning

<domain>
## Phase Boundary

Configure master campaign settings in WordPress admin and automatically link/unlink designations to the master campaign when funds are published, unpublished, trashed, or restored. This phase handles the API integration only — frontend embed is Phase 7.

</domain>

<decisions>
## Implementation Decisions

### Linking Behavior (Add/Remove from Campaign Group)
- **On publish:** Add designation to master campaign's active designation group
- **On unpublish/draft:** Remove designation from campaign group (not from Classy entirely)
- **On trash:** Remove designation from campaign group
- **On restore from trash:** Re-add designation to campaign group automatically
- Designation stays in Classy regardless (is_active flag managed separately)
- Goal: Unpublished/trashed funds should NOT appear in donation dropdown

### Idempotency
- Check if designation is already in campaign group before attempting to add
- Avoid duplicate add attempts for existing funds

### Error Handling
- Retry 2-3 times silently on API failure
- Log all failures for debugging
- Show admin notice if linking fails after retries (so admin knows to investigate)
- Do NOT block fund publish if linking fails (designation creation is the critical path)

### Settings UI
- Rename "Template Campaign ID" to new label (Claude's discretion on exact wording)
- Validate Campaign ID exists in Classy when settings are saved
- Component ID deferred to Phase 7 (not needed for API linking)

### Claude's Discretion
- Exact setting label wording ("Master Campaign ID" vs "Primary Campaign ID")
- Number of retry attempts (2-3)
- Admin notice wording and styling
- Logging verbosity

</decisions>

<specifics>
## Specific Ideas

- Master Campaign ID is 764694 (already created in Classy)
- Component ID mKAgOmLtRHVGFGh_eaqM6 is for Phase 7 frontend, not needed here
- Luke Dringoli confirmed `PUT /campaigns/{id}` with `{"designation_id": ...}` adds to group
- 856 designations already in group (manually added), 5 pending — new logic should handle the 5 naturally during testing

</specifics>

<deferred>
## Deferred Ideas

- Component ID setting — Phase 7 (Frontend Embed)
- Bulk "sync all" admin button — Phase 8 (Admin UI) if needed
- Delete designation from Classy entirely — not planned (keep designations, just unlink from group)

</deferred>

---

*Phase: 06-master-campaign-integration*
*Context gathered: 2026-01-29*
