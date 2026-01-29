# Requirements: FCG GoFundMe Pro Sync

**Defined:** 2026-01-22
**Updated:** 2026-01-29 (Phases 5-7 complete)
**Core Value:** When a fund is published in WordPress, the designation is automatically created in Classy and linked to the master campaign — no manual data entry required.

## v1 Requirements

Requirements for single master campaign architecture. Each maps to roadmap phases.

### Configuration

- [x] **CONF-01**: Admin can configure template campaign ID for duplication
- [x] **CONF-02**: fundraising_goal ACF field added to funds

### Code Cleanup (Post-Pivot)

- [x] **CLEAN-01**: Remove obsolete campaign sync methods from sync handler
- [x] **CLEAN-02**: Remove campaign-related constants and post meta logic
- [x] **CLEAN-03**: Remove unused API client campaign lifecycle methods
- [x] **CLEAN-04**: Update CLAUDE.md to reflect new architecture
- [x] **CLEAN-05**: Bump plugin version to mark architecture change

### Master Campaign Integration

- [x] **MASTER-01**: Rename "Template Campaign ID" setting to "Master Campaign ID"
- [x] **MASTER-02**: Add "Master Component ID" setting for embed code
- [x] **MASTER-03**: After designation creation, link to master campaign via API
- [x] **MASTER-04**: New designations appear in master campaign dropdown

### Inbound Sync (Classy → WordPress)

- [x] **SYNC-01**: Donation totals are polled from Classy every 15 minutes
- [x] **SYNC-02**: Campaign status is polled and reflected in WordPress
- [x] **SYNC-03**: Goal progress percentage is calculated and stored
- [x] **SYNC-04**: Post meta updated with donation data without triggering outbound sync

### Admin UI (Optional)

- [ ] **ADMN-01**: Designation ID displayed in fund edit meta box
- [ ] **ADMN-02**: Donation total displayed in fund edit meta box (from inbound sync)
- [ ] **ADMN-03**: Last sync timestamp displayed
- [ ] **ADMN-04**: Manual "Sync Now" button for individual fund

### Frontend Embed Integration

- [x] **EMBD-01**: Fund single template displays Classy donation embed
- [x] **EMBD-02**: Embed uses master campaign with `?designation={id}` parameter
- [x] **EMBD-03**: Legacy donation form replaced/removed (modal workaround: direct links)
- [x] **EMBD-04**: Graceful fallback when designation ID not present

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Advanced Sync

- **SYNC-05**: Real-time sync via webhooks (when Classy supports)
- **SYNC-06**: Individual donation records (not just totals)
- **SYNC-07**: Configurable polling interval (currently hardcoded 15 min)

### Admin Enhancements

- **ADMN-05**: Bulk sync from admin list table
- **ADMN-06**: Sync status column in funds list
- **ADMN-07**: Conflict resolution UI

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Per-fund campaigns | Architecture pivot moved to single master campaign |
| Real-time webhooks | Classy doesn't offer webhook integration |
| Individual donation records | Only totals needed for current use case |
| Local development environment | All testing on WP Engine staging |
| Multiple designations per fund | 1:1 relationship by design |

## Archived Requirements (Pre-Pivot)

The following requirements were completed but made obsolete by the architecture pivot (2026-01-28):

| Requirement | Status | Notes |
|-------------|--------|-------|
| CAMP-01 through CAMP-06 | Archived | Per-fund campaign creation removed |
| STAT-01 through STAT-03 | Archived | Campaign status management removed |
| MIGR-01 through MIGR-05 | Archived | Bulk migration no longer needed |

Code implementing these requirements will be removed in Phase 5 (Code Cleanup).

## Traceability

Which phases cover which requirements. Updated after architecture pivot.

| Requirement | Phase | Status |
|-------------|-------|--------|
| CONF-01 | Phase 1 | Complete |
| CONF-02 | Phase 1 | Complete |
| SYNC-01 | Phase 4 | Complete |
| SYNC-02 | Phase 4 | Complete |
| SYNC-03 | Phase 4 | Complete |
| SYNC-04 | Phase 4 | Complete |
| CLEAN-01 | Phase 5 | Complete |
| CLEAN-02 | Phase 5 | Complete |
| CLEAN-03 | Phase 5 | Complete |
| CLEAN-04 | Phase 5 | Complete |
| CLEAN-05 | Phase 5 | Complete |
| MASTER-01 | Phase 6 | Complete |
| MASTER-02 | Phase 6 | Complete |
| MASTER-03 | Phase 6 | Complete |
| MASTER-04 | Phase 6 | Complete |
| EMBD-01 | Phase 7 | Complete |
| EMBD-02 | Phase 7 | Complete |
| EMBD-03 | Phase 7 | Complete (with modal workaround) |
| EMBD-04 | Phase 7 | Complete |
| ADMN-01 | Phase 8 | Pending |
| ADMN-02 | Phase 8 | Pending |
| ADMN-03 | Phase 8 | Pending |
| ADMN-04 | Phase 8 | Pending |

**Coverage:**
- v1 requirements: 23 total (post-pivot)
- Mapped to phases: 23
- Unmapped: 0 ✓

---
*Requirements defined: 2026-01-22*
*Last updated: 2026-01-28 after architecture pivot*
